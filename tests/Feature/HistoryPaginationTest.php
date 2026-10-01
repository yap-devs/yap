<?php

use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;
use Inertia\Testing\AssertableInertia as Assert;

test('package history is paginated separately from current and queued packages', function () {
    $this->withoutVite();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $package = Package::create(['name' => 'History package', 'description' => 'Test', 'status' => Package::STATUS_ACTIVE, 'price' => 1, 'duration_days' => 30, 'traffic_limit' => 1000]);
    $current = $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_ACTIVE, 'remaining_traffic' => 100, 'started_at' => now()->subDay()]);
    $queued = $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_ACTIVE, 'remaining_traffic' => 1000, 'started_at' => now()->addMonth()]);
    $history = collect(range(1, 23))->map(fn () => $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_USED, 'remaining_traffic' => 0]));
    $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_EXPIRED, 'remaining_traffic' => 0])->delete();
    $other->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_USED, 'remaining_traffic' => 0]);

    $this->actingAs($user)->get(route('package'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('userPackages', 2)
        ->where('userPackages.0.id', $queued->id)
        ->where('userPackages.1.id', $current->id)
        ->has('historicalPackages.data', 20)
        ->where('historicalPackages.total', 23)
        ->where('historicalPackages.data.0.id', $history->last()->id)
    );
    $this->get(route('package', ['packages_page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('userPackages', 2)
        ->has('historicalPackages.data', 3)
        ->where('historicalPackages.current_page', 2)
        ->where('historicalPackages.data.0.id', $history[2]->id)
    );
});

test('affiliate histories paginate independently and preserve all commission totals', function () {
    $this->withoutVite();
    $this->seed(AffiliateLevelSeeder::class);
    $owner = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    $referrals = collect(range(1, 23))->map(function () use ($owner, $promoter) {
        $friend = User::factory()->create();

        return AffiliateReferral::create(['promoter_id' => $promoter->id, 'referrer_user_id' => $owner->id, 'referred_user_id' => $friend->id, 'code' => $promoter->code, 'status' => AffiliateReferral::STATUS_REGISTERED, 'registered_at' => now()]);
    });
    $referral = $referrals->last();
    $commissions = collect(range(1, 23))->map(fn (int $source_id) => AffiliateCommission::create([
        'referral_id' => $referral->id, 'promoter_id' => $promoter->id, 'referrer_user_id' => $owner->id, 'referred_user_id' => $referral->referred_user_id,
        'source_type' => AffiliateCommission::SOURCE_PACKAGE_PURCHASE, 'source_id' => $source_id, 'base_amount' => 1, 'commission_rate' => 0.1, 'amount' => 0.1,
        'status' => $source_id <= 21 ? AffiliateCommission::STATUS_PENDING : AffiliateCommission::STATUS_CREDITED,
    ]));
    $commissions->last()->delete();
    $other = User::factory()->create();
    $other_promoter = app(AffiliateService::class)->ensurePromoter($other);
    AffiliateReferral::create(['promoter_id' => $other_promoter->id, 'referrer_user_id' => $other->id, 'referred_user_id' => User::factory()->create()->id, 'code' => $other_promoter->code, 'status' => AffiliateReferral::STATUS_REGISTERED]);

    $this->actingAs($owner)->get(route('affiliate'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('affiliate.referrals.data', 20)
        ->where('affiliate.referrals.total', 23)
        ->where('affiliate.referrals.data.0.id', $referral->id)
        ->where('affiliate.referrals.data.0.pending_commission', '2.10')
        ->where('affiliate.referrals.data.0.credited_commission', '0.10')
        ->missing('affiliate.referrals.data.0.commissions')
        ->has('affiliate.commissions.data', 20)
        ->where('affiliate.commissions.total', 22)
        ->where('affiliate.stats.pending_commission', '2.10')
        ->where('affiliate.stats.credited_commission', '0.10')
    );
    $this->get(route('affiliate', ['referrals_page' => 2, 'commissions_page' => 2]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('affiliate.referrals.data', 3)
        ->where('affiliate.referrals.data.0.id', $referrals[2]->id)
        ->has('affiliate.commissions.data', 2)
        ->where('affiliate.commissions.data.0.id', $commissions[1]->id)
        ->where('affiliate.commissions.current_page', 2)
        ->where('affiliate.referrals.next_page_url', null)
        ->where('affiliate.referrals.prev_page_url', fn (string $url) => str_contains($url, 'commissions_page=2'))
    );
});
