<?php

namespace App\Http\Controllers;

use App\Models\Node;
use App\Services\NodeConfigurationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentConfigurationController extends Controller
{
    public function show(Request $request, NodeConfigurationService $service): Response
    {
        $data = $request->validate(['revision' => ['sometimes', 'integer', 'min:0'], 'applied_revision' => ['sometimes', 'integer', 'min:0'], 'agent_version' => ['sometimes', 'string', 'max:128'], 'core_version' => ['sometimes', 'string', 'max:128']]);
        /** @var Node $node */
        $node = $request->attributes->get('node');
        if (! $node->last_seen_at || $node->last_seen_at->lt(now()->subMinute())) {
            Node::whereKey($node->id)->update(['agent_version' => $data['agent_version'] ?? $node->agent_version, 'core_version' => $data['core_version'] ?? $node->core_version, 'last_seen_at' => now(), 'applied_revision' => min((int) ($data['applied_revision'] ?? $node->applied_revision), $node->desired_revision)]);
        }
        $snapshot = $service->snapshot($node);
        if ((int) ($data['revision'] ?? 0) === $snapshot['revision']) {
            return response('', 304);
        }

        return response()->json($snapshot);
    }
}
