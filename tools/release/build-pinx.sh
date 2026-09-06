#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PACKAGE="com_pinoox_cms"

if [[ $# -lt 1 ]]; then
  echo 'Usage: tools/release/build-pinx.sh <pinoox-build-root> [output-file]' >&2
  exit 64
fi

PINOX_ROOT="$(cd "$1" && pwd)"
[[ -f "$PINOX_ROOT/pinoox" ]] || { echo "Invalid Pinoox build root: $PINOX_ROOT" >&2; exit 65; }
[[ ! -e "$PINOX_ROOT/apps/$PACKAGE" ]] || { echo "Refusing to overwrite an existing $PACKAGE app in build root." >&2; exit 66; }

PHP_BIN="${PHP_BIN:-php}"
"$ROOT/tools/release/verify-source.sh"

version_name="$("$PHP_BIN" -r '$a=require $argv[1]; echo $a["version-name"];' "$ROOT/payload/app.php")"
version_code="$("$PHP_BIN" -r '$a=require $argv[1]; echo $a["version-code"];' "$ROOT/payload/app.php")"
OUTPUT="${2:-$ROOT/../output/NanoPino-${PACKAGE}-${version_name}-${version_code}.pinx}"
mkdir -p "$(dirname "$OUTPUT")"

cleanup() { rm -rf "$PINOX_ROOT/apps/$PACKAGE"; }
trap cleanup EXIT

mkdir -p "$PINOX_ROOT/apps/$PACKAGE"
rsync -a --delete --exclude 'node_modules/' "$ROOT/payload/" "$PINOX_ROOT/apps/$PACKAGE/"

cd "$PINOX_ROOT"
"$PHP_BIN" pinoox pinx:build "$PACKAGE" --output="$OUTPUT" --no-sign --yes
"$PHP_BIN" pinoox pinx:info "$OUTPUT"
"$PHP_BIN" "$ROOT/tools/release/verify-pinx-installability.php" "$OUTPUT"
sha256sum "$OUTPUT"
printf "pinx=%s\n" "$OUTPUT"
