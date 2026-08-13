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

    $content = $response->getContent();
    $config = yaml_parse($content);
    $final_rule_index = array_search('MATCH,Proxy', $config['rules'], true);
    $direct_rules = [
        'DOMAIN-SUFFIX,servicewechat.com,DIRECT',
        'DOMAIN-SUFFIX,wechat.com,DIRECT',
        'DOMAIN-SUFFIX,local,DIRECT',
        'DOMAIN-SUFFIX,localhost,DIRECT',
        'DOMAIN-SUFFIX,localhost.localdomain,DIRECT',
        'DOMAIN-SUFFIX,localdomain,DIRECT',
        'DOMAIN-SUFFIX,home.arpa,DIRECT',
        'DOMAIN-SUFFIX,lan,DIRECT',
        'DOMAIN-SUFFIX,internal,DIRECT',
        'DOMAIN-SUFFIX,intranet,DIRECT',
        'DOMAIN-SUFFIX,corp,DIRECT',
        'DOMAIN-SUFFIX,private,DIRECT',
        'DOMAIN-SUFFIX,router.asus.com,DIRECT',
        'DOMAIN-SUFFIX,tplinkwifi.net,DIRECT',
        'DOMAIN-SUFFIX,tplogin.cn,DIRECT',
        'DOMAIN-SUFFIX,tendawifi.com,DIRECT',
        'DOMAIN-SUFFIX,router.ctc,DIRECT',
        'DOMAIN-SUFFIX,my.router,DIRECT',
        'DOMAIN-SUFFIX,fritz.box,DIRECT',
        'DOMAIN-SUFFIX,myrouter.local,DIRECT',
        'DOMAIN-SUFFIX,routerlogin.net,DIRECT',
        'DOMAIN-SUFFIX,linksyssmartwifi.com,DIRECT',
        'DOMAIN-SUFFIX,synology.me,DIRECT',
        'DOMAIN-SUFFIX,myqnapcloud.com,DIRECT',
        'IP-CIDR,169.254.0.0/16,DIRECT,no-resolve',
        'IP-CIDR6,::1/128,DIRECT,no-resolve',
        'IP-CIDR6,fc00::/7,DIRECT,no-resolve',
        'IP-CIDR6,fe80::/10,DIRECT,no-resolve',
        'GEOIP,PRIVATE,DIRECT',
        'DOMAIN-SUFFIX,doubao.com,DIRECT',
        'DOMAIN-SUFFIX,zijieapi.com,DIRECT',
        'DOMAIN-SUFFIX,tgalileo.com,DIRECT',
        'DOMAIN-SUFFIX,qianwen.com,DIRECT',
        'DOMAIN-SUFFIX,windows.com,DIRECT',
        'DOMAIN-SUFFIX,msftconnecttest.com,DIRECT',
        'DOMAIN-SUFFIX,msftncsi.com,DIRECT',
        'DOMAIN-SUFFIX,bilivideo.com,DIRECT',
        'DOMAIN-SUFFIX,dingtalkapps.com,DIRECT',
        'DOMAIN-SUFFIX,xiaohongshu.com,DIRECT',
        'DOMAIN-SUFFIX,xhscdn.com,DIRECT',
        'DOMAIN-SUFFIX,meituan.net,DIRECT',
        'DOMAIN-SUFFIX,larkenterprise.com,DIRECT',
        'DOMAIN-SUFFIX,microsoftvvare.net,DIRECT',
    ];

    expect($content)
        ->toContain('proxies:')
        ->toContain('name: Tokyo[1x]')
        ->toContain('server: tokyo.example.com')
        ->toContain('uuid: '.$user->uuid)
        ->and($config['dns']['enable'])->toBeFalse()
        ->and($final_rule_index)->toBeInt()
        ->and($config['rules'])->toHaveCount(count(array_unique($config['rules'])));

    foreach ($direct_rules as $direct_rule) {
        $direct_rule_index = array_search($direct_rule, $config['rules'], true);

        expect($direct_rule_index)
            ->toBeInt()
            ->toBeLessThan($final_rule_index);
    }
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
