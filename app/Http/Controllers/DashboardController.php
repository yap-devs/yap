<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $clashUrl = route('clash', ['uuid' => $request->user()->uuid]);

        return Inertia::render('Dashboard', compact('clashUrl'));
    }
}
