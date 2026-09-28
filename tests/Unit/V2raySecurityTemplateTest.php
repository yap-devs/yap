<?php

test('node template isolates proxy clients from management and private destinations', function () {
    $config = json_decode(file_get_contents(__DIR__.'/../../resources/v2ray-conf-demo.json'), true, flags: JSON_THROW_ON_ERROR);
    $rules = $config['routing']['rules'];

    expect($config['routing']['domainStrategy'])->toBe('IPOnDemand');
    expect($config['outbounds'][0]['settings']['domainStrategy'])->toBe('UseIP');

    expect($rules[0]['inboundTag'])->toBe(['main-inbound'])
        ->and($rules[0]['outboundTag'])->toBe('block')
        ->and($rules[0]['port'])->toBe('10085');

    expect($rules[1]['domains'])->toContain('domain:localhost', 'domain:local', 'domain:home.arpa')
        ->and($rules[1]['inboundTag'])->toBe(['main-inbound'])
        ->and($rules[1]['outboundTag'])->toBe('block');

    expect($rules[2]['ip'])->toContain('127.0.0.0/8', '100.64.0.0/10', '169.254.0.0/16', '::1/128', 'fc00::/7', 'fe80::/10')
        ->and($rules[2]['inboundTag'])->toBe(['main-inbound'])
        ->and($rules[2]['outboundTag'])->toBe('block');

    expect($rules[3]['inboundTag'])->toBe(['api'])
        ->and($rules[3]['outboundTag'])->toBe('api')
        ->and($config['inbounds'][1]['listen'])->toBe('127.0.0.1');
});
