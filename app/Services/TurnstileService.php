<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    private const SITEVERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function verify(string $token, ?string $client_ip): bool
    {
        $site_key = config('yap.turnstile.site_key');
        $secret_key = config('yap.turnstile.secret_key');
        $hostname = config('yap.turnstile.hostname');
        $action = config('yap.turnstile.action');

        if (! is_string($site_key) || $site_key === ''
            || ! is_string($secret_key) || $secret_key === ''
            || ! is_string($hostname) || $hostname === ''
            || ! is_string($action) || $action === '') {
            logger()->warning('Turnstile verification is not configured.');

            return false;
        }

        try {
            $response = Http::asForm()
                ->connectTimeout(3)
                ->timeout(5)
                ->post(self::SITEVERIFY_URL, [
                    'secret' => $secret_key,
                    'response' => $token,
                    'remoteip' => $client_ip,
                ]);
        } catch (ConnectionException) {
            logger()->warning('Turnstile verification request failed.');

            return false;
        }

        if (! $response->successful()) {
            logger()->warning('Turnstile verification returned an unsuccessful response.', [
                'status' => $response->status(),
            ]);

            return false;
        }

        $result = $response->json();

        return is_array($result)
            && ($result['success'] ?? false) === true
            && is_string($result['hostname'] ?? null)
            && hash_equals($hostname, $result['hostname'])
            && is_string($result['action'] ?? null)
            && hash_equals($action, $result['action']);
    }
}
