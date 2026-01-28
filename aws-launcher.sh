#!/usr/bin/env bash

#######################################################################
# AWS Lightsail Launcher
#
# Dependencies: aws-cli, jq
# Usage: ./aws-launcher.sh <command> [options]
#######################################################################

set -e

# Disable AWS CLI pager
export AWS_PAGER=""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
DEFAULT_BUNDLE="nano_3_0"
DEFAULT_BLUEPRINT="debian_12"
KEY_PAIR_NAME="id_ed25519"

# OS blueprint mapping
declare -A BLUEPRINTS=(
    ["debian12"]="debian_12"
    ["debian11"]="debian_11"
    ["ubuntu24"]="ubuntu_24_04"
    ["ubuntu22"]="ubuntu_22_04"
    ["amazon2"]="amazon_linux_2"
    ["amazon2023"]="amazon_linux_2023"
    ["centos9"]="centos_stream_9"
    ["alma9"]="alma_linux_9"
)

declare -A REGIONS=(
    ["sg"]="ap-southeast-1"
    ["tokyo"]="ap-northeast-1"
    ["ap-southeast-1"]="ap-southeast-1"
    ["ap-northeast-1"]="ap-northeast-1"
)

declare -A REGION_NAMES=(
    ["ap-southeast-1"]="Singapore"
    ["ap-northeast-1"]="Tokyo"
)

declare -A REGION_AZ=(
    ["ap-southeast-1"]="ap-southeast-1a"
    ["ap-northeast-1"]="ap-northeast-1a"
)

print_ok() { echo -e "${GREEN}[OK]${NC} $1"; }
print_err() { echo -e "${RED}[ERROR]${NC} $1"; }
print_info() { echo -e "${BLUE}[INFO]${NC} $1"; }

check_deps() {
    for cmd in aws jq; do
        if ! command -v "$cmd" &>/dev/null; then
            print_err "Missing: $cmd"
            exit 1
        fi
    done

    if ! aws sts get-caller-identity &>/dev/null; then
        print_err "AWS credentials not configured"
        exit 1
    fi
}

resolve_region() {
    local input=$1
    local region="${REGIONS[$input]}"
    if [ -z "$region" ]; then
        print_err "Invalid region: $input (use: sg, tokyo, ap-southeast-1, ap-northeast-1)"
        exit 1
    fi
    echo "$region"
}

resolve_blueprint() {
    local input=$1
    # If empty, return default
    if [ -z "$input" ]; then
        echo "$DEFAULT_BLUEPRINT"
        return
    fi
    # Check if it's a shorthand
    local blueprint="${BLUEPRINTS[$input]}"
    if [ -n "$blueprint" ]; then
        echo "$blueprint"
        return
    fi
    # Check if it's already a valid blueprint ID (contains underscore)
    if [[ "$input" == *"_"* ]]; then
        echo "$input"
        return
    fi
    # Invalid
    print_err "Invalid OS: $input"
    print_err "Available: debian12, debian11, ubuntu24, ubuntu22, amazon2, amazon2023, centos9, alma9"
    exit 1
}

