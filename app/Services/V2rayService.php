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

    public function addUser($email, $uuid)
    {
        $result = Process::run($this->prefix . ' addV2rayVmessUser -e ' . $email . ' -u ' . $uuid);

        return json_decode($result->output(), true);
    }

    public function removeUser($email)
    {
        $result = Process::run($this->prefix . ' removeV2rayUser -e ' . $email);

        return json_decode($result->output(), true);
    }
}
