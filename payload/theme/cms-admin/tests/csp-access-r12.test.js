import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')
const readTheme = (relative) => readFileSync(resolve(themeRoot, relative), 'utf8')

test('R12 admin bootstrap is nonce-ready without modifying Pincore', () => {
  const controller = readPackage('Controller/AdminController.php')
  const scripts = readTheme('partials/scripts.twig')
  const shell = readTheme('main.twig')
  const policy = readPackage('Cms/Security/Http/CspPolicy.php')

  assert.match(controller, /PinooxScriptHelper::bootstrap\(\$viewData\['bootstrap'\]\)/)
  assert.match(controller, /cms_csp_nonce/)
  assert.match(controller, /JSON_HEX_TAG/)
  assert.match(controller, /JSON_HEX_AMP/)
  assert.doesNotMatch(scripts, /pinoox_bootstrap\(/)
  assert.match(scripts, /nonce="{{ cspNonce\|e\('html_attr'\) }}"/)
  assert.match(scripts, /window\.__PINOOX__/)
  assert.match(shell, /<style nonce="{{ cspNonce\|e\('html_attr'\) }}">/)
  assert.match(policy, /'script-src-attr' => \["'none'"\]/)
  assert.match(policy, /'style-src-attr' => \["'unsafe-inline'"\]/)
  assert.match(policy, /public bool \$reportOnly = true/)
})

test('R12 platform_super transition is audited instead of disabled or auto-granted', () => {
  const app = readPackage('app.php')
  const readiness = readPackage('Cms/Security/Access/PlatformSuperTransitionReadiness.php')
  const posture = readPackage('Cms/Security/Posture/SecurityPostureService.php')
  const securityApi = readPackage('Controller/Api/SecurityRuntimeApiController.php')

  assert.match(app, /'platform_super'\s*=>\s*true/)
  assert.match(readiness, /where\('app', Platform::PACKAGE\)/)
  assert.match(readiness, /group_key/)
  assert.match(readiness, /pluck\('role_key'\)/)
  assert.match(readiness, /implicit_only_accounts/)
  assert.match(readiness, /'ready' => \$platformSuper && \$total > 0 && \$implicitOnly === 0 && \$explicit > 0/)
  assert.doesNotMatch(readiness, /assignRole|syncWithoutDetaching|firstOrCreate/)
  assert.match(posture, /authorization\.platform_super/)
  assert.match(securityApi, /platform_super_transition/)
})

test('R12 security center surfaces platform super cutover readiness', () => {
  const page = readTheme('src/pages/system/page-security.vue')
  assert.match(page, /Platform Super Transition/)
  assert.match(page, /implicit_only_accounts/)
  assert.match(page, /Not safe to disable/)
  assert.match(page, /platform_super/)
})
