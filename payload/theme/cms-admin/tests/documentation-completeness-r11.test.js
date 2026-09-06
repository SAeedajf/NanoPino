import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../../..')
const manifest = JSON.parse(readFileSync(resolve(packageRoot, 'payload/resources/docs/documentation-manifest-v1.json'), 'utf8'))

test('required NanoPino documentation paths are complete', () => {
  assert.equal(manifest.documentation_version, '0.23.29')
  assert.equal(manifest.required_docs.length, 31)
  const missing = manifest.required_docs.filter((path) => !existsSync(resolve(packageRoot, path)))
  assert.deepEqual(missing, [])
})
