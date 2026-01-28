<?php

namespace App\Services;

use Spatie\Ssh\Ssh;

class V2rayService
{
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
            ->usePrivateKey(config('yap.ssh_private_key_path'));
    }

    public function addOrRemoveUsers(array $users)
    {
        // 1. read current json conf
        $current_config = json_decode($this->ssh->execute('cat /usr/local/etc/v2ray/config.json')->getOutput());

        // If config is empty, invalid, or missing required structure, use the demo config as template
        $is_invalid_config = is_null($current_config)
            || (is_object($current_config) && empty((array) $current_config))
            || ! isset($current_config->inbounds[0]->settings);

        if ($is_invalid_config) {
            logger()->driver('job')->log(
                'info',
                "[V2rayService] Empty or invalid config detected, using demo config as template: $this->internal_server"
            );
            $demo_config_path = resource_path('v2ray-conf-demo.json');
            $current_config = json_decode(file_get_contents($demo_config_path));
            if (is_null($current_config)) {
                logger()->driver('job')->log(
                    'warning',
                    "[V2rayService] Failed to read demo V2ray config: $demo_config_path"
                );

                return;
            }
        }

        // 2. compare with given users
        $current_users = $current_config->inbounds[0]->settings->clients ?? [];
        if (array_column($current_users, 'id') == array_column($users, 'id')) {
            logger()->driver('job')->log(
                'info',
                "[V2rayService] No need to update V2ray users: $this->internal_server"
            );

            return;
        }

        // 3. write back to json conf
        $current_config->inbounds[0]->settings->clients = $users;
        // backup first
        $this->ssh->execute('cp /usr/local/etc/v2ray/config.json /usr/local/etc/v2ray/config.'.now()->format('YmdHis').'.json');
        $this->ssh->execute('echo \''.json_encode($current_config).'\' > /usr/local/etc/v2ray/config.json');
        $this->ssh->execute('systemctl restart v2ray');
        logger()->driver('job')->log(
            'info',
            "[V2rayService] Updated V2ray users: $this->internal_server"
        );
    }

    public function getStats($reset = false)
    {
        $command = '/usr/local/bin/v2ray api stats -s localhost:10085 -json';
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
}
