<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\Affiliate\AffiliateService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PackageController extends Controller
{
    public function index(Request $request)
    {
        $packages = Package::where('status', Package::STATUS_ACTIVE)->get();

        $userPackages = $this->presentUserPackages($request->user()->packages()
            ->latest()
            ->get());

        return Inertia::render('Package/Index', compact('packages', 'userPackages'));
    }

    public function buy(Request $request, Package $package, AffiliateService $affiliateService)
    {
        abort_if($package->status !== Package::STATUS_ACTIVE, 404);

        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($user, $package, $affiliateService) {
            // Lock the user row to prevent concurrent balance modifications
            $user = User::lockForUpdate()->find($user->id);

            if ($user->balance < $package->price) {
                return redirect()->route('package')->with('error', __('messages.errors.insufficient_package_balance'));
            }

            $started_at = CarbonImmutable::now();
            if ($user->packages()->active()->exists()) {
                $latest_active_package = $user->packages()
                    ->active()
                    ->orderBy('ended_at', 'desc')
                    ->first();

                if ($latest_active_package->ended_at) {
                    $started_at = CarbonImmutable::parse($latest_active_package->ended_at);
                }
            }

            $user_package = new UserPackage([
                'remaining_traffic' => $package->traffic_limit,
                'status' => UserPackage::STATUS_ACTIVE,
            ]);
            $user_package->package()->associate($package);
            $user_package->user()->associate($user);
            $user_package->activateAt($started_at);
            $user_package->save();

            $user->decrement('balance', $package->price);

            $user->balanceDetails()->create([
                'amount' => -$package->price,
                'description' => __('messages.balance_descriptions.bought_package', ['name' => $package->name], 'en'),
            ]);

            $affiliateService->handlePackagePurchased($user_package);

            return redirect()->route('package')->with('success', __('messages.success.package_bought'));
        });
    }

    /**
     * @param  Collection<int, UserPackage>  $user_packages
     * @return Collection<int, array<string, mixed>>
     */
    private function presentUserPackages(Collection $user_packages): Collection
    {
        return $user_packages->map(function (UserPackage $user_package): array {
            $is_queued = $user_package->isQueued();

            return [
                'id' => $user_package->id,
                'package' => $user_package->package,
                'remaining_traffic' => $user_package->remaining_traffic,
                'status' => $user_package->status,
                'display_status' => $user_package->displayStatus(),
                'is_queued' => $is_queued,
                'validity_days' => $user_package->package->duration_days,
                'display_validity' => $this->displayValidity($user_package),
                'started_at' => $user_package->started_at?->toDateTimeString(),
                'ended_at' => $user_package->ended_at?->toDateTimeString(),
                'display_ended_at' => $is_queued ? null : $user_package->ended_at?->toDateTimeString(),
            ];
        });
    }

    private function displayValidity(UserPackage $user_package): string
    {
        if (! $user_package->isQueued()) {
            return 'expiry_date';
        }

        return $user_package->package->duration_days > 0
            ? 'queued_duration'
            : 'queued_pending_activation';
    }
}
