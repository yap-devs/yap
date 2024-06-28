<?php

namespace App\Services;

use App\Models\User;
use App\Models\VmessServer;
use Illuminate\Database\Eloquent\Collection;

readonly class ClashService
{
    /**
     * @param User $user
     * @param VmessServer[]|Collection $vmess_servers
     */
    public function __construct(
        private User  $user,
        private array $vmess_servers = []
    )
    {
    }

    public function genConf()
    {
        $template = yaml_parse_file(resource_path('clash-conf-template.yaml'));
        $vmess_servers = $this->vmess_servers ?: VmessServer::all();

        $proxies = [];
        $proxy_groups = [
            'proxies' => [],
            'name' => 'Proxy',
            'type' => 'select',
        ];
        /** @var VmessServer $vmess_server */
        foreach ($vmess_servers as $vmess_server) {
            $proxies[] = [
                'name' => $vmess_server->name,
                'type' => 'vmess',
                'server' => $vmess_server->server,
                'port' => $vmess_server->port,
                'uuid' => $this->user->uuid,
                'alterId' => 0,
                'cipher' => 'auto',
            ];

            $proxy_groups['proxies'][] = $vmess_server->name;
        }

        $template['proxies'] = $proxies;
        $template['proxy-groups'] = [$proxy_groups];

        yaml_emit_file(storage_path("clash-config/{$this->user->uuid}.yaml"), $template);
    }

    public function delConf()
    {
        unlink(storage_path("clash-config/{$this->user->uuid}.yaml"));
    }
}
