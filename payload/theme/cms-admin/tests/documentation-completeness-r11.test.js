import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const repositoryRoot = resolve(packageRoot, '..')
const manifest = JSON.parse(readFileSync(resolve(packageRoot, 'resources/docs/documentation-manifest-v1.json'), 'utf8'))
const app = readFileSync(resolve(packageRoot, 'app.php'), 'utf8')

test('required NanoPino documentation paths are complete', () => {
  const appVersion = app.match(/'version-name'\s*=>\s*'([^']+)'/)?.[1]
  assert.equal(manifest.documentation_version, appVersion)
  assert.equal(manifest.required_docs.length, 33)
  const missing = manifest.required_docs.filter((path) => !existsSync(resolve(repositoryRoot, path)))
  assert.deepEqual(missing, [])
})
