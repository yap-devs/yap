#!/bin/sh
run_job() (
    job_name=$1
    job_period=$2
    job_script=$3
    # Longer tasks only run in the slot that has not slept for four minutes.
    if [ "$job_period" -gt 60 ] && [ "${CRON_SLOT:-0}" != 0 ]; then
        exit 0
    fi
    if [ "$YAP_ENABLED" != 1 ]; then
        exit 0
    fi
    mkdir -p "$CRON_STATE" || exit 1
    command -v flock >/dev/null 2>&1 || { echo 'ERROR: flock is unavailable.' >&2; exit 1; }
    exec 9>"$CRON_STATE/$job_name.lock" || exit 1
    if [ "$job_name" = scheduler ]; then
        flock 9 || exit 1
    else
        flock -n 9 || exit 0
    fi
    job_bucket=$(($(date +%s) / job_period))
    last_bucket=''
    if [ -f "$CRON_STATE/$job_name.last" ]; then
        read -r last_bucket < "$CRON_STATE/$job_name.last"
    fi
    [ "$last_bucket" != "$job_bucket" ] || exit 0
    # Record an attempt before running; business jobs must handle retries safely.
    printf '%s\n' "$job_bucket" > "$CRON_STATE/$job_name.last" || exit 1
    if [ "$job_name" = scheduler ]; then
        # Laravel guards individual tasks; later minute ticks must still run.
        flock -u 9 || exit 1
        exec 9>&-
    fi
    sh "$job_script"
    job_status=$?
    if [ "$job_status" -ne 0 ]; then
        printf 'ERROR: %s exited with status %s\n' "$job_name" "$job_status" >&2
    fi
    exit "$job_status"
)
