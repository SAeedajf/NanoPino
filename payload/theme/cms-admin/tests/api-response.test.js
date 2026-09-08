import test from 'node:test'
import assert from 'node:assert/strict'
import { cmsRequest } from '../src/services/cms-api.js'
import { api } from '../runtime/common.mjs'
import { performAdminAction } from '../src/services/admin-provider.js'

const adapters = { vue: () => cmsRequest('/test'), runtime: () => api('/test'), action: () => performAdminAction('test') }
for (const [name, run] of Object.entries(adapters)) {
  test(`${name}: malformed successful response cannot acknowledge an operation`, async t => {
    t.mock.method(globalThis, 'fetch', async () => new Response('<html>login</html>', { status: 200 }))
    const before = globalThis.window
    globalThis.window = { __PINOOX__: { cmsAdmin: { actions: { test: '/test' } } } }
    try { await assert.rejects(run, e => e.code === 'CMS_INVALID_RESPONSE' && e.status === 200 && !e.message.includes('<html>')) }
    finally { globalThis.window = before }
  })
  test(`${name}: server diagnostics survive without exposing detail payloads`, async t => {
    const before = globalThis.window
    globalThis.window = { __PINOOX__: { cmsAdmin: { actions: { test: '/test' } } } }
    const details = { error_id: 'cms-123', usages: [4], private: 'do-not-display' }
    t.mock.method(globalThis, 'fetch', async () => new Response(JSON.stringify({ success: false, error: { message: 'In use', code: 'MEDIA_IN_USE', details } }), { status: 409, headers: { 'X-Correlation-ID': 'trace-456' } }))
    try { await assert.rejects(run, e => e.code === 'MEDIA_IN_USE' && e.status === 409 && e.errorId === 'cms-123' && e.correlationId === 'trace-456' && e.details.usages[0] === 4 && e.message.includes('cms-123') && !e.message.includes('do-not-display')) }
    finally { globalThis.window = before }
  })
}
test('empty 204 succeeds; null/primitive 200 fails; valid data remains unchanged', async t => {
  for (const body of ['null', 'true', '"ok"']) {
    t.mock.method(globalThis, 'fetch', async () => new Response(body))
    await assert.rejects(() => cmsRequest('/test'), { code: 'CMS_INVALID_RESPONSE' })
  }
  t.mock.method(globalThis, 'fetch', async () => new Response(null, { status: 204 }))
  assert.equal((await cmsRequest('/test')).data, null)
  t.mock.method(globalThis, 'fetch', async () => new Response('{"success":true,"data":{"id":7}}'))
  assert.deepEqual((await cmsRequest('/test')).data, { id: 7 })
})
test('non-JSON HTTP failure retains status and safe response correlation', async t => {
  t.mock.method(globalThis, 'fetch', async () => new Response('private server trace', {status: 502, headers: {'X-Correlation-ID': 'req-9'}}))
  await assert.rejects(() => cmsRequest('/test'), e => e.status === 502 && e.correlationId === 'req-9' && !e.message.includes('private'))
})
