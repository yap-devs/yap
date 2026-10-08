<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateCommissionResource\Pages\EditAffiliateCommission;
use App\Filament\Resources\AffiliateCommissionResource\Pages\ListAffiliateCommissions;
use App\Filament\Resources\BalanceDetails\BalanceDetailResource;
use App\Models\AffiliateCommission;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AffiliateCommissionResource extends Resource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Commissions';

    protected static ?int $navigationSort = 4;

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
            TextEntry::make('referrer.email')->label('Referrer')->placeholder('Account removed'),
            TextEntry::make('referred.email')->label('Referred')->placeholder('Account removed'),
            TextEntry::make('source_type')->label('Source'),
            TextEntry::make('source_id')->label('Source record'),
            TextEntry::make('base_amount')->money('USD'),
            TextEntry::make('commission_rate')->formatStateUsing(fn (mixed $state): string => ((float) $state * 100).'%'),
            TextEntry::make('amount')->money('USD'),
            TextEntry::make('status')->badge(),
            TextEntry::make('hold_until')->dateTime()->placeholder('Not recorded'),
            TextEntry::make('credited_at')->dateTime()->placeholder('Not credited'),
            TextEntry::make('reversed_at')->dateTime()->placeholder('Not reversed'),
            TextEntry::make('reason')->placeholder('Not recorded'),
            TextEntry::make('credited_balance_detail_id')->label('Credited ledger entry')->placeholder('Not credited')
                ->url(fn (AffiliateCommission $record): ?string => $record->credited_balance_detail_id ? BalanceDetailResource::getUrl('index', ['tableSearch' => (string) $record->credited_balance_detail_id]) : null),
            TextEntry::make('processing_state')->label('Settlement')->state(fn (AffiliateCommission $record): string => $record->status === AffiliateCommission::STATUS_PENDING
                ? ($record->hold_until && $record->hold_until->isPast() ? 'Due for scheduled eligibility check' : 'Hold period has not ended') : 'No pending settlement'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Commission')->searchable()->sortable(),
                TextColumn::make('referrer.email')->label('Referrer')->wrap()->searchable(),
                TextColumn::make('referred.email')->label('Referred')->wrap()->searchable(),
                TextColumn::make('source_type')->wrap()->searchable(),
                TextColumn::make('source_id')->numeric()->sortable(),
                TextColumn::make('base_amount')->money()->sortable(),
                TextColumn::make('commission_rate')->formatStateUsing(fn ($state): string => ((float) $state * 100).'%'),
                TextColumn::make('amount')->money()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('hold_until')->dateTime()->sortable(),
                TextColumn::make('credited_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'credited' => 'Credited', 'rejected' => 'Rejected', 'reversed' => 'Reversed']),
                Filter::make('due')->label('Due for processing')->query(fn (Builder $query): Builder => $query->where('status', AffiliateCommission::STATUS_PENDING)->where('hold_until', '<=', now())),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->labeledFrom('sm'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateCommissions::route('/'),
            'edit' => EditAffiliateCommission::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class])->with(['referrer', 'referred']);
    }
}
