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
            ->select('id', 'remote_id', 'gateway', 'status', 'amount', 'created_at')
            ->paginate(10);

        return Inertia::render('Payment/Index', compact('payments'));
    }
}
