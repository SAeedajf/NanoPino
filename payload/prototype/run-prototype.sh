#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PORT="${PORT:-8093}"

echo "NanoPino prototype: http://127.0.0.1:${PORT}/"
exec php -S "127.0.0.1:${PORT}" -t "$ROOT"
