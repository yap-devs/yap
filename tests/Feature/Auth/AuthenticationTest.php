<?php

use App\Models\User;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Auth;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('stale remember cookies leave the visitor signed out without breaking pages', function (string $state, string $path) {
    $user = User::factory()->create(['id' => 1, 'remember_token' => 'current-token']);
    $guard = Auth::guard('web');
    $cookie = $user->id.'|current-token|'.$guard->hashPasswordForCookie($user->getAuthPassword());
    if ($state === 'revoked') {
        $user->update(['remember_token' => 'replacement-token']);
    } elseif ($state === 'deleted') {
        $user->delete();
    } elseif ($state === 'password changed') {
        $user->update(['password' => 'replacement-password']);
    }

    $cookie_name = 'remember_web_'.sha1(SessionGuard::class);
    $response = $this->withCookie($cookie_name, $cookie)->get($path === 'admin' ? '/'.trim(config('yap.admin_panel_path'), '/') : '/');

    if ($path === 'admin') {
        $response->assertRedirect();
    } else {
        $response->assertOk();
    }
    $this->assertGuest();
    expect(Auth::viaRemember())->toBeFalse();
})->with(['revoked', 'deleted', 'password changed'])->with(['home', 'admin']);

test('valid remember cookies restore the session for current and legacy formats', function (bool $legacy_cookie) {
    $user = User::factory()->create(['remember_token' => 'current-token']);
    $guard = Auth::guard('web');
    $cookie_hash = $legacy_cookie ? $user->getAuthPassword() : $guard->hashPasswordForCookie($user->getAuthPassword());
    $cookie = $user->id.'|current-token|'.$cookie_hash;

    $response = $this->withCookie('remember_web_'.sha1(SessionGuard::class), $cookie)->get('/');

    $response->assertOk();
    $this->assertAuthenticatedAs($user);
    expect(Auth::viaRemember())->toBeTrue();
})->with([true, false]);

test('existing login sessions retain their authentication after the guard fix', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['login_web_'.sha1(SessionGuard::class) => $user->id])->get('/');

    $response->assertOk();
    $this->assertAuthenticatedAs($user);
    expect(Auth::viaRemember())->toBeFalse();
});
