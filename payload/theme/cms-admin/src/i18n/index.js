const DEFAULT_LOCALE = 'fa'
const DEFAULT_DIRECTION = 'rtl'

function bootConfig() {
  if (typeof window === 'undefined') return {}
  return window.__PINOOX__?.cmsAdmin?.i18n || {}
}

function deepGet(source, path) {
  const segments = String(path || '').split('.').filter(Boolean)
  let value = source
  for (const segment of segments) {
    if (!value || typeof value !== 'object' || !(segment in value)) return undefined
    value = value[segment]
  }
  return value
}


function localeCandidates(locale, fallbackLocale) {
  const out = []
  for (const value of [locale, String(locale || '').split('-')[0], fallbackLocale, String(fallbackLocale || '').split('-')[0]]) {
    const key = String(value || '').trim()
    if (key && !out.includes(key)) out.push(key)
  }
  return out
}

function catalogValue(catalogs, key, locale, fallbackLocale) {
  if (!catalogs || typeof catalogs !== 'object') return undefined
  for (const candidate of localeCandidates(locale, fallbackLocale)) {
    const value = deepGet(catalogs[candidate] || {}, key)
    if (typeof value === 'string' || typeof value === 'number') return value
  }
  return undefined
}

function interpolate(value, replace = {}) {
  return String(value).replace(/:([A-Za-z0-9_]+)/g, (match, key) =>
    Object.prototype.hasOwnProperty.call(replace, key) ? String(replace[key]) : match,
  )
}

export function cmsLocale() {
  const root = typeof window !== 'undefined' ? window.__PINOOX__ || {} : {}
  return String(bootConfig().locale || root.locale || DEFAULT_LOCALE)
}

export function cmsDirection() {
  const root = typeof window !== 'undefined' ? window.__PINOOX__ || {} : {}
  return String(bootConfig().direction || root.direction || DEFAULT_DIRECTION)
}

export function t(key, replace = {}, fallback = '') {
  const value = deepGet(bootConfig().messages || {}, key)
  if (typeof value === 'string' || typeof value === 'number') {
    return interpolate(value, replace)
  }
  return fallback || String(key)
}

export function cmsBrand() {
  const brand = typeof window !== 'undefined' ? window.__PINOOX__?.cmsAdmin?.brand || {} : {}
  return {
    name: String(brand.name || t('brand.name', {}, 'NanoPino')),
    subtitle: String(brand.subtitle || t('brand.subtitle', {}, '')),
  }
}

export function applyCmsDocumentLocale() {
  if (typeof document === 'undefined') return
  document.documentElement.lang = cmsLocale()
  document.documentElement.dir = cmsDirection()
}


export function createScopedTranslator(catalogs = {}) {
  return (key, replace = {}, fallback = '') => {
    const cfg = bootConfig()
    const value = catalogValue(catalogs, key, cmsLocale(), cfg.fallbackLocale || 'en')
    if (typeof value === 'string' || typeof value === 'number') {
      return interpolate(value, replace)
    }
    return t(key, replace, fallback)
  }
}

export function cmsI18nContext() {
  const cfg = bootConfig()
  return Object.freeze({
    version: 'v1',
    locale: cmsLocale(),
    fallbackLocale: String(cfg.fallbackLocale || 'en'),
    direction: cmsDirection(),
    t,
  })
}
