import test from 'node:test'
import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')
const sha256 = (value) => createHash('sha256').update(value).digest('hex')

test('release version has one Pinoox-native source of truth and matching evidence', () => {
  const app = readPackage('app.php')
  const release = readPackage('Cms/Support/CmsRelease.php')
  const evidence = JSON.parse(readPackage('resources/release/release-metadata-v1.json'))

  const versionName = app.match(/'version-name'\s*=>\s*'([^']+)'/)?.[1]
  const versionCode = Number(app.match(/'version-code'\s*=>\s*(\d+)/)?.[1] || 0)
  assert.ok(versionName, 'app.php must expose version-name')
  assert.ok(versionCode > 0, 'app.php must expose version-code')
  assert.doesNotMatch(release, /public const VERSION\b/)
  assert.match(release, /dirname\(__DIR__, 2\) \. '\/app\.php'/)
  assert.equal(evidence.version_name, versionName)
  assert.equal(evidence.version_code, versionCode)
  assert.equal(evidence.app_sha256, sha256(app))
})

test('packaged documentation audit is explicit and fail-closed without source evidence', () => {
  const manifestRaw = readPackage('resources/docs/documentation-manifest-v1.json')
  const manifest = JSON.parse(manifestRaw)
  const evidence = JSON.parse(readPackage('resources/docs/source-audit-evidence-v1.json'))
  const gate = readPackage('Cms/Release/ProductionReleaseGate.php')
  const audit = readPackage('Cms/Documentation/DocumentationAuditService.php')

  assert.equal(manifest.distribution.source_docs_packaged, false)
  assert.equal(manifest.distribution.artifact_requires_source_audit, true)
  assert.equal(manifest.distribution.artifact_evidence, 'resources/docs/source-audit-evidence-v1.json')
  assert.ok(Array.isArray(manifest.release_blockers) && manifest.release_blockers.length > 0)
  assert.equal(evidence.status, 'not_available')
  assert.equal(evidence.documentation_manifest_sha256, sha256(manifestRaw))
  assert.match(audit, /docs\.source_audit_not_passed/)
  assert.match(gate, /release\.declared_blockers_present/)
  assert.match(gate, /RELEASE_METADATA_PATH/)
})
