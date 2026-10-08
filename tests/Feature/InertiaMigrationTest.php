<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('inertia resolves existing pages in the project directory', function () {
    $this->get('/login')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Auth/Login')
        ->has('translations')
        ->where('auth.user', null)
    );
});

test('inertia remains client rendered and uses the new initial page protocol', function () {
    expect(config('inertia.ssr.enabled'))->toBeFalse();

    $response = $this->get('/login')->assertOk();
    $response->assertSee('type="application/json"', false)
        ->assertSee('data-inertia', false)
        ->assertSee('<script data-page="app" type="application/json">', false)
        ->assertSee('<div id="app"></div>', false);
});

test('inertia profile updates preserve validation and redirect semantics', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->from('/profile')
        ->withHeader('X-Inertia', 'true')
        ->patch('/profile', ['name' => 'Updated name', 'email' => $user->email])
        ->assertStatus(303)->assertRedirect('/profile')->assertSessionHasNoErrors();

    $this->flushHeaders();
    $this->get('/profile')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Profile/Edit')->where('auth.user.name', 'Updated name')
    );

    $this->from('/profile')->withHeader('X-Inertia', 'true')->patch('/profile', ['name' => '', 'email' => 'invalid'])
        ->assertStatus(303)->assertSessionHasErrors(['name', 'email']);
});
