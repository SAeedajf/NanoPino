import {
  existsSync,
  readFileSync,
  readdirSync,
  statSync,
} from 'node:fs'
import { extname, resolve } from 'node:path'


const buildMetadataPath = resolve('dist/.cms-build.json')
if (!existsSync(buildMetadataPath)) {
  throw new Error('Missing dist/.cms-build.json source-parity metadata.')
}
const buildMetadata = JSON.parse(readFileSync(buildMetadataPath, 'utf8'))
if (
  buildMetadata?.schema !== 1
  || buildMetadata?.algorithm !== 'sha256-path-filehash-v1'
  || !/^[a-f0-9]{64}$/.test(String(buildMetadata?.sourceFingerprint || ''))
  || !Number.isInteger(buildMetadata?.sourceFiles)
  || buildMetadata.sourceFiles < 1
) {
  throw new Error('Invalid dist/.cms-build.json source-parity metadata.')
}

const manifestPath = resolve('dist/.vite/manifest.json')
if (!existsSync(manifestPath)) {
  throw new Error('Missing dist/.vite/manifest.json')
}

const raw = readFileSync(manifestPath, 'utf8')
const manifest = JSON.parse(raw)

const entry = manifest['src/main.js']
if (!entry || entry.isEntry !== true || !entry.file) {
  throw new Error('Vite manifest does not contain a valid src/main.js entry.')
}

const visited = new Set()
const assets = new Set()

function walk(key) {
  if (visited.has(key)) return
  visited.add(key)

  const chunk = manifest[key]
  if (!chunk) throw new Error(`Missing manifest dependency: ${key}`)

  if (chunk.file) assets.add(chunk.file)
  for (const css of chunk.css || []) assets.add(css)
  for (const asset of chunk.assets || []) assets.add(asset)
  for (const dep of chunk.imports || []) walk(dep)
  for (const dep of chunk.dynamicImports || []) walk(dep)
}

walk('src/main.js')

for (const asset of assets) {
  if (
    asset.startsWith('/') ||
    asset.includes('..') ||
    asset.includes('\\')
  ) {
    throw new Error(`Unsafe/non-relative manifest asset path: ${asset}`)
  }

  const file = resolve('dist', asset)
  if (!existsSync(file) || !statSync(file).isFile()) {
    throw new Error(`Missing built asset: ${asset}`)
  }
}

// Inspect emitted JS/CSS. Root-domain "/assets/..." breaks Pinoox apps mounted
// below a path such as /qwe/.
const forbidden = [
  /(["'(`])\/assets\//g,
  /url\(\s*["']?\/assets\//g,
]

function allFiles(dir) {
  const result = []
  for (const name of readdirSync(dir)) {
    const file = resolve(dir, name)
    const stat = statSync(file)
    if (stat.isDirectory()) result.push(...allFiles(file))
    else result.push(file)
  }
  return result
}

for (const file of allFiles(resolve('dist'))) {
  if (!['.js', '.css'].includes(extname(file))) continue

  const text = readFileSync(file, 'utf8')
  for (const pattern of forbidden) {
    pattern.lastIndex = 0
    if (pattern.test(text)) {
      throw new Error(
        `Absolute /assets/ URL found in production bundle: ${file}`
      )
    }
  }
}

console.log(`OK: ${assets.size} production assets verified.`)
console.log('OK: no root-domain /assets/ references found in emitted JS/CSS.')
console.log('Manifest:', manifestPath)

console.log('Source fingerprint:', buildMetadata.sourceFingerprint)
console.log('Source files:', buildMetadata.sourceFiles)
