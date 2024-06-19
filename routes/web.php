<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/auth/github/redirect', function () {
    return Socialite::driver('github')->redirect();
});

Route::get('/auth/github/callback', function () {
    $githubUser = Socialite::driver('github')->user();

    dd($githubUser);

    $user = User::updateOrCreate([
        'email' => $githubUser->email,
    ], [
        'name' => $githubUser->name,
        'email' => $githubUser->email,
    ]);

    Auth::login($user);

    return redirect('/dashboard');
});

