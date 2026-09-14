import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../../', import.meta.url)))
const read = (path) => readFileSync(resolve(packageRoot, path), 'utf8')

test('Auth identity crosses the server boot, users API, and client provider consistently', () => {
  const actor = read('Cms/Runtime/RuntimeActor.php')
  const identity = read('Cms/Identity/PinooxEffectiveIdentity.php')
  const controller = read('Controller/AdminController.php')
  const usersApi = read('Controller/Api/UserRuntimeApiController.php')
  const provider = read('theme/cms-admin/src/services/admin-provider.js')

  assert.match(actor, /Auth::boot\(\)/)
  assert.match(actor, /return \$id > 0 \? \$id : null/)
  assert.match(identity, /AuthorizationRequest\(\$definition->identifier\(\)/)
  assert.match(controller, /'authState'\s*=>\s*\$runtimeActorId !== null \? 'authenticated' : 'anonymous'/)
  assert.match(controller, /'currentUser'\s*=>\s*\$currentUser/)
  assert.match(usersApi, /PinooxEffectiveIdentity::withEffectiveAbilities/)
  assert.match(provider, /authState === 'anonymous'\) return false/)
})

test('session CSRF is subject-bound, time-limited, and sent on every client mutation', () => {
  const csrf = read('Cms/Security/Http/PinooxSessionCsrfTokenManager.php')
  const integrity = read('Cms/Security/Http/CmsRequestIntegrityFlow.php')
  const policy = read('Cms/Security/Http/RequestIntegrityPolicy.php')
  const api = read('theme/cms-admin/src/services/cms-api.js')

  assert.match(csrf, /SESSION_KEY='cms\.csrf\.v1',TTL=7200/)
  assert.match(csrf, /\$row\['subject_id'\].*\$subjectId/)
  assert.match(csrf, /hash_equals\(\$expected,\$token\)/)
  assert.match(integrity, /Auth::boot\(\)/)
  assert.match(integrity, /X-CSRF-TOKEN/)
  assert.match(integrity, /cms_subject_id/)
  assert.match(policy, /AuthMechanism::Anonymous.*AUTH_REQUIRED/s)
  assert.match(policy, /sameOrigin.*ORIGIN_MISMATCH/s)
  assert.match(policy, /csrf->verify\(\$context->csrfToken, \$context->subjectId\)/)
  assert.match(api, /credentials:'same-origin'/)
  assert.match(api, /X-CSRF-TOKEN/)
})

test('role and capability enforcement remains native, scoped, and fail-closed', () => {
  const templates = read('Cms/Identity/CoreRoleTemplates.php')
  const provisioner = read('Cms/Identity/PinooxRoleTemplateProvisioner.php')
  const authorization = read('Cms/Authorization/AuthorizationManager.php')
  const scope = read('Cms/Authorization/SingleSiteScopeGuard.php')
  const gateway = read('Cms/Authorization/PinooxAccessGateway.php')
  const lifecycle = read('lifecycle.php')

  for (const capability of ['cms.admin', 'content.read', 'content.create', 'builder.publish', 'users.read']) {
    assert.match(templates, new RegExp(capability.replace('.', '\\.'), 's'))
  }
  assert.match(provisioner, /Access::givePermissionToRole/)
  assert.doesNotMatch(provisioner, /assignRole\(/)
  assert.match(authorization, /has\(\$request->capability\)/)
  assert.match(authorization, /\$this->scopeGuard->allows/)
  assert.match(scope, /\$this->siteId/)
  assert.match(gateway, /Access::can\(/)
  assert.match(lifecycle, /'install'\s*=>\s*\$provisionRoleTemplates/)
  assert.match(lifecycle, /'update'\s*=>\s*\$provisionRoleTemplates/)
})

test('user role/session routes carry explicit capability and integrity metadata', () => {
  const manifest = read('Cms/Runtime/CmsRuntimeApiManifest.php')
  const users = read('Controller/Api/UserRuntimeApiController.php')

  for (const capability of [
    'users.read',
    'users.create',
    'users.update',
    'users.roles.manage',
    'users.sessions.revoke',
    'users.delete',
  ]) {
    assert.match(manifest, new RegExp("'" + capability.replace('.', '\\.') + "'"))
  }
  assert.match(manifest, /cms_csrf/)
  assert.match(users, /revokeSessions/)
  assert.match(users, /assignRole/)
})
