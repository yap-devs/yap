<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\UserPackage;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveUserPackagesTable extends TableWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Active User Packages')
            ->description('Current active subscriptions with remaining traffic and expiration windows.')
            ->query(app(AdminDashboardReportService::class)->getActiveUserPackagesQuery())
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->striped()
            ->recordClasses(fn (UserPackage $record): array => [
                'bg-rose-50/70 dark:bg-rose-950/20' => $this->getRemainingTrafficRatio($record) < 0.1 || $this->isPackageEndingSoon($record),
                'bg-amber-50/70 dark:bg-amber-950/20' => $this->getRemainingTrafficRatio($record) >= 0.1 && $this->getRemainingTrafficRatio($record) < 0.3,
            ])
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (UserPackage $record): string => 'Package #'.$record->id)
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Package')
                    ->description(fn (UserPackage $record): string => $this->formatGigabytes((float) ($record->package?->traffic_limit ?? 0)).' total allowance')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color('success'),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('Y-m-d H:i')
                    ->since(),
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
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
                TextColumn::make('package.traffic_limit')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
            ]);
    }

    private function formatGigabytes(float $bytes): string
    {
        return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
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
