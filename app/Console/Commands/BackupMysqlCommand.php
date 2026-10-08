<?php

namespace App\Console\Commands;

use App\Services\LocalMysqlBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupMysqlCommand extends Command
{
    protected $signature = 'app:backup-mysql';

    protected $description = 'Create a verified local MySQL backup and prune expired backups after success';

    public function handle(LocalMysqlBackupService $backup): int
    {
        try {
            $created = $backup->run();
            $this->info($created ? 'Local MySQL backup completed.' : 'Local MySQL backup skipped.');

            return self::SUCCESS;
        } catch (Throwable) {
            logger()->error('Local MySQL backup failed; existing backups were retained.');
            $this->error('Local MySQL backup failed. Check backup permissions, binaries, and database configuration.');

            return self::FAILURE;
        }
    }
}
