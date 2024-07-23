<?php

namespace App\Services;

use App\Models\User;
use App\Models\VmessServer;
use Illuminate\Support\Facades\File;

readonly class ClashService
{
    /**
     * @param User $user
     */
    public function __construct(
        private User $user,
    )
    {
    }

    public function genConf($vmess_servers = null)
    {
        $template = yaml_parse_file(resource_path('clash-conf-template.yaml'));
        $vmess_servers = $vmess_servers ?: VmessServer::all();

        $proxies = [];
        /** @var VmessServer $vmess_server */
        foreach ($vmess_servers as $vmess_server) {
            $name = "[{$vmess_server->rate}x]$vmess_server->name";

            $proxies[] = [
                'name' => $name,
                'type' => 'vmess',
                'server' => $vmess_server->server,
                'port' => $vmess_server->port,
                'uuid' => $this->user->uuid,
                'alterId' => 0,
                'cipher' => 'auto',
            ];
        }

        $template['proxies'] = $proxies;
        $template['proxy-groups'] = [
            [
                'proxies' => array_column($proxies, 'name'),
                'name' => 'Proxy',
                'type' => 'select',
            ],
            [
                'proxies' => array_column($proxies, 'name'),
                'name' => 'Auto',
                'type' => 'url-test',
                'url' => 'http://www.gstatic.cn/generate_204',
                'interval' => 120,
                'tolerance' => 40,
            ],
        ];

        yaml_emit_file(storage_path("clash-config/{$this->user->uuid}.yaml"), $template);
    }

    public function delConf()
    {
        if (File::exists(storage_path("clash-config/{$this->user->uuid}.yaml"))) {
            File::delete(storage_path("clash-config/{$this->user->uuid}.yaml"));
        }
    }

    public function confExists()
    {
        return File::exists(storage_path("clash-config/{$this->user->uuid}.yaml"));
    }
}
