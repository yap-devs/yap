<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(array_keys(config('yap.locales')))],
        ]);

        $locale = $validated['locale'];

        session(['locale' => $locale]);

        return back()->withCookie(Cookie::forever('locale', $locale));
    }
}
