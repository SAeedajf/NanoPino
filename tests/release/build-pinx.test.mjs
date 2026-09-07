import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, copyFileSync, existsSync, rmSync, readdirSync, symlinkSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { spawnSync } from 'node:child_process'

// Exercise the real shell orchestration. Native PHP/PINX behavior is covered by
// the separate Pincore lifecycle matrix; these controlled doubles inject failures.
function fixture(t) {
  const root = mkdtempSync(join(tmpdir(), 'nanopino build '))
  t.after(() => rmSync(root, { recursive: true, force: true }))
  const repo = join(root, 'source'), native = join(root, 'pinoox'), bin = join(root, 'bin')
  for (const path of [join(repo, 'tools/release'), join(repo, 'payload'), native, bin]) mkdirSync(path, { recursive: true })
  const script = join(repo, 'tools/release/build-pinx.sh')
  copyFileSync(new URL('../../tools/release/build-pinx.sh', import.meta.url), script)
  const executable = (path, contents) => writeFileSync(path, `#!/usr/bin/env bash\nset -eu\n${contents}\n`, { mode: 0o755 })
  executable(join(repo, 'tools/release/verify-source.sh'), 'exit 0')
  writeFileSync(join(native, 'pinoox'), '')
  executable(join(bin, 'rsync'), 'exit 0')
  executable(join(bin, 'php'), `
if [[ "$1" == -r ]]; then
  if [[ "$2" == *version-name* ]]; then echo 0.23.29; else echo 2329; fi
elif [[ "$1" == pinoox && "$2" == pinx:build ]]; then
  for arg in "$@"; do
    if [[ "$arg" == --output=* ]]; then printf 'candidate' > "\${arg#--output=}"; fi
  done
  [[ "\${FAIL_STAGE:-}" != build ]]
elif [[ "$1" == pinoox && "$2" == pinx:info ]]; then
  [[ "\${FAIL_STAGE:-}" != info ]]
else
  [[ "\${FAIL_STAGE:-}" != verify ]]
fi`)
  const run = (output, fail = '') => spawnSync('bash', [script, native, output], {
    cwd: root, encoding: 'utf8', env: { ...process.env, PATH: `${bin}:${process.env.PATH}`, PHP_BIN: join(bin, 'php'), FAIL_STAGE: fail },
  })
  return { root, repo, native, run }
}

test('relative output with spaces is published relative to caller and staging is cleaned', (t) => {
  const f = fixture(t)
  const result = f.run('output files/release.pinx')
  assert.equal(result.status, 0, result.stderr)
  assert.equal(readFileSync(join(f.root, 'output files/release.pinx'), 'utf8'), 'candidate')
  assert.deepEqual(readdirSync(join(f.root, 'output files')), ['release.pinx'])
  assert.equal(existsSync(join(f.native, 'apps/com_pinoox_cms')), false)
})

for (const stage of ['build', 'info', 'verify']) {
  test(`${stage} failure preserves existing artifact and removes temporary files`, (t) => {
    const f = fixture(t)
    const output = join(f.root, 'release.pinx')
    writeFileSync(output, 'previous verified release')
    assert.notEqual(f.run(output, stage).status, 0)
    assert.equal(readFileSync(output, 'utf8'), 'previous verified release')
    assert.equal(existsSync(join(f.native, 'apps/com_pinoox_cms')), false)
    assert.equal(readdirSync(f.root).some(name => name.startsWith('.nanopino-build.')), false)
  })
}

test('rejects output within source or native build directories before staging', (t) => {
  const f = fixture(t)
  for (const output of [join(f.repo, 'release.pinx'), join(f.native, 'apps/com_pinoox_cms/release.pinx')]) {
    assert.notEqual(f.run(output).status, 0)
    assert.equal(existsSync(output), false)
  }
})

test('refuses symlink output and preserves its target', (t) => {
  const f = fixture(t), output = join(f.root, 'release.pinx'), target = join(f.root, 'original')
  writeFileSync(target, 'keep')
  symlinkSync(target, output)
  assert.notEqual(f.run(output).status, 0)
  assert.equal(readFileSync(target, 'utf8'), 'keep')
})

test('never cleans an existing application owned by another build', (t) => {
  const f = fixture(t), app = join(f.native, 'apps/com_pinoox_cms')
  mkdirSync(app, { recursive: true })
  writeFileSync(join(app, 'sentinel'), 'keep')
  assert.notEqual(f.run('release.pinx').status, 0)
  assert.equal(readFileSync(join(app, 'sentinel'), 'utf8'), 'keep')
})
