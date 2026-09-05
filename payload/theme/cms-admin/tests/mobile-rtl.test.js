import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const css = readFileSync(new URL('../src/styles/admin.scss', import.meta.url), 'utf8')

test('Admin styles include mobile-first breakpoints and logical sizing', () => {
  assert.match(css, /@media \(min-width:/)
  assert.match(css, /min-inline-size:/)
  assert.match(css, /min-block-size:/)
  assert.match(css, /padding-inline/)
  assert.match(css, /margin-inline/)
})

test('Admin styles include reduced-motion accessibility handling', () => {
  assert.match(css, /@media \(prefers-reduced-motion: reduce\)/)
})
