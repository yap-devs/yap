<?php

namespace App\Services;

use RuntimeException;
use Spatie\Ssh\Ssh;

class V2rayService
{
    public const DEFAULT_CONFIG_PATH = '/usr/local/etc/v2ray/config.json';

    public const DEFAULT_SERVICE_NAME = 'v2ray';

    private Ssh $ssh;

    public function __construct(
        private readonly string $internal_server
    ) {
        $parsed = explode(':', $this->internal_server);
        $host = $parsed[0];
        $port = 22;
        if (count($parsed) > 1) {
            $port = (int) $parsed[1];
        }

        $this->ssh = Ssh::create(config('yap.ssh_user'), $host, $port)
            ->disableStrictHostKeyChecking()
            ->usePrivateKey(config('yap.ssh_private_key_path'))
            ->addExtraOption('-o ConnectTimeout=5')
            ->setTimeout(10);
    }

    public function syncUsersByPort(array $users_by_port): void
    {
        $editor = new V2rayConfigEditor;
        [$config, $is_template] = $this->loadConfigOrTemplate($editor);
        $changed = false;

        if ($is_template) {
            [$config, $changed] = $editor->prepareTemplateVmessInboundsForPorts($config, array_keys($users_by_port));
        }

        foreach ($users_by_port as $port => $users) {
            [$config, $found, $port_changed] = $editor->updateVmessClientsByPort($config, (int) $port, array_values($users));

            if (! $found) {
                logger()->driver('job')->log(
                    'warning',
                    "[V2rayService] No matching V2Ray vmess inbound found: $this->internal_server, port: $port"
                );
            }

            $changed = $changed || $port_changed;
        }

        if (! $changed) {
            logger()->driver('job')->log(
                'info',
                "[V2rayService] No need to update V2ray users: $this->internal_server"
            );

            return;
        }

        $this->writeConfig($editor->encode($config));
        logger()->driver('job')->log(
            'info',
            "[V2rayService] Updated V2ray users: $this->internal_server"
        );
    }

    public function readConfig(string $config_path = self::DEFAULT_CONFIG_PATH): string
    {
        $process = $this->ssh->execute('cat '.$this->shellQuote($config_path));
        if ($process->isSuccessful()) {
            return $process->getOutput();
        }

        throw new RuntimeException('Failed to read V2Ray config: '.$process->getErrorOutput());
    }

    public function writeConfig(
        string $json,
        string $config_path = self::DEFAULT_CONFIG_PATH,
        string $service_name = self::DEFAULT_SERVICE_NAME,
    ): void {
        (new V2rayConfigEditor)->decode($json);

        $encoded_config = base64_encode($json);
        $quoted_config_path = $this->shellQuote($config_path);
        $backup_path = $this->shellQuote($config_path.'.'.now()->format('YmdHis').'.json');

        $this->run(
            'test -f '.$quoted_config_path.' && cp '.$quoted_config_path.' '.$backup_path.' || true',
            'Failed to backup V2Ray config'
        );
        $this->run(
            'printf %s '.$this->shellQuote($encoded_config).' | base64 -d > '.$quoted_config_path,
            'Failed to write V2Ray config'
        );
        $this->run(
            'systemctl restart '.$this->shellQuote($service_name),
            'Failed to restart V2Ray service'
        );
    }

    public function getStats(bool $reset = false, int $api_port = 10085): array
    {
        $command = '/usr/local/bin/v2ray api stats -s localhost:'.(int) $api_port.' -json';
        if ($reset) {
            $command .= ' -reset';
        }

        $output = $this->ssh->execute($command)->getOutput();
        $stat = json_decode($output, true);
        if ($stat === null && json_last_error() !== JSON_ERROR_NONE) {
            logger()->driver('job')->log(
                'warning',
                "[V2rayService] Failed to get V2ray stats: $this->internal_server, error: ".json_last_error_msg()
                .', output: '.$output
            );

            return [];
        }
        if (! $stat) {
            // no stats, maybe nobody is using it yet
            return [];
        }

        $stat = $stat['stat'];
        $res = [];
        foreach ($stat as $item) {
            if (! isset($item['value'])) {
                continue;
            }
            [$type, $name, , $direction] = explode('>>>', $item['name']);
            $res[$type][$name][$direction] = $item['value'];
        }

        return $res;
    }

    private function loadConfigOrTemplate(V2rayConfigEditor $editor): array
    {
        try {
            return [$editor->decode($this->readConfig()), false];
        } catch (RuntimeException) {
            logger()->driver('job')->log(
                'info',
                "[V2rayService] Empty or invalid config detected, using demo config as template: $this->internal_server"
            );
        }

        $demo_config_path = resource_path('v2ray-conf-demo.json');
        $demo_config = file_get_contents($demo_config_path);
        if ($demo_config !== false) {
            return [$editor->decode($demo_config), true];
        }

        throw new RuntimeException("Failed to read demo V2Ray config: $demo_config_path");
    }

    private function run(string $command, string $message): void
    {
        $process = $this->ssh->execute($command);

        if ($process->isSuccessful()) {
            return;
        }

        throw new RuntimeException($message.': '.$process->getErrorOutput());
    }

    private function shellQuote(string $value): string
    {
        return "'".str_replace("'", "'\"'\"'", $value)."'";
    }
}
