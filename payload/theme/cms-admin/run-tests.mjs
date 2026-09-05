import { existsSync, readdirSync } from 'node:fs'
import { resolve } from 'node:path'
import { spawnSync } from 'node:child_process'

const testDir = resolve('tests')
if (!existsSync(testDir)) {
  console.error('ERROR: Admin test suite is missing: theme/cms-admin/tests')
  process.exit(2)
}

const tests = readdirSync(testDir)
  .filter((name) => name.endsWith('.test.js'))
  .sort()
  .map((name) => resolve(testDir, name))

if (tests.length === 0) {
  console.error('ERROR: Admin test suite contains zero *.test.js files.')
  process.exit(2)
}

const result = spawnSync(process.execPath, ['--test', ...tests], {
  stdio: 'inherit',
})

if (result.error) {
  console.error(result.error.message)
  process.exit(3)
}

process.exit(result.status ?? 1)
