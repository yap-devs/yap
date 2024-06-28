<?php

namespace App\Http\Controllers;

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

        return redirect()->route('profile.edit');
    }
}
