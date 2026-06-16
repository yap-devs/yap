<?php

namespace App\Console\Commands;

use App\Services\ClientDownloadMirrorService;
use Illuminate\Console\Command;

class SyncClientDownloadsCommand extends Command
{
    protected $signature = 'app:sync-client-downloads {--dry-run : Resolve GitHub assets without uploading to R2}';

    protected $description = 'Sync client download assets from GitHub releases to Cloudflare R2';

    public function handle(ClientDownloadMirrorService $mirror_service): int
    {
        $manifest = $mirror_service->sync((bool) $this->option('dry-run'));

        foreach ($manifest['assets'] as $asset) {
            $this->line($asset['label'].': '.$asset['version'].' -> '.$asset['latest_path']);
        }

        $this->info('Synced '.count($manifest['assets']).' client download assets.');

        return self::SUCCESS;
    }
}
