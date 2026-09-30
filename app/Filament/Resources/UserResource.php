<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Payment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'md' => 2,
                'xl' => 3,
            ])
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('email_verified_at'),
                TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                TextInput::make('uuid')
                    ->label('UUID')
                    ->required()
                    ->maxLength(255)
                    ->default(''),
                TextInput::make('traffic_downlink')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('traffic_uplink')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('traffic_unpaid')
                    ->numeric()
                    ->default(0),
                DateTimePicker::make('last_settled_at'),
                TextInput::make('github_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('github_nickname')
                    ->required()
                    ->maxLength(255)
                    ->default(''),
                DateTimePicker::make('github_created_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('balance')
                    ->money()
                    ->sortable(),
                TextColumn::make('paid_top_up_count')
                    ->label('Paid Orders')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('paid_top_up_total')
                    ->label('Lifetime Top-Ups')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('last_paid_at')
                    ->label('Last Paid')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('Y-m-d')
                    ->sortable(),
                TextColumn::make('traffic_downlink')
                    ->label('Downlink')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('traffic_uplink')
                    ->label('Uplink')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('traffic_unpaid')
                    ->label('Unpaid')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_settled_at')
                    ->label('Settled')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sub2api_key_status')
                    ->label('AI Key')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state): string => $state ?? 'none')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->stackedOnMobile()
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->filters([
                Filter::make('registered_this_month')
                    ->label('New This Month')
                    ->query(fn (Builder $query): Builder => $query->where('users.created_at', '>=', now()->startOfMonth())),
                Filter::make('paid_this_month')
                    ->label('Paid This Month')
                    ->query(fn (Builder $query): Builder => $query->whereHas('payments', fn (Builder $payments): Builder => $payments
                        ->where('status', Payment::STATUS_PAID)
                        ->where('created_at', '>=', now()->startOfMonth()))),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make()
                    ->labeledFrom('sm'),
                static::adjustBalanceAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function adjustBalanceAction(): Action
    {
        return Action::make('adjustBalance')
            ->label('Adjust Balance')
            ->icon('heroicon-m-banknotes')
            ->labeledFrom('sm')
            ->schema([
                Select::make('operation')
                    ->label('Operation')
                    ->options([
                        'increase' => 'Increase',
                        'decrease' => 'Decrease',
                    ])
                    ->default('increase')
                    ->required()
                    ->native(false)
                    ->selectablePlaceholder(false),
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->required(),
                TextInput::make('description')
                    ->label('Description')
                    ->maxLength(255)
                    ->default('Admin balance adjustment'),
            ])
            ->action(function (User $record, array $data): void {
                $amount = number_format((float) $data['amount'], 2, '.', '');
                $signed_amount = $data['operation'] === 'decrease' ? '-'.$amount : $amount;
                $description = filled($data['description'] ?? null)
                    ? $data['description']
                    : 'Admin balance adjustment';

                DB::transaction(function () use ($record, $signed_amount, $description): void {
                    /** @var User $user */
                    $user = User::query()
                        ->whereKey($record->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();

                    $user->update([
                        'balance' => bcadd((string) $user->balance, $signed_amount, 2),
                    ]);

                    $user->balanceDetails()->create([
                        'amount' => $signed_amount,
                        'description' => $description,
                    ]);
                });

                $record->refresh();

                Notification::make()
                    ->title('Balance adjusted')
                    ->success()
                    ->send();
            });
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->withCount(['payments as paid_top_up_count' => fn (Builder $query): Builder => $query
                ->where('status', Payment::STATUS_PAID)])
            ->withSum(['payments as paid_top_up_total' => fn (Builder $query): Builder => $query
                ->where('status', Payment::STATUS_PAID)], 'amount')
            ->withMax(['payments as last_paid_at' => fn (Builder $query): Builder => $query
                ->where('status', Payment::STATUS_PAID)], 'created_at');
    }
}
