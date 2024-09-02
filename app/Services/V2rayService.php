<?php

namespace App\Services;


use Illuminate\Support\Facades\Process;

class V2rayService
{
    private string $prefix;

    public function __construct(
        private readonly string $server
    )
    {
        $this->prefix = config('v2ray.v2bridge.path') . ' handler -s ' . $this->server;
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
        $command = config('v2ray.v2ray.path') . ' api stats -t 10 -s ' . $this->server . ' -json';

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
