#!/usr/bin/env bash

#######################################################################
# Cloudflare DNS Sync
#
# Resolve IPs from a source or AWS Lightsail and sync them as DNS records
# on a target domain via the Cloudflare API.
#
# IP sources (first positional arg):
#   domain name    Resolve via dig (A + AAAA)
#   -              Read IPs from stdin (pipe)
#   --lightsail-ids Read and rotate AWS Lightsail IPv4 addresses by instance ID/name
#   --lightsail-tags Read and rotate AWS Lightsail IPv4 addresses by tags
#
# Dependencies: dig, curl, jq, grep (extended regex), aws (Lightsail mode)
#
# Authentication (one of the following):
#   Option 1 - API Token:      CF_Token
#   Option 2 - Global API Key: CF_Key + CF_Email
#
# Usage:
#   ./cf-dns-sync.sh <source-domain> <target-domain> [options]
#   curl ... | jq ... | ./cf-dns-sync.sh - <target-domain> [options]
#######################################################################

set -e

# Disable AWS CLI pager when Lightsail mode is used
export AWS_PAGER=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Defaults
TTL=60        # 60s = minimum allowed by Cloudflare
PROXIED=false
DRY_RUN=false
SKIP_DNS_SYNC=false
DEBUG=false
CHECK_PORT=""  # empty = skip health check
CHECK_TIMEOUT=3
SOURCE_MODE="source"
LIGHTSAIL_IDS=""
LIGHTSAIL_TAGS=""
INSTANCE_SSH_USER="${LIGHTSAIL_SSH_USER:-}"
INSTANCE_SSH_KEY="${LIGHTSAIL_SSH_KEY:-}"
PROBE_TIMEOUT=3
SSH_CONNECT_TIMEOUT=8
STATIC_IP_READY_TIMEOUT=60
RETURN_PROBE_ROUNDS=2
RETURN_PROBE_CARRIER_MIN_OK=2
RETURN_PROBE_MIN_CARRIERS=2
RETURN_PROBE_TOTAL_MIN_OK=6
ALLOW_PROBE_OUTAGE=false
LIGHTSAIL_MAX_ATTEMPTS=10
CREATED_STATIC_IPS=()
LIGHTSAIL_EXCLUDED_IPV4S=()
COMPLETED_ROTATIONS=()
FORCE_ROTATE_IP=false
LOG_TS_FORMAT='%Y-%m-%d %H:%M:%S%z'
ALLOCATED_STATIC_IP_NAME=""
ALLOCATED_STATIC_IP_ADDRESS=""
SELECTED_LIGHTSAIL_IP=""
COLLECTED_LIGHTSAIL_IPS='{"A":[],"AAAA":[]}'
LIGHTSAIL_CLEANUP_TRAP_SET=false
INSTANCE_SSH_HOST_KEY_ALIAS=""
ROTATION_RESTORE_ACTIVE=false
ROTATION_RESTORE_REGION=""
ROTATION_RESTORE_INSTANCE_NAME=""
ROTATION_RESTORE_STATIC_IP=""
ROTATION_RESTORE_CANDIDATE_STATIC_IP=""

RETURN_PROBE_TARGETS=(
    "cm|Beijing Mobile|bj-cm-v4.ip.zstaticcdn.com|80"
    "cu|Beijing Unicom|bj-cu-v4.ip.zstaticcdn.com|80"
    "ct|Beijing Telecom|bj-ct-v4.ip.zstaticcdn.com|80"
    "cm|Shanghai Mobile|sh-cm-v4.ip.zstaticcdn.com|80"
    "cu|Shanghai Unicom|sh-cu-v4.ip.zstaticcdn.com|80"
    "ct|Shanghai Telecom|sh-ct-v4.ip.zstaticcdn.com|80"
    "cm|Guangzhou Mobile|gd-guangzhou-cm-v4.ip.zstaticcdn.com|443"
    "cu|Guangzhou Unicom|gd-guangzhou-cu-v4.ip.zstaticcdn.com|443"
    "ct|Guangzhou Telecom|gd-guangzhou-ct-v4.ip.zstaticcdn.com|443"
    "cm|Nanjing Mobile|js-nanjing-cm-v4.ip.zstaticcdn.com|443"
    "cu|Nanjing Unicom|js-nanjing-cu-v4.ip.zstaticcdn.com|443"
    "ct|Nanjing Telecom|js-nanjing-ct-v4.ip.zstaticcdn.com|443"
    "cm|Chongqing Mobile|cq-cm-v4.ip.zstaticcdn.com|80"
    "cu|Chongqing Unicom|cq-cu-v4.ip.zstaticcdn.com|80"
    "ct|Chongqing Telecom|cq-ct-v4.ip.zstaticcdn.com|80"
)

log_line() {
    local color=$1
    local level=$2
    local message=$3
    local timestamp
    timestamp=$(date "+${LOG_TS_FORMAT}")

    echo -e "${color}[${timestamp}] [${level}]${NC} ${message}"
}

print_ok()   { [ "$DEBUG" == "true" ] && log_line "$GREEN" "OK" "$1"; return 0; }
print_err()  { log_line "$RED" "ERROR" "$1"; }
print_info() { [ "$DEBUG" == "true" ] && log_line "$BLUE" "INFO" "$1"; return 0; }
print_warn() { log_line "$YELLOW" "WARN" "$1"; }
print_result() { log_line "$CYAN" "RESULT" "$1"; }

CF_API="https://api.cloudflare.com/client/v4"

check_deps() {
    local required=(curl jq)
    if [ "$SOURCE_MODE" != "lightsail" ]; then
        required+=(dig)
    fi

    for cmd in "${required[@]}"; do
        if ! command -v "$cmd" &>/dev/null; then
            print_err "Missing dependency: $cmd"
            exit 1
        fi
    done

    if [ "$SOURCE_MODE" == "lightsail" ]; then
        for cmd in aws ssh base64; do
            if ! command -v "$cmd" &>/dev/null; then
                print_err "Missing dependency: $cmd"
                exit 1
            fi
        done

        if ! aws sts get-caller-identity &>/dev/null; then
            print_err "AWS credentials not configured"
            exit 1
        fi

        print_info "Auth: AWS CLI"
    fi

    if [ "$SKIP_DNS_SYNC" != "true" ]; then
        # Determine auth method (prefer Global API Key when both are set)
        if [ -n "$CF_Key" ] && [ -n "$CF_Email" ]; then
            AUTH_METHOD="key"
            print_info "Auth: Global API Key (${CF_Email})"
        elif [ -n "$CF_Token" ]; then
            AUTH_METHOD="token"
            print_info "Auth: API Token"
        else
            print_err "Cloudflare credentials not set"
            print_err "Option 1 - API Token:      export CF_Token=\"your-token\""
            print_err "Option 2 - Global API Key:  export CF_Key=\"your-key\" CF_Email=\"your-email\""
            exit 1
        fi
    fi
}

# Make an authenticated Cloudflare API request
# Usage: cf_api <method> <endpoint> [data]
cf_api() {
    local method=$1
    local endpoint=$2
    local data=$3

    local args=(
        -s -X "$method"
        -H "Content-Type: application/json"
    )

    if [ "$AUTH_METHOD" == "token" ]; then
        args+=(-H "Authorization: Bearer $CF_Token")
    else
        args+=(-H "X-Auth-Key: $CF_Key" -H "X-Auth-Email: $CF_Email")
    fi

    if [ -n "$data" ]; then
        args+=(-d "$data")
    fi

    curl "${args[@]}" "${CF_API}${endpoint}"
}

