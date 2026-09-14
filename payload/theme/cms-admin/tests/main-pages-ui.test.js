import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const read = (path) => readFileSync(resolve(root, path), 'utf8')

test('phase 3 makes the primary CMS pages data-aware and accessible', () => {
  const dashboard = read('src/pages/dashboard/page-dashboard-final.vue')
  const content = read('src/pages/content/page-content.vue')
  const media = read('src/pages/media/page-media.vue')
  const settings = read('src/pages/settings/page-settings.vue')
  const system = read('src/pages/system/page-system.vue')
  const builder = read('src/pages/builder/page-builder.vue')

  assert.match(dashboard, /const chartItems=computed\(/)
  assert.match(dashboard, /dashboard\.inventory_aria/)
  assert.doesNotMatch(dashboard, /chartBars=\[38,46,54,62,70,78,86\]/)
  assert.match(content, /cms-stat-grid cms-stat-grid--five/)
  assert.match(content, /content_page\.content_type/)
  assert.match(content, /content_page\.metadata_json/)
  assert.match(media, /:aria-label="`\$\{titleOf\(asset\)\} — \$\{kindLabel\(asset\.kind\)\}`"/)
  assert.match(media, /:aria-pressed="view==='list' \? 'true' : 'false'"/)
  assert.doesNotMatch(settings, /<main class="settings-main"/)
  assert.match(settings, /aria-labelledby="settings-main-heading"/)
  assert.match(system, /system_page\.health_checks/)
  assert.match(system, /system_page\.structured_logs/)
  assert.match(builder, /:title="t\('builder_page\.preview'\)"/)
})

test('phase 3 adds shared responsive states and focus treatment', () => {
  const styles = read('src/styles/admin.scss')

  assert.match(styles, /\.cms-stat-grid--five\s*\{/)
  assert.match(styles, /\.cms-page-state,\s*\.cms-empty-state\s*\{/)
  assert.match(styles, /\.cms-media-card:focus-visible/)
  assert.match(styles, /\.settings-nav button:focus-visible/)
  assert.match(styles, /@media \(min-width: 64rem\)[\s\S]*\.cms-stat-grid--five\s*\{\s*grid-template-columns: repeat\(5, minmax\(0, 1fr\)\)/)
})
