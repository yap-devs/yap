<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateCommissionResource\Pages\EditAffiliateCommission;
use App\Filament\Resources\AffiliateCommissionResource\Pages\ListAffiliateCommissions;
use App\Models\AffiliateCommission;
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

class AffiliateCommissionResource extends Resource
{
    protected static ?string $model = AffiliateCommission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'md' => 2,
                'xl' => 3,
            ])
            ->components([
                TextInput::make('referral_id')->required()->numeric(),
                TextInput::make('promoter_id')->required()->numeric(),
                TextInput::make('referrer_user_id')->required()->numeric(),
                TextInput::make('referred_user_id')->required()->numeric(),
                TextInput::make('source_type')->required()->maxLength(255),
                TextInput::make('source_id')->required()->numeric(),
                TextInput::make('affiliate_level')->required()->numeric(),
                TextInput::make('base_amount')->required()->numeric()->prefix('$'),
                TextInput::make('commission_rate')->required()->numeric(),
                TextInput::make('amount')->required()->numeric()->prefix('$'),
                Select::make('status')->required()->options([
                    AffiliateCommission::STATUS_PENDING => 'Pending',
                    AffiliateCommission::STATUS_CREDITED => 'Credited',
                    AffiliateCommission::STATUS_REJECTED => 'Rejected',
                    AffiliateCommission::STATUS_REVERSED => 'Reversed',
                ]),
                DateTimePicker::make('hold_until'),
                DateTimePicker::make('credited_at'),
                DateTimePicker::make('reversed_at'),
                TextInput::make('reason')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make()
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
        return parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
