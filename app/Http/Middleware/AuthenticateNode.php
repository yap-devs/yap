<?php

namespace App\Http\Middleware;

use App\Models\Node;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateNode
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('node_agent.enabled'), 404);
        $token = $request->bearerToken();
        abort_unless(is_string($token) && strlen($token) >= 32 && strlen($token) <= 256, 401);

        $node = Node::where('agent_token_hash', hash('sha256', $token))->where('enabled', true)->first();
        abort_unless($node, 401);
        $request->attributes->set('node', $node);

        return app(ThrottleRequests::class)->handle($request, $next, 'agent-node');
    }
}
