<?php

namespace App\Http\Controllers;

use App\Services\FutoonService;
use Illuminate\Http\Request;

class FutoonController extends Controller
{
    public function submit(Request $request)
    {
        $out_trade_no = $request->input('out_trade_no');
        $name = $request->input('name');
        $money = $request->input('money');

        return response()->redirectTo((new FutoonService())->submit($out_trade_no, $name, $money));
    }

    public function notify(Request $request)
    {
        return (new FutoonService())->notify($request);
    }
}
