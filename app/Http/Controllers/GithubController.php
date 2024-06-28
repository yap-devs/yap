<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class GithubController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request)
    {
        $user = Socialite::driver('github')->user();

        $request->user()->update([
            'github_id' => $user->id,
            'github_nickname' => $user->nickname,
            'github_token' => $user->token,
            'github_created_at' => $user->user['created_at'],
        ]);

        return redirect()->route('profile.edit');
    }
}
