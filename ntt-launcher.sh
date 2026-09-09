#!/usr/bin/env bash

set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
AUTH_FILE="${NTT_AUTH_FILE:-${SCRIPT_DIR}/auth.json}"
API_BASE="${NTT_API_BASE:-https://api.customer.jp/webarenaIndigo/v1}"
OAUTH_URL="${NTT_OAUTH_URL:-https://api.customer.jp/oauth/v1/accesstokens}"
DEFAULT_REGION_ID=1
DEFAULT_OS_ID=22
DEFAULT_PLAN_ID=1
DEFAULT_SSH_KEY_NAME="${NTT_SSH_KEY_NAME:-}"
DEFAULT_FIREWALL_PORTS="${NTT_FIREWALL_PORTS:-1-65535}"
WAIT_TIMEOUT="${NTT_WAIT_TIMEOUT:-300}"
POLL_INTERVAL="${NTT_POLL_INTERVAL:-5}"
JSON_OUTPUT=false
ACCESS_TOKEN=""
CREATED_INSTANCE_ID=""
CREATED_FIREWALL_ID=""
LAUNCH_COMMITTED=false

die() { echo "[ERROR] $*" >&2; exit 1; }
info() { [ "$JSON_OUTPUT" == "true" ] || echo "[INFO] $*" >&2; }

check_deps() {
    local command
    for command in curl jq; do
        command -v "$command" >/dev/null 2>&1 || die "Missing dependency: ${command}"
    done
    [ -r "$AUTH_FILE" ] || die "Credential file is not readable: ${AUTH_FILE}"
    jq -e '.key | strings | length > 0' "$AUTH_FILE" >/dev/null || die "Missing key in ${AUTH_FILE}"
    jq -e '.secret | strings | length > 0' "$AUTH_FILE" >/dev/null || die "Missing secret in ${AUTH_FILE}"
}

authenticate() {
    local payload response
    payload=$(jq -n --arg client_id "$(jq -r '.key' "$AUTH_FILE")" \
        --arg client_secret "$(jq -r '.secret' "$AUTH_FILE")" \
        '{grantType: "client_credentials", clientId: $client_id, clientSecret: $client_secret, code: ""}')
    response=$(curl -fsS --connect-timeout 15 --max-time 45 \
        -H 'Content-Type: application/json' -d "$payload" "$OAUTH_URL") \
        || die "Indigo authentication failed"
    ACCESS_TOKEN=$(jq -r '.accessToken // empty' <<< "$response")
    [ -n "$ACCESS_TOKEN" ] || die "Indigo authentication response did not contain an access token"
}

api_request() {
    local method=$1 endpoint=$2 payload=${3:-} attempt response_file status response
    response_file=$(mktemp)
    for attempt in 1 2 3 4 5; do
        local args=(-sS --connect-timeout 15 --max-time 60 -o "$response_file" -w '%{http_code}'
            -X "$method" -H "Authorization: Bearer ${ACCESS_TOKEN}" -H 'Content-Type: application/json')
        [ -n "$payload" ] && args+=(-d "$payload")
        status=$(curl "${args[@]}" "${API_BASE}${endpoint}") || status=000
        response=$(<"$response_file")
        if [[ "$status" =~ ^2 ]]; then
            rm -f "$response_file"
            printf '%s' "$response"
            return 0
        fi
        if [ "$status" != "429" ] && [ "$status" != "000" ] && [ "$status" -lt 500 ]; then
            rm -f "$response_file"
            echo "$response" >&2
            return 1
        fi
        sleep $((attempt * 2))
    done
    rm -f "$response_file"
    echo "$response" >&2
    return 1
}

instance_list() { api_request GET '/vm/getinstancelist'; }

find_instance() {
    local identifier=$1
    instance_list | jq --arg identifier "$identifier" \
        'map(select((.id | tostring) == $identifier or .instance_name == $identifier)) | first // empty'
}

wait_for_ip() {
    local instance_id=$1 elapsed=0 instance ip
    while [ "$elapsed" -lt "$WAIT_TIMEOUT" ]; do
        instance=$(find_instance "$instance_id")
        ip=$(jq -r '.ip // empty' <<< "$instance")
        if [ -n "$ip" ]; then
            printf '%s' "$instance"
            return 0
        fi
        sleep "$POLL_INTERVAL"
        elapsed=$((elapsed + POLL_INTERVAL))
    done
    return 1
}

resolve_ssh_key_id() {
    local key_name=$1 keys
    keys=$(api_request GET '/vm/sshkey')
    jq -r --arg name "$key_name" \
        '(.sshkeys // .ssh_keys // []) | map(select(.name == $name and (.status | ascii_upcase) == "ACTIVE")) | first.id // empty' \
        <<< "$keys"
}

