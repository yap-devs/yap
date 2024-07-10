<?php

namespace App\Http\Controllers;

use App\Models\VmessServer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $clashUrl = route('clash', ['uuid' => $request->user()->uuid]);
        $unitPrice = config('yap.unit_price');
        $servers = VmessServer::all();

        return Inertia::render('Dashboard', compact('clashUrl', 'unitPrice', 'servers'));
    }
}
