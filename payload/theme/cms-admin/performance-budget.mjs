import { readFile, readdir, stat } from 'node:fs/promises'
import { join } from 'node:path'

const assetsDir = join(process.cwd(), 'dist', 'assets')
const entries = await readdir(assetsDir)
const assets = []

for (const name of entries) {
  if (!/\.(?:js|css)$/.test(name)) continue
  const size = (await stat(join(assetsDir, name))).size
  assets.push({ name, size, type: name.endsWith('.js') ? 'js' : 'css' })
}

const javascript = assets.filter((asset) => asset.type === 'js')
const styles = assets.filter((asset) => asset.type === 'css')
const totalJs = javascript.reduce((sum, asset) => sum + asset.size, 0)
const totalCss = styles.reduce((sum, asset) => sum + asset.size, 0)
const largestJs = Math.max(0, ...javascript.map((asset) => asset.size))
const largestCss = Math.max(0, ...styles.map((asset) => asset.size))
const manifest = JSON.parse(await readFile(join(process.cwd(), 'dist', '.vite', 'manifest.json'), 'utf8'))
const initialChunks = new Set()
const initialAssets = new Set()
const visitInitialChunk = (key) => {
  if (initialChunks.has(key)) return
  initialChunks.add(key)
  const chunk = manifest[key]
  if (!chunk) return
  if (chunk.file) initialAssets.add(chunk.file)
  for (const file of chunk.css || []) initialAssets.add(file)
  for (const dependency of chunk.imports || []) visitInitialChunk(dependency)
}
visitInitialChunk('src/main.js')
const initialJs = [...initialAssets]
  .filter((file) => file.endsWith('.js'))
  .reduce((sum, file) => sum + (assets.find((asset) => asset.name === file.replace(/^assets\//, ''))?.size || 0), 0)
const initialCss = [...initialAssets]
  .filter((file) => file.endsWith('.css'))
  .reduce((sum, file) => sum + (assets.find((asset) => asset.name === file.replace(/^assets\//, ''))?.size || 0), 0)
const budgets = {
  totalJsBytes: 2_200_000,
  totalCssBytes: 300_000,
  largestJsBytes: 750_000,
  largestCssBytes: 150_000,
  initialJsBytes: 1_200_000,
}

const failures = []
if (totalJs > budgets.totalJsBytes) failures.push(`total JS ${totalJs} > ${budgets.totalJsBytes}`)
if (totalCss > budgets.totalCssBytes) failures.push(`total CSS ${totalCss} > ${budgets.totalCssBytes}`)
if (largestJs > budgets.largestJsBytes) failures.push(`largest JS ${largestJs} > ${budgets.largestJsBytes}`)
if (largestCss > budgets.largestCssBytes) failures.push(`largest CSS ${largestCss} > ${budgets.largestCssBytes}`)
if (initialJs > budgets.initialJsBytes) failures.push(`initial JS ${initialJs} > ${budgets.initialJsBytes}`)

console.log(JSON.stringify({
  status: failures.length === 0 ? 'pass' : 'fail',
  raw_bytes: { javascript: totalJs, css: totalCss },
  initial: { javascript: initialJs, css: initialCss, files: initialAssets.size },
  largest: {
    javascript: javascript.toSorted((a, b) => b.size - a.size)[0] ?? null,
    css: styles.toSorted((a, b) => b.size - a.size)[0] ?? null,
  },
  budgets,
  failures,
}, null, 2))

if (failures.length) process.exitCode = 1
