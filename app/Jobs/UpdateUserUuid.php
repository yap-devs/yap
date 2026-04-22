<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\UuidUpdated;
use App\Services\Sub2apiKeyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UpdateUserUuid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public static function pendingCacheKey(int $user_id): string
    {
        return 'user_uuid_reset_pending:'.$user_id;
    }

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public ?int $old_key_id = null,
        public ?string $old_uuid = null,
        public ?string $new_uuid = null,
    ) {
        //
    }

    /**
     * Handle the job.
     *
     * Updates the user's UUID in the database and dispatches a job to generate a new Clash profile link.
     */
    public function handle(): void
    {
        $old_key_id = $this->old_key_id ?? $this->user->sub2api_key_id;
        $old_uuid = $this->old_uuid ?? $this->user->uuid;
        $new_uuid = $this->new_uuid ?? (string) Str::uuid();

        $this->user->refresh();
        if ($this->user->uuid !== $new_uuid) {
            if ($this->user->uuid !== $old_uuid) {
                Log::channel('job')->warning(
                    "[UpdateUserUuid] Skipping stale UUID rotation for user[{$this->user->email}] current UUID [{$this->user->uuid}] expected [$old_uuid]"
                );

                Cache::forget(self::pendingCacheKey($this->user->id));

                return;
            }

            $this->user->update(['uuid' => $new_uuid]);
            $this->user->refresh();
        }

        Log::channel('job')->info(
            "[UpdateUserUuid] Rotating user[{$this->user->email}] UUID from [$old_uuid] to [$new_uuid]"
        );

        app(Sub2apiKeyService::class)->rotateAfterUuidReset($this->user, $old_key_id);
        // create new clash profile
        GenerateClashProfileLink::dispatch();
        // email user
        $this->user->notify(new UuidUpdated($this->user));
        Cache::forget(self::pendingCacheKey($this->user->id));
    }

    public function failed(?Throwable $exception): void
    {
        Cache::forget(self::pendingCacheKey($this->user->id));
    }
}
