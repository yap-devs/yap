<?php

use App\Models\AffiliateCommission;
use App\Models\AffiliateLevel;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Affiliate\AffiliateDashboardService;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

test('referred registration is visible before payment', function () {
    $this->withoutVite();
    $this->seed(AffiliateLevelSeeder::class);
    $referrer = User::factory()->create();
    $referred = User::factory()->create();

    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);

    AffiliateReferral::create([
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'code' => $promoter->code,
        'status' => AffiliateReferral::STATUS_REGISTERED,
        'registered_at' => now(),
    ]);

    $dashboard = app(AffiliateDashboardService::class)->dashboard($referrer);

    expect($dashboard['referrals'][0]['status'])->toBe(AffiliateReferral::STATUS_REGISTERED);

    $response = $this->actingAs($referrer)->get(route('affiliate'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Affiliate/Index')
        ->where('affiliate.referrals.0.status', AffiliateReferral::STATUS_REGISTERED)
    );
});

test('affiliate page is not available when disabled', function () {
    config(['affiliate.enabled' => false]);
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('affiliate'));

    $response->assertNotFound();
});

test('qualified payment enables package commission without crediting immediately', function () {
    $this->seed(AffiliateLevelSeeder::class);
    config(['affiliate.pending_days' => 7]);

    $referrer = User::factory()->create();
    $referred = User::factory()->create(['balance' => 20]);
    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);

    $referrer->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 50,
        'remote_id' => 'referrer-paid',
    ]);

    $referral = AffiliateReferral::create([
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'code' => $promoter->code,
        'status' => AffiliateReferral::STATUS_REGISTERED,
        'registered_at' => now(),
    ]);

    $payment = $referred->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 20,
        'remote_id' => 'referred-paid',
    ]);

    app(AffiliateService::class)->handlePaymentPaid($payment);

    expect($referral->refresh()->status)->toBe(AffiliateReferral::STATUS_QUALIFIED);

    $package = Package::create([
        'name' => 'Test Package',
        'description' => 'Test',
        'status' => Package::STATUS_ACTIVE,
        'price' => 10,
        'duration_days' => 30,
        'traffic_limit' => 1024,
    ]);

    $this->actingAs($referred)->post(route('package.buy', $package))->assertRedirect(route('package'));

    $commission = AffiliateCommission::first();
    expect($commission)->not->toBeNull();
    expect($commission->status)->toBe(AffiliateCommission::STATUS_PENDING);
    expect((string) $commission->base_amount)->toBe('10.00');
    expect((string) $commission->amount)->toBe('1.00');
    expect((float) $referrer->refresh()->balance)->toBe(0.0);
});

test('pending commission is credited after hold period', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $referrer = User::factory()->create();
    $referred = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);

    $referral = AffiliateReferral::create([
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'code' => $promoter->code,
        'status' => AffiliateReferral::STATUS_EARNING,
        'registered_at' => now(),
        'qualified_at' => now(),
        'commission_expires_at' => now()->addDays(30),
    ]);

    AffiliateCommission::create([
        'referral_id' => $referral->id,
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'source_type' => AffiliateCommission::SOURCE_PACKAGE_PURCHASE,
        'source_id' => 123,
        'affiliate_level' => 3,
        'base_amount' => 10,
        'commission_rate' => 0.2,
        'amount' => 2,
        'status' => AffiliateCommission::STATUS_PENDING,
        'hold_until' => now()->subMinute(),
    ]);

    $credited = app(AffiliateService::class)->creditPendingCommissions();

    expect($credited)->toBe(1);
    expect((float) $referrer->refresh()->balance)->toBe(2.0);
    expect(AffiliateCommission::first()->status)->toBe(AffiliateCommission::STATUS_CREDITED);
});