# Launch instance
# Usage: launch <region> [--name NAME] [--os OS] [--script FILE]
cmd_launch() {
    local region=""
    local name=""
    local os=""
    local script_file=""

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --name|-n)
                name="$2"
                shift 2
                ;;
            --os|-o)
                os="$2"
                shift 2
                ;;
            --script|-s)
                script_file="$2"
                shift 2
                ;;
            -*)
                print_err "Unknown option: $1"
                exit 1
                ;;
            *)
                # First positional arg is region
                if [ -z "$region" ]; then
                    region="$1"
                else
                    print_err "Unexpected argument: $1"
                    exit 1
                fi
                shift
                ;;
        esac
    done

    # Validate region
    if [ -z "$region" ]; then
        print_err "Region is required"
        exit 1
    fi
    region=$(resolve_region "$region")

    # Set defaults
    name=${name:-"ls-$(date +%m%d%H%M%S)"}
    local blueprint=$(resolve_blueprint "$os")

    local userdata=""
    if [ -n "$script_file" ]; then
        if [ ! -f "$script_file" ]; then
            print_err "Script file not found: $script_file"
            exit 1
        fi
        userdata=$(cat "$script_file")
        print_info "Using startup script: $script_file"
    fi

    print_info "Creating instance: $name"
    print_info "Region: ${REGION_NAMES[$region]} ($region)"
    print_info "OS: $blueprint"

    local create_args=(
        --region "$region"
        --instance-names "$name"
        --availability-zone "${REGION_AZ[$region]}"
        --blueprint-id "$blueprint"
        --bundle-id "$DEFAULT_BUNDLE"
        --key-pair-name "$KEY_PAIR_NAME"
    )

    if [ -n "$userdata" ]; then
        create_args+=(--user-data "$userdata")
    fi

    local result
    if ! result=$(aws lightsail create-instances "${create_args[@]}" --output json 2>&1); then
        print_err "Failed to create instance"
        echo "$result"
        exit 1
    fi

    print_ok "Instance created"

    # Wait and open ports
    print_info "Waiting for instance to initialize..."
    sleep 8

    print_info "Opening all TCP ports..."
    aws lightsail put-instance-public-ports \
        --region "$region" \
        --instance-name "$name" \
        --port-infos "fromPort=0,toPort=65535,protocol=tcp" 2>/dev/null || true

    # Get instance info
    sleep 3
    local info
    info=$(aws lightsail get-instance --region "$region" --instance-name "$name" --output json 2>/dev/null)

    local ipv4=$(echo "$info" | jq -r '.instance.publicIpAddress // "pending"')
    local ipv6=$(echo "$info" | jq -r '(.instance.ipv6Addresses // [])[0] // "pending"')
    local state=$(echo "$info" | jq -r '.instance.state.name')

    echo ""
    echo -e "${CYAN}============================================${NC}"
    echo -e "  Name:    ${GREEN}$name${NC}"
    echo -e "  Region:  $region (${REGION_NAMES[$region]})"
    echo -e "  IPv4:    ${GREEN}$ipv4${NC}"
    echo -e "  IPv6:    ${GREEN}$ipv6${NC}"
    echo -e "  Status:  $state"
    echo -e "  SSH:     ssh root@$ipv4"
    echo -e "${CYAN}============================================${NC}"
}

# List instances
cmd_list() {
    local filter_region=$1

    for region in "ap-southeast-1" "ap-northeast-1"; do
        if [ -n "$filter_region" ]; then
            local resolved=$(resolve_region "$filter_region")
            [ "$region" != "$resolved" ] && continue
        fi

        local instances
        instances=$(aws lightsail get-instances --region "$region" --output json 2>/dev/null || echo '{"instances":[]}')
        local count=$(echo "$instances" | jq '.instances | length')

        [ "$count" -eq 0 ] && continue

        echo -e "${CYAN}${REGION_NAMES[$region]} ($region) - $count instance(s):${NC}"
        echo "+------------------------+----------+-----------------+------------------------------------------+"
        printf "| %-22s | %-8s | %-15s | %-40s |\n" "Name" "Status" "IPv4" "IPv6"
        echo "+------------------------+----------+-----------------+------------------------------------------+"

        local data
        data=$(echo "$instances" | jq -r '.instances[] | [.name, .state.name, (.publicIpAddress // "N/A"), ((.ipv6Addresses // [])[0] // "N/A")] | @tsv')

        while IFS=$'\t' read -r name state ipv4 ipv6; do
            [ -z "$name" ] && continue
            case $state in
                running) printf "| %-22s | \033[0;32m%-8s\033[0m | %-15s | %-40s |\n" "${name:0:22}" "$state" "$ipv4" "$ipv6" ;;
                stopped) printf "| %-22s | \033[0;31m%-8s\033[0m | %-15s | %-40s |\n" "${name:0:22}" "$state" "$ipv4" "$ipv6" ;;
                *) printf "| %-22s | \033[0;33m%-8s\033[0m | %-15s | %-40s |\n" "${name:0:22}" "$state" "$ipv4" "$ipv6" ;;
            esac
        done <<< "$data"

        echo "+------------------------+----------+-----------------+------------------------------------------+"
        echo ""
    done
}

# Delete instance
cmd_delete() {
    local region=$(resolve_region "$1")
    local name=$2

    [ -z "$name" ] && { print_err "Please provide instance name"; exit 1; }

    # Check if instance exists
    if ! aws lightsail get-instance --region "$region" --instance-name "$name" &>/dev/null; then
        print_err "Instance not found: $name"
        exit 1
    fi

    print_info "Deleting: $name ($region)"
    aws lightsail delete-instance --region "$region" --instance-name "$name" &>/dev/null
    print_ok "Deleted"
}

