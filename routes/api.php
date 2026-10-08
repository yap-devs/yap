<?php

use App\Http\Controllers\AgentConfigurationController;
use App\Http\Controllers\AgentTrafficController;
use App\Http\Middleware\AuthenticateNode;
use Illuminate\Support\Facades\Route;

Route::prefix('agent/v1')->middleware(['throttle:agent-ip', AuthenticateNode::class])->group(function (): void {
    Route::get('config', [AgentConfigurationController::class, 'show']);
    Route::post('traffic', [AgentTrafficController::class, 'store']);
});
