<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VmessServer;
use App\Services\ClashService;
use App\Services\V2rayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class UpdateUserUuid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user
    )
    {
        //
    }

    /**
     * Handle the job.
     *
     * Updates the user's UUID in the database and dispatches a job to generate a new Clash profile link.
     */
    public function handle(): void
    {
        // update user uuid in database
        $this->user->update(['uuid' => (string)Str::uuid()]);
        // create new clash profile
        GenerateClashProfileLink::dispatch();
    }
}
