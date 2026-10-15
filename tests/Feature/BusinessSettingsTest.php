<?php

use App\Filament\Pages\BusinessSettings as SettingsPage;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BalanceReminder;
use App\Services\BusinessSettings;
use App\Services\TrafficBillingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\HttpClient\CurlClient;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Supports\Collection;

test('initialization preserves effective configuration and never overwrites settings', function () {
    config(['yap.unit_price' => '0.07']);
    $this->artisan('app:initialize-settings')->assertSuccessful();
    expect(Setting::where('key', 'unit_price')->value('value'))->toBe('0.07');
    Setting::where('key', 'unit_price')->update(['value' => '0.09']);
    $this->artisan('app:initialize-settings')->assertSuccessful();
    expect(Setting::count())->toBe(5)
        ->and(app(BusinessSettings::class)->get('unit_price'))->toBe('0.09');
});

test('settings are consistent within a lifecycle and fresh in the next lifecycle', function () {
    Setting::create(['key' => 'unit_price', 'value' => '0.04']);
    $settings = app(BusinessSettings::class);
    expect($settings->get('unit_price'))->toBe('0.04');
    Setting::where('key', 'unit_price')->update(['value' => '0.06']);
    expect($settings->get('unit_price'))->toBe('0.04');
    app()->forgetScopedInstances();
    expect(app(BusinessSettings::class)->get('unit_price'))->toBe('0.06');
});

test('invalid persisted settings fail instead of falling back to environment prices', function () {
    Setting::create(['key' => 'unit_price', 'value' => 'invalid']);
    app(BusinessSettings::class)->get('unit_price');
})->throws(ValidationException::class);

test('homepage uses database unit price without changing package sale price', function () {
    Setting::create(['key' => 'unit_price', 'value' => '0.08']);
    Package::factory()->create(['status' => Package::STATUS_ACTIVE, 'traffic_limit' => 10 * 1024 ** 3, 'price' => '0.50']);
    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('unitPrice', 0.08)
        ->where('packages.0.price', fn ($price) => (float) $price === 0.5)
        ->where('packages.0.original_price', 0.8));
});

test('owner can save validated settings with a durable audit trail', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(SettingsPage::class)->fillForm([
        'usd_rmb_rate' => '7.123456', 'usd_jpy_rate' => '150.5',
        'unit_price' => '0.05', 'reset_subscription_price' => '0.75',
        'balance_reminder_threshold' => '1.20',
    ])->call('save')->assertHasNoFormErrors()->assertNotified();
    expect(Setting::where('key', 'unit_price')->value('value'))->toBe('0.05');
    $this->assertDatabaseHas('setting_changes', ['key' => 'unit_price', 'new_value' => '0.05', 'user_id' => 1]);
});

test('settings reject invalid prices and exchange rates atomically', function (string $key, string $value) {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(SettingsPage::class)->fillForm([$key => $value])->call('save')->assertHasFormErrors();
    expect(Setting::count())->toBe(0)->and(DB::table('setting_changes')->count())->toBe(0);
})->with([
    ['unit_price', '-1'], ['unit_price', '0.001'], ['unit_price', '1000000'],
    ['usd_rmb_rate', '0'], ['usd_jpy_rate', '1e2'], ['usd_rmb_rate', '7.1234567'],
]);

test('non owners cannot access or invoke settings saves', function () {
    $this->actingAs(User::factory()->create(['id' => 2]));
    $this->get(SettingsPage::getUrl())->assertForbidden();
    Livewire::test(SettingsPage::class)->assertForbidden();
    expect(Setting::count())->toBe(0);
});

test('alipay orders snapshot the database exchange rate and retain their original pricing', function () {
    Setting::create(['key' => 'usd_rmb_rate', 'value' => '7.25']);
    $user = User::factory()->create();
    $gateway = Mockery::mock(Alipay::class);
    $gateway->shouldReceive('scan')->once()->with(Mockery::on(fn (array $order): bool => $order['total_amount'] === '36.25'))
        ->andReturn(new Collection(['code' => '10000', 'qr_code' => 'https://example.test/qr']));
    app()->instance('pay.alipay', $gateway);
    $this->actingAs($user)->post(route('alipay.newOrder'), ['amount' => 5])->assertRedirect();
    $payment = $user->payments()->sole();
    expect($payment->payload['pricing'])->toBe(['currency' => 'cny', 'amount' => '36.25', 'exchange_rate' => '7.25']);
    Setting::where('key', 'usd_rmb_rate')->update(['value' => '8']);
    expect($payment->fresh()->payload['pricing']['amount'])->toBe('36.25');
});

