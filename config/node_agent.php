<?php

return [
    'snapshot_store' => env('NODE_SNAPSHOT_STORE', 'file'),
    'enabled' => env('NODE_AGENT_ENABLED', false),
    'poll_interval_seconds' => (int) env('NODE_POLL_INTERVAL_SECONDS', 5),
    'traffic_interval_seconds' => (int) env('NODE_TRAFFIC_INTERVAL_SECONDS', 60),
    'max_batch_records' => 1000,
    'max_request_bytes' => 1048576,
    'record_retention_days' => (int) env('NODE_TRAFFIC_RETENTION_DAYS', 7),
];