# Get Zone ID for a domain
# Walks up the domain labels to find the matching zone
get_zone_id() {
    local domain=$1
    local lookup="$domain"

    while [[ "$lookup" == *.* ]]; do
        local result
        result=$(cf_api GET "/zones?name=${lookup}&status=active") || true

        # Validate API response
        local success
        success=$(echo "$result" | jq -r '.success // empty' 2>/dev/null) || true

        if [ "$success" != "true" ]; then
            local err_msg
            err_msg=$(echo "$result" | jq -r '.errors[]?.message // empty' 2>/dev/null) || true
            if [ -n "$err_msg" ]; then
                print_err "Cloudflare API error: ${err_msg}" >&2
            else
                print_err "Cloudflare API request failed (check network/credentials)" >&2
                print_err "Response: ${result:0:200}" >&2
            fi
            return 1
        fi

        local count
        count=$(echo "$result" | jq -r '.result | length')

        if [ "$count" -gt 0 ]; then
            echo "$result" | jq -r '.result[0].id'
            return 0
        fi

        # Strip the first label and try the parent domain
        lookup="${lookup#*.}"
    done

    print_err "No matching zone found after trying all parent domains" >&2
    return 1
}

# Extract all unique IPv4 and IPv6 addresses from arbitrary text
# Outputs JSON: {"A":["1.2.3.4",...],"AAAA":["::1",...]}
extract_ips_from_text() {
    local text="$1"
    local ipv4_list=()
    local ipv6_list=()

    # Extract IPv4
    while IFS= read -r ip; do
        [ -n "$ip" ] && ipv4_list+=("$ip")
    done < <(echo "$text" | { grep -oE '([0-9]{1,3}\.){3}[0-9]{1,3}' || true; } | sort -u)

    # Extract IPv6 (simplified: sequences of hex groups separated by colons)
    while IFS= read -r ip; do
        [ -n "$ip" ] && ipv6_list+=("$ip")
    done < <(echo "$text" | { grep -oE '([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}' || true; } | sort -u)

    # Build JSON
    local json='{"A":[],"AAAA":[]}'
    for ip in "${ipv4_list[@]}"; do
        json=$(echo "$json" | jq --arg ip "$ip" '.A += [$ip]')
    done
    for ip in "${ipv6_list[@]}"; do
        json=$(echo "$json" | jq --arg ip "$ip" '.AAAA += [$ip]')
    done

    echo "$json"
}

# Resolve all IPs from a domain via dig
resolve_ips() {
    local domain=$1
    local ipv4_list=()
    local ipv6_list=()

    # Resolve A records (IPv4)
    while IFS= read -r ip; do
        [ -n "$ip" ] && ipv4_list+=("$ip")
    done < <(dig +short A "$domain" 2>/dev/null | { grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' || true; })

    # Resolve AAAA records (IPv6)
    while IFS= read -r ip; do
        [ -n "$ip" ] && ipv6_list+=("$ip")
    done < <(dig +short AAAA "$domain" 2>/dev/null | { grep -E '^[0-9a-fA-F:]+$' || true; })

    # Output as JSON for easy parsing
    local json='{"A":[],"AAAA":[]}'
    for ip in "${ipv4_list[@]}"; do
        json=$(echo "$json" | jq --arg ip "$ip" '.A += [$ip]')
    done
    for ip in "${ipv6_list[@]}"; do
        json=$(echo "$json" | jq --arg ip "$ip" '.AAAA += [$ip]')
    done

    echo "$json"
}

# Check if a TCP port is reachable on a given IP
# Returns 0 if reachable, 1 if not
check_tcp() {
    local ip=$1
    local port=$2
    timeout "$CHECK_TIMEOUT" bash -c "echo >/dev/tcp/${ip}/${port}" 2>/dev/null
}

# Filter IPs by TCP port reachability
# Input: JSON {"A":[...],"AAAA":[...]}
# Output: same format with unreachable IPs removed
filter_by_port() {
    local resolved=$1
    local port=$2
    local filtered='{"A":[],"AAAA":[]}'

    local total=0
    local alive=0
    local dead=0

    # Check IPv4
    while IFS= read -r ip; do
        [ -z "$ip" ] && continue
        total=$((total + 1))
        if check_tcp "$ip" "$port"; then
            filtered=$(echo "$filtered" | jq --arg ip "$ip" '.A += [$ip]')
            print_ok "${ip}:${port} reachable" >&2
            alive=$((alive + 1))
        else
            print_warn "${ip}:${port} unreachable (filtered out)" >&2
            dead=$((dead + 1))
        fi
    done < <(echo "$resolved" | jq -r '.A[]' 2>/dev/null)

    # Check IPv6
    while IFS= read -r ip; do
        [ -z "$ip" ] && continue
        total=$((total + 1))
        if check_tcp "$ip" "$port"; then
            filtered=$(echo "$filtered" | jq --arg ip "$ip" '.AAAA += [$ip]')
            print_ok "[${ip}]:${port} reachable" >&2
            alive=$((alive + 1))
        else
            print_warn "[${ip}]:${port} unreachable (filtered out)" >&2
            dead=$((dead + 1))
        fi
    done < <(echo "$resolved" | jq -r '.AAAA[]' 2>/dev/null)

    print_info "Health check: ${alive}/${total} alive, ${dead} filtered out" >&2
    echo "$filtered"
}

normalize_csv() {
    local value=$1

    echo "$value" | tr ',' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | grep -v '^$'
}

json_add_ipv4_unique() {
    local json=$1
    local ip=$2

    echo "$json" | jq --arg ip "$ip" '.A += [$ip] | .A |= unique'
}

remember_lightsail_excluded_ip() {
    local ip=$1
    local excluded_ip

    [ -z "$ip" ] && return 0
    [ "$ip" == "None" ] && return 0

    for excluded_ip in "${LIGHTSAIL_EXCLUDED_IPV4S[@]}"; do
        [ "$excluded_ip" == "$ip" ] && return 0
    done

    LIGHTSAIL_EXCLUDED_IPV4S+=("$ip")
}

is_lightsail_excluded_ip() {
    local ip=$1
    local excluded_ip

    for excluded_ip in "${LIGHTSAIL_EXCLUDED_IPV4S[@]}"; do
        [ "$excluded_ip" == "$ip" ] && return 0
    done

    return 1
}

validate_positive_int() {
    local name=$1
    local value=$2

    if ! [[ "$value" =~ ^[1-9][0-9]*$ ]]; then
        print_err "${name} must be a positive integer"
        exit 1
    fi
}

validate_lightsail_probe_options() {
    [ "$SOURCE_MODE" != "lightsail" ] && return 0

    validate_positive_int "--probe-timeout" "$PROBE_TIMEOUT"
    validate_positive_int "--probe-rounds" "$RETURN_PROBE_ROUNDS"
    validate_positive_int "--probe-carrier-min-ok" "$RETURN_PROBE_CARRIER_MIN_OK"
    validate_positive_int "--probe-min-carriers" "$RETURN_PROBE_MIN_CARRIERS"
    validate_positive_int "--probe-total-min-ok" "$RETURN_PROBE_TOTAL_MIN_OK"
    validate_positive_int "--ssh-timeout" "$SSH_CONNECT_TIMEOUT"
    validate_positive_int "--static-ip-ready-timeout" "$STATIC_IP_READY_TIMEOUT"
    validate_positive_int "--max-attempts" "$LIGHTSAIL_MAX_ATTEMPTS"

    if [ "$RETURN_PROBE_CARRIER_MIN_OK" -gt 5 ]; then
        print_err "--probe-carrier-min-ok cannot exceed 5"
        exit 1
    fi

    if [ "$RETURN_PROBE_MIN_CARRIERS" -gt 3 ]; then
        print_err "--probe-min-carriers cannot exceed 3"
        exit 1
    fi

    if [ "$RETURN_PROBE_TOTAL_MIN_OK" -gt "${#RETURN_PROBE_TARGETS[@]}" ]; then
        print_err "--probe-total-min-ok cannot exceed ${#RETURN_PROBE_TARGETS[@]}"
        exit 1
    fi
}

warn_deprecated_probe_option() {
    local option=$1

    print_warn "${option} is deprecated and ignored; Lightsail mode now probes return-path connectivity from the instance" >&2
}

warn_ignored_option() {
    local option=$1

    print_warn "${option} is ignored by the current Lightsail rotation flow" >&2
}

instance_matches_tags() {
    local tags_json=$1
    local filters_csv=$2
    local filter

    [ -z "$filters_csv" ] && return 0

    while IFS= read -r filter; do
        [ -z "$filter" ] && continue

        local key="${filter%%=*}"
        local value="${filter#*=}"

        if [ -z "$key" ] || [ "$key" == "$filter" ]; then
            print_err "Invalid Lightsail tag filter: ${filter} (expected key=value)" >&2
            return 2
        fi

        local matched
        matched=$(echo "$tags_json" | jq --arg key "$key" --arg value "$value" \
            'any(.[]?; .key == $key and ((.value // "") == $value))')

        [ "$matched" != "true" ] && return 1
    done < <(normalize_csv "$filters_csv")

    return 0
}

build_instance_ssh_target() {
    local ip=$1

    if [ -n "$INSTANCE_SSH_USER" ]; then
        echo "${INSTANCE_SSH_USER}@${ip}"
    else
        echo "$ip"
    fi
}

instance_ssh() {
    local ip=$1
    local command=$2
    local target
    target=$(build_instance_ssh_target "$ip")

    local args=(
        -o BatchMode=yes
        -o StrictHostKeyChecking=accept-new
        -o ConnectTimeout="$SSH_CONNECT_TIMEOUT"
    )

    if [ -n "$INSTANCE_SSH_KEY" ]; then
        args+=(-i "$INSTANCE_SSH_KEY")
    fi

    if [ -n "$INSTANCE_SSH_HOST_KEY_ALIAS" ]; then
        args+=(-o HostKeyAlias="$INSTANCE_SSH_HOST_KEY_ALIAS")
    fi

    ssh "${args[@]}" "$target" "$command"
}

wait_for_instance_ssh() {
    local ip=$1
    local elapsed=0

    while [ "$elapsed" -lt "$STATIC_IP_READY_TIMEOUT" ]; do
        if instance_ssh "$ip" "printf ok" >/dev/null 2>&1; then
            return 0
        fi

        sleep 3
        elapsed=$((elapsed + 3))
    done

    return 1
}

probe_return_target_from_instance() {
    local ip=$1
    local host=$2
    local port=$3

    instance_ssh "$ip" "timeout '$PROBE_TIMEOUT' bash -lc 'echo >/dev/tcp/$host/$port'" >/dev/null 2>&1
}

probe_return_path_once() {
    local ip=$1
    local cm_ok=0
    local cu_ok=0
    local ct_ok=0
    local total_ok=0
    local target carrier label host port

    for target in "${RETURN_PROBE_TARGETS[@]}"; do
        IFS='|' read -r carrier label host port <<< "$target"

        if probe_return_target_from_instance "$ip" "$host" "$port"; then
            total_ok=$((total_ok + 1))
            case "$carrier" in
                cm) cm_ok=$((cm_ok + 1)) ;;
                cu) cu_ok=$((cu_ok + 1)) ;;
                ct) ct_ok=$((ct_ok + 1)) ;;
            esac
            print_ok "Return probe ${label}: ${host}:${port} reachable from ${ip}" >&2
        else
            print_warn "Return probe ${label}: ${host}:${port} unreachable from ${ip}" >&2
        fi
    done

    local carriers_ok=0
    [ "$cm_ok" -ge "$RETURN_PROBE_CARRIER_MIN_OK" ] && carriers_ok=$((carriers_ok + 1))
    [ "$cu_ok" -ge "$RETURN_PROBE_CARRIER_MIN_OK" ] && carriers_ok=$((carriers_ok + 1))
    [ "$ct_ok" -ge "$RETURN_PROBE_CARRIER_MIN_OK" ] && carriers_ok=$((carriers_ok + 1))

    print_info "Return probe result for ${ip}: total=${total_ok}/15 carriers=${carriers_ok}/3 cm=${cm_ok}/5 cu=${cu_ok}/5 ct=${ct_ok}/5" >&2

    if [ "$carriers_ok" -ge "$RETURN_PROBE_MIN_CARRIERS" ] && [ "$total_ok" -ge "$RETURN_PROBE_TOTAL_MIN_OK" ]; then
        return 0
    fi

    return 1
}

