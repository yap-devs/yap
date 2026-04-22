<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Sub2apiKeyService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class AiController extends Controller
{
    public function __construct(private readonly Sub2apiKeyService $sub2api_key_service) {}

    public function index(Request $request)
    {
        abort_if(! config('services.sub2api.enabled'), 404);

        /** @var User $user */
        $user = $request->user();

        $aiKey = $this->sub2api_key_service->getDisplayKey($user);
        $createThreshold = config('services.sub2api.min_balance_to_create_key');
        $keepActiveThreshold = config('services.sub2api.min_balance_to_keep_active');
        $baseUrl = rtrim((string) config('services.sub2api.base_url'), '/');

        return Inertia::render('Ai/Index', compact(
            'aiKey',
            'baseUrl',
            'createThreshold',
            'keepActiveThreshold'
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
        } catch (ValidationException $e) {
            throw $e;
        } catch (RuntimeException) {
            return redirect()->route('ai.index')->with('error', 'Failed to create AI key. Please try again later.');
        }

        return redirect()->route('ai.index')->with('success', 'AI key created successfully.');
    }
}
