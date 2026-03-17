<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RechargeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Recharge/Index', [
            'githubSponsorURL' => config('yap.github.sponsor_url'),
            'stripeSandbox' => str_starts_with(config('yap.payment.stripe.secret'), 'sk_test_'),
        ]);
    }
}