test('default referral code remains compatible with cookie registration', function () {
    $this->withoutVite();
    config([
        'yap.turnstile.site_key' => 'test-site-key',
        'yap.turnstile.secret_key' => 'test-secret-key',
        'yap.turnstile.hostname' => 'localhost',
        'yap.turnstile.action' => 'register',
    ]);
    Http::fake([
        'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
            'success' => true,
            'hostname' => 'localhost',
            'action' => 'register',
        ]),
    ]);
    $this->seed(AffiliateLevelSeeder::class);
    $referrer = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);

    $system_code = AffiliateReferralCode::query()->where('promoter_id', $promoter->id)->sole();

    expect($system_code->code)->toBe($promoter->code)
        ->and($system_code->type)->toBe(AffiliateReferralCode::TYPE_SYSTEM);

    $this->get('/?ref='.strtoupper($promoter->code))
        ->assertCookie(AffiliateService::COOKIE_NAME, $promoter->code);

    $this->withCookie(AffiliateService::COOKIE_NAME, $promoter->code)->post('/register', [
        'name' => 'Referred User',
        'email' => 'referred@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'cf-turnstile-response' => 'valid-token',
    ])->assertRedirect(route('dashboard', absolute: false));

    $referred = User::query()->where('email', 'referred@example.com')->sole();
    $referral = AffiliateReferral::query()->where('referred_user_id', $referred->id)->sole();

    expect($referral->promoter_id)->toBe($promoter->id)
        ->and($referral->code)->toBe($promoter->code);
});

test('affiliate data migrations backfill existing state idempotently', function () {
    $this->seed(AffiliateLevelSeeder::class);
    AffiliateLevel::query()->update(['maximum_referral_codes' => 99]);

    $user = User::factory()->create();
    $created_at = now()->subDay()->startOfSecond();
    $promoter_id = DB::table('affiliate_promoters')->insertGetId([
        'user_id' => $user->id,
        'code' => 'historical-code',
        'status' => 'active',
        'custom_commission_rate' => null,
        'total_valid_referrals' => 0,
        'total_commission_amount' => 0,
        'created_at' => $created_at,
        'updated_at' => $created_at,
        'deleted_at' => null,
    ]);

    $code_migration = require database_path('migrations/2026_07_29_105923_backfill_affiliate_referral_codes.php');
    $level_migration = require database_path('migrations/2026_07_29_105936_set_affiliate_level_referral_code_limits.php');

    $code_migration->up();
    $code_migration->up();
    $level_migration->up();

    $codes = AffiliateReferralCode::query()->where('promoter_id', $promoter_id)->get();

    expect($codes)->toHaveCount(1)
        ->and($codes->sole()->code)->toBe('historical-code')
        ->and($codes->sole()->type)->toBe(AffiliateReferralCode::TYPE_SYSTEM)
        ->and(AffiliateLevel::query()->orderBy('level')->pluck('maximum_referral_codes', 'level')->all())
        ->toBe([0 => 1, 1 => 2, 2 => 3, 3 => 5, 4 => 8, 5 => 13]);
});

test('starter can create one normalized custom referral code', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $user = User::factory()->create();
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'starter-payment',
    ]);

    app(AffiliateService::class)->ensurePromoter($user);

    $this->actingAs($user)
        ->post(route('affiliate.codes.store'), ['code' => '  My-Code  '])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $custom_code = AffiliateReferralCode::query()->where('code', 'my-code')->sole();

    expect($custom_code->type)->toBe(AffiliateReferralCode::TYPE_CUSTOM)
        ->and($custom_code->status)->toBe(AffiliateReferralCode::STATUS_ACTIVE);
});

test('custom referral code validation reserves names permanently', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $first_user = User::factory()->create();
    $second_user = User::factory()->create();

    foreach ([$first_user, $second_user] as $index => $user) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_GITHUB,
            'status' => Payment::STATUS_PAID,
            'amount' => 5,
            'remote_id' => 'validation-payment-'.$index,
        ]);
        app(AffiliateService::class)->ensurePromoter($user);
    }

    $this->actingAs($first_user)
        ->post(route('affiliate.codes.store'), ['code' => 'reserved-name'])
        ->assertSessionHasNoErrors();

    $code = AffiliateReferralCode::query()->where('code', 'reserved-name')->sole();
    $this->patch(route('affiliate.codes.disable', $code))->assertSessionHasNoErrors();

    $this->actingAs($second_user)
        ->post(route('affiliate.codes.store'), ['code' => 'RESERVED-NAME'])
        ->assertSessionHasErrors('code');

    $this->post(route('affiliate.codes.store'), ['code' => 'admin'])
        ->assertSessionHasErrors('code');

    $this->post(route('affiliate.codes.store'), ['code' => '-invalid-'])
        ->assertSessionHasErrors('code');
});

