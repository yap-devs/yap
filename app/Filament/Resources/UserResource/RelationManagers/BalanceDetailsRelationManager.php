<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\BalanceDetails\BalanceDetailResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class BalanceDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'balanceDetails';

    protected static ?string $title = 'Balance ledger';

    protected static bool $isLazy = false;

    #[On('account-operation-completed.{ownerRecord.id}')]
    public function refreshRecords(): void
    {
        $this->flushCachedTableRecords();
    }

    public function infolist(Schema $schema): Schema
    {
        return BalanceDetailResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return BalanceDetailResource::table($table)->modifyQueryUsing(fn ($query) => $query->with('user'));
    }
}
