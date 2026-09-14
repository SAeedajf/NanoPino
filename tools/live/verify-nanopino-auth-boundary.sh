#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${NANOPINO_TARGET_URL:-https://test.boxpdf.ir}"
BASE_URL="${BASE_URL%/}"

probe() {
  local label="$1" path="$2" permission="$3" body status code
  body="$(mktemp)"
  trap 'rm -f "$body"' RETURN
  status="$(curl -ksS --max-time 20 -o "$body" -w '%{http_code}' \
    -H 'Accept: application/json' "$BASE_URL$path")"
  code="$(sed -n 's/.*"code":"\([A-Z_]*\)".*/\1/p' "$body" | head -n 1)"

  if [[ "$status" != "401" || "$code" != "AUTHENTICATION_REQUIRED" ]]; then
    printf 'FAIL %-14s expected=401 AUTHENTICATION_REQUIRED actual=%s %s permission=%s\n' \
      "$label" "$status" "${code:-NO_CODE}" "$permission" >&2
    return 1
  fi
  printf 'PASS %-14s HTTP 401 AUTHENTICATION_REQUIRED permission=%s\n' "$label" "$permission"
}

failures=0
probe dashboard "/qwe" "cms.admin" || failures=1
probe health "/qwe/api/v1/cms/system/health" "system.health.view" || failures=1
probe users "/qwe/api/v1/cms/users" "users.read" || failures=1
if [[ "$failures" -ne 0 ]]; then
  exit 1
fi
printf 'Anonymous auth-boundary probe passed for %s\n' "$BASE_URL"
