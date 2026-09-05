#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

cat > vite.config.js <<'VITECONFIG'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import luma from '@pinooxhq/luma/vite'
import { resolve } from 'node:path'

delete process.env.LUMA_LOCAL

export default defineConfig({
  plugins: [
    vue(),
    luma({ entry: 'src/main.js' }),
  ],
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(process.cwd(), 'src/main.js'),
    },
  },
})
VITECONFIG

unset LUMA_LOCAL || true
rm -rf dist
npm run build
node verify-dist.mjs

echo
echo "SUCCESS: dist is ready."
