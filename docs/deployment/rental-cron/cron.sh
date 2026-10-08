#!/bin/sh
CRON_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd) || exit 1
export CRON_ROOT
[ -f "$CRON_ROOT/config.sh" ] || { echo 'ERROR: Create private config.sh from config.example.sh.' >&2; exit 1; }
. "$CRON_ROOT/config.sh"
. "$CRON_ROOT/cron-jobs/runner.sh"
cron_status=0
run_job scheduler 60 "$CRON_ROOT/cron-jobs/scheduler.sh" || cron_status=1
run_job queue 60 "$CRON_ROOT/cron-jobs/queue.sh" || cron_status=1
# Add unrelated site tasks here as separate run_job calls when needed.
exit "$cron_status"
