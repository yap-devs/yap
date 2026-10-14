<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;

class QueueHealthCommand extends Command
{
    protected $signature = 'app:queue-health {--output= : Write the snapshot to a private JSON file}';

    protected $description = 'Inspect database queue backlog and validate the rental worker reservation window';

    public function handle(Filesystem $files): int
    {
        $retry_after = (int) config('queue.connections.database.retry_after');
        if ($retry_after <= 60) {
            $this->error('Database queue retry_after must exceed 60 seconds for the 55-second rental watchdog.');

            return self::FAILURE;
        }

        $now = now()->timestamp;
        $queues = DB::connection(config('queue.connections.database.connection'))
            ->table(config('queue.connections.database.table'))
            ->select('queue')
            ->selectRaw('COUNT(*) AS total, MIN(created_at) AS oldest_created')
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN 1 ELSE 0 END) AS ready', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at IS NULL AND available_at > ? THEN 1 ELSE 0 END) AS delayed_jobs', [$now])
            ->selectRaw('SUM(CASE WHEN reserved_at > ? THEN 1 ELSE 0 END) AS reserved', [$now - $retry_after])
            ->selectRaw('SUM(CASE WHEN reserved_at <= ? THEN 1 ELSE 0 END) AS expired', [$now - $retry_after])
            ->selectRaw('MIN(CASE WHEN reserved_at IS NULL AND available_at <= ? THEN available_at END) AS oldest_ready', [$now])
            ->groupBy('queue')->orderBy('queue')->get()
            ->mapWithKeys(fn (object $queue): array => [$queue->queue => [
                'total' => (int) $queue->total,
                'ready' => (int) $queue->ready,
                'delayed' => (int) $queue->delayed_jobs,
                'reserved' => (int) $queue->reserved,
                'expired' => (int) $queue->expired,
                'oldest_created_age_seconds' => max(0, $now - $queue->oldest_created),
                'oldest_ready_age_seconds' => $queue->oldest_ready === null ? null : max(0, $now - $queue->oldest_ready),
            ]])->all();

        $failed_jobs = in_array(config('queue.failed.driver'), ['database', 'database-uuids'], true)
            ? DB::connection(config('queue.failed.database'))->table(config('queue.failed.table'))->where('connection', 'database')->count()
            : null;
        $snapshot = json_encode([
            'observed_at' => $now,
            'worker_queue' => config('queue.connections.database.queue'),
            'retry_after_seconds' => $retry_after,
            'queues' => $queues,
            'failed_jobs' => $failed_jobs,
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n";

        $output = $this->option('output');
        if (is_string($output) && $output !== '') {
            $files->replace($output, $snapshot, 0600);
        } else {
            $this->output->write($snapshot);
        }

        return self::SUCCESS;
    }
}
