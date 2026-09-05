#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

echo "== NanoPino Admin Production Builder =="
echo "Directory: $(pwd)"
echo "Node: $(node --version)"
echo "npm:  $(npm --version)"
echo "Vite base: relative (./)"

unset LUMA_LOCAL || true

echo
echo "[1/4] Installing/updating dependencies..."
npm install --no-audit --no-fund

echo
echo "[2/4] Cleaning old dist..."
rm -rf dist

echo
echo "[3/4] Building Vue/Luma frontend..."
npm run build

echo
echo "[4/4] Build script already verified manifest, assets, relative URLs and source fingerprint."

echo
echo "SUCCESS NANOPINO SOURCE-PARITY BUILD"
echo "Built folder:"
echo "  $(pwd)/dist"
echo
echo "Replace the OLD dist on hosting with this NEW dist:"
echo "  /home/ix2ir/test/apps/com_pinoox_cms/theme/cms-admin/dist/"
