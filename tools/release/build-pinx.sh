#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PACKAGE="com_pinoox_cms"

if [[ $# -lt 1 || $# -gt 2 ]]; then
  echo 'Usage: tools/release/build-pinx.sh <pinoox-build-root> [output-file]' >&2
  exit 64
fi

PINOX_ROOT="$(cd "$1" && pwd)"
[[ -f "$PINOX_ROOT/pinoox" ]] || { echo "Invalid Pinoox build root: $PINOX_ROOT" >&2; exit 65; }
[[ ! -e "$PINOX_ROOT/apps/$PACKAGE" && ! -L "$PINOX_ROOT/apps/$PACKAGE" ]] || { echo "Refusing to overwrite an existing $PACKAGE app in build root." >&2; exit 66; }

PHP_BIN="${PHP_BIN:-php}"
export PHP_BIN
for dependency in "$PHP_BIN" node npm python3 rsync sha256sum mktemp; do
  command -v "$dependency" >/dev/null || { echo "Required build tool unavailable: $dependency" >&2; exit 69; }
done
"$ROOT/tools/release/verify-source.sh"

version_name="$("$PHP_BIN" -r '$a=require $argv[1]; echo $a["version-name"];' "$ROOT/payload/app.php")"
version_code="$("$PHP_BIN" -r '$a=require $argv[1]; echo $a["version-code"];' "$ROOT/payload/app.php")"
OUTPUT="${2:-$ROOT/../output/NanoPino-${PACKAGE}-${version_name}-${version_code}.pinx}"
# Resolve against the caller's directory before entering the native build root.
OUTPUT="$(python3 - "$OUTPUT" "$ROOT" "$PINOX_ROOT" <<'PYTHON'
from pathlib import Path
import sys
output = Path(sys.argv[1]).absolute()
# Reject symlink destinations rather than following them to an unrelated artifact.
if output.is_symlink() or (output.exists() and not output.is_file()):
    sys.exit("Output must be a regular file path, not a directory or symlink.")
output = output.resolve()
for forbidden in (Path(sys.argv[2]).resolve(), Path(sys.argv[3]).resolve()):
    if output == forbidden or forbidden in output.parents:
        sys.exit("Output must be outside the source repository and Pinoox build root.")
print(output)
PYTHON
)"
mkdir -p "$(dirname "$OUTPUT")"

STAGING=""
APP_CREATED=0
cleanup() {
  if [[ "$APP_CREATED" == 1 ]]; then rm -rf -- "$PINOX_ROOT/apps/$PACKAGE"; fi
  if [[ -n "$STAGING" ]]; then rm -rf -- "$STAGING"; fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# mkdir (without -p) also refuses a competing build's app directory.
mkdir -p "$PINOX_ROOT/apps"
mkdir "$PINOX_ROOT/apps/$PACKAGE"
APP_CREATED=1
STAGING="$(mktemp -d "$(dirname "$OUTPUT")/.nanopino-build.XXXXXXXX")"
CANDIDATE="$STAGING/$(basename "$OUTPUT")"
rsync -a --delete --exclude 'node_modules/' "$ROOT/payload/" "$PINOX_ROOT/apps/$PACKAGE/"

cd "$PINOX_ROOT"
"$PHP_BIN" pinoox pinx:build "$PACKAGE" --output="$CANDIDATE" --no-sign --yes
"$PHP_BIN" pinoox pinx:info "$CANDIDATE"
"$PHP_BIN" "$ROOT/tools/release/verify-pinx-installability.php" "$CANDIDATE"
[[ -s "$CANDIDATE" && ! -L "$CANDIDATE" ]] || { echo 'Native build produced no regular PINX artifact.' >&2; exit 70; }
# Staging is on the destination filesystem: publish only after every gate passes.
mv -fT -- "$CANDIDATE" "$OUTPUT"
sha256sum "$OUTPUT"
printf "pinx=%s\n" "$OUTPUT"
