<?php

use App\Models\User;
use App\Models\VmessServer;
use App\Services\V2rayUserIdentifier;

test('it uses plain email when an internal server has one entry port', function () {
    $user = new User;
    $user->forceFill(['email' => 'user@example.com']);
    $server = new VmessServer;
    $server->forceFill([
        'internal_server' => '10.0.0.1',
        'port' => 2377,
    ]);

    $identifier = new V2rayUserIdentifier;

    expect($identifier->clientEmail($user, $server, ['10.0.0.1' => 1]))->toBe('user@example.com');
});

test('it appends the port when an internal server has multiple entry ports', function () {
    $user = new User;
    $user->forceFill(['email' => 'user@example.com']);
    $server = new VmessServer;
    $server->forceFill([
        'internal_server' => '10.0.0.1',
        'port' => 8964,
    ]);

    $identifier = new V2rayUserIdentifier;

    expect($identifier->clientEmail($user, $server, ['10.0.0.1' => 2]))->toBe('user@example.com|8964');
});
