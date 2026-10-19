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
        $failed_count = 0;

        User::query()
            ->whereNotNull('sub2api_key_id')
            ->chunkById(100, function ($users) use ($sub2api_key_service, &$failed_count) {
                foreach ($users as $user) {
                    try {
                        $sub2api_key_service->syncUserUsageAndStatus($user);
                    } catch (Throwable $e) {
                        $failed_count++;
                        Log::channel('job')->error('[SyncSub2apiCommand] User sync failed', ['user_id' => $user->id]);
                        report($e);
                    }
                }
            });

        if ($failed_count > 0) {
            $this->error("Sub2API sync failed for {$failed_count} user(s).");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
