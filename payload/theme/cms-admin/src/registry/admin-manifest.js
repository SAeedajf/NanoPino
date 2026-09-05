import { t } from '../i18n/index.js'

const EMPTY_MANIFEST = Object.freeze({
  schema: 1,
  navigation: [],
  routes: [],
  widgets: [],
  panels: [],
  components: [],
})

function asArray(value) {
  return Array.isArray(value) ? value : []
}

function uniqueById(items, surface) {
  const seen = new Set()
  return items.map((item) => ({ ...item })).filter((item) => {
    const id = String(item?.id || '').trim()
    if (!id) return false
    if (seen.has(id)) {
      throw new Error(`Duplicate CMS admin ${surface} id: ${id}`)
    }
    seen.add(id)
    item.id = id
    return true
  })
}

function sortByOrder(items) {
  return [...items].sort((a, b) => {
    const order = Number(a.order || 100) - Number(b.order || 100)
    return order || String(a.id).localeCompare(String(b.id))
  })
}

export function resolveAdminManifest(input = null) {
  const source = input && typeof input === 'object' ? input : EMPTY_MANIFEST

  return {
    schema: Number(source.schema || 1),
    navigation: sortByOrder(uniqueById(asArray(source.navigation), 'navigation')),
    routes: sortByOrder(uniqueById(asArray(source.routes), 'route')),
    widgets: sortByOrder(uniqueById(asArray(source.widgets), 'widget')),
    panels: sortByOrder(uniqueById(asArray(source.panels), 'panel')),
    components: uniqueById(asArray(source.components), 'component'),
  }
}

export function navigationSections(manifest) {
  const sections = new Map()

  for (const item of manifest.navigation) {
    if (item.parent) continue
    const section = String(item.section || 'main')
    if (!sections.has(section)) {
      sections.set(section, [])
    }

    const children = manifest.navigation
      .filter((child) => child.parent === item.id)
      .map(toLumaNavItem)

    sections.get(section).push({
      ...toLumaNavItem(item),
      ...(children.length ? { children } : {}),
    })
  }

  return [...sections.entries()].map(([key, items]) => ({
    key,
    label: sectionLabel(key),
    items,
  }))
}

function toLumaNavItem(item) {
  return {
    key: item.id,
    label: item.label,
    icon: item.icon || 'circle',
    route: item.route || undefined,
  }
}

function sectionLabel(section) {
  return t(`sections.${section}`, {}, section)
}

export { EMPTY_MANIFEST }
