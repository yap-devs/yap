<?php

use App\Services\ClientDownloadMirrorService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

test('it selects only supported client release assets', function () {
    $service = app(ClientDownloadMirrorService::class);
    $targets = $service->targets();
    $assets = [
        ['name' => 'cmfa-2.11.30-meta-arm64-v8a-release.apk'],
        ['name' => 'cmfa-2.11.30-meta-universal-release.apk'],
        ['name' => 'Clash.Verge_2.5.1_x64-setup.exe'],
        ['name' => 'Clash.Verge_2.5.1_x64_fixed_webview2-setup.exe'],
        ['name' => 'Clash.Verge_2.5.1_arm64_fixed_webview2-setup.exe'],
        ['name' => 'Clash.Verge_2.5.1_aarch64.dmg'],
        ['name' => 'Clash.Verge_2.5.1_x64.dmg'],
        ['name' => 'Clash.Verge_x64.app.tar.gz'],
    ];

    expect($service->findAsset($assets, $targets['clash-meta-android-universal'])['name'])
        ->toBe('cmfa-2.11.30-meta-universal-release.apk')
        ->and($service->findAsset($assets, $targets['clash-verge-windows-x64-webview2'])['name'])
        ->toBe('Clash.Verge_2.5.1_x64_fixed_webview2-setup.exe')
        ->and($service->findAsset($assets, $targets['clash-verge-macos-apple-silicon'])['name'])
        ->toBe('Clash.Verge_2.5.1_aarch64.dmg')
        ->and($service->findAsset($assets, $targets['clash-verge-macos-intel'])['name'])
        ->toBe('Clash.Verge_2.5.1_x64.dmg');
});

test('client download targets can be configured', function () {
    config()->set('services.client_downloads.targets', [
        'custom-client' => [
            'repo' => 'example/project',
            'label' => 'Custom Client',
            'patterns' => ['/custom-release\.zip$/i'],
            'latest_name' => 'custom-client.zip',
        ],
    ]);

    $service = app(ClientDownloadMirrorService::class);

    expect($service->targets())->toHaveKey('custom-client')
        ->and($service->findAsset([
            ['name' => 'ignored.zip'],
            ['name' => 'custom-release.zip'],
        ], $service->targets()['custom-client'])['name'])->toBe('custom-release.zip')
        ->and($service->downloads())->toHaveCount(1)
        ->and($service->downloads()[0]['key'])->toBe('custom-client');
});

test('downloads skips invalid configured targets', function () {
    config()->set('services.client_downloads.targets', [
        'broken-client' => [
            'repo' => 'example/project',
        ],
    ]);

    expect(app(ClientDownloadMirrorService::class)->downloads())->toBe([]);
});

test('it generates temporary urls with the configured mirrored path and download headers', function () {
    config()->set('services.client_downloads.disk', 'r2_downloads');
    config()->set('services.client_downloads.prefix', 'clients');
    config()->set('services.client_downloads.signed_url_ttl_minutes', 15);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')
        ->with('clients/manifest.json')
        ->andReturn(true);
    $disk->shouldReceive('get')
        ->with('clients/manifest.json')
        ->andReturn(json_encode([
            'assets' => [
                'clash-meta-android-universal' => [
                    'latest_path' => 'clients/clash-meta-android-universal/clash-meta-android-universal.apk',
                    'versioned_path' => 'clients/clash-meta-android-universal/2.11.30/cmfa-2.11.30-meta-universal-release.apk',
                    'source_name' => 'cmfa-2.11.30-meta-universal-release.apk',
                ],
            ],
        ]));
    $disk->shouldReceive('exists')
        ->with('clients/clash-meta-android-universal/2.11.30/cmfa-2.11.30-meta-universal-release.apk')
        ->andReturn(true);
    $disk->shouldReceive('temporaryUrl')
        ->once()
        ->withArgs(function (string $path, DateTimeInterface $expires_at, array $options): bool {
            return $path === 'clients/clash-meta-android-universal/2.11.30/cmfa-2.11.30-meta-universal-release.apk'
                && $expires_at->between(now()->addMinutes(14), now()->addMinutes(16))
                && $options['ResponseContentType'] === 'application/vnd.android.package-archive'
                && $options['ResponseContentDisposition'] === 'attachment; filename="clash-meta-android-universal.apk"';
        })
        ->andReturn('https://signed-r2.example.com/client.apk?signature=abc');

    Storage::shouldReceive('disk')
        ->with('r2_downloads')
        ->andReturn($disk);

    expect(app(ClientDownloadMirrorService::class)->temporaryDownloadUrl('clash-meta-android-universal'))
        ->toBe('https://signed-r2.example.com/client.apk?signature=abc');
});
