<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StatController extends Controller
{
    public function index(Request $request)
    {
        $chartData = [];
        if ($request->user()->isValid) {
            $chartData = $request->user()->stats()
                ->where('created_at', '>=', now()->subDays(14)->startOfDay())
                ->selectRaw('DATE(created_at) as period, SUM(traffic_downlink) as traffic_downlink, SUM(traffic_uplink) as traffic_uplink')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('period')
                ->toBase()
                ->get()
                ->map(function (object $stat): array {
                    return [
                        'date' => CarbonImmutable::parse($stat->period)->format('m/d'),
                        'traffic_downlink' => (int) $stat->traffic_downlink,
                        'traffic_uplink' => (int) $stat->traffic_uplink,
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
