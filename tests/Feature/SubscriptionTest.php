<?php

use App\Jobs\GenerateClashProfileLink;
use App\Jobs\UpdateUserUuid;
use App\Models\User;
use App\Models\VmessServer;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('clash subscription keeps yaml route compatibility', function () {
    $user = User::factory()->create([
        'balance' => 1,
        'uuid' => (string) Str::uuid(),
    ]);
    VmessServer::create([
        'name' => 'Tokyo',
        'server' => 'tokyo.example.com',
        'port' => 443,
        'rate' => 1,
        'internal_server' => 'internal.example.com',
        'enabled' => true,
    ]);
    app(SubscriptionService::class)->warmCache($user);

    $response = $this->get(route('subscription.clash', ['uuid' => $user->uuid]));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=yap.yaml')
        ->assertHeader('Content-Type', 'application/x-yaml')
        ->assertHeader('Subscription-Userinfo', 'upload=0; download=0; total=70368744177664; expire=612894867');

    expect($response->getContent())
        ->toContain('proxies:')
        ->toContain('name: Tokyo[1x]')
        ->toContain('server: tokyo.example.com')
        ->toContain('uuid: '.$user->uuid);
});

test('universal subscription returns base64 encoded vmess links', function () {
    $user = User::factory()->create([
        'balance' => 1,
        'uuid' => (string) Str::uuid(),
    ]);
    VmessServer::create([
        'name' => 'Tokyo',
        'server' => 'tokyo.example.com',
        'port' => 443,
        'rate' => 1,
        'internal_server' => 'internal.example.com',
        'enabled' => true,
    ]);
    app(SubscriptionService::class)->warmCache($user);

    $response = $this->get(route('subscription.universal', ['uuid' => $user->uuid]));

    $response->assertOk()
        ->assertHeader('Content-Disposition', 'attachment; filename=yap.txt')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');

    $links = explode("\n", trim(base64_decode($response->getContent())));
    $payload = json_decode(base64_decode(Str::after($links[0], 'vmess://')), true);

    expect($links[0])->toStartWith('vmess://')
        ->and($payload)->toMatchArray([
            'v' => '2',
            'ps' => 'Tokyo[1x]',
            'add' => 'tokyo.example.com',
            'port' => '443',
            'id' => $user->uuid,
            'aid' => '0',
            'scy' => 'auto',
            'net' => 'tcp',
            'type' => 'none',
            'tls' => '',
        ]);
});

test('invalid users cannot access subscriptions', function () {
    $user = User::factory()->create([
        'balance' => 0,
        'uuid' => (string) Str::uuid(),
    ]);

    $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertNotFound();
    $this->get(route('subscription.universal', ['uuid' => $user->uuid]))->assertNotFound();
});

test('valid users cannot access subscriptions before cache is built', function () {
    Bus::fake();
    $user = User::factory()->create([
        'balance' => 1,
        'uuid' => (string) Str::uuid(),
    ]);

    $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertNotFound();
    $this->get(route('subscription.universal', ['uuid' => $user->uuid]))->assertNotFound();

    Bus::assertDispatchedTimes(GenerateClashProfileLink::class, 1);
});

