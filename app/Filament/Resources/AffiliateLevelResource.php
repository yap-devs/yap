<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateLevelResource\Pages\CreateAffiliateLevel;
use App\Filament\Resources\AffiliateLevelResource\Pages\EditAffiliateLevel;
use App\Filament\Resources\AffiliateLevelResource\Pages\ListAffiliateLevels;
use App\Models\AffiliateLevel;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AffiliateLevelResource extends Resource
{
    protected static ?string $model = AffiliateLevel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'md' => 2,
                'xl' => 3,
            ])
            ->components([
                TextInput::make('level')->required()->numeric(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('minimum_self_paid_amount')->required()->numeric()->prefix('$'),
                TextInput::make('minimum_valid_referrals')->required()->numeric(),
                TextInput::make('commission_rate')->required()->numeric()->helperText('0.10 means 10%'),
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
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
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
