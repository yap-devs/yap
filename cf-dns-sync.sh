#!/usr/bin/env bash

#######################################################################
# Cloudflare DNS Sync
#
# Resolve IPs from a source domain and sync them as DNS records
# on a target domain via the Cloudflare API.
#
# Dependencies: dig, curl, jq
#
# Authentication (one of the following):
#   Option 1 - API Token:     CF_API_TOKEN
#   Option 2 - Global API Key: CF_Key + CF_Email
#
# Usage: ./cf-dns-sync.sh <source-domain> <target-domain> [options]
#######################################################################

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Defaults
TTL=1         # 1 = auto
PROXIED=false
DRY_RUN=false

print_ok()   { echo -e "${GREEN}[OK]${NC} $1"; }
print_err()  { echo -e "${RED}[ERROR]${NC} $1"; }
print_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }

CF_API="https://api.cloudflare.com/client/v4"

check_deps() {
    for cmd in dig curl jq; do
        if ! command -v "$cmd" &>/dev/null; then
            print_err "Missing dependency: $cmd"
            exit 1
        fi
    done

    # Determine auth method (prefer Global API Key when both are set)
    if [ -n "$CF_Key" ] && [ -n "$CF_Email" ]; then
        AUTH_METHOD="key"
        print_info "Auth: Global API Key (${CF_Email})"
    elif [ -n "$CF_API_TOKEN" ]; then
        AUTH_METHOD="token"
        print_info "Auth: API Token"
    else
        print_err "Cloudflare credentials not set"
        print_err "Option 1 - API Token:      export CF_API_TOKEN=\"your-token\""
        print_err "Option 2 - Global API Key:  export CF_Key=\"your-key\" CF_Email=\"your-email\""
        exit 1
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
        args+=(-H "Authorization: Bearer $CF_API_TOKEN")
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

# Resolve all IPs from a domain
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
    echo "Resolve IPs from a source domain and sync them to a target domain"
    echo "via the Cloudflare DNS API."
    echo ""
    echo "Usage: $0 <source-domain> <target-domain> [options]"
    echo ""
    echo "Options:"
    echo "  --ttl N            TTL in seconds (default: 1 = auto)"
    echo "  --proxied          Enable Cloudflare proxy (default: off)"
    echo "  --dry-run          Show what would be done without making changes"
    echo "  -h, --help         Show this help"
    echo ""
    echo "Environment (one of the following):"
    echo "  CF_API_TOKEN       Cloudflare API Token (DNS edit permission)"
    echo "  CF_Key         Cloudflare Global API Key"
    echo "  CF_Email       Cloudflare account email (used with CF_Key)"
    echo ""
    echo "Examples:"
    echo "  export CF_API_TOKEN=\"your-token\""
    echo "  $0 source.example.com target.example.com"
    echo ""
    echo "  export CF_Key=\"your-global-key\" CF_Email=\"you@example.com\""
    echo "  $0 source.example.com target.example.com"
    echo "  $0 source.example.com target.example.com --proxied"
    echo "  $0 source.example.com target.example.com --dry-run"
    echo "  $0 source.example.com sub.target.com --ttl 300"
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
            -h|--help)
                show_help
                exit 0
                ;;
            -*)
                print_err "Unknown option: $1"
                show_help
                exit 1
                ;;
            *)
                if [ -z "$source_domain" ]; then
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

    if [ -z "$source_domain" ] || [ -z "$target_domain" ]; then
        print_err "Both source and target domains are required"
        echo ""
        show_help
        exit 1
    fi

    check_deps

    # Step 1: Resolve IPs from source domain
    echo ""
    echo -e "${CYAN}=== Step 1: Resolve IPs from source domain ===${NC}"
    print_info "Resolving: ${source_domain}"

    local resolved
    resolved=$(resolve_ips "$source_domain")

    local ipv4_count
    ipv4_count=$(echo "$resolved" | jq '.A | length')
    local ipv6_count
    ipv6_count=$(echo "$resolved" | jq '.AAAA | length')

    if [ "$ipv4_count" -eq 0 ] && [ "$ipv6_count" -eq 0 ]; then
        print_err "No IPs resolved from ${source_domain}"
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
    echo -e "  Source:  ${source_domain}"
    echo -e "  Target:  ${target_domain}"
    echo -e "  IPv4:    ${ipv4_count} record(s)"
    echo -e "  IPv6:    ${ipv6_count} record(s)"
    echo -e "  TTL:     $([ "$TTL" -eq 1 ] && echo "Auto" || echo "${TTL}s")"
    echo -e "  Proxied: ${PROXIED}"
    if [ "$DRY_RUN" == "true" ]; then
        echo -e "  Mode:    ${YELLOW}DRY-RUN${NC}"
    else
        echo -e "  Mode:    ${GREEN}LIVE${NC}"
    fi
    echo -e "${CYAN}============================================${NC}"
}

main "$@"
