<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use App\Models\VmessServer;
use App\Services\ClashService;
use App\Services\V2rayService;
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
    protected $description = 'Generate subscription link for all users.';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle()
    {
        GenerateClashProfileLink::dispatchSync();
    }
}
