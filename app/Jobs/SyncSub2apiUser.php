<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Sub2apiKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncSub2apiUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $user_id) {}

    /**
     * Execute the job.
     */
    public function handle(Sub2apiKeyService $sub2api_key_service): void
    {
        /** @var User|null $user */
        $user = User::query()->find($this->user_id);
        if (! $user) {
            return;
        }

        $sub2api_key_service->syncUserUsageAndStatus($user);
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel('job')->error('[SyncSub2apiUser] Failed for user '.$this->user_id.': '.$exception?->getMessage());
    }
}