probe_return_path_from_instance() {
    local ip=$1
    local round

    if ! wait_for_instance_ssh "$ip"; then
        print_err "Instance SSH is unreachable after assigning IPv4 ${ip}" >&2
        return 2
    fi

    for (( round=1; round<=RETURN_PROBE_ROUNDS; round++ )); do
        print_info "Return path probe round ${round}/${RETURN_PROBE_ROUNDS} for ${ip}" >&2
        if probe_return_path_once "$ip"; then
            print_ok "Return path probe passed for ${ip} on round ${round}" >&2
            return 0
        fi
    done

    print_warn "Return path probe failed for ${ip} after ${RETURN_PROBE_ROUNDS} round(s)" >&2
    return 1
}

get_lightsail_regions() {
    aws lightsail get-regions --region us-east-1 --include-availability-zones --query 'regions[].name' --output text 2>/dev/null
}

get_attached_static_ip_name() {
    local region=$1
    local instance_name=$2

    aws lightsail get-static-ips --region "$region" \
        --query "staticIps[?attachedTo=='${instance_name}'].name | [0]" \
        --output text 2>/dev/null | grep -v '^None$' || true
}

get_static_ip_address() {
    local region=$1
    local static_ip_name=$2

    aws lightsail get-static-ip --region "$region" --static-ip-name "$static_ip_name" \
        --query 'staticIp.ipAddress' --output text 2>/dev/null
}

release_static_ip() {
    local region=$1
    local static_ip_name=$2

    [ -z "$static_ip_name" ] && return 0

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would detach and release static IP: ${static_ip_name} (${region})" >&2
        return 0
    fi

    print_info "Releasing static IP: ${static_ip_name} (${region})" >&2
    aws lightsail detach-static-ip --region "$region" --static-ip-name "$static_ip_name" &>/dev/null || true
    aws lightsail release-static-ip --region "$region" --static-ip-name "$static_ip_name" &>/dev/null || true
}

release_static_ip_strict() {
    local region=$1
    local static_ip_name=$2

    [ -z "$static_ip_name" ] && return 0

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would detach and release static IP: ${static_ip_name} (${region})" >&2
        return 0
    fi

    print_info "Releasing static IP: ${static_ip_name} (${region})" >&2
    aws lightsail detach-static-ip --region "$region" --static-ip-name "$static_ip_name" &>/dev/null || true
    if ! aws lightsail release-static-ip --region "$region" --static-ip-name "$static_ip_name" &>/dev/null; then
        return 1
    fi
}

remember_completed_rotation() {
    local region=$1
    local instance_name=$2
    local previous_static_ip=$3
    local new_static_ip=$4

    COMPLETED_ROTATIONS+=("${region}|${instance_name}|${previous_static_ip}|${new_static_ip}")
}

