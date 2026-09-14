import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (file) => readFileSync(new URL(`../../../${file}`, import.meta.url), 'utf8')

test('published page and post URLs are registered before the admin wildcard', () => {
  const routes = read('routes/web.php')
  assert.match(routes, /get\('\/site', \[PublicContentController::class, 'site'\]\)/)
  assert.match(routes, /get\('\/page\/\{slug\}'\, \[PublicContentController::class, 'page'\]\)/)
  assert.match(routes, /get\('\/post\/\{slug\}'\, \[PublicContentController::class, 'post'\]\)/)
  assert.ok(routes.indexOf("get('/page/{slug}'") < routes.indexOf("get('*'"))
})

test('dashboard exposes a mount-aware public-site link instead of routing to the control plane', () => {
  const admin = read('Controller/AdminController.php')
  const dashboard = read('theme/cms-admin/src/pages/dashboard/page-dashboard-final.vue')
  const provider = read('theme/cms-admin/src/services/admin-provider.js')
  assert.match(admin, /'publicSiteUrl' => AdminRuntimeUrl::appPath\('\/site', \$adminMountPath\)/)
  assert.match(dashboard, /class="cms-dashboard-public-site"/)
  assert.match(dashboard, /target="_blank"/)
  assert.match(dashboard, /t\('dashboard\.view_site'\)/)
  assert.match(provider, /export function publicSiteUrl\(\)/)
})

test('public site home renders published content cards with a safe empty state', () => {
  const controller = read('Controller/PublicContentController.php')
  const renderer = read('Cms/PublicSite/PublicSiteHomeRenderer.php')
  assert.match(controller, /ContentStatus::Published/)
  assert.match(controller, /new PublicSiteHomeRenderer\(\)/)
  assert.match(renderer, /PublicContentUrl::path\(\$record, \$mountPath\)/)
  assert.match(renderer, /public-site__empty/)
  assert.match(renderer, /no_published/)
})

test('public content is restricted to published records and sanitized before HTML output', () => {
  const controller = read('Controller/PublicContentController.php')
  const renderer = read('Cms/PublicSite/PublicPageRenderer.php')
  const repository = read('Cms/Content/PinooxContentRepository.php')
  assert.match(controller, /findPublishedBySlug\(\$context->siteId, \$type, \$context->locale, \$slug\)/)
  assert.match(repository, /where\('status', ContentStatus::Published->value\)/)
  assert.match(renderer, /StrictRichTextSanitizer/)
  assert.match(renderer, /rel="canonical"/)
})

