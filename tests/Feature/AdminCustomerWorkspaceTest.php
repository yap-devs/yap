<?php

use App\Filament\Resources\AffiliateCommissionResource\Pages\EditAffiliateCommission;
use App\Filament\Resources\BalanceDetails\Pages\ManageBalanceDetails;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ViewUser;
use App\Filament\Resources\UserResource\RelationManagers\BalanceDetailsRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\PaymentsRelationManager;
use App\Models\AffiliateCommission;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');
});

test('user details explain access and expose related account records', function () {
    $user = User::factory()->create(['balance' => '10.00']);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->assertOk()
        ->assertSee($user->email)
        ->assertSee('Positive balance')
        ->assertSee('Recharge orders')
        ->assertSee('Balance ledger')
        ->assertSee('Packages')
        ->assertDontSee('Operation history');
});

test('editing user details preserves password balance and technical fields', function () {
    $user = User::factory()->create(['name' => 'Before', 'balance' => '10.00', 'uuid' => 'original-subscription', 'traffic_unpaid' => 123]);
    $password = $user->password;

    Livewire::test(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'After', 'password' => '', 'balance' => 999, 'uuid' => 'tampered', 'traffic_unpaid' => 0])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->fresh()->name)->toBe('After');
    expect($user->fresh()->password)->toBe($password);
    expect($user->fresh()->balance)->toBe('10.00');
    expect($user->fresh()->uuid)->toBe('original-subscription');
    expect($user->fresh()->traffic_unpaid)->toBe(123);
});

test('admin created users receive a generated subscription identity and zero balance', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'New account', 'email' => 'new-account@example.test', 'password' => 'secure-password', 'balance' => 99, 'uuid' => 'tampered'])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'new-account@example.test')->firstOrFail();
    expect($user->uuid)->toMatch('/^[0-9a-f-]{36}$/');
    expect($user->balance)->toBe('0.00');
    expect(Hash::check('secure-password', $user->password))->toBeTrue();
});

test('the existing balance adjustment updates the account ledger without an audit table', function () {
    Bus::fake();
    $user = User::factory()->create(['balance' => '10.00']);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->callAction('adjustBalance', ['operation' => 'increase', 'amount' => '2.50', 'description' => 'Service compensation'])
        ->assertHasNoActionErrors();

    expect($user->fresh()->balance)->toBe('12.50');
    $this->assertDatabaseHas('balance_details', ['user_id' => $user->id, 'amount' => '2.50', 'description' => 'Service compensation']);

    Livewire::test(ViewUser::class, ['record' => $user->id])
        ->callAction('adjustBalance', ['operation' => 'increase', 'amount' => '-2.50'])
        ->assertHasActionErrors(['amount']);
    expect($user->fresh()->balance)->toBe('12.50');
});

test('orders can be searched and filtered without hiding internal accounts', function () {
    $user = User::factory()->create(['email' => 'buyer@example.test']);
    $expired = $user->payments()->create(['gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_EXPIRED, 'amount' => 9, 'remote_id' => 'receipt-42']);
    $paid = $user->payments()->create(['gateway' => Payment::GATEWAY_STRIPE, 'status' => Payment::STATUS_PAID, 'amount' => 12]);

    Livewire::test(ListPayments::class)
        ->assertCanSeeTableRecords([$expired, $paid])
        ->filterTable('status', Payment::STATUS_EXPIRED)
        ->assertCanSeeTableRecords([$expired])
        ->assertCanNotSeeTableRecords([$paid])
        ->searchTable('receipt-42')
        ->assertCanSeeTableRecords([$expired]);
});

test('payment details show the existing order without offering new compensation logic', function () {
    $user = User::factory()->create(['balance' => '1.00']);
    $payment = $user->payments()->create(['gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_EXPIRED, 'amount' => 9]);

    Livewire::test(ViewPayment::class, ['record' => $payment->id])
        ->assertOk()->assertSee('Order details')->assertSee($user->email)
        ->assertActionDoesNotExist('compensate');

    expect($user->fresh()->balance)->toBe('1.00');
    expect($payment->fresh()->status)->toBe(Payment::STATUS_EXPIRED);
});

test('user related orders and ledger entries are scoped to the selected account', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $payment = $user->payments()->create(['gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_PAID, 'amount' => 9]);
    $other_payment = $other->payments()->create(['gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_PAID, 'amount' => 10]);
    $entry = $user->balanceDetails()->create(['amount' => 9, 'description' => 'Old top-up']);
    $other_entry = $other->balanceDetails()->create(['amount' => 10]);

    Livewire::test(PaymentsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => ViewUser::class])
        ->assertCanSeeTableRecords([$payment])->assertCanNotSeeTableRecords([$other_payment]);
    Livewire::test(BalanceDetailsRelationManager::class, ['ownerRecord' => $user, 'pageClass' => ViewUser::class])
        ->assertCanSeeTableRecords([$entry])->assertCanNotSeeTableRecords([$other_entry]);
    Livewire::test(ManageBalanceDetails::class)
        ->callAction(TestAction::make('view')->table($entry))
        ->assertSee('Old top-up');
});

test('commission financial records cannot be changed through the old editor', function () {
    $commission = AffiliateCommission::create([
        'referral_id' => 1, 'promoter_id' => 1, 'referrer_user_id' => 1, 'referred_user_id' => 2,
        'source_type' => AffiliateCommission::SOURCE_PACKAGE_PURCHASE, 'source_id' => 1,
        'affiliate_level' => 1, 'base_amount' => 10, 'commission_rate' => 0.1, 'amount' => 1,
        'status' => AffiliateCommission::STATUS_PENDING,
    ]);

    Livewire::test(EditAffiliateCommission::class, ['record' => $commission->id])->assertForbidden();
});

test('ordinary users cannot open account and billing management pages', function () {
    $user = User::factory()->create();
    $payment = $user->payments()->create(['gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_PAID, 'amount' => 9]);
    $this->actingAs($user);

    $this->get(UserResource::getUrl('view', ['record' => $user]))->assertForbidden();
    Livewire::test(ViewPayment::class, ['record' => $payment->id])->assertForbidden();
    Livewire::test(ManageBalanceDetails::class)->assertForbidden();
});
