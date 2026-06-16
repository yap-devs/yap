<?php

use App\Models\User;
use App\Services\ClientDownloadMirrorService;

test('customer service page includes mirrored client download links', function () {
    $this->withoutVite();
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('customer.service'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('CustomerService/Index')
        ->has('clientDownloads', 4)
        ->where('clientDownloads.0.key', 'clash-meta-android-universal')
        ->where('clientDownloads.0.label', 'Clash Meta for Android Universal')
        ->where('clientDownloads.0.repo', 'MetaCubeX/ClashMetaForAndroid')
        ->where('clientDownloads.0.url', route('customer.service.download', ['client' => 'clash-meta-android-universal']))
        ->where('clientDownloads.0.github_url', 'https://github.com/MetaCubeX/ClashMetaForAndroid/releases/latest')
        ->where('clientDownloads.1.key', 'clash-verge-windows-x64-webview2')
        ->where('clientDownloads.1.label', 'Clash Verge Rev Windows x64 WebView2')
        ->where('clientDownloads.2.key', 'clash-verge-macos-apple-silicon')
        ->where('clientDownloads.3.key', 'clash-verge-macos-intel')
    );
});

test('valid users are redirected to a temporary client download url', function () {
    $this->mock(ClientDownloadMirrorService::class, function ($mock) {
        $mock->shouldReceive('targets')
            ->once()
            ->andReturn(app(ClientDownloadMirrorService::class)->targets());

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

test('missing mirrored client downloads redirect to github fallback', function () {
    $this->mock(ClientDownloadMirrorService::class, function ($mock) {
        $mock->shouldReceive('targets')
            ->once()
            ->andReturn(app(ClientDownloadMirrorService::class)->targets());

        $mock->shouldReceive('temporaryDownloadUrl')
            ->once()
            ->with('clash-meta-android-universal')
            ->andThrow(new RuntimeException('Mirrored client download is unavailable.'));

        $mock->shouldReceive('githubReleaseUrl')
            ->once()
            ->with('clash-meta-android-universal')
            ->andReturn('https://github.com/MetaCubeX/ClashMetaForAndroid/releases/latest');
    });

    $user = User::factory()->create(['balance' => 1]);

    $response = $this->actingAs($user)->get(route('customer.service.download', [
        'client' => 'clash-meta-android-universal',
    ]));

    $response->assertRedirect('https://github.com/MetaCubeX/ClashMetaForAndroid/releases/latest');
});
