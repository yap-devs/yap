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
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $data = $request->getContent();
        $key = config('yap.github.webhook_secret');

        if ($request->header('X-Hub-Signature') !== "sha1=" . hash_hmac('sha1', $data, $key)) {
            return response()->json(['message' => 'Invalid sha1 signature'], 403);
        }

        if ($request->header('X-Hub-Signature-256') !== "sha256=" . hash_hmac('sha256', $data, $key)) {
            return response()->json(['message' => 'Invalid sha256 signature'], 403);
        }

        return $next($request);
    }
}
