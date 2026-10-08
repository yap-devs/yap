<?php

namespace App\Filament\Resources\Packages;

use App\Filament\Resources\Packages\Pages\ManagePackages;
use App\Models\Package;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Package catalog';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return auth()->id() === 1;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            Select::make('status')->required()->options(static::statuses())->default(Package::STATUS_HIDDEN)->native(false)
                ->helperText('Active: available for purchase. Hidden: not listed publicly. Disabled: unavailable for purchase.'),
            TextInput::make('price')->label('Price')->prefix('USD')->required()->numeric()->minValue(0)->maxValue(999999.99)->rules(['decimal:0,2']),
            TextInput::make('traffic_limit')->label('Traffic allowance')->suffix('GiB')->required()->numeric()->minValue(0)->maxValue(1000000)->rules(['decimal:0,2'])->default(10737418240)
                ->formatStateUsing(fn ($state): mixed => $state !== null ? round($state / 1073741824, 2) : null)
                ->dehydrateStateUsing(fn (?Package $record, $state): string => $record && bccomp((string) $state, (string) round($record->traffic_limit / 1073741824, 2), 2) === 0
                    ? (string) $record->traffic_limit
                    : bcmul((string) $state, '1073741824', 0)),
            TextInput::make('duration_days')->label('Duration')->suffix('days')->required()->integer()->minValue(0)->maxValue(3650)->default(30)
                ->helperText('0 means no expiration.'),
            Textarea::make('description')->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('name'), TextEntry::make('status')->badge(),
            TextEntry::make('price')->money('USD'),
            TextEntry::make('traffic_limit')->label('Allowance')->formatStateUsing(fn ($state): string => number_format($state / 1073741824, 2).' GiB'),
            TextEntry::make('duration_days')->formatStateUsing(fn ($state): string => $state ? $state.' days' : 'No expiration'),
            TextEntry::make('description')->placeholder('No description')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(), TextColumn::make('status')->badge(),
            TextColumn::make('price')->money('USD')->sortable(),
            TextColumn::make('traffic_limit')->label('Allowance')->formatStateUsing(fn ($state): string => number_format($state / 1073741824, 2).' GiB'),
            TextColumn::make('duration_days')->label('Duration')->formatStateUsing(fn ($state): string => $state ? $state.' days' : 'No expiration'),
            TextColumn::make('subscriptions_count')->counts('subscriptions')->label('Subscriptions'),
        ])->defaultSort('id', 'desc')->stackedOnMobile()->filters([SelectFilter::make('status')->options(static::statuses())])
            ->recordActions([ViewAction::make(), EditAction::make()->label('Edit product')]);
    }

    public static function statuses(): array
    {
        return [Package::STATUS_ACTIVE => 'Active', Package::STATUS_HIDDEN => 'Hidden', Package::STATUS_DISABLED => 'Disabled'];
    }

    public static function getPages(): array
    {
        return ['index' => ManagePackages::route('/')];
    }
}
