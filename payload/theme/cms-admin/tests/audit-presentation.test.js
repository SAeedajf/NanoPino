import test from 'node:test'
import assert from 'node:assert/strict'
import { auditTimestamp, filterAuditEvents } from '../src/services/audit-presentation.js'

test('audit seconds retain exact UTC incident time', () => {
  assert.equal(auditTimestamp(1788739200, 'en').iso, '2026-09-07T00:00:00.000Z')
  assert.match(auditTimestamp(1788739200, 'fa').text, /UTC$/)
})
test('missing and invalid incident time never imply epoch or now', () => {
  for (const value of [null, undefined, '', 0, 'invalid', Infinity]) assert.equal(auditTimestamp(value).text, '—')
})
test('investigator can locate event by actor, scope, correlation and UTC date', () => {
  const events = [{ id: 'event-a', actorId: 42, scopeId: 'shop', correlationId: 'trace-xyz', occurredAt: 1788739200 }, { id: 'event-b', actorId: 9 }]
  for (const query of ['42', 'SHOP', ' trace-xyz ', '2026-09-07']) assert.deepEqual(filterAuditEvents(events, query), [events[0]])
  assert.deepEqual(filterAuditEvents(events, 'absent'), [])
  assert.deepEqual(filterAuditEvents(events, ''), events)
})
