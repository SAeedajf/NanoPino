import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')

test('R11 remote search is fail-closed and SSRF guarded', () => {
  const config = readPackage('Cms/Search/RemoteSearchConfiguration.php')
  const transport = readPackage('Cms/Search/GuardedRemoteSearchTransport.php')
  const runtime = readPackage('Cms/Runtime/CmsRuntimeServices.php')
  const settings = readPackage('Cms/Settings/CoreSettings.php')

  assert.match(config, /\['meilisearch', 'typesense'\]/)
  assert.match(config, /search\.remote\.api_key/)
  assert.match(settings, /sensitive: true/)
  assert.match(transport, /SsrfGuard/)
  assert.match(transport, /NetworkEndpointPolicy/)
  assert.match(transport, /CURLOPT_RESOLVE/)
  assert.match(transport, /CURLOPT_FOLLOWLOCATION => false/)
  assert.match(transport, /CURLOPT_PROTOCOLS => CURLPROTO_HTTPS/)
  assert.match(transport, /CURLOPT_SSL_VERIFYPEER => true/)
  assert.match(transport, /maxResponseBytes/)
  assert.match(runtime, /new SearchManager\(\s*self::searchDriver\(\),\s*new PinooxDatabaseSearchDriver\(\)/s)
  assert.match(runtime, /RuntimeBindingState::setSsrf\(false\)/)
  assert.match(runtime, /RuntimeBindingState::setSsrf\(true\)/)
})

test('R11 remote search index and delete use the same deterministic identity', () => {
  const transport = readPackage('Cms/Search/GuardedRemoteSearchTransport.php')
  assert.match(transport, /'id' => \$this->documentKey\(\$payload\)/)
  assert.match(transport, /rawurlencode\(\$this->documentKey\(\$payload\)\)/)
  assert.match(transport, /'source_id' => \(string\)\(\$payload\['id'\]/)
})

test('R11 migration recovery is batch-bounded and verifies exact baseline restoration', () => {
  const provider = readPackage('Cms/Recovery/PinooxMigrationSnapshotProvider.php')
  const executor = readPackage('Cms/ExtensionCenter/Operation/PinooxExtensionOperationExecutor.php')

  assert.match(provider, /MigrationQuery::fetchAllByBatch\(null, \$this->package\)/)
  assert.match(provider, /Refusing migration rollback across unrelated batches/)
  assert.match(provider, /Refusing migration rollback that includes baseline migrations/)
  assert.match(provider, /->rollback\(count\(\$batches\)\)/)
  assert.match(provider, /Migration recovery did not return to the captured state/)
  assert.doesNotMatch(executor, /rollback\(count\(\$new\)\)/)
  assert.match(executor, /new PinooxMigrationSnapshotProvider\(\$package\)/)
})

test('R11 install update and uninstall exceptions converge on recovery and quarantine', () => {
  const executor = readPackage('Cms/ExtensionCenter/Operation/PinooxExtensionOperationExecutor.php')
  assert.match(executor, /private function recoverFailedOperation\(/)
  assert.match(executor, /private function quarantineFailedPackage\(/)
  assert.match(executor, /set\('enable', false\)->save\(\)/)
  assert.match(executor, /Extension operation recovery was incomplete/)
  assert.match(executor, /catch \(\\Throwable \$error\)[\s\S]*recoverFailedOperation/)
})

test('R11 CSP remains explicitly report-only until Pinoox bootstrap nonce compatibility is proven', () => {
  const csp = readPackage('Cms/Security/Http/CspPolicy.php')
  const state = readPackage('Cms/Runtime/RuntimeBindingState.php')
  assert.match(csp, /public bool \$reportOnly = true/)
  assert.match(state, /setCsp\(bool \$bound\)/)
})
