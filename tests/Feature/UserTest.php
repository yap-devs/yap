<?php

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Facades\DB;

test('is valid uses eager loaded packages', function () {
    $user = User::factory()->create([
        'balance' => 0,
    ]);

    $package = Package::create([
        'name' => 'Test Package',
        'description' => 'Test package',
        'price' => 1,
        'duration_days' => 30,
        'traffic_limit' => 1024,
    ]);

    UserPackage::create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1024,
        'status' => UserPackage::STATUS_ACTIVE,
    ]);

    $user = User::with('packages')->findOrFail($user->id);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    expect($user->is_valid)->toBeTrue()
        ->and($queries)->toBeEmpty();
});
