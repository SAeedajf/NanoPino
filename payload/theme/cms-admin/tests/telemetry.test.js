import test from 'node:test'
import assert from 'node:assert/strict'
import { configureTelemetry, trackPage } from '../src/services/telemetry.js'

test('telemetry remains silent until it is explicitly enabled with consent', () => {
  const previousWindow = globalThis.window
  const calls = []
  globalThis.window = {
    location: { origin: 'https://admin.example.test', pathname: '/nano' },
    dataLayer: [],
    _paq: [],
    _mtm: [],
    gtag: (...args) => calls.push(args),
  }

  try {
    configureTelemetry({ enabled: false, consent: true, ga4MeasurementId: 'G-TEST', googleTagManagerId: 'GTM-TEST', matomoSiteId: '1', matomoTagManagerId: 'MTM-TEST' })
    trackPage('/content?token=hidden', 'Content')
    assert.equal(calls.length, 0)
    assert.deepEqual(window.dataLayer, [])
    assert.deepEqual(window._paq, [])
    assert.deepEqual(window._mtm, [])
  } finally {
    globalThis.window = previousWindow
  }
})

test('opt-in providers receive only the pathname', () => {
  const previousWindow = globalThis.window
  const calls = []
  globalThis.window = {
    location: { origin: 'https://admin.example.test', pathname: '/nano' },
    dataLayer: [],
    _paq: [],
    _mtm: [],
    gtag: (...args) => calls.push(args),
  }

  try {
    configureTelemetry({ enabled: true, consent: true, ga4MeasurementId: 'G-TEST', googleTagManagerId: 'GTM-TEST', matomoSiteId: '1', matomoTagManagerId: 'MTM-TEST' })
    trackPage('/content?token=hidden', 'Content')
    assert.equal(calls[0][2].page_path, '/content')
    assert.equal(window.dataLayer[0].page_path, '/content')
    assert.deepEqual(window._paq.slice(0, 2), [['setCustomUrl', '/content'], ['setDocumentTitle', 'Content']])
    assert.equal(window._mtm[0].page_path, '/content')
  } finally {
    configureTelemetry({ enabled: false, consent: false })
    globalThis.window = previousWindow
  }
})
