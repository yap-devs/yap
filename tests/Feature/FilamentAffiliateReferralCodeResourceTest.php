<?php

use App\Filament\Resources\AffiliateReferralCodes\AffiliateReferralCodeResource;
use App\Filament\Resources\AffiliateReferralCodes\Pages\ManageAffiliateReferralCodes;
use App\Filament\Resources\AffiliateReferralCodes\Widgets\ReferralCodeOverview;
use App\Models\AffiliatePromoter;
use App\Models\AffiliateReferralCode;
use App\Models\Payment;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;
use Database\Seeders\AffiliateLevelSeeder;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(AffiliateLevelSeeder::class);
    $this->actingAs(User::factory()->create(['id' => 1]));
});

test('admin can inspect and filter referral codes', function () {
    $owner = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    $system_code = $promoter->referralCodes()->where('type', AffiliateReferralCode::TYPE_SYSTEM)->sole();
    $active_code = AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'active-admin-code',
    ]);
    $disabled_code = AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'disabled-admin-code',
        'status' => AffiliateReferralCode::STATUS_DISABLED,
        'disabled_at' => now(),
    ]);
    $trashed_code = AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'trashed-admin-code',
    ]);
    $trashed_code->delete();

    $page = Livewire::test(ManageAffiliateReferralCodes::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$system_code, $active_code, $disabled_code])
        ->assertCanNotSeeTableRecords([$trashed_code])
        ->assertTableColumnStateSet('registration_count', 0, $active_code)
        ->assertTableColumnStateSet('valid_referral_count', 0, $active_code);

    $page->filterTable('type', AffiliateReferralCode::TYPE_CUSTOM)
        ->assertCanSeeTableRecords([$active_code, $disabled_code])
        ->assertCanNotSeeTableRecords([$system_code]);

    $page->resetTableFilters()
        ->filterTable('status', AffiliateReferralCode::STATUS_DISABLED)
        ->assertCanSeeTableRecords([$disabled_code])
        ->assertCanNotSeeTableRecords([$active_code, $system_code]);

    $promoter->update(['status' => AffiliatePromoter::STATUS_BLOCKED]);
    $page->resetTableFilters()
        ->filterTable('promoter_status', AffiliatePromoter::STATUS_BLOCKED)
        ->assertCanSeeTableRecords([$system_code, $active_code, $disabled_code]);

    $page->resetTableFilters()
        ->filterTable('trashed', false)
        ->assertCanSeeTableRecords([$trashed_code])
        ->assertCanNotSeeTableRecords([$system_code, $active_code, $disabled_code]);
});

test('admin can disable and enable a custom referral code', function () {
    $owner = User::factory()->create();
    $owner->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'filament-code-action-payment',
    ]);
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    $system_code = $promoter->referralCodes()->where('type', AffiliateReferralCode::TYPE_SYSTEM)->sole();
    $custom_code = AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'filament-action-code',
    ]);

    Livewire::test(ManageAffiliateReferralCodes::class)
        ->assertActionHidden(TestAction::make('disable')->table($system_code))
        ->assertActionHidden(TestAction::make('enable')->table($system_code))
        ->callAction(TestAction::make('disable')->table($custom_code))
        ->assertNotified('Referral code disabled');

    expect($custom_code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_DISABLED)
        ->and($custom_code->disabled_at)->not->toBeNull();

    Livewire::test(ManageAffiliateReferralCodes::class)
        ->callAction(TestAction::make('enable')->table($custom_code))
        ->assertNotified('Referral code enabled');

    expect($custom_code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_ACTIVE)
        ->and($custom_code->disabled_at)->toBeNull();
});

test('admin enable action obeys quota and orphaned records have no actions', function () {
    $owner = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    $disabled_code = AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'over-quota-code',
        'status' => AffiliateReferralCode::STATUS_DISABLED,
        'disabled_at' => now(),
    ]);

    Livewire::test(ManageAffiliateReferralCodes::class)
        ->callAction(TestAction::make('enable')->table($disabled_code))
        ->assertNotified(__('messages.affiliate.code_validation.limit_reached'));

    expect($disabled_code->refresh()->status)->toBe(AffiliateReferralCode::STATUS_DISABLED);

    $active_code = AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'orphaned-admin-code',
    ]);
    $promoter->delete();

    Livewire::test(ManageAffiliateReferralCodes::class)
        ->assertCanSeeTableRecords([$active_code, $disabled_code])
        ->assertActionHidden(TestAction::make('disable')->table($active_code))
        ->assertActionHidden(TestAction::make('enable')->table($disabled_code));
});

test('referral code overview reports operational totals', function () {
    $owner = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'overview-active-code',
    ]);
    AffiliateReferralCode::factory()->create([
        'promoter_id' => $promoter->id,
        'code' => 'overview-disabled-code',
        'status' => AffiliateReferralCode::STATUS_DISABLED,
        'disabled_at' => now(),
    ]);

    $widget = app(ReferralCodeOverview::class);
    $stats = (new ReflectionMethod($widget, 'getStats'))->invoke($widget);

    expect(AffiliateReferralCodeResource::getWidgets())->toBe([ReferralCodeOverview::class])
        ->and($stats)->toHaveCount(4)
        ->and($stats[0]->getValue())->toBe('2')
        ->and($stats[1]->getValue())->toBe('1')
        ->and($stats[2]->getValue())->toBe('0')
        ->and($stats[3]->getValue())->toBe('0.00 USD');
});
