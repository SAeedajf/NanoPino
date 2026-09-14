import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (file) => readFileSync(new URL(`../../../${file}`, import.meta.url), 'utf8')

test('phase 6 keeps every public surface mount-aware and locale-aware', () => {
  const controller = read('Controller/PublicContentController.php')
  const home = read('Cms/PublicSite/PublicSiteHomeRenderer.php')
  const page = read('Cms/PublicSite/PublicPageRenderer.php')
  const taxonomy = read('Cms/PublicSite/PublicTaxonomyRenderer.php')

  assert.match(controller, /AdminRuntimeUrl::appPath\('\/site', AdminRuntimeUrl::currentMountPath\(\)\)/)
  assert.match(controller, /\$context->locale/)
  assert.match(home, /PublicContentUrl::path\(\$record, \$mountPath\)/)
  assert.match(taxonomy, /PublicContentUrl::path\(\$record, \$mountPath\)/)
  for (const source of [home, page, taxonomy]) {
    assert.match(source, /new PublicSiteCopy\(/)
    assert.match(source, /<html lang=/)
    assert.match(source, /\$copy->direction\(\)/)
    assert.match(source, /public-primary-content/)
    assert.match(source, /prefers-reduced-motion:reduce/)
  }
})

test('phase 6 makes navigation keyboard-visible, active-aware, and responsive', () => {
  const navigation = read('Cms/PublicSite/PublicNavigation.php')
  const copy = read('Cms/PublicSite/PublicSiteCopy.php')

  assert.match(navigation, /public-navigation__skip/)
  assert.match(navigation, /aria-current="page"/)
  assert.match(navigation, /min-block-size:44px/)
  assert.match(navigation, /focus-within/)
  assert.match(navigation, /prefers-reduced-motion:reduce/)
  assert.match(copy, /'fa'/)
  assert.match(copy, /'en'/)
  assert.match(copy, /'site_aria'/)
})

test('phase 6 public cards expose truthful counts and visitor-facing states', () => {
  const home = read('Cms/PublicSite/PublicSiteHomeRenderer.php')
  const taxonomy = read('Cms/PublicSite/PublicTaxonomyRenderer.php')
  const page = read('Cms/PublicSite/PublicPageRenderer.php')

  assert.match(home, /\$visibleCount\+\+/)
  assert.match(taxonomy, /\$visibleCount\+\+/)
  assert.match(home, /public-site__footer/)
  assert.match(taxonomy, /public-taxonomy__footer/)
  assert.match(page, /public-page__footer/)
  assert.match(page, /renderTaxonomyTerms\(\$taxonomyTerms, \$copy\)/)
})
