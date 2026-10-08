<?php

namespace App\Filament\Resources\UserPackages;

use App\Filament\Resources\UserPackages\Pages\ManageUserPackages;
use App\Filament\Resources\UserResource;
use App\Models\UserPackage;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserPackageResource extends Resource
{
    protected static ?string $model = UserPackage::class;

    protected static ?string $slug = 'package-subscriptions';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->id() === 1;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('id')->label('Subscription'),
            TextEntry::make('user.email')->label('Account')->url(fn (UserPackage $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
            TextEntry::make('package.name')->label('Product'),
            TextEntry::make('display_status')->state(fn (UserPackage $record): string => $record->displayStatus())->badge(),
            TextEntry::make('remaining_traffic')->label('Remaining')->formatStateUsing(fn ($state): string => number_format($state / 1073741824, 2).' GiB'),
            TextEntry::make('started_at')->dateTime(), TextEntry::make('ended_at')->dateTime()->placeholder('No expiration'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Subscription')->sortable(),
            TextColumn::make('user.email')->label('Account')->searchable()->wrap()->url(fn (UserPackage $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
            TextColumn::make('package.name')->label('Product')->searchable(),
            TextColumn::make('status')->state(fn (UserPackage $record): string => $record->displayStatus())->badge(),
            TextColumn::make('remaining_traffic')->label('Remaining')->formatStateUsing(fn ($state): string => number_format($state / 1073741824, 2).' GiB')->sortable(),
            TextColumn::make('started_at')->label('Starts')->dateTime('Y-m-d H:i')->sortable(),
            TextColumn::make('ended_at')->label('Ends')->dateTime('Y-m-d H:i')->placeholder('No expiration')->sortable(),
        ])->defaultSort('id', 'desc')->stackedOnMobile()->filters([
            SelectFilter::make('state')->label('Status')->options(['available' => 'Available now', 'queued' => 'Queued', 'expired' => 'Expired', 'used' => 'Used', 'disabled' => 'Disabled'])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'available' => $query->available(), 'queued' => $query->queued(),
                    'expired' => $query->where(fn (Builder $query): Builder => $query->where('status', UserPackage::STATUS_EXPIRED)->orWhere(fn (Builder $query): Builder => $query->active()->where('ended_at', '<=', now()))),
                    'used', 'disabled' => $query->where('status', $data['value']), default => $query,
                }),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'package']);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUserPackages::route('/')];
    }
}
