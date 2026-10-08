<?php

namespace App\Filament\Resources\Nodes;

use App\Filament\Resources\Nodes\Pages\ManageNodes;
use App\Models\Node;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NodeResource extends Resource
{
    protected static ?string $model = Node::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Network';

    public static function canAccess(): bool
    {
        return auth()->id() === 1 && (bool) config('node_agent.enabled');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            Toggle::make('enabled')->default(false),
            Textarea::make('core_config')->label('V2Fly base configuration')->rows(16)->columnSpanFull()
                ->formatStateUsing(fn ($state) => json_encode($state ?? new \stdClass, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                ->rules(['required', 'json', fn (): \Closure => function (string $attribute, mixed $value, \Closure $fail): void {
                    $decoded = is_string($value) ? json_decode($value) : null;
                    if (! $decoded instanceof \stdClass) {
                        $fail('Core configuration must be a JSON object.');
                    }
                }])->dehydrateStateUsing(fn (string $state): \stdClass => json_decode($state, false, 512, JSON_THROW_ON_ERROR)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([TextColumn::make('name')->searchable(), TextColumn::make('traffic_source'), TextColumn::make('routes_count')->counts('routes'), TextColumn::make('desired_revision'), TextColumn::make('applied_revision'), TextColumn::make('last_seen_at')->dateTime(), TextColumn::make('agent_version'), IconColumn::make('enabled')->boolean()])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageNodes::route('/')];
    }
}
