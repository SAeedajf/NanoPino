// Shared by native runtime and the bundled Vue admin.
const identifier = value => typeof value === 'string' && /^[A-Za-z0-9_.:-]{1,96}$/.test(value) ? value : null

export async function readApiResponse(response, translate, { method = 'GET' } = {}) {
  if (response.ok && (response.status === 204 || method.toUpperCase() === 'HEAD')) return null
  let payload
  try { payload = await response.json() } catch { payload = null }
  const valid = payload !== null && typeof payload === 'object'
  if (response.ok && valid && payload.success !== false) return payload

  const details = payload?.error?.details ?? null
  const errorId = identifier(details?.error_id) || identifier(payload?.error_id)
  const correlationId = identifier(details?.correlation_id) || identifier(payload?.correlation_id)
    || identifier(response.headers?.get?.('X-Correlation-ID'))
  const uncertain = response.ok && !valid
  const fallback = uncertain
    ? translate('common.api_unconfirmed', 'The server response could not be confirmed. Check the current state before retrying.')
    : `${translate('common.api_error', 'API error')} (${response.status})`
  const supplied = payload?.error?.message || payload?.message
  const message = !uncertain && typeof supplied === 'string' ? supplied : fallback
  const trace = errorId || correlationId
  const error = new Error(trace ? `${message} — ${translate('common.error_reference', 'Tracking ID')}: \u2066${trace}\u2069` : message)
  error.code = uncertain ? 'CMS_INVALID_RESPONSE' : (payload?.error?.code || 'CMS_API_ERROR')
  error.status = response.status
  error.details = details
  error.errorId = errorId
  error.correlationId = correlationId
  throw error
}
