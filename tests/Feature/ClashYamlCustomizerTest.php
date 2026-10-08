<?php

use App\Models\NodeRoute;
use App\Models\User;
use App\Services\ClashService;
use Symfony\Component\Yaml\Yaml;

test('clash customizer transforms configuration arrays before serialization', function () {
    $user = User::factory()->make();
    $server = new NodeRoute(['name' => '香港: HK', 'server' => 'hk.example.com', 'port' => 443, 'rate' => 1]);
    $service = new ClashService($user, base_path('tests/Fixtures/ClashYamlCustomizerArray.php'));

    $config = Yaml::parse($service->genConf([$server]));

    expect($config['dns']['enable'])->toBeTrue()
        ->and($config['rules'][0])->toBe('DOMAIN-SUFFIX,custom.example,DIRECT')
        ->and($config['rules'][array_key_last($config['rules'])])->toBe('MATCH,Proxy')
        ->and($config['proxies'][0])->toMatchArray([
            'name' => '香港: HK[1x]',
            'server' => 'hk.example.com',
            'port' => 443,
            'uuid' => $user->uuid,
        ])
        ->and($config['proxy-groups'][0]['proxies'])->toContain('香港: HK[1x]');
});

test('clash customizer must preserve required configuration arrays', function () {
    $service = new ClashService(User::factory()->make(), base_path('tests/Fixtures/ClashYamlCustomizerInvalid.php'));

    expect(fn () => $service->genConf([]))->toThrow(RuntimeException::class, 'must preserve the [proxies] array');
});

test('clash example customizer preserves default configuration', function () {
    $user = User::factory()->make();
    $default = new ClashService($user, base_path('tests/Fixtures/missing-customizer.php'));
    $customized = new ClashService($user, app_path('ClashYamlCustomizer.example.php'));

    expect(Yaml::parse($customized->genConf([])))->toBe(Yaml::parse($default->genConf([])));
});
