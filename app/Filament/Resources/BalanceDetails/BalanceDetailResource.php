<?php

namespace App\Filament\Resources\BalanceDetails;

use App\Filament\Resources\BalanceDetails\Pages\ManageBalanceDetails;
use App\Filament\Resources\UserResource;
use App\Models\BalanceDetail;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BalanceDetailResource extends Resource
{
    protected static ?string $model = BalanceDetail::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Balance ledger';

    protected static ?string $modelLabel = 'ledger entry';

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
            TextEntry::make('id')->label('Entry number'),
            TextEntry::make('user.email')->label('Account')->placeholder('Account removed')
                ->url(fn (BalanceDetail $record): ?string => $record->user ? UserResource::getUrl('view', ['record' => $record->user_id]) : null),
            TextEntry::make('amount')->money('USD'),
            TextEntry::make('description')->label('Reason / description')->columnSpanFull()->placeholder('Not recorded'),
            TextEntry::make('created_at')->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Entry')->searchable()->sortable(),
            TextColumn::make('user.email')->label('Account')->searchable()->wrap()->placeholder('Account removed')
                ->url(fn (BalanceDetail $record): ?string => $record->user ? UserResource::getUrl('view', ['record' => $record->user_id]) : null),
            TextColumn::make('amount')->money('USD')->sortable()->color(fn (mixed $state): string => (float) $state < 0 ? 'danger' : ((float) $state > 0 ? 'success' : 'gray')),
            TextColumn::make('description')->label('Reason / description')->searchable()->wrap()->limit(80),
            TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
        ])->filters([
            SelectFilter::make('direction')->options(['credit' => 'Credits', 'debit' => 'Debits'])
                ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                    'credit' => $query->where('amount', '>', 0),
                    'debit' => $query->where('amount', '<', 0),
                    default => $query,
                }),
        ])->defaultSort('id', 'desc')->striped()->stackedOnMobile()->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return ['index' => ManageBalanceDetails::route('/')];
    }
}
