<?php

namespace App\Services;


use Exception;
use Illuminate\Support\Facades\Process;
use Throwable;

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
     *
     * @throws Exception When adding the user fails.
     * @throws Throwable
     */
    public function addUser($email, $uuid)
    {
        $result = Process::run($this->prefix . ' addV2rayVmessUser -e ' . $email . ' -u ' . $uuid);

        throw_if($result->failed(), new Exception('Failed to add user'));

        return json_decode($result->output(), true);
    }

    /**
     * Remove a user from the V2ray service.
     *
     * @param string $email The email of the user to be removed.
     * @return array The decoded JSON response from the V2ray service.
     *
     * @throws Exception if the removal of the user fails.
     * @throws Throwable
     */
    public function removeUser($email)
    {
        $result = Process::run($this->prefix . ' removeV2rayUser -e ' . $email);

        throw_if($result->failed(), new Exception('Failed to remove user'));

        return json_decode($result->output(), true);
    }

    /**
     * Get the statistics of the V2ray service.
     *
     * If an email is provided, it retrieves the uplink and downlink traffic stats
     * for the specified user. Otherwise, it retrieves the overall statistics for
     * the V2ray service.
     *
     * @param string|null $email The email of the user for whom to retrieve traffic stats.
     * @return array The decoded JSON response containing the statistics.
     *
     * @throws Exception if the retrieval of statistics fails.
     * @throws Throwable
     */
    public function stats($email = null)
    {
        $command = config('v2ray.v2ray.path') . ' api stats -s ' . $this->server . ' -json';

        if ($email) {
            $command .= " 'user>>>$email>>>traffic>>>uplink'";
            $command .= " 'user>>>$email>>>traffic>>>downlink'";
        }

        $result = Process::run($command);

        throw_if($result->failed(), new Exception('Failed to get stats'));

        $stat = json_decode($result->output(), true)['stat'];

        $res = [];
        foreach ($stat as $item) {
            [$type, $name, , $direction] = explode('>>>', $item['name']);
            $res[$type][$name][$direction] = $item['value'];
        }

        return $res;
    }
}
