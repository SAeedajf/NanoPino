#!/usr/bin/env bash
set -euo pipefail

# Safe production monitor: GET-only probes, no credentials, no mutations.
BASE_URL="${NANOPINO_TARGET_URL:-https://test.boxpdf.ir}"
BASE_URL="${BASE_URL%/}"
SAMPLES="${NANOPINO_MONITOR_SAMPLES:-1}"
INTERVAL_SECONDS="${NANOPINO_MONITOR_INTERVAL_SECONDS:-60}"
TIMEOUT_SECONDS="${NANOPINO_MONITOR_TIMEOUT_SECONDS:-20}"
OUTPUT_FILE="${NANOPINO_MONITOR_OUTPUT:-}"

usage() {
  cat <<'EOF'
Usage: monitor-nanopino-hypercare.sh

Environment:
  NANOPINO_TARGET_URL                  Target base URL (default: https://test.boxpdf.ir)
  NANOPINO_MONITOR_SAMPLES             Number of snapshots (default: 1)
  NANOPINO_MONITOR_INTERVAL_SECONDS    Delay between snapshots (default: 60)
  NANOPINO_MONITOR_TIMEOUT_SECONDS     Per-request timeout (default: 20)
  NANOPINO_MONITOR_OUTPUT              Optional JSONL output file

The monitor performs only GET requests. It does not authenticate, mutate data,
upload files, follow user-controlled URLs or send alerts to an external service.
Use its non-zero exit status as the input to an approved scheduler/alert route.
EOF
}

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" ]]; then
  usage
  exit 0
fi

is_positive_integer() { [[ "$1" =~ ^[1-9][0-9]*$ ]]; }
if ! is_positive_integer "$SAMPLES" || ! is_positive_integer "$INTERVAL_SECONDS" || ! is_positive_integer "$TIMEOUT_SECONDS"; then
  printf 'Invalid monitor configuration: samples, interval and timeout must be positive integers.\n' >&2
  exit 2
fi

tmp_dir="$(mktemp -d)"
trap 'rm -rf "$tmp_dir"' EXIT

json_escape() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  value="${value//$'\n'/\\n}"
  printf '%s' "$value"
}

probe() {
  local label="$1" path="$2" expectation="$3" headers_file
  headers_file="$tmp_dir/${label}.headers"
  local result status latency expected_ok
  result="$(curl -sS -L --max-time "$TIMEOUT_SECONDS" \
    -D "$headers_file" -o /dev/null -w '%{http_code}|%{time_total}' \
    "$BASE_URL$path" 2>"$tmp_dir/${label}.error" || true)"
  status="${result%%|*}"
  latency="${result#*|}"
  [[ "$status" =~ ^[0-9]{3}$ ]] || status="000"
  [[ "$latency" =~ ^[0-9]+([.][0-9]+)?$ ]] || latency="0"

  expected_ok=0
  case "$expectation" in
    200) [[ "$status" == "200" ]] && expected_ok=1 ;;
    private) [[ "$status" == "401" || "$status" == "403" ]] && expected_ok=1 ;;
    *) printf 'Unknown expectation: %s\n' "$expectation" >&2; exit 2 ;;
  esac

  printf '%s|%s|%s|%s' "$label" "$status" "$latency" "$expected_ok"
}

required_headers_ok() {
  local headers_file="$1" header
  for header in \
    content-security-policy \
    strict-transport-security \
    referrer-policy \
    permissions-policy \
    cross-origin-opener-policy \
    cross-origin-resource-policy \
    x-content-type-options \
    x-frame-options; do
    if ! awk -v key="$header" 'BEGIN { IGNORECASE=1 } tolower($0) ~ "^" key ":" { found=1 } END { exit(found ? 0 : 1) }' "$headers_file"; then
      return 1
    fi
  done
  return 0
}

emit_snapshot() {
  local sample="$1" timestamp="$2" manager public dashboard health overall
  local manager_status public_status dashboard_status health_status
  local manager_latency public_latency dashboard_latency health_latency
  local manager_ok public_ok dashboard_ok health_ok header_ok
  local raw

  raw="$(probe manager /manager/ 200)"
  IFS='|' read -r _ manager_status manager_latency manager_ok <<< "$raw"
  raw="$(probe public_site /qwe/site 200)"
  IFS='|' read -r _ public_status public_latency public_ok <<< "$raw"
  raw="$(probe private_dashboard /qwe private)"
  IFS='|' read -r _ dashboard_status dashboard_latency dashboard_ok <<< "$raw"
  raw="$(probe private_health /qwe/api/v1/cms/system/health private)"
  IFS='|' read -r _ health_status health_latency health_ok <<< "$raw"
  header_ok=0
  if required_headers_ok "$tmp_dir/public_site.headers"; then header_ok=1; fi

  overall=0
  if [[ "$manager_ok$public_ok$dashboard_ok$health_ok$header_ok" == "11111" ]]; then overall=1; fi

  local json
  json="{\"timestamp\":\"$(json_escape "$timestamp")\",\"sample\":$sample,\"target\":\"$(json_escape "$BASE_URL")\",\"overall_ok\":$overall,\"probes\":{\"manager\":{\"status\":$manager_status,\"latency_seconds\":$manager_latency,\"ok\":$manager_ok},\"public_site\":{\"status\":$public_status,\"latency_seconds\":$public_latency,\"ok\":$public_ok},\"private_dashboard\":{\"status\":$dashboard_status,\"latency_seconds\":$dashboard_latency,\"ok\":$dashboard_ok},\"private_health\":{\"status\":$health_status,\"latency_seconds\":$health_latency,\"ok\":$health_ok},\"security_headers\":{\"all_required\":$header_ok}}}"
  printf '%s\n' "$json"
  if [[ -n "$OUTPUT_FILE" ]]; then
    mkdir -p "$(dirname "$OUTPUT_FILE")"
    printf '%s\n' "$json" >> "$OUTPUT_FILE"
  fi
  [[ "$overall" == "1" ]]
}

failures=0
for ((sample = 1; sample <= SAMPLES; sample++)); do
  if ! emit_snapshot "$sample" "$(date -u +%Y-%m-%dT%H:%M:%SZ)"; then
    failures=$((failures + 1))
  fi
  if (( sample < SAMPLES )); then
    sleep "$INTERVAL_SECONDS"
  fi
done

if (( failures > 0 )); then
  printf 'Hypercare monitor failed: %s/%s snapshot(s) failed for %s\n' "$failures" "$SAMPLES" "$BASE_URL" >&2
  exit 1
fi
printf 'Hypercare monitor passed: %s snapshot(s) for %s\n' "$SAMPLES" "$BASE_URL" >&2
