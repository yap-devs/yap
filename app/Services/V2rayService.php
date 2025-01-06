<?php

namespace App\Services;


use Illuminate\Support\Facades\Process;
use Spatie\Ssh\Ssh;

class V2rayService
{
    private string $prefix;
    private Ssh $ssh;

    public function __construct(
        private readonly string $internal_server
    )
    {
        $this->prefix = config('v2ray.v2bridge.path') . ' handler -s ' . $this->internal_server;

        $parsed = explode(':', $this->internal_server);
        $host = $parsed[0];
        $port = 22;
        if (count($parsed) > 1) {
            $port = (int)$parsed[1];
        }

        $this->ssh = Ssh::create(config('yap.ssh_user'), $host, $port)
            ->disableStrictHostKeyChecking()
            ->usePrivateKey(config('yap.ssh_private_key_path'));
    }

    /**
     * Adds multiple users to the V2ray service.
     *
     * @param array $users An array of users to be added.
     */
    public function addUsers(array $users)
    {
        // 1. read current json conf
        $current_config = json_decode($this->ssh->execute('cat /usr/local/etc/v2ray/config.json')->getOutput());
        if (is_null($current_config)) {
            logger()->driver('job')->log(
                'warning',
                "[V2rayService] Failed to read current V2ray config: $this->internal_server"
            );

            return;
        }
        // 2. add new users with unique check
        $current_users = $current_config->inbounds[0]->settings->clients;
        foreach ($users as $user) {
            if (in_array($user['id'], array_column($current_users, 'id'))) {
                continue;
            }
            $current_users[] = $user;
        }
        // 3. write back to json conf if changed
        if ($current_users !== $current_config->inbounds[0]->settings->clients) {
            $current_config->inbounds[0]->settings->clients = $current_users;
            $this->ssh->execute('echo \'' . json_encode($current_config) . '\' > /usr/local/etc/v2ray/config.json');

            // 4. restart v2ray service
            $this->ssh->execute('systemctl restart v2ray');
        }
    }

    /**
     * Removes multiple users from the V2ray service.
     *
     * @param array $users An array of users to be removed.
     */
    public function removeUsers(array $users)
    {
        // 1. read current json conf
        $current_config = json_decode($this->ssh->execute('cat /usr/local/etc/v2ray/config.json')->getOutput());
        if (is_null($current_config)) {
            logger()->driver('job')->log(
                'warning',
                "[V2rayService] Failed to read current V2ray config: $this->internal_server"
            );

            return;
        }
        // 2. remove users with unique check
        $current_users = $current_config->inbounds[0]->settings->clients;
        foreach ($users as $user) {
            $current_users = array_filter($current_users, fn($u) => $u->id !== $user['id']);
        }
        // 3. write back to json conf if changed
        if ($current_users !== $current_config->inbounds[0]->settings->clients) {
            $current_config->inbounds[0]->settings->clients = array_values($current_users);
            $this->ssh->execute('echo \'' . json_encode($current_config) . '\' > /usr/local/etc/v2ray/config.json');

            // 4. restart v2ray service
            $this->ssh->execute('systemctl restart v2ray');
        }
    }

    public function getStats($reset = false)
    {
        $command = config('v2ray.v2ray.path') . ' api stats -s localhost:10085 -json';
        if ($reset) {
            $command .= ' -reset';
        }

        $stat = json_decode($this->ssh->execute($command)->getOutput(), true);
        if (!$stat) {
            logger()->driver('job')->log(
                'warning',
                "[V2rayService] Failed to get V2ray stats: $this->internal_server"
            );

            return [];
        }

        $stat = $stat['stat'];
        $res = [];
        foreach ($stat as $item) {
            if (!isset($item['value'])) {
                continue;
            }
            [$type, $name, , $direction] = explode('>>>', $item['name']);
            $res[$type][$name][$direction] = $item['value'];
        }

        return $res;
    }

    /**
     * Adds a user to the application.
     *
     * @param string $email The email of the user.
     * @param string $uuid The UUID of the user.
     * @return array The decoded JSON result of adding the user.
     */
    public function addUser($email, $uuid)
    {
        $result = $this->runWithRetry($this->prefix . ' addV2rayVmessUser -e ' . $email . ' -u ' . $uuid);

        return is_null($result) ? [] : json_decode($result, true);
    }

    /**
     * Remove a user from the V2ray service.
     *
     * @param string $email The email of the user to be removed.
     * @return array The decoded JSON response from the V2ray service.
     */
    public function removeUser($email)
    {
        $result = $this->runWithRetry($this->prefix . ' removeV2rayUser -e ' . $email);

        return is_null($result) ? [] : json_decode($result, true);
    }

    /**
     * Get the statistics of the V2ray service.
     *
     * If an email is provided, it retrieves the uplink and downlink traffic stats
     * for the specified user. Otherwise, it retrieves the overall statistics for
     * the V2ray service.
     *
     * @param null $email The email of the user for whom to retrieve traffic stats.
     * @param bool $reset Whether to reset the statistics after retrieving them.
     * @return array The decoded JSON response containing the statistics.
     */
    public function stats($email = null, $reset = false)
    {
        $command = config('v2ray.v2ray.path') . ' api stats -t 10 -s ' . $this->internal_server . ' -json';

        if ($reset) {
            $command .= ' -reset';
        }

        if ($email) {
            $command .= " 'user>>>$email>>>traffic>>>uplink'";
            $command .= " 'user>>>$email>>>traffic>>>downlink'";
        }

        $result = $this->runWithRetry($command);
        if (is_null($result)) {
            return [];
        }

        $stat = json_decode($result, true);
        if (!$stat) {
            return [];
        }

        $stat = $stat['stat'];

        $res = [];
        foreach ($stat as $item) {
            if (!isset($item['value'])) {
                continue;
            }
            [$type, $name, , $direction] = explode('>>>', $item['name']);
            $res[$type][$name][$direction] = $item['value'];
        }

        return $res;
    }

    /**
     * Run a command with retry.
     *
     * Executes the given command and retries a specified number of times if it fails.
     *
     * @param string $command The command to be executed.
     * @param int $retry The maximum number of times to retry the command (default: 3).
     * @return string|null The output of the command if it succeeds, or null if it fails after all retries.
     */
    private function runWithRetry($command, $retry = 3)
    {
        $result = Process::run($command);

        $retryCount = 0;
        while ($result->failed() && $retryCount < $retry) {
            $result = Process::run($command);
            $retryCount++;
        }

        if ($result->failed()) {
            logger()->driver('job')->log(
                'warning',
                "[V2rayService] Command [$command] failed: {$result->errorOutput()}"
            );

            return null;
        }

        return $result->output();
    }
}
