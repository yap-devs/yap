#!/bin/sh
[ "$YAP_ENABLED" = 1 ] || exit 0
[ -n "$APP_DIR" ] && [ -f "$APP_DIR/artisan" ] || exit 1
cd "$APP_DIR" || exit 1
exec "$PHP_BIN" artisan queue:work database --queue=default --stop-when-empty --max-time=40 --max-jobs=100 --tries=3 --backoff=60 --timeout=30 --no-interaction
