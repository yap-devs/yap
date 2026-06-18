<?php

use App\Filament\Resources\AffiliateCommissionResource;
use App\Filament\Resources\AffiliateLevelResource;
use App\Filament\Resources\AffiliatePromoterResource;
use App\Filament\Resources\AffiliateReferralResource;
use App\Filament\Resources\RelayServerResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VmessServerResource;
use App\Filament\Widgets\AiRecentUsageTable;
use App\Filament\Widgets\AiUsageRankingTable;
use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\PaymentTopUpPeriodRankingTable;
use App\Filament\Widgets\PaymentTopUpRankingTable;
use App\Filament\Widgets\TotalTrafficLeaderboardTable;
use App\Filament\Widgets\UserPackagesTable;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

test('resource table is stacked on mobile for :dataset', function (string $resource): void {
    $table = $resource::table(Table::make(new FilamentTableHarness));

    expect($table->isStackedOnMobile())->toBeTrue();
})->with([
    'users' => [UserResource::class],
    'vmess servers' => [VmessServerResource::class],
    'relay servers' => [RelayServerResource::class],
    'affiliate promoters' => [AffiliatePromoterResource::class],
    'affiliate referrals' => [AffiliateReferralResource::class],
    'affiliate commissions' => [AffiliateCommissionResource::class],
    'affiliate levels' => [AffiliateLevelResource::class],
]);

test('widget table is stacked on mobile for :dataset', function (string $widget): void {
    $instance = app($widget);
    $instance->pageFilters = [];

    $table = $instance->table(Table::make($instance));

    expect($table->isStackedOnMobile())->toBeTrue();
})->with([
    'user packages' => [UserPackagesTable::class],
    'ai recent usage' => [AiRecentUsageTable::class],
    'ai usage ranking' => [AiUsageRankingTable::class],
    'daily traffic ranking' => [DailyTrafficRankingTable::class],
    'total traffic leaderboard' => [TotalTrafficLeaderboardTable::class],
    'payment top up ranking' => [PaymentTopUpRankingTable::class],
    'payment top up period ranking' => [PaymentTopUpPeriodRankingTable::class],
]);

class FilamentTableHarness implements HasTable
{
    public function callTableColumnAction(string $name, string $recordKey): mixed
    {
        return null;
    }

    public function deselectAllTableRecords(): void {}

    public function getActiveTableLocale(): ?string
    {
        return null;
    }

    public function getAllSelectableTableRecordKeys(): array
    {
        return [];
    }

    public function getAllTableRecordsCount(): int
    {
        return 0;
    }

    public function getAllSelectableTableRecordsCount(): int
    {
        return 0;
    }

    public function getTableFilterState(string $name): ?array
    {
        return null;
    }

    public function getTableFilterFormState(string $name): ?array
    {
        return null;
    }

    public function getSelectedTableRecords(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): EloquentCollection|Collection|LazyCollection
    {
        return new Collection;
    }

    public function getSelectedTableRecordsQuery(bool $shouldFetchSelectedRecords = true, ?int $chunkSize = null): Builder
    {
        return Model::query();
    }

    public function parseTableFilterName(string $name): string
    {
        return $name;
    }

    public function getTableGrouping(): ?Group
    {
        return null;
    }

    public function getMountedTableAction(): ?Action
    {
        return null;
    }

    public function getMountedTableActionForm(): ?Schema
    {
        return null;
    }

    public function getMountedTableActionRecord(): ?Model
    {
        return null;
    }

    public function getMountedTableBulkAction(): ?Action
    {
        return null;
    }

    public function getMountedTableBulkActionForm(): ?Schema
    {
        return null;
    }

    public function getTable(): Table
    {
        return Table::make($this);
    }

    public function getTableFiltersForm(): Schema
    {
        return Schema::make();
    }

    public function getTableRecords(): Collection|Paginator|CursorPaginator
    {
        return new Collection;
    }

    public function getTableRecordsPerPage(): int|string|null
    {
        return null;
    }

    public function getTablePage(): int|string
    {
        return 1;
    }

    public function getTableSortColumn(): ?string
    {
        return null;
    }

    public function getTableSortDirection(): ?string
    {
        return null;
    }

    public function getAllTableSummaryQuery(): ?Builder
    {
        return null;
    }

    public function getPageTableSummaryQuery(): ?Builder
    {
        return null;
    }

    public function isTableColumnToggledHidden(string $name): bool
    {
        return false;
    }

    public function getTableRecord(?string $key): Model|array|null
    {
        return null;
    }

    public function getTableRecordKey(Model|array $record): string
    {
        return '1';
    }

    public function toggleTableReordering(): void {}

    public function isTableReordering(): bool
    {
        return false;
    }

    public function isTableLoaded(): bool
    {
        return true;
    }

    public function hasTableSearch(): bool
    {
        return false;
    }

    public function resetTableSearch(): void {}

    public function resetTableColumnSearch(string $column): void {}

    public function getTableSearchIndicator(): Indicator
    {
        return Indicator::make('');
    }

    public function getTableColumnSearchIndicators(): array
    {
        return [];
    }

    public function getFilteredTableQuery(): ?Builder
    {
        return null;
    }

    public function getFilteredSortedTableQuery(): ?Builder
    {
        return null;
    }

    public function getTableQueryForExport(): Builder
    {
        return Model::query();
    }

    public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
    {
        return null;
    }

    public function callMountedTableAction(array $arguments = []): mixed
    {
        return null;
    }

    public function mountTableAction(string $name, ?string $record = null, array $arguments = []): mixed
    {
        return null;
    }

    public function replaceMountedTableAction(string $name, ?string $record = null, array $arguments = []): void {}

    public function mountTableBulkAction(string $name, ?array $selectedRecords = null): mixed
    {
        return null;
    }

    public function replaceMountedTableBulkAction(string $name, ?array $selectedRecords = null): void {}
}
