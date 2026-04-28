<?php

use App\Jobs\RefreshSub2apiPricing;
use App\Models\User;
use App\Services\Sub2apiPricingService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

test('it fetches and caches ai model pricing with the group multiplier', function () {
    config()->set('services.sub2api.enabled', true);
    config()->set('services.sub2api.base_url', 'https://ai.test');
    config()->set('services.sub2api.admin_email', 'admin@example.com');
    config()->set('services.sub2api.admin_password', 'password');
    config()->set('services.sub2api.default_group_id', 2);

    Cache::forget('sub2api_access_token');
    Cache::forget('sub2api_pricing:group:2');

    Http::fake([
        'https://ai.test/api/v1/auth/login' => Http::response([
            'code' => 0,
            'data' => [
                'access_token' => 'token',
                'expires_in' => 3600,
            ],
        ]),
        'https://ai.test/api/v1/admin/groups/2' => Http::response([
            'code' => 0,
            'data' => [
                'id' => 2,
                'name' => 'OpenAI',
                'rate_multiplier' => 0.5,
            ],
        ]),
        'https://ai.test/api/v1/admin/accounts*' => Http::response([
            'code' => 0,
            'data' => [
                'items' => [
                    [
                        'credentials' => [
                            'model_mapping' => [
                                'gpt-5.4' => 'gpt-5.4',
                            ],
                        ],
                    ],
                ],
            ],
        ]),
        'https://ai.test/api/v1/admin/channels/model-pricing*' => Http::response([
            'code' => 0,
            'data' => [
                'found' => true,
                'input_price' => 0.0000025,
                'output_price' => 0.000015,
                'cache_read_price' => 0.00000025,
                'cache_write_price' => 0,
            ],
        ]),
    ]);

    $service = app(Sub2apiPricingService::class);

    $empty_guide = $service->getPricingGuide();
    $guide = $service->refreshPricingGuideIfMissing();
    $cached_guide = $service->getPricingGuide();
    $warm_guide = $service->refreshPricingGuideIfMissing();

    expect($empty_guide['available'])->toBeFalse()
        ->and($guide['available'])->toBeTrue()
        ->and($guide['cached_for_seconds'])->toBe(604800)
        ->and($guide['group_name'])->toBe('OpenAI')
        ->and($guide['group_multiplier'])->toBe(0.5)
        ->and($guide['models'])->toHaveCount(1)
        ->and($guide['models'][0]['model'])->toBe('gpt-5.4')
        ->and($guide['models'][0]['input_per_million'])->toBe(1.25)
        ->and($guide['models'][0]['output_per_million'])->toBe(7.5)
        ->and($guide['models'][0]['cache_read_per_million'])->toBe(0.125)
        ->and($cached_guide)->toBe($guide)
        ->and($warm_guide)->toBe($guide);

    Http::assertSentCount(4);
});

test('it reserves one async pricing refresh while cache is missing', function () {
    config()->set('services.sub2api.enabled', true);
    config()->set('services.sub2api.default_group_id', 2);

    Cache::forget('sub2api_pricing:group:2');
    Cache::forget('sub2api_pricing_refreshing:group:2');

    $service = app(Sub2apiPricingService::class);

    expect($service->reserveRefreshIfMissing())->toBeTrue()
        ->and($service->reserveRefreshIfMissing())->toBeFalse();

    $service->releaseRefreshReservation();

    expect($service->reserveRefreshIfMissing())->toBeTrue();
});

test('ai page queues pricing refresh when cache is missing', function () {
    config()->set('services.sub2api.enabled', true);
    config()->set('services.sub2api.base_url', 'https://ai.test');
    config()->set('services.sub2api.default_group_id', 2);
    config()->set('services.sub2api.key_prefix', 'sk-yap-');

    Cache::forget('sub2api_pricing:group:2');
    Cache::forget('sub2api_pricing_refreshing:group:2');
    Bus::fake();

    $user = User::factory()->create([
        'sub2api_key_id' => 123,
        'sub2api_key_status' => 'active',
    ]);

    $this->actingAs($user)->get(route('ai.index'))->assertOk();

    Bus::assertDispatched(RefreshSub2apiPricing::class);
});
