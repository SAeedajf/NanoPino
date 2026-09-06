import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')
const readTheme = (relative) => readFileSync(resolve(themeRoot, relative), 'utf8')

test('R10 formerly decorative admin centers use live runtime clients', () => {
  const api = readTheme('src/services/cms-api.js')
  const updates = readTheme('src/pages/updates/page-updates.vue')
  const infrastructure = readTheme('src/pages/system/page-infrastructure.vue')
  const performance = readTheme('src/pages/system/page-performance.vue')
  const security = readTheme('src/pages/system/page-security.vue')
  const dashboard = readTheme('src/pages/dashboard/page-dashboard.vue')

  for (const token of ['updateApi','infrastructureApi','performanceApi','searchApi','healthHistory']) {
    assert.match(api, new RegExp(`export const ${token}|healthHistory:`))
  }
  assert.match(updates, /updateApi\.policy/)
  assert.match(updates, /updateApi\.savePolicy/)
  assert.match(infrastructure, /infrastructureApi\.retryQueue/)
  assert.match(infrastructure, /infrastructureApi\.invalidateTag/)
  assert.match(performance, /performanceApi\.status/)
  assert.match(security, /securityApi\.status/)
  assert.match(dashboard, /pagination\?\.total/)
  assert.match(dashboard, /systemApi\.health/)
})

test('R10 health history is persisted and exposed through the runtime manifest', () => {
  const facade = readPackage('Cms/Api/V1/SystemHealth/SystemHealthApiFacade.php')
  const runtime = readPackage('Cms/Runtime/CmsRuntimeServices.php')
  const manifest = readPackage('Cms/Runtime/CmsRuntimeApiManifest.php')
  const controller = readPackage('Controller/Api/SystemRuntimeApiController.php')

  assert.match(facade, /\$this->history->append\(\$overall, \$results\)/)
  assert.match(facade, /public function history\(/)
  assert.match(runtime, /FileHealthHistoryRepository/)
  assert.match(manifest, /\/system\/health\/history/)
  assert.match(controller, /function healthHistory/)
})

test('R10 content totals are exact and ownership-aware', () => {
  const contract = readPackage('Cms/Content/ContentRepositoryInterface.php')
  const pinoox = readPackage('Cms/Content/PinooxContentRepository.php')
  const memory = readPackage('Cms/Content/InMemoryContentRepository.php')
  const service = readPackage('Cms/Content/ContentService.php')
  const controller = readPackage('Controller/Api/ContentRuntimeApiController.php')

  assert.match(contract, /public function count\(ContentQuery \$query\): int/)
  assert.match(pinoox, /return \(int\) \$builder->count\(\)/)
  assert.match(memory, /public function count\(ContentQuery \$query\): int/)
  assert.match(service, /public function count\(ContentQuery \$query, \?int \$actorId = null\): int/)
  assert.match(service, /authorId: \$actorId/)
  assert.match(controller, /'total' => \$total/)
})

test('R10 reusable blocks share one repository between API and preview', () => {
  const runtime = readPackage('Cms/Runtime/CmsRuntimeServices.php')
  const manifest = readPackage('Cms/Runtime/CmsRuntimeApiManifest.php')
  const controller = readPackage('Controller/Api/GlobalBlockRuntimeApiController.php')
  const builder = readTheme('src/pages/builder/page-builder.vue')

  assert.match(runtime, /globalBlockRepository\(\)/)
  assert.match(runtime, /new GlobalBlockReferenceExpander\(/)
  assert.match(runtime, /self::globalBlockRepository\(\)/)
  assert.match(manifest, /\/builder\/global-blocks/)
  assert.match(controller, /expected_version/)
  assert.match(builder, /globalBlockApi\.create/)
  assert.match(builder, /core\/global-reference/)
})

test('R10 theme patterns are discovered through the active theme stack and insert into Builder', () => {
  const theme = readPackage('Controller/Api/ThemeRuntimeApiController.php')
  const manifest = readPackage('Cms/Runtime/CmsRuntimeApiManifest.php')
  const builder = readTheme('src/pages/builder/page-builder.vue')

  assert.match(theme, /ThemePatternLoader/)
  assert.match(theme, /\$stack->paths/)
  assert.doesNotMatch(theme, /sourceThemePath.*=>/)
  assert.match(manifest, /\/themes\/\{package\}\/\{theme\}\/patterns/)
  assert.match(builder, /themeApi\.patterns/)
  assert.match(builder, /function insertPattern/)
})

test('R10 does not advertise unavailable marketplace, remote transport, or missing SDK starters as active', () => {
  const admin = readPackage('Controller/AdminController.php')
  const drivers = readPackage('Cms/Driver/CoreDrivers.php')

  assert.match(admin, /'marketplace'\s*=>\s*\[[\s\S]*?'connected'\s*=>\s*false/)
  assert.match(admin, /'starters'\s*=>\s*\[\]/)
  assert.match(admin, /'active'\s*=>\s*'search\.database'/)
  assert.match(drivers, /UnboundRemoteSearchTransport/)
})

test('R10 actor-sensitive runtime caches are invalidated on actor change', () => {
  const runtime = readPackage('Cms/Runtime/CmsRuntimeServices.php')

  assert.match(runtime, /private static bool \$actorContextInitialized = false/)
  assert.match(runtime, /synchronizeActorContext\(\$actorId\)/)
  for (const property of ['authorization','settings','media','builder','builderPreview','builderApi','globalBlocks','content','revisions','userAdministration','searchApi','infrastructureApi','performanceApi','updateApi']) {
    assert.match(runtime, new RegExp(`self::\\$${property} = null`))
  }
  for (const persistent of ['settingsRepository','cacheStore','queueRepository','storageDriver','queryProbe']) {
    assert.doesNotMatch(
      runtime.slice(runtime.indexOf('private static function synchronizeActorContext')),
      new RegExp(`self::\\$${persistent} = null`),
    )
  }
})
