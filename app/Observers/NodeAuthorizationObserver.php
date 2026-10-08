<?php

namespace App\Observers;

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\NodeAuthorizationService;
use Illuminate\Database\Eloquent\Model;

class NodeAuthorizationObserver
{
    public function updated(Model $model): void
    {
        if (! config('node_agent.enabled')) {
            return;
        }
        if ($model instanceof NodeRoute) {
            if (! $model->wasChanged(['enabled', 'for_low_priority', 'deleted_at'])) {
                return;
            }
            app(NodeAuthorizationService::class)->notify(array_values(array_unique(array_filter([$model->node_id, $model->getOriginal('node_id')]))));

            return;
        }
        if ($model instanceof Node) {
            if ($model->wasChanged(['core_config', 'enabled'])) {
                app(NodeAuthorizationService::class)->notify([$model->id]);
            }

            return;
        }
        if ($model instanceof User) {
            $changed = $model->wasChanged(['uuid', 'github_created_at', 'deleted_at']);
            if ($model->wasChanged('balance')) {
                $before = clone $model;
                $before->balance = $model->getRawOriginal('balance');
                $changed = $changed || $before->is_valid !== $model->is_valid || $before->is_low_priority !== $model->is_low_priority;
            }
            if (! $changed) {
                return;
            }
        }
        if ($model instanceof UserPackage && ! $model->wasChanged(['status', 'started_at', 'ended_at', 'user_id', 'deleted_at'])) {
            return;
        }
        app(NodeAuthorizationService::class)->notify();
    }

    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        if (config('node_agent.enabled')) {
            app(NodeAuthorizationService::class)->notify($model instanceof NodeRoute ? [$model->node_id] : null);
        }
    }
}
