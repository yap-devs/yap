<?php

use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;

test('package history can be paged without hiding current packages', function () {
    $user = User::factory()->create();
    $package = Package::create(['name' => 'Browser package', 'description' => 'Test', 'status' => Package::STATUS_ACTIVE, 'price' => 1, 'duration_days' => 30, 'traffic_limit' => 1000]);
    $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_ACTIVE, 'remaining_traffic' => 100, 'started_at' => now()->subDay()]);
    foreach (range(1, 23) as $i) {
        $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_USED, 'remaining_traffic' => 0]);
    }
    $this->actingAs($user);

    visit('/package')
        ->assertSee('Current and Queued Packages')
        ->assertScript('document.querySelectorAll("tbody tr").length', 22)
        ->assertPresent('nav[aria-label="Package History"] span[aria-disabled="true"]')
        ->click('nav[aria-label="Package History"] a[href*="packages_page=2"]:has-text("2")')
        ->assertQueryStringHas('packages_page', '2')
        ->assertScript('document.querySelectorAll("tbody tr").length', 5)
        ->assertSee('Current and Queued Packages')
        ->assertNoJavaScriptErrors();
});

test('affiliate pagination keeps the other list page and unfinished code input', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $user = User::factory()->create();
    $user->payments()->create(['gateway' => Payment::GATEWAY_STRIPE, 'status' => Payment::STATUS_PAID, 'amount' => 5]);
    $promoter = app(AffiliateService::class)->ensurePromoter($user);
    foreach (range(1, 23) as $i) {
        $friend = User::factory()->create();
        $referral = AffiliateReferral::create(['promoter_id' => $promoter->id, 'referrer_user_id' => $user->id, 'referred_user_id' => $friend->id, 'code' => $promoter->code, 'status' => AffiliateReferral::STATUS_REGISTERED, 'registered_at' => now()]);
        AffiliateCommission::create(['referral_id' => $referral->id, 'promoter_id' => $promoter->id, 'referrer_user_id' => $user->id, 'referred_user_id' => $friend->id, 'source_type' => AffiliateCommission::SOURCE_PACKAGE_PURCHASE, 'source_id' => $i, 'base_amount' => 1, 'commission_rate' => 0.1, 'amount' => 0.1, 'status' => AffiliateCommission::STATUS_PENDING]);
    }
    $this->actingAs($user);

    visit('/affiliate')
        ->assertSee('Commission History')
        ->fill('#referral-code', 'unfinished-code')
        ->click('nav[aria-label="Invited Friends"] a[href*="referrals_page=2"]:has-text("2")')
        ->assertQueryStringHas('referrals_page', '2')
        ->assertValue('#referral-code', 'unfinished-code')
        ->click('nav[aria-label="Commission History"] a[href*="commissions_page=2"]:has-text("2")')
        ->assertQueryStringHas('referrals_page', '2')
        ->assertQueryStringHas('commissions_page', '2')
        ->assertValue('#referral-code', 'unfinished-code')
        ->assertNoJavaScriptErrors();
});
