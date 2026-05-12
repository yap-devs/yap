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
CHECK_PORT=""  # empty = skip health check
CHECK_TIMEOUT=3
SOURCE_MODE="source"
LIGHTSAIL_IDS=""
LIGHTSAIL_TAGS=""
PROBE_PORT=22
PROBE_SSH_HOSTS=""
PROBE_MIN_OK=1
PROBE_TIMEOUT=5
SSH_CONNECT_TIMEOUT=8
LIGHTSAIL_MAX_ATTEMPTS=10
CREATED_STATIC_IPS=()
FORCE_ROTATE_IP=false
LOG_TS_FORMAT='%Y-%m-%d %H:%M:%S%z'

log_line() {
    local color=$1
    local level=$2
    local message=$3
    local timestamp
    timestamp=$(date "+${LOG_TS_FORMAT}")

    echo -e "${color}[${timestamp}] [${level}]${NC} ${message}"
}

print_ok()   { log_line "$GREEN" "OK" "$1"; }
print_err()  { log_line "$RED" "ERROR" "$1"; }
print_info() { log_line "$BLUE" "INFO" "$1"; }
print_warn() { log_line "$YELLOW" "WARN" "$1"; }

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

        if [ -z "$PROBE_SSH_HOSTS" ]; then
            print_err "Chinese probe SSH hosts are required for Lightsail mode"
            print_err "Use: --probe-ssh-hosts aliyun-sh[,other-host]"
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
    done < <(echo "$text" | grep -oE '([0-9]{1,3}\.){3}[0-9]{1,3}' | sort -u)

    # Extract IPv6 (simplified: sequences of hex groups separated by colons)
    while IFS= read -r ip; do
        [ -n "$ip" ] && ipv6_list+=("$ip")
    done < <(echo "$text" | grep -oE '([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}' | sort -u)

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
    done < <(dig +short A "$domain" 2>/dev/null | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$')

    # Resolve AAAA records (IPv6)
    while IFS= read -r ip; do
        [ -n "$ip" ] && ipv6_list+=("$ip")
    done < <(dig +short AAAA "$domain" 2>/dev/null | grep -E '^[0-9a-fA-F:]+$')

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

probe_tcp_from_ssh() {
    local host=$1
    local ip=$2
    local port=$3

    ssh -o BatchMode=yes -o ConnectTimeout="$SSH_CONNECT_TIMEOUT" "$host" \
        "timeout '$PROBE_TIMEOUT' bash -lc 'echo >/dev/tcp/$ip/$port'" >/dev/null 2>&1
}

# Check if Chinese SSH probe nodes can reach a TCP port.
# Returns 0 when enough probes pass, 1 when probes work but the IP fails,
# and 2 when no probe host can be reached.
probe_tcp_from_china() {
    local ip=$1
    local port=$2
    local ok_count=0
    local reachable_probes=0
    local host

    while IFS= read -r host; do
        [ -z "$host" ] && continue

        if ssh -o BatchMode=yes -o ConnectTimeout="$SSH_CONNECT_TIMEOUT" "$host" "printf ok" >/dev/null 2>&1; then
            reachable_probes=$((reachable_probes + 1))
        else
            print_warn "Probe SSH host unreachable: ${host}" >&2
            continue
        fi

        if probe_tcp_from_ssh "$host" "$ip" "$port"; then
            ok_count=$((ok_count + 1))
            print_ok "Probe ${host}: ${ip}:${port} reachable" >&2
        else
            print_warn "Probe ${host}: ${ip}:${port} unreachable" >&2
        fi
    done < <(normalize_csv "$PROBE_SSH_HOSTS")

    if [ "$reachable_probes" -eq 0 ]; then
        print_err "No Chinese SSH probe host is reachable" >&2
        return 2
    fi

    if [ "$ok_count" -ge "$PROBE_MIN_OK" ]; then
        print_ok "China TCP check: ${ok_count}/${reachable_probes} probe(s) passed" >&2
        return 0
    fi

    print_warn "China TCP check: ${ok_count}/${reachable_probes} probe(s) passed" >&2
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

allocate_and_attach_static_ip() {
    local region=$1
    local instance_name=$2
    local attempt=$3
    local safe_name
    safe_name=$(echo "$instance_name" | tr -c 'A-Za-z0-9-' '-')
    safe_name="${safe_name:0:40}"
    local static_ip_name="cf-dns-sync-${safe_name}-$(date +%s)-${attempt}"

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would allocate and attach static IP: ${static_ip_name} -> ${instance_name} (${region})" >&2
        echo ""
        return 0
    fi

    print_info "Allocating static IP: ${static_ip_name} (${region})" >&2
    aws lightsail allocate-static-ip --region "$region" --static-ip-name "$static_ip_name" --output json >/dev/null
    CREATED_STATIC_IPS+=("${region}:${static_ip_name}")

    print_info "Attaching static IP to ${instance_name}" >&2
    aws lightsail attach-static-ip --region "$region" --static-ip-name "$static_ip_name" --instance-name "$instance_name" --output json >/dev/null
    sleep 3

    get_static_ip_address "$region" "$static_ip_name"
}

ensure_lightsail_instance_reachable() {
    local region=$1
    local instance_name=$2
    local current_ip=$3

    print_info "Checking Lightsail instance ${instance_name} (${region}) IPv4 ${current_ip}" >&2
    if [ "$FORCE_ROTATE_IP" == "true" ]; then
        print_warn "Force rotate enabled; rotating ${instance_name} even if current IPv4 is reachable" >&2
    elif [ -n "$current_ip" ] && [ "$current_ip" != "None" ]; then
        local check_result=0
        if probe_tcp_from_china "$current_ip" "$PROBE_PORT"; then
            check_result=0
        else
            check_result=$?
        fi

        if [ "$check_result" -eq 0 ]; then
            echo "$current_ip"
            return 0
        fi

        if [ "$check_result" -eq 2 ]; then
            print_err "China probe check failed; aborting before rotating ${instance_name}" >&2
            return 1
        fi
    fi

    if [ "$DRY_RUN" == "true" ]; then
        print_warn "[DRY-RUN] Would rotate ${instance_name} until Chinese probe check passes" >&2
        echo "$current_ip"
        return 0
    fi

    local attempt
    for (( attempt=1; attempt<=LIGHTSAIL_MAX_ATTEMPTS; attempt++ )); do
        print_info "Rotating ${instance_name} static IP, attempt ${attempt}/${LIGHTSAIL_MAX_ATTEMPTS}" >&2

        local old_static_ip
        old_static_ip=$(get_attached_static_ip_name "$region" "$instance_name")
        if [ -n "$old_static_ip" ]; then
            release_static_ip "$region" "$old_static_ip"
        fi

        local new_ip
        new_ip=$(allocate_and_attach_static_ip "$region" "$instance_name" "$attempt")

        if [ -n "$new_ip" ] && [ "$new_ip" != "None" ]; then
            local check_result=0
            if probe_tcp_from_china "$new_ip" "$PROBE_PORT"; then
                check_result=0
            else
                check_result=$?
            fi

            if [ "$check_result" -eq 0 ]; then
                print_ok "Selected IPv4 for ${instance_name}: ${new_ip}" >&2
                echo "$new_ip"
                return 0
            fi

            if [ "$check_result" -eq 2 ]; then
                print_err "China probe check failed; aborting before more rotations for ${instance_name}" >&2
                return 1
            fi
        fi
    done

    print_err "No China-reachable IPv4 found for ${instance_name} after ${LIGHTSAIL_MAX_ATTEMPTS} attempts" >&2
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

collect_lightsail_ips() {
    local ids_csv=$1
    local tags_csv=$2
    local resolved='{"A":[],"AAAA":[]}'
    local ids=()
    local regions_touched=()
    local found=0

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
            regions_touched+=("$region")

            if [ "$state" != "running" ]; then
                print_warn "Instance ${name} is ${state}; Chinese probe TCP check may fail" >&2
            fi

            local selected_ip
            selected_ip=$(ensure_lightsail_instance_reachable "$region" "$name" "$public_ip") || exit 1
            if [ -n "$selected_ip" ] && [ "$selected_ip" != "None" ]; then
                resolved=$(json_add_ipv4_unique "$resolved" "$selected_ip")
            fi
        done <<< "$rows"
    done

    if [ "$found" -eq 0 ]; then
        print_err "No matching Lightsail instances found for IDs '${ids_csv}' and tags '${tags_csv}'" >&2
        exit 1
    fi

    local cleaned_regions
    cleaned_regions=$(printf '%s\n' "${regions_touched[@]}" | sort -u)
    while IFS= read -r region; do
        [ -n "$region" ] && cleanup_unattached_static_ips "$region"
    done <<< "$cleaned_regions"

    echo "$resolved"
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

    local existing
    existing=$(get_existing_records "$zone_id" "$target_domain" "$record_type")

    # Build arrays of existing IPs and their record IDs
    local existing_ips=()
    local existing_ids=()
    local count
    count=$(echo "$existing" | jq 'length')

    for (( i=0; i<count; i++ )); do
        local ip
        ip=$(echo "$existing" | jq -r ".[$i].content")
        local id
        id=$(echo "$existing" | jq -r ".[$i].id")
        existing_ips+=("$ip")
        existing_ids+=("$id")
    done

    # Delete records whose IP is not in desired list
    for (( i=0; i<${#existing_ips[@]}; i++ )); do
        local ip="${existing_ips[$i]}"
        local id="${existing_ids[$i]}"
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
                else
                    print_err "Failed to delete ${record_type} ${ip}"
                fi
            fi
        fi
    done

    # Create records for IPs not yet present
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
                else
                    local err_msg
                    err_msg=$(echo "$create_result" | jq -r '.errors[]?.message // "Unknown error"')
                    print_err "Failed to create ${record_type} ${desired}: ${err_msg}"
                fi
            fi
        else
            print_info "Already exists ${record_type}: ${desired} (skipped)"
        fi
    done
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
    echo "  --probe-ssh-hosts H  Chinese SSH probe hosts, comma-separated"
    echo "  --probe-port PORT    Lightsail TCP port to test from probes (default: 22)"
    echo "  --probe-min-ok N     Minimum successful probe hosts (default: 1)"
    echo "  --probe-timeout N    Remote TCP check timeout in seconds (default: 5)"
    echo "  --ssh-timeout N      SSH connect timeout in seconds (default: 8)"
    echo "  --max-attempts N   Max Lightsail static IP rotation attempts (default: 10)"
    echo "  --force-rotate-ip  Always rotate Lightsail static IPs before DNS sync"
    echo "  --ttl N            TTL in seconds (default: 60)"
    echo "  --proxied          Enable Cloudflare proxy (default: off)"
    echo "  --dry-run          Show what would be done without making changes"
    echo "  --skip-dns-sync    Collect/rotate IPs but do not update Cloudflare DNS"
    echo "  -h, --help         Show this help"
    echo ""
    echo "Environment (one of the following):"
    echo "  CF_Token       Cloudflare API Token (DNS edit permission)"
    echo "  CF_Key         Cloudflare Global API Key"
    echo "  CF_Email       Cloudflare account email (used with CF_Key)"
    echo "  AWS credentials must be configured for Lightsail mode"
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
    echo "  # From AWS Lightsail, rotate static IPs until Chinese SSH probe TCP passes"
    echo "  $0 --lightsail-ids ls-a,ls-b target.example.com --probe-ssh-hosts aliyun-sh --probe-port 22"
    echo "  $0 --lightsail-tags role=proxy,env=prod target.example.com --probe-ssh-hosts aliyun-sh"
    echo "  $0 --lightsail-tags role=proxy target.example.com --probe-ssh-hosts aliyun-sh --force-rotate-ip"
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
            --probe-ssh-hosts)
                PROBE_SSH_HOSTS="$2"
                shift 2
                ;;
            --probe-port)
                PROBE_PORT="$2"
                shift 2
                ;;
            --probe-min-ok)
                PROBE_MIN_OK="$2"
                shift 2
                ;;
            --probe-timeout)
                PROBE_TIMEOUT="$2"
                shift 2
                ;;
            --ssh-timeout)
                SSH_CONNECT_TIMEOUT="$2"
                shift 2
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

    check_deps

    # Step 1: Get IPs from source
    echo ""
    echo -e "${CYAN}=== Step 1: Collect IPs from source ===${NC}"

    local resolved
    if [ "$SOURCE_MODE" == "lightsail" ]; then
        if [ -n "$LIGHTSAIL_TAGS" ]; then
            print_info "Collecting AWS Lightsail IPv4 addresses for IDs '${LIGHTSAIL_IDS}' and tags '${LIGHTSAIL_TAGS}'"
        else
            print_info "Collecting AWS Lightsail IPv4 addresses for IDs '${LIGHTSAIL_IDS}'"
        fi
        resolved=$(collect_lightsail_ips "$LIGHTSAIL_IDS" "$LIGHTSAIL_TAGS")
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

    if [ "$ipv4_count" -gt 0 ]; then
        print_ok "IPv4 (A) records: ${ipv4_count}"
        echo "$resolved" | jq -r '.A[]' | while read -r ip; do
            echo -e "  ${GREEN}$ip${NC}"
        done
    fi

    if [ "$ipv6_count" -gt 0 ]; then
        print_ok "IPv6 (AAAA) records: ${ipv6_count}"
        echo "$resolved" | jq -r '.AAAA[]' | while read -r ip; do
            echo -e "  ${GREEN}$ip${NC}"
        done
    fi

    # Health check: filter by TCP port reachability
    if [ -n "$CHECK_PORT" ]; then
        echo ""
        echo -e "${CYAN}=== Health Check: TCP port ${CHECK_PORT} ===${NC}"
        resolved=$(filter_by_port "$resolved" "$CHECK_PORT")

        # Recount after filtering
        ipv4_count=$(echo "$resolved" | jq '.A | length')
        ipv6_count=$(echo "$resolved" | jq '.AAAA | length')

        if [ "$ipv4_count" -eq 0 ] && [ "$ipv6_count" -eq 0 ]; then
            print_err "No reachable IPs after health check"
            exit 1
        fi
    fi

    if [ "$SKIP_DNS_SYNC" == "true" ]; then
        print_warn "Skipping Cloudflare DNS sync by request"
        echo ""
        echo -e "${CYAN}============================================${NC}"
        echo -e "  Source:  AWS Lightsail (IDs: ${LIGHTSAIL_IDS:-any}, tags: ${LIGHTSAIL_TAGS:-any})"
        echo -e "  Target:  ${target_domain}"
        echo -e "  IPv4:    ${ipv4_count} record(s)"
        echo -e "  IPv6:    ${ipv6_count} record(s)"
        echo -e "  Force IP rotation: ${FORCE_ROTATE_IP}"
        echo -e "  Mode:    ${YELLOW}DNS SKIPPED${NC}"
        echo -e "${CYAN}============================================${NC}"
        exit 0
    fi

    # Step 2: Get Cloudflare Zone ID for target domain
    echo ""
    echo -e "${CYAN}=== Step 2: Find Cloudflare zone for target ===${NC}"
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
    echo ""
    echo -e "${CYAN}=== Step 3: Sync DNS records to ${target_domain} ===${NC}"

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

        sync_records "$zone_id" "$target_domain" "A" "${ipv4_array[@]}"
    fi

    # Sync AAAA records
    if [ "$ipv6_count" -gt 0 ]; then
        local ipv6_array=()
        while IFS= read -r ip; do
            ipv6_array+=("$ip")
        done < <(echo "$resolved" | jq -r '.AAAA[]')

        sync_records "$zone_id" "$target_domain" "AAAA" "${ipv6_array[@]}"
    fi

    # Summary
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
    fi
    if [ "$DRY_RUN" == "true" ]; then
        echo -e "  Mode:    ${YELLOW}DRY-RUN${NC}"
    else
        echo -e "  Mode:    ${GREEN}LIVE${NC}"
    fi
    echo -e "${CYAN}============================================${NC}"
}

main "$@"
