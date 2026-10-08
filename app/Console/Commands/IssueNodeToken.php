<?php

namespace App\Console\Commands;

use App\Models\Node;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class IssueNodeToken extends Command
{
    protected $signature = 'nodes:issue-token {node : Existing node ID}';

    protected $description = 'Rotate and print a node token once for provisioning';

    public function handle(): int
    {
        $node = Node::findOrFail($this->argument('node'));
        $token = Str::random(64);
        $node->update(['agent_token_hash' => hash('sha256', $token)]);
        $this->line($token);

        return self::SUCCESS;
    }
}
