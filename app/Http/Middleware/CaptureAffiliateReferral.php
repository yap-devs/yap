<?php

namespace App\Http\Middleware;

use App\Services\Affiliate\AffiliateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureAffiliateReferral
{
    public function handle(Request $request, Closure $next): Response
    {
        app(AffiliateService::class)->captureReferral($request);

        return $next($request);
    }
}