test('public content pages expose only safe public taxonomy links', () => {
  const controller = read('Controller/PublicContentController.php')
  const renderer = read('Cms/PublicSite/PublicPageRenderer.php')
  assert.match(controller, /publicTaxonomyLinks\(\$record\)/)
  assert.match(controller, /findMany\(array_values\(array_unique\(\$ids\)\)\)/)
  assert.match(controller, /!\$definition->public/)
  assert.match(controller, /\$term->siteId !== \$record->siteId/)
  assert.match(controller, /\$term->taxonomy !== \$taxonomy/)
  assert.match(controller, /\$term->locale !== \$record->locale/)
  assert.match(renderer, /class="public-page__terms"/)
  assert.match(renderer, /taxonomy_aria/)
  assert.match(renderer, /htmlspecialchars\(\$value, ENT_QUOTES/)
})

test('public routes consume the centralized configured site context', () => {
  const controller = read('Controller/PublicContentController.php')
  const context = read('Cms/PublicSite/PublicSiteContext.php')
  const app = read('app.php')
  assert.match(controller, /PublicSiteContext::fromRuntime\(\$this->getRequest\(\)->getHost\(\)\)/)
  assert.doesNotMatch(controller, /findPublishedBySlug\(1, \$type, 'fa', \$slug\)/)
  assert.doesNotMatch(controller, /siteId: 1,\s*status: ContentStatus::Published,\s*locale: 'fa'/)
  assert.match(context, /AppEngine::config\('com_pinoox_cms'\)->get\('public', \[\]\)/)
  assert.match(context, /siteId: \(int\)\(\$selected\['site_id'\]/)
  assert.match(context, /preg_match\('\/\^\[a-z\]\{2\}/)
  assert.match(app, /'public'\s*=>\s*\[\s*[\s\S]*'site_id'\s*=>\s*1[\s\S]*'locale'\s*=>\s*'fa'/)
})

test('public site context uses an exact host allowlist for multi-site routing', () => {
  const context = read('Cms/PublicSite/PublicSiteContext.php')
  const controller = read('Controller/PublicContentController.php')
  const app = read('app.php')
  assert.match(context, /fromRuntime\(\?string \$host = null\)/)
  assert.match(context, /normalizeHost\(\$host\)/)
  assert.match(context, /request host is not configured/)
  assert.doesNotMatch(context, /\*\\\./)
  assert.match(controller, /PublicSiteContext::fromRuntime\(\$this->getRequest\(\)->getHost\(\)\)/)
  assert.match(app, /'hosts'\s*=>\s*\[\]/)
})

test('public host registry is an audited site-scoped setting', () => {
  const settings = read('Cms/Settings/CoreSettings.php')
  const context = read('Cms/PublicSite/PublicSiteContext.php')
  assert.match(settings, /'site\.public_hosts', self::OWNER, SettingType::Json, \[\]/)
  assert.match(settings, /readPermission: null, writePermission: 'settings\.manage'/)
  assert.match(settings, /PublicSiteContext::validateHostMap\(\$value\)/)
  assert.match(context, /HOSTS_SETTING = 'site\.public_hosts'/)
  assert.match(context, /settingsRepository\(\)->find\(/)
  assert.match(context, /new SettingScope\(ScopeType::Site, \$baseContext->siteId\)/)
  assert.match(context, /Managed public host registry must be an object/)
})

test('managed public host data fails closed instead of falling back on corruption', () => {
  const context = read('Cms/PublicSite/PublicSiteContext.php')
  const start = context.indexOf('public static function fromRuntime')
  const end = context.indexOf('/** @param array<string,mixed> $config */', start)
  const runtime = context.slice(start, end)
  assert.match(context, /if \(!is_array\(\$record->value\)\)/)
  assert.ok(runtime && !runtime.includes('catch (\\Throwable $error)'))
})

test('public navigation is a validated site setting rendered by every public surface', () => {
  const navigation = read('Cms/PublicSite/PublicNavigation.php')
  const settings = read('Cms/Settings/CoreSettings.php')
  const controller = read('Controller/PublicContentController.php')
  const home = read('Cms/PublicSite/PublicSiteHomeRenderer.php')
  const page = read('Cms/PublicSite/PublicPageRenderer.php')
  const taxonomy = read('Cms/PublicSite/PublicTaxonomyRenderer.php')
  assert.match(settings, /PublicNavigation::SETTING/)
  assert.match(settings, /PublicNavigation::validate\(\$value\)/)
  assert.match(navigation, /Public navigation paths must be safe app-local paths/)
  assert.match(navigation, /Public navigation supports at most two levels/)
  assert.match(navigation, /AdminRuntimeUrl::appPath\(\$item\['path'\], \$mountPath\)/)
  assert.match(controller, /private function publicNavigation\(PublicSiteContext \$context\)/)
  assert.match(home, /\?->render\(\$mountPath, \$canonicalPath/)
  assert.match(page, /\?->render\(\$mountPath, \$canonicalPath/)
  assert.match(taxonomy, /\?->render\(\$mountPath, \$canonicalPath/)
})

test('content admin exposes a canonical public URL only after publishing', () => {
  const api = read('Controller/Api/ContentRuntimeApiController.php')
  const page = read('theme/cms-admin/src/pages/content/page-content.vue')
  assert.match(api, /\['public_url'\] = PublicContentUrl::path\(\$record\)/)
  assert.match(page, /status==='published'&&item\.public_url/)
  assert.match(page, /view_public/)
})