# Start instance
cmd_start() {
    local region=$(resolve_region "$1")
    local name=$2

    [ -z "$name" ] && { print_err "Please provide instance name"; exit 1; }

    # Check if instance exists
    if ! aws lightsail get-instance --region "$region" --instance-name "$name" &>/dev/null; then
        print_err "Instance not found: $name"
        exit 1
    fi

    print_info "Starting: $name"
    if ! aws lightsail start-instance --region "$region" --instance-name "$name" &>/dev/null; then
        print_err "Failed to start instance"
        exit 1
    fi
    print_ok "Started"
}

# Stop instance
cmd_stop() {
    local region=$(resolve_region "$1")
    local name=$2

    [ -z "$name" ] && { print_err "Please provide instance name"; exit 1; }

    # Check if instance exists
    if ! aws lightsail get-instance --region "$region" --instance-name "$name" &>/dev/null; then
        print_err "Instance not found: $name"
        exit 1
    fi

    print_info "Stopping: $name"
    if ! aws lightsail stop-instance --region "$region" --instance-name "$name" &>/dev/null; then
        print_err "Failed to stop instance"
        exit 1
    fi
    print_ok "Stopped"
}

# Rotate IP
cmd_rotate() {
    local region=$(resolve_region "$1")
    local name=$2

    [ -z "$name" ] && { print_err "Please provide instance name"; exit 1; }

    print_info "Rotating IP for: $name"

    # Release static IP if attached
    local static_ip
    static_ip=$(aws lightsail get-static-ips --region "$region" \
        --query "staticIps[?attachedTo=='$name'].name" --output text 2>/dev/null)

    if [ -n "$static_ip" ] && [ "$static_ip" != "None" ]; then
        print_info "Releasing static IP: $static_ip"
        aws lightsail detach-static-ip --region "$region" --static-ip-name "$static_ip" &>/dev/null || true
        aws lightsail release-static-ip --region "$region" --static-ip-name "$static_ip" &>/dev/null || true
    fi

    print_info "Restarting instance..."
    aws lightsail stop-instance --region "$region" --instance-name "$name" &>/dev/null
    sleep 12
    aws lightsail start-instance --region "$region" --instance-name "$name" &>/dev/null
    sleep 12

    local new_ip
    new_ip=$(aws lightsail get-instance --region "$region" --instance-name "$name" \
        --query "instance.publicIpAddress" --output text 2>/dev/null)

    print_ok "New IP: $new_ip"
}

show_help() {
    echo "AWS Lightsail Launcher"
    echo ""
    echo "Usage: $0 <command> [options]"
    echo ""
    echo "Commands:"
    echo "  launch <region> [options]      Create instance"
    echo "  list [region]                  List instances"
    echo "  delete <region> <name>         Delete instance"
    echo "  start <region> <name>          Start instance"
    echo "  stop <region> <name>           Stop instance"
    echo "  rotate <region> <name>         Rotate IP"
    echo ""
    echo "Launch options:"
    echo "  -n, --name NAME       Instance name (default: ls-MMDDHHMISS)"
    echo "  -o, --os OS           OS type (default: debian12)"
    echo "  -s, --script FILE     Startup script file"
    echo ""
    echo "Regions: sg, tokyo (or ap-southeast-1, ap-northeast-1)"
    echo ""
    echo "OS options: debian12, debian11, ubuntu24, ubuntu22,"
    echo "            amazon2, amazon2023, centos9, alma9"
    echo ""
    echo "Examples:"
    echo "  $0 launch sg"
    echo "  $0 launch sg -n my-server"
    echo "  $0 launch sg -o ubuntu24"
    echo "  $0 launch sg -s ./setup.sh"
    echo "  $0 launch tokyo -n web -o ubuntu24 -s init.sh"
    echo "  $0 list"
    echo "  $0 delete sg my-server"
}

main() {
    check_deps

    case ${1:-help} in
        launch|new|create) shift; cmd_launch "$@" ;;
        list|ls)           cmd_list "$2" ;;
        delete|rm|del)     cmd_delete "$2" "$3" ;;
        start)             cmd_start "$2" "$3" ;;
        stop)              cmd_stop "$2" "$3" ;;
        rotate|ip)         cmd_rotate "$2" "$3" ;;
        *)                 show_help ;;
    esac
}

main "$@"
