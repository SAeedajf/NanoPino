import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const appRoot = resolve(root, '../..')
const read = (path) => readFileSync(resolve(root, path), 'utf8')

test('Phase 18 evidence is explicit about source, browser and target boundaries', () => {
  const evidence = JSON.parse(readFileSync(resolve(appRoot, 'resources/release/uiux-wcag-browser-matrix-phase18-v1.json'), 'utf8'))
  assert.equal(evidence.verification.frontend_tests, '208/208')
  assert.equal(evidence.verification.php_tests, '72/72')
  assert.equal(evidence.verification.source_dist_parity, true)
  assert.equal(evidence.target.authenticated_admin_e2e, 'pending')
  assert.equal(evidence.gate.production_ready, false)
  assert.deepEqual(evidence.browser_matrix.map((item) => item.result), ['PASS', 'PASS', 'PASS', 'PASS'])
})

test('Phase 18 preserves the CMS accessibility contracts in source', () => {
  const main = read('src/main.js')
  const css = read('src/styles/admin.scss')
  const twig = read('main.twig')
  assert.match(main, /focusMainContent\(\)/)
  assert.match(main, /preventScroll: true/)
  assert.match(css, /--cms-touch-target:\s*44px/)
  assert.match(css, /--cms-success:\s*#047857/)
  assert.match(css, /--cms-warning:\s*#b45309/)
  assert.match(css, /--cms-danger:\s*#b91c1c/)
  assert.match(css, /--cms-success:\s*#34d399/)
  assert.match(css, /text-size-adjust:\s*100%/)
  assert.match(css, /prefers-reduced-motion: reduce/)
  assert.match(twig, /meta name="viewport" content="width=device-width, initial-scale=1/)
  assert.doesNotMatch(twig, /user-scalable=no|max(?:imum|imum)-scale:\s*1\.0/)
})
