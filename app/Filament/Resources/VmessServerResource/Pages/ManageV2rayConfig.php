<?php

namespace App\Filament\Resources\VmessServerResource\Pages;

use App\Filament\Resources\VmessServerResource;
use App\Models\VmessServer;
use App\Services\V2rayConfigEditor;
use App\Services\V2rayService;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Throwable;

class ManageV2rayConfig extends Page
{
    use InteractsWithRecord;

    protected static string $resource = VmessServerResource::class;

    protected static ?string $title = 'V2Ray 配置';

    public ?array $data = [];

    public bool $showRawJson = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorizeAccess();
        $this->loadRemoteConfig();
    }

    protected function authorizeAccess(): void
    {
        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('远端配置')
                    ->description('配置文件直接从目标机器读取，保存时会备份远端 JSON 并重启 V2Ray。')
                    ->schema([
                        Hidden::make('config_hash'),
                        TextInput::make('remote_server')
                            ->label('SSH 地址')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('config_path')
                            ->label('配置文件')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('service_name')
                            ->label('systemd 服务')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('current_port')
                            ->label('当前 YAP 入口端口')
                            ->disabled()
                            ->dehydrated(false),
                        Textarea::make('load_error')
                            ->label('读取错误')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (Get $get): bool => filled($get('load_error')))
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Inbounds')
                    ->description('显示完整 inbounds；与当前节点端口匹配的 VMess inbound 会标记为当前入口。Raw JSON 会保留未结构化展示的字段。')
                    ->visible(fn (): bool => ! $this->showRawJson)
                    ->schema([
                        Repeater::make('inbounds')
                            ->hiddenLabel()
                            ->addActionLabel('新增 VMess 入口')
                            ->cloneable()
                            ->reorderableWithButtons()
                            ->collapsible()
                            ->itemLabel(fn (array $state): string => $this->inboundItemLabel($state))
                            ->schema([
                                Hidden::make('original_index'),
                                Toggle::make('is_current_entry')
                                    ->label('当前入口')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('tag')
                                    ->label('Tag')
                                    ->maxLength(255),
                                TextInput::make('protocol')
                                    ->label('Protocol')
                                    ->required()
                                    ->maxLength(255)
                                    ->default('vmess'),
                                TextInput::make('listen')
                                    ->label('Listen')
                                    ->maxLength(255),
                                TextInput::make('port')
                                    ->label('Port')
                                    ->required()
                                    ->numeric()
                                    ->minValue(1)
                                    ->maxValue(65535),
                                TextInput::make('clients_count')
                                    ->label('Clients')
                                    ->disabled()
                                    ->dehydrated(false),
                                Repeater::make('clients')
                                    ->label('VMess clients')
                                    ->helperText('当前入口的 clients 会被 YAP 用户同步任务按用户状态覆盖。')
                                    ->addActionLabel('新增 client')
                                    ->visible(fn (Get $get): bool => $get('protocol') === 'vmess')
                                    ->schema([
                                        Hidden::make('raw_json'),
                                        TextInput::make('id')
                                            ->label('UUID')
                                            ->required(),
                                        TextInput::make('email')
                                            ->label('Email')
                                            ->maxLength(255),
                                        TextInput::make('alterId')
                                            ->label('Alter ID')
                                            ->numeric(),
                                        TextInput::make('security')
                                            ->label('Security')
                                            ->maxLength(255),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                                Textarea::make('raw_json')
                                    ->label('Raw inbound JSON')
                                    ->helperText('保存时会先读取这里的 JSON，再用上面的 tag/listen/port/protocol/clients 覆盖对应字段。新增入口可留空。')
                                    ->rows(12)
                                    ->nullable()
                                    ->json()
                                    ->columnSpanFull(),
                            ])
                            ->columns(3),
                    ]),
                Section::make('原始 JSON 配置')
                    ->description('这是完整 config.json 预览，方便复制到新机器。切换到这里时会根据当前表单状态重新生成。')
                    ->visible(fn (): bool => $this->showRawJson)
                    ->schema([
                        Textarea::make('raw_config_json')
                            ->label('config.json')
                            ->rows(34)
                            ->readOnly()
                            ->dehydrated(false)
                            ->helperText('只读预览，可直接全选复制。保存请切回表单视图。')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function reloadConfig(): void
    {
        $this->loadRemoteConfig();

        Notification::make()
            ->success()
            ->title('已重新读取远端配置')
            ->send();
    }

    public function save(): void
    {
        $this->authorizeAccess();

        try {
            $data = $this->content->getState();
            $record = $this->getVmessRecord();
            $editor = app(V2rayConfigEditor::class);
            $v2ray = new V2rayService($record->internal_server);
            $latest_json = $v2ray->readConfig();

            if (($data['config_hash'] ?? null) !== $editor->hash($latest_json)) {
                Notification::make()
                    ->danger()
                    ->title('远端配置已变化')
                    ->body('保存前远端 config.json 已被修改，请先重新读取后再保存。')
                    ->send();

                return;
            }

            $config = $editor->decode($latest_json);
            $config = $editor->applyInboundForm($config, $data['inbounds'] ?? []);
            $json = $editor->encode($config);

            $v2ray->writeConfig($json);
            $this->fillFromJson($json);

            Notification::make()
                ->success()
                ->title('V2Ray 配置已保存并重启')
                ->send();
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->danger()
                ->title('保存 V2Ray 配置失败')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function toggleRawJsonView(): void
    {
        if (! $this->showRawJson) {
            try {
                $this->refreshRawJsonFromForm();
            } catch (Throwable $e) {
                Notification::make()
                    ->danger()
                    ->title('生成原始 JSON 失败')
                    ->body($e->getMessage())
                    ->send();

                return;
            }
        }

        $this->showRawJson = ! $this->showRawJson;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleRawJsonView')
                ->label(fn (): string => $this->showRawJson ? '显示表单' : '显示原始 JSON')
                ->icon(fn (): string => $this->showRawJson ? 'heroicon-o-list-bullet' : 'heroicon-o-code-bracket-square')
                ->color('gray')
                ->action(function (): void {
                    $this->toggleRawJsonView();
                }),
            Action::make('reloadConfig')
                ->label('重新读取')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $this->reloadConfig();
                }),
            Action::make('save')
                ->label('保存并重启')
                ->icon('heroicon-o-check')
                ->color('primary')
                ->visible(fn (): bool => ! $this->showRawJson)
                ->requiresConfirmation()
                ->modalHeading('保存并重启 V2Ray')
                ->modalDescription('这会备份远端配置文件、覆盖 config.json 并重启 v2ray 服务。')
                ->action(function (): void {
                    $this->save();
                }),
        ];
    }

    private function loadRemoteConfig(): void
    {
        $record = $this->getVmessRecord();

        try {
            $json = (new V2rayService($record->internal_server))->readConfig();
            $this->fillFromJson($json);
        } catch (Throwable $e) {
            report($e);

            $this->data = $this->baseData($record) + [
                'config_hash' => '',
                'inbounds' => [],
                'load_error' => $e->getMessage(),
                'raw_config_json' => '',
            ];

            $this->content->fill($this->data);

            Notification::make()
                ->danger()
                ->title('读取远端 V2Ray 配置失败')
                ->body($e->getMessage())
                ->send();
        }
    }

    private function fillFromJson(string $json): void
    {
        $record = $this->getVmessRecord();
        $editor = app(V2rayConfigEditor::class);
        $config = $editor->decode($json);

        $this->data = $this->baseData($record) + [
            'config_hash' => $editor->hash($json),
            'inbounds' => $editor->inboundsToForm($config, (int) $record->port),
            'load_error' => '',
            'raw_config_json' => $editor->encode($config),
        ];

        $this->content->fill($this->data);
    }

    private function refreshRawJsonFromForm(): void
    {
        $editor = app(V2rayConfigEditor::class);
        $data = $this->content->getState();
        $config = $editor->decode($this->data['raw_config_json'] ?? '{}');
        $config = $editor->applyInboundForm($config, $data['inbounds'] ?? []);

        $this->data['raw_config_json'] = $editor->encode($config);
    }

    private function baseData(VmessServer $record): array
    {
        return [
            'remote_server' => $record->internal_server,
            'config_path' => V2rayService::DEFAULT_CONFIG_PATH,
            'service_name' => V2rayService::DEFAULT_SERVICE_NAME,
            'current_port' => $record->port,
        ];
    }

    private function inboundItemLabel(array $state): string
    {
        $label = ($state['tag'] ?? '') !== '' ? $state['tag'] : 'untagged';
        $label .= ' / '.($state['protocol'] ?? 'unknown');

        if (($state['port'] ?? null) !== null && $state['port'] !== '') {
            $label .= ' / :'.$state['port'];
        }

        return ($state['is_current_entry'] ?? false) ? '当前入口 - '.$label : $label;
    }

    private function getVmessRecord(): VmessServer
    {
        /** @var VmessServer $record */
        $record = $this->getRecord();

        return $record;
    }
}
