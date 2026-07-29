<?php

namespace App\Filament\Resources\AffiliateReferralCodes;

use App\Filament\Resources\AffiliateReferralCodes\Pages\ManageAffiliateReferralCodes;
use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Services\Affiliate\AffiliateReferralCodeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateReferralCodeResource extends Resource
{
    protected static ?string $model = AffiliateReferralCode::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('promoter.user.email')
                    ->label('Owner')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('code')->wrap()->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('registration_count')->label('Registered')->numeric()->sortable(),
                TextColumn::make('valid_referral_count')->label('Valid')->numeric()->sortable(),
                TextColumn::make('pending_commission')->money()->sortable(),
                TextColumn::make('credited_commission')->money()->sortable(),
                TextColumn::make('disabled_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->recordActions([
                Action::make('disable')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateReferralCode $record): bool => $record->type === AffiliateReferralCode::TYPE_CUSTOM
                        && $record->status === AffiliateReferralCode::STATUS_ACTIVE)
                    ->action(fn (AffiliateReferralCode $record, AffiliateReferralCodeService $referralCodeService) => $referralCodeService->disable(
                        $record->promoter,
                        $record,
                    )),
                Action::make('enable')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->visible(fn (AffiliateReferralCode $record): bool => $record->type === AffiliateReferralCode::TYPE_CUSTOM
                        && $record->status === AffiliateReferralCode::STATUS_DISABLED)
                    ->action(fn (AffiliateReferralCode $record, AffiliateReferralCodeService $referralCodeService) => $referralCodeService->enable(
                        $record->promoter,
                        $record->promoter->user,
                        $record,
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAffiliateReferralCodes::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['promoter.user'])
            ->withCount([
                'referrals as registration_count',
                'referrals as valid_referral_count' => fn (Builder $query): Builder => $query
                    ->whereIn('status', [
                        AffiliateReferral::STATUS_QUALIFIED,
                        AffiliateReferral::STATUS_EARNING,
                        AffiliateReferral::STATUS_EXPIRED,
                    ])
                    ->whereNotNull('qualified_at'),
            ])
            ->withSum([
                'commissions as pending_commission' => fn (Builder $query): Builder => $query
                    ->where('affiliate_commissions.status', AffiliateCommission::STATUS_PENDING),
            ], 'amount')
            ->withSum([
                'commissions as credited_commission' => fn (Builder $query): Builder => $query
                    ->where('affiliate_commissions.status', AffiliateCommission::STATUS_CREDITED),
            ], 'amount');
    }
}
