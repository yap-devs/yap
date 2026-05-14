<?php

namespace App\Services;

use App\Models\User;
use App\Models\VmessServer;

class V2rayUserIdentifier
{
    public function clientEmail(User $user, VmessServer $server, array $server_port_counts): string
    {
        if (($server_port_counts[$server->internal_server] ?? 1) <= 1) {
            return $user->email;
        }

        return $user->email.'|'.$server->port;
    }

    public function portCounts($vmess_servers): array
    {
        $ports = [];

        /** @var VmessServer $server */
        foreach ($vmess_servers as $server) {
            $ports[$server->internal_server][$server->port] = true;
        }

        return array_map('count', $ports);
    }
}
