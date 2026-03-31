<?php

namespace App\Providers;

use App\Services\BepusdtService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(BepusdtService::class, function ($app) {
            return new BepusdtService;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');
        Model::unguard();

        RateLimiter::for('financial', function (Request $request) {
            return Limit::perMinute(4)->by($request->user()?->id ?: $request->ip())->response(function (Request $request) {
                logger()->driver('throttle')->warning('RateLimiter [financial]: ' . $request->path(), [
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip(),
                ]);

                abort(429, 'Too many requests.');
            });
        });

        RateLimiter::for('subscription-reset', function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->id ?: $request->ip())->response(function (Request $request) {
                logger()->driver('throttle')->warning('RateLimiter [subscription-reset]: ' . $request->path(), [
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip(),
                ]);

                abort(429, 'Too many requests.');
            });
        });
    }
}
