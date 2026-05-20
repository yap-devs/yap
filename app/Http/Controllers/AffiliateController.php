<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Affiliate\AffiliateDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AffiliateController extends Controller
{
    public function index(Request $request, AffiliateDashboardService $affiliateDashboardService)
    {
        abort_if(! config('affiliate.enabled'), 404);

        /** @var User $user */
        $user = $request->user();
        $affiliate = $affiliateDashboardService->dashboard($user);

        return Inertia::render('Affiliate/Index', compact('affiliate'));
    }
}
