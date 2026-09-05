import { cmsBrand, cmsDirection, t } from '../i18n/index.js'
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

  return {
    brand: {
      title: brand.name,
      subtitle: brand.subtitle,
    },
    direction: cmsDirection(),
    nav: {
      sections: navigationSections(manifest),
    },
    pageMeta,
    user: {
      roleLabel: t('common.site_manager', {}, 'Site manager'),
    },
  }
}
