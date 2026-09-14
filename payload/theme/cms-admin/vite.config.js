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
      perf: false,
    }),
  ],

  build: {
    outDir: 'dist',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: resolve(process.cwd(), 'src/main.js'),
      output: {
        manualChunks(id) {
          const normalized = id.replaceAll('\\\\', '/')
          if (/node_modules\/@pinooxhq\/luma\/node_modules\/@vueuse\/gesture\//.test(normalized)) return 'vendor-luma-gesture'
          if (/node_modules\/@pinooxhq\/luma\/node_modules\/(?:primevue|@primeuix|primeicons)\//.test(normalized)) return 'vendor-prime'
          if (/node_modules\/(?:primevue|@primeuix|primeicons)\//.test(normalized)) return 'vendor-prime'
          if (/node_modules\/@pinooxhq\/luma\/src\/(?:ui|layouts|core)\//.test(normalized)) return 'vendor-luma-ui'
          if (/node_modules\/@pinooxhq\/luma\//.test(normalized)) return 'vendor-luma-rest'
          if (/node_modules\/(?:vue|vue-router|pinia|vue-i18n|@vue)\//.test(normalized)) return 'vendor-vue'
          if (/node_modules\/(?:@lucide\/vue|lucide-vue-next)\//.test(normalized)) return 'vendor-icons'
        },
      },
    },
  },
})
