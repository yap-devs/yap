#!/bin/sh
CRON_ROOT=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd) || exit 1
export CRON_ROOT
[ -f "$CRON_ROOT/config.sh" ] || { echo 'ERROR: Create private config.sh from config.example.sh.' >&2; exit 1; }
. "$CRON_ROOT/config.sh"
. "$CRON_ROOT/cron-jobs/runner.sh"
# Start independent tasks before waiting for any of them.
run_job scheduler 60 "$CRON_ROOT/cron-jobs/scheduler.sh" &
scheduler_pid=$!
run_job queue 60 "$CRON_ROOT/cron-jobs/queue.sh" &
queue_pid=$!
legacy_pid=''
if [ -n "${LEGACY_CRON_SCRIPT:-}" ]; then
    run_job legacy 60 "$LEGACY_CRON_SCRIPT" &
    legacy_pid=$!
fi
cron_status=0
wait "$scheduler_pid" || cron_status=1
wait "$queue_pid" || cron_status=1
if [ -n "$legacy_pid" ]; then
    wait "$legacy_pid" || cron_status=1
fi
exit "$cron_status"
