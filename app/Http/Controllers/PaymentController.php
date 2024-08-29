<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = $request->user()->payments()
            ->latest()
            ->paginate(10);

        return Inertia::render('Payment/Index', compact('payments'));
    }
}
