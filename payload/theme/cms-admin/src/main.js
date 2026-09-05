import { createApp } from '@pinooxhq/luma'
import { RootShell } from '@pinooxhq/luma/layouts'
import { createPinia } from 'pinia'
import { coreComponentRegistry, buildAdminRoutes } from './registry/admin-routes.js'
import { resolveAdminManifest } from './registry/admin-manifest.js'
import { mergeComponentRegistries } from './registry/admin-components.js'
import { buildThemeConfig } from './registry/theme-config.js'
import './styles/admin.scss'
import { applyCmsDocumentLocale } from './i18n/index.js'

const signal = (name, detail = {}) => {
  window.dispatchEvent(new CustomEvent(name, { detail }))
}

async function bootAdmin() {
  applyCmsDocumentLocale()
  document.documentElement.dataset.cmsBoot = 'booting'
  signal('pinoox-cms:boot-start')

  try {
    const pinia = createPinia()
    const boot = window.__PINOOX__ || {}
    const manifest = resolveAdminManifest(boot.cmsAdmin?.manifest)
    const componentRegistry = mergeComponentRegistries(coreComponentRegistry, manifest.components)

    const runtime = await createApp({
      AppRoot: RootShell,
      routes: buildAdminRoutes(manifest.routes, componentRegistry),
      pinia,
      mount: '#app',
      themeConfig: buildThemeConfig(manifest),
    })

    document.documentElement.dataset.cmsBoot = 'ready'
    signal('pinoox-cms:boot-ready', {
      buildId: boot.cmsAdmin?.frontend?.buildId || null,
    })

    return runtime
  } catch (error) {
    document.documentElement.dataset.cmsBoot = 'failed'
    console.error('NanoPino Admin boot failed.', error)
    signal('pinoox-cms:boot-failed', {
      code: 'cms.admin.runtime_boot_failed',
    })
    return null
  }
}

void bootAdmin()
