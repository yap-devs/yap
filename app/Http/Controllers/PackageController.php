<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::where('status', Package::STATUS_ACTIVE)->get();

        $userPackages = UserPackage::where('user_id', $request->user()->id)->get();

        return Inertia::render('Package/Index', compact('packages', 'userPackages'));
    }

    public function buy(Request $request, Package $package)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->balance < $package->price) {
            return redirect()->route('package')->withErrors([
                'error' => 'Insufficient balance to buy this package.',
            ]);
        }

        $user_package = new UserPackage([
            'remaining_traffic' => $package->traffic_limit,
            'status' => UserPackage::STATUS_ACTIVE,
            'started_at' => now(),
            'ended_at' => now()->addDays($package->duration_days),
        ]);
        $user_package->package()->associate($package);
        $user_package->user()->associate($user);
        $user_package->save();

        $user->balance -= $package->price;
        $user->save();

        $user->balanceDetails()->create([
            'amount' => -$package->price,
            'description' => 'Bought package ' . $package->name,
        ]);

        return redirect()->route('package')->withErrors([
            'success' => 'Package bought successfully.',
        ]);
    }
}
