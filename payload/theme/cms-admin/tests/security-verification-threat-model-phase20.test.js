import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../..', import.meta.url)))
const read = (path) => readFileSync(resolve(packageRoot, path), 'utf8')

test('Phase 20 threat model covers trust boundaries and every registered threat', () => {
  const model = JSON.parse(read('resources/release/security-threat-model-phase20-v1.json'))
  const ids = model.threats.map((threat) => threat.id)

  assert.equal(model.scope.product, 'com_pinoox_cms')
  assert.ok(model.trust_boundaries.length >= 5)
  assert.equal(model.threats.length, 11)
  assert.equal(new Set(ids).size, ids.length)
  for (const threat of model.threats) {
    assert.match(threat.severity, /^(critical|high|medium|low)$/)
    assert.ok(threat.control)
    assert.ok(threat.verification)
    assert.ok(threat.residual_risk)
  }
  assert.equal(model.gate.production_ready, false)
})

test('Phase 20 mutation route manifest requires throttle and CSRF flows', () => {
  const manifest = read('Cms/Runtime/CmsRuntimeApiManifest.php')

  assert.match(manifest, /if \(!is_array\(\$route\['flow'\]\)[\s\S]*throttle:/)
  assert.match(manifest, /if \(!in_array\(\$method, \['GET', 'HEAD', 'OPTIONS'\][\s\S]*cms_csrf/)
  assert.match(manifest, /CoreCapabilities::definitions\(\)/)
  assert.match(manifest, /sprintf\('CMS API mutation %s is missing cms_csrf\.'/)
})

test('Phase 20 session mutation policy is subject-bound and same-origin only', () => {
  const policy = read('Cms/Security/Http/RequestIntegrityPolicy.php')
  const csrf = read('Cms/Security/Http/PinooxSessionCsrfTokenManager.php')
  const flow = read('Cms/Security/Http/CmsRequestIntegrityFlow.php')

  assert.match(policy, /AuthMechanism::Anonymous[\s\S]*AUTH_REQUIRED/)
  assert.match(policy, /!\$context->sameOrigin[\s\S]*ORIGIN_MISMATCH/)
  assert.match(policy, /!\$this->csrf->verify\(\$context->csrfToken, \$context->subjectId\)/)
  assert.match(csrf, /hash_equals\(\$expected,\$token\)/)
  assert.match(csrf, /subject_id/)
  assert.match(flow, /sameOrigin\(\$request\)/)
})

test('Phase 20 output, network and package boundaries remain fail-closed', () => {
  const sanitizer = read('Cms/Security/Output/StrictRichTextSanitizer.php')
  const ssrf = read('Cms/Security/Network/SsrfGuard.php')
  const packagePreflight = read('Cms/Security/Package/PinooxPinxPackagePreflight.php')
  const trust = read('Cms/ExtensionCenter/Trust/PackageTrustEvaluator.php')

  assert.match(sanitizer, /DROP_WITH_CONTENT/)
  assert.match(sanitizer, /safeHref/)
  assert.match(ssrf, /allowedHosts/)
  assert.match(ssrf, /FILTER_FLAG_NO_PRIV_RANGE \| FILTER_FLAG_NO_RES_RANGE/)
  assert.match(packagePreflight, /CanonicalPackagePath::normalize/)
  assert.match(packagePreflight, /PackageResourceGuard/)
  assert.match(trust, /PackageTrustLevel::Failed/)
})

test('Phase 20 response boundary emits defensive headers and redacts credentials', () => {
  const headers = read('Cms/Security/Http/SecurityHeadersPolicy.php')
  const csp = read('Cms/Security/Http/CspPolicy.php')
  const redactor = read('Cms/Security/Secrets/SensitiveDataRedactor.php')
  const listener = read('Cms/Security/Http/PinooxSecurityResponseListener.php')

  for (const header of ['X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy', 'Cross-Origin-Opener-Policy', 'Cross-Origin-Resource-Policy']) {
    assert.match(headers, new RegExp(`['"]${header}['"]`))
  }
  assert.match(csp, /frame-ancestors/)
  assert.match(csp, /object-src/)
  assert.match(redactor, /private_key/)
  assert.match(redactor, /REDACTED_JWT/)
  assert.match(listener, /Cache-Control/, 'API denials must be non-cacheable')
})

test('Phase 20 evidence keeps target and signed lifecycle claims explicit', () => {
  const model = JSON.parse(read('resources/release/security-threat-model-phase20-v1.json'))
  const evidence = model.verification

  assert.equal(evidence.target_mutations, 'not executed')
  assert.match(evidence.phase19_pinx_preflight, /0 high findings/)
  assert.equal(model.gate.authenticated_target_e2e, false)
  assert.equal(model.gate.signed_target_lifecycle, false)
  assert.equal(model.gate.target_read_only_headers_verified, true)
})
