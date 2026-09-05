import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import luma from '@pinooxhq/luma/vite'
import { resolve } from 'node:path'

// CMS can be mounted at any Pinoox app path (/qwe/, /cms/, ...).
// Relative base keeps lazy chunks, CSS and fonts under the same app asset root.
delete process.env.LUMA_LOCAL

export default defineConfig({
  base: './',

  plugins: [
    vue(),
    luma({
      entry: 'src/main.js',
    }),
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
