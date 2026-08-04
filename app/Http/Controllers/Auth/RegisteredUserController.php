<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\SafeEmail;
use App\Services\Affiliate\AffiliateService;
use App\Services\TurnstileService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'turnstileSiteKey' => config('yap.turnstile.site_key'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(
        Request $request,
        AffiliateService $affiliate_service,
        TurnstileService $turnstile_service,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'max:255',
                Rule::email()->rfcCompliant(strict: true),
                new SafeEmail,
                Rule::unique(User::class),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cf-turnstile-response' => ['required', 'string', 'max:2048'],
        ]);

        if (! $turnstile_service->verify($validated['cf-turnstile-response'], $request->ip())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'The security verification failed. Please try again.',
            ]);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'uuid' => (string) Str::uuid(),
        ]);

        event(new Registered($user));

        try {
            $affiliate_service->createReferralFromCookie($request, $user);
        } catch (Throwable $e) {
            logger()->warning('Affiliate referral creation failed during registration: '.$e->getMessage());
        }

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
