<?php

namespace App\Services;

use JsonException;
use RuntimeException;
use Spatie\Ssh\Ssh;
use stdClass;
use Symfony\Component\Process\Process;
use Throwable;
use UnexpectedValueException;

class V2rayService
{
    private const CONFIG_PATH = '/usr/local/etc/v2ray/config.json';

    private const LOCK_PATH = '/run/lock/yap-v2ray-config.lock';

    private Ssh $ssh;

    public function __construct(
        private readonly string $internal_server,
        ?Ssh $ssh = null,
    ) {
        [$host, $port] = $this->parseServer($this->internal_server);

        $this->ssh = $ssh ?? Ssh::create(config('yap.ssh_user'), $host, $port)
            ->disableStrictHostKeyChecking()
            ->usePrivateKey(config('yap.ssh_private_key_path'))
            ->addExtraOption('-o ConnectTimeout=5')
            ->setTimeout(60);
    }

    public function addOrRemoveUsers(array $users): void
    {
        $read_process = $this->ssh->execute('cat '.self::CONFIG_PATH);
        $this->assertProcessSucceeded($read_process, 'read current V2ray configuration');
        $current_config_json = $read_process->getOutput();
        $current_config = $this->decodeConfig($current_config_json);

        $current_users = array_map(
            fn (mixed $user): mixed => is_object($user) ? get_object_vars($user) : $user,
            $current_config->inbounds[0]->settings->clients ?? [],
        );
        if ($current_users === $users) {
            logger()->driver('job')->info('[V2rayService] V2ray users are already current.', [
                'server' => $this->internal_server,
            ]);

            return;
        }

        $current_config->inbounds[0]->settings->clients = $users;
        $encoded_config = json_encode($current_config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $deployment_id = bin2hex(random_bytes(16));
        $remote_temp_path = "/tmp/yap-v2ray-$deployment_id.json";
        $remote_restore_path = "/tmp/yap-v2ray-restore-$deployment_id.json";
        $backup_path = self::CONFIG_PATH.".yap-backup-$deployment_id";
        $expected_config_hash = hash('sha256', $current_config_json);
        $local_temp_path = null;

        try {
            $local_temp_path = $this->createLocalConfigFile($encoded_config);
            $upload_process = $this->ssh->upload($local_temp_path, $remote_temp_path);
            $this->assertProcessSucceeded($upload_process, 'upload V2ray configuration');

            $deploy_process = $this->ssh->execute($this->deploymentCommand(
                $remote_temp_path,
                $remote_restore_path,
                $backup_path,
                $expected_config_hash,
            ));
            $this->assertDeploymentSucceeded($deploy_process);

            logger()->driver('job')->info('[V2rayService] Updated V2ray users.', [
                'server' => $this->internal_server,
            ]);
        } finally {
            if ($local_temp_path !== null && is_file($local_temp_path)) {
                @unlink($local_temp_path);
            }

            $this->cleanupRemoteFile($remote_temp_path);
            $this->cleanupRemoteFile($remote_restore_path);
        }
    }

    public function getStats(bool $reset = false): array
    {
        $command = '/usr/local/bin/v2ray api stats -s localhost:10085 -json';
        if ($reset) {
            $command .= ' -reset';
        }

        $process = $this->ssh->execute($command);
        $this->assertProcessSucceeded($process, 'read V2ray stats');

        try {
            $stat = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            logger()->driver('job')->warning('[V2rayService] Failed to decode V2ray stats.', [
                'server' => $this->internal_server,
            ]);

            return [];
        }

        if (! is_array($stat) || empty($stat['stat']) || ! is_array($stat['stat'])) {
            return [];
        }

        $result = [];
        foreach ($stat['stat'] as $item) {
            if (! isset($item['name'], $item['value'])) {
                continue;
            }

            $parts = explode('>>>', $item['name']);
            if (count($parts) !== 4) {
                continue;
            }

            [$type, $name, , $direction] = $parts;
            $result[$type][$name][$direction] = $item['value'];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseServer(string $server): array
    {
        $host = $server;
        $port = 22;

        if (preg_match('/^\[([^]]+)](?::([0-9]+))?$/', $server, $matches) === 1) {
            $host = $matches[1];
            $port = isset($matches[2]) ? (int) $matches[2] : 22;
        } elseif (substr_count($server, ':') === 1) {
            [$host, $raw_port] = explode(':', $server, 2);
            $port = ctype_digit($raw_port) ? (int) $raw_port : 0;
        }

        $is_ip = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $is_hostname = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        throw_if(! $is_ip && ! $is_hostname, UnexpectedValueException::class, 'Invalid V2ray server host.');
        throw_if($port < 1 || $port > 65535, UnexpectedValueException::class, 'Invalid V2ray server port.');

        return [$host, $port];
    }

    private function decodeConfig(string $config): stdClass
    {
        try {
            $decoded_config = json_decode($config, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The current V2ray configuration is not valid JSON.', previous: $exception);
        }

        throw_if(! $decoded_config instanceof stdClass, UnexpectedValueException::class, 'The current V2ray configuration has an invalid structure.');

        $inbounds = $decoded_config->inbounds ?? null;
        $first_inbound = is_array($inbounds) ? ($inbounds[0] ?? null) : null;
        $settings = $first_inbound instanceof stdClass ? ($first_inbound->settings ?? null) : null;
        $clients = $settings instanceof stdClass ? ($settings->clients ?? []) : null;

        throw_if(! $settings instanceof stdClass, UnexpectedValueException::class, 'The current V2ray configuration is missing inbound settings.');
        throw_if(! is_array($clients), UnexpectedValueException::class, 'The current V2ray configuration has invalid clients.');

        return $decoded_config;
    }

    private function createLocalConfigFile(string $config): string
    {
        $path = tempnam(sys_get_temp_dir(), 'yap-v2ray-');
        throw_if($path === false, RuntimeException::class, 'Unable to create a local V2ray configuration file.');

        if (! chmod($path, 0600) || file_put_contents($path, $config, LOCK_EX) === false) {
            @unlink($path);

            throw new RuntimeException('Unable to write the local V2ray configuration file.');
        }

        return $path;
    }

    private function deploymentCommand(
        string $remote_temp_path,
        string $remote_restore_path,
        string $backup_path,
        string $expected_config_hash,
    ): string {
        return 'flock -w 30 -E 75 '.self::LOCK_PATH." sh -c '"
            .'current_hash=$(sha256sum '.self::CONFIG_PATH.'); '
            ."if [ \"\${current_hash%% *}\" != $expected_config_hash ]; then exit 19; fi; "
            ."if ! /usr/local/bin/v2ray test -config $remote_temp_path >/dev/null 2>&1; then exit 20; fi; "
            .'if ! cp --preserve=mode,ownership '.self::CONFIG_PATH." $backup_path; then exit 21; fi; "
            ."if ! chmod 644 $remote_temp_path; then exit 22; fi; "
            ."if ! mv -f $remote_temp_path ".self::CONFIG_PATH.'; then exit 23; fi; '
            .'if systemctl restart v2ray >/dev/null 2>&1 && systemctl is-active --quiet v2ray; then exit 0; fi; '
            ."if ! cp --preserve=mode,ownership $backup_path $remote_restore_path; then exit 30; fi; "
            ."if ! chmod 644 $remote_restore_path; then exit 30; fi; "
            ."if ! mv -f $remote_restore_path ".self::CONFIG_PATH.'; then exit 30; fi; '
            .'if ! systemctl restart v2ray >/dev/null 2>&1; then exit 31; fi; '
            ."if ! systemctl is-active --quiet v2ray; then exit 32; fi; exit 40'";
    }

    private function assertDeploymentSucceeded(Process $process): void
    {
        $process->getErrorOutput();

        if ($process->isSuccessful()) {
            return;
        }

        $message = match ($process->getExitCode()) {
            19 => 'The V2ray configuration changed during deployment.',
            20 => 'V2ray rejected the uploaded configuration.',
            21, 22, 23 => 'V2ray configuration deployment failed.',
            30, 31, 32 => 'V2ray configuration rollback failed.',
            40 => 'V2ray failed to start with the new configuration; the previous configuration was restored.',
            75 => 'Timed out waiting for the V2ray deployment lock.',
            default => 'V2ray configuration deployment failed.',
        };

        throw new RuntimeException("$message (exit code {$process->getExitCode()}).");
    }

    private function assertProcessSucceeded(Process $process, string $operation): void
    {
        $process->getErrorOutput();

        if (! $process->isSuccessful()) {
            $exit_code = $process->getExitCode();
            $exit_code_description = $exit_code === null ? 'unknown' : (string) $exit_code;

            throw new RuntimeException("Unable to $operation (exit code $exit_code_description).");
        }
    }

    private function cleanupRemoteFile(string $remote_temp_path): void
    {
        try {
            $process = $this->ssh->execute("rm -f -- $remote_temp_path");
            $process->getErrorOutput();

            if (! $process->isSuccessful()) {
                logger()->driver('job')->warning('[V2rayService] Failed to clean up a remote temporary file.', [
                    'server' => $this->internal_server,
                ]);
            }
        } catch (Throwable) {
            logger()->driver('job')->warning('[V2rayService] Failed to clean up a remote temporary file.', [
                'server' => $this->internal_server,
            ]);
        }
    }
}
