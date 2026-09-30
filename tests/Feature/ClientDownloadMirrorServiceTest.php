<?php

use App\Services\ClientDownloadMirrorService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Http;
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
        ->and($service->downloads()[0]['key'])->toBe('custom-client')
        ->and($service->downloads()[0]['label'])->toBe('Custom Client')
        ->and($service->downloads()[0]['repo'])->toBe('example/project');
});

test('downloads skips invalid configured targets', function () {
    config()->set('services.client_downloads.targets', [
        'broken-client' => [
            'repo' => 'example/project',
        ],
    ]);

    expect(app(ClientDownloadMirrorService::class)->downloads())->toBe([]);
});

test('sync skips binary writes when the current release is already mirrored', function () {
    config()->set('services.client_downloads.disk', 'r2_downloads');
    config()->set('services.client_downloads.prefix', 'clients');
    config()->set('services.client_downloads.targets', [
        'custom-client' => [
            'repo' => 'example/project',
            'label' => 'Custom Client',
            'patterns' => ['/custom-release\.zip$/i'],
            'latest_name' => 'custom-client.zip',
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/example/project/releases/latest' => Http::response([
            'tag_name' => 'v1.2.3',
            'assets' => [
                [
                    'name' => 'custom-release.zip',
                    'browser_download_url' => 'https://downloads.example.com/custom-release.zip',
                    'size' => 123,
                ],
            ],
        ]),
    ]);

    $versioned_path = 'clients/custom-client/1.2.3/custom-release.zip';
    $latest_path = 'clients/custom-client/custom-client.zip';
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('directories')->with('clients/custom-client')->andReturn([]);
    $disk->shouldReceive('exists')
        ->once()
        ->with('clients/manifest.json')
        ->andReturn(true);
    $disk->shouldReceive('get')
        ->once()
        ->with('clients/manifest.json')
        ->andReturn(json_encode([
            'assets' => [
                'custom-client' => [
                    'versioned_path' => $versioned_path,
                    'latest_path' => $latest_path,
                ],
            ],
        ]));
    $disk->shouldReceive('exists')
        ->once()
        ->with($versioned_path)
        ->andReturn(true);
    $disk->shouldReceive('exists')
        ->once()
        ->with($latest_path)
        ->andReturn(true);
    $disk->shouldReceive('readStream')
        ->zeroOrMoreTimes()
        ->andThrow(new RuntimeException('Current mirrored binaries must not be read during sync.'));
    $disk->shouldReceive('put')
        ->once()
        ->withArgs(fn (string $path, mixed $contents): bool => is_string($contents)
            && $path === 'clients/manifest.json'
            && json_decode($contents, true)['assets']['custom-client']['versioned_path'] === $versioned_path)
        ->andReturn(true);

    Storage::shouldReceive('disk')
        ->with('r2_downloads')
        ->andReturn($disk);

    $manifest = app(ClientDownloadMirrorService::class)->sync();

    expect($manifest['assets']['custom-client']['versioned_path'])->toBe($versioned_path)
        ->and($manifest['assets']['custom-client']['latest_path'])->toBe($latest_path);
});

