<?php

namespace App\Filament\Resources\NodeRoutes;

use App\Filament\Resources\NodeRoutes\Pages\ManageNodeRoutes;
use App\Filament\Resources\Nodes\NodeResource;
use App\Models\Node;
use App\Models\NodeRoute;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class NodeRouteResource extends Resource
{
    protected static ?string $model = NodeRoute::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Network';

    protected static ?string $navigationLabel = 'Routes';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return auth()->id() === 1 && (bool) config('node_agent.enabled');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Section::make('Public entry')->columns(2)->columnSpanFull()->schema([
                Select::make('node_id')->label('Node')->disabled(fn (?NodeRoute $record): bool => $record !== null)->relationship('node', 'name')->required()->searchable()->preload(),
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('server')->label('Public address')->required()->maxLength(255),
                TextInput::make('port')->label('Public port')->required()->integer()->minValue(1)->maxValue(65535),
                TextInput::make('rate')->label('Traffic multiplier')->suffix('×')->required()->numeric()->minValue(0)->maxValue(999999.99)->rules(['decimal:0,2'])->default('1.00')
                    ->helperText('Billing uses the multiplier recorded at collection time.'),
                TextInput::make('sort')->label('Display order')->integer()->minValue(0)->default(0),
                Toggle::make('enabled')->default(false), Toggle::make('for_low_priority')->label('Allow low-balance users')->default(false),
            ]),
            Section::make('Handler identity')->description('Identity is fixed after creation. Enabled ports sharing a handler must stay contiguous.')->columns(2)->columnSpanFull()->schema([
                TextInput::make('inbound_tag')->disabled(fn (?NodeRoute $record): bool => $record !== null)->required()->default('yap-vmess')->maxLength(64)->rules(['regex:/^yap-[a-zA-Z0-9_-]+$/', 'not_in:yap-api']),
                TextInput::make('listen_port')->label('Node listen port')->disabled(fn (?NodeRoute $record): bool => $record !== null)->required()->integer()->minValue(1)->maxValue(65535),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('node.name')->label('Node')->searchable()->url(fn (NodeRoute $record): string => NodeResource::getUrl('view', ['record' => $record->node_id])),
            TextColumn::make('server')->label('Public address')->searchable()->wrap(), TextColumn::make('port')->label('Public port'),
            TextColumn::make('listen_port')->label('Listen port')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('rate')->label('Multiplier')->suffix('×'), IconColumn::make('for_low_priority')->label('Low balance')->boolean(),
            IconColumn::make('enabled')->boolean(), IconColumn::make('node.enabled')->label('Node enabled')->boolean(),
        ])->defaultSort('sort')->filters([
            SelectFilter::make('node_id')->label('Node')->options(fn (): array => Node::pluck('name', 'id')->all()),
            TernaryFilter::make('enabled'), TernaryFilter::make('for_low_priority')->label('Low-balance access'),
        ])->recordActions([EditAction::make()->label('Edit route')
            ->after(function (NodeRoute $record, Component $livewire): void {
                $record->refresh();
                $livewire->dispatch('node-operation-completed.'.$record->node_id);
            })]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('node');
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageNodeRoutes::route('/')];
    }
}
