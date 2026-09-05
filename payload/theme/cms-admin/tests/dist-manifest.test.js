import test from 'node:test'
import assert from 'node:assert/strict'
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs'
import { extname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = resolve(fileURLToPath(new URL('..', import.meta.url)))
const dist = resolve(root, 'dist')
const manifest = JSON.parse(readFileSync(resolve(dist, '.vite/manifest.json'), 'utf8'))

test('dist has a valid main entry and all reachable assets', () => {
  const entry = manifest['src/main.js']
  assert.equal(entry?.isEntry, true)
  assert.ok(entry?.file)
  const visited = new Set()
  const assets = new Set()
  const walk = (key) => {
    if (visited.has(key)) return
    visited.add(key)
    const chunk = manifest[key]
    assert.ok(chunk, `missing manifest dependency ${key}`)
    if (chunk.file) assets.add(chunk.file)
    for (const k of ['css','assets']) for (const file of chunk[k] || []) assets.add(file)
    for (const k of ['imports','dynamicImports']) for (const dep of chunk[k] || []) walk(dep)
  }
  walk('src/main.js')
  assert.ok(assets.size >= 20)
  for (const asset of assets) {
    assert.ok(!asset.startsWith('/') && !asset.includes('..') && !asset.includes('\\'))
    assert.equal(existsSync(resolve(dist, asset)), true, asset)
  }
})

test('compiled JS/CSS never use root-domain /assets paths', () => {
  const files = []
  const collect = (dir) => {
    for (const name of readdirSync(dir)) {
      const file = resolve(dir, name)
      const stat = statSync(file)
      if (stat.isDirectory()) collect(file)
      else files.push(file)
    }
  }
  collect(dist)
  for (const file of files.filter((f) => ['.js','.css'].includes(extname(f)))) {
    const text = readFileSync(file, 'utf8')
    assert.doesNotMatch(text, /(["'(`])\/assets\//, file)
    assert.doesNotMatch(text, /url\(\s*["']?\/assets\//, file)
  }
})