test('sync rebuilds the latest object when the versioned asset exists', function () {
    config()->set('services.client_downloads.disk', 'r2_downloads');
    config()->set('services.client_downloads.prefix', 'clients');
    config()->set('services.client_downloads.targets', [
        'custom-client' => [
            'repo' => 'example/project',
            'label' => 'Custom Client',
            'patterns' => ['/custom-release\.zip$/i'],
            'latest_name' => 'custom-client.zip',
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/example/project/releases/latest' => Http::response([
            'tag_name' => 'v1.2.3',
            'assets' => [
                [
                    'name' => 'custom-release.zip',
                    'browser_download_url' => 'https://downloads.example.com/custom-release.zip',
                    'size' => 123,
                ],
            ],
        ]),
    ]);

    $versioned_path = 'clients/custom-client/1.2.3/custom-release.zip';
    $latest_path = 'clients/custom-client/custom-client.zip';
    $stream = fopen('php://temp', 'w+b');
    fwrite($stream, 'client-binary');
    rewind($stream);

    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('directories')->with('clients/custom-client')->andReturn([]);
    $disk->shouldReceive('exists')
        ->once()
        ->with('clients/manifest.json')
        ->andReturn(true);
    $disk->shouldReceive('get')
        ->once()
        ->with('clients/manifest.json')
        ->andReturn(json_encode([
            'assets' => [
                'custom-client' => [
                    'versioned_path' => $versioned_path,
                    'latest_path' => $latest_path,
                ],
            ],
        ]));
    $disk->shouldReceive('exists')
        ->twice()
        ->with($versioned_path)
        ->andReturn(true);
    $disk->shouldReceive('exists')
        ->once()
        ->with($latest_path)
        ->andReturn(false);
    $disk->shouldReceive('readStream')
        ->once()
        ->with($versioned_path)
        ->andReturn($stream);
    $disk->shouldReceive('put')
        ->once()
        ->withArgs(fn (string $path, mixed $contents, array $options): bool => $path === $latest_path
            && is_resource($contents)
            && $options === [
                'visibility' => 'private',
                'ContentType' => 'application/octet-stream',
            ])
        ->andReturn(true);
    $disk->shouldReceive('put')
        ->once()
        ->withArgs(fn (string $path, mixed $contents): bool => is_string($contents)
            && $path === 'clients/manifest.json')
        ->andReturn(true);

    Storage::shouldReceive('disk')
        ->with('r2_downloads')
        ->andReturn($disk);

    app(ClientDownloadMirrorService::class)->sync();
});

test('sync downloads a release when the versioned asset is missing', function () {
    config()->set('services.client_downloads.disk', 'r2_downloads');
    config()->set('services.client_downloads.prefix', 'clients');
    config()->set('services.client_downloads.targets', [
        'custom-client' => [
            'repo' => 'example/project',
            'label' => 'Custom Client',
            'patterns' => ['/custom-release\.zip$/i'],
            'latest_name' => 'custom-client.zip',
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/example/project/releases/latest' => Http::response([
            'tag_name' => 'v1.2.3',
            'assets' => [
                [
                    'name' => 'custom-release.zip',
                    'browser_download_url' => 'https://downloads.example.com/custom-release.zip',
                    'size' => 123,
                ],
            ],
        ]),
        'https://downloads.example.com/custom-release.zip' => Http::response('client-binary'),
    ]);

    $versioned_path = 'clients/custom-client/1.2.3/custom-release.zip';
    $latest_path = 'clients/custom-client/custom-client.zip';
    $uploaded_paths = [];
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('directories')->with('clients/custom-client')->andReturn([]);
    $disk->shouldReceive('exists')
        ->once()
        ->with('clients/manifest.json')
        ->andReturn(false);
    $disk->shouldReceive('exists')
        ->once()
        ->with($versioned_path)
        ->andReturn(false);
    $disk->shouldReceive('put')
        ->twice()
        ->withArgs(function (string $path, mixed $contents, array $options) use (&$uploaded_paths, $latest_path, $versioned_path): bool {
            if (! is_resource($contents) || ! in_array($path, [$versioned_path, $latest_path], true)) {
                return false;
            }

            $uploaded_paths[] = $path;

            return $options === [
                'visibility' => 'private',
                'ContentType' => 'application/octet-stream',
            ];
        })
        ->andReturn(true);
    $disk->shouldReceive('put')
        ->once()
        ->withArgs(fn (string $path, mixed $contents): bool => is_string($contents)
            && $path === 'clients/manifest.json')
        ->andReturn(true);

    Storage::shouldReceive('disk')
        ->with('r2_downloads')
        ->andReturn($disk);

    app(ClientDownloadMirrorService::class)->sync();

    expect($uploaded_paths)->toBe([$versioned_path, $latest_path]);
    Http::assertSentCount(2);
});

