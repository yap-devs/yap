<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliatePromoterResource\Pages\EditAffiliatePromoter;
use App\Filament\Resources\AffiliatePromoterResource\Pages\ListAffiliatePromoters;
use App\Filament\Resources\AffiliatePromoterResource\Pages\ViewAffiliatePromoter;
use App\Filament\Resources\AffiliatePromoterResource\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\AffiliatePromoterResource\RelationManagers\ReferralsRelationManager;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePromoter;
use App\Models\AffiliateReferral;
use App\Services\Affiliate\AffiliateLevelService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class AffiliatePromoterResource extends Resource
{
    protected static ?string $model = AffiliatePromoter::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?string $navigationLabel = 'Promoters';

    protected static ?int $navigationSort = 1;

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

    public static function configureAction(): Action
    {
        return Action::make('configurePromoter')->label('Configure promoter')->authorize(fn (): bool => auth()->id() === 1)
            ->visible(fn (AffiliatePromoter $record): bool => ! $record->trashed() && $record->user !== null)
            ->modalDescription('Changes apply to new commissions. Existing commission amounts and attribution are preserved. Blocked promoters cannot earn or receive pending commissions.')
            ->fillForm(fn (AffiliatePromoter $record): array => ['status' => $record->status, 'rate_percent' => $record->custom_commission_rate !== null ? bcmul($record->custom_commission_rate, '100', 2) : null])
            ->schema([
                Select::make('status')->options([AffiliatePromoter::STATUS_ACTIVE => 'Active', AffiliatePromoter::STATUS_BLOCKED => 'Blocked'])->required(),
                TextInput::make('rate_percent')->label('Custom commission')->suffix('%')->numeric()->minValue(0)->maxValue(100)->rules(['decimal:0,2'])->helperText('Leave empty to use the earned tier rate.'),
            ])->action(function (AffiliatePromoter $record, array $data, Component $livewire): void {
                $rate = filled($data['rate_percent'] ?? null) ? bcdiv((string) $data['rate_percent'], '100', 4) : null;
                $record->update(['status' => $data['status'], 'custom_commission_rate' => $rate]);
                $record->setRawAttributes(static::getEloquentQuery()->findOrFail($record->id)->getAttributes(), true);
                $livewire->dispatch('promoter-operation-completed.'.$record->id);
                Notification::make()->title('Promoter settings updated')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Promoter account')->columns(2)->schema([
                TextEntry::make('user.email')->label('Account')->url(fn (AffiliatePromoter $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
                TextEntry::make('status')->badge(), TextEntry::make('code')->label('Permanent system code')->copyable(),
                TextEntry::make('created_at')->dateTime(),
            ]),
            Section::make('Eligibility and earnings')->columns(2)->schema([
                TextEntry::make('earned_level')->label('Earned tier')->state(fn (AffiliatePromoter $record): string => $record->user ? app(AffiliateLevelService::class)->currentLevel($record->user)->name : 'Account removed'),
                TextEntry::make('custom_commission_rate')->label('Custom rate')->formatStateUsing(fn ($state): string => bcmul((string) $state, '100', 2).'%')->placeholder('Earned tier rate'),
                TextEntry::make('valid_referrals_count')->label('Valid referrals'),
                TextEntry::make('credited_total')->label('Credited commissions')->money('USD')->default(0),
                TextEntry::make('pending_total')->label('Pending commissions')->money('USD')->default(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.email')->label('Account')->wrap()->searchable()->url(fn (AffiliatePromoter $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
            TextColumn::make('code')->label('System code')->searchable()->copyable(), TextColumn::make('status')->badge(),
            TextColumn::make('custom_commission_rate')->label('Custom rate')->formatStateUsing(fn ($state): string => bcmul((string) $state, '100', 2).'%')->placeholder('Tier rate'),
            TextColumn::make('valid_referrals_count')->label('Valid referrals')->sortable(),
            TextColumn::make('pending_total')->label('Pending')->money('USD')->default(0)->sortable(),
            TextColumn::make('credited_total')->label('Credited')->money('USD')->default(0)->sortable(),
        ])->defaultSort('id', 'desc')->stackedOnMobile()->filters([SelectFilter::make('status')->options(['active' => 'Active', 'blocked' => 'Blocked'])])
            ->recordUrl(fn (AffiliatePromoter $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->url(fn (AffiliatePromoter $record): string => static::getUrl('view', ['record' => $record])), static::configureAction()]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAffiliatePromoters::route('/'), 'view' => ViewAffiliatePromoter::route('/{record}'), 'edit' => EditAffiliatePromoter::route('/{record}/edit')];
    }

    public static function getRelations(): array
    {
        return [ReferralsRelationManager::class, CommissionsRelationManager::class];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user')->withCount(['referrals as valid_referrals_count' => fn (Builder $query): Builder => $query->whereIn('status', [AffiliateReferral::STATUS_QUALIFIED, AffiliateReferral::STATUS_EARNING, AffiliateReferral::STATUS_EXPIRED])->whereNotNull('qualified_at')])
            ->withSum(['commissions as credited_total' => fn (Builder $query): Builder => $query->where('status', AffiliateCommission::STATUS_CREDITED)], 'amount')
            ->withSum(['commissions as pending_total' => fn (Builder $query): Builder => $query->where('status', AffiliateCommission::STATUS_PENDING)], 'amount');
    }
}
