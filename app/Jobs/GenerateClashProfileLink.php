<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VmessServer;
use App\Services\SubscriptionService;
use App\Services\V2rayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class GenerateClashProfileLink implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 600;

    private Collection $vmess_servers;

    /**
     * Execute the job.
     */
    public function handle(SubscriptionService $subscription_service): void
    {
        Cache::lock('generate-clash-profile-link:processing', 330)->block(
            1,
            fn () => $this->generate($subscription_service),
        );
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function failed(?Throwable $exception): void
    {
        logger()->driver('job')->error('[GenerateClashProfileLink] Job failed after all attempts.', [
            'exception' => $exception === null ? null : $exception::class,
        ]);
    }

    private function generate(SubscriptionService $subscription_service): void
    {
        $this->vmess_servers = VmessServer::where('enabled', true)->with('relays')->get();

        $result = [];
        $users = User::withTrashed()->with(['packages' => function ($query) {
            $query->available();
        }])->get();
        foreach ($users as $user) {
            $result[] = $this->preProcessUser($user, $subscription_service);
        }
        $this->processV2Ray($result, $this->vmess_servers);
    }

    /**
     * Processes a user for V2ray services.
     *
     * @param  User  $user  The user entity to be processed
     * @return array [$user, array] [$user, servers belonging to the user]
     */
    private function preProcessUser(User $user, SubscriptionService $subscription_service): array
    {
        if (
            // user deleted
            $user->deleted_at
            // or user is not valid and no active packages
            || (
                ! $user->is_valid
                && $user->packages->isEmpty()
            )
        ) {
            $subscription_service->forgetCache($user);

            return [$user, []];
        }

        $servers = $subscription_service->serversFor($user, $this->vmess_servers);
        $subscription_service->warmCache($user, $servers);

        return [$user, $servers->all()];
    }

    private function processV2Ray(array $result, Collection $vmess_servers): void
    {
        // ['internal_server' => $users] - deduplicate users by uuid per internal_server
        // Multiple vmess_servers may share the same internal_server (different entry points),
        // so we must avoid adding the same user multiple times.
        $server_user_map = [];
        foreach ($vmess_servers as $server) {
            if (empty($server->internal_server)) {
                continue;
            }

            $server_user_map[$server->internal_server] = [];
        }

        foreach ($result as $item) {
            /** @var User $user */
            /** @var array<int, VmessServer> $servers */
            [$user, $servers] = $item;
            if (empty($servers)) {
                continue;
            }

            foreach ($servers as $server) {
                if (empty($server->internal_server)) {
                    continue;
                }

                $server_user_map[$server->internal_server][$user->uuid] = [
                    'id' => $user->uuid,
                    'email' => $user->v2rayStatsLabel(),
                ];
            }
        }

        // Convert associative arrays back to indexed arrays
        $server_user_map = array_map('array_values', $server_user_map);

        $failed_servers = [];

        foreach ($server_user_map as $internal_server => $users) {
            $server_reference = substr(hash('sha256', $internal_server), 0, 12);

            try {
                $v2ray = app()->make(V2rayService::class, [
                    'internal_server' => $internal_server,
                ]);
                $v2ray->addOrRemoveUsers($users);
            } catch (Throwable) {
                $failed_servers[] = $server_reference;
                logger()->driver('job')->error('[GenerateClashProfileLink] Failed to update a V2ray server.', [
                    'server_reference' => $server_reference,
                ]);
            }
        }

        if ($failed_servers !== []) {
            throw new RuntimeException(
                'Failed to update '.count($failed_servers).' V2ray server(s): '.implode(', ', $failed_servers).'.'
            );
        }
    }
}
