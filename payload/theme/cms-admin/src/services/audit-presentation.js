// AuditEvent.occurredAt is Unix seconds, not JavaScript milliseconds.
// UTC is explicit so two investigators see the same incident time.
export function auditTimestamp(seconds, locale = 'fa') {
  if (seconds === null || seconds === undefined || seconds === '') return { iso: undefined, text: '—' }
  const value = Number(seconds)
  const date = new Date(value * 1000)
  if (!Number.isFinite(value) || value <= 0 || !Number.isFinite(date.getTime())) return { iso: undefined, text: '—' }
  const iso = date.toISOString()
  try {
    return { iso, text: new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'medium', timeZone: 'UTC' }).format(date) + ' UTC' }
  } catch {
    return { iso, text: iso }
  }
}

export function filterAuditEvents(events, query) {
  const needle = String(query || '').trim().toLowerCase()
  if (!needle) return events
  return events.filter(event => [event.id, event.action, event.owner, event.actorId, event.outcome,
    event.correlationId, event.targetType, event.targetId, event.scopeType, event.scopeId,
    auditTimestamp(event.occurredAt, 'en').iso]
    .some(value => value !== null && value !== undefined && String(value).toLowerCase().includes(needle)))
}