test('referral code limits follow affiliate levels', function () {
    $this->seed(AffiliateLevelSeeder::class);

    expect(AffiliateLevel::query()->orderBy('level')->pluck('maximum_referral_codes', 'level')->all())
        ->toBe([0 => 1, 1 => 2, 2 => 3, 3 => 5, 4 => 8, 5 => 13]);

    $visitor = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($visitor);

    $this->actingAs($visitor)
        ->post(route('affiliate.codes.store'), ['code' => 'visitor-code'])
        ->assertSessionHasErrors('code');

    expect($promoter->referralCodes()->where('status', AffiliateReferralCode::STATUS_ACTIVE)->count())->toBe(1);
});

test('deactivated code releases its slot after the cooldown', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $user = User::factory()->create();
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'cooldown-payment',
    ]);
    app(AffiliateService::class)->ensurePromoter($user);

    $this->actingAs($user)->post(route('affiliate.codes.store'), ['code' => 'first-code']);
    $code = AffiliateReferralCode::query()->where('code', 'first-code')->sole();
    $this->patch(route('affiliate.codes.disable', $code))->assertSessionHasNoErrors();
    $disabled_at = $code->refresh()->disabled_at->toDateTimeString();

    $this->post(route('affiliate.codes.store'), ['code' => 'second-code'])
        ->assertSessionHasErrors('code');

    $this->travel(23)->hours();
    $this->patch(route('affiliate.codes.disable', $code))->assertSessionHasNoErrors();
    expect($code->refresh()->disabled_at->toDateTimeString())->toBe($disabled_at);

    $this->travel(1)->hours();
    $this->post(route('affiliate.codes.store'), ['code' => 'second-code'])
        ->assertSessionHasNoErrors();

    expect(AffiliateReferralCode::query()->where('code', 'second-code')->exists())->toBeTrue();
});

test('users cannot manage another promoter code or deactivate the system code', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $owner = User::factory()->create();
    $other_user = User::factory()->create();
    $owner->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'owner-payment',
    ]);
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    app(AffiliateService::class)->ensurePromoter($other_user);

    $this->actingAs($owner)->post(route('affiliate.codes.store'), ['code' => 'owner-code']);
    $custom_code = AffiliateReferralCode::query()->where('code', 'owner-code')->sole();
    $system_code = AffiliateReferralCode::query()
        ->where('promoter_id', $promoter->id)
        ->where('type', AffiliateReferralCode::TYPE_SYSTEM)
        ->sole();

    $this->actingAs($other_user)
        ->patch(route('affiliate.codes.disable', $custom_code))
        ->assertNotFound();

    $this->actingAs($owner)
        ->patch(route('affiliate.codes.disable', $system_code))
        ->assertSessionHasErrors('code');

    expect($custom_code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_ACTIVE)
        ->and($system_code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_ACTIVE);
});

test('deactivating a code immediately stops new attribution but preserves history', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $referrer = User::factory()->create();
    $referrer->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'attribution-payment',
    ]);
    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);

    $this->actingAs($referrer)->post(route('affiliate.codes.store'), ['code' => 'campaign-one']);
    $code = AffiliateReferralCode::query()->where('code', 'campaign-one')->sole();
    $first_referred = User::factory()->create();
    $second_referred = User::factory()->create();

    $first_request = Request::create('/', 'GET', [], [AffiliateService::COOKIE_NAME => $code->code]);
    app(AffiliateService::class)->createReferralFromCookie($first_request, $first_referred);

    $this->patch(route('affiliate.codes.disable', $code))->assertSessionHasNoErrors();

    $second_request = Request::create('/', 'GET', [], [AffiliateService::COOKIE_NAME => $code->code]);
    app(AffiliateService::class)->createReferralFromCookie($second_request, $second_referred);

    expect(AffiliateReferral::query()->where('referred_user_id', $first_referred->id)->exists())->toBeTrue()
        ->and(AffiliateReferral::query()->where('referred_user_id', $second_referred->id)->exists())->toBeFalse()
        ->and(AffiliateReferral::query()->where('promoter_id', $promoter->id)->where('code', $code->code)->count())->toBe(1);
});

