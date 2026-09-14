// Shared by native runtime and the bundled Vue admin.
const identifier = value => typeof value === 'string' && /^[A-Za-z0-9_.:-]{1,96}$/.test(value) ? value : null

function emitAuthenticationRequired(code, status, correlationId) {
  if (status !== 401 && code !== 'AUTHENTICATION_REQUIRED') return
  try {
    globalThis.window?.dispatchEvent?.(new CustomEvent('pinoox-cms:auth-required', {
      detail: { code: code || 'AUTHENTICATION_REQUIRED', status: status || 401, correlationId: correlationId || null },
    }))
  } catch {
    // Auth notifications must never mask the original API failure.
  }
}

export function createRequestSignal(signal, timeoutMs = 15000) {
  const hasTimeout = Number.isFinite(Number(timeoutMs)) && Number(timeoutMs) > 0
  if (typeof AbortController === 'undefined' || (!signal && !hasTimeout)) {
    return { signal, timedOut: () => false, dispose: () => {} }
  }

  const controller = new AbortController()
  let timedOut = false
  let timer = null
  const abortFromCaller = () => controller.abort()

  if (signal) {
    if (signal.aborted) abortFromCaller()
    else signal.addEventListener?.('abort', abortFromCaller, { once: true })
  }
  if (hasTimeout) {
    timer = setTimeout(() => {
      timedOut = true
      controller.abort()
    }, Number(timeoutMs))
  }

  return {
    signal: controller.signal,
    timedOut: () => timedOut,
    dispose: () => {
      if (timer) clearTimeout(timer)
      signal?.removeEventListener?.('abort', abortFromCaller)
    },
  }
}

export function createRequestTimeoutError(translate) {
  const error = new Error(translate('common.request_timeout', 'The server took too long to respond. Check the connection and try again.'))
  error.code = 'CMS_REQUEST_TIMEOUT'
  error.status = 408
  return error
}

export async function readApiResponse(response, translate, { method = 'GET' } = {}) {
  if (response.ok && (response.status === 204 || method.toUpperCase() === 'HEAD')) return null
  let payload
  try { payload = await response.json() } catch { payload = null }
  const valid = payload !== null && typeof payload === 'object' && !Array.isArray(payload)
  // CMS mutations and reads must use the explicit response envelope. Accepting
  // any JSON object here makes an HTML/proxy fallback or an empty `{}` look like
  // a successful operation and leaves the UI in a false state.
  if (response.ok && valid && payload.success === true) return payload

  const details = payload?.error?.details ?? null
  const errorId = identifier(details?.error_id) || identifier(payload?.error_id)
  const correlationId = identifier(details?.correlation_id) || identifier(payload?.correlation_id)
    || identifier(response.headers?.get?.('X-Correlation-ID'))
  const uncertain = response.ok && (!valid || payload.success !== true)
  const fallback = uncertain
    ? translate('common.api_unconfirmed', 'The server response could not be confirmed. Check the current state before retrying.')
    : `${translate('common.api_error', 'API error')} (${response.status})`
  const supplied = payload?.error?.message || payload?.message
  const code = payload?.error?.code || 'CMS_API_ERROR'
  const localized = code === 'AUTHENTICATION_REQUIRED'
    ? translate('common.authentication_required', 'Authentication is required.')
    : code === 'ACCESS_DENIED' || code === 'FORBIDDEN'
      ? translate('common.access_denied', 'You do not have permission to perform this action.')
      : null
  const message = !uncertain && localized ? localized : (!uncertain && typeof supplied === 'string' ? supplied : fallback)
  const trace = errorId || correlationId
  const error = new Error(trace ? `${message} — ${translate('common.error_reference', 'Tracking ID')}: \u2066${trace}\u2069` : message)
  error.code = uncertain ? 'CMS_INVALID_RESPONSE' : code
  error.status = response.status
  error.details = details
  error.errorId = errorId
  error.correlationId = correlationId
  error.authRequired = response.status === 401 || code === 'AUTHENTICATION_REQUIRED'
  emitAuthenticationRequired(code, response.status, correlationId)
  throw error
}
