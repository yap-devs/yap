<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VmessServerResource\Pages\CreateVmessServer;
use App\Filament\Resources\VmessServerResource\Pages\EditVmessServer;
use App\Filament\Resources\VmessServerResource\Pages\ListVmessServers;
use App\Filament\Resources\VmessServerResource\Pages\ManageV2rayConfig;
use App\Models\VmessServer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VmessServerResource extends Resource
{
    protected static ?string $model = VmessServer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('节点信息')
                    ->description('YAP 业务节点配置；V2Ray 远端 JSON 在编辑页的“V2Ray 配置”中维护。')
                    ->schema([
                        TextInput::make('name')
                            ->label('名称')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('server')
                            ->label('外部地址')
                            ->helperText('写入 Clash 订阅的 server 字段；只使用中转时可留空。')
                            ->maxLength(255),
                        TextInput::make('port')
                            ->label('入站端口')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(65535),
                        TextInput::make('rate')
                            ->label('倍率')
                            ->required()
                            ->numeric()
                            ->default(1.0),
                        TextInput::make('internal_server')
                            ->label('SSH 地址')
                            ->helperText('格式为 host 或 host:port，用于远程写入 V2Ray 配置。')
                            ->required()
                            ->maxLength(255)
                            ->default(''),
                        Toggle::make('enabled')
                            ->label('启用')
                            ->default(true)
                            ->required(),
                        Toggle::make('for_low_priority')
                            ->label('低优先级用户可用')
                            ->default(false)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('relays_count')->counts('relays'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('server')
                    ->searchable(),
                TextColumn::make('port')
                    ->numeric(thousandsSeparator: false)
                    ->sortable(),
                TextColumn::make('rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('internal_server')
                    ->searchable(),
                ToggleColumn::make('enabled'),
                ToggleColumn::make('for_low_priority'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                self::v2rayConfigAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListVmessServers::route('/'),
            'create' => CreateVmessServer::route('/create'),
            'edit' => EditVmessServer::route('/{record}/edit'),
            'v2ray-config' => ManageV2rayConfig::route('/{record}/v2ray-config'),
        ];
    }

    public static function v2rayConfigAction(): Action
    {
        return Action::make('v2rayConfig')
            ->label('V2Ray 配置')
            ->icon('heroicon-o-server-stack')
            ->visible(fn (VmessServer $record): bool => ! $record->trashed())
            ->url(fn (VmessServer $record): string => static::getUrl('v2ray-config', ['record' => $record]));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
