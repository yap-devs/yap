<?php

namespace App\Services;

use App\Models\Node;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class NodeHealthService
{
    public function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subSeconds(max(120, (int) config('node_agent.poll_interval_seconds', 5) * 6));
    }

    public function state(Node $node): string
    {
        return match (true) {
            ! $node->enabled => 'disabled',
            $node->last_seen_at === null => 'unseen',
            $node->last_seen_at->lessThanOrEqualTo($this->cutoff()) => 'offline',
            $node->applied_revision !== $node->desired_revision => 'pending',
            default => 'current',
        };
    }

    public static function labels(): array
    {
        return ['disabled' => 'Disabled', 'unseen' => 'Not reported', 'offline' => 'Heartbeat stale', 'pending' => 'Configuration pending', 'current' => 'Configuration current'];
    }

    public function filter(Builder $query, ?string $state): Builder
    {
        return match ($state) {
            'disabled' => $query->where('enabled', false),
            'unseen' => $query->where('enabled', true)->whereNull('last_seen_at'),
            'offline' => $query->where('enabled', true)->where('last_seen_at', '<=', $this->cutoff()),
            'pending' => $query->where('enabled', true)->where('last_seen_at', '>', $this->cutoff())->whereColumn('applied_revision', '!=', 'desired_revision'),
            'current' => $query->where('enabled', true)->where('last_seen_at', '>', $this->cutoff())->whereColumn('applied_revision', 'desired_revision'),
            default => $query,
        };
    }
}
