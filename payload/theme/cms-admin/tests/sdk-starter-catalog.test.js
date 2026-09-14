import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const themeRoot = resolve(fileURLToPath(new URL('..', import.meta.url)))
const packageRoot = resolve(themeRoot, '../..')
const readPackage = (relative) => readFileSync(resolve(packageRoot, relative), 'utf8')

test('SDK exposes seven executable starter paths through the read-only admin catalog', () => {
  const catalog = readPackage('Cms/Sdk/Package/SdkStarterCatalog.php')
  const controller = readPackage('Controller/AdminController.php')
  const generator = readPackage('../tools/generate-sdk-starters.php')
  const page = readPackage('theme/cms-admin/src/pages/developer/page-sdk.vue')

  for (const id of ['module', 'plugin', 'integration', 'theme', 'admin-extension', 'block', 'block-package']) {
    assert.match(catalog, new RegExp(`'${id}'`))
  }
  assert.match(controller, /SdkStarterCatalog::all\(\)/)
  assert.match(generator, /Refusing to overwrite a non-empty output directory/)
  assert.match(page, /starter_executable/)
  assert.match(page, /starter\.command/)
})
