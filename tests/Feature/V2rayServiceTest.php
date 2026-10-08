<?php

use App\Models\NodeRoute;
use Illuminate\Validation\ValidationException;

// The retired SSH cases are replaced with the agent configuration boundary.
test('the panel cannot instantiate the retired ssh implementation', function () {
    expect(class_exists('App\\Services\\V2rayService'))->toBeFalse();
});

test('agent routes reject unsafe inbound names', function (string $tag) {
    expect(fn () => NodeRoute::factory()->create(['inbound_tag' => $tag]))->toThrow(ValidationException::class);
})->with(['shell;command', 'ordinary', '', '../path', 'yap-a b', 'yap-$(id)']);

test('agent routes reject listener ports outside the supported range', function (int $port) {
    expect(fn () => NodeRoute::factory()->create(['listen_port' => $port]))->toThrow(ValidationException::class);
})->with([0, 65536]);
