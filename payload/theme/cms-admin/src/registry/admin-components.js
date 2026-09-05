import { h } from 'vue'
import { cmsBrand, cmsDirection, cmsI18nContext, cmsLocale, createScopedTranslator, t } from '../i18n/index.js'
import { LBadge, LButton, LIcon, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'

const HOST_API = Object.freeze({
  version: 'v1',
  h,
  LBadge,
  LButton,
  LIcon,
  LPage,
  LPanel,
  LStatCard,
  brand: cmsBrand(),
  i18n: cmsI18nContext(),
})

function createExtensionHost(definition = {}) {
  const scopedT = createScopedTranslator(definition.i18n || {})
  return Object.freeze({
    ...HOST_API,
    brand: cmsBrand(),
    i18n: Object.freeze({
      version: 'v1',
      locale: cmsLocale(),
      direction: cmsDirection(),
      t,
      extensionT: scopedT,
    }),
  })
}

function fallbackComponent() {
  return import('../pages/system/page-extension-unavailable.vue')
}

export function resolveExtensionComponentRegistry(definitions = []) {
  const registry = {}

  for (const definition of Array.isArray(definitions) ? definitions : []) {
    const id = String(definition?.id || '').trim()
    if (!id || registry[id]) continue

    try {
      const moduleUrl = normalizeSameOriginModuleUrl(definition.moduleUrl)
      const exportName = normalizeExportName(definition.exportName)

      registry[id] = async () => {
        try {
          const module = await import(/* @vite-ignore */ moduleUrl)

          if (exportName === 'createComponent') {
            const factory = module.createComponent
            if (typeof factory !== 'function') {
              throw new Error('Admin module createComponent(host) export is missing.')
            }
            const component = factory(createExtensionHost(definition))
            if (!component || typeof component !== 'object') {
              throw new Error('Admin component factory returned an invalid component.')
            }
            return component
          }

          const component = exportName === 'default' ? module.default : module[exportName]
          if (!component) {
            throw new Error(`Admin component export not found: ${exportName}`)
          }
          return component
        } catch (error) {
          console.error(`Unable to load CMS Admin component ${id}`, error)
          return fallbackComponent()
        }
      }
    } catch (error) {
      console.error(`Rejected CMS Admin component ${id}`, error)
      registry[id] = fallbackComponent
    }
  }

  return registry
}

export function mergeComponentRegistries(coreRegistry, extensionDefinitions = []) {
  return Object.freeze({
    ...coreRegistry,
    ...resolveExtensionComponentRegistry(extensionDefinitions),
  })
}

function normalizeSameOriginModuleUrl(value) {
  const raw = String(value || '').trim()
  if (!raw || !raw.startsWith('/') || raw.startsWith('//') || raw.includes('\\') || raw.includes('\0')) {
    throw new Error('Extension Admin module must be a same-origin absolute path.')
  }

  const segments = raw.split('?')[0].split('/').filter(Boolean)
  if (segments.includes('..')) {
    throw new Error('Extension Admin module path traversal rejected.')
  }

  if (!/\.m?js(?:\?[A-Za-z0-9._=&%-]+)?$/.test(raw)) {
    throw new Error('Extension Admin module must be a JavaScript module.')
  }

  if (typeof window !== 'undefined') {
    const url = new URL(raw, window.location.origin)
    if (url.origin !== window.location.origin) {
      throw new Error('Cross-origin Extension Admin module rejected.')
    }
    return `${url.pathname}${url.search}`
  }

  return raw
}

function normalizeExportName(value) {
  const name = String(value || 'createComponent')
  if (name === 'default' || name === 'createComponent') return name
  if (!/^[A-Za-z_$][A-Za-z0-9_$]{0,100}$/.test(name)) {
    throw new Error('Invalid Extension Admin module export name.')
  }
  return name
}

export { HOST_API, createExtensionHost, normalizeSameOriginModuleUrl, normalizeExportName }
