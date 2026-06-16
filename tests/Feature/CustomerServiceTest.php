<?php

use App\Models\User;
use App\Services\ClientDownloadMirrorService;
use Illuminate\Support\Facades\Storage;

test('customer service page includes mirrored client download links', function () {
    $this->withoutVite();
    config()->set('services.client_downloads.disk', 'r2_downloads');
    Storage::fake('r2_downloads');
    Storage::disk('r2_downloads')->put('clients/clash-meta-android-universal/clash-meta-android-universal.apk', 'apk');
    Storage::disk('r2_downloads')->put('clients/clash-meta-android-universal/2.11.30/cmfa.apk', 'apk');
    Storage::disk('r2_downloads')->put('clients/manifest.json', json_encode([
        'assets' => [
            'clash-meta-android-universal' => [
                'latest_path' => 'clients/clash-meta-android-universal/clash-meta-android-universal.apk',
                'versioned_path' => 'clients/clash-meta-android-universal/2.11.30/cmfa.apk',
            ],
        ],
    ]));

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.service'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CustomerService/Index')
        ->has('clientDownloads', 4)
        ->where('clientDownloads.0.key', 'clash-meta-android-universal')
        ->where('clientDownloads.0.available', true)
        ->where('clientDownloads.0.url', route('customer.service.download', ['client' => 'clash-meta-android-universal']))
        ->where('clientDownloads.1.key', 'clash-verge-windows-x64-webview2')
        ->where('clientDownloads.1.available', false)
        ->where('clientDownloads.1.url', null)
        ->where('clientDownloads.2.key', 'clash-verge-macos-apple-silicon')
        ->where('clientDownloads.3.key', 'clash-verge-macos-intel')
    );
});

test('customer service page falls back to github links when mirror storage is unavailable', function () {
    $this->withoutVite();
    config()->set('services.client_downloads.disk', 'missing_disk');

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.service'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CustomerService/Index')
        ->has('clientDownloads', 4)
        ->where('clientDownloads.0.available', false)
        ->where('clientDownloads.0.url', null)
    );
});

test('customer service page hides primary links when manifest is stale', function () {
    $this->withoutVite();
    config()->set('services.client_downloads.disk', 'r2_downloads');
    Storage::fake('r2_downloads');
    Storage::disk('r2_downloads')->put('clients/manifest.json', json_encode([
        'assets' => [
            'clash-meta-android-universal' => [
                'latest_path' => 'clients/clash-meta-android-universal/clash-meta-android-universal.apk',
            ],
        ],
    ]));

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.service'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CustomerService/Index')
        ->where('clientDownloads.0.available', false)
        ->where('clientDownloads.0.url', null)
    );
});

test('valid users are redirected to a temporary client download url', function () {
    $this->mock(ClientDownloadMirrorService::class, function ($mock) {
        $mock->shouldReceive('targets')
            ->once()
            ->andReturn(app(ClientDownloadMirrorService::class)->targets());

        $mock->shouldReceive('hasMirroredDownload')
            ->once()
            ->with('clash-meta-android-universal')
            ->andReturn(true);

        $mock->shouldReceive('temporaryDownloadUrl')
            ->once()
            ->with('clash-meta-android-universal')
            ->andReturn('https://signed-r2.example.com/client.apk?signature=abc');
    });

    $user = User::factory()->create(['balance' => 1]);

    $response = $this->actingAs($user)->get(route('customer.service.download', [
        'client' => 'clash-meta-android-universal',
    ]));

    $response->assertRedirect('https://signed-r2.example.com/client.apk?signature=abc');
});

test('invalid users cannot download mirrored clients', function () {
    $user = User::factory()->create(['balance' => 0]);

    $response = $this->actingAs($user)->get(route('customer.service.download', [
        'client' => 'clash-meta-android-universal',
    ]));

    $response->assertForbidden();
});

test('unknown client downloads return not found', function () {
    $user = User::factory()->create(['balance' => 1]);

    $response = $this->actingAs($user)->get(route('customer.service.download', [
        'client' => 'unknown-client',
    ]));

    $response->assertNotFound();
});

test('missing mirrored client downloads return not found', function () {
    $this->mock(ClientDownloadMirrorService::class, function ($mock) {
        $mock->shouldReceive('targets')
            ->once()
            ->andReturn(app(ClientDownloadMirrorService::class)->targets());

        $mock->shouldReceive('hasMirroredDownload')
            ->once()
            ->with('clash-meta-android-universal')
            ->andReturn(false);
    });

    $user = User::factory()->create(['balance' => 1]);

    $response = $this->actingAs($user)->get(route('customer.service.download', [
        'client' => 'clash-meta-android-universal',
    ]));

    $response->assertNotFound();
});
