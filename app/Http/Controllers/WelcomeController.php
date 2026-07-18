<?php

namespace App\Http\Controllers;

use App\Models\Package;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class WelcomeController extends Controller
{
    public function index()
    {
        $packages = Package::query()
            ->where('status', Package::STATUS_ACTIVE)
            ->orderBy('traffic_limit')
            ->get([
                'id',
                'name',
                'price',
                'duration_days',
                'traffic_limit',
            ])
            ->map(fn (Package $package): array => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => $package->price,
                'original_price' => $package->original_price,
                'duration_days' => $package->duration_days,
                'traffic_limit' => $package->traffic_limit,
            ]);

        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'unitPrice' => config('yap.unit_price'),
            'packages' => $packages,
        ]);
    }
}
