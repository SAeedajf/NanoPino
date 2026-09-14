import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const appRoot = resolve(root, '../..')
const read = (path) => readFileSync(resolve(root, path), 'utf8')
const readApp = (path) => readFileSync(resolve(appRoot, path), 'utf8')

test('phase 2 defines a semantic token bridge for Luma and PrimeVue', () => {
  const styles = read('src/styles/admin.scss')
  for (const token of [
    '--cms-primary', '--cms-surface-panel', '--cms-text', '--cms-border',
    '--cms-space-4', '--cms-radius-md', '--cms-focus-ring', '--cms-motion-fast',
  ]) {
    assert.match(styles, new RegExp(token.replaceAll('-', '\\-')))
  }
  assert.match(styles, /\[data-theme='dark'\]/)
  assert.match(styles, /--px-primary: var\(--cms-primary\)/)
  assert.match(styles, /--p-content-background: var\(--cms-surface-panel\)/)
})

test('phase 2 keeps the native Luma shell configured and responsive', () => {
  const theme = read('src/registry/theme-config.js')
  const routes = read('src/registry/admin-routes.js')
  const styles = read('src/styles/admin.scss')
  assert.match(theme, /sidebarWidth: '272px'/)
  assert.match(theme, /topbarHeight: '64px'/)
  assert.match(theme, /pageMaxWidth: '1440px'/)
  assert.doesNotMatch(theme, /primary:\s*'/)
  assert.match(routes, /component: PageLayout/)
  assert.match(styles, /\.px-layout__content > \*\s*\{/)
  assert.match(styles, /@media \(max-width: 30rem\)/)
})

test('phase 2 adds a localized skip link and preserves reduced motion', () => {
  const main = read('src/main.js')
  const styles = read('src/styles/admin.scss')
  for (const value of ['cms-skip-link', 'cms-main-content', 'skip_to_content']) {
    assert.match(main, new RegExp(value.replaceAll('-', '\\-')))
  }
  assert.match(styles, /@media \(prefers-reduced-motion: reduce\)/)
  assert.match(styles, /min-block-size: var\(--cms-touch-target\)/)
  for (const locale of ['fa', 'en']) {
    assert.match(readApp(`lang/${locale}/admin.lang.php`), /skip_to_content/)
  }
})