test('dashboard aggregates referral and commission statistics per code', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $referrer = User::factory()->create();
    $system_referred = User::factory()->create();
    $custom_referred = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);
    $custom_code = AffiliateReferralCode::create([
        'promoter_id' => $promoter->id,
        'code' => 'stats-code',
        'type' => AffiliateReferralCode::TYPE_CUSTOM,
        'status' => AffiliateReferralCode::STATUS_ACTIVE,
    ]);

    AffiliateReferral::create([
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $system_referred->id,
        'code' => $promoter->code,
        'status' => AffiliateReferral::STATUS_REGISTERED,
        'registered_at' => now(),
    ]);
    $custom_referral = AffiliateReferral::create([
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $custom_referred->id,
        'code' => $custom_code->code,
        'status' => AffiliateReferral::STATUS_EARNING,
        'registered_at' => now(),
        'qualified_at' => now(),
    ]);

    foreach ([AffiliateCommission::STATUS_PENDING => 2, AffiliateCommission::STATUS_CREDITED => 3] as $status => $amount) {
        AffiliateCommission::create([
            'referral_id' => $custom_referral->id,
            'promoter_id' => $promoter->id,
            'referrer_user_id' => $referrer->id,
            'referred_user_id' => $custom_referred->id,
            'source_type' => AffiliateCommission::SOURCE_PACKAGE_PURCHASE,
            'source_id' => $amount,
            'affiliate_level' => 1,
            'base_amount' => 10,
            'commission_rate' => 0.1,
            'amount' => $amount,
            'status' => $status,
        ]);
    }

    $codes = collect(app(AffiliateDashboardService::class)->dashboard($referrer)['codes'])->keyBy('code');

    expect($codes[$promoter->code]['registration_count'])->toBe(1)
        ->and($codes[$promoter->code]['valid_referral_count'])->toBe(0)
        ->and($codes[$custom_code->code]['registration_count'])->toBe(1)
        ->and($codes[$custom_code->code]['valid_referral_count'])->toBe(1)
        ->and($codes[$custom_code->code]['pending_commission'])->toBe('2.00')
        ->and($codes[$custom_code->code]['credited_commission'])->toBe('3.00');
});

test('a level downgrade preserves active codes but blocks new codes', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $user = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($user);
    $custom_code = AffiliateReferralCode::create([
        'promoter_id' => $promoter->id,
        'code' => 'grandfathered-code',
        'type' => AffiliateReferralCode::TYPE_CUSTOM,
        'status' => AffiliateReferralCode::STATUS_ACTIVE,
    ]);

    $dashboard = app(AffiliateDashboardService::class)->dashboard($user);

    expect($dashboard['code_quota']['maximum'])->toBe(1)
        ->and($dashboard['code_quota']['active_count'])->toBe(2)
        ->and($dashboard['code_quota']['can_create'])->toBeFalse()
        ->and($custom_code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_ACTIVE);

    $this->actingAs($user)
        ->post(route('affiliate.codes.store'), ['code' => 'another-code'])
        ->assertSessionHasErrors('code');
});

test('custom code can reactivate within quota unless promoter is blocked', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $user = User::factory()->create();
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'reactivation-payment',
    ]);
    $promoter = app(AffiliateService::class)->ensurePromoter($user);

    $this->actingAs($user)->post(route('affiliate.codes.store'), ['code' => 'return-code']);
    $code = AffiliateReferralCode::query()->where('code', 'return-code')->sole();
    $this->patch(route('affiliate.codes.disable', $code))->assertSessionHasNoErrors();
    $this->patch(route('affiliate.codes.enable', $code))->assertSessionHasNoErrors();

    expect($code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_ACTIVE)
        ->and($code->disabled_at)->toBeNull();

    $this->patch(route('affiliate.codes.disable', $code))->assertSessionHasNoErrors();
    $promoter->update(['status' => $promoter::STATUS_BLOCKED]);

    $this->patch(route('affiliate.codes.enable', $code))
        ->assertSessionHasErrors('code');
    $this->post(route('affiliate.codes.store'), ['code' => 'blocked-code'])
        ->assertSessionHasErrors('code');

    expect($code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_DISABLED);
});
