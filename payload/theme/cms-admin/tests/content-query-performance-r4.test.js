import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const packageRoot = resolve(fileURLToPath(new URL('../../..', import.meta.url)))
const read = (path) => readFileSync(resolve(packageRoot, path), 'utf8')

test('R4 content collection hydration is batch-bound instead of N+1', () => {
  const repo = read('Cms/Content/PinooxContentRepository.php')
  const budget = read('Cms/Content/ContentQueryBudget.php')

  assert.match(repo, /private function hydrateMany\(/)
  assert.match(repo, /whereIn\('content_id', \$ids\)/)
  assert.match(repo, /whereIn\('source_content_id', \$ids\)/)
  assert.equal((repo.match(/whereIn\('content_id', \$ids\)/g) || []).length, 2)
  assert.doesNotMatch(repo, /->map\(fn \(ContentModel \$model\): ContentRecord => \$this->hydrate\(\$model\)\)/)
  assert.match(budget, /public const LIST_LIMIT = 1;/)
  assert.match(budget, /public const FULL_LIMIT = 4;/)
})

test('R4 list projection omits heavy payload and association reads', () => {
  const projection = read('Cms/Content/ContentProjection.php')
  const repo = read('Cms/Content/PinooxContentRepository.php')
  const controller = read('Controller/Api/ContentRuntimeApiController.php')
  const contract = JSON.parse(read('resources/api/content-v1.json'))

  assert.match(projection, /case List = 'list';/)
  assert.match(repo, /private const LIST_COLUMNS/)
  assert.doesNotMatch(repo.match(/private const LIST_COLUMNS = \[[\s\S]*?\];/)?.[0] || '', /'document'|'metadata'/)
  assert.match(repo, /if \(\$projection->includesAssociations\(\)\)/)
  assert.match(controller, /ContentProjection::tryFrom\(\$projectionRaw\)/)
  assert.match(controller, /before_id/)
  assert.equal(contract.repository_query_budget.list_projection.limit, 1)
  assert.equal(contract.repository_query_budget.detail_or_editor_projection.limit, 4)
})

test('R4 relation and taxonomy validation use batch findMany', () => {
  const service = read('Cms/Content/ContentService.php')
  const contentRepo = read('Cms/Content/ContentRepositoryInterface.php')
  const termRepo = read('Cms/Taxonomy/TermRepositoryInterface.php')

  assert.match(contentRepo, /public function findMany\(/)
  assert.match(termRepo, /public function findMany\(/)
  assert.match(service, /\$this->repository->findMany\(/)
  assert.ok((service.match(/\$this->terms->findMany\(/g) || []).length >= 2)
  assert.match(service, /ContentProjection::List/)
})

test('R4 migration adds idempotent indexes for cursor and batch hydration', () => {
  const migration = read('database/migrations/2026_09_05_180000_add_content_read_performance_indexes.php')

  for (const index of [
    'cms_content_site_cursor_index',
    'cms_content_relation_hydration_index',
    'cms_content_term_hydration_index',
  ]) {
    assert.match(migration, new RegExp(index))
  }
  assert.ok((migration.match(/hasIndex\(/g) || []).length >= 6)
})

test('R4 Pinoox query probe binds real database telemetry fail-open', () => {
  const probe = read('Cms/Performance/Query/PinooxQueryProbe.php')
  const binder = read('Cms/Runtime/CmsRuntimeBinder.php')
  const services = read('Cms/Runtime/CmsRuntimeServices.php')
  const snapshot = read('Cms/Performance/PerformanceSnapshotService.php')

  assert.match(probe, /CmsDatabase::connection\(\)->listen/)
  assert.match(probe, /QueryExecuted/)
  assert.match(probe, /MAX_OBSERVATIONS = 1000/)
  assert.match(binder, /CmsRuntimeServices::bindQueryProbe\(\)/)
  assert.match(services, /self::bindQueryProbe\(\);/)
  assert.match(snapshot, /'bound'=>\$this->queries->isBound\(\)/)
  assert.match(snapshot, /'binding_error'=>\$this->queries->bindingError\(\)/)
})
