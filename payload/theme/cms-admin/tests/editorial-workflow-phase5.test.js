import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const read = (path) => readFileSync(resolve(root, path), 'utf8')

test('phase 5 exposes the editorial workflow in canonical Vue and runtime fallback', () => {
  const vue = read('src/pages/content/page-content.vue')
  const runtime = read('runtime/content.mjs')

  for (const source of [vue, runtime]) {
    assert.match(source, /workflow_title/)
    assert.match(source, /workflow_help/)
    assert.match(source, /workflow_steps_aria/)
    assert.match(source, /pending_review/)
    assert.match(source, /approved/)
    assert.match(source, /published/)
    assert.match(source, /archived/)
    assert.match(source, /workflowStage/)
  }
  assert.match(vue, /cms-editorial-workflow__steps/)
  assert.match(vue, /role="region" :aria-labelledby="content-bulk-heading"/)
  assert.match(runtime, /cms-editorial-workflow-steps/)
  assert.match(runtime, /workflowSteps\(\)/)
})

test('phase 5 keeps editorial actions permission-aware and gives specific success feedback', () => {
  const vue = read('src/pages/content/page-content.vue')
  const runtime = read('runtime/content.mjs')

  for (const action of ['submitReview', 'approve', 'publish', 'archive']) {
    assert.match(vue, new RegExp(`contentApi\\.${action}`))
  }
  for (const notice of ['submitted_notice', 'approved_notice', 'published_notice', 'archived_notice']) {
    assert.match(vue, new RegExp(notice))
    assert.match(runtime, new RegExp(notice))
  }
  assert.match(vue, /canSubmitReview/)
  assert.match(vue, /canApprove/)
  assert.match(vue, /canArchive/)
  assert.match(runtime, /permissions\.submitReview/)
  assert.match(runtime, /permissions\.approve/)
  assert.match(runtime, /permissions\.archive/)
})

test('phase 5 workflow is responsive, keyboard-readable, and localized', () => {
  const vue = read('src/pages/content/page-content.vue')
  const fa = read('../../lang/fa/admin.lang.php')
  const en = read('../../lang/en/admin.lang.php')

  assert.match(vue, /@media\(max-width:900px\)/)
  assert.match(vue, /@media\(max-width:600px\)/)
  assert.match(vue, /prefers-reduced-motion:reduce/)
  assert.match(vue, /aria-current/)
  for (const source of [fa, en]) {
    assert.match(source, /workflow_title/)
    assert.match(source, /workflow_draft_hint/)
    assert.match(source, /published_notice/)
  }
})
