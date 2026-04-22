<?php

namespace App\Services;

use App\Models\Sub2apiUsageRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class Sub2apiUsageSyncService
{
    public function __construct(
        private readonly Sub2apiService $sub2api_service,
    ) {}

    public function syncUser(User $user, ?int $key_id = null): void
    {
        $key_id = $key_id ?? $user->sub2api_key_id;
        if (! $key_id) {
            return;
        }

        $items = $this->sub2api_service->listUsage($key_id, (int) ($user->sub2api_last_usage_id ?? 0));
        if ($items === []) {
            $user->forceFill(['sub2api_last_synced_at' => now()])->save();

            return;
        }

        foreach ($items as $item) {
            DB::transaction(function () use ($item, $user) {
                if (Sub2apiUsageRecord::where('remote_usage_id', $item['id'])->exists()) {
                    return;
                }

                /** @var User $locked_user */
                $locked_user = User::lockForUpdate()->find($user->id);

                if (Sub2apiUsageRecord::where('remote_usage_id', $item['id'])->exists()) {
                    return;
                }

                $amount = $this->roundUpMoney((float) ($item['actual_cost'] ?? 0));
                $locked_user->sub2apiUsageRecords()->create([
                    'remote_usage_id' => $item['id'],
                    'remote_request_id' => $item['request_id'] ?? null,
                    'remote_api_key_id' => $item['api_key_id'] ?? $locked_user->sub2api_key_id,
                    'model' => $item['model'] ?? null,
                    'amount' => $amount,
                    'usage_created_at' => $item['created_at'] ?? null,
                    'payload' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                if ($amount > 0) {
                    $locked_user->decrement('balance', $amount);
                    $locked_user->balanceDetails()->create([
                        'amount' => -$amount,
                        'description' => $this->buildDescription($item, $amount),
                    ]);
                }

                $locked_user->sub2api_last_usage_id = max((int) ($locked_user->sub2api_last_usage_id ?? 0), (int) $item['id']);
                $locked_user->sub2api_last_synced_at = now();
                $locked_user->save();

                $user->fill($locked_user->getAttributes());
                $user->syncOriginal();
            });
        }
    }

    private function roundUpMoney(float $amount): float
    {
        return ceil($amount * 100) / 100;
    }

    private function buildDescription(array $item, float $amount): string
    {
        $model = $item['model'] ?? 'unknown';
        $input = number_format((int) ($item['input_tokens'] ?? 0));
        $output = number_format((int) ($item['output_tokens'] ?? 0));
        $cache = (int) ($item['cache_read_tokens'] ?? 0);

        $desc = "AI $model | in:$input out:$output";
        if ($cache > 0) {
            $desc .= ' cache:'.number_format($cache);
        }

        return $desc;
    }
}
