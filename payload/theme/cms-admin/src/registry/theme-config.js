import { cmsBrand, cmsDirection, t } from '../i18n/index.js'
import { cmsPlatform } from '../services/nanoshell-platform.js'
import { navigationSections } from './admin-manifest.js'

export function buildThemeConfig(manifest) {
  const pageMeta = Object.fromEntries(
    manifest.routes.map((route) => [
      route.name,
      {
        title: route.meta?.title || route.name,
        lead: route.meta?.lead || '',
        badge: route.meta?.badge || undefined,
      },
    ]),
  )

  const brand = cmsBrand()
  const platform = cmsPlatform()

  return {
    brand: {
      title: brand.name,
      subtitle: brand.subtitle,
    },
    platform: {
      id: platform.id,
      name: platform.name,
      contract: platform.contract,
      version: platform.version,
    },
    font: {
      sans: 'Vazir, Vazirmatn, Inter, system-ui, sans-serif',
      mono: 'JetBrains Mono, ui-monospace, SFMono-Regular, Menlo, monospace',
    },
    layout: {
      sidebarWidth: '272px',
      sidebarCollapsedWidth: '72px',
      topbarHeight: '64px',
      pageMaxWidth: '1440px',
      radius: 'md',
    },
    direction: cmsDirection(),
    nav: {
      sections: navigationSections(manifest),
    },
    pageMeta,
    // The CMS is opened by Pinoox Manager with a short-lived manager JWT.
    // Do not call the generic remote-account endpoint from inside /qwe:
    // that would resolve to /qwe/api/v1/account/api/v1/auth/get.
    auth: {
      skipMe: true,
      autoLoginFromUrl: true,
    },
    user: {
      roleLabel: t('common.site_manager', {}, 'Site manager'),
    },
    loading: {
      enabled: true,
      delay: 220,
      label: t('state.loading_title', {}, 'Loading'),
    },
  }
}
