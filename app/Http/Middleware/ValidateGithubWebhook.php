<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateGithubWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $data = $request->getContent();
        $key = config('yap.github.webhook_secret');

        if ($request->header('X-Hub-Signature') !== 'sha1='.hash_hmac('sha1', $data, $key)) {
            return response()->json(['message' => __('messages.errors.invalid_sha1_signature')], 403);
        }

        if ($request->header('X-Hub-Signature-256') !== 'sha256='.hash_hmac('sha256', $data, $key)) {
            return response()->json(['message' => __('messages.errors.invalid_sha256_signature')], 403);
        }

        return $next($request);
    }
}
