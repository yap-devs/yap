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
            return;
        }

        $new_items = $this->filterNewItems($items);
        if ($new_items === []) {
            return;
        }

        DB::transaction(function () use ($new_items, $user) {
            /** @var User $locked_user */
            $locked_user = User::lockForUpdate()->find($user->id);

            $imported_items = [];
            foreach ($new_items as $item) {
                if (Sub2apiUsageRecord::where('remote_usage_id', $item['id'])->exists()) {
                    continue;
                }

                $locked_user->sub2apiUsageRecords()->create([
                    'remote_usage_id' => $item['id'],
                    'remote_request_id' => $item['request_id'] ?? null,
                    'remote_api_key_id' => $item['api_key_id'] ?? $locked_user->sub2api_key_id,
                    'model' => $item['model'] ?? null,
                    'amount' => (float) ($item['actual_cost'] ?? 0),
                    'usage_created_at' => $item['created_at'] ?? null,
                    'payload' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);

                $imported_items[] = $item;
            }

            if ($imported_items !== []) {
                $total_amount = $this->roundUpMoney(
                    array_sum(array_map(fn (array $item) => (float) ($item['actual_cost'] ?? 0), $imported_items))
                );

                if ($total_amount > 0) {
                    $locked_user->decrement('balance', $total_amount);
                    $locked_user->balanceDetails()->create([
                        'amount' => -$total_amount,
                        'description' => $this->buildBatchDescription($imported_items, $total_amount),
                    ]);
                }
            }

            $max_id = max(array_map(fn (array $item) => (int) $item['id'], $new_items));
            $locked_user->sub2api_last_usage_id = max((int) ($locked_user->sub2api_last_usage_id ?? 0), $max_id);
            $locked_user->sub2api_last_synced_at = now();
            $locked_user->save();

            $user->fill($locked_user->getAttributes());
            $user->syncOriginal();
        });
    }

    private function filterNewItems(array $items): array
    {
        $remote_ids = array_column($items, 'id');
        $existing_ids = Sub2apiUsageRecord::whereIn('remote_usage_id', $remote_ids)
            ->pluck('remote_usage_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter($items, fn (array $item) => ! in_array((int) $item['id'], $existing_ids, true)));
    }

    private function roundUpMoney(float $amount): float
    {
        return ceil($amount * 100) / 100;
    }

    private function buildBatchDescription(array $items, float $total_amount): string
    {
        $count = count($items);
        $total_input = 0;
        $total_output = 0;
        $total_cache = 0;
        $models = [];

        foreach ($items as $item) {
            $total_input += (int) ($item['input_tokens'] ?? 0);
            $total_output += (int) ($item['output_tokens'] ?? 0);
            $total_cache += (int) ($item['cache_read_tokens'] ?? 0);
            $model = $item['model'] ?? 'unknown';
            $models[$model] = ($models[$model] ?? 0) + 1;
        }

        $model_summary = implode(', ', array_map(
            fn (string $model, int $n) => $n > 1 ? "$model x$n" : $model,
            array_keys($models),
            array_values($models)
        ));

        $desc = "AI $model_summary | {$count}req in:".number_format($total_input).' out:'.number_format($total_output);
        if ($total_cache > 0) {
            $desc .= ' cache:'.number_format($total_cache);
        }

        return mb_substr($desc, 0, 255);
    }
}
