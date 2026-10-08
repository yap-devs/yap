<?php

return [
    'content_store' => env('SUBSCRIPTION_CONTENT_STORE', 'file'),
    'lock_store' => env('SUBSCRIPTION_LOCK_STORE', 'database'),
    'ttl_seconds' => (int) env('SUBSCRIPTION_TTL_SECONDS', 43200),
    'lock_wait_seconds' => (int) env('SUBSCRIPTION_LOCK_WAIT_SECONDS', 10),
];
