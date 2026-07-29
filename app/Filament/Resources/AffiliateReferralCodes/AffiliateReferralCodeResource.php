<?php

namespace App\Filament\Resources\AffiliateReferralCodes;

use App\Filament\Resources\AffiliateReferralCodes\Pages\ManageAffiliateReferralCodes;
use App\Filament\Resources\AffiliateReferralCodes\Widgets\ReferralCodeOverview;
use App\Filament\Resources\UserResource;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePromoter;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use App\Services\Affiliate\AffiliateReferralCodeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\ValidationException;

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
                    ->searchable()
                    ->url(fn (AffiliateReferralCode $record): ?string => $record->promoter?->user
                        ? UserResource::getUrl('edit', ['record' => $record->promoter->user_id])
                        : null),
                TextColumn::make('code')
                    ->wrap()
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Referral code copied'),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AffiliateReferralCode::TYPE_SYSTEM => 'info',
                        AffiliateReferralCode::TYPE_CUSTOM => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AffiliateReferralCode::STATUS_ACTIVE => 'success',
                        AffiliateReferralCode::STATUS_DISABLED => 'danger',
                    })
                    ->sortable(),
                TextColumn::make('promoter.status')
                    ->label('Promoter')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        AffiliatePromoter::STATUS_ACTIVE => 'success',
                        AffiliatePromoter::STATUS_BLOCKED => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('registration_count')->label('Registered')->numeric()->sortable(),
                TextColumn::make('valid_referral_count')->label('Valid')->numeric()->sortable(),
                TextColumn::make('pending_commission')->default(0)->money()->sortable(),
                TextColumn::make('credited_commission')->default(0)->money()->sortable(),
                TextColumn::make('disabled_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        AffiliateReferralCode::TYPE_SYSTEM => 'System',
                        AffiliateReferralCode::TYPE_CUSTOM => 'Custom',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        AffiliateReferralCode::STATUS_ACTIVE => 'Active',
                        AffiliateReferralCode::STATUS_DISABLED => 'Disabled',
                    ]),
                SelectFilter::make('promoter_status')
                    ->label('Promoter status')
                    ->options([
                        AffiliatePromoter::STATUS_ACTIVE => 'Active',
                        AffiliatePromoter::STATUS_BLOCKED => 'Blocked',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $status): Builder => $query->whereHas(
                            'promoter',
                            fn (Builder $query): Builder => $query->where('status', $status),
                        ),
                    )),
                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('disable')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->requiresConfirmation()
                    ->modalHeading('Disable referral code')
                    ->modalDescription('New visits using this code will stop being attributed immediately. Existing referrals are preserved.')
                    ->modalSubmitActionLabel('Disable code')
                    ->successNotificationTitle('Referral code disabled')
                    ->visible(fn (AffiliateReferralCode $record): bool => $record->type === AffiliateReferralCode::TYPE_CUSTOM
                        && $record->status === AffiliateReferralCode::STATUS_ACTIVE
                        && ! $record->trashed()
                        && $record->promoter !== null)
                    ->action(function (
                        AffiliateReferralCode $record,
                        AffiliateReferralCodeService $referralCodeService,
                        Action $action,
                    ): void {
                        try {
                            $referralCodeService->disable($record->promoter, $record);
                        } catch (ValidationException $exception) {
                            $action->failureNotificationTitle(static::validationMessage($exception));
                            $action->failure();
                        }
                    }),
                Action::make('enable')
                    ->color('success')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->requiresConfirmation()
                    ->modalHeading('Enable referral code')
                    ->modalDescription('The code becomes available for new attribution immediately and must fit the owner\'s current quota.')
                    ->modalSubmitActionLabel('Enable code')
                    ->successNotificationTitle('Referral code enabled')
                    ->visible(fn (AffiliateReferralCode $record): bool => $record->type === AffiliateReferralCode::TYPE_CUSTOM
                        && $record->status === AffiliateReferralCode::STATUS_DISABLED
                        && ! $record->trashed()
                        && $record->promoter?->user !== null)
                    ->action(function (
                        AffiliateReferralCode $record,
                        AffiliateReferralCodeService $referralCodeService,
                        Action $action,
                    ): void {
                        try {
                            $referralCodeService->enable($record->promoter, $record->promoter->user, $record);
                        } catch (ValidationException $exception) {
                            $action->failureNotificationTitle(static::validationMessage($exception));
                            $action->failure();
                        }
                    }),
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
            ->withoutGlobalScopes([SoftDeletingScope::class])
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

    public static function getWidgets(): array
    {
        return [ReferralCodeOverview::class];
    }

    private static function validationMessage(ValidationException $exception): string
    {
        return (string) collect($exception->errors())->flatten()->first();
    }
}
