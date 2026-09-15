import { createApp } from '@pinooxhq/luma'
import { defineAsyncComponent } from 'vue'
import { createPinia } from 'pinia'
import { coreComponentRegistry, buildAdminRoutes } from './registry/admin-routes.js'
import { resolveAdminManifest } from './registry/admin-manifest.js'
import { mergeComponentRegistries } from './registry/admin-components.js'
import { buildThemeConfig } from './registry/theme-config.js'
import './styles/admin.scss'
import { applyCmsDocumentLocale, t } from './i18n/index.js'
import { applyNanoShellIdentity } from './services/nanoshell-platform.js'
import { configureTelemetry, installUnhandledErrorCapture, trackPage } from './services/telemetry.js'

// Keep the initial entry focused on boot/auth/router work. The shell is loaded
// after the runtime contract is ready, so Luma's visual dependencies do not
// block the first application paint.
const RootShell = defineAsyncComponent(() => import('@pinooxhq/luma/layouts/RootShell.vue'))

const signal = (name, detail = {}) => {
  window.dispatchEvent(new CustomEvent(name, { detail }))
}

function installShellAccessibility() {
  const root = document.querySelector('#app')
  if (!root) return

  const main = root.querySelector('main')
  if (!main) return

  main.id ||= 'cms-main-content'
  main.tabIndex = -1

  if (!root.querySelector('#cms-skip-link')) {
    const skip = document.createElement('a')
    skip.id = 'cms-skip-link'
    skip.className = 'cms-skip-link'
    skip.href = `#${main.id}`
    skip.textContent = t('a11y.skip_to_content', {}, 'Skip to content')
    root.prepend(skip)
  }
}

function focusMainContent() {
  const main = document.querySelector('#cms-main-content')
  if (!main || typeof main.focus !== 'function') return

  // SPA route changes must announce the new page to keyboard and screen-reader users.
  main.focus({ preventScroll: true })
}

async function bootAdmin() {
  applyNanoShellIdentity()
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

    // PageLayout renders its semantic main after the router is mounted.
    // Keep the check bounded so a broken custom route cannot create a loop.
    let shellChecks = 0
    const checkShell = () => {
      installShellAccessibility()
      if (!document.querySelector('#cms-main-content') && shellChecks++ < 60) {
        window.requestAnimationFrame(checkShell)
      }
    }
    window.requestAnimationFrame(checkShell)

    configureTelemetry(boot.cmsAdmin?.telemetry)
    installUnhandledErrorCapture()
    runtime?.router?.afterEach?.((to) => {
      window.requestAnimationFrame(() => {
        installShellAccessibility()
        focusMainContent()
      })
      trackPage(to.fullPath, to.meta?.title || document.title)
    })
    trackPage(runtime?.router?.currentRoute?.value?.fullPath || window.location.pathname, document.title)

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
