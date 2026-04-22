<?php

namespace App\Services;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class Sub2apiKeyService
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public function __construct(
        private readonly Sub2apiService $sub2api_service,
        private readonly Sub2apiUsageSyncService $usage_sync_service,
    ) {}

    public function getDisplayKey(User $user): ?string
    {
        if (! $user->sub2api_key_id) {
            return null;
        }

        return $this->sub2api_service->generateCustomKey($user);
    }

    public function canCreate(User $user): bool
    {
        return $this->sub2api_service->isEnabled()
            && (float) $user->balance > $this->sub2api_service->getCreateThreshold();
    }

    public function createForUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            /** @var User $locked_user */
            $locked_user = User::lockForUpdate()->find($user->id);
            if ($locked_user->sub2api_key_id) {
                return;
            }

            if (! $this->canCreate($locked_user)) {
                throw new DomainException($this->getCreateErrorMessage());
            }
        });

        $created_key = $this->sub2api_service->createKey($user);
        $should_cleanup_remote_key = true;

        try {
            DB::transaction(function () use ($created_key, $user, &$should_cleanup_remote_key) {
                /** @var User $locked_user */
                $locked_user = User::lockForUpdate()->find($user->id);
                if ($locked_user->sub2api_key_id) {
                    return;
                }

                if (! $this->canCreate($locked_user)) {
                    throw new DomainException($this->getCreateErrorMessage());
                }

                $locked_user->sub2api_key_id = $created_key['id'];
                $locked_user->sub2api_key_status = self::STATUS_ACTIVE;
                $locked_user->save();

                $user->fill($locked_user->getAttributes());
                $user->syncOriginal();
                $should_cleanup_remote_key = false;
            });
        } catch (Throwable $e) {
            $this->cleanupRemoteKey((int) $created_key['id']);

            throw $e;
        }

        if ($should_cleanup_remote_key) {
            $this->cleanupRemoteKey((int) $created_key['id']);
        }
    }

    public function syncUserStatus(User $user): void
    {
        if (! $user->sub2api_key_id) {
            return;
        }

        $desired_status = (float) $user->balance > $this->sub2api_service->getKeepActiveThreshold()
            ? self::STATUS_ACTIVE
            : self::STATUS_INACTIVE;

        if ($user->sub2api_key_status === $desired_status) {
            return;
        }

        $this->sub2api_service->updateKeyStatus((int) $user->sub2api_key_id, $desired_status);

        $user->forceFill([
            'sub2api_key_status' => $desired_status,
        ])->save();
    }

    public function rotateAfterUuidReset(User $user, ?int $old_key_id = null): void
    {
        $old_key_id = $old_key_id ?? $user->sub2api_key_id;
        if (! $old_key_id) {
            return;
        }

        $this->usage_sync_service->syncUser($user, $old_key_id);

        if ($user->sub2api_key_id && (int) $user->sub2api_key_id !== $old_key_id) {
            $this->sub2api_service->deleteKey($old_key_id);

            return;
        }

        $new_key = $this->sub2api_service->createKey($user);

        $new_status = (float) $user->balance > $this->sub2api_service->getKeepActiveThreshold()
            ? self::STATUS_ACTIVE
            : self::STATUS_INACTIVE;

        if ($new_status === self::STATUS_INACTIVE) {
            $this->sub2api_service->updateKeyStatus((int) $new_key['id'], $new_status);
        }

        try {
            DB::transaction(function () use ($new_key, $new_status, $user) {
                /** @var User $locked_user */
                $locked_user = User::lockForUpdate()->find($user->id);
                $locked_user->forceFill([
                    'sub2api_key_id' => $new_key['id'],
                    'sub2api_key_status' => $new_status,
                    'sub2api_last_usage_id' => null,
                    'sub2api_last_synced_at' => null,
                ])->save();

                $user->fill($locked_user->getAttributes());
                $user->syncOriginal();
            });
        } catch (Throwable $e) {
            $this->cleanupRemoteKey((int) $new_key['id']);

            throw $e;
        }

        $this->sub2api_service->deleteKey($old_key_id);
    }

    private function cleanupRemoteKey(int $key_id): void
    {
        try {
            $this->sub2api_service->deleteKey($key_id);
        } catch (Throwable $e) {
            Log::channel('job')->warning('[Sub2apiKeyService] Failed to cleanup remote key '.$key_id.': '.$e->getMessage());
        }
    }

    private function getCreateErrorMessage(): string
    {
        return $this->sub2api_service->isEnabled()
            ? 'Your balance must be above the AI key creation threshold.'
            : 'AI key creation is currently disabled.';
    }
}
