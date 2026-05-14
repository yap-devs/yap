<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use App\Models\VmessServer;

test('it seeds empty user buckets for every enabled v2ray port', function () {
    $job = new GenerateClashProfileLink;
    $server_with_users = new VmessServer;
    $server_with_users->forceFill([
        'internal_server' => '10.0.0.1',
        'port' => 2377,
    ]);
    $server_without_users = new VmessServer;
    $server_without_users->forceFill([
        'internal_server' => '10.0.0.1',
        'port' => 8964,
    ]);
    $user = new User;
    $user->forceFill([
        'uuid' => '00000000-0000-0000-0000-000000000001',
        'email' => 'user@example.com',
    ]);

    $property = new ReflectionProperty($job, 'vmess_servers');
    $property->setAccessible(true);
    $property->setValue($job, collect([$server_with_users, $server_without_users]));

    $method = new ReflectionMethod($job, 'buildServerUserMap');
    $method->setAccessible(true);

    $map = $method->invoke($job, [
        [$user, [$server_with_users]],
    ]);

    expect($map['10.0.0.1'][2377])->toBe([
        [
            'id' => '00000000-0000-0000-0000-000000000001',
            'email' => 'user@example.com|2377',
        ],
    ])->and($map['10.0.0.1'][8964])->toBe([]);
});
