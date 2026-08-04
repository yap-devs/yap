<?php

use App\Models\User;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config([
        'yap.turnstile.site_key' => 'test-site-key',
        'yap.turnstile.secret_key' => 'test-secret-key',
        'yap.turnstile.hostname' => 'localhost, register.example.com, ',
        'yap.turnstile.action' => 'register',
    ]);

    Http::preventStrayRequests();
});

function turnstileRegistrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf-turnstile-response' => 'valid-token',
    ], $overrides);
}

test('registration screen exposes only the public turnstile site key', function () {
    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Auth/Register')
            ->where('turnstileSiteKey', 'test-site-key')
            ->missing('turnstileSecretKey')
        );
});

test('new users can register from any configured turnstile hostname', function (string $hostname) {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
            'hostname' => $hostname,
            'action' => 'register',
        ]),
    ]);

    $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->post('/register', turnstileRegistrationData());

    $this->assertAuthenticated();
    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    Http::assertSent(function (ClientRequest $request) {
        return $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token'
            && $request['remoteip'] === '203.0.113.10';
    });
})->with([
    'first hostname' => 'localhost',
    'second hostname' => 'register.example.com',
]);

test('a missing turnstile token rejects registration without creating a user', function () {
    Http::fake();

    $response = $this->post('/register', turnstileRegistrationData([
        'cf-turnstile-response' => null,
    ]));

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    Http::assertNothingSent();
});

test('unsafe email local parts reject registration before turnstile verification', function (string $email) {
    Http::fake();

    $response = $this->post('/register', turnstileRegistrationData([
        'email' => $email,
    ]));

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
    expect(User::query()->where('email', $email)->exists())->toBeFalse();
    Http::assertNothingSent();
})->with([
    'shell operators' => "test'&&id#@ed25519.de",
    'shell variable expansion' => 'test${ifs}@ed25519.de',
    'brace expansion' => 'a{b@ed25519.de',
    'command substitution' => 'a$(id)b@ed25519.de',
    'unicode confusable' => 'аdmin@ed25519.de',
]);

test('a common email with a plus alias can register', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
            'hostname' => 'localhost',
            'action' => 'register',
        ]),
    ]);

    $email = 'test.user+tag@example.com';
    $response = $this->post('/register', turnstileRegistrationData([
        'email' => $email,
    ]));

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
    expect(User::query()->where('email', $email)->exists())->toBeTrue();
});

test('an unsuccessful siteverify response rejects registration', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => false,
        ]),
    ]);

    $response = $this->post('/register', turnstileRegistrationData());

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a non successful siteverify http status rejects registration', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([], 503),
    ]);

    $response = $this->post('/register', turnstileRegistrationData());

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('a siteverify network failure rejects registration', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::failedConnection(),
    ]);

    $response = $this->post('/register', turnstileRegistrationData());

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
});

test('siteverify hostname and action must match', function (array $turnstile_response) {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response($turnstile_response),
    ]);

    $response = $this->post('/register', turnstileRegistrationData());

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
})->with([
    'hostname mismatch' => [[
        'success' => true,
        'hostname' => 'attacker.example.com',
        'action' => 'register',
    ]],
    'action mismatch' => [[
        'success' => true,
        'hostname' => 'localhost',
        'action' => 'login',
    ]],
]);

test('missing turnstile configuration fails closed', function (string $config_key, string $value) {
    config([$config_key => $value]);
    Http::fake();

    $response = $this->post('/register', turnstileRegistrationData());

    $response->assertSessionHasErrors('cf-turnstile-response');
    $this->assertGuest();
    expect(User::query()->where('email', 'test@example.com')->exists())->toBeFalse();
    Http::assertNothingSent();
})->with([
    'site key' => ['yap.turnstile.site_key', ''],
    'secret key' => ['yap.turnstile.secret_key', ''],
    'hostnames' => ['yap.turnstile.hostname', ' , '],
    'action' => ['yap.turnstile.action', ''],
]);

test('registration post requests are rate limited by ip', function () {
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => false,
        ]),
    ]);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
            ->post('/register', turnstileRegistrationData())
            ->assertSessionHasErrors('cf-turnstile-response');
    }

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.20'])
        ->post('/register', turnstileRegistrationData())
        ->assertTooManyRequests();
});
