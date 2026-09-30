<?php

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Widgets\CustomerGrowthChart;
use App\Filament\Widgets\CustomerSalesOverview;
use App\Models\Payment;
use App\Models\User;
use App\Services\CustomerSalesReportService;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('monthly customer report separates new buyers from returning buyers and excludes unpaid and internal activity', function () {
    $this->travelTo(now()->setDate(2026, 9, 20)->setTime(12, 0));

    $admin = User::factory()->create(['id' => 1]);
    $returning = User::factory()->create(['id' => 6, 'created_at' => '2026-07-03 10:00:00']);
    $new_buyer = User::factory()->create(['id' => 7, 'created_at' => '2026-09-02 10:00:00']);
    User::factory()->create(['id' => 8, 'created_at' => '2026-09-05 10:00:00']);

    foreach ([
        [$returning, '2026-08-05 10:00:00', 20, Payment::STATUS_PAID],
        [$returning, '2026-09-05 10:00:00', 30, Payment::STATUS_PAID],
        [$returning, '2026-09-06 10:00:00', 10, Payment::STATUS_PAID],
        [$new_buyer, '2026-09-07 10:00:00', 50, Payment::STATUS_PAID],
        [$new_buyer, '2026-09-08 10:00:00', 40, Payment::STATUS_CREATED],
        [$new_buyer, '2026-09-09 10:00:00', 40, Payment::STATUS_REFUNDED],
        [$admin, '2026-09-10 10:00:00', 1000, Payment::STATUS_PAID],
    ] as [$user, $date, $amount, $status]) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_ALIPAY,
            'status' => $status,
            'amount' => $amount,
            'created_at' => $date,
        ]);
    }

    $returning->balanceDetails()->create(['amount' => -12, 'created_at' => '2026-09-11 10:00:00']);
    $admin->balanceDetails()->create(['amount' => -500, 'created_at' => '2026-09-11 10:00:00']);

    $report = app(CustomerSalesReportService::class)->monthly(2);

    expect($report['2026-08'])->toBe([
        'new_users' => 0,
        'first_time_buyers' => 1,
        'returning_buyers' => 0,
        'top_up' => 20.0,
        'balance_charges' => 0.0,
    ])->and($report['2026-09'])->toBe([
        'new_users' => 2,
        'first_time_buyers' => 1,
        'returning_buyers' => 1,
        'top_up' => 90.0,
        'balance_charges' => 12.0,
    ]);
});

test('customer list exposes paid order totals and customer analytics widgets', function () {
    $user = User::factory()->create(['id' => 6]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 25,
        'created_at' => '2026-09-03 10:00:00',
    ]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 100,
        'created_at' => '2026-09-04 10:00:00',
    ]);

    $record = UserResource::getEloquentQuery()->findOrFail($user->id);
    $widgets = (new ReflectionMethod(ListUsers::class, 'getHeaderWidgets'))->invoke(app(ListUsers::class));

    expect($record->paid_top_up_count)->toBe(1)
        ->and((float) $record->paid_top_up_total)->toBe(25.0)
        ->and($record->last_paid_at)->toStartWith('2026-09-03')
        ->and($widgets)->toBe([CustomerSalesOverview::class, CustomerGrowthChart::class]);
});

test('admin can render the customer analytics list', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');

    Livewire::test(ListUsers::class)->assertOk();
    Livewire::test(CustomerSalesOverview::class)->assertOk();
    Livewire::test(CustomerGrowthChart::class)->assertOk();
});
