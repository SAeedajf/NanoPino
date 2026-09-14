import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')

test('Pinoox role templates are provisioned on install and update without auto-assigning users', () => {
  const lifecycle = readFileSync(resolve(packageRoot, 'lifecycle.php'), 'utf8')

  assert.match(lifecycle, /PinooxRoleTemplateProvisioner/)
  assert.match(lifecycle, /CmsKernel::boot\(\$context->package\)/)
  assert.match(lifecycle, /foreach \(\$kernel->roleTemplates->definitions\(\) as \$template\)/)
  assert.match(lifecycle, /'install'\s*=>\s*\$provisionRoleTemplates/)
  assert.match(lifecycle, /'update'\s*=>\s*\$provisionRoleTemplates/)
  assert.doesNotMatch(lifecycle, /assignRole\(/)
})
