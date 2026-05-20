<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliatePromoterResource\Pages\EditAffiliatePromoter;
use App\Filament\Resources\AffiliatePromoterResource\Pages\ListAffiliatePromoters;
use App\Models\AffiliatePromoter;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AffiliatePromoterResource extends Resource
{
    protected static ?string $model = AffiliatePromoter::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('user_id')->required()->numeric(),
            TextInput::make('code')->required()->maxLength(255),
            Select::make('status')->required()->options([
                AffiliatePromoter::STATUS_ACTIVE => 'Active',
                AffiliatePromoter::STATUS_BLOCKED => 'Blocked',
            ]),
            TextInput::make('custom_commission_rate')->numeric()->helperText('Optional. 0.25 means 25%.'),
            TextInput::make('total_valid_referrals')->required()->numeric(),
            TextInput::make('total_commission_amount')->required()->numeric()->prefix('$'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.email')->searchable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('custom_commission_rate')->formatStateUsing(fn ($state): string => $state === null ? '-' : ((float) $state * 100).'%'),
                TextColumn::make('total_valid_referrals')->numeric()->sortable(),
                TextColumn::make('total_commission_amount')->money()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliatePromoters::route('/'),
            'edit' => EditAffiliatePromoter::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
