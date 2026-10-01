<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserStat;
use App\Models\VmessServer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $clashUrl = route('subscription.clash', ['uuid' => $user->uuid]);
        $universalSubscriptionUrl = route('subscription.universal', ['uuid' => $user->uuid]);
        $unitPrice = config('yap.unit_price');
        $servers = VmessServer::where('enabled', true)->get(['id', 'name', 'rate', 'for_low_priority']);
        $todayTraffic = Cache::remember(UserStat::todayTrafficCacheKey($user->id), 60 * 30, function () use ($user) {
            return $user->stats()
                ->where('created_at', '>=', now()->startOfDay())
                ->where('created_at', '<', now()->addDay()->startOfDay())
                ->selectRaw('sum(traffic_uplink) + sum(traffic_downlink) as traffic')
                ->first()->traffic ?? 0;
        });

        return Inertia::render('Dashboard', compact('clashUrl', 'universalSubscriptionUrl', 'unitPrice', 'servers', 'todayTraffic'));
    }
}
