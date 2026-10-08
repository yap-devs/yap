<?php

use App\Models\Node;
use App\Models\User;
use App\Services\NodeConfigurationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGithubAccount(int $github_id): SocialiteUser
{
    return (new SocialiteUser)
        ->map([
            'id' => $github_id,
            'nickname' => 'github-user-'.$github_id,
        ])
        ->setRaw(['created_at' => '2015-01-01T00:00:00Z'])
        ->setToken('github-token-'.$github_id);
}

test('github unlinking revokes cached node authorization immediately', function () {
    Notification::fake();
    config(['node_agent.enabled' => false, 'node_agent.snapshot_store' => 'array']);
    $node = Node::factory()->create();
    $user = User::factory()->create([
        'balance' => '0.00',
        'github_id' => 7007,
        'github_created_at' => '2015-01-01T00:00:00Z',
    ]);
    config(['node_agent.enabled' => true]);
    $service = app(NodeConfigurationService::class);
    $before = $service->snapshot($node->fresh());
    expect(array_column($before['users'], 'id'))->toContain($user->id);

    $this->actingAs($user)->delete(route('github.destroy'))->assertRedirect('/profile');

    $after = $service->snapshot($node->fresh());
    expect($user->fresh()->is_valid)->toBeFalse()
        ->and($after['revision'])->toBeGreaterThan($before['revision'])
        ->and(array_column($after['users'], 'id'))->not->toContain($user->id)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('github unlinking rolls back if its durable authorization notification cannot be stored', function () {
    Notification::fake();
    config(['node_agent.enabled' => false]);
    $user = User::factory()->create([
        'github_id' => 8008,
        'github_created_at' => '2015-01-01T00:00:00Z',
    ]);
    config(['node_agent.enabled' => true, 'queue.connections.database.connection' => 'separate_queue']);
    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($user)->delete(route('github.destroy')))
        ->toThrow(LogicException::class, 'application connection');

    expect($user->fresh()->github_id)->toBe(8008)
        ->and($user->github_created_at)->not->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('a stale github unlink request cannot clear a newer binding or refresh node authorization', function (?int $current_github_id) {
    Notification::fake();
    config(['node_agent.enabled' => false]);
    $node = Node::factory()->create();
    $user = User::factory()->create(['github_id' => 9009, 'github_created_at' => '2015-01-01T00:00:00Z']);
    User::whereKey($user->id)->update(['github_id' => $current_github_id]);
    $revision = $node->fresh()->desired_revision;
    config(['node_agent.enabled' => true]);

    $this->actingAs($user)->delete(route('github.destroy'))->assertRedirect('/profile');

    expect($user->fresh()->github_id)->toBe($current_github_id)
        ->and($node->fresh()->desired_revision)->toBe($revision)
        ->and(DB::table('jobs')->count())->toBe(0);
})->with([null, 9010]);

test('unlinking an already unlinked github account does not dispatch a sync job', function () {
    $user = User::factory()->create();
    Bus::fake();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});

test('github binding is limited to twice per day independently from unlinking', function () {
    $user = User::factory()->create();
    Bus::fake();

    Socialite::fake('github', fakeGithubAccount(1001));
    $this->actingAs($user)
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Socialite::fake('github', fakeGithubAccount(2002));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Socialite::fake('github', fakeGithubAccount(3003));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertTooManyRequests();

    expect($user->refresh()->github_id)->toBeNull();

    $this->travel(24)->hours();
    $this->travel(1)->seconds();

    Socialite::fake('github', fakeGithubAccount(3003));
    $this->actingAs($user)
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    expect($user->refresh()->github_id)->toBe(3003);
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});

test('github unlinking is limited to twice per day independently from binding', function () {
    $user = User::factory()->create([
        'github_id' => 4001,
        'github_nickname' => 'github-user-4001',
        'github_token' => 'github-token-4001',
        'github_created_at' => '2015-01-01T00:00:00Z',
    ]);
    Bus::fake();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Socialite::fake('github', fakeGithubAccount(4002));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Socialite::fake('github', fakeGithubAccount(4003));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertTooManyRequests();

    expect($user->refresh()->github_id)->toBe(4003);
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});

test('github binding rate limit cannot be bypassed with different local users', function () {
    $github_id = 5005;
    $users = User::factory()->count(3)->create();
    Bus::fake();

    foreach ($users->take(2) as $user) {
        Socialite::fake('github', fakeGithubAccount($github_id));
        $this->actingAs($user)
            ->get('/auth/github/callback')
            ->assertRedirect('/profile');

        User::query()->whereKey($user->getKey())->update([
            'github_id' => null,
            'github_nickname' => '',
            'github_token' => '',
            'github_created_at' => null,
        ]);
    }

    Socialite::fake('github', fakeGithubAccount($github_id));
    $this->actingAs($users->last())
        ->get('/auth/github/callback')
        ->assertTooManyRequests();

    expect($users->last()->refresh()->github_id)->toBeNull();
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});

test('github unlink rate limit cannot be bypassed with different local users', function () {
    $github_id = 6006;
    $first_user = User::factory()->create([
        'github_id' => $github_id,
        'github_nickname' => 'github-user-'.$github_id,
        'github_token' => 'github-token-'.$github_id,
        'github_created_at' => '2015-01-01T00:00:00Z',
    ]);
    $second_user = User::factory()->create();
    Bus::fake();

    $this->actingAs($first_user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Socialite::fake('github', fakeGithubAccount($github_id));
    $this->actingAs($second_user)
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    $this->actingAs($second_user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Socialite::fake('github', fakeGithubAccount($github_id));
    $this->actingAs($first_user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');

    $this->actingAs($first_user)
        ->delete(route('github.destroy'))
        ->assertTooManyRequests();

    expect($first_user->refresh()->github_id)->toBe($github_id);
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});
