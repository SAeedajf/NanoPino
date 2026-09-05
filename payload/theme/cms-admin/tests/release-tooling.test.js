import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')

test('Vite is configured for relative Pinoox app mounts', () => {
  const vite = read('vite.config.js')
  assert.match(vite, /base:\s*['"]\.\/['"]/)
  assert.match(vite, /manifest:\s*true/)
  assert.match(vite, /input:\s*resolve\(process\.cwd\(\),\s*['"]src\/main\.js['"]\)/)
})

test('release build is fail-closed and verifies source parity', () => {
  const pkg = JSON.parse(read('package.json'))
  assert.equal(pkg.scripts.test, 'node run-tests.mjs')
  assert.match(pkg.scripts.build, /vite build/)
  assert.match(pkg.scripts.build, /source-fingerprint\.mjs/)
  assert.match(pkg.scripts.build, /verify-dist\.mjs/)
})

test('source fingerprint covers release-critical tooling', () => {
  const fp = read('source-fingerprint.mjs')
  for (const file of ['package.json','vite.config.js','verify-dist.mjs','source-fingerprint.mjs','run-tests.mjs','build-linux.sh','build-windows.ps1']) {
    assert.ok(fp.includes(`'${file}'`), `fingerprint should include ${file}`)
  }
})
