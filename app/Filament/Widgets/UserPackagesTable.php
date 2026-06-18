<?php

namespace App\Filament\Widgets;

use App\Models\UserPackage;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class UserPackagesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $report_service = app(AdminDashboardReportService::class);

        return $table
            ->heading('User Packages')
            ->description('Package subscriptions with usage, expiration status, and ended-package profit details.')
            ->query(
                $report_service
                    ->applyUserPackageStatus(
                        $report_service->getUserPackagesQuery(),
                        $this->pageFilters['status'] ?? 'ended',
                    ),
            )
            ->defaultSort('user_packages.id', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->recordClasses(fn (UserPackage $record): array => [
                'bg-rose-50/70 dark:bg-rose-950/20' => $record->status === UserPackage::STATUS_ACTIVE && ($this->getRemainingTrafficRatio($record) < 0.1 || $this->isPackageEndingSoon($record)),
                'bg-amber-50/70 dark:bg-amber-950/20' => $record->status === UserPackage::STATUS_ACTIVE && $this->getRemainingTrafficRatio($record) >= 0.1 && $this->getRemainingTrafficRatio($record) < 0.3,
            ])
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (UserPackage $record): string => 'Package #'.$record->id)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Package')
                    ->description(fn (UserPackage $record): string => $this->formatGigabytes((float) ($record->package?->traffic_limit ?? 0)).' total allowance')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (UserPackage $record): string => $this->getStatusTone($record))
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable(),
                TextColumn::make('ended_at')
                    ->label('Ends')
                    ->dateTime('Y-m-d H:i')
                    ->badge()
                    ->color(fn (UserPackage $record): string => $this->getExpiryTone($record))
                    ->description(fn (UserPackage $record): string => $this->formatExpiryHint($record))
                    ->sortable(),
                TextColumn::make('remaining_traffic')
                    ->label('Remaining')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (UserPackage $record): string => $this->getRemainingTrafficTone($record))
                    ->description(fn (UserPackage $record): string => $this->formatRemainingTrafficRatio($record))
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state))
                    ->sortable(),
                TextColumn::make('package.traffic_limit')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state))
                    ->sortable(),
                TextColumn::make('package.price')
                    ->label('Revenue')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state))
                    ->sortable(),
                TextColumn::make('consumed_cost')
                    ->label('Consumed Cost')
                    ->alignEnd()
                    ->state(fn (UserPackage $record): float => $this->getConsumedCost($record))
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state)),
                TextColumn::make('expected_profit')
                    ->label('Profit')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (UserPackage $record): string => $this->getExpectedProfit($record) >= 0 ? 'success' : 'danger')
                    ->description(fn (UserPackage $record): string => $this->formatProfitDescription($record))
                    ->state(fn (UserPackage $record): float => $this->getExpectedProfit($record))
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state)),
            ])
            ->stackedOnMobile();
    }

    private function formatGigabytes(float $bytes): string
    {
        return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }

    private function getConsumedCost(UserPackage $user_package): float
    {
        $traffic_limit = (float) ($user_package->package?->traffic_limit ?? 0);
        $consumed_traffic = max($traffic_limit - (float) $user_package->remaining_traffic, 0);

        return $this->bytesToCost($consumed_traffic);
    }

    private function getExpectedProfit(UserPackage $user_package): float
    {
        if (! $this->isEndedPackage($user_package)) {
            return 0.0;
        }

        return (float) ($user_package->package?->price ?? 0) - $this->getConsumedCost($user_package);
    }

    private function formatProfitDescription(UserPackage $user_package): string
    {
        if ($this->isEndedPackage($user_package)) {
            return 'Ended package profit';
        }

        return 'Unsettled package';
    }

    private function bytesToCost(float $bytes): float
    {
        return round($bytes / 1024 / 1024 / 1024 * (float) config('yap.unit_price'), 2);
    }

    private function formatRemainingTrafficRatio(UserPackage $user_package): string
    {
        return number_format($this->getRemainingTrafficRatio($user_package) * 100, 1).'% left';
    }

    private function getRemainingTrafficRatio(UserPackage $user_package): float
    {
        $traffic_limit = max((float) ($user_package->package?->traffic_limit ?? 0), 1);

        return min(max((float) $user_package->remaining_traffic / $traffic_limit, 0), 1);
    }

    private function getRemainingTrafficTone(UserPackage $user_package): string
    {
        return match (true) {
            $this->getRemainingTrafficRatio($user_package) < 0.1 => 'danger',
            $this->getRemainingTrafficRatio($user_package) < 0.3 => 'warning',
            default => 'success',
        };
    }

    private function getStatusTone(UserPackage $user_package): string
    {
        return match ($user_package->status) {
            UserPackage::STATUS_ACTIVE => 'success',
            UserPackage::STATUS_EXPIRED => 'warning',
            UserPackage::STATUS_USED => 'danger',
            UserPackage::STATUS_DISABLED => 'gray',
            default => 'gray',
        };
    }

    private function isEndedPackage(UserPackage $user_package): bool
    {
        return in_array($user_package->status, [UserPackage::STATUS_EXPIRED, UserPackage::STATUS_USED], true);
    }

    private function isPackageEndingSoon(UserPackage $user_package): bool
    {
        if (! $user_package->ended_at) {
            return false;
        }

        return CarbonImmutable::parse($user_package->ended_at)->lessThanOrEqualTo(now()->addDays(3));
    }

    private function getExpiryTone(UserPackage $user_package): string
    {
        if (! $user_package->ended_at) {
            return 'gray';
        }

        return match (true) {
            CarbonImmutable::parse($user_package->ended_at)->isPast() => 'danger',
            $this->isPackageEndingSoon($user_package) => 'warning',
            default => 'success',
        };
    }

    private function formatExpiryHint(UserPackage $user_package): string
    {
        if (! $user_package->ended_at) {
            return 'No expiration recorded';
        }

        return CarbonImmutable::parse($user_package->ended_at)->diffForHumans();
    }
}
