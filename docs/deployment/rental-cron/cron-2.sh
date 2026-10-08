#!/bin/sh
CRON_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd) || exit 1
CRON_SLOT=2
export CRON_SLOT
sleep 120 || exit 1
exec sh "$CRON_ROOT/cron.sh"
