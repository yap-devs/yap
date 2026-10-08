#!/bin/sh
set -eu

# Build outside the repository and verify the immutable upstream revision.
patch_dir=$(CDPATH='' cd -- "$(dirname -- "$0")" && pwd)
output=${1:-"$PWD/v2ray-yap"}
case "$output" in /*) ;; *) output="$PWD/$output" ;; esac
build_dir=$(mktemp -d "${TMPDIR:-/tmp}/yap-v2fly.XXXXXXXX")
trap 'rm -rf "$build_dir"' EXIT HUP INT TERM
export GOTOOLCHAIN="${GOTOOLCHAIN:-go1.26.8}"
revision=b53bebbb859f2970e99432d53047a1bb8620e987
git clone --quiet --depth 1 --branch v5.53.0 https://github.com/v2fly/v2ray-core.git "$build_dir/source"
cd "$build_dir/source"
[ "$(git rev-parse HEAD)" = "$revision" ] || { echo 'Upstream revision mismatch' >&2; exit 1; }
git apply --check "$patch_dir/v2fly-v5.53.0-port-stats.patch"
git apply "$patch_dir/v2fly-v5.53.0-port-stats.patch"
git apply --check "$patch_dir/v2fly-v5.53.0-security-deps.patch"
git apply "$patch_dir/v2fly-v5.53.0-security-deps.patch"
go test ./app/dispatcher -run 'TestYAPPortStats|TestStatsWriter' -count=1
CGO_ENABLED=0 go build -trimpath -ldflags='-s -w' -o "$output" ./main
printf 'Built %s from %s with yap-port-stats-v1\n' "$output" "$revision"