update_status() {
    local instance_id=$1 status=$2 payload response error_code
    payload=$(jq -n --argjson id "$instance_id" --arg status "$status" '{instanceId: $id, status: $status}')
    if ! response=$(api_request POST '/vm/instance/statusupdate' "$payload" 2>&1); then
        error_code=$(jq -r '.errorCode // .sucessCode // empty' <<< "$response" 2>/dev/null || true)
        case "${status}:${error_code}" in
            start:I10016|stop:I10017|destroy:I10018) return 0 ;;
        esac
        echo "$response" >&2
        return 1
    fi
    error_code=$(jq -r '.errorCode // .sucessCode // empty' <<< "$response")
    case "${status}:${error_code}" in
        start:I10016|stop:I10017|stop:I10025|destroy:I10018) return 0 ;;
    esac
    [ "$(jq -r '.success // false' <<< "$response")" == "true" ] || return 1
    printf '%s' "$response"
}

configure_firewall() {
    local instance_id=$1 instance_name=$2 ports=$3 firewall_name payload response firewall_id attempt assigned=false
    firewall_name="relay-${instance_name}-$(date +%s)"
    payload=$(jq -n --arg name "$firewall_name" --arg ports "$ports" --arg id "$instance_id" '{
        name: $name,
        inbound: [
            {type: "Custom", protocol: "TCP", port: $ports, source: "0.0.0.0"},
            {type: "Custom", protocol: "UDP", port: $ports, source: "0.0.0.0"}
        ],
        outbound: [
            {type: "Custom", protocol: "TCP", port: $ports, source: "0.0.0.0"},
            {type: "Custom", protocol: "UDP", port: $ports, source: "0.0.0.0"}
        ],
        instances: []
    }')
    response=$(api_request POST '/nw/createfirewall' "$payload") || return 1
    firewall_id=$(jq -r '.firewallId // empty' <<< "$response")
    [ -n "$firewall_id" ] || return 1
    payload=$(jq -n --arg template_id "$firewall_id" --arg instance_id "$instance_id" \
        '{templateid: $template_id, instanceid: $instance_id}')
    for attempt in {1..36}; do
        if api_request POST '/nw/assign' "$payload" >/dev/null 2>&1; then
            assigned=true
            break
        fi
        sleep "$POLL_INTERVAL"
    done
    if [ "$assigned" != "true" ]; then
        api_request DELETE "/nw/deletefirewall/${firewall_id}" >/dev/null 2>&1 || true
        return 1
    fi
    printf '%s' "$firewall_id"
}

cleanup_failed_launch() {
    [ "$LAUNCH_COMMITTED" == "false" ] || return 0
    [ -n "$CREATED_INSTANCE_ID" ] || return 0
    info "Cleaning up failed launch ${CREATED_INSTANCE_ID}"
    update_status "$CREATED_INSTANCE_ID" stop >/dev/null 2>&1 || true
    local attempt
    for attempt in {1..12}; do
        sleep "$POLL_INTERVAL"
        update_status "$CREATED_INSTANCE_ID" destroy >/dev/null 2>&1 && break
    done
    [ -z "$CREATED_FIREWALL_ID" ] \
        || api_request DELETE "/nw/deletefirewall/${CREATED_FIREWALL_ID}" >/dev/null 2>&1 \
        || true
}

start_instance() {
    local instance_id=$1 attempt
    for attempt in {1..5}; do
        if update_status "$instance_id" start >/dev/null 2>&1; then
            return 0
        fi
        sleep $((attempt * POLL_INTERVAL))
    done
    return 1
}

