import { createHash } from 'node:crypto'
import {
  existsSync,
  lstatSync,
  readFileSync,
  readdirSync,
  writeFileSync,
} from 'node:fs'
import { join, relative, resolve, sep } from 'node:path'

const root = resolve('.')
// Runtime ES modules are served directly by Pinoox and are not Vite inputs.
// They have an independent release fingerprint (runtime-fingerprint.mjs).
const candidates = ['src', 'public']
const explicit = [
  'package.json',
  'vite.config.js',
  'verify-dist.mjs',
  'source-fingerprint.mjs',
  'runtime-fingerprint.mjs',
  'run-tests.mjs',
  'build-linux.sh',
  'build-windows.ps1',
]
const files = []

function normalize(path) {
  return path.split(sep).join('/')
}

function collect(directory) {
  const absolute = resolve(root, directory)
  if (!existsSync(absolute)) return

  for (const name of readdirSync(absolute)) {
    const path = join(absolute, name)
    const stat = lstatSync(path)
    if (stat.isSymbolicLink()) {
      throw new Error(`Symlink is not allowed in Admin source fingerprint: ${path}`)
    }
    if (stat.isDirectory()) collect(normalize(relative(root, path)))
    else if (stat.isFile()) files.push(normalize(relative(root, path)))
  }
}

for (const directory of candidates) collect(directory)
for (const file of explicit) {
  const path = resolve(root, file)
  if (existsSync(path)) {
    const stat = lstatSync(path)
    if (stat.isSymbolicLink() || !stat.isFile()) {
      throw new Error(`Invalid explicit Admin source file: ${file}`)
    }
    files.push(file)
  }
}

const paths = [...new Set(files)].sort()
const aggregate = createHash('sha256')

for (const file of paths) {
  if (
    file.startsWith('/')
    || file.includes('../')
    || file.includes('\\')
  ) {
    throw new Error(`Unsafe source path: ${file}`)
  }

  const fileHash = createHash('sha256')
    .update(readFileSync(resolve(root, file)))
    .digest('hex')

  aggregate.update(`${file}\0${fileHash}\n`)
}

const payload = {
  schema: 1,
  algorithm: 'sha256-path-filehash-v1',
  sourceFingerprint: aggregate.digest('hex'),
  sourceFiles: paths.length,
}

const metadataPath = resolve(root, 'dist/.cms-build.json')
const writeEvidence = process.argv.includes('--write-build-evidence')

if (writeEvidence) {
  if (!existsSync(resolve(root, 'dist/.vite/manifest.json'))) {
    throw new Error('Refusing to write build evidence before a Vite manifest exists.')
  }
  writeFileSync(metadataPath, `${JSON.stringify(payload, null, 2)}\n`, 'utf8')
  console.log('Build source evidence written.')
} else {
  if (!existsSync(metadataPath)) {
    throw new Error('Missing dist/.cms-build.json. Run the production build before release verification.')
  }
  const recorded = JSON.parse(readFileSync(metadataPath, 'utf8'))
  if (recorded?.sourceFingerprint !== payload.sourceFingerprint || recorded?.sourceFiles !== payload.sourceFiles) {
    throw new Error(`Admin source/dist parity mismatch. source=${payload.sourceFingerprint}/${payload.sourceFiles} dist=${recorded?.sourceFingerprint || 'missing'}/${recorded?.sourceFiles ?? 'missing'}`)
  }
  console.log('Source/dist parity verified.')
}

console.log(`Source fingerprint: ${payload.sourceFingerprint}`)
console.log(`Source files: ${payload.sourceFiles}`)
