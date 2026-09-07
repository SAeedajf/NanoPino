import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../..', import.meta.url)))
const read = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')

test('P3 extension identity preserves human name from Pinoox manifest to catalog', () => {
  const projector = read('Cms/Discovery/AppConfigManifestProjector.php')
  const manifest = read('Cms/Manifest/ExtensionManifest.php')
  const factory = read('Cms/Manifest/ExtensionManifestFactory.php')
  const definition = read('Cms/Extension/ExtensionDefinition.php')
  const catalog = read('Cms/ExtensionCenter/ExtensionCenterCatalogService.php')

  assert.match(projector, /'name'\s*=>\s*\(string\).*\['title'\]/)
  assert.match(manifest, /public function name\(\): string/)
  assert.match(factory, /name:\s*trim\(\(string\)\(\$pinx\['name'\]/)
  assert.match(definition, /name:\s*\$manifest->name\(\)/)
  assert.match(catalog, /\$definition->name\(\)/)
})

test('P3 core modules are protected in Domain and both admin surfaces', () => {
  const center = read('Cms/ExtensionCenter/ExtensionCenterService.php')
  const vue = read('theme/cms-admin/src/pages/extensions/page-extensions.vue')
  const runtime = read('theme/cms-admin/runtime/extensions.mjs')

  assert.match(center, /isCoreModule\(\$extensionId\)/)
  assert.match(center, /ExtensionOperationType::Deactivate/)
  assert.match(center, /ExtensionOperationType::Uninstall/)
  assert.match(vue, /core_protected/)
  assert.match(runtime, /core_action_blocked/)
})

test('P3 package review is structured and update uses the extension-specific endpoint', () => {
  const vue = read('theme/cms-admin/src/pages/extensions/page-extensions.vue')
  const runtime = read('theme/cms-admin/runtime/extensions.mjs')
  const api = read('theme/cms-admin/src/services/cms-api.js')

  assert.doesNotMatch(vue, /reviewSummary.*JSON\.stringify/)
  assert.match(vue, /dependencyIssues/)
  assert.match(vue, /reviewPermissions/)
  assert.match(vue, /reviewSecurity/)
  assert.match(runtime, /reviewData\.dependencies/)
  assert.match(runtime, /reviewData\.permissions/)
  assert.match(api, /\/extensions\/\$\{encodeURIComponent\(id\)\}\/update/)
})

test('P3 Update Center runtime is bound to policy history and recovery APIs', () => {
  const runtime = read('theme/cms-admin/runtime/updates.mjs')
  const vue = read('theme/cms-admin/src/pages/updates/page-updates.vue')

  assert.match(runtime, /\/updates\//)
  assert.match(runtime, /\/policy/)
  assert.match(runtime, /history/)
  assert.match(runtime, /recovery-points/)
  assert.match(runtime, /method:'PUT'/)
  assert.match(vue, /updateApi\.policy/)
  assert.match(vue, /updateApi\.history/)
  assert.match(vue, /updateApi\.recoveryPoints/)
  assert.match(vue, /updateApi\.savePolicy/)
})

test('P3 Recovery uses versioned APIs and exposes actionable Safe Mode context', () => {
  const vue = read('theme/cms-admin/src/pages/recovery/page-recovery.vue')
  const runtime = read('theme/cms-admin/runtime/recovery.mjs')
  const api = read('theme/cms-admin/src/services/cms-api.js')

  assert.doesNotMatch(vue, /performAdminAction/)
  assert.match(vue, /recoveryApi\.restore/)
  assert.match(vue, /recoveryApi\.disableSafeMode/)
  assert.match(vue, /safeMode\.reason/)
  assert.match(vue, /open_extensions/)
  assert.match(runtime, /safeMode\.reason/)
  assert.match(runtime, /quarantined/)
  assert.match(api, /\/recovery\/points\//)
})

test('P3 Developer SDK generates bounded starters through a permission-aware API', () => {
  const capability = read('Cms/Capability/CoreCapabilities.php')
  const contract = read('Cms/Api/V1/Developer/DeveloperApiContract.php')
  const controller = read('Controller/Api/DeveloperRuntimeApiController.php')
  const exportService = read('Cms/Sdk/Package/ExtensionStarterExportService.php')
  const scaffold = read('Cms/Sdk/Package/ExtensionScaffoldGenerator.php')
  const page = read('theme/cms-admin/src/pages/developer/page-sdk.vue')

  assert.match(capability, /system\.developer\.generate/)
  assert.match(contract, /\/api\/v1\/cms\/developer/)
  assert.match(controller, /AuthorizationRequest\(\s*'system\.developer\.generate'/)
  assert.match(exportService, /MAX_SOURCE_BYTES = 1048576/)
  assert.match(exportService, /MAX_ARCHIVE_BYTES = 2097152/)
  assert.match(exportService, /ExtensionType::CoreModule/)
  assert.match(scaffold, /Development Example/)
  assert.match(page, /developerApi\.generateStarter/)
  assert.match(page, /downloadArchive/)
  assert.match(page, /downloadSource/)
})
