import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const appRoot = resolve(root, '../..')
const read = (path) => readFileSync(resolve(root, path), 'utf8')
const readApp = (path) => readFileSync(resolve(appRoot, path), 'utf8')

test('R6 uses Pinoox-native Admin language catalogs with NanoPino brand', () => {
  for (const locale of ['fa', 'en']) {
    const path = resolve(appRoot, `lang/${locale}/admin.lang.php`)
    assert.equal(existsSync(path), true)
    const source = readFileSync(path, 'utf8')
    assert.match(source, /'name'\s*=>\s*'NanoPino'/)
    assert.match(source, /'routes'\s*=>/)
    assert.match(source, /'a11y'\s*=>/)
  }

  const i18n = readApp('Cms/Admin/AdminI18n.php')
  assert.match(i18n, /Pinoox\\Portal\\Lang/)
  assert.match(i18n, /GROUP\s*=\s*'admin'/)
  assert.match(i18n, /FALLBACK_LOCALE\s*=\s*'en'/)
})

test('R6 boot payload exposes locale, direction, brand and translation catalog', () => {
  const controller = readApp('Controller/AdminController.php')
  assert.match(controller, /AdminI18n::catalog\(\$adminLocale\)/)
  assert.match(controller, /AdminI18n::direction\(\$adminLocale\)/)
  assert.match(controller, /'brand'\s*=>\s*\[/)
  assert.match(controller, /'i18n'\s*=>\s*\[/)

  const twig = read('main.twig')
  assert.match(twig, /lang="\{\{ bootstrap\.locale/)
  assert.match(twig, /dir="\{\{ bootstrap\.direction/)
  assert.match(twig, /t\('admin\.brand\.admin_title'\)/)
  assert.match(twig, /t\('admin\.shell\.booting_title'\)/)
})

test('R6 core Admin definitions resolve public copy through AdminI18n', () => {
  const files = [
    'CoreAdminDefinitions.php', 'CoreContentAdminDefinitions.php', 'CoreMediaRevisionAdminDefinitions.php',
    'CoreIdentityAdminDefinitions.php', 'CoreSettingsAdminDefinitions.php', 'CoreThemeAdminDefinitions.php',
    'CoreBlockAdminDefinitions.php', 'CoreBuilderAdminDefinitions.php', 'CoreFullSiteAdminDefinitions.php',
    'CoreDeveloperAdminDefinitions.php',
  ]
  for (const file of files) {
    const source = readApp(`Cms/Admin/${file}`)
    assert.match(source, /AdminI18n::text\(/, `${file} must use AdminI18n`)
  }
})

test('R6 runtime pages use translation route metadata and NanoPino dynamic brand', () => {
  const runtimeFiles = [
    'appearance.mjs','blocks.mjs','builder.mjs','content.mjs','dashboard.mjs','extensions.mjs','logs.mjs',
    'media.mjs','recovery.mjs','revisions.mjs','settings.mjs','site-editor.mjs','system.mjs','updates.mjs','users.mjs',
  ]
  for (const file of runtimeFiles) {
    const source = read(`runtime/${file}`)
    assert.match(source, /\btr\(/, `${file} must use runtime translation catalog`)
    assert.doesNotMatch(source, /Pinoox CMS/, `${file} must not expose the old product brand`)
  }
  const common = read('runtime/common.mjs')
  assert.match(common, /export function tr\(/)
  assert.match(common, /export function brandName\(/)
  assert.match(read('runtime/site-editor.mjs'), /brandName\(\)/)
})

test('R6 adds keyboard and accessible-name contracts to critical runtime controls', () => {
  const media = read('runtime/media.mjs')
  assert.match(media, /role:'button',tabindex:0/)
  assert.match(media, /e\.key==='Enter'\|\|e\.key===' '/)
  assert.match(media, /a11y\.media_upload/)

  const builder = read('runtime/builder.mjs')
  assert.match(builder, /a11y\.builder_move_up/)
  assert.match(builder, /a11y\.builder_move_down/)
  assert.match(builder, /a11y\.builder_viewport/)
  assert.match(builder, /'aria-pressed':selected/)

  const extensions = read('runtime/extensions.mjs')
  assert.match(extensions, /a11y\.extension_package/)

  const settings = read('runtime/settings.mjs')
  assert.match(settings, /a11y\.settings_group_mobile/)

  const users = read('runtime/users.mjs')
  assert.match(users, /role:'button',tabindex:0/)
})

test('R6 separates Vite bundle parity from directly-served runtime module parity', () => {
  const jsFingerprint = read('source-fingerprint.mjs')
  assert.match(jsFingerprint, /const candidates = \['src', 'public'\]/)
  assert.doesNotMatch(jsFingerprint, /const candidates = \['src', 'public', 'runtime'\]/)
  assert.match(jsFingerprint, /runtime-fingerprint\.mjs/)

  const phpFingerprint = readApp('Cms/Admin/Frontend/AdminFrontendSourceFingerprint.php')
  assert.match(phpFingerprint, /\['src','public'\]/)
  assert.doesNotMatch(phpFingerprint, /\['src','public','runtime'\]/)

  const runtimeFingerprint = readApp('Cms/Admin/Frontend/AdminFrontendRuntimeFingerprint.php')
  assert.match(runtimeFingerprint, /\/runtime/)
  assert.match(runtimeFingerprint, /hash_init\('sha256'\)/)

  const gate = readApp('Cms/Release/ProductionReleaseGate.php')
  assert.match(gate, /verifyAdminRuntime/)
  assert.match(gate, /release\.admin_runtime_parity_mismatch/)
  assert.match(gate, /runtimeParity/)
})


test('R6 release tooling cannot silently relabel a stale Vite dist', () => {
  const fingerprint = read('source-fingerprint.mjs')
  const pkg = JSON.parse(read('package.json'))
  assert.match(fingerprint, /--write-build-evidence/)
  assert.match(fingerprint, /Admin source\/dist parity mismatch/)
  assert.match(pkg.scripts.build, /source-fingerprint\.mjs --write-build-evidence/)
  assert.match(pkg.scripts.build, /source-fingerprint\.mjs && node verify-dist/)
})

test('R6 critical interaction semantics include live regions and true tab behavior', () => {
  const pageState = read('src/components/cms-page-state.vue')
  assert.match(pageState, /aria-live/)
  assert.match(pageState, /aria-busy/)
  const content = read('src/pages/content/page-content.vue')
  assert.match(content, /role=\"tab\"/)
  assert.match(content, /aria-selected/)
  assert.match(content, /moveStatusTab/)
  const runtimeContent = read('runtime/content.mjs')
  assert.match(runtimeContent, /role: 'tablist'/)
  assert.match(runtimeContent, /'aria-selected'/)
})

test('R6 exposes a bounded public i18n contract to third-party Admin Extensions', () => {
  const componentDefinition = readApp('Cms/Admin/AdminComponentDefinition.php')
  assert.match(componentDefinition, /public array \$i18n=\[\]/)
  assert.match(componentDefinition, /65536/)
  assert.match(componentDefinition, /validateI18nNode/)
  assert.match(componentDefinition, /'i18n'=>\$this->i18n/)

  const context = readApp('Cms/Sdk/ExtensionContext.php')
  assert.match(context, /function adminComponent\([\s\S]*array \$i18n=\[\]/)

  const registry = read('src/registry/admin-components.js')
  assert.match(registry, /createExtensionHost/)
  assert.match(registry, /extensionT: scopedT/)
  assert.match(registry, /factory\(createExtensionHost\(definition\)\)/)

  const scaffold = readApp('Cms/Sdk/Package/ExtensionScaffoldGenerator.php')
  assert.match(scaffold, /i18n:\s*\[/)
  assert.match(scaffold, /i18n\.extensionT/)
})

test('R6 high-traffic Content and Dashboard surfaces no longer embed Persian UI literals', () => {
  const files = [
    'src/pages/content/page-content.vue',
    'runtime/content.mjs',
    'src/pages/dashboard/page-dashboard.vue',
    'runtime/dashboard.mjs',
    'src/pages/recovery/page-recovery.vue',
  ]
  const persian = /[\u0600-\u06FF]/
  for (const file of files) {
    assert.doesNotMatch(read(file), persian, `${file} must resolve Persian copy from the language catalog`)
  }
})
