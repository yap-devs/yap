<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('balance')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('uuid')
                    ->label('UUID')
                    ->required()
                    ->maxLength(255)
                    ->default(''),
                Forms\Components\TextInput::make('traffic_downlink')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('traffic_uplink')
                    ->required()
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('traffic_unpaid')
                    ->numeric()
                    ->default(0),
                Forms\Components\DateTimePicker::make('last_settled_at'),
                Forms\Components\TextInput::make('github_id')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('github_nickname')
                    ->required()
                    ->maxLength(255)
                    ->default(''),
                Forms\Components\DateTimePicker::make('github_created_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('balance')
                    ->money()
                    ->sortable(),
                Tables\Columns\TextColumn::make('traffic_downlink')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('traffic_uplink')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('traffic_unpaid')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_settled_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
