<?php

namespace App\Console\Commands;

use App\Services\BusinessSettings;
use Illuminate\Console\Command;

class InitializeSettings extends Command
{
    protected $signature = 'app:initialize-settings';

    protected $description = 'Import missing business settings from effective application configuration without overwriting existing values';

    public function handle(BusinessSettings $settings): int
    {
        $settings->initialize();
        $this->info('Business settings initialized. Existing values were preserved.');

        return self::SUCCESS;
    }
}
