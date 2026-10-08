<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AiAnalytics;
use App\Filament\Pages\CashFlow;
use App\Filament\Pages\TrafficReports;
use App\Filament\Pages\UserPackages;
use App\Filament\Resources\AffiliatePromoterResource;
use App\Filament\Resources\NodeRoutes\NodeRouteResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\UserPackages\UserPackageResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminOperationsOverviewService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;

class OperationsWorkspace extends Widget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.operations-workspace';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->id() === 1;
    }

    public function mount(): void
    {
        abort_unless(static::canView(), 403);
    }

    protected function getViewData(): array
    {
        $overview = app(AdminOperationsOverviewService::class)->overview();
        $quick_links = [
            ['label' => 'Customers', 'description' => 'Manage accounts, balances and access', 'icon' => 'heroicon-o-user-group', 'url' => UserResource::getUrl()],
            ['label' => 'Recharge orders', 'description' => 'Inspect payment status and channel details', 'icon' => 'heroicon-o-credit-card', 'url' => PaymentResource::getUrl()],
            ['label' => 'Subscriptions', 'description' => 'Review purchased packages and remaining traffic', 'icon' => 'heroicon-o-cube', 'url' => UserPackageResource::getUrl()],
            ['label' => 'Promoters', 'description' => 'Review referrals and commission settings', 'icon' => 'heroicon-o-megaphone', 'url' => AffiliatePromoterResource::getUrl()],
        ];
        if (config('node_agent.enabled')) {
            $quick_links[] = ['label' => 'Route settings', 'description' => 'Manage entries and traffic multipliers', 'icon' => 'heroicon-o-arrows-right-left', 'url' => NodeRouteResource::getUrl()];
        }
        $backup_labels = ['healthy' => 'Available', 'disabled' => 'Disabled', 'missing' => 'No backup yet', 'overdue' => 'Overdue', 'failed' => 'Last attempt failed', 'running' => 'Running', 'unavailable' => 'Unavailable'];
        $scheduler = $overview['scheduler'];
        $failed_tasks = collect($scheduler['tasks'])->filter(fn (array $task): bool => ($task['state']['status'] ?? null) === 'failed')->count();

        return [
            ...$overview, 'quick_links' => $quick_links, 'polling_interval' => $this->getPollingInterval(),
            'attention_checks' => count(array_filter($overview['attention'], fn (array $item): bool => $item['count'] > 0)),
            'scheduler_label' => $failed_tasks ? $failed_tasks.' failed task(s)' : ($scheduler['healthy'] ? 'Recent tick received' : 'No recent successful tick'),
            'scheduler_healthy' => $scheduler['healthy'] && ! $failed_tasks,
            'next_task' => collect($scheduler['tasks'])->sortBy('next_at')->first(),
            'latest_backup_at' => $overview['backup']['completed_at'] ?? (isset($overview['backup']['latest']['modified_at']) ? CarbonImmutable::createFromTimestamp($overview['backup']['latest']['modified_at'])->toIso8601String() : null),
            'latest_backup_size' => $overview['backup']['latest'] ? Number::fileSize($overview['backup']['latest']['bytes'], precision: 1) : 'No backup yet',
            'total_backup_size' => Number::fileSize($overview['backup']['total_bytes'], precision: 1),
            'backup_label' => $backup_labels[$overview['backup']['status']],
            'checked_at' => now()->format('H:i T'),
            'reports' => [['label' => 'Cash flow', 'url' => CashFlow::getUrl()], ['label' => 'Traffic trends', 'url' => TrafficReports::getUrl()], ['label' => 'AI analytics', 'url' => AiAnalytics::getUrl()], ['label' => 'Package analysis', 'url' => UserPackages::getUrl()]],
        ];
    }

    public function formatDate(?string $value): string
    {
        return $value ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone'))->format('M j, H:i T') : 'Not recorded yet';
    }
}
