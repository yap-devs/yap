<?php

namespace App\Http\Controllers;

use App\Models\User;
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
        $clashUrl = route('clash', ['uuid' => $user->uuid]);
        $unitPrice = config('yap.unit_price');
        $servers = VmessServer::where('enabled', true)->get();
        $todayTraffic = Cache::remember('today_traffic_' . $user->id, 60 * 30, function () use ($user) {
            return $user->stats()
                ->whereDate('created_at', '>=', now()->startOfDay())
                ->whereDate('created_at', '<=', now()->endOfDay())
                ->selectRaw('sum(traffic_uplink) + sum(traffic_downlink) as traffic')
                ->first()->traffic ?? 0;
        });

        return Inertia::render('Dashboard', compact('clashUrl', 'unitPrice', 'servers', 'todayTraffic'));
    }
}
