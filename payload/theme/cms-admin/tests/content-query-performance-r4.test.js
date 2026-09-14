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

test('R4 content mutations batch association writes inside the existing transaction', () => {
  const repo = read('Cms/Content/PinooxContentRepository.php')

  assert.match(repo, /->lockForUpdate\(\)/)
  assert.match(repo, /ContentFieldValueModel::query\(\)->upsert\(/)
  assert.match(repo, /ContentRelationModel::query\(\)->insert\(\$rows\)/)
  assert.match(repo, /ContentTermModel::query\(\)->insert\(\$rows\)/)
  assert.doesNotMatch(repo, /ContentFieldValueModel::create\(/)
  assert.doesNotMatch(repo, /ContentRelationModel::create\(/)
  assert.doesNotMatch(repo, /ContentTermModel::create\(/)
})

test('R4 standalone taxonomy assignment batches term rows after replacement', () => {
  const repo = read('Cms/Taxonomy/PinooxTermRepository.php')

  assert.match(repo, /ContentTermModel::query\(\)->insert\(\$rows\)/)
  assert.match(repo, /array_values\(array_unique\(array_map\('intval', \$termIds\)\)\)/)
  assert.match(repo, /'created_at' => \$now/)
  assert.match(repo, /'updated_at' => \$now/)
  assert.doesNotMatch(repo, /ContentTermModel::create\(/)
})

test('R4 media library default listing has a site-status cursor index', () => {
  const migration = read('database/migrations/2026_09_10_195000_add_media_status_read_index.php')

  assert.match(migration, /cms_media_status_cursor_index/)
  assert.match(migration, /\['site_id', 'status', 'id'\]/)
  assert.equal((migration.match(/hasIndex\(/g) || []).length, 2)
})

test('R4 revision history list uses metadata projection while preview keeps full snapshots', () => {
  const repository = read('Cms/Revision/PinooxRevisionRepository.php')
  const interfaceSource = read('Cms/Revision/RevisionRepositoryInterface.php')
  const service = read('Cms/Revision/RevisionService.php')
  const controller = read('Controller/Api/ContentRuntimeApiController.php')

  assert.match(interfaceSource, /summariesForContent\(/)
  assert.match(repository, /private const SUMMARY_COLUMNS = \[/)
  assert.match(repository, /->select\(self::SUMMARY_COLUMNS\)/)
  assert.match(service, /public function historySummary\(/)
  assert.match(service, /\$this->revisions->summariesForContent\(/)
  assert.match(service, /\$this->revisions->forContent\(/)
  assert.match(controller, /CmsRuntimeServices::revisions\(\)->historySummary\(/)
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

test('R4 content status listings have a site-scoped cursor index', () => {
  const migration = read('database/migrations/2026_09_10_193000_add_content_status_read_index.php')

  assert.match(migration, /cms_content_status_cursor_index/)
  assert.match(migration, /\['site_id', 'status', 'locale', 'id'\]/)
  assert.equal((migration.match(/hasIndex\(/g) || []).length, 2)
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

test('R4 database search narrows hit projections and has deterministic pagination order', () => {
  const driver = read('Cms/Search/PinooxDatabaseSearchDriver.php')
  const migration = read('database/migrations/2026_09_10_190000_add_search_read_indexes.php')

  assert.match(driver, /private const HIT_COLUMNS = \[/)
  assert.match(driver, /->select\(self::HIT_COLUMNS\)/)
  assert.match(driver, /->orderByDesc\('updated_at'\)\s*\n\s*->orderByDesc\('id'\)/)
  assert.match(migration, /cms_search_site_updated_index/)
  assert.match(migration, /cms_search_scope_updated_index/)
  assert.ok((migration.match(/hasIndex\(/g) || []).length >= 4)
})

test('R4 Builder summary listing avoids hydrating document JSON while full reads stay available', () => {
  const repository = read('Cms/Builder/PinooxBuilderDocumentRepository.php')
  const repositoryInterface = read('Cms/Builder/BuilderDocumentRepositoryInterface.php')
  const service = read('Cms/Builder/BuilderService.php')
  const facade = read('Cms/Api/V1/Builder/BuilderApiFacade.php')
  const siteEditor = read('theme/cms-admin/src/pages/site-editor/page-site-editor.vue')
  const builder = read('theme/cms-admin/src/pages/builder/page-builder.vue')

  assert.match(repositoryInterface, /listSummaries\(/)
  assert.match(repository, /private const SUMMARY_COLUMNS = \[/)
  assert.doesNotMatch(repository.match(/private const SUMMARY_COLUMNS = \[[\s\S]*?\];/)?.[0] || '', /document_json/)
  assert.match(repository, /->select\(self::SUMMARY_COLUMNS\)/)
  assert.match(repository, /private function summary\(/)
  assert.match(service, /public function listDocumentSummaries\(/)
  assert.match(service, /\$this->documents->listSummaries\(/)
  assert.match(facade, /\$projection === 'summary'/)
  assert.match(facade, /summaryRecord\(/)
  assert.match(facade, /'document' => \$record->document->toArray\(\)/)
  assert.match(siteEditor, /projection:'summary'/)
  assert.match(builder, /projection:'summary'/)
})

test('R4 Builder summary ordering has a site-scoped covering index', () => {
  const migration = read('database/migrations/2026_09_10_201000_add_builder_listing_index.php')

  assert.match(migration, /cms_builder_target_list_index/)
  assert.match(migration, /\['site_id', 'target_type', 'target_key', 'locale', 'id'\]/)
  assert.equal((migration.match(/hasIndex\(/g) || []).length, 2)
})

test('R4 user status summary uses one conditional aggregate query', () => {
  const repository = read('Cms/Identity/PinooxIdentityRepository.php')

  assert.match(repository, /->selectRaw\('COUNT\(\*\) AS total'\)/)
  assert.equal((repository.match(/->selectRaw\('SUM\(CASE WHEN status = \? THEN 1 ELSE 0 END\)/g) || []).length, 4)
  assert.match(repository, /->first\(\)/)
  assert.doesNotMatch(repository, /statusCount/)
})
