<?php

use App\Http\Controllers\ClashController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GithubController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StatController;
use App\Http\Middleware\ValidateGithubWebhook;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';

Route::get('/clash/{uuid}/yap.yaml', [ClashController::class, 'index'])->name('clash');

Route::group(['prefix' => 'dashboard', 'middleware' => ['auth', 'verified']], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});

Route::group(['prefix' => 'auth/github', 'middleware' => ['auth']], function () {
    Route::get('/', [GithubController::class, 'redirect'])->name('github.redirect');
    Route::get('/callback', [GithubController::class, 'callback']);
});

Route::post('/github/sponsor/webhook', [GithubController::class, 'sponsorWebhook'])
    ->middleware(ValidateGithubWebhook::class)
    ->name('github.sponsor_webhook');

Route::group(['prefix' => 'stat', 'middleware' => ['auth']], function () {
    Route::get('/', [StatController::class, 'index'])->name('stat');
});
