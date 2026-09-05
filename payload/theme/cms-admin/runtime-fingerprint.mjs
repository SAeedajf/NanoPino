import { createHash } from 'node:crypto'
import { existsSync, lstatSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs'
import { join, relative, resolve, sep } from 'node:path'

const root = resolve('.')
const runtimeRoot = resolve(root, 'runtime')
const files = []
const normalize = (path) => path.split(sep).join('/')

function collect(directory) {
  for (const name of readdirSync(directory)) {
    const path = join(directory, name)
    const stat = lstatSync(path)
    if (stat.isSymbolicLink()) throw new Error(`Symlink is not allowed in Admin runtime: ${path}`)
    if (stat.isDirectory()) collect(path)
    else if (stat.isFile()) files.push(normalize(relative(root, path)))
  }
}

if (!existsSync(runtimeRoot)) throw new Error('Admin runtime module directory is missing.')
collect(runtimeRoot)
const paths = [...new Set(files)].sort()
if (paths.length === 0) throw new Error('Admin runtime module directory is empty.')

const aggregate = createHash('sha256')
for (const file of paths) {
  if (file.startsWith('/') || file.includes('../') || file.includes('\\')) throw new Error(`Unsafe runtime path: ${file}`)
  const fileHash = createHash('sha256').update(readFileSync(resolve(root, file))).digest('hex')
  aggregate.update(`${file}\0${fileHash}\n`)
}

const payload = {
  schema: 1,
  algorithm: 'sha256-path-filehash-v1',
  runtimeFingerprint: aggregate.digest('hex'),
  runtimeFiles: paths.length,
}
const output = resolve(root, '../../resources/release/admin-runtime-v1.json')
mkdirSync(resolve(root, '../../resources/release'), { recursive: true })
writeFileSync(output, `${JSON.stringify(payload, null, 2)}\n`, 'utf8')
console.log(`Runtime fingerprint: ${payload.runtimeFingerprint}`)
console.log(`Runtime files: ${payload.runtimeFiles}`)
