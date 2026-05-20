<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateReferralResource\Pages\EditAffiliateReferral;
use App\Filament\Resources\AffiliateReferralResource\Pages\ListAffiliateReferrals;
use App\Models\AffiliateReferral;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AffiliateReferralResource extends Resource
{
    protected static ?string $model = AffiliateReferral::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('promoter_id')->required()->numeric(),
            TextInput::make('referrer_user_id')->required()->numeric(),
            TextInput::make('referred_user_id')->required()->numeric(),
            TextInput::make('code')->required()->maxLength(255),
            Select::make('status')->required()->options([
                AffiliateReferral::STATUS_REGISTERED => 'Registered',
                AffiliateReferral::STATUS_QUALIFIED => 'Qualified',
                AffiliateReferral::STATUS_EARNING => 'Earning',
                AffiliateReferral::STATUS_EXPIRED => 'Expired',
                AffiliateReferral::STATUS_REJECTED => 'Rejected',
                AffiliateReferral::STATUS_BLOCKED => 'Blocked',
            ]),
            DateTimePicker::make('registered_at'),
            DateTimePicker::make('qualified_at'),
            DateTimePicker::make('commission_expires_at'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('referrer.email')->label('Referrer')->searchable(),
                TextColumn::make('referred.email')->label('Referred')->searchable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('first_qualified_payment_amount')->money()->sortable(),
                TextColumn::make('commission_expires_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAffiliateReferrals::route('/'),
            'edit' => EditAffiliateReferral::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
