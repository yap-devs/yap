<?php

namespace App\Services;

use App\Models\NodeRoute;
use App\Models\User;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

readonly class ClashService
{
    public function __construct(
        private User $user,
        private ?string $customizer_path = null,
    ) {}

    public function genConf(?iterable $routes = null): string
    {
        $template = Yaml::parseFile(resource_path('clash-conf-template.yaml'));
        throw_unless(is_array($template), RuntimeException::class, 'Unable to parse the Clash configuration template.');

        $proxies = $this->proxies($routes);

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

        return Yaml::dump($this->customizeConfig($template), 10, 2, Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
    }

    public function proxies(?iterable $routes = null): array
    {
        $routes ??= app(SubscriptionService::class)->serversFor($this->user);
        $proxies = [];
        /** @var NodeRoute $route */
        foreach ($routes as $route) {
            $rate = rtrim(rtrim($route->rate, '0'), '.');
            $proxies[] = [
                'name' => "$route->name[{$rate}x]",
                'type' => 'vmess',
                'server' => $route->server,
                'port' => $route->port,
                'uuid' => $this->user->uuid,
                'alterId' => 0,
                'cipher' => 'auto',
            ];
        }

        return $proxies;
    }

    private function customizeConfig(array $config): array
    {
        $customizer_path = $this->customizer_path ?? app_path('ClashYamlCustomizer.php');

        if (! File::exists($customizer_path)) {
            return $config;
        }

        $customizer = require $customizer_path;
        throw_unless(
            is_callable($customizer),
            RuntimeException::class,
            "The Clash YAML customizer [$customizer_path] must return a callable.",
        );

        $customized_config = $customizer($config);
        throw_unless(
            is_array($customized_config),
            RuntimeException::class,
            "The Clash YAML customizer [$customizer_path] must return a configuration array.",
        );

        foreach (['proxies', 'proxy-groups', 'rules'] as $required_key) {
            throw_unless(
                isset($customized_config[$required_key]) && is_array($customized_config[$required_key]),
                RuntimeException::class,
                "The Clash YAML customizer [$customizer_path] must preserve the [$required_key] array.",
            );
        }

        return $customized_config;
    }
}
