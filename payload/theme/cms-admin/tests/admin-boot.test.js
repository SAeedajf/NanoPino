import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')

test('Admin boot awaits Luma and emits explicit lifecycle signals', () => {
  const main = read('src/main.js')
  assert.match(main, /await createApp\(/)
  assert.match(main, /pinoox-cms:boot-start/)
  assert.match(main, /pinoox-cms:boot-ready/)
  assert.match(main, /pinoox-cms:boot-failed/)
  assert.match(main, /cms\.admin\.runtime_boot_failed/)
})

test('SPA route changes move focus to the new main content without stealing scroll', () => {
  const main = read('src/main.js')
  assert.match(main, /function focusMainContent\(\)/)
  assert.match(main, /main\.focus\(\{ preventScroll: true \}\)/)
  assert.match(main, /afterEach\?\.\(\(to\) => \{[\s\S]*focusMainContent\(\)/)
})

test('server shell resolves locale/direction dynamically and never returns an empty app mount', () => {
  const twig = read('main.twig')
  assert.match(twig, /lang="\{\{ bootstrap\.locale\|default\('fa'\) \}\}"/)
  assert.match(twig, /dir="\{\{ bootstrap\.direction\|default\('rtl'\) \}\}"/)
  assert.match(twig, /data-cms-platform="nanoshell"/)
  assert.match(twig, /data-cms-platform-contract="nanoshell-platform-v1"/)
  assert.match(twig, /<div id="app">[\s\S]+<\/div>/)
  assert.match(twig, /cms\.admin\.javascript_disabled/)
})

test('boot guard provides visible runtime recovery without innerHTML', () => {
  const guard = read('public/admin-boot-guard.js')
  assert.match(guard, /pinoox-cms:boot-ready/)
  assert.match(guard, /pinoox-cms:boot-failed/)
  assert.match(guard, /window\.location\.reload\(\)/)
  assert.doesNotMatch(guard, /\.innerHTML\s*=/)
  assert.doesNotMatch(guard, /document\.write\s*\(/)
})
