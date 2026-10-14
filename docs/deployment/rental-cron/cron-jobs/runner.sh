#!/bin/sh
record_job_status() (
    exec 8>"$CRON_STATE/$job_name.status.lock" || exit 1
    flock 8 || exit 1
    for status_file in "$CRON_STATE/$job_name.status" "$CRON_STATE/history/$job_name.$((job_bucket % 1440))"; do
        recorded_bucket=0
        if [ -f "$status_file" ]; then
            read -r recorded_bucket recorded_rest < "$status_file"
        fi
        # An older overlapping tick must not replace a newer tick's status.
        if [ "$recorded_bucket" -le "$job_bucket" ]; then
            printf '%s\t%s\t%s\t%s\n' "$job_bucket" "$job_started" "$1" "$2" > "$status_file.tmp" || exit 1
            mv -f "$status_file.tmp" "$status_file" || exit 1
        fi
    done
)

run_job() (
    job_name=$1
    job_period=$2
    job_script=$3
    # Longer tasks only run in the slot that has not slept for four minutes.
    if [ "$job_period" -gt 60 ] && [ "${CRON_SLOT:-0}" != 0 ]; then
        exit 0
    fi
    if [ "$job_name" != legacy ] && [ "$YAP_ENABLED" != 1 ]; then
        exit 0
    fi
    mkdir -p "$CRON_STATE/history" || exit 1
    command -v flock >/dev/null 2>&1 || { echo 'ERROR: flock is unavailable.' >&2; exit 1; }
    exec 9>"$CRON_STATE/$job_name.lock" || exit 1
    if [ "$job_name" = scheduler ]; then
        flock 9 || exit 1
    else
        flock -n 9 || exit 0
    fi
    job_started=$(date +%s)
    job_bucket=$((job_started / job_period))
    last_bucket=''
    if [ -f "$CRON_STATE/$job_name.last" ]; then
        read -r last_bucket < "$CRON_STATE/$job_name.last"
    fi
    [ "$last_bucket" != "$job_bucket" ] || exit 0
    # Record an attempt before running; business jobs must handle retries safely.
    printf '%s\n' "$job_bucket" > "$CRON_STATE/$job_name.last" || exit 1
    record_job_status - - || exit 1
    if [ "$job_name" = scheduler ]; then
        # Laravel guards individual tasks; later minute ticks must still run.
        flock -u 9 || exit 1
        exec 9>&-
    fi
    sh "$job_script"
    job_status=$?
    record_job_status "$(date +%s)" "$job_status" || exit 1
    if [ "$job_status" -ne 0 ]; then
        printf 'ERROR: %s exited with status %s\n' "$job_name" "$job_status" >&2
    fi
    exit "$job_status"
)
