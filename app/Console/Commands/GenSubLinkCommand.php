<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use Illuminate\Console\Command;
use Throwable;

class GenSubLinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:gen-sub-link-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild subscription cache and sync users.';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle()
    {
        GenerateClashProfileLink::dispatchSync();

        $this->info('Rebuilt subscription cache and synced users.');
    }
}