rollback_completed_rotations() {
    local index

    for (( index=${#COMPLETED_ROTATIONS[@]}-1; index>=0; index-- )); do
        local entry=${COMPLETED_ROTATIONS[$index]}
        local region instance_name previous_static_ip new_static_ip
        IFS='|' read -r region instance_name previous_static_ip new_static_ip <<< "$entry"

        print_warn "Rolling back ${instance_name}: restoring ${previous_static_ip}" >&2
        release_static_ip "$region" "$new_static_ip"
        if [ -n "$previous_static_ip" ]; then
            attach_static_ip "$region" "$instance_name" "$previous_static_ip" || true
        fi
    done

    COMPLETED_ROTATIONS=()
}

finalize_completed_rotations() {
    local entry

    for entry in "${COMPLETED_ROTATIONS[@]}"; do
        local region instance_name previous_static_ip new_static_ip
        IFS='|' read -r region instance_name previous_static_ip new_static_ip <<< "$entry"

        [ -n "$previous_static_ip" ] && release_static_ip "$region" "$previous_static_ip"
    done

    COMPLETED_ROTATIONS=()
}

clear_rotation_restore() {
    ROTATION_RESTORE_ACTIVE=false
    ROTATION_RESTORE_REGION=""
    ROTATION_RESTORE_INSTANCE_NAME=""
    ROTATION_RESTORE_STATIC_IP=""
    ROTATION_RESTORE_CANDIDATE_STATIC_IP=""
}

set_rotation_restore() {
    local region=$1
    local instance_name=$2
    local static_ip_name=$3

    ROTATION_RESTORE_ACTIVE=true
    ROTATION_RESTORE_REGION="$region"
    ROTATION_RESTORE_INSTANCE_NAME="$instance_name"
    ROTATION_RESTORE_STATIC_IP="$static_ip_name"
    ROTATION_RESTORE_CANDIDATE_STATIC_IP=""
}

set_rotation_candidate_restore() {
    local region=$1
    local instance_name=$2
    local static_ip_name=$3

    ROTATION_RESTORE_ACTIVE=true
    ROTATION_RESTORE_REGION="$region"
    ROTATION_RESTORE_INSTANCE_NAME="$instance_name"
    ROTATION_RESTORE_CANDIDATE_STATIC_IP="$static_ip_name"
}

restore_previous_static_ip_if_needed() {
    [ "$ROTATION_RESTORE_ACTIVE" != "true" ] && return 0

    if [ -n "$ROTATION_RESTORE_CANDIDATE_STATIC_IP" ]; then
        print_warn "Releasing interrupted candidate static IP ${ROTATION_RESTORE_CANDIDATE_STATIC_IP}" >&2
        release_static_ip "$ROTATION_RESTORE_REGION" "$ROTATION_RESTORE_CANDIDATE_STATIC_IP"
    fi

    if [ -z "$ROTATION_RESTORE_STATIC_IP" ]; then
        clear_rotation_restore
        return 0
    fi

    print_warn "Restoring previous static IP ${ROTATION_RESTORE_STATIC_IP} to ${ROTATION_RESTORE_INSTANCE_NAME}" >&2
    if ! attach_static_ip "$ROTATION_RESTORE_REGION" "$ROTATION_RESTORE_INSTANCE_NAME" "$ROTATION_RESTORE_STATIC_IP"; then
        print_err "Failed to restore previous static IP ${ROTATION_RESTORE_STATIC_IP} to ${ROTATION_RESTORE_INSTANCE_NAME}" >&2
        return 1
    fi

    clear_rotation_restore
}

detach_static_ip() {
    local region=$1
    local static_ip_name=$2

    [ -z "$static_ip_name" ] && return 0

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would detach static IP: ${static_ip_name} (${region})" >&2
        return 0
    fi

    print_info "Detaching static IP: ${static_ip_name} (${region})" >&2
    if ! aws lightsail detach-static-ip --region "$region" --static-ip-name "$static_ip_name" --output json >/dev/null; then
        return 1
    fi
}

allocate_static_ip() {
    local region=$1
    local instance_name=$2
    local attempt=$3
    local safe_name
    safe_name=$(echo "$instance_name" | tr -c 'A-Za-z0-9-' '-')
    safe_name="${safe_name:0:40}"
    local static_ip_name="cf-dns-sync-${safe_name}-$(date +%s)-${attempt}"
    ALLOCATED_STATIC_IP_NAME=""
    ALLOCATED_STATIC_IP_ADDRESS=""

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would allocate static IP: ${static_ip_name} (${region})" >&2
        return 0
    fi

    print_info "Allocating static IP: ${static_ip_name} (${region})" >&2
    if ! aws lightsail allocate-static-ip --region "$region" --static-ip-name "$static_ip_name" --output json >/dev/null; then
        return 1
    fi

    CREATED_STATIC_IPS+=("${region}:${static_ip_name}")

    ALLOCATED_STATIC_IP_NAME="$static_ip_name"
    if ! ALLOCATED_STATIC_IP_ADDRESS=$(get_static_ip_address "$region" "$static_ip_name"); then
        return 1
    fi
}

attach_static_ip() {
    local region=$1
    local instance_name=$2
    local static_ip_name=$3

    [ -z "$static_ip_name" ] && return 1

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would attach static IP: ${static_ip_name} -> ${instance_name} (${region})" >&2
        return 0
    fi

    print_info "Attaching static IP to ${instance_name}" >&2
    if ! aws lightsail attach-static-ip --region "$region" --static-ip-name "$static_ip_name" --instance-name "$instance_name" --output json >/dev/null; then
        return 1
    fi

    sleep 3
}

ensure_lightsail_instance_reachable() {
    local region=$1
    local instance_name=$2
    local current_ip=$3
    SELECTED_LIGHTSAIL_IP=""
    INSTANCE_SSH_HOST_KEY_ALIAS="lightsail-${region}-${instance_name}"

    print_info "Checking Lightsail instance ${instance_name} (${region}) IPv4 ${current_ip}" >&2
    if [ "$FORCE_ROTATE_IP" == "true" ]; then
        print_warn "Force rotate enabled; rotating ${instance_name} even if current IPv4 is reachable" >&2
        remember_lightsail_excluded_ip "$current_ip"
    elif [ -n "$current_ip" ] && [ "$current_ip" != "None" ]; then
        local check_result=0
        if probe_return_path_from_instance "$current_ip"; then
            check_result=0
        else
            check_result=$?
        fi

        if [ "$check_result" -eq 0 ]; then
            SELECTED_LIGHTSAIL_IP="$current_ip"
            return 0
        fi

        if [ "$check_result" -eq 2 ]; then
            print_err "Return path probe could not SSH into ${instance_name}; aborting before rotating" >&2
            return 1
        fi

        remember_lightsail_excluded_ip "$current_ip"
    fi

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would rotate ${instance_name} until return path probe passes" >&2
        SELECTED_LIGHTSAIL_IP="$current_ip"
        return 0
    fi

    local active_static_ip
    active_static_ip=$(get_attached_static_ip_name "$region" "$instance_name")

    if [ -n "$current_ip" ] && [ "$current_ip" != "None" ] && [ "$ALLOW_PROBE_OUTAGE" != "true" ]; then
        print_err "Rotating ${instance_name} changes the currently published IPv4 during probes" >&2
        print_err "Re-run with --allow-probe-outage to allow that short outage window, or keep the current IP" >&2
        return 1
    fi

    if [ -z "$active_static_ip" ] && [ -n "$current_ip" ] && [ "$current_ip" != "None" ]; then
        print_warn "No attached static IP found for ${instance_name}; rollback cannot restore current dynamic IPv4 ${current_ip}" >&2
    fi

    local attempt
    for (( attempt=1; attempt<=LIGHTSAIL_MAX_ATTEMPTS; attempt++ )); do
        print_info "Allocating Lightsail static IP candidate ${attempt}/${LIGHTSAIL_MAX_ATTEMPTS} for ${instance_name}" >&2

        if ! allocate_static_ip "$region" "$instance_name" "$attempt"; then
            print_warn "Failed to allocate static IP candidate for ${instance_name}" >&2
            continue
        fi

        local new_static_ip=$ALLOCATED_STATIC_IP_NAME
        local new_ip=$ALLOCATED_STATIC_IP_ADDRESS

        if [ -z "$new_static_ip" ] || [ -z "$new_ip" ] || [ "$new_ip" == "None" ]; then
            print_warn "Allocated static IP candidate has no IPv4 address; skipping" >&2
            release_static_ip "$region" "$new_static_ip"
            continue
        fi

        if is_lightsail_excluded_ip "$new_ip"; then
            print_warn "Skipping previously used IPv4 for ${instance_name}: ${new_ip}" >&2
            release_static_ip "$region" "$new_static_ip"
            continue
        fi

        local previous_static_ip=$active_static_ip

        print_info "Testing static IP candidate ${attempt}/${LIGHTSAIL_MAX_ATTEMPTS} for ${instance_name}: ${new_ip}" >&2

        if [ -n "$previous_static_ip" ]; then
            if ! detach_static_ip "$region" "$previous_static_ip"; then
                print_err "Failed to detach current static IP ${previous_static_ip} before testing ${new_ip}" >&2
                release_static_ip "$region" "$new_static_ip"
                continue
            fi
            active_static_ip=""
            set_rotation_restore "$region" "$instance_name" "$previous_static_ip"
        fi

        if ! attach_static_ip "$region" "$instance_name" "$new_static_ip"; then
            print_err "Failed to attach static IP candidate ${new_static_ip} (${new_ip}) to ${instance_name}" >&2
            release_static_ip "$region" "$new_static_ip"
            if [ -n "$previous_static_ip" ]; then
                if attach_static_ip "$region" "$instance_name" "$previous_static_ip"; then
                    active_static_ip="$previous_static_ip"
                    clear_rotation_restore
                else
                    print_err "Failed to restore previous static IP ${previous_static_ip} on ${instance_name}" >&2
                    return 1
                fi
            fi
            continue
        fi

        active_static_ip="$new_static_ip"
        set_rotation_candidate_restore "$region" "$instance_name" "$new_static_ip"

        local check_result=0
        if probe_return_path_from_instance "$new_ip"; then
            check_result=0
        else
            check_result=$?
        fi

        if [ "$check_result" -eq 0 ]; then
            print_ok "Selected IPv4 for ${instance_name}: ${new_ip}" >&2
            remember_completed_rotation "$region" "$instance_name" "$previous_static_ip" "$new_static_ip"
            clear_rotation_restore
            if [ -n "$current_ip" ] && [ "$current_ip" != "None" ] && [ "$current_ip" != "$new_ip" ]; then
                print_result "Rotated ${instance_name}: ${current_ip} -> ${new_ip}" >&2
            fi
            SELECTED_LIGHTSAIL_IP="$new_ip"
            return 0
        fi

        remember_lightsail_excluded_ip "$new_ip"
        if ! release_static_ip_strict "$region" "$new_static_ip"; then
            print_err "Failed to release rejected static IP candidate ${new_static_ip}" >&2
            active_static_ip="$new_static_ip"
            if [ -n "$previous_static_ip" ]; then
                if attach_static_ip "$region" "$instance_name" "$previous_static_ip"; then
                    active_static_ip="$previous_static_ip"
                    clear_rotation_restore
                else
                    print_err "Failed to restore previous static IP ${previous_static_ip} on ${instance_name}" >&2
                fi
            fi
            return 1
        fi
        set_rotation_candidate_restore "$region" "$instance_name" ""
        active_static_ip=""

        if [ -n "$previous_static_ip" ]; then
            if attach_static_ip "$region" "$instance_name" "$previous_static_ip"; then
                active_static_ip="$previous_static_ip"
                clear_rotation_restore
            else
                print_err "Failed to restore previous static IP ${previous_static_ip} on ${instance_name}" >&2
                return 1
            fi
        else
            clear_rotation_restore
        fi
    done

    print_err "No return-path reachable IPv4 found for ${instance_name} after ${LIGHTSAIL_MAX_ATTEMPTS} allocated candidate(s)" >&2
    return 1
}

cleanup_unattached_static_ips() {
    local region=$1
    local item

    for item in "${CREATED_STATIC_IPS[@]}"; do
        local item_region="${item%%:*}"
        local static_ip_name="${item#*:}"

        [ "$item_region" != "$region" ] && continue

        local is_attached
        is_attached=$(aws lightsail get-static-ip --region "$region" --static-ip-name "$static_ip_name" \
            --query 'staticIp.isAttached' --output text 2>/dev/null || true)

        [ "$is_attached" != "False" ] && continue

        if [ "$DRY_RUN" == "true" ]; then
            print_warn "[DRY-RUN] Would release unattached static IP: ${static_ip_name} (${region})" >&2
        else
            print_info "Releasing unattached static IP created by this run: ${static_ip_name} (${region})" >&2
            aws lightsail release-static-ip --region "$region" --static-ip-name "$static_ip_name" &>/dev/null || true
        fi
    done
}

cleanup_created_static_ips() {
    restore_previous_static_ip_if_needed || true
    rollback_completed_rotations

    [ "${#CREATED_STATIC_IPS[@]}" -eq 0 ] && return 0

    local regions=()
    local item

    for item in "${CREATED_STATIC_IPS[@]}"; do
        local item_region="${item%%:*}"
        regions+=("$item_region")
    done

    local cleaned_regions
    cleaned_regions=$(printf '%s\n' "${regions[@]}" | sort -u)
    while IFS= read -r region; do
        [ -n "$region" ] && cleanup_unattached_static_ips "$region"
    done <<< "$cleaned_regions"

    CREATED_STATIC_IPS=()
}

register_lightsail_cleanup_trap() {
    [ "$LIGHTSAIL_CLEANUP_TRAP_SET" == "true" ] && return 0

    trap cleanup_created_static_ips EXIT
    trap 'exit 130' INT
    trap 'exit 143' TERM
    LIGHTSAIL_CLEANUP_TRAP_SET=true
}

clear_lightsail_cleanup_trap() {
    [ "$LIGHTSAIL_CLEANUP_TRAP_SET" != "true" ] && return 0

    trap - EXIT INT TERM
    LIGHTSAIL_CLEANUP_TRAP_SET=false
}

collect_lightsail_ips() {
    local ids_csv=$1
    local tags_csv=$2
    local resolved='{"A":[],"AAAA":[]}'
    local ids=()
    local found=0

    register_lightsail_cleanup_trap

    while IFS= read -r id; do
        ids+=("$id")
    done < <(normalize_csv "$ids_csv")

    if [ "${#ids[@]}" -eq 0 ] && [ -z "$tags_csv" ]; then
        print_err "No Lightsail instance IDs or tags provided" >&2
        exit 1
    fi

    local region
    for region in $(get_lightsail_regions); do
        local instances
        instances=$(aws lightsail get-instances --region "$region" --output json 2>/dev/null || echo '{"instances":[]}')
        local rows
        rows=$(echo "$instances" | jq -r '.instances[] | [.name, (.publicIpAddress // ""), (.arn // ""), (.supportCode // ""), (.state.name // ""), ((.tags // []) | @base64)] | @tsv')

        local seeded_row_name seeded_public_ip seeded_arn seeded_support_code seeded_state seeded_tags_base64
        while IFS=$'\t' read -r seeded_row_name seeded_public_ip seeded_arn seeded_support_code seeded_state seeded_tags_base64; do
            remember_lightsail_excluded_ip "$seeded_public_ip"
        done <<< "$rows"

        while IFS=$'\t' read -r name public_ip arn support_code state tags_base64; do
            [ -z "$name" ] && continue

            local id_wanted=false
            local id
            if [ "${#ids[@]}" -eq 0 ]; then
                id_wanted=true
            else
                for id in "${ids[@]}"; do
                    if [ "$id" == "$name" ] || [ "$id" == "$arn" ] || [ "$id" == "$support_code" ]; then
                        id_wanted=true
                        break
                    fi
                done
            fi

            [ "$id_wanted" != "true" ] && continue

            local tags_json
            tags_json=$(printf '%s' "$tags_base64" | base64 -d 2>/dev/null || echo '[]')

            local tag_result=0
            if instance_matches_tags "$tags_json" "$tags_csv"; then
                tag_result=0
            else
                tag_result=$?
            fi

            [ "$tag_result" -eq 2 ] && exit 1
            [ "$tag_result" -ne 0 ] && continue

            found=$((found + 1))

            if [ "$state" != "running" ]; then
                print_warn "Instance ${name} is ${state}; return path TCP check may fail" >&2
            fi

            local selected_ip
            if ! ensure_lightsail_instance_reachable "$region" "$name" "$public_ip"; then
                rollback_completed_rotations
                exit 1
            fi
            selected_ip="$SELECTED_LIGHTSAIL_IP"
            if [ -n "$selected_ip" ] && [ "$selected_ip" != "None" ]; then
                resolved=$(json_add_ipv4_unique "$resolved" "$selected_ip")
            fi
        done <<< "$rows"
    done

    if [ "$found" -eq 0 ]; then
        print_err "No matching Lightsail instances found for IDs '${ids_csv}' and tags '${tags_csv}'" >&2
        rollback_completed_rotations
        exit 1
    fi

    COLLECTED_LIGHTSAIL_IPS="$resolved"
}

# Get existing DNS records for the target domain
get_existing_records() {
    local zone_id=$1
    local name=$2
    local type=$3

    local result
    result=$(cf_api GET "/zones/${zone_id}/dns_records?type=${type}&name=${name}&per_page=100")

    local success
    success=$(echo "$result" | jq -r '.success')

    if [ "$success" != "true" ]; then
        local errors
        errors=$(echo "$result" | jq -r '.errors[]?.message // "Unknown error"')
        print_err "Failed to fetch existing ${type} records: ${errors}"
        return 1
    fi

    echo "$result" | jq -r '.result'
}

# Sync records for a given type (A or AAAA)
sync_records() {
    local zone_id=$1
    local target_domain=$2
    local record_type=$3
    shift 3
    local desired_ips=("$@")
    local failed=false
    local created_ids=()
    local created_ips=()
    local deleted_ips=()
    local deleted_ttls=()
    local deleted_proxied=()

    local existing
    if ! existing=$(get_existing_records "$zone_id" "$target_domain" "$record_type"); then
        return 1
    fi

    # Build arrays of existing IPs and their record IDs
    local existing_ips=()
    local existing_ids=()
    local existing_ttls=()
    local existing_proxied=()
    local count
    count=$(echo "$existing" | jq 'length')

    for (( i=0; i<count; i++ )); do
        local ip
        ip=$(echo "$existing" | jq -r ".[$i].content")
        local id
        id=$(echo "$existing" | jq -r ".[$i].id")
        local ttl
        ttl=$(echo "$existing" | jq -r ".[$i].ttl")
        local proxied
        proxied=$(echo "$existing" | jq -r ".[$i].proxied // false")
        existing_ips+=("$ip")
        existing_ids+=("$id")
        existing_ttls+=("$ttl")
        existing_proxied+=("$proxied")
    done

    # Create records for IPs not yet present before deleting old records.
    for desired in "${desired_ips[@]}"; do
        local found=false

        for existing_ip in "${existing_ips[@]}"; do
            if [ "$desired" == "$existing_ip" ]; then
                found=true
                break
            fi
        done

        if [ "$found" == "false" ]; then
            local payload
            payload=$(jq -n \
                --arg type "$record_type" \
                --arg name "$target_domain" \
                --arg content "$desired" \
                --argjson ttl "$TTL" \
                --argjson proxied "$PROXIED" \
                '{type: $type, name: $name, content: $content, ttl: $ttl, proxied: $proxied}')

            if [ "$DRY_RUN" == "true" ]; then
                print_warn "[DRY-RUN] Would create ${record_type} record: ${desired}"
            else
                print_info "Creating ${record_type} record: ${desired}"
                local create_result
                create_result=$(cf_api POST "/zones/${zone_id}/dns_records" "$payload")
                local create_ok
                create_ok=$(echo "$create_result" | jq -r '.success')
                if [ "$create_ok" == "true" ]; then
                    print_ok "Created ${record_type} ${desired}"
                    local created_id
                    created_id=$(echo "$create_result" | jq -r '.result.id // empty')
                    if [ -n "$created_id" ]; then
                        created_ids+=("$created_id")
                        created_ips+=("$desired")
                    fi
                else
                    local err_msg
                    err_msg=$(echo "$create_result" | jq -r '.errors[]?.message // "Unknown error"')
                    print_err "Failed to create ${record_type} ${desired}: ${err_msg}"
                    failed=true
                fi
            fi
        else
            print_info "Already exists ${record_type}: ${desired} (skipped)"
        fi
    done

    if [ "$failed" == "true" ]; then
        if [ "$DRY_RUN" != "true" ]; then
            for (( i=0; i<${#created_ids[@]}; i++ )); do
                local created_id="${created_ids[$i]}"
                local created_ip="${created_ips[$i]}"

                print_warn "Rolling back created ${record_type} record: ${created_ip}" >&2
                cf_api DELETE "/zones/${zone_id}/dns_records/${created_id}" >/dev/null || true
            done
        fi

        return 1
    fi

    # Delete records whose IP is not in desired list only after creates pass.
    for (( i=0; i<${#existing_ips[@]}; i++ )); do
        local ip="${existing_ips[$i]}"
        local id="${existing_ids[$i]}"
        local ttl="${existing_ttls[$i]}"
        local proxied="${existing_proxied[$i]}"
        local found=false

        for desired in "${desired_ips[@]}"; do
            if [ "$ip" == "$desired" ]; then
                found=true
                break
            fi
        done

        if [ "$found" == "false" ]; then
            if [ "$DRY_RUN" == "true" ]; then
                print_warn "[DRY-RUN] Would delete ${record_type} record: ${ip} (id: ${id})"
            else
                print_info "Deleting ${record_type} record: ${ip}"
                local del_result
                del_result=$(cf_api DELETE "/zones/${zone_id}/dns_records/${id}")
                local del_ok
                del_ok=$(echo "$del_result" | jq -r '.success')
                if [ "$del_ok" == "true" ]; then
                    print_ok "Deleted ${record_type} ${ip}"
                    deleted_ips+=("$ip")
                    deleted_ttls+=("$ttl")
                    deleted_proxied+=("$proxied")
                else
                    local err_msg
                    err_msg=$(echo "$del_result" | jq -r '.errors[]?.message // "Unknown error"')
                    print_err "Failed to delete ${record_type} ${ip}: ${err_msg}"
                    failed=true
                fi
            fi
        fi
    done

    if [ "$failed" == "true" ] && [ "$DRY_RUN" != "true" ]; then
        for (( i=0; i<${#deleted_ips[@]}; i++ )); do
            local deleted_ip="${deleted_ips[$i]}"
            local deleted_ttl="${deleted_ttls[$i]}"
            local deleted_proxied="${deleted_proxied[$i]}"
            local restore_payload
            restore_payload=$(jq -n \
                --arg type "$record_type" \
                --arg name "$target_domain" \
                --arg content "$deleted_ip" \
                --argjson ttl "$deleted_ttl" \
                --argjson proxied "$deleted_proxied" \
                '{type: $type, name: $name, content: $content, ttl: $ttl, proxied: $proxied}')

            print_warn "Restoring deleted ${record_type} record: ${deleted_ip}" >&2
            cf_api POST "/zones/${zone_id}/dns_records" "$restore_payload" >/dev/null || true
        done

        for (( i=0; i<${#created_ids[@]}; i++ )); do
            local created_id="${created_ids[$i]}"
            local created_ip="${created_ips[$i]}"

            print_warn "Rolling back created ${record_type} record: ${created_ip}" >&2
            cf_api DELETE "/zones/${zone_id}/dns_records/${created_id}" >/dev/null || true
        done
    fi

    [ "$failed" == "false" ]
}

show_help() {
    echo "Cloudflare DNS Sync"
    echo ""
    echo "Collect IPs from a source and sync them to a target domain"
    echo "via the Cloudflare DNS API."
    echo ""
    echo "Usage:"
    echo "  $0 <source-domain> <target-domain> [options]"
    echo "  <command> | $0 - <target-domain> [options]"
    echo "  $0 --lightsail-ids ID[,ID...] <target-domain> [options]"
    echo "  $0 --lightsail-tags KEY=VALUE[,KEY=VALUE...] <target-domain> [options]"
    echo ""
    echo "Source (first argument):"
    echo "  domain.com         Resolve IPs via dig (A + AAAA records)"
    echo "  -                  Read from stdin, auto-extract all IPs"
    echo "  --lightsail-ids    Match AWS Lightsail instances by name, ARN, or support code"
    echo "  --lightsail-tags   Match AWS Lightsail instances by tags; all tags must match"
    echo ""
    echo "Options:"
    echo "  --check-port PORT  Filter out IPs where TCP port is unreachable"
    echo "  --check-timeout N  Health check timeout in seconds (default: 3)"
    echo "  --instance-ssh-user USER  SSH user for Lightsail return-path probes (default: current user)"
    echo "  --instance-ssh-key PATH   SSH private key for Lightsail return-path probes (default: SSH config/agent)"
    echo "  --probe-timeout N         Return-path TCP probe timeout in seconds (default: 3)"
    echo "  --probe-rounds N          Return-path probe rounds before rejecting IP (default: 2)"
    echo "  --probe-carrier-min-ok N  Per-carrier target successes required (default: 2)"
    echo "  --probe-min-carriers N    Carrier groups required to pass (default: 2)"
    echo "  --probe-total-min-ok N    Total target successes required (default: 6)"
    echo "  --ssh-timeout N           SSH connect timeout in seconds (default: 8)"
    echo "  --static-ip-ready-timeout N  Max seconds to wait for SSH after attaching IP (default: 60)"
    echo "  --max-attempts N          Max Lightsail static IP candidates to test (default: 10)"
    echo "  --allow-probe-outage      Allow temporary outage while candidate IPs are probed"
    echo "  --force-rotate-ip  Always rotate Lightsail static IPs before DNS sync"
    echo "  --probe-ssh-hosts/--probe-port/--probe-min-ok are deprecated and ignored"
    echo "  --ttl N            TTL in seconds (default: 60)"
    echo "  --proxied          Enable Cloudflare proxy (default: off)"
    echo "  --dry-run          Show what would be done without making changes"
    echo "  --skip-dns-sync    Collect/rotate IPs but do not update Cloudflare DNS"
    echo "  --debug            Show detailed step-by-step logs"
    echo "  -h, --help         Show this help"
    echo ""
    echo "Environment (one of the following):"
    echo "  CF_Token       Cloudflare API Token (DNS edit permission)"
    echo "  CF_Key         Cloudflare Global API Key"
    echo "  CF_Email       Cloudflare account email (used with CF_Key)"
    echo "  AWS credentials must be configured for Lightsail mode"
    echo "  LIGHTSAIL_SSH_USER  Optional default for --instance-ssh-user"
    echo "  LIGHTSAIL_SSH_KEY   Optional default for --instance-ssh-key"
    echo "  Lightsail rotation temporarily detaches the current static IP while each candidate is probed"
    echo ""
    echo "Examples:"
    echo "  # From domain (dig)"
    echo "  $0 source.example.com target.example.com"
    echo "  $0 source.example.com target.example.com --proxied --dry-run"
    echo ""
    echo "  # From API via pipe (extract IPs from any JSON/text)"
    echo "  curl -s 'https://api.example.com/servers' -H 'auth: token' \\"
    echo "    | jq -r '.data[].inIp' \\"
    echo "    | $0 - target.example.com"
    echo ""
    echo "  # From a file"
    echo "  cat ip-list.txt | $0 - target.example.com"
    echo ""
    echo "  # Inline IPs"
    echo "  echo -e '1.2.3.4\n5.6.7.8' | $0 - target.example.com"
    echo ""
    echo "  # With health check (only sync IPs where port 443 is open)"
    echo "  $0 source.example.com target.example.com --check-port 443"
    echo ""
    echo "  # From AWS Lightsail, rotate static IPs until return-path probes pass"
    echo "  $0 --lightsail-ids ls-a,ls-b target.example.com"
    echo "  $0 --lightsail-tags role=proxy,env=prod target.example.com --instance-ssh-key ~/.ssh/lightsail.pem"
    echo "  $0 --lightsail-tags role=proxy target.example.com --force-rotate-ip --allow-probe-outage"
}

main() {
    local source_domain=""
    local target_domain=""

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --ttl)
                TTL="$2"
                shift 2
                ;;
            --proxied)
                PROXIED=true
                shift
                ;;
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --skip-dns-sync)
                SKIP_DNS_SYNC=true
                shift
                ;;
            --debug)
                DEBUG=true
                shift
                ;;
            --check-port)
                CHECK_PORT="$2"
                shift 2
                ;;
            --check-timeout)
                CHECK_TIMEOUT="$2"
                shift 2
                ;;
            --lightsail-ids)
                SOURCE_MODE="lightsail"
                LIGHTSAIL_IDS="$2"
                shift 2
                ;;
            --lightsail-tags)
                SOURCE_MODE="lightsail"
                LIGHTSAIL_TAGS="$2"
                shift 2
                ;;
            --instance-ssh-user)
                INSTANCE_SSH_USER="$2"
                shift 2
                ;;
            --instance-ssh-key)
                INSTANCE_SSH_KEY="$2"
                shift 2
                ;;
            --probe-timeout)
                PROBE_TIMEOUT="$2"
                shift 2
                ;;
            --probe-ssh-hosts)
                warn_deprecated_probe_option "$1"
                shift 2
                ;;
            --probe-port)
                warn_deprecated_probe_option "$1"
                shift 2
                ;;
            --probe-min-ok)
                warn_deprecated_probe_option "$1"
                shift 2
                ;;
            --probe-rounds)
                RETURN_PROBE_ROUNDS="$2"
                shift 2
                ;;
            --probe-carrier-min-ok)
                RETURN_PROBE_CARRIER_MIN_OK="$2"
                shift 2
                ;;
            --probe-min-carriers)
                RETURN_PROBE_MIN_CARRIERS="$2"
                shift 2
                ;;
            --probe-total-min-ok)
                RETURN_PROBE_TOTAL_MIN_OK="$2"
                shift 2
                ;;
            --ssh-timeout)
                SSH_CONNECT_TIMEOUT="$2"
                shift 2
                ;;
            --static-ip-ready-timeout)
                STATIC_IP_READY_TIMEOUT="$2"
                shift 2
                ;;
            --static-ip-batch-size)
                warn_ignored_option "$1"
                shift 2
                ;;
            --allow-probe-outage)
                ALLOW_PROBE_OUTAGE=true
                shift
                ;;
            --max-attempts)
                LIGHTSAIL_MAX_ATTEMPTS="$2"
                shift 2
                ;;
            --force-rotate-ip)
                FORCE_ROTATE_IP=true
                shift
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            -*)
                # Allow bare "-" as stdin source (first positional arg)
                if [ "$1" == "-" ] && [ -z "$source_domain" ]; then
                    source_domain="-"
                else
                    print_err "Unknown option: $1"
                    show_help
                    exit 1
                fi
                shift
                ;;
            *)
                if [ "$SOURCE_MODE" == "lightsail" ] && [ -z "$target_domain" ]; then
                    target_domain="$1"
                elif [ -z "$source_domain" ]; then
                    source_domain="$1"
                elif [ -z "$target_domain" ]; then
                    target_domain="$1"
                else
                    print_err "Unexpected argument: $1"
                    exit 1
                fi
                shift
                ;;
        esac
    done

    if [ "$SOURCE_MODE" == "lightsail" ] && [ -z "$target_domain" ] && [ -n "$source_domain" ]; then
        target_domain="$source_domain"
        source_domain=""
    fi

    if [ "$SOURCE_MODE" == "lightsail" ]; then
        if { [ -z "$LIGHTSAIL_IDS" ] && [ -z "$LIGHTSAIL_TAGS" ]; } || [ -z "$target_domain" ]; then
            print_err "Lightsail IDs or tags, and target are required"
            echo ""
            show_help
            exit 1
        fi
    elif [ -z "$source_domain" ] || [ -z "$target_domain" ]; then
        print_err "Both source and target are required"
        echo ""
        show_help
        exit 1
    fi

    validate_lightsail_probe_options
    check_deps

    # Step 1: Get IPs from source
    if [ "$DEBUG" == "true" ]; then
        echo ""
        echo -e "${CYAN}=== Step 1: Collect IPs from source ===${NC}"
    fi

    local resolved
    if [ "$SOURCE_MODE" == "lightsail" ]; then
        if [ -n "$LIGHTSAIL_TAGS" ]; then
            print_info "Collecting AWS Lightsail IPv4 addresses for IDs '${LIGHTSAIL_IDS}' and tags '${LIGHTSAIL_TAGS}'"
        else
            print_info "Collecting AWS Lightsail IPv4 addresses for IDs '${LIGHTSAIL_IDS}'"
        fi
        collect_lightsail_ips "$LIGHTSAIL_IDS" "$LIGHTSAIL_TAGS"
        resolved="$COLLECTED_LIGHTSAIL_IPS"
    elif [ "$source_domain" == "-" ]; then
        # Read from stdin
        print_info "Reading IPs from stdin..."
        local stdin_text
        stdin_text=$(cat)
        if [ -z "$stdin_text" ]; then
            print_err "No input received from stdin"
            exit 1
        fi
        resolved=$(extract_ips_from_text "$stdin_text")
    else
        # Resolve from domain
        print_info "Resolving: ${source_domain}"
        resolved=$(resolve_ips "$source_domain")
    fi

    local ipv4_count
    ipv4_count=$(echo "$resolved" | jq '.A | length')
    local ipv6_count
    ipv6_count=$(echo "$resolved" | jq '.AAAA | length')

    if [ "$ipv4_count" -eq 0 ] && [ "$ipv6_count" -eq 0 ]; then
        if [ "$SOURCE_MODE" == "lightsail" ]; then
            print_err "No IPv4 addresses collected from Lightsail"
        else
            print_err "No IPs resolved from ${source_domain}"
        fi
        exit 1
    fi

    if [ "$DEBUG" == "true" ] && [ "$ipv4_count" -gt 0 ]; then
        print_ok "IPv4 (A) records: ${ipv4_count}"
        echo "$resolved" | jq -r '.A[]' | while read -r ip; do
            echo -e "  ${GREEN}$ip${NC}"
        done
    fi

    if [ "$DEBUG" == "true" ] && [ "$ipv6_count" -gt 0 ]; then
        print_ok "IPv6 (AAAA) records: ${ipv6_count}"
        echo "$resolved" | jq -r '.AAAA[]' | while read -r ip; do
            echo -e "  ${GREEN}$ip${NC}"
        done
    fi

    # Health check: filter by TCP port reachability
    if [ -n "$CHECK_PORT" ]; then
        if [ "$DEBUG" == "true" ]; then
            echo ""
            echo -e "${CYAN}=== Health Check: TCP port ${CHECK_PORT} ===${NC}"
        fi
        local pre_filter_ipv4_count=$ipv4_count
        resolved=$(filter_by_port "$resolved" "$CHECK_PORT")

        # Recount after filtering
        ipv4_count=$(echo "$resolved" | jq '.A | length')
        ipv6_count=$(echo "$resolved" | jq '.AAAA | length')

        if [ "$SOURCE_MODE" == "lightsail" ] && [ "$ipv4_count" -lt "$pre_filter_ipv4_count" ]; then
            print_err "One or more Lightsail IPv4 addresses failed the TCP health check; rolling back rotations" >&2
            exit 1
        fi

        if [ "$ipv4_count" -eq 0 ] && [ "$ipv6_count" -eq 0 ]; then
            print_err "No reachable IPs after health check"
            exit 1
        fi
    fi

    if [ "$SKIP_DNS_SYNC" == "true" ]; then
        local compact_ips
        compact_ips=$(echo "$resolved" | jq -r '.A + .AAAA | join(",")')
        if [ "$DEBUG" == "true" ]; then
            print_warn "Skipping Cloudflare DNS sync by request"
            echo ""
            echo -e "${CYAN}============================================${NC}"
            if [ "$SOURCE_MODE" == "lightsail" ]; then
                echo -e "  Source:  AWS Lightsail (IDs: ${LIGHTSAIL_IDS:-any}, tags: ${LIGHTSAIL_TAGS:-any})"
                echo -e "  Force IP rotation: ${FORCE_ROTATE_IP}"
                echo -e "  Return probe: ${RETURN_PROBE_ROUNDS} round(s), >=${RETURN_PROBE_MIN_CARRIERS}/3 carriers, >=${RETURN_PROBE_TOTAL_MIN_OK}/15 total"
            else
                echo -e "  Source:  $([ "$source_domain" == "-" ] && echo "stdin" || echo "$source_domain")"
            fi
            echo -e "  Target:  ${target_domain}"
            echo -e "  IPv4:    ${ipv4_count} record(s)"
            echo -e "  IPv6:    ${ipv6_count} record(s)"
            echo -e "  Mode:    ${YELLOW}DNS SKIPPED${NC}"
            echo -e "${CYAN}============================================${NC}"
        else
            print_result "DNS skipped target=${target_domain} ips=${compact_ips}"
        fi
        if [ "$SOURCE_MODE" == "lightsail" ]; then
            finalize_completed_rotations
            cleanup_created_static_ips
            clear_lightsail_cleanup_trap
        fi
        exit 0
    fi

    # Step 2: Get Cloudflare Zone ID for target domain
    if [ "$DEBUG" == "true" ]; then
        echo ""
        echo -e "${CYAN}=== Step 2: Find Cloudflare zone for target ===${NC}"
    fi
    print_info "Looking up zone for: ${target_domain}"

    local zone_id
    zone_id=$(get_zone_id "$target_domain") || true

    if [ -z "$zone_id" ]; then
        print_err "Could not find Cloudflare zone for: ${target_domain}"
        print_err "Ensure the domain is added to your Cloudflare account"
        exit 1
    fi

    print_ok "Zone ID: ${zone_id}"

    # Step 3: Sync DNS records
    if [ "$DEBUG" == "true" ]; then
        echo ""
        echo -e "${CYAN}=== Step 3: Sync DNS records to ${target_domain} ===${NC}"
    fi

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "DRY-RUN mode: no changes will be made"
        echo ""
    fi

    # Sync A records
    if [ "$ipv4_count" -gt 0 ]; then
        local ipv4_array=()
        while IFS= read -r ip; do
            ipv4_array+=("$ip")
        done < <(echo "$resolved" | jq -r '.A[]')

        if ! sync_records "$zone_id" "$target_domain" "A" "${ipv4_array[@]}"; then
            print_err "DNS sync failed for A records" >&2
            exit 1
        fi
    fi

    # Sync AAAA records
    if [ "$ipv6_count" -gt 0 ]; then
        local ipv6_array=()
        while IFS= read -r ip; do
            ipv6_array+=("$ip")
        done < <(echo "$resolved" | jq -r '.AAAA[]')

        if ! sync_records "$zone_id" "$target_domain" "AAAA" "${ipv6_array[@]}"; then
            print_err "DNS sync failed for AAAA records" >&2
            exit 1
        fi
    fi

    if [ "$SOURCE_MODE" == "lightsail" ]; then
        finalize_completed_rotations
        cleanup_created_static_ips
        clear_lightsail_cleanup_trap
    fi

    # Summary
    local compact_ips
    compact_ips=$(echo "$resolved" | jq -r '.A + .AAAA | join(",")')
    if [ "$DEBUG" == "true" ]; then
        echo ""
        echo -e "${CYAN}============================================${NC}"
        if [ "$SOURCE_MODE" == "lightsail" ]; then
            echo -e "  Source:  AWS Lightsail (IDs: ${LIGHTSAIL_IDS:-any}, tags: ${LIGHTSAIL_TAGS:-any})"
        else
            echo -e "  Source:  $([ "$source_domain" == "-" ] && echo "stdin" || echo "$source_domain")"
        fi
        echo -e "  Target:  ${target_domain}"
        echo -e "  IPv4:    ${ipv4_count} record(s)"
        echo -e "  IPv6:    ${ipv6_count} record(s)"
        echo -e "  TTL:     ${TTL}s"
        echo -e "  Proxied: ${PROXIED}"
        if [ "$SOURCE_MODE" == "lightsail" ]; then
            echo -e "  Force IP rotation: ${FORCE_ROTATE_IP}"
            echo -e "  Return probe: ${RETURN_PROBE_ROUNDS} round(s), >=${RETURN_PROBE_MIN_CARRIERS}/3 carriers, >=${RETURN_PROBE_TOTAL_MIN_OK}/15 total"
        fi
        if [ "$DRY_RUN" == "true" ]; then
            echo -e "  Mode:    ${YELLOW}DRY-RUN${NC}"
        else
            echo -e "  Mode:    ${GREEN}LIVE${NC}"
        fi
        echo -e "${CYAN}============================================${NC}"
    else
        local mode="LIVE"
        [ "$DRY_RUN" == "true" ] && mode="DRY-RUN"
        print_result "Synced target=${target_domain} ips=${compact_ips} mode=${mode}"
    fi
}

main "$@"
