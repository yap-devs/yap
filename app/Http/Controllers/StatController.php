<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class StatController extends Controller
{
    public function index(Request $request)
    {
        $chartData = [];
        if ($request->user()->isValid) {
            $chartData = collect($request->user()->stats()->where('created_at', '>=', now()->subDays(14)->startOfDay())->get())
                ->groupBy('date')
                ->map(function ($stats, $date) {
                    return [
                        'date' => $date,
                        'traffic_downlink' => $stats->sum('traffic_downlink'),
                        'traffic_uplink' => $stats->sum('traffic_uplink'),
                    ];
                });
            $chartData = [
                'labels' => $chartData->pluck('date'),
                'datasets' => [
                    [
                        'label' => 'Downlink',
                        'data' => $chartData->pluck('traffic_downlink'),
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                    ],
                    [
                        'label' => 'Uplink',
                        'data' => $chartData->pluck('traffic_uplink'),
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                    ],
                ],
            ];
        }

        return Inertia::render('Stat/Index', compact('chartData'));
    }
}
