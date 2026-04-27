<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class Sub2apiPricingService
{
    private const CACHE_TTL_SECONDS = 604800;

    private const MAX_MODELS = 100;

    public function __construct(private readonly Sub2apiService $sub2api_service) {}

    public function getPricingGuide(): array
    {
        if (! $this->sub2api_service->isEnabled()) {
            return $this->emptyGuide();
        }

        $group_id = (int) config('services.sub2api.default_group_id');
        if ($group_id <= 0) {
            return $this->emptyGuide();
        }

        $cached_guide = Cache::get($this->cacheKey($group_id));

        return is_array($cached_guide) ? $cached_guide : $this->emptyGuide();
    }

    public function refreshPricingGuide(?int $group_id = null): array
    {
        if (! $this->sub2api_service->isEnabled()) {
            return $this->emptyGuide();
        }

        $group_id = $group_id ?: (int) config('services.sub2api.default_group_id');
        if ($group_id <= 0) {
            return $this->emptyGuide();
        }

        $guide = $this->fetchPricingGuide($group_id);
        if ($guide['available']) {
            Cache::put($this->cacheKey($group_id), $guide, now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return $guide;
    }

    public function refreshPricingGuideIfMissing(): array
    {
        if (! $this->sub2api_service->isEnabled()) {
            return $this->emptyGuide();
        }

        $group_id = (int) config('services.sub2api.default_group_id');
        if ($group_id <= 0) {
            return $this->emptyGuide();
        }

        $cached_guide = Cache::get($this->cacheKey($group_id));
        if (is_array($cached_guide)) {
            return $cached_guide;
        }

        return $this->refreshPricingGuide($group_id);
    }

    private function fetchPricingGuide(int $group_id): array
    {
        try {
            $group = $this->sub2api_service->request('get', '/api/v1/admin/groups/'.$group_id);
            $multiplier = (float) ($group['rate_multiplier'] ?? 1);
            $models = $this->getModelsForGroup($group_id);

            $pricing = [];
            foreach (array_slice($models, 0, self::MAX_MODELS) as $model) {
                $default_pricing = $this->sub2api_service->request('get', '/api/v1/admin/channels/model-pricing', query: [
                    'model' => $model,
                ]);

                if (! ($default_pricing['found'] ?? false)) {
                    continue;
                }

                $pricing[] = [
                    'model' => $model,
                    'input_per_million' => $this->toPerMillion($default_pricing['input_price'] ?? null, $multiplier),
                    'cache_read_per_million' => $this->toPerMillion($default_pricing['cache_read_price'] ?? null, $multiplier),
                    'cache_write_per_million' => $this->toPerMillion($default_pricing['cache_write_price'] ?? null, $multiplier),
                    'output_per_million' => $this->toPerMillion($default_pricing['output_price'] ?? null, $multiplier),
                ];
            }

            usort($pricing, fn (array $a, array $b) => strnatcasecmp($a['model'], $b['model']));

            return [
                'available' => $pricing !== [],
                'group_name' => $group['name'] ?? 'Default',
                'group_multiplier' => $multiplier,
                'models' => $pricing,
                'cached_for_seconds' => self::CACHE_TTL_SECONDS,
                'max_models' => self::MAX_MODELS,
            ];
        } catch (Throwable $e) {
            Log::warning('Failed to fetch AI model pricing.', [
                'group_id' => $group_id,
                'message' => $e->getMessage(),
            ]);

            return $this->emptyGuide();
        }
    }

    private function getModelsForGroup(int $group_id): array
    {
        $models = [];
        $accounts = $this->sub2api_service->request('get', '/api/v1/admin/accounts', query: [
            'page' => 1,
            'page_size' => 100,
            'group' => $group_id,
            'lite' => 'true',
        ]);

        foreach (($accounts['items'] ?? []) as $account) {
            $mapping = $account['credentials']['model_mapping'] ?? $account['model_mapping'] ?? [];
            if (is_string($mapping)) {
                $mapping = json_decode($mapping, true) ?: [];
            }

            if (! is_array($mapping)) {
                continue;
            }

            foreach (array_keys($mapping) as $model) {
                $models[] = $model;
            }
        }

        $models = array_values(array_unique($models));
        sort($models, SORT_NATURAL);

        return $models;
    }

    private function toPerMillion(mixed $price, float $multiplier): ?float
    {
        if ($price === null) {
            return null;
        }

        return round((float) $price * 1000000 * $multiplier, 6);
    }

    private function emptyGuide(): array
    {
        return [
            'available' => false,
            'group_name' => null,
            'group_multiplier' => null,
            'models' => [],
            'cached_for_seconds' => self::CACHE_TTL_SECONDS,
            'max_models' => self::MAX_MODELS,
        ];
    }

    private function cacheKey(int $group_id): string
    {
        return 'sub2api_pricing:group:'.$group_id;
    }
}
