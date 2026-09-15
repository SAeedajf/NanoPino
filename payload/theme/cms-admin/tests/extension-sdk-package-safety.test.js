import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')

test('SDK module migration scaffold uses logical table names exactly once', () => {
  const source = readPackage('Cms/Sdk/Package/ExtensionScaffoldGenerator.php')
  assert.match(source, /schema->create\('example_items'/)
  assert.match(source, /schema->dropIfExists\('example_items'\)/)
  assert.doesNotMatch(source, /schema->create\(\$this->table\('example_items'\)/)
  assert.doesNotMatch(source, /dropIfExists\(\$this->table\('example_items'\)\)/)
})

test('PINX extension path uses CMS preflight before native installer without replacing PINX lifecycle', () => {
  const inspector = readPackage('Cms/ExtensionCenter/Package/PinooxPinxPackageInspector.php')
  const executor = readPackage('Cms/ExtensionCenter/Operation/PinooxExtensionOperationExecutor.php')
  const themeController = readPackage('Controller/Api/ThemeRuntimeApiController.php')
  const services = readPackage('Cms/Runtime/CmsRuntimeServices.php')
  const preflight = readPackage('Cms/Security/Package/PinooxPinxPackagePreflight.php')
  const plan = readPackage('Cms/Installer/PackageFilePlan.php')

  assert.match(inspector, /preflight->inspectReader\(\$reader\)/)
  assert.match(executor, /security_preflight/)
  assert.match(executor, /inspectPath\(/)
  assert.match(executor, /Pinx::installer\(\)/)
  assert.match(executor, /require_signature.*request->options/s)
  assert.match(executor, /nanoshell-local-converter/)
  assert.match(executor, /rotateLegacyConverterIdentity/)
  assert.match(services, /wordpressThemeNativePackageBuilder\(\)[\s\S]*wordpress-converter-sign\.key\.json/)
  assert.match(themeController, /recordThemeUpdateHistory/)
  assert.match(themeController, /sameThemeSource\(\$update\)/)
  assert.match(themeController, /no_op.*true/s)
  assert.match(themeController, /Theme is already current; no files changed\./)
  assert.doesNotMatch(themeController, /\$native->identifier\(\)/)
  assert.match(themeController, /wordpress-theme-import/)
  assert.match(preflight, /CanonicalPackagePath::normalize/)
  assert.match(preflight, /new PackageFilePlan/)
  assert.match(plan, /Symlink package entries are forbidden/)
  assert.match(plan, /Case-insensitive package target collision/)
})

test('high static-risk findings fail closed during extension review', () => {
  const review = readPackage('Cms/ExtensionCenter/Review/ExtensionInstallReviewService.php')
  const scanner = readPackage('Cms/Security/Package/ExtensionStaticRiskScanner.php')
  assert.match(review, /permissionReview->hasUnknown\(\)[\s\S]*security\?->hasHighRisk/)
  assert.match(scanner, /php\.dynamic_eval/)
  assert.match(scanner, /php\.process_exec/)
  assert.match(scanner, /js\.dynamic_code/)
})
