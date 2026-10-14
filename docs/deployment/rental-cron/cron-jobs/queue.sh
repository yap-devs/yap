#!/bin/sh
[ "$YAP_ENABLED" = 1 ] || exit 0
[ -n "$APP_DIR" ] && [ -f "$APP_DIR/artisan" ] || exit 1
command -v timeout >/dev/null 2>&1 || { echo 'ERROR: GNU timeout is required for the queue worker.' >&2; exit 1; }
cd "$APP_DIR" || exit 1
# Bound the whole process group, including preflight and jobs without pcntl.
exec timeout --kill-after=5 50 sh -c '
    "$PHP_BIN" artisan app:queue-health --output="$CRON_STATE/queue-health.json" --no-interaction || exit 1
    exec "$PHP_BIN" artisan queue:work database --stop-when-empty --max-time=20 --max-jobs=100 --tries=3 --backoff=60 --timeout=30 --no-interaction
'
