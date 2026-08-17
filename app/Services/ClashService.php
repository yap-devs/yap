<?php

namespace App\Services;

use App\Models\RelayServer;
use App\Models\User;
use App\Models\VmessServer;
use Illuminate\Support\Facades\File;
use RuntimeException;

readonly class ClashService
{
    public function __construct(
        private User $user,
        private ?string $customizer_path = null,
    ) {}

    public function genConf(?iterable $vmess_servers = null): string
    {
        $template = yaml_parse_file(resource_path('clash-conf-template.yaml'));
        throw_unless(is_array($template), RuntimeException::class, 'Unable to parse the Clash configuration template.');

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

        $yaml = yaml_emit($template);
        throw_if($yaml === false, RuntimeException::class, 'Unable to emit the Clash configuration.');

        return $this->customizeYaml($yaml);
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
        $customizer_path = $this->customizer_path ?? app_path('ClashYamlCustomizer.php');

        if (! File::exists($customizer_path)) {
            return $yaml;
        }

        $path = tempnam(sys_get_temp_dir(), 'yap-clash-');
        throw_if($path === false, RuntimeException::class, 'Unable to create a temporary Clash configuration file.');

        try {
            throw_if(File::put($path, $yaml) === false, RuntimeException::class, 'Unable to write the temporary Clash configuration file.');

            $customizer = require $customizer_path;
            throw_unless(
                is_callable($customizer),
                RuntimeException::class,
                "The Clash YAML customizer [$customizer_path] must return a callable.",
            );

            $customizer($path);

            $customized_yaml = File::get($path);
            $customized_config = yaml_parse($customized_yaml);
            throw_unless(
                is_array($customized_config),
                RuntimeException::class,
                "The Clash YAML customizer [$customizer_path] produced invalid YAML.",
            );

            foreach (['proxies', 'proxy-groups', 'rules'] as $required_key) {
                throw_unless(
                    isset($customized_config[$required_key]) && is_array($customized_config[$required_key]),
                    RuntimeException::class,
                    "The Clash YAML customizer [$customizer_path] must preserve the [$required_key] array.",
                );
            }

            return $customized_yaml;
        } finally {
            File::delete($path);
        }
    }
}
