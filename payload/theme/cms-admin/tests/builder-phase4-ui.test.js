import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve('.')
const read = (path) => readFileSync(resolve(root, path), 'utf8')

test('phase 4 keeps the canonical Vue Builder and runtime Builder accessible and in parity', () => {
  const vue = read('src/pages/builder/page-builder.vue')
  const runtime = read('runtime/builder.mjs')

  for (const source of [vue, runtime]) {
    assert.match(source, /cms-builder-statusbar/)
    assert.match(source, /aria-live.{0,30}polite/)
    assert.match(source, /builder_page\.canvas/)
    assert.match(source, /aria-label.{0,80}node\.id/)
    assert.match(source, /ArrowUp.*ArrowDown/)
    assert.match(source, /builder_page\.drag_hint/)
  }
  assert.match(vue, /emits:\['select','move'\]/)
  assert.match(vue, /@move="move=>\{selectedPath=move\.path;moveSelected\(move\.delta\)\}"/)
  assert.match(runtime, /this\.selectPath\(p\);this\.moveSelected\(e\.key==='ArrowUp'\?-1:1\)/)
})

test('phase 4 provides Builder workspace focus, selection and touch affordances', () => {
  const styles = read('src/styles/admin.scss')

  assert.match(styles, /\.cms-builder-live-block\[role='button'\]:focus-visible/)
  assert.match(styles, /\.cms-builder-live-block\.selected/)
  assert.match(styles, /\.cms-builder-drag-handle\s*\{[\s\S]*min-inline-size: var\(--cms-touch-target\)/)
  assert.match(styles, /\.cms-builder-statusbar/)
})
