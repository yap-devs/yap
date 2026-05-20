<?php

use App\Models\AffiliateReferral;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;

test('affiliate page renders invite dashboard in a browser', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/affiliate')
        ->assertSee('Affiliate')
        ->assertSee('Invite friends')
        ->assertSee('Current Level')
        ->assertSee('Copy Link')
        ->assertNoJavaScriptErrors();
});

test('affiliate page renders registered referral progress in a browser', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $referrer = User::factory()->create();
    $referred = User::factory()->create(['email' => 'friend@example.com']);
    $promoter = app(AffiliateService::class)->ensurePromoter($referrer);

    AffiliateReferral::create([
        'promoter_id' => $promoter->id,
        'referrer_user_id' => $referrer->id,
        'referred_user_id' => $referred->id,
        'code' => $promoter->code,
        'status' => AffiliateReferral::STATUS_REGISTERED,
        'registered_at' => now(),
    ]);

    $this->actingAs($referrer);

    visit('/affiliate')
        ->assertSee('Registered')
        ->assertSee('Friend registered but has not paid yet')
        ->assertNoJavaScriptErrors();
});
