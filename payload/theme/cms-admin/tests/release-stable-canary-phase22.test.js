import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const read = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')

test('phase 22 defines explicit Canary and Stable promotion channels', () => {
  const source = read('Cms/Release/ReleaseChannel.php')
  const policy = read('Cms/Release/ReleasePromotionPolicy.php')
  assert.match(source, /case Canary = 'canary'/)
  assert.match(source, /case Stable = 'stable'/)
  assert.match(policy, /stable\.canary_promotion_required/)
  assert.match(policy, /release\.signature_required/)
  assert.match(policy, /canary\.rollback_requested/)
})

test('phase 22 health policy is bounded and fail-closed', () => {
  const snapshot = read('Cms/Release/CanaryHealthSnapshot.php')
  const policy = read('Cms/Release/ReleasePromotionPolicy.php')
  assert.match(snapshot, /errorRatePercent\(\)/)
  assert.match(snapshot, /Canary error count is outside the sample range/)
  assert.match(policy, /minimumWindowSeconds = 300/)
  assert.match(policy, /maximumErrorRatePercent = 1\.0/)
  assert.match(policy, /maximumP95LatencyMs = 1200\.0/)
})

test('phase 22 support plan contains rollback, monitoring and communication readiness', () => {
  const source = read('Cms/Release/ReleaseSupportPlan.php')
  for (const field of ['monitoringReady', 'incidentRunbookReady', 'rollbackRunbookReady', 'customerCommunicationReady']) {
    assert.match(source, new RegExp(field))
  }
  assert.match(source, /support\.rollback_runbook_ready/)
})

test('phase 22 evidence keeps technical RC, Canary and Stable boundaries honest', () => {
  const evidence = JSON.parse(read('resources/release/stable-canary-support-phase22-v1.json'))
  assert.equal(evidence.schema, 1)
  assert.equal(evidence.phase, 22)
  assert.deepEqual(evidence.channels, ['canary', 'stable'])
  assert.equal(evidence.verification.local_technical_candidate, true)
  assert.equal(evidence.verification.canary_promotion_executed, false)
  assert.equal(evidence.verification.stable_promotion_executed, false)
  assert.equal(evidence.verification.post_release_support_ready, false)
  assert.equal(evidence.release.signed, true)
  assert.equal(evidence.release.signing.key_id, 'nanopino-local:main')
  assert.equal(evidence.release.signing.target_trust_store_registered, false)
  assert.equal(evidence.verification.signed_artifact_verified, true)
  assert.equal(evidence.verification.signed_lifecycle_executed, false)
  assert.equal(evidence.target.mutations_executed, false)
  assert.equal(evidence.verification.canary_observation_recorded, true)
  assert.equal(evidence.verification.canary_observation_promotable, false)
  assert.equal(evidence.verification.stable_approval, false)
  assert.equal(evidence.verification.target_canary_deployment_verified, false)
})

test('phase 22 exposes a safe, scheduler-ready hypercare monitor contract', () => {
  const evidence = JSON.parse(read('resources/release/hypercare-activation-phase22-v1.json'))
  const monitor = read('../tools/live/monitor-nanopino-hypercare.sh')
  assert.equal(evidence.schema, 1)
  assert.equal(evidence.phase, 22)
  assert.equal(evidence.activation.status, 'source_operational_bundle_ready')
  assert.equal(evidence.activation.target_mutations_executed, false)
  assert.equal(evidence.activation.on_call_owner_assigned, false)
  assert.equal(evidence.activation.alert_channel_connected, false)
  assert.equal(evidence.monitor.method, 'GET')
  assert.equal(evidence.monitor.probes.length, 4)
  assert.match(monitor, /GET-only probes/)
  assert.match(monitor, /NANOPINO_MONITOR_OUTPUT/)
  assert.match(monitor, /exit 1/)
  assert.doesNotMatch(monitor, /curl[^\n]*(--data|-X POST|--upload-file)/)
})
