import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../..', import.meta.url)))
const read = (path) => readFileSync(resolve(packageRoot, path), 'utf8')

test('Phase 19 canonical content collection uses the scalar list projection', () => {
  const page = read('theme/cms-admin/src/pages/content/page-content.vue')
  assert.match(page, /contentApi\.list\(\{search:query\.value[\s\S]*projection:'list'\}\)/)
})

test('Phase 19 scheduled publication hydrates claimed rows in one bounded batch', () => {
  const repository = read('Cms/Content/PinooxContentRepository.php')
  const publishDue = repository.match(/public function publishDue\([\s\S]*?\n    \}\n\n    public function slugExists/)?.[0] || ''

  assert.match(publishDue, /\$publishedIds = \[\]/)
  assert.match(publishDue, /whereIn\('id', \$publishedIds\)->get\(\)/)
  assert.match(publishDue, /\$this->hydrateMany\(\$freshModels, ContentProjection::Detail\)/)
  assert.doesNotMatch(publishDue, /ContentModel::find\(/)
  assert.doesNotMatch(publishDue, /\$this->hydrate\(\$fresh, ContentProjection::Detail\)/)
})

test('Phase 19 build enforces bounded JavaScript and CSS payloads', () => {
  const packageJson = JSON.parse(read('theme/cms-admin/package.json'))
  const budget = read('theme/cms-admin/performance-budget.mjs')

  assert.match(packageJson.scripts.build, /performance-budget\.mjs/)
  assert.match(budget, /totalJsBytes: 2_200_000/)
  assert.match(budget, /totalCssBytes: 300_000/)
  assert.match(budget, /largestJsBytes: 750_000/)
  assert.match(budget, /largestCssBytes: 150_000/)
})

test('Phase 19 retains bounded backend performance budgets and query telemetry', () => {
  const budgets = read('Cms/Performance/CorePerformanceBudgets.php')
  const snapshot = read('Cms/Performance/PerformanceSnapshotService.php')
  const recorder = read('Cms/Performance/FilePerformanceRecorder.php')

  assert.match(budgets, /Typical request DB time/)
  assert.match(budgets, /Typical Queue job/)
  assert.match(snapshot, /'recent_samples'/)
  assert.match(snapshot, /'n_plus_one'/)
  assert.match(snapshot, /'slow'/)
  assert.match(recorder, /private int \$maxBytes=5_242_880/)
})
