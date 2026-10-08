<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\UserResource;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Recharge orders';

    protected static ?string $modelLabel = 'recharge order';

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Order details')->columns(2)->schema([
                TextEntry::make('id')->label('Order number')->formatStateUsing(fn (mixed $state): string => '#'.$state),
                TextEntry::make('status')->badge()->color(fn (string $state): string => $state === Payment::STATUS_PAID ? 'success' : 'gray'),
                TextEntry::make('user.email')->label('Account')->placeholder('Account removed')
                    ->url(fn (Payment $record): ?string => $record->user ? UserResource::getUrl('view', ['record' => $record->user_id]) : null),
                TextEntry::make('amount')->money('USD'),
                TextEntry::make('gateway')->label('Payment channel')->formatStateUsing(fn (string $state): string => static::gateways()[$state] ?? $state),
                TextEntry::make('remote_id')->label('Channel order number')->copyable()->placeholder('Not recorded'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('updated_at')->label('Last order update')->dateTime(),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('Order')->searchable()->sortable()->formatStateUsing(fn (mixed $state): string => '#'.$state),
            TextColumn::make('user.email')->label('Account')->searchable()->wrap()->placeholder('Account removed')
                ->url(fn (Payment $record): ?string => $record->user ? UserResource::getUrl('view', ['record' => $record->user_id]) : null),
            TextColumn::make('amount')->money('USD')->sortable(),
            TextColumn::make('status')->badge()->color(fn (string $state): string => $state === Payment::STATUS_PAID ? 'success' : 'gray')->sortable(),
            TextColumn::make('gateway')->label('Channel')->formatStateUsing(fn (string $state): string => static::gateways()[$state] ?? $state),
            TextColumn::make('remote_id')->label('Channel order')->searchable()->wrap()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')->label('Created')->dateTime('Y-m-d H:i')->sortable(),
        ])->filters([
            SelectFilter::make('status')->options(static::statuses()),
            SelectFilter::make('gateway')->label('Channel')->options(static::gateways()),
            Filter::make('expired_or_cancelled')->label('Expired or cancelled')->query(fn (Builder $query): Builder => $query->whereIn('status', [Payment::STATUS_EXPIRED, Payment::STATUS_CANCELLED])),
        ])->defaultSort('id', 'desc')->striped()->stackedOnMobile()
            ->recordUrl(fn (Payment $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->url(fn (Payment $record): string => static::getUrl('view', ['record' => $record]))]);
    }

    public static function statuses(): array
    {
        return [Payment::STATUS_CREATED => 'Awaiting payment', Payment::STATUS_PAID => 'Paid', Payment::STATUS_CANCELLED => 'Cancelled', Payment::STATUS_EXPIRED => 'Expired', Payment::STATUS_REFUNDED => 'Refunded'];
    }

    public static function gateways(): array
    {
        return [Payment::GATEWAY_ALIPAY => 'Alipay', Payment::GATEWAY_USDT => 'USDT', Payment::GATEWAY_STRIPE => 'Stripe', Payment::GATEWAY_GITHUB => 'GitHub Sponsors'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return ['index' => ListPayments::route('/'), 'view' => ViewPayment::route('/{record}')];
    }
}
