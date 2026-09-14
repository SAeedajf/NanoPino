import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const read = (path) => readFileSync(resolve(root, path), 'utf8')

test('phase 13 registers a native production scheduler for due editorial content', () => {
  const schedule = read('../../schedule.php')
  assert.match(schedule, /CmsRuntimeServices::content\(\)->publishDue\(100\)/)
  assert.match(schedule, /cms\.content\.publish-due/)
  assert.match(schedule, /everyMinute\(\)/)
  assert.match(schedule, /withoutOverlapping\(\)/)
})

test('phase 13 publication is a compare-and-set transition with revision and audit evidence', () => {
  const repository = read('../../Cms/Content/PinooxContentRepository.php')
  const service = read('../../Cms/Content/ContentService.php')
  assert.match(repository, /where\('status', ContentStatus::Scheduled->value\)/)
  assert.match(repository, /->update\(\[/)
  assert.match(repository, /'status' => ContentStatus::Published->value/)
  assert.match(service, /content\.scheduled_publish/)
  assert.match(service, /RevisionKind::Published/)
})

test('phase 13 keeps scheduling controls and published-state feedback in the canonical editor', () => {
  const page = read('src/pages/content/page-content.vue')
  const api = read('src/services/cms-api.js')
  assert.match(page, /scheduleContent/)
  assert.match(page, /scheduled_notice/)
  assert.match(page, /scheduled_at/)
  assert.match(api, /schedule:\(id,publishAt\)/)
})
