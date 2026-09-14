import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const root = new URL('../../../', import.meta.url)
const read = (path) => readFileSync(new URL(path, root), 'utf8')

test('admin boot publishes native effective abilities instead of role-only abilities', () => {
  const helper = read('Cms/Identity/PinooxEffectiveIdentity.php')
  const controller = read('Controller/AdminController.php')
  assert.match(helper, /AuthorizationRequest\(\$definition->identifier\(\)\)/)
  assert.match(helper, /\$user\['abilities'\]/)
  assert.match(controller, /PinooxEffectiveIdentity::withEffectiveAbilities/)
})

test('users API publishes the same effective ability envelope used by the admin boot', () => {
  const controller = read('Controller/Api/UserRuntimeApiController.php')
  assert.match(controller, /PinooxEffectiveIdentity::withEffectiveAbilities/)
  assert.match(controller, /'current'\s*=>\s*PinooxEffectiveIdentity/)
})
