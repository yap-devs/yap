<?php

use App\Filament\Resources\AffiliateLevelResource\Pages\CreateAffiliateLevel;
use App\Filament\Resources\AffiliateLevelResource\Pages\EditAffiliateLevel;
use App\Filament\Resources\AffiliatePromoterResource\Pages\EditAffiliatePromoter;
use App\Filament\Resources\AffiliatePromoterResource\Pages\ViewAffiliatePromoter;
use App\Filament\Resources\AffiliateReferralResource\Pages\ViewAffiliateReferral;
use App\Filament\Resources\Packages\Pages\ManagePackages;
use App\Filament\Resources\UserPackages\Pages\ManageUserPackages;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Models\AffiliateCommission;
use App\Models\AffiliateLevel;
use App\Models\AffiliateReferral;
use App\Models\Package;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    config(['node_agent.enabled' => false]);
    Bus::fake();
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');
});

test('catalog forms display readable units and save existing product fields', function () {
    Livewire::test(ManagePackages::class)->callAction('create', ['name' => 'Support plan', 'status' => 'hidden', 'price' => '5.00', 'traffic_limit' => 10, 'duration_days' => 30])
        ->assertHasNoActionErrors();
    $product = Package::where('name', 'Support plan')->firstOrFail();
    expect((int) $product->traffic_limit)->toBe(10737418240);
    $user = User::factory()->create();
    $user->packages()->create(['package_id' => $product->id, 'status' => 'active', 'remaining_traffic' => 100]);
    Livewire::test(ManagePackages::class)->callAction(TestAction::make('edit')->table($product), ['name' => 'Renamed plan', 'status' => 'disabled', 'price' => 99, 'traffic_limit' => 999, 'duration_days' => 99])
        ->assertHasNoActionErrors();
    expect($product->fresh()->name)->toBe('Renamed plan');
    expect((float) $product->fresh()->price)->toBe(99.0);
    expect((int) $product->fresh()->duration_days)->toBe(99);
    expect((int) $product->fresh()->traffic_limit)->toBe(1072668082176);
});

test('account details and the subscription list show existing purchases without grant actions', function () {
    $user = User::factory()->create(['balance' => 0]);
    $product = Package::factory()->create();
    $subscription = $user->packages()->create(['package_id' => $product->id, 'status' => 'active', 'remaining_traffic' => $product->traffic_limit, 'started_at' => now(), 'ended_at' => now()->addDays(30)]);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertSee('Available package')->assertActionDoesNotExist('grantPackage');
    Livewire::test(ManageUserPackages::class)->assertCanSeeTableRecords([$subscription]);
});

test('renaming a catalog product preserves its exact stored traffic allowance', function () {
    $product = Package::factory()->create(['traffic_limit' => 1000000000]);

    Livewire::test(ManagePackages::class)
        ->callAction(TestAction::make('edit')->table($product), ['name' => 'Renamed legacy allowance'])
        ->assertHasNoActionErrors();

    expect((int) $product->fresh()->traffic_limit)->toBe(1000000000);
});

test('referral status can be restored without rewriting attribution', function () {
    $referrer = User::factory()->create();
    $referred = User::factory()->create();
    $referral = AffiliateReferral::create(['promoter_id' => 1, 'referrer_user_id' => $referrer->id, 'referred_user_id' => $referred->id, 'code' => 'existing-code', 'status' => AffiliateReferral::STATUS_BLOCKED]);

    Livewire::test(ViewAffiliateReferral::class, ['record' => $referral->id])
        ->callAction('changeReferralStatus', ['status' => AffiliateReferral::STATUS_QUALIFIED])
        ->assertHasNoActionErrors();

    expect($referral->fresh()->status)->toBe(AffiliateReferral::STATUS_QUALIFIED);
    expect($referral->fresh()->only(['referrer_user_id', 'referred_user_id', 'code']))->toBe(['referrer_user_id' => $referrer->id, 'referred_user_id' => $referred->id, 'code' => 'existing-code']);
});

test('the legacy promoter editor no longer permits rebinding or rewriting totals', function () {
    $user = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($user);
    Livewire::test(EditAffiliatePromoter::class, ['record' => $promoter->id])->assertForbidden();
});

test('affiliate tier forms translate displayed percentages into existing commission rates', function () {
    Livewire::test(CreateAffiliateLevel::class)
        ->fillForm(['level' => 9, 'name' => 'Partner tier', 'minimum_self_paid_amount' => '20.00', 'minimum_valid_referrals' => 3, 'commission_rate' => '12.50', 'maximum_referral_codes' => 4, 'status' => 'active'])
        ->call('create')->assertHasNoFormErrors();
    $tier = AffiliateLevel::where('level', 9)->firstOrFail();
    expect($tier->commission_rate)->toBe('0.1250');
    $page = Livewire::test(EditAffiliateLevel::class, ['record' => $tier->id])
        ->fillForm(['commission_rate' => '15.00'])->call('save')->assertHasNoFormErrors();
    expect($tier->fresh()->commission_rate)->toBe('0.1500');
    $page->fillForm(['commission_rate' => '17.00'])->call('save')->assertHasNoFormErrors();
    expect($tier->fresh()->commission_rate)->toBe('0.1700');
});

test('promoter settings retain derived earnings throughout livewire requests', function () {
    $user = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($user);
    AffiliateCommission::create(['promoter_id' => $promoter->id, 'referral_id' => 1, 'referrer_user_id' => $user->id, 'referred_user_id' => 99, 'source_type' => 'user_package', 'source_id' => 1, 'base_amount' => '50.00', 'commission_rate' => '0.2500', 'status' => 'credited', 'amount' => '12.50']);
    $page = Livewire::test(ViewAffiliatePromoter::class, ['record' => $promoter->id]);
    $page->callAction('configurePromoter', ['status' => 'active', 'rate_percent' => 25])->assertHasNoActionErrors();
    expect((string) $page->instance()->record->credited_total)->toBe('12.5');
    $page->call('$refresh');
    expect((float) $page->instance()->record->credited_total)->toBe(12.5);
    expect($promoter->fresh()->custom_commission_rate)->toBe('0.2500');
});

test('the visitor tier remains editable with its zero level identifier', function () {
    $this->seed(AffiliateLevelSeeder::class);
    $visitor = AffiliateLevel::where('level', 0)->firstOrFail();
    Livewire::test(EditAffiliateLevel::class, ['record' => $visitor->id])
        ->fillForm(['name' => 'Visitor access'])->call('save')->assertHasNoFormErrors();
    expect($visitor->fresh()->name)->toBe('Visitor access');
    expect($visitor->fresh()->level)->toBe(0);
});
