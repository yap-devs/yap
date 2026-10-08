<?php

use App\Filament\Resources\NodeRoutes\NodeRouteResource;
use App\Filament\Resources\Nodes\NodeResource;
use App\Filament\Resources\Nodes\Pages\ManageNodes;
use App\Filament\Resources\TrafficRecords\TrafficRecordResource;
use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    config(['node_agent.enabled' => true, 'subscription.content_store' => 'array', 'subscription.lock_store' => 'array']);
    Bus::fake();
});

test('guests cannot access node management or raw traffic resources', function (string $resource) {
    $this->get($resource::getUrl())->assertRedirect(route('filament.admin.auth.login'));
})->with([NodeResource::class, NodeRouteResource::class, TrafficRecordResource::class]);

test('ordinary users cannot access node resources through http or livewire', function (string $resource) {
    $this->actingAs(User::factory()->create(['id' => 2]));

    $this->get($resource::getUrl())->assertForbidden();
    $page = $resource::getPages()['index']->getPage();
    Livewire::test($page)->assertForbidden();
})->with([NodeResource::class, NodeRouteResource::class, TrafficRecordResource::class]);

test('subscription lookup rejects sql and path traversal payloads', function (string $uuid) {
    User::factory()->create(['balance' => 10]);
    $this->get('/clash/'.rawurlencode($uuid).'/yap.yaml')->assertNotFound();
    $this->get('/sub/'.rawurlencode($uuid).'/yap.txt')->assertNotFound();
})->with(["' OR 1=1 --", '../../.env', '../storage/app/private/mysql-backup-status.json', 'uuid";touch /tmp/yap-injected;#']);

test('private operation files have no public download route', function (string $path) {
    $this->get($path)->assertNotFound();
})->with([
    '/storage/app/private/mysql-backup-status.json',
    '/storage/app/private/scheduler-status.json',
    '/storage/app/private/backups/.credentials-test',
    '/docs/deployment/rental-cron/config.sh',
]);

test('a copied subscription cache entry cannot expose another accounts credentials', function () {
    $victim = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 10]);
    $attacker = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 10]);
    NodeRoute::factory()->create();
    $service = app(SubscriptionService::class);
    $service->content($victim, 'clash');
    Cache::put($service->cacheKey($attacker, 'clash'), Cache::get($service->cacheKey($victim, 'clash')), 600);

    $response = $this->get(route('subscription.clash', ['uuid' => $attacker->uuid]))->assertOk();
    $proxies = Yaml::parse($response->getContent())['proxies'];
    expect($proxies)->not->toBeEmpty();
    foreach ($proxies as $proxy) {
        expect($proxy['uuid'])->toBe($attacker->uuid)->not->toBe($victim->uuid);
    }
});

test('cached credentials cannot bypass subscription revocation after debt or deletion', function (string $revocation) {
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 10, 'github_created_at' => null]);
    NodeRoute::factory()->create();
    $service = app(SubscriptionService::class);
    $service->warmCache($user);
    $cached = Cache::get($service->cacheKey($user, 'clash'));
    if ($revocation === 'debt') {
        $user->update(['balance' => -100]);
    } else {
        $user->delete();
    }
    Cache::put($service->cacheKey($user, 'clash'), $cached, 600);

    $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertNotFound();
    $this->get(route('subscription.universal', ['uuid' => $user->uuid]))->assertNotFound();
})->with(['debt', 'deleted']);

test('extra profile fields cannot escalate panel access or restore subscription eligibility', function () {
    $user = User::factory()->create(['id' => 2, 'uuid' => (string) Str::uuid(), 'balance' => -100, 'github_created_at' => null]);
    $uuid = $user->uuid;
    $this->actingAs($user)->patch(route('profile.update'), [
        'name' => 'Attacker',
        'email' => $user->email,
        'id' => 1,
        'balance' => 999999,
        'uuid' => 'attacker-selected-token',
        'github_created_at' => '1970-01-01',
    ])->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->id)->toBe(2)->and($user->balance)->toBe('-100.00')
        ->and($user->uuid)->toBe($uuid)->and($user->github_created_at)->toBeNull()
        ->and($user->is_valid)->toBeFalse();
    $this->get(NodeResource::getUrl())->assertForbidden();
});

test('node create actions discard security metadata outside their form schema', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(ManageNodes::class)->callAction('create', [
        'name' => 'Metadata injection probe',
        'enabled' => false,
        'core_config' => '{}',
        'agent_token_hash' => hash('sha256', 'attacker-token'),
        'traffic_source' => 'legacy',
        'applied_revision' => 999999,
    ])->assertHasNoActionErrors();

    $node = Node::where('name', 'Metadata injection probe')->firstOrFail();
    expect($node->agent_token_hash)->toBeNull()->and($node->traffic_source)->toBe('agent')
        ->and($node->applied_revision)->toBe(0);
});
