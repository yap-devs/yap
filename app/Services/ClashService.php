<?php

namespace App\Services;

use App\Models\RelayServer;
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

            if (empty($vmess_server->server) && $vmess_server->relays->isNotEmpty()) {
                /** @var RelayServer $relay */
                foreach ($vmess_server->relays as $relay) {
                    if (!$relay->enabled) {
                        continue;
                    }

                    $proxies[] = [
                        'name' => "[$relay->name]$name",
                        'type' => 'vmess',
                        'server' => $relay->server,
                        'port' => $vmess_server->port,
                        'uuid' => $this->user->uuid,
                        'alterId' => 0,
                        'cipher' => 'auto',
                    ];
                }
            } else {
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
        }

        $template['proxies'] = $proxies;
        $proxy_names = array_column($proxies, 'name');
        $proxy_names_with_auto = array_merge(['Auto', 'Fallback'], $proxy_names);
        $template['proxy-groups'] = [
            [
                'proxies' => $proxy_names_with_auto,
                'name' => 'Proxy',
                'type' => 'select',
            ],
            [
                'proxies' => $proxy_names,
                'name' => 'Auto',
                'type' => 'url-test',
                'url' => 'https://www.gstatic.com/generate_204',
                'interval' => 3600,
            ],
            [
                'proxies' => $proxy_names,
                'name' => 'Fallback',
                'type' => 'fallback',
                'url' => 'https://www.gstatic.com/generate_204',
                'interval' => 3600,
            ]
        ];

        $path = storage_path("clash-config/{$this->user->uuid}.yaml");
        yaml_emit_file($path, $template);
        if (file_exists(app_path('ClashYamlCustomizer.php'))) {
            $customizer = require app_path('ClashYamlCustomizer.php');

            if (is_callable($customizer)) {
                $customizer($path);
            }
        }
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
