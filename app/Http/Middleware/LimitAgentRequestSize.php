<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitAgentRequestSize
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/agent/*')) {
            $limit = (int) config('node_agent.max_request_bytes');
            abort_if((int) $request->headers->get('Content-Length', 0) > $limit, 413);
            $stream = $request->getContent(true);
            $sample = stream_get_contents($stream, $limit + 1);
            abort_if($sample === false, 400);
            abort_if(strlen($sample) > $limit, 413);
            rewind($stream);
        }

        return $next($request);
    }
}
