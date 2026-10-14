#!/bin/sh
umask 077
PHP_BIN='/usr/local/bin/php8.3'
APP_DIR='/absolute/private/path/to/yap'
YAP_ENABLED=0
# Optional unrelated task; runs independently even when YAP is disabled.
# LEGACY_CRON_SCRIPT='/absolute/private/path/to/legacy.sh'
CRON_STATE="$CRON_ROOT/state"
export PHP_BIN APP_DIR YAP_ENABLED CRON_STATE
