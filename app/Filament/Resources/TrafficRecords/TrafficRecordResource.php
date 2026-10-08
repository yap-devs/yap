<?php

namespace App\Filament\Resources\TrafficRecords;

use App\Filament\Resources\Nodes\NodeResource;
use App\Filament\Resources\TrafficRecords\Pages\ManageTrafficRecords;
use App\Filament\Resources\UserResource;
use App\Models\Node;
use App\Models\TrafficRecord;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrafficRecordResource extends Resource
{
    protected static ?string $model = TrafficRecord::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Network';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return auth()->id() === 1 && (bool) config('node_agent.enabled');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('batch.node.name')->label('Node'), TextEntry::make('user_id')->label('Account ID'),
            TextEntry::make('route.name')->label('Route'), TextEntry::make('applied_rate')->label('Recorded multiplier')->suffix('×'),
            TextEntry::make('raw_uplink')->label('Raw upload bytes')->numeric(), TextEntry::make('raw_downlink')->label('Raw download bytes')->numeric(),
            TextEntry::make('billed_uplink')->label('Billed upload bytes')->numeric(), TextEntry::make('billed_downlink')->label('Billed download bytes')->numeric(),
            TextEntry::make('batch.received_at')->label('Received')->dateTime(), TextEntry::make('batch.aggregated_at')->label('Aggregated')->dateTime()->placeholder('Pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        $gib = fn ($state): string => number_format((float) $state / 1073741824, 4).' GiB';

        return $table->columns([
            TextColumn::make('batch.node.name')->label('Node')->url(fn (TrafficRecord $record): string => NodeResource::getUrl('view', ['record' => $record->batch->node_id])),
            TextColumn::make('user_id')->label('Account ID')->searchable()->url(fn (TrafficRecord $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
            TextColumn::make('route.name')->label('Route')->searchable()->placeholder('Route removed'),
            TextColumn::make('raw_uplink')->label('Raw upload')->formatStateUsing($gib), TextColumn::make('raw_downlink')->label('Raw download')->formatStateUsing($gib),
            TextColumn::make('applied_rate')->label('Multiplier')->suffix('×'),
            TextColumn::make('billed_uplink')->label('Billed upload')->formatStateUsing($gib), TextColumn::make('billed_downlink')->label('Billed download')->formatStateUsing($gib),
            TextColumn::make('created_at')->label('Received')->dateTime('Y-m-d H:i')->sortable(),
        ])->defaultSort('id', 'desc')->stackedOnMobile()->filters([
            SelectFilter::make('node_id')->label('Node')->options(fn (): array => Node::pluck('name', 'id')->all())
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['value'] ?? null, fn (Builder $query, $value): Builder => $query->whereHas('batch', fn (Builder $query): Builder => $query->where('node_id', $value)))),
            Filter::make('received')->schema([DatePicker::make('from')->label('From'), DatePicker::make('until')->label('Until')->afterOrEqual('from')])
                ->query(fn (Builder $query, array $data): Builder => $query->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date))),
        ])->recordActions([ViewAction::make()]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['batch.node', 'route']);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageTrafficRecords::route('/')];
    }
}
