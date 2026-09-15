import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const repositoryRoot = resolve(packageRoot, '..')
const readPayload = (path) => readFileSync(resolve(packageRoot, path), 'utf8')
const readRepository = (path) => readFileSync(resolve(repositoryRoot, path), 'utf8')

test('Phase 21 RC evidence covers every canonical phase through 21', () => {
  const evidence = JSON.parse(readPayload('resources/release/release-candidate-phase21-v1.json'))
  assert.equal(evidence.release.package, 'com_pinoox_cms')
  assert.equal(evidence.release.version, '0.23.74')
  assert.equal(evidence.release.version_code, 2374)
  assert.deepEqual(evidence.phase_coverage.completed_source_phases, Array.from({ length: 21 }, (_, i) => i + 1))
  assert.equal(evidence.release_gate.local_technical_candidate, true)
  assert.equal(evidence.release_gate.stable, false)
})

test('Phase 21 RC keeps package metadata and signed lifecycle boundaries explicit', () => {
  const evidence = JSON.parse(readPayload('resources/release/release-candidate-phase21-v1.json'))
  const manifest = readRepository('manifest.json')
  const app = readPayload('app.php')
  const gate = readPayload('Cms/Release/ProductionReleaseGate.php')

  assert.match(manifest, /com_pinoox_cms/)
  assert.match(manifest, /0\.23\.93/)
  assert.match(app, /'version-name' => '0\.23\.93'/)
  assert.match(app, /'version-code' => 2393/)
  assert.match(gate, /verifyReleaseMetadata/)
  assert.match(gate, /declared_release_blockers_present|release\.declared_blockers_present/)
  assert.equal(evidence.release.signed, true)
})

test('package inspector preserves CMS profile metadata from raw verified manifest JSON', () => {
  const inspector = readPayload('Cms/ExtensionCenter/Package/PinooxPinxPackageInspector.php')
  assert.match(inspector, /json_decode\(\$reader->manifestJson\(\), true, 512, JSON_THROW_ON_ERROR\)/)
  assert.match(inspector, /fromPinxArray\(\$raw\)/)
  assert.match(inspector, /toArray\(\) is the native transport projection/)
  assert.match(inspector, /payload\/manifest\.json/)
})

test('Phase 21 RC records all local integrity and parity gates', () => {
  const evidence = JSON.parse(readPayload('resources/release/release-candidate-phase21-v1.json'))
  const verification = evidence.verification

  assert.equal(verification.frontend_tests, '235/235')
  assert.equal(verification.php_tests, '35/35')
  assert.equal(verification.documentation_audit.status, 'passed')
  assert.equal(verification.vite_build, true)
  assert.equal(verification.reachable_assets, 62)
  assert.equal(verification.admin_source_dist_parity, true)
  assert.equal(verification.admin_runtime_parity, true)
  assert.equal(verification.release_metadata_parity, true)
  assert.equal(verification.archive_integrity, 'verified after canonical signed csp-nonce-fix build')
  assert.equal(verification.signature_verification, 'PASS via native PinxVerifier with require_signature')
})

test('Phase 21 RC carries forward security and performance evidence without overclaiming', () => {
  const evidence = JSON.parse(readPayload('resources/release/release-candidate-phase21-v1.json'))
  const matrix = readRepository('docs/audit/2026-09-12-phase-matrix.md')

  assert.equal(evidence.verification.phase20_security_preflight.high_findings, 0)
  assert.equal(evidence.phase_coverage.phase_19_performance_budgets, true)
  assert.match(matrix, /\| 19 \| Performance and Scalability \|/)
  assert.match(matrix, /\| 20 \| Security Verification and Threat Model \|/)
  assert.match(matrix, /\| 21 \| Final Release Candidate \|/)
})

test('Phase 21 target boundary is read-only and stable remains blocked', () => {
  const evidence = JSON.parse(readPayload('resources/release/release-candidate-phase21-v1.json'))
  const blockers = evidence.release_gate.declared_open_blockers

  assert.match(evidence.verification.target_mutations, /^PASS; target accepted signed 0\.23\.74 update/)
  assert.match(evidence.target_read_only.deployment, /^PASS — Manager UI reports update success;/)
  assert.equal(blockers.length, 9)
  assert.ok(blockers.includes('signed PINX lifecycle E2E'))
  assert.ok(blockers.includes('target database migration evidence'))
  assert.ok(blockers.includes('production security verification'))
  assert.ok(blockers.includes('production performance evidence'))
})
