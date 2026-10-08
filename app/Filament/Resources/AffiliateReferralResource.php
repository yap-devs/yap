<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateReferralResource\Pages\EditAffiliateReferral;
use App\Filament\Resources\AffiliateReferralResource\Pages\ListAffiliateReferrals;
use App\Filament\Resources\AffiliateReferralResource\Pages\ViewAffiliateReferral;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\AffiliateReferral;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AffiliateReferralResource extends Resource
{
    protected static ?string $model = AffiliateReferral::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Referrals';

    protected static ?int $navigationSort = 3;

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

    public static function statusAction(): Action
    {
        return Action::make('changeReferralStatus')->label('Change status')->authorize(fn (): bool => auth()->id() === 1)
            ->visible(fn (AffiliateReferral $record): bool => ! $record->trashed())
            ->modalDescription('Referral status controls eligibility for new and pending commissions. Attribution and recorded payment details stay attached to this referral.')
            ->fillForm(fn (AffiliateReferral $record): array => ['status' => $record->status])
            ->schema([Select::make('status')->options(static::statuses())->required()->native(false)->selectablePlaceholder(false)])
            ->action(function (AffiliateReferral $record, array $data): void {
                $record->update(['status' => $data['status']]);
                $record->refresh();
                Notification::make()->title('Referral status updated')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('referrer.email')->label('Referrer')->url(fn (AffiliateReferral $record): string => UserResource::getUrl('view', ['record' => $record->referrer_user_id])),
            TextEntry::make('referred.email')->label('Referred account')->url(fn (AffiliateReferral $record): string => UserResource::getUrl('view', ['record' => $record->referred_user_id])),
            TextEntry::make('code')->label('Attribution code')->copyable(), TextEntry::make('status')->badge(),
            TextEntry::make('registered_at')->dateTime(), TextEntry::make('qualified_at')->dateTime()->placeholder('Not qualified'),
            TextEntry::make('first_qualified_payment_id')->label('Qualifying order')->placeholder('None')->url(fn (AffiliateReferral $record): ?string => $record->first_qualified_payment_id ? PaymentResource::getUrl('view', ['record' => $record->first_qualified_payment_id]) : null),
            TextEntry::make('first_qualified_payment_amount')->label('Qualifying payment')->money('USD')->placeholder('Not recorded'),
            TextEntry::make('commission_expires_at')->dateTime()->placeholder('Not qualified'),
            TextEntry::make('source')->placeholder('Not recorded'), TextEntry::make('landing_path')->placeholder('Not recorded'),
        ]);
    }

    public static function statuses(): array
    {
        return ['registered' => 'Registered', 'qualified' => 'Qualified', 'earning' => 'Earning', 'expired' => 'Expired', 'blocked' => 'Blocked', 'rejected' => 'Rejected'];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('referrer.email')->label('Referrer')->wrap()->searchable()->url(fn (AffiliateReferral $record): string => UserResource::getUrl('view', ['record' => $record->referrer_user_id])),
            TextColumn::make('referred.email')->label('Referred')->wrap()->searchable()->url(fn (AffiliateReferral $record): string => UserResource::getUrl('view', ['record' => $record->referred_user_id])),
            TextColumn::make('code')->searchable(), TextColumn::make('status')->badge()->sortable(),
            TextColumn::make('first_qualified_payment_amount')->label('Qualifying payment')->money('USD'),
            TextColumn::make('commission_expires_at')->label('Commission ends')->dateTime()->sortable(),
        ])->defaultSort('id', 'desc')->stackedOnMobile()->filters([SelectFilter::make('status')->options(static::statuses())])
            ->recordUrl(fn (AffiliateReferral $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->url(fn (AffiliateReferral $record): string => static::getUrl('view', ['record' => $record])), static::statusAction()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAffiliateReferrals::route('/'), 'view' => ViewAffiliateReferral::route('/{record}'), 'edit' => EditAffiliateReferral::route('/{record}/edit')];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['referrer', 'referred']);
    }
}
