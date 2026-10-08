<?php

use App\Models\Node;
use Illuminate\Support\Str;

test('authenticated nodes behind one ip have independent rate limits', function () {
    config(['node_agent.enabled' => true, 'node_agent.snapshot_store' => 'array']);
    $first_token = Str::random(64);
    $second_token = Str::random(64);
    Node::factory()->create(['agent_token_hash' => hash('sha256', $first_token)]);
    Node::factory()->create(['agent_token_hash' => hash('sha256', $second_token)]);
    $this->freezeTime();

    for ($request = 0; $request < 120; $request++) {
        $this->withToken($first_token)->getJson('/api/agent/v1/config')->assertOk();
    }
    $this->withToken($first_token)->getJson('/api/agent/v1/config')->assertTooManyRequests();
    $this->withToken($second_token)->getJson('/api/agent/v1/config')->assertOk();
});