test('dashboard includes clash and universal subscription urls', function () {
    $this->withoutVite();
    $user = User::factory()->create([
        'balance' => 1,
        'uuid' => (string) Str::uuid(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('clashUrl', route('subscription.clash', ['uuid' => $user->uuid]))
        ->where('universalSubscriptionUrl', route('subscription.universal', ['uuid' => $user->uuid]))
    );
});

test('gen sub link command overwrites fixed subscription cache keys', function () {
    $user = User::factory()->create([
        'balance' => 1,
        'uuid' => (string) Str::uuid(),
    ]);
    VmessServer::create([
        'name' => 'Tokyo',
        'server' => 'tokyo.example.com',
        'port' => 443,
        'rate' => 1,
        'internal_server' => '',
        'enabled' => true,
    ]);

    $service = app(SubscriptionService::class);
    $clash_key = $service->cacheKey($user, SubscriptionService::FORMAT_CLASH);
    $universal_key = $service->cacheKey($user, SubscriptionService::FORMAT_UNIVERSAL);
    Cache::forever($clash_key, 'stale clash');
    Cache::forever($universal_key, 'stale universal');

    $this->artisan('app:gen-sub-link-command')
        ->expectsOutput('Rebuilt subscription cache and synced users.')
        ->assertSuccessful();

    expect($service->cacheKey($user, SubscriptionService::FORMAT_CLASH))->toBe($clash_key)
        ->and($service->cacheKey($user, SubscriptionService::FORMAT_UNIVERSAL))->toBe($universal_key)
        ->and(Cache::get($clash_key))->toContain('uuid: '.$user->uuid)
        ->and(base64_decode(Cache::get($universal_key)))->toContain('vmess://');
});

test('gen sub link command forgets invalid user subscription caches', function () {
    $user = User::factory()->create([
        'balance' => 0,
        'uuid' => (string) Str::uuid(),
    ]);
    VmessServer::create([
        'name' => 'Tokyo',
        'server' => 'tokyo.example.com',
        'port' => 443,
        'rate' => 1,
        'internal_server' => '',
        'enabled' => true,
    ]);

    $service = app(SubscriptionService::class);
    $clash_key = $service->cacheKey($user, SubscriptionService::FORMAT_CLASH);
    $universal_key = $service->cacheKey($user, SubscriptionService::FORMAT_UNIVERSAL);
    Cache::forever($clash_key, 'stale clash');
    Cache::forever($universal_key, 'stale universal');

    $this->artisan('app:gen-sub-link-command')
        ->expectsOutput('Rebuilt subscription cache and synced users.')
        ->assertSuccessful();

    expect(Cache::has($clash_key))->toBeFalse()
        ->and(Cache::has($universal_key))->toBeFalse();
});

test('clash subscription falls back when yaml customizer fails', function () {
    $customizer_path = app_path('ClashYamlCustomizer.php');
    $customizer_exists = File::exists($customizer_path);
    $customizer_contents = $customizer_exists ? File::get($customizer_path) : null;

    File::put($customizer_path, "<?php\n\nreturn function (string \$path): void {\n    throw new RuntimeException('Customizer failed.');\n};\n");

    try {
        $user = User::factory()->create([
            'balance' => 1,
            'uuid' => (string) Str::uuid(),
        ]);
        VmessServer::create([
            'name' => 'Tokyo',
            'server' => 'tokyo.example.com',
            'port' => 443,
            'rate' => 1,
            'internal_server' => 'internal.example.com',
            'enabled' => true,
        ]);
        app(SubscriptionService::class)->warmCache($user);

        $response = $this->get(route('subscription.clash', ['uuid' => $user->uuid]));

        $response->assertOk();
        expect($response->getContent())
            ->toContain('proxies:')
            ->toContain('uuid: '.$user->uuid);
    } finally {
        if ($customizer_exists) {
            File::put($customizer_path, $customizer_contents);
        } else {
            File::delete($customizer_path);
        }
    }
});

test('uuid rotation clears fixed subscription caches before async rebuild', function () {
    Bus::fake();
    Notification::fake();

    $old_uuid = (string) Str::uuid();
    $new_uuid = (string) Str::uuid();
    $user = User::factory()->create([
        'balance' => 1,
        'uuid' => $old_uuid,
    ]);

    $service = app(SubscriptionService::class);
    $clash_key = $service->cacheKey($user, SubscriptionService::FORMAT_CLASH);
    $universal_key = $service->cacheKey($user, SubscriptionService::FORMAT_UNIVERSAL);
    Cache::forever($clash_key, 'old uuid '.$old_uuid);
    Cache::forever($universal_key, 'old uuid '.$old_uuid);

    (new UpdateUserUuid($user, null, $old_uuid, $new_uuid))->handle($service);

    $user->refresh();

    expect($user->uuid)->toBe($new_uuid)
        ->and(Cache::has($clash_key))->toBeFalse()
        ->and(Cache::has($universal_key))->toBeFalse();
});
