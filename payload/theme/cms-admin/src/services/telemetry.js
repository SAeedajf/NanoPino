const state = {
  config: {},
  active: false,
  installed: false,
}

const allowedContextKeys = new Set(['operation', 'path', 'method', 'status', 'correlationId', 'source'])

function asBoolean(value) {
  return value === true || value === 1 || value === '1' || value === 'true'
}

function cleanPath(value) {
  const fallback = typeof window !== 'undefined' ? window.location.pathname : '/'
  try {
    const url = new URL(String(value || fallback), window.location.origin)
    return url.pathname || '/'
  } catch {
    return fallback
  }
}

function cleanContext(context = {}) {
  return Object.fromEntries(
    Object.entries(context)
      .filter(([key]) => allowedContextKeys.has(key))
      .map(([key, value]) => [key, key === 'path' ? cleanPath(value) : String(value ?? '')]),
  )
}

function providerEnabled(key) {
  return state.active && Boolean(state.config[key])
}

export function createCorrelationId() {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID()
  }
  return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`
}

export function configureTelemetry(config = {}) {
  state.config = {
    ...(config && typeof config === 'object' ? config : {}),
    enabled: asBoolean(config?.enabled),
    consent: asBoolean(config?.consent),
  }
  state.active = state.config.enabled && state.config.consent
  return state.active
}

export function trackPage(path, title = '') {
  if (!state.active || typeof window === 'undefined') return

  const pagePath = cleanPath(path)
  const pageTitle = String(title || document.title || '').slice(0, 160)
  const event = { event: 'page_view', page_path: pagePath, page_title: pageTitle }

  try {
    if (providerEnabled('ga4MeasurementId') && typeof window.gtag === 'function') {
      window.gtag('event', 'page_view', {
        send_to: state.config.ga4MeasurementId,
        page_path: pagePath,
        page_title: pageTitle,
      })
    }

    if (providerEnabled('googleTagManagerId')) {
      window.dataLayer = window.dataLayer || []
      window.dataLayer.push(event)
    }

    if (providerEnabled('matomoSiteId')) {
      window._paq = window._paq || []
      window._paq.push(['setCustomUrl', pagePath])
      window._paq.push(['setDocumentTitle', pageTitle])
      window._paq.push(['trackPageView'])
    }

    if (providerEnabled('matomoTagManagerId')) {
      window._mtm = window._mtm || []
      window._mtm.push(event)
    }
  } catch (error) {
    // Analytics must never make the admin unusable.
    console.debug('NanoPino telemetry provider failed.', error)
  }
}

export function captureError(error, context = {}) {
  if (!state.active || !state.config.sentryDsn || typeof window === 'undefined') return
  if (!window.Sentry || typeof window.Sentry.captureException !== 'function') return

  try {
    const safeContext = cleanContext(context)
    if (typeof window.Sentry.withScope === 'function') {
      window.Sentry.withScope((scope) => {
        Object.entries(safeContext).forEach(([key, value]) => scope.setTag(key, value))
        window.Sentry.captureException(error instanceof Error ? error : new Error(String(error)))
      })
      return
    }
    window.Sentry.captureException(error instanceof Error ? error : new Error(String(error)), {
      tags: safeContext,
    })
  } catch (captureFailure) {
    console.debug('NanoPino error reporter failed.', captureFailure)
  }
}

export function installUnhandledErrorCapture() {
  if (state.installed || typeof window === 'undefined') return
  state.installed = true
  window.addEventListener('error', (event) => {
    captureError(event.error || new Error(event.message || 'Unhandled browser error'), {
      source: 'window.error',
      path: window.location.pathname,
    })
  })
  window.addEventListener('unhandledrejection', (event) => {
    captureError(event.reason || new Error('Unhandled promise rejection'), {
      source: 'window.unhandledrejection',
      path: window.location.pathname,
    })
  })
}
