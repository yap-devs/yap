<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class BalanceDetailController extends Controller
{
    public function index(Request $request)
    {
        $balanceDetails = $request->user()->balanceDetails()->orderBy('created_at', 'desc')->get();

        return Inertia::render('BalanceDetail/Index', compact('balanceDetails'));
    }
}