test('stripe orders use database rates and persist the quoted rate', function (string $currency, string $rate, int $minor_amount, string $amount) {
    config(['yap.payment.stripe.secret' => 'sk_test_settings']);
    Setting::create(['key' => $currency === 'jpy' ? 'usd_jpy_rate' : 'usd_rmb_rate', 'value' => $rate]);
    $client = Mockery::mock(ClientInterface::class);
    $client->shouldReceive('request')->once()->withArgs(function ($method, $url, $headers, $params) use ($minor_amount, $currency): bool {
        return $method === 'post' && str_ends_with($url, '/checkout/sessions')
            && $params['line_items'][0]['price_data']['unit_amount'] === $minor_amount
            && $params['line_items'][0]['price_data']['currency'] === $currency;
    })->andReturn([json_encode(['id' => 'cs_test_settings', 'object' => 'checkout.session', 'url' => 'https://example.test/checkout']), 200, []]);
    ApiRequestor::setHttpClient($client);
    try {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('stripe.newOrder'), ['amount' => 5, 'currency' => $currency])->assertRedirect();
        $quoted = $user->payments()->sole()->payload[Payment::STATUS_CREATED];
        expect($quoted['exchange_rate'])->toBe($rate)->and($quoted['amount'])->toBe($amount);
    } finally {
        ApiRequestor::setHttpClient(new CurlClient);
    }
})->with([
    ['cny', '7.25', 3625, '36.25'],
    ['jpy', '150.5', 753, '753'],
]);

test('traffic settlement uses the database price rather than the environment fallback', function () {
    Notification::fake();
    Setting::create(['key' => 'unit_price', 'value' => '0.07']);
    $user = User::factory()->create(['balance' => '10.00', 'traffic_unpaid' => 2 * 1024 ** 3 + 1]);
    app(TrafficBillingService::class)->settle($user);
    expect($user->fresh()->balance)->toBe('9.86')
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(1);
});

test('subscription reset charges the configured database price', function () {
    Queue::fake();
    Setting::create(['key' => 'reset_subscription_price', 'value' => '0.75']);
    $user = User::factory()->create(['balance' => '10.00']);
    $this->actingAs($user)->post(route('customer.service.resetSubscription'))->assertRedirect(route('customer.service'));
    expect($user->fresh()->balance)->toBe('9.25');
    $this->assertDatabaseHas('balance_details', ['user_id' => $user->id, 'amount' => '-0.75']);
});

test('balance reminder uses the database threshold', function () {
    Notification::fake();
    Setting::create(['key' => 'balance_reminder_threshold', 'value' => '2.00']);
    $user = User::factory()->create(['balance' => '5.00']);
    $user->update(['balance' => '1.50']);
    Notification::assertSentTo($user, BalanceReminder::class);
});

test('saving settings invalidates report results after commit', function () {
    $owner = User::factory()->create(['id' => 1]);
    Cache::forever('admin_dashboard_report_version', 'before-settings-change');
    $settings = app(BusinessSettings::class);
    $settings->save([...$settings->all(), 'unit_price' => '0.12'], $owner);
    expect(Cache::get('admin_dashboard_report_version'))->not->toBe('before-settings-change');
});

test('a new artisan command refreshes the settings snapshot', function () {
    Setting::create(['key' => 'unit_price', 'value' => '0.04']);
    expect(app(BusinessSettings::class)->get('unit_price'))->toBe('0.04');
    Setting::where('key', 'unit_price')->update(['value' => '0.06']);
    $this->artisan('app:initialize-settings')->assertSuccessful();
    expect(app(BusinessSettings::class)->get('unit_price'))->toBe('0.06');
});
