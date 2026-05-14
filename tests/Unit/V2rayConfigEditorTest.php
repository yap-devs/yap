<?php

use App\Services\V2rayConfigEditor;

function sampleV2rayConfig(): array
{
    return [
        'log' => [
            'loglevel' => 'warning',
        ],
        'inbounds' => [
            [
                'tag' => 'main-inbound',
                'port' => 2377,
                'protocol' => 'vmess',
                'settings' => [
                    'clients' => [
                        [
                            'id' => '00000000-0000-0000-0000-000000000001',
                            'email' => 'old@example.com',
                            'level' => 1,
                        ],
                    ],
                ],
                'sniffing' => [
                    'enabled' => true,
                    'destOverride' => ['http', 'tls'],
                ],
            ],
            [
                'listen' => '127.0.0.1',
                'port' => 10085,
                'protocol' => 'dokodemo-door',
                'settings' => [
                    'address' => '127.0.0.1',
                ],
                'tag' => 'api',
            ],
            [
                'tag' => 'manual-vmess',
                'port' => 3000,
                'protocol' => 'vmess',
                'settings' => [
                    'clients' => [
                        [
                            'id' => '00000000-0000-0000-0000-000000000002',
                            'email' => 'manual@example.com',
                        ],
                    ],
                ],
                'streamSettings' => [
                    'network' => 'tcp',
                ],
            ],
        ],
        'outbounds' => [
            [
                'protocol' => 'freedom',
                'tag' => 'direct',
            ],
        ],
    ];
}

test('it parses all inbounds and marks the current entry by vmess port', function () {
    $editor = new V2rayConfigEditor;
    $form_inbounds = $editor->inboundsToForm(sampleV2rayConfig(), 2377);

    expect($form_inbounds)->toHaveCount(3)
        ->and($form_inbounds[0]['tag'])->toBe('main-inbound')
        ->and($form_inbounds[0]['is_current_entry'])->toBeTrue()
        ->and($form_inbounds[1]['tag'])->toBe('api')
        ->and($form_inbounds[1]['is_current_entry'])->toBeFalse()
        ->and($form_inbounds[2]['tag'])->toBe('manual-vmess')
        ->and($form_inbounds[2]['is_current_entry'])->toBeFalse();
});

test('it applies inbound form changes while preserving raw inbound fields', function () {
    $editor = new V2rayConfigEditor;
    $config = sampleV2rayConfig();
    $form_inbounds = $editor->inboundsToForm($config, 2377);

    $form_inbounds[0]['tag'] = 'renamed-main';
    $form_inbounds[0]['clients'][] = [
        'id' => '00000000-0000-0000-0000-000000000003',
        'email' => 'new@example.com',
        'alterId' => '',
        'security' => '',
    ];
    $form_inbounds[2]['port'] = 3001;

    $updated = $editor->applyInboundForm($config, $form_inbounds);

    expect($updated['log']['loglevel'])->toBe('warning')
        ->and($updated['inbounds'])->toHaveCount(3)
        ->and($updated['inbounds'][0]['tag'])->toBe('renamed-main')
        ->and($updated['inbounds'][0]['settings']['clients'])->toHaveCount(2)
        ->and($updated['inbounds'][0]['settings']['clients'][0]['level'])->toBe(1)
        ->and($updated['inbounds'][0]['sniffing']['enabled'])->toBeTrue()
        ->and($updated['inbounds'][1]['protocol'])->toBe('dokodemo-door')
        ->and($updated['inbounds'][2]['port'])->toBe(3001)
        ->and($updated['inbounds'][2]['streamSettings']['network'])->toBe('tcp');
});

test('it syncs users only to the matching vmess inbound port', function () {
    $editor = new V2rayConfigEditor;
    $config = sampleV2rayConfig();

    [$updated, $found, $changed] = $editor->updateVmessClientsByPort($config, 2377, [
        [
            'id' => '00000000-0000-0000-0000-000000000004',
            'email' => 'synced@example.com',
        ],
    ]);

    expect($found)->toBeTrue()
        ->and($changed)->toBeTrue()
        ->and($updated['inbounds'][0]['settings']['clients'][0]['email'])->toBe('synced@example.com')
        ->and($updated['inbounds'][1]['settings']['address'])->toBe('127.0.0.1')
        ->and($updated['inbounds'][2]['settings']['clients'][0]['email'])->toBe('manual@example.com');
});

test('it prepares template vmess inbounds for requested ports', function () {
    $editor = new V2rayConfigEditor;
    $config = sampleV2rayConfig();

    [$updated, $changed] = $editor->prepareTemplateVmessInboundsForPorts($config, [8964, 8965]);

    expect($changed)->toBeTrue()
        ->and($updated['inbounds'])->toHaveCount(4)
        ->and($updated['inbounds'][0]['port'])->toBe(8964)
        ->and($updated['inbounds'][0]['protocol'])->toBe('vmess')
        ->and($updated['inbounds'][0]['settings']['clients'])->toBe([])
        ->and($updated['inbounds'][1]['tag'])->toBe('api')
        ->and($updated['inbounds'][3]['port'])->toBe(8965)
        ->and($updated['inbounds'][3]['tag'])->toBe('main-inbound-8965')
        ->and($updated['inbounds'][3]['settings']['clients'])->toBe([]);
});
