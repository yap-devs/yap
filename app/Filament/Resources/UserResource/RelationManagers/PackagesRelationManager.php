<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\UserPackages\UserPackageResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Livewire\Attributes\On;

class PackagesRelationManager extends RelationManager
{
    protected static string $relationship = 'packages';

    protected static ?string $title = 'Packages';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    #[On('account-operation-completed.{ownerRecord.id}')]
    public function refreshRecords(): void
    {
        $this->flushCachedTableRecords();
    }

    public function infolist(Schema $schema): Schema
    {
        return UserPackageResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return UserPackageResource::table($table)->modifyQueryUsing(fn ($query) => $query->with(['package', 'user']));
    }
}
