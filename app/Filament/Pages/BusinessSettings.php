<?php

namespace App\Filament\Pages;

use App\Services\BusinessSettings as Settings;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class BusinessSettings extends Page
{
    protected string $view = 'filament.pages.business-settings';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 3;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->id() === 1;
    }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->form->fill(app(Settings::class)->all());
    }

    public function form(Schema $schema): Schema
    {
        $fields = [];
        foreach (Settings::DEFINITIONS as $key => $definition) {
            $fields[] = TextInput::make($key)
                ->label($definition['label'])
                ->required()
                ->rules(Settings::rules()[$key]);
        }

        return $schema->components($fields)->statePath('data');
    }

    public function save(): void
    {
        abort_unless(self::canAccess(), 403);
        app(Settings::class)->save($this->form->getState(), auth()->user());
        Notification::make()->title('Business settings saved')->success()->send();
    }

    public function getSubheading(): ?string
    {
        return 'Changes apply to new operations. Existing payment orders keep their original amounts. Traffic is priced at settlement time; package sale prices are managed separately.';
    }
}
