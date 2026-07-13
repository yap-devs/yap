<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Sub2apiKeyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSub2apiUsageCommand extends Command
{
    protected $signature = 'app:sync-sub2api-command';

    protected $description = 'Sync Sub2API usage and key status';

    public function handle(Sub2apiKeyService $sub2api_key_service): int
    {
        User::query()
            ->whereNotNull('sub2api_key_id')
            ->chunkById(100, function ($users) use ($sub2api_key_service) {
                foreach ($users as $user) {
                    try {
                        $sub2api_key_service->syncUserUsageAndStatus($user);
                    } catch (Throwable $e) {
                        Log::channel('job')->error('[SyncSub2apiCommand] Failed for user '.$user->email.': '.$e->getMessage());
                    }
                }
            });

        return self::SUCCESS;
    }
}
