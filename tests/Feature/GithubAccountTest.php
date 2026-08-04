<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use Illuminate\Bus\UniqueLock;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
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

function releaseGithubSyncJobUniqueLock(): void
{
    Cache::lock(UniqueLock::getKey(new GenerateClashProfileLink))->forceRelease();
}

test('unlinking an already unlinked github account does not dispatch a sync job', function () {
    $user = User::factory()->create();
    Bus::fake();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');

    Bus::assertNotDispatched(GenerateClashProfileLink::class);
});

test('github binding is limited to twice per day independently from unlinking', function () {
    $user = User::factory()->create();
    Bus::fake();

    Socialite::fake('github', fakeGithubAccount(1001));
    $this->actingAs($user)
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    Socialite::fake('github', fakeGithubAccount(2002));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

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
    Bus::assertDispatchedTimes(GenerateClashProfileLink::class, 5);
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
    releaseGithubSyncJobUniqueLock();

    Socialite::fake('github', fakeGithubAccount(4002));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    Socialite::fake('github', fakeGithubAccount(4003));
    $this->actingAs($user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    $this->actingAs($user)
        ->delete(route('github.destroy'))
        ->assertTooManyRequests();

    expect($user->refresh()->github_id)->toBe(4003);
    Bus::assertDispatchedTimes(GenerateClashProfileLink::class, 4);
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
        releaseGithubSyncJobUniqueLock();

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
    Bus::assertDispatchedTimes(GenerateClashProfileLink::class, 2);
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
    releaseGithubSyncJobUniqueLock();

    Socialite::fake('github', fakeGithubAccount($github_id));
    $this->actingAs($second_user)
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    $this->actingAs($second_user)
        ->delete(route('github.destroy'))
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    Socialite::fake('github', fakeGithubAccount($github_id));
    $this->actingAs($first_user->refresh())
        ->get('/auth/github/callback')
        ->assertRedirect('/profile');
    releaseGithubSyncJobUniqueLock();

    $this->actingAs($first_user)
        ->delete(route('github.destroy'))
        ->assertTooManyRequests();

    expect($first_user->refresh()->github_id)->toBe($github_id);
    Bus::assertDispatchedTimes(GenerateClashProfileLink::class, 4);
});
