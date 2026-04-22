<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::where('status', Package::STATUS_ACTIVE)->get();

        $userPackages = $request->user()->packages()
            ->latest()
            ->get();

        return Inertia::render('Package/Index', compact('packages', 'userPackages'));
    }

    public function buy(Request $request, Package $package)
    {
        abort_if($package->status !== Package::STATUS_ACTIVE, 404);

        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($user, $package) {
            // Lock the user row to prevent concurrent balance modifications
            $user = User::lockForUpdate()->find($user->id);

            if ($user->balance < $package->price) {
                return redirect()->route('package')->with('error', 'Insufficient balance to buy this package.');
            }

            $started_at = CarbonImmutable::now();
            if ($user->packages()->where('status', UserPackage::STATUS_ACTIVE)->exists()) {
                $started_at = $user->packages()
                    ->where('status', UserPackage::STATUS_ACTIVE)
                    ->orderBy('ended_at', 'desc')
                    ->first()
                    ->ended_at;
                $started_at = CarbonImmutable::parse($started_at);
            }

            $user_package = new UserPackage([
                'remaining_traffic' => $package->traffic_limit,
                'status' => UserPackage::STATUS_ACTIVE,
                'started_at' => $started_at,
                'ended_at' => $started_at->addDays($package->duration_days),
            ]);
            $user_package->package()->associate($package);
            $user_package->user()->associate($user);
            $user_package->save();

            $user->decrement('balance', $package->price);

            $user->balanceDetails()->create([
                'amount' => -$package->price,
                'description' => 'Bought package '.$package->name,
            ]);

            return redirect()->route('package')->with('success', 'Package bought successfully.');
        });
    }
}
