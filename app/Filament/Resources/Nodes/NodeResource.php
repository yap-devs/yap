<?php

namespace App\Filament\Resources\Nodes;

use App\Filament\Resources\Nodes\Pages\ManageNodes;
use App\Filament\Resources\Nodes\Pages\ViewNode;
use App\Filament\Resources\Nodes\RelationManagers\RoutesRelationManager;
use App\Models\Node;
use App\Services\NodeHealthService;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return auth()->id() === 1 && (bool) config('node_agent.enabled');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255),
            Toggle::make('enabled')->default(false)->helperText('A node must have a provisioned agent before it can be enabled.'),
            Section::make('Advanced configuration')->collapsed()->columnSpanFull()->schema([
                Textarea::make('core_config')->label('V2Fly base configuration')->rows(16)->columnSpanFull()
                    ->formatStateUsing(fn ($state): string => json_encode($state ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                    ->rules(['required', 'json', fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                        $decoded = is_string($value) ? json_decode($value) : null;
                        if (! $decoded instanceof \stdClass) {
                            $fail('Core configuration must be a JSON object.');
                        }
                    }])->dehydrateStateUsing(fn (string $state): \stdClass => json_decode($state, false, 512, JSON_THROW_ON_ERROR)),
            ]),
        ]);
    }

    public static function editAction(): EditAction
    {
        return EditAction::make()->label('Edit settings')
            ->after(function (Node $record, Component $livewire): void {
                $record->setRawAttributes(static::getEloquentQuery()->findOrFail($record->id)->getAttributes(), true);
                $livewire->dispatch('node-operation-completed.'.$record->id);
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Node health')->columns(2)->schema([
                TextEntry::make('name'), TextEntry::make('health')->state(fn (Node $record): string => NodeHealthService::labels()[app(NodeHealthService::class)->state($record)])->badge(),
                TextEntry::make('last_seen_at')->label('Last heartbeat')->dateTime()->placeholder('Not reported'),
                TextEntry::make('last_traffic_at')->label('Last traffic batch')->dateTime()->placeholder('Not reported'),
                TextEntry::make('agent_version')->placeholder('Not reported'), TextEntry::make('core_version')->placeholder('Not reported'),
            ]),
            Section::make('Configuration delivery')->description('Heartbeat and revision acknowledgement do not verify public entry connectivity.')->columns(2)->schema([
                TextEntry::make('desired_revision')->label('Desired revision'), TextEntry::make('applied_revision')->label('Applied revision'),
                TextEntry::make('agent_provisioned')->label('Agent provisioned')->state(fn (Node $record): string => filled($record->agent_token_hash) ? 'Yes' : 'No'),
                TextEntry::make('routes_count')->label('Routes'),
                TextEntry::make('unaggregated_batches_count')->label('Batches awaiting aggregation'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('health')->label('Health')->state(fn (Node $record): string => NodeHealthService::labels()[app(NodeHealthService::class)->state($record)])->badge()
                ->color(fn (Node $record): string => match (app(NodeHealthService::class)->state($record)) {
                    'current' => 'success', 'offline' => 'danger', 'pending', 'unseen' => 'warning', default => 'gray'
                }),
            TextColumn::make('routes_count')->label('Routes'),
            TextColumn::make('desired_revision')->label('Desired'), TextColumn::make('applied_revision')->label('Applied'),
            TextColumn::make('last_seen_at')->label('Heartbeat')->since()->placeholder('Not reported'),
            TextColumn::make('last_traffic_at')->label('Traffic batch')->since()->placeholder('Not reported'),
            TextColumn::make('agent_version')->label('Agent')->toggleable(isToggledHiddenByDefault: true), IconColumn::make('enabled')->boolean(),
        ])->filters([SelectFilter::make('health')->options(NodeHealthService::labels())->query(fn (Builder $query, array $data): Builder => app(NodeHealthService::class)->filter($query, $data['value'] ?? null))])
            ->defaultSort('id', 'desc')->recordUrl(fn (Node $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->url(fn (Node $record): string => static::getUrl('view', ['record' => $record])), static::editAction()]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount(['routes', 'batches as unaggregated_batches_count' => fn (Builder $query): Builder => $query->whereNull('aggregated_at')])->withMax('batches as last_traffic_at', 'received_at');
    }

    public static function getRelations(): array
    {
        return [RoutesRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => ManageNodes::route('/'), 'view' => ViewNode::route('/{record}')];
    }
}
