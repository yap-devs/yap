<?php

namespace App\Services;

use App\Jobs\RefreshNodeAuthorization;
use App\Models\Node;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class NodeAuthorizationService
{
    /** @param array<int, int>|null $node_ids */
    public function notify(?array $node_ids = null): void
    {
        $connection = DB::connection();
        $queue_connection = config('queue.connections.database.connection');
        throw_if($queue_connection && $queue_connection !== $connection->getName(), LogicException::class, 'Node authorization requires the database queue on the application connection.');
        $table = config('queue.connections.database.table', 'jobs');
        $queue = new DatabaseQueue($connection, $table, config('queue.connections.database.queue', 'default'), 360, false);
        $queue->setContainer(app());
        $job = (new RefreshNodeAuthorization($node_ids))->beforeCommit();
        // Persist on the financial connection so commit and notification are atomic.
        $job_id = $queue->push($job);
        $connection->afterCommit(function () use ($connection, $table, $job, $job_id): void {
            try {
                $job->handle($this);
                $connection->table($table)->where('id', $job_id)->delete();
            } catch (Throwable $exception) {
                // A committed payment must not fail because its refresh needs a retry.
                try {
                    logger()->warning('Node authorization refresh deferred to queue.', ['exception' => $exception::class]);
                } catch (Throwable) {
                }
            }
        });
    }

    /** @param array<int, int>|null $node_ids */
    public function refresh(?array $node_ids = null): void
    {
        $query = Node::query();
        if ($node_ids === null) {
            $query->where('enabled', true);
        } else {
            $query->whereKey($node_ids);
        }
        $query->increment('desired_revision');
    }
}
