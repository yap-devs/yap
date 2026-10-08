<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateLevelResource\Pages\CreateAffiliateLevel;
use App\Filament\Resources\AffiliateLevelResource\Pages\EditAffiliateLevel;
use App\Filament\Resources\AffiliateLevelResource\Pages\ListAffiliateLevels;
use App\Models\AffiliateLevel;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AffiliateLevelResource extends Resource
{
    protected static ?string $model = AffiliateLevel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|\UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Levels';

    protected static ?int $navigationSort = 5;

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
        return $schema
            ->columns([
                'md' => 2,
                'xl' => 3,
            ])
            ->components([
                TextInput::make('level')->required()->integer()->minValue(0)->maxValue(1000)->unique(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('minimum_self_paid_amount')->required()->numeric()->minValue(0)->maxValue(999999.99)->rules(['decimal:0,2'])->prefix('$'),
                TextInput::make('minimum_valid_referrals')->required()->integer()->minValue(0)->maxValue(1000000),
                TextInput::make('commission_rate')->label('Commission rate')->required()->numeric()->minValue(0)->maxValue(100)->rules(['decimal:0,2'])->suffix('%')
                    ->formatStateUsing(fn ($state): mixed => $state !== null ? bcmul((string) $state, '100', 2) : null)->dehydrateStateUsing(fn ($state): string => bcdiv((string) $state, '100', 4)),
                TextInput::make('maximum_referral_codes')
                    ->label('Referral code limit')
                    ->helperText('Includes the permanent system code.')
                    ->required()
                    ->integer()
                    ->minValue(1)->maxValue(1000),
                Select::make('status')->required()->options([
                    AffiliateLevel::STATUS_ACTIVE => 'Active',
                    AffiliateLevel::STATUS_DISABLED => 'Disabled',
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('level')->sortable(),
                TextColumn::make('name')->wrap()->searchable(),
                TextColumn::make('minimum_self_paid_amount')->money()->sortable(),
                TextColumn::make('minimum_valid_referrals')->numeric()->sortable(),
                TextColumn::make('commission_rate')->formatStateUsing(fn ($state): string => ((float) $state * 100).'%'),
                TextColumn::make('maximum_referral_codes')->numeric()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make()
                    ->labeledFrom('sm'),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateLevels::route('/'),
            'create' => CreateAffiliateLevel::route('/create'),
            'edit' => EditAffiliateLevel::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
