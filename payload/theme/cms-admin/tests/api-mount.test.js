import test from 'node:test'
import assert from 'node:assert/strict'
import { api, apiBase } from '../runtime/common.mjs'

function setBoot(value) {
  globalThis.window = { __PINOOX__: value }
}

test('runtime API base uses server-provided app-mounted URL', () => {
  setBoot({ cmsAdmin: { mountPath: '/qwe', apiBase: '/qwe/api/v1/cms' } })
  assert.equal(apiBase(), '/qwe/api/v1/cms')
})

test('runtime API base falls back to app mount when explicit base is unavailable', () => {
  setBoot({ cmsAdmin: { mountPath: '/qwe' } })
  assert.equal(apiBase(), '/qwe/api/v1/cms')
})

test('runtime API request never escapes the active app mount', async () => {
  setBoot({ cmsAdmin: { mountPath: '/qwe', apiBase: '/qwe/api/v1/cms' } })
  let requested = null
  globalThis.fetch = async (url) => {
    requested = String(url)
    return {
      ok: true,
      status: 200,
      async json() { return { success: true, data: { items: [] } } },
    }
  }
  const result = await api('/content?limit=100')
  assert.deepEqual(result, { items: [] })
  assert.equal(requested, '/qwe/api/v1/cms/content?limit=100')
})
