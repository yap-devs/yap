#!/bin/sh
[ "$YAP_ENABLED" = 1 ] || exit 0
[ -n "$APP_DIR" ] && [ -f "$APP_DIR/artisan" ] || { echo 'ERROR: APP_DIR is not configured.' >&2; exit 1; }
cd "$APP_DIR" || exit 1
exec "$PHP_BIN" artisan schedule:run --no-interaction