cmd_launch() {
    local name ssh_key_name ports
    name="indigo-relay-$(date +%m%d%H%M%S)"
    ssh_key_name=$DEFAULT_SSH_KEY_NAME
    ports=$DEFAULT_FIREWALL_PORTS
    while [ "$#" -gt 0 ]; do
        case $1 in
            --name|-n) name=$2; shift 2 ;;
            --ssh-key) ssh_key_name=$2; shift 2 ;;
            --ports) ports=$2; shift 2 ;;
            *) die "Unknown launch option: $1" ;;
        esac
    done
    [ -n "$ssh_key_name" ] || die "SSH key name is required (--ssh-key or NTT_SSH_KEY_NAME)"
    local ssh_key_id payload response instance_id instance firewall_id
    ssh_key_id=$(resolve_ssh_key_id "$ssh_key_name")
    [ -n "$ssh_key_id" ] || die "Active SSH key not found: ${ssh_key_name}"
    payload=$(jq -n --argjson ssh_key_id "$ssh_key_id" --arg name "$name" \
        --argjson region "$DEFAULT_REGION_ID" --argjson os "$DEFAULT_OS_ID" --argjson plan "$DEFAULT_PLAN_ID" \
        '{sshKeyId: $ssh_key_id, regionId: $region, osId: $os, instancePlan: $plan, instanceName: $name}')
    info "Creating ${name}"
    response=$(api_request POST '/vm/createinstance' "$payload") || die "Failed to create Indigo instance"
    instance_id=$(jq -r '.vms.id // empty' <<< "$response")
    [ -n "$instance_id" ] || die "Create response did not contain an instance ID"
    CREATED_INSTANCE_ID=$instance_id
    LAUNCH_COMMITTED=false
    trap cleanup_failed_launch EXIT
    if ! instance=$(wait_for_ip "$instance_id"); then
        die "Instance ${instance_id} did not receive an IP within ${WAIT_TIMEOUT}s"
    fi
    info "Opening TCP/UDP ${ports} on ${instance_id}"
    firewall_id=$(configure_firewall "$instance_id" "$name" "$ports") || die "Failed to create or assign firewall"
    CREATED_FIREWALL_ID=$firewall_id
    info "Waiting for Indigo provisioning to settle"
    sleep 30
    info "Starting ${instance_id}"
    start_instance "$instance_id" || die "Failed to start instance ${instance_id}"
    LAUNCH_COMMITTED=true
    trap - EXIT
    jq -n --argjson instance "$instance" --arg firewall_id "$firewall_id" \
        '$instance + {firewall_id: ($firewall_id | tonumber)}'
}

cmd_get() {
    local instance
    instance=$(find_instance "${1:?Instance ID or name is required}")
    [ -n "$instance" ] || die "Instance not found: $1"
    printf '%s\n' "$instance"
}

cmd_list() { instance_list; }

cmd_status() {
    local status=$1 identifier=$2 instance instance_id
    instance=$(find_instance "$identifier")
    [ -n "$instance" ] || die "Instance not found: ${identifier}"
    instance_id=$(jq -r '.id' <<< "$instance")
    update_status "$instance_id" "$status" | jq .
}

cmd_delete() {
    local identifier=$1 instance instance_id instance_name attempt destroyed=false firewalls firewall_id
    instance=$(find_instance "$identifier")
    [ -n "$instance" ] || die "Instance not found: ${identifier}"
    instance_id=$(jq -r '.id' <<< "$instance")
    instance_name=$(jq -r '.instance_name' <<< "$instance")
    update_status "$instance_id" stop >/dev/null || true
    for attempt in {1..36}; do
        sleep "$POLL_INTERVAL"
        if update_status "$instance_id" destroy >/dev/null 2>&1; then
            destroyed=true
            break
        fi
    done
    [ "$destroyed" == "true" ] || die "Instance ${instance_id} did not stop in time for deletion"

    firewalls=$(api_request GET '/nw/getfirewalllist') || firewalls='[]'
    while IFS= read -r firewall_id; do
        [ -z "$firewall_id" ] && continue
        api_request DELETE "/nw/deletefirewall/${firewall_id}" >/dev/null || true
    done < <(jq -r --arg prefix "relay-${instance_name}-" \
        '.[] | select(.name | startswith($prefix)) | .id' <<< "$firewalls")
    jq -n --argjson id "$instance_id" '{success: true, instanceId: $id, status: "destroyed"}'
}

show_help() {
    cat <<'EOF'
WebARENA Indigo launcher

Usage:
  ntt-launcher.sh [--json] launch [--name NAME] [--ssh-key NAME] [--ports RANGE]
  ntt-launcher.sh [--json] list
  ntt-launcher.sh [--json] get ID_OR_NAME
  ntt-launcher.sh [--json] start|stop|reset ID_OR_NAME
  ntt-launcher.sh [--json] delete ID_OR_NAME

Defaults: Tokyo, Debian 12, 1 vCPU, 1 GB RAM, 20 GB SSD, 100 Mbps,
and TCP/UDP ports 1-65535. Set the SSH key with --ssh-key or NTT_SSH_KEY_NAME.

Credentials are read from auth.json beside this script or NTT_AUTH_FILE.
EOF
}

main() {
    [ "${1:-}" == "--json" ] && { JSON_OUTPUT=true; shift; }
    case ${1:-help} in
        -h|--help|help) show_help; exit 0 ;;
    esac
    check_deps
    authenticate
    local command=${1:-help}
    shift || true
    case $command in
        launch|create|new) cmd_launch "$@" ;;
        list|ls) cmd_list ;;
        get|show) cmd_get "$@" ;;
        start|stop|reset) cmd_status "$command" "$@" ;;
        delete|destroy|rm) cmd_delete "$@" ;;
        *) show_help; exit 1 ;;
    esac
}

main "$@"
