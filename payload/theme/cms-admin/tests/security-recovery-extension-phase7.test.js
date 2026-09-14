import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const read = (file) => readFileSync(resolve(themeRoot, file), 'utf8')

test('Phase 7 security center is localized and exposes a busy boundary', () => {
  const page = read('src/pages/system/page-security.vue')
  assert.match(page, /aria-live="assertive"/)
  assert.match(page, /:aria-busy="loading \? 'true' : 'false'"/)
  assert.match(page, /security_page\.posture_title/)
  assert.match(page, /security_page\.runtime_gates/)
  assert.match(page, /security_page\.api_security_matrix/)
  assert.doesNotMatch(page, />Security Posture<|>Runtime Gates<|>Platform Super Transition<|>Rate Limit Profiles<|>API Security Matrix</)
})

test('Phase 7 recovery actions are confirmation-gated, single-flight, and recoverable', () => {
  const page = read('src/pages/recovery/page-recovery.vue')
  assert.match(page, /actionBusy = ref\(''\)/)
  assert.match(page, /Boolean\(actionBusy\)/)
  assert.match(page, /:loading="actionBusy === `restore:\$\{point\.id\}`"/)
  assert.match(page, /confirmed\(t\('recovery_page\.restore_confirm'/)
  assert.match(page, /role="alert" aria-live="assertive"/)
  assert.match(page, /dismiss_error/)
})

test('Phase 7 extension operations show progress and cannot overlap', () => {
  const page = read('src/pages/extensions/page-extensions.vue')
  assert.match(page, /inspecting=ref\(false\)/)
  assert.match(page, /role="status" aria-live="polite".*extensions_page\.inspecting/s)
  assert.match(page, /if\(!canInstall\|\|installing\.value\|\|inspecting\.value\|\|!approved\.value/)
  assert.match(page, /loading:busy\.value\.id===props\.item\.id/)
  assert.match(page, /extensions_page\.repair/)
  assert.match(page, /extensions_page\.delete/)
  assert.doesNotMatch(page, /\{default:\(\)=> 'Repair'\}|\{default:\(\)=> 'Uninstall'\}/)
})

test('Phase 7 runtime fallback keeps recovery and extension actions busy-safe', () => {
  const recovery = read('runtime/recovery.mjs')
  const extensions = read('runtime/extensions.mjs')
  assert.match(recovery, /busy: ''/)
  assert.match(recovery, /disabled: Boolean\(this\.busy\)/)
  assert.match(extensions, /busy:\{id:'',action:''\}/)
  assert.match(extensions, /disabled:!this\.file\|\|this\.installing\|\|!this\.capabilities\.install/)
})
