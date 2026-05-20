<?php

use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Services\Affiliate\AffiliateDashboardService;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;

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
