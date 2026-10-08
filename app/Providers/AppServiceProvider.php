<?php

namespace App\Providers;

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Models\UserPackage;
use App\Observers\NodeAuthorizationObserver;
use App\Services\BepusdtService;
use App\Services\DatabaseTimezoneService;
use App\Services\SchedulerStatusService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(DatabaseTimezoneService::class);
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
        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => app(DatabaseTimezoneService::class)->configure($event->connection));
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if ($event->command === 'schedule:run') {
                app(SchedulerStatusService::class)->started('scheduler');
            }
        });
        Event::listen(CommandFinished::class, function (CommandFinished $event): void {
            if ($event->command === 'schedule:run') {
                app(SchedulerStatusService::class)->finished('scheduler', $event->exitCode);
            }
        });
        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event): void {
            app(SchedulerStatusService::class)->finished($event->task->description, 1);
        });

        foreach ([Node::class, NodeRoute::class, User::class, UserPackage::class] as $model) {
            $model::observe(NodeAuthorizationObserver::class);
        }

        RateLimiter::for('agent-ip', fn (Request $request) => Limit::perMinute(1200)->by('agent-ip:'.$request->ip()));
        RateLimiter::for('agent-node', fn (Request $request) => Limit::perMinute(120)->by('agent-node:'.$request->attributes->get('node')->id));

        RateLimiter::for('financial', function (Request $request) {
            return Limit::perMinute(4)->by($request->user()?->id ?: $request->ip())->response(function (Request $request) {
                logger()->driver('throttle')->warning('RateLimiter [financial]: '.$request->path(), [
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip(),
                ]);

                abort(429, 'Too many requests.');
            });
        });

        RateLimiter::for('subscription-reset', function (Request $request) {
            return Limit::perMinute(1)->by($request->user()?->id ?: $request->ip())->response(function (Request $request) {
                logger()->driver('throttle')->warning('RateLimiter [subscription-reset]: '.$request->path(), [
                    'user_id' => $request->user()?->id,
                    'ip' => $request->ip(),
                ]);

                abort(429, 'Too many requests.');
            });
        });

        RateLimiter::for('affiliate-code', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('registration', function (Request $request) {
            return [
                Limit::perMinute(3)->by('registration:minute:'.$request->ip()),
                Limit::perHour(10)->by('registration:hour:'.$request->ip()),
            ];
        });
    }
}
