<?php

namespace App\Services;

use App\Models\RelayServer;
use App\Models\User;
use App\Models\VmessServer;
use Illuminate\Support\Facades\File;
use Throwable;

readonly class ClashService
{
    public function __construct(
        private User $user,
    ) {}

    public function genConf(?iterable $vmess_servers = null): string
    {
        $template = yaml_parse_file(resource_path('clash-conf-template.yaml'));
        $proxies = $this->proxies($vmess_servers);

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
            ],
        ];

        return $this->customizeYaml(yaml_emit($template));
    }

    public function proxies(?iterable $vmess_servers = null): array
    {
        $vmess_servers = $vmess_servers ?? VmessServer::where('enabled', true)->with('relays')->get();

        $proxies = [];
        /** @var VmessServer $vmess_server */
        foreach ($vmess_servers as $vmess_server) {
            if (empty($vmess_server->server) && $vmess_server->relays->isNotEmpty()) {
                /** @var RelayServer $relay */
                foreach ($vmess_server->relays as $relay) {
                    if (! $relay->enabled) {
                        continue;
                    }

                    $proxies[] = [
                        'name' => "$vmess_server->name[$relay->name][{$vmess_server->rate}x]",
                        'type' => 'vmess',
                        'server' => $relay->server,
                        'port' => $relay->port ?: $vmess_server->port,
                        'uuid' => $this->user->uuid,
                        'alterId' => 0,
                        'cipher' => 'auto',
                    ];
                }
            } else {
                $proxies[] = [
                    'name' => "$vmess_server->name[{$vmess_server->rate}x]",
                    'type' => 'vmess',
                    'server' => $vmess_server->server,
                    'port' => $vmess_server->port,
                    'uuid' => $this->user->uuid,
                    'alterId' => 0,
                    'cipher' => 'auto',
                ];
            }
        }

        return $proxies;
    }

    private function customizeYaml(string $yaml): string
    {
        if (file_exists(app_path('ClashYamlCustomizer.php'))) {
            $path = tempnam(sys_get_temp_dir(), 'yap-clash-');

            if ($path === false) {
                return $yaml;
            }

            File::put($path, $yaml);
            $customizer = require app_path('ClashYamlCustomizer.php');

            try {
                if (is_callable($customizer)) {
                    $customizer($path);
                }

                return File::get($path);
            } catch (Throwable $exception) {
                report($exception);

                return $yaml;
            } finally {
                File::delete($path);
            }
        }

        return $yaml;
    }
}
