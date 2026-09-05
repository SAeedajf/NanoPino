import { PageLayout } from '@pinooxhq/luma/layouts'

export const coreComponentRegistry = Object.freeze({
  'core:dashboard': () => import('../pages/dashboard/page-dashboard.vue'),
  'core:content': () => import('../pages/content/page-content.vue'),
  'core:revisions': () => import('../pages/revisions/page-revisions.vue'),
  'core:media': () => import('../pages/media/page-media.vue'),
  'core:appearance': () => import('../pages/appearance/page-appearance.vue'),
  'core:blocks': () => import('../pages/blocks/page-blocks.vue'),
  'core:builder': () => import('../pages/builder/page-builder.vue'),
  'core:site-editor': () => import('../pages/site-editor/page-site-editor.vue'),
  'core:extensions': () => import('../pages/extensions/page-extensions.vue'),
  'core:updates': () => import('../pages/updates/page-updates.vue'),
  'core:users': () => import('../pages/users/page-users.vue'),
  'core:settings': () => import('../pages/settings/page-settings.vue'),
  'core:audit': () => import('../pages/audit/page-audit.vue'),
  'core:recovery': () => import('../pages/recovery/page-recovery.vue'),
  'core:system': () => import('../pages/system/page-system.vue'),
  'core:infrastructure': () => import('../pages/system/page-infrastructure.vue'),
  'core:security': () => import('../pages/system/page-security.vue'),
  'core:performance': () => import('../pages/system/page-performance.vue'),
  'core:logs': () => import('../pages/system/page-logs.vue'),
  'core:developer-sdk': () => import('../pages/developer/page-sdk.vue'),
})

function fallbackComponent() {
  return import('../pages/system/page-extension-unavailable.vue')
}

export function buildAdminRoutes(definitions = [], componentRegistry = coreComponentRegistry) {
  const children = definitions.map((route) => ({
    path: normalizeChildPath(route.path),
    name: route.name,
    component: componentRegistry[route.component] || fallbackComponent,
    meta: {
      ...(route.meta || {}),
      permission: route.permission || null,
      owner: route.owner || null,
      adminDefinitionId: route.id,
      requiresAuth: true,
    },
  }))

  return [{
    path: '/',
    component: PageLayout,
    meta: { requiresAuth: true },
    children,
  }]
}

function normalizeChildPath(path) {
  const value = String(path || '').trim()
  if (value === '/' || value === '') return ''
  return value.replace(/^\/+/, '')
}
