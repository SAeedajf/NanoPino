$ErrorActionPreference = "Stop"

Write-Host "== NanoPino Admin Production Builder =="
node --version
npm --version

npm install --no-audit --no-fund
npm run build

if (-not (Test-Path "dist/.vite/manifest.json")) {
    throw "dist/.vite/manifest.json was not created."
}

# npm run build performs dist verification and writes dist/.cms-build.json

Write-Host ""
Write-Host "Build complete."
Write-Host "Upload the dist folder to:"
Write-Host "/home/ix2ir/test/apps/com_pinoox_cms/theme/cms-admin/dist/"
