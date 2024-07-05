<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GithubController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $user = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            return redirect()->route('profile.edit');
        }

        abort_if(User::where('github_id', $user->id)->exists(), 403, 'This GitHub account has been linked to another user.');

        $request->user()->update([
            'github_id' => $user->id,
            'github_nickname' => $user->nickname,
            'github_token' => $user->token,
            'github_created_at' => $user->user['created_at'],
        ]);

        GenerateClashProfileLink::dispatch();

        return redirect()->route('profile.edit');
    }

    public function sponsorWebhook(Request $request)
    {
        logger('GitHub sponsor webhook', $request->all());

        $user = User::where('github_id', $request->input('sponsorship.sponsor.id'))->first();

        if (!$user) {
            return response()->json(['message' => 'User not found']);
        }

        if ($request->input('action') !== 'created') {
            return response()->json(['message' => 'Ignoring action']);
        }

        $amount = $request->input('sponsorship.tier.monthly_price_in_dollars');
        $remote_id = $request->input('sponsorship.tier.node_id') . '|' . $request->input('sponsorship.tier.created_at');

        if (Payment::where('remote_id', $remote_id)->exists()) {
            return response()->json(['message' => 'Payment already exists']);
        }

        $user->payments()->create([
            'status' => Payment::STATUS_PAID,
            'amount' => $amount,
            'remote_id' => $remote_id,
            'payload' => $request->all(),
        ]);

        $user->balance += $amount;
        $user->save();

        GenerateClashProfileLink::dispatch();

        return response()->json(['message' => 'ok']);
    }
}
