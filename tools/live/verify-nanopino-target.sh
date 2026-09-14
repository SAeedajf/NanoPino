#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${NANOPINO_TARGET_URL:-https://test.boxpdf.ir}"
BASE_URL="${BASE_URL%/}"

http_status() {
  curl -ksS -L --max-time 20 -o /dev/null -w '%{http_code}' "$1"
}

header_value() {
  curl -ksS -L --max-time 20 -D - -o /dev/null "$1" \
    | awk -v key="$2" 'BEGIN { IGNORECASE=1 } tolower($0) ~ "^" tolower(key) ":" { sub(/^[^:]*:[[:space:]]*/, ""); gsub(/[\r\n]/, ""); print; exit }'
}

assert_status() {
  local label="$1" url="$2" expected="$3" actual
  actual="$(http_status "$url")"
  if [[ "$actual" != "$expected" ]]; then
    printf 'FAIL %-18s expected=%s actual=%s url=%s\n' "$label" "$expected" "$actual" "$url" >&2
    return 1
  fi
  printf 'PASS %-18s HTTP %s\n' "$label" "$actual"
}

assert_private_status() {
  local label="$1" url="$2" actual allowed
  shift 2
  actual="$(http_status "$url")"
  for allowed in "$@"; do
    if [[ "$actual" == "$allowed" ]]; then
      printf 'PASS %-18s HTTP %s (private boundary)\n' "$label" "$actual"
      return 0
    fi
  done
  printf 'FAIL %-18s expected one of [%s] actual=%s url=%s\n' "$label" "$*" "$actual" "$url" >&2
  return 1
}

assert_status "manager" "$BASE_URL/manager/" 200
assert_status "public-site" "$BASE_URL/qwe/site" 200
# 401 is the canonical unauthenticated response. 403 remains accepted for
# older target deployments that normalize missing identity to authorization.
assert_private_status "private-dashboard" "$BASE_URL/qwe/" 401 403
assert_private_status "private-health" "$BASE_URL/qwe/api/v1/cms/system/health" 401 403

for header in X-Content-Type-Options X-Frame-Options; do
  value="$(header_value "$BASE_URL/qwe/site" "$header")"
  if [[ -z "$value" ]]; then
    printf 'FAIL %-18s missing %s\n' "public-header" "$header" >&2
    exit 1
  fi
  printf 'PASS %-18s %s: %s\n' "public-header" "$header" "$value"
done

printf 'Target boundary probe passed for %s\n' "$BASE_URL"
