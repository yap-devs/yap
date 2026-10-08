<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

test('react reports render errors when sentry is disabled', function () {
    $page = visit('/login')->assertSee('Log in')->assertNoJavaScriptErrors();
    $page->script(<<<'JS'
        (() => {
          window.expectedRenderErrors = [];
          window.addEventListener('error', (event) => {
            window.expectedRenderErrors.push(event.message);
          }, {once: true});
          // Intentionally trigger a render error to verify React's default reporting.
          window.YAP_TRANSLATIONS.common.network_error = {invalid: 'translation'};
          document.dispatchEvent(new CustomEvent('inertia:networkError', {
            cancelable: true, detail: {error: {code: 'ERR_NETWORK'}}
          }));
        })()
        JS);

    $page->assertScript('document.querySelector("#app").childElementCount', 0)
        ->assertScript('window.expectedRenderErrors.length', 1);
});

test('login and profile edits work through inertia navigation', function () {
    $user = User::factory()->create(['uuid' => (string) Str::uuid()]);

    $page = visit('/login')
        ->fill('#email', $user->email)
        ->fill('#password', 'password')
        ->click('Log in')
        ->assertPathIs('/dashboard')
        ->assertNoJavaScriptErrors();

    $page->navigate('/profile')
        ->fill('#name', 'Browser migration user')
        ->click('form:has(#name) button')
        ->assertSee('Saved.')
        ->assertNoJavaScriptErrors();

    expect($user->fresh()->name)->toBe('Browser migration user');
});

test('inertia exception events retain user friendly error messages', function () {
    $page = visit('/login')->assertSee('Log in');

    expect($page->script("document.dispatchEvent(new CustomEvent('inertia:httpException', {cancelable: true, detail: {response: {status: 429}}}))"))->toBeFalse();
    $page->assertSee('Too many requests, please try again later.');

    expect($page->script("document.dispatchEvent(new CustomEvent('inertia:networkError', {cancelable: true, detail: {error: new Error('Failed to fetch')}}))"))->toBeFalse();
    $page->assertSee('Network interrupted, please try again.')->assertNoJavaScriptErrors();
});

test('inertia xhr failures show a toast without an unhandled rejection', function (string $action) {
    $page = visit('/login')->assertSee('Log in');

    $page->script(<<<'JS'
        (() => {
          window.networkFailure = null;
          window.unhandledRejections = [];
          window.addEventListener('unhandledrejection', (event) => {
            window.unhandledRejections.push(String(event.reason));
          });
          document.addEventListener('inertia:networkError', (event) => {
            window.networkFailure = {
              code: event.detail.error.code,
              name: event.detail.error.name,
              prevented: event.defaultPrevented,
            };
          }, {once: true});
          const originalSend = XMLHttpRequest.prototype.send;
          XMLHttpRequest.prototype.send = function () {
            XMLHttpRequest.prototype.send = originalSend;
            queueMicrotask(() => this.dispatchEvent(new ProgressEvent('error')));
          };
        })()
        JS);

    if ($action === 'navigation') {
        $page->click('Forgot your password?');
    } else {
        $page->fill('#email', 'network@example.com')->fill('#password', 'password')->click('Log in');
    }

    $page->assertSee('Network interrupted, please try again.')
        ->assertScript('window.networkFailure', ['code' => 'ERR_NETWORK', 'name' => 'HttpNetworkError', 'prevented' => true])
        ->assertScript('window.unhandledRejections.length', 0)
        ->assertPathIs('/login')
        ->assertNoJavaScriptErrors();
})->with(['navigation', 'form submission']);

test('alipay qr rendering and paid polling return to the profile', function () {
    $user = User::factory()->create();
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'browser-migration-order',
        'payload' => [Payment::STATUS_CREATED => [
            'qr_code' => 'https://example.invalid/pay',
            'out_trade_no' => 'browser-migration-order',
        ]],
    ]);

    $this->actingAs($user);

    visit('/alipay/'.$payment->id.'/scan')
        ->assertPathIs('/profile')
        ->assertSee('Profile Information')
        ->assertNoJavaScriptErrors();
});

test('locale changes update translations on replacement visits and history', function () {
    $page = visit('/login')->assertSee('Log in');
    $page->select('select:not([multiple])', 'zh_CN')->assertSee('登录');
    $page->click('忘记密码？')->assertPathIs('/forgot-password');
    $page->select('select:not([multiple])', 'en')->assertSee('Email Password Reset Link');
    $page->back()->assertPathIs('/login')->assertSee('登录')->assertNoJavaScriptErrors();
});

test('alipay closed orders retain the qr canvas and stop navigation', function () {
    $user = User::factory()->create();
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CANCELLED,
        'amount' => 5,
        'remote_id' => 'closed-browser-order',
        'payload' => [Payment::STATUS_CREATED => [
            'qr_code' => 'https://example.invalid/pay',
            'out_trade_no' => 'closed-browser-order',
        ]],
    ]);
    $this->actingAs($user);
    visit('/alipay/'.$payment->id.'/scan')->assertSee('Payment closed!')
        ->assertPresent('canvas[width]')
        ->assertScript('document.querySelector("canvas").getBoundingClientRect().width', 256)
        ->assertPathIs('/alipay/'.$payment->id.'/scan')->assertNoJavaScriptErrors();
});
