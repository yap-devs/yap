<?php

use App\Http\Controllers\AlipayController;
use App\Http\Controllers\BalanceDetailController;
use App\Http\Controllers\BepusdtController;
use App\Http\Controllers\ClashController;
use App\Http\Controllers\CustomerServiceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FutoonController;
use App\Http\Controllers\GithubController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PaymentController;
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

Route::get('/futoon/submit', [FutoonController::class, 'submit'])
    ->middleware('auth')
    ->name('futoon.submit');
Route::get('/futoon/notify', [FutoonController::class, 'notify'])->name('futoon.notify');

Route::group(['prefix' => 'alipay', 'middleware' => ['auth']], function () {
    Route::get('/{payment}/query', [AlipayController::class, 'query'])->name('alipay.query');
    Route::get('/{payment}/scan', [AlipayController::class, 'scan'])->name('alipay.scan');
    Route::post('/newOrder', [AlipayController::class, 'newOrder'])->name('alipay.newOrder');
});
Route::post('/alipay/notify', [AlipayController::class, 'notify']);

Route::group(['prefix' => 'payment', 'middleware' => ['auth']], function () {
    Route::get('/', [PaymentController::class, 'index'])->name('payment');
});

Route::group(['prefix' => 'balance/detail', 'middleware' => ['auth']], function () {
    Route::get('/', [BalanceDetailController::class, 'index'])->name('balance.detail');
});

Route::group(['prefix' => 'customer/service', 'middleware' => ['auth']], function () {
    Route::get('/', [CustomerServiceController::class, 'index'])->name('customer.service');
    Route::post('/resetSubscription', [CustomerServiceController::class, 'resetSubscription'])->name('customer.service.resetSubscription');
});

Route::group(['prefix' => 'package', 'middleware' => ['auth']], function () {
    Route::get('/', [PackageController::class, 'index'])->name('package');
    Route::post('/{package}/buy', [PackageController::class, 'buy'])->name('package.buy');
});

Route::group(['prefix' => 'bepusdt', 'middleware' => ['auth']], function () {
    Route::get('/{payment}/scan', [BepusdtController::class, 'scan'])->name('bepusdt.scan');
    Route::get('/newOrder', [BepusdtController::class, 'newOrder'])->name('bepusdt.newOrder');
});
Route::post('/bepusdt/notify', [BepusdtController::class, 'notify'])->name('bepusdt.notify');
