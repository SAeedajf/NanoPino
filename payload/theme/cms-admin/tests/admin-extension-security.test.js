import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/registry/admin-components.js', import.meta.url), 'utf8')

test('Admin extension loader is same-origin and traversal constrained', () => {
  assert.match(source, /raw\.startsWith\('\/'\)/)
  assert.match(source, /raw\.startsWith\('\/\/'\)/)
  assert.match(source, /raw\.includes\('\\\\'\)/)
  assert.match(source, /segments\.includes\('\.\.'\)/)
  assert.match(source, /url\.origin !== window\.location\.origin/)
})

test('Admin extension loader restricts module/export forms', () => {
  assert.match(source, /\\\.m\?js/)
  assert.match(source, /createComponent/)
  assert.match(source, /Invalid Extension Admin module export name/)
  assert.doesNotMatch(source, /eval\s*\(/)
  assert.doesNotMatch(source, /new Function\s*\(/)
})
