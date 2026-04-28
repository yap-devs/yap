<?php

namespace App\Http\Controllers;

use App\Jobs\RefreshSub2apiPricing;
use App\Models\User;
use App\Services\Sub2apiKeyService;
use App\Services\Sub2apiPricingService;
use DomainException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class AiController extends Controller
{
    public function __construct(
        private readonly Sub2apiKeyService $sub2api_key_service,
        private readonly Sub2apiPricingService $sub2api_pricing_service,
    ) {}

    public function index(Request $request)
    {
        abort_if(! config('services.sub2api.enabled'), 404);

        /** @var User $user */
        $user = $request->user();

        $aiKey = $this->sub2api_key_service->getDisplayKey($user);
        $createThreshold = config('services.sub2api.min_balance_to_create_key');
        $keepActiveThreshold = config('services.sub2api.min_balance_to_keep_active');
        $baseUrl = $aiKey ? rtrim((string) config('services.sub2api.base_url'), '/') : null;
        $pricingGuide = $aiKey ? $this->sub2api_pricing_service->getPricingGuide() : null;

        if ($aiKey && ! ($pricingGuide['available'] ?? false) && $this->sub2api_pricing_service->reserveRefreshIfMissing()) {
            RefreshSub2apiPricing::dispatch();
        }

        return Inertia::render('Ai/Index', compact(
            'aiKey',
            'baseUrl',
            'createThreshold',
            'keepActiveThreshold',
            'pricingGuide'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! config('services.sub2api.enabled')) {
            throw new HttpResponseException(redirect()->route('dashboard'));
        }

        /** @var User $user */
        $user = $request->user();

        try {
            $this->sub2api_key_service->createForUser($user);
        } catch (DomainException $e) {
            return redirect()->route('ai.index')->with('error', $e->getMessage());
        } catch (RuntimeException) {
            return redirect()->route('ai.index')->with('error', 'Failed to create AI key. Please try again later.');
        }

        return redirect()->route('ai.index')->with('success', 'AI key created successfully.');
    }
}
