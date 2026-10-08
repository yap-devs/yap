<?php

namespace App\Jobs;

use App\Services\NodeAuthorizationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshNodeAuthorization implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param array<int, int>|null $node_ids */
    public function __construct(public ?array $node_ids = null) {}

    public function handle(NodeAuthorizationService $service): void
    {
        $service->refresh($this->node_ids);
    }

    public function failed(?Throwable $exception): void
    {
        logger()->error('Node authorization refresh failed.', ['exception' => $exception ? $exception::class : null]);
    }
}
