#!/bin/sh
CRON_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd) || exit 1
CRON_SLOT=1
export CRON_SLOT
sleep 60 || exit 1
exec sh "$CRON_ROOT/cron.sh"