test('dry run does not access the client download disk', function () {
    config()->set('services.client_downloads.disk', 'r2_downloads');
    config()->set('services.client_downloads.prefix', 'clients');
    config()->set('services.client_downloads.targets', [
        'custom-client' => [
            'repo' => 'example/project',
            'label' => 'Custom Client',
            'patterns' => ['/custom-release\.zip$/i'],
            'latest_name' => 'custom-client.zip',
        ],
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/example/project/releases/latest' => Http::response([
            'tag_name' => 'v1.2.3',
            'assets' => [
                [
                    'name' => 'custom-release.zip',
                    'browser_download_url' => 'https://downloads.example.com/custom-release.zip',
                    'size' => 123,
                ],
            ],
        ]),
    ]);

    Storage::shouldReceive('disk')->never();

    $manifest = app(ClientDownloadMirrorService::class)->sync(true);

    expect($manifest['assets']['custom-client']['versioned_path'])
        ->toBe('clients/custom-client/1.2.3/custom-release.zip');
    Http::assertSentCount(1);
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

describe('client version retention', function () {
    beforeEach(function () {
        config()->set('services.client_downloads.disk', 'r2_downloads');
        config()->set('services.client_downloads.prefix', 'mirror/clients');
        config()->set('services.client_downloads.targets', [
            'custom-client' => [
                'repo' => 'example/project',
                'label' => 'Custom Client',
                'patterns' => ['/custom-release\.zip$/i'],
                'latest_name' => 'custom-client.zip',
            ],
        ]);

        $this->disk = Storage::fake('r2_downloads');
        foreach (['1.8.0', '1.9.0', '1.10.0', '1.11.0'] as $version) {
            $this->disk->put('mirror/clients/custom-client/'.$version.'/custom-release.zip', $version);
        }
        $this->disk->put('mirror/clients/custom-client/1.8.0/extra.zip', 'extra');
        $this->disk->put('mirror/clients/custom-client/custom-client.zip', '1.11.0');
        $this->disk->put('mirror/clients/unrelated/1.0.0/file.zip', 'unrelated');
        $this->disk->put('mirror/clients/custom-client/readme.txt', 'keep');

        $this->release_version = 'v1.11.0';
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/example/project/releases/latest' => fn () => Http::response([
                'tag_name' => $this->release_version,
                'assets' => [[
                    'name' => 'custom-release.zip',
                    'browser_download_url' => 'https://downloads.example.com/custom-release.zip',
                ]],
            ]),
        ]);
    });

    test('sync retains three versions by default and cleans existing history without a new release', function () {
        expect(config('services.client_downloads.keep_versions'))->toBe(3);

        app(ClientDownloadMirrorService::class)->sync();
        app(ClientDownloadMirrorService::class)->sync();

        $this->disk->assertMissing('mirror/clients/custom-client/1.8.0/custom-release.zip');
        $this->disk->assertMissing('mirror/clients/custom-client/1.8.0/extra.zip');
        foreach (['1.9.0', '1.10.0', '1.11.0'] as $version) {
            $this->disk->assertExists('mirror/clients/custom-client/'.$version.'/custom-release.zip');
        }
        $this->disk->assertExists('mirror/clients/custom-client/custom-client.zip');
        $this->disk->assertExists('mirror/clients/custom-client/readme.txt');
        $this->disk->assertExists('mirror/clients/unrelated/1.0.0/file.zip');
        expect($this->disk->json('mirror/clients/manifest.json')['assets']['custom-client']['version'])
            ->toBe('1.11.0');
    });

    test('sync honors the configured version count', function (int $keep_versions, int $expected_count) {
        config()->set('services.client_downloads.keep_versions', $keep_versions);

        app(ClientDownloadMirrorService::class)->sync();

        expect($this->disk->directories('mirror/clients/custom-client'))->toHaveCount($expected_count);
        $this->disk->assertExists('mirror/clients/custom-client/1.11.0/custom-release.zip');
    })->with([[1, 1], [2, 2], [5, 4]]);

    test('sync rejects invalid retention counts before changing files', function (mixed $keep_versions) {
        config()->set('services.client_downloads.keep_versions', $keep_versions);
        $files = $this->disk->allFiles();

        expect(fn () => app(ClientDownloadMirrorService::class)->sync())
            ->toThrow(InvalidArgumentException::class);

        expect($this->disk->allFiles())->toBe($files);
        Http::assertNothingSent();
    })->with([0, -1, 'invalid']);

    test('sync always retains the current release even after an upstream rollback', function () {
        config()->set('services.client_downloads.keep_versions', 1);
        $this->release_version = 'v1.8.0';

        app(ClientDownloadMirrorService::class)->sync();

        $this->disk->assertExists('mirror/clients/custom-client/1.8.0/custom-release.zip');
        expect($this->disk->directories('mirror/clients/custom-client'))->toHaveCount(1)
            ->and($this->disk->get('mirror/clients/custom-client/custom-client.zip'))->toBe('1.8.0');
    });

    test('dry run does not prune existing versions', function () {
        $files = $this->disk->allFiles();

        app(ClientDownloadMirrorService::class)->sync(true);

        expect($this->disk->allFiles())->toBe($files);
    });

    test('sync protects the current version with an empty prefix', function () {
        config()->set('services.client_downloads.prefix', '');
        config()->set('services.client_downloads.keep_versions', 1);
        $this->disk->put('custom-client/1.11.0/custom-release.zip', 'current');
        $this->disk->put('custom-client/1.10.0/custom-release.zip', 'old');

        app(ClientDownloadMirrorService::class)->sync();

        $this->disk->assertExists('custom-client/1.11.0/custom-release.zip');
        $this->disk->assertMissing('custom-client/1.10.0/custom-release.zip');
        expect($this->disk->json('manifest.json')['assets']['custom-client']['versioned_path'])
            ->toBe('custom-client/1.11.0/custom-release.zip');
    });

    test('sync rejects unsafe version directory names before writing', function (string $version) {
        $this->release_version = $version;
        $files = $this->disk->allFiles();

        expect(fn () => app(ClientDownloadMirrorService::class)->sync())
            ->toThrow(InvalidArgumentException::class);

        expect($this->disk->allFiles())->toBe($files);
    })->with(['release/1.12.0', 'release\\1.12.0', '..', '.', 'v']);

    test('sync does not prune any client when a later target fails', function () {
        config()->set('services.client_downloads.targets.broken-client', [
            'repo' => 'example/project',
            'label' => 'Broken Client',
            'patterns' => ['/missing\.zip$/i'],
            'latest_name' => 'broken.zip',
        ]);

        expect(fn () => app(ClientDownloadMirrorService::class)->sync())
            ->toThrow(RuntimeException::class, 'No matching asset found');

        expect($this->disk->directories('mirror/clients/custom-client'))->toHaveCount(4);
        $this->disk->assertMissing('mirror/clients/manifest.json');
    });

    test('sync does not prune when a storage write fails', function (string $failed_path) {
        $fake_disk = $this->disk;
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')->andReturnUsing(fn (string $path): bool => $fake_disk->exists($path));
        $disk->shouldReceive('readStream')->andReturnUsing(fn (string $path) => $fake_disk->readStream($path));
        $disk->shouldReceive('put')->andReturnUsing(fn (string $path, mixed $contents): bool => $path !== $failed_path);
        $disk->shouldNotReceive('directories');
        $disk->shouldNotReceive('deleteDirectory');
        Storage::shouldReceive('disk')->with('r2_downloads')->andReturn($disk);

        expect(fn () => app(ClientDownloadMirrorService::class)->sync())->toThrow(RuntimeException::class);

        expect($fake_disk->directories('mirror/clients/custom-client'))->toHaveCount(4);
    })->with([
        'latest upload' => 'mirror/clients/custom-client/custom-client.zip',
        'manifest upload' => 'mirror/clients/manifest.json',
    ]);
});
