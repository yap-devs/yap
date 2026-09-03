<?php

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use App\Notifications\PackageExpireReminder;
use App\Notifications\PackageLowTrafficReminder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification as NotificationFacade;

test('queues an expiration reminder every day for packages ending tomorrow', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();
    $package = Package::create([
        'name' => 'Test package',
        'price' => 10,
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ]);

    $user->packages()->create([
        'package_id' => $package->id,
        'remaining_traffic' => 500,
        'status' => UserPackage::STATUS_ACTIVE,
        'ended_at' => now()->addDay(),
    ]);

    $this->artisan('app:package-status-notification-command')->assertSuccessful();

    NotificationFacade::assertSentTo($user, PackageExpireReminder::class);
});

test('queues one low traffic reminder per package each week', function () {
    NotificationFacade::fake();
    $user = User::factory()->create();
    $package = Package::create([
        'name' => 'Test package',
        'price' => 10,
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ]);

    $user_package = $user->packages()->create([
        'package_id' => $package->id,
        'remaining_traffic' => 100,
        'status' => UserPackage::STATUS_ACTIVE,
        'ended_at' => now()->addDay(),
    ]);

    $this->artisan('app:package-low-traffic-notification-command')->assertSuccessful();
    $this->artisan('app:package-low-traffic-notification-command')->assertSuccessful();

    expect(NotificationFacade::sent($user, PackageLowTrafficReminder::class))->toHaveCount(1);
    expect(Cache::has('package-low-traffic-reminder:'.$user->id.':'.$user_package->id.':'.now('Asia/Tokyo')->startOfWeek()->toDateString()))->toBeTrue();
});
