<?php

use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

test('credential bearing endpoints are excluded from telescope recording', function (string $path) {
    $method = new ReflectionMethod(Telescope::class, 'requestIsToApprovedUri');

    expect($method->invoke(null, Request::create($path)))->toBeFalse();
})->with([
    '/api/agent/v1/config',
    '/api/agent/v1/traffic',
    '/clash/00000000-0000-4000-8000-000000000001/yap.yaml',
    '/sub/00000000-0000-4000-8000-000000000001/yap.txt',
]);

test('ordinary panel diagnostics remain recordable', function () {
    $method = new ReflectionMethod(Telescope::class, 'requestIsToApprovedUri');

    expect($method->invoke(null, Request::create('/dashboard')))->toBeTrue();
});

test('telescope masks session and csrf headers alongside default bearer secrets', function () {
    expect(Telescope::$hiddenRequestHeaders)->toContain('authorization', 'cookie', 'x-csrf-token', 'x-xsrf-token')
        ->and(Telescope::$hiddenRequestParameters)->toContain('password', '_token');
});
