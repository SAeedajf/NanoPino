import test from 'node:test'
import assert from 'node:assert/strict'
import { createComponent as settings } from '../runtime/settings.mjs'
import { createComponent as builder } from '../runtime/builder.mjs'
import { createComponent as content } from '../runtime/content.mjs'
import { createComponent as media } from '../runtime/media.mjs'
import { createComponent as siteEditor } from '../runtime/site-editor.mjs'
import { createComponent as extensions } from '../runtime/extensions.mjs'
import { createComponent as users } from '../runtime/users.mjs'
import { readFileSync } from 'node:fs'
import { runInNewContext } from 'node:vm'

const h = (tag, props = {}, children = []) => ({ tag, props, children })
function instance(factory) {
  const component = factory({ h })
  const vm = component.data()
  for (const [key, fn] of Object.entries(component.methods)) vm[key] = fn.bind(vm)
  for (const [key, fn] of Object.entries(component.computed || {})) {
    Object.defineProperty(vm, key, { configurable: true, get: fn.bind(vm) })
  }
  return vm
}
const response = data => ({ ok: true, status: 200, json: async () => ({ success: true, data }) })
function deferred() { let resolve; const promise = new Promise(r => { resolve = r }); return { promise, resolve } }
function settingState() {
  const vm = instance(settings)
  vm.items = ['a', 'b'].map(key => ({ key, label: key, group: 'general', type: 'string', value: `old ${key}`, version: 1, scope: { type: 'site', id: 1 } }))
  vm.drafts = { a: 'new a', b: 'new b' }
  vm.originals = { a: 'old a', b: 'old b' }
  return vm
}

test('saving one setting preserves other drafts and edits made during the request', async () => {
  const vm = settingState(), pending = deferred()
  let writes = 0
  globalThis.fetch = async () => { writes++; return pending.promise }
  const save = vm.saveOne(vm.items[0])
  vm.drafts.a = 'newer a'
  await vm.saveOne(vm.items[0])
  pending.resolve(response({ key: 'a', value: 'new a', version: 2 }))
  await save
  assert.equal(writes, 1)
  assert.equal(vm.drafts.a, 'newer a')
  assert.equal(vm.drafts.b, 'new b')
  assert.equal(vm.originals.a, 'new a')
  assert.equal(vm.items[0].version, 2)
  assert.equal(vm.dirtyCount, 2)
})

test('partial settings batch acknowledges successes so retry only sends remaining writes', async () => {
  const vm = settingState(), calls = []
  let fail = true
  globalThis.fetch = async (url, options) => {
    calls.push([url.split('/').at(-1), JSON.parse(options.body).expected_version])
    if (url.endsWith('/b') && fail) throw new Error('unavailable')
    return response({ key: url.split('/').at(-1), value: url.endsWith('/a') ? 'new a' : 'new b', version: 2 })
  }
  await vm.saveAll()
  assert.equal(vm.isDirty(vm.items[0]), false)
  assert.equal(vm.isDirty(vm.items[1]), true)
  fail = false
  await vm.saveAll()
  assert.deepEqual(calls, [['a', 1], ['b', 1], ['b', 1]])
  assert.equal(vm.dirtyCount, 0)
})

test('invalid JSON blocks the whole settings batch before any write', async () => {
  const vm = settingState()
  vm.items[1].type = 'json'; vm.drafts.b = '{invalid'
  let writes = 0
  globalThis.fetch = async () => { writes++; return response({}) }
  await vm.saveAll()
  assert.equal(writes, 0)
  assert.ok(vm.error)
  assert.equal(vm.drafts.b, '{invalid')
})

test('settings refresh preserves local drafts while refreshing server metadata', async () => {
  const vm = settingState(), pending = deferred()
  globalThis.fetch = async () => pending.promise
  const load = vm.load()
  vm.drafts.b = 'typed during load'
  pending.resolve(response({ items: vm.items.map(i => ({ ...i, version: 3 })) }))
  await load
  assert.equal(vm.drafts.b, 'typed during load')
  assert.equal(vm.items[0].version, 3)
  assert.equal(vm.error, '')
})

test('reset reads inherited server value and leaves other drafts intact', async () => {
  const vm = settingState(), calls = []
  globalThis.fetch = async (url, options) => {
    calls.push(options.method)
    return response(options.method === 'DELETE' ? { deleted: true } : { key: 'a', value: 'inherited', version: null })
  }
  await vm.reset(vm.items[0])
  assert.deepEqual(calls, ['DELETE', 'GET'])
  assert.equal(vm.drafts.a, 'inherited')
  assert.equal(vm.drafts.b, 'new b')
  assert.equal(vm.isDirty(vm.items[0]), false)
})

function builderState() {
  const vm = instance(builder)
  Object.defineProperty(vm, 'canEdit', { value: true })
  Object.defineProperty(vm, 'canPublish', { value: true })
  vm.document = { version: 1, blocks: [{ id: 'a', type: 'core/text', attributes: { text: 'old' } }] }
  vm.record = { id: 1, version: 1, document: structuredClone(vm.document) }
  vm.dirty = true
  vm.syncAdvanced = () => {}
  vm.scheduleAutosave = () => {}
  vm.loadRevisions = async () => {}
  return vm
}

test('builder save acknowledges only its snapshot and keeps later edits dirty', async () => {
  const vm = builderState(), pending = deferred()
  let submitted
  globalThis.fetch = async (_url, options) => { submitted = JSON.parse(options.body); return pending.promise }
  const save = vm.saveInternal()
  vm.document.blocks[0].attributes.text = 'newer'
  pending.resolve(response({ id: 1, version: 2, document: submitted.document }))
  await save
  assert.equal(submitted.document.blocks[0].attributes.text, 'old')
  assert.equal(vm.document.blocks[0].attributes.text, 'newer')
  assert.equal(vm.record.version, 2)
  assert.equal(vm.dirty, true)
})

test('builder publish preserves unpublished edits made while publish is pending', async () => {
  const vm = builderState(), pending = deferred()
  vm.dirty = false
  globalThis.fetch = async () => pending.promise
  const publish = vm.publish()
  vm.document.blocks[0].attributes.text = 'unpublished'
  pending.resolve(response({ ...vm.record, status: 'published', version: 2 }))
  await publish
  assert.equal(vm.document.blocks[0].attributes.text, 'unpublished')
  assert.equal(vm.dirty, true)
  assert.equal(vm.record.status, 'published')
})

test('pointer cancellation clears drag state without moving any block', () => {
  const vm = builderState()
  vm.drag = { active: true, sourceId: 'a', overId: 'b', mode: 'after', pointerId: 7 }
  let moves = 0; vm.moveByDrop = () => { moves++ }
  vm.dragCancel({ pointerId: 7 })
  vm.dragEnd({ pointerId: 7 })
  assert.equal(moves, 0)
  assert.equal(vm.drag.active, false)
})

test('bulk content action remains the confirmed action and reconciles partial success', async () => {
  globalThis.confirm = () => true
  const vm = instance(content), pending = deferred(), calls = []
  vm.selected = ['1', '2']; vm.bulkAction = 'publish'; vm.load = async () => {}
  globalThis.fetch = async url => {
    calls.push(url)
    if (calls.length === 1) return pending.promise
    throw new Error('second failed')
  }
  const run = vm.runBulk()
  vm.bulkAction = 'trash'
  pending.resolve(response({}))
  await run
  assert.ok(calls.every(url => url.endsWith('/publish')))
  assert.deepEqual(vm.selected, ['2'])
  assert.equal(vm.bulkBusy, false)
  assert.match(vm.error, /second failed/)
})

test('content publish retry updates the already-created record instead of creating again', async () => {
  const vm = instance(content), calls = []
  vm.payload = () => ({ type: 'post', title: 'Draft' })
  vm.load = async () => {}
  globalThis.fetch = async (url, options) => {
    calls.push([url, options.method])
    if (url.endsWith('/publish')) throw new Error('publish failed')
    return response({ id: 5, status: 'draft' })
  }
  await vm.save(true)
  await vm.save(true)
  assert.deepEqual(calls.map(c => c[1]), ['POST', 'POST', 'PUT', 'POST'])
  assert.ok(calls[2][0].endsWith('/content/5'))
})

test('runtime settings controls identify their setting and help text', () => {
  const vm = settingState()
  for (const type of ['string', 'json', 'string_list', 'integer']) {
    const control = vm.renderControl({ ...vm.items[0], type })
    assert.equal(control.props['aria-label'], 'a')
    assert.equal(control.props['aria-describedby'], vm.helpId(vm.items[0]))
  }
})

test('canonical Vue settings load and save preserve unrelated drafts', async () => {
  const source = readFileSync(new URL('../src/pages/settings/page-settings.vue', import.meta.url), 'utf8')
  const script = source.split('<script setup>')[1].split('</script>')[0].replace(/^import .*$/gm, '')
  const rows = ['a', 'b'].map(key => ({ key, group: 'general', type: 'string', value: `old ${key}`, version: 1 }))
  const context = {
    ref: value => ({ value }), computed: getter => ({ get value() { return getter() } }), onMounted: () => {},
    t: key => key, Intl, URLSearchParams,
    settingsApi: {
      list: async () => ({ data: { items: rows.map(row => ({ ...row })) } }),
      update: async (key, payload) => ({ data: { key, value: payload.value, version: 2 } }),
    },
  }
  runInNewContext(script + '\nglobalThis.model = {load,saveOne,items,drafts,dirtyCount,error};', context)
  const vm = context.model
  await vm.load()
  assert.equal(vm.error.value, '')
  vm.drafts.value.a = 'new a'; vm.drafts.value.b = 'new b'
  await vm.saveOne(vm.items.value[0])
  assert.equal(vm.items.value[0].version, 2)
  assert.equal(vm.drafts.value.b, 'new b')
  assert.equal(vm.dirtyCount.value, 1)
})

test('user selection is a native button separate from privileged row actions', () => {
  const renderH = (tag, props = {}, children = []) => ({ tag, props, children: children?.default ? children.default() : children })
  const component = users({ h: renderH, LPage: 'LPage', LPanel: 'LPanel', LButton: 'LButton' })
  const vm = instance(users)
  vm.items = [{ id: 7, name: 'Operator', roles: [], status: 'active' }]
  vm.current = { id: 9, abilities: ['users.roles.manage'] }
  const nodes = []
  function walk(node) {
    if (Array.isArray(node)) return node.forEach(walk)
    if (!node || typeof node !== 'object') return
    nodes.push(node); walk(node.children)
  }
  walk(component.render.call(vm))
  const button = nodes.find(node => node.tag === 'button' && node.props.class?.includes('uc-user-select'))
  assert.ok(button)
  assert.equal(button.props.type, 'button')
  button.props.onClick()
  assert.equal(vm.selected.id, 7)
  const row = nodes.find(node => node.tag === 'article' && node.props.key === 7)
  assert.equal(row.props.role, undefined)
  assert.equal(row.props.tabindex, undefined)
})


test('content type changes preserve shared authoring state and require consent before incompatible field loss', () => {
  const vm = instance(content)
  vm.types = [
    { key: 'page', hierarchical: true, fields: [{ key: 'body', type: 'richtext' }, { key: 'hero', type: 'text' }] },
    { key: 'post', hierarchical: false, fields: [{ key: 'body', type: 'richtext' }, { key: 'tagline', type: 'text' }] },
  ]
  vm.form = { site_id: 1, type: 'page', title: 'Kept title', slug: 'kept', excerpt: 'Kept excerpt', locale: 'fa', parent_id: '9', fields: { body: 'Shared body', hero: 'Unsaved hero' }, metadataJson: '{}' }
  vm.previousType = 'page'
  globalThis.confirm = () => false
  vm.changeFormType('post')
  assert.equal(vm.form.type, 'page')
  assert.equal(vm.form.title, 'Kept title')
  assert.equal(vm.form.fields.hero, 'Unsaved hero')

  globalThis.confirm = () => true
  vm.changeFormType('post')
  assert.equal(vm.form.type, 'post')
  assert.equal(vm.form.title, 'Kept title')
  assert.equal(vm.form.excerpt, 'Kept excerpt')
  assert.equal(vm.form.fields.body, 'Shared body')
  assert.equal(vm.form.fields.hero, undefined)
  assert.equal(vm.form.parent_id, '')
})

test('content save acknowledges its submitted snapshot without closing over newer edits', async () => {
  const vm = instance(content), pending = deferred()
  vm.types = [{ key: 'post', fields: [] }]
  vm.form = { site_id: 1, type: 'post', title: 'Submitted', slug: '', excerpt: '', locale: 'fa', parent_id: '', fields: {}, metadataJson: '{}' }
  vm.previousType = 'post'; vm.editorOpen = true; vm.load = async () => {}
  globalThis.fetch = async () => pending.promise
  const save = vm.save(false)
  vm.form.title = 'Typed while saving'
  pending.resolve(response({ id: 21, status: 'draft', title: 'Submitted' }))
  await save
  assert.equal(vm.editing, '21')
  assert.equal(vm.form.title, 'Typed while saving')
  assert.equal(vm.editorOpen, true)
  assert.match(vm.notice, /saved_newer_draft|newer/i)
})

test('content scheduling saves the submitted content snapshot before scheduling', async () => {
  const vm = instance(content), pending = deferred(), calls = []
  vm.types = [{ key: 'post', fields: [] }]
  vm.editing = '9'; vm.scheduleAt = '2026-09-08T10:30'
  vm.form = { site_id: 1, type: 'post', title: 'Scheduled body', slug: '', excerpt: '', locale: 'fa', parent_id: '', fields: {}, metadataJson: '{}' }
  vm.load = async () => {}
  globalThis.fetch = async (url, options) => {
    calls.push([url, options.method, options.body ? JSON.parse(options.body) : null])
    if (calls.length === 1) return pending.promise
    return response({})
  }
  const run = vm.schedule()
  vm.form.title = 'Newer unsaved title'
  pending.resolve(response({ id: 9, title: 'Scheduled body', status: 'draft' }))
  await run
  assert.equal(calls.length, 2)
  assert.equal(calls[0][1], 'PUT')
  assert.equal(calls[0][2].title, 'Scheduled body')
  assert.ok(calls[1][0].endsWith('/content/9/schedule'))
  assert.equal(vm.form.title, 'Newer unsaved title')
})

test('media detail accepts only the latest selection response', async () => {
  const vm = instance(media), first = deferred(), second = deferred()
  let call = 0
  globalThis.fetch = async () => (++call === 1 ? first.promise : second.promise)
  const a = vm.openDetail({ id: 1 })
  const b = vm.openDetail({ id: 2 })
  second.resolve(response({ id: 2, kind: 'image', title: 'B', alt: '' }))
  await b
  first.resolve(response({ id: 1, kind: 'image', title: 'A', alt: '' }))
  await a
  assert.equal(vm.detail.id, 2)
  assert.equal(vm.form.title, 'B')
})

test('media metadata save preserves edits typed after the request snapshot', async () => {
  const vm = instance(media), pending = deferred()
  vm.detail = { id: 7, kind: 'image', title: 'Before' }
  vm.form = { title: 'Submitted', alt: '', caption: '', description: '', focal_x: '', focal_y: '' }
  vm.draftDirty = true
  globalThis.fetch = async (_url, options) => {
    if (options?.method === 'PATCH') return pending.promise
    return response({ items: [], summary: {}, pagination: {} })
  }
  const save = vm.saveMetadata()
  vm.form.title = 'Typed after submit'
  pending.resolve(response({ id: 7, kind: 'image', title: 'Submitted', alt: '' }))
  await save
  assert.equal(vm.form.title, 'Typed after submit')
  assert.equal(vm.draftDirty, true)
  assert.match(vm.notice, /saved_newer_draft|newer/i)
})

test('full site style save keeps newer token edits dirty', async () => {
  const vm = instance(siteEditor), pending = deferred()
  Object.defineProperty(vm, 'canCustomize', { configurable: true, value: true })
  vm.stylesLoaded = true
  vm.styleVersion = 3
  vm.styleValue = { colors: { primary: '#111111' } }
  globalThis.fetch = async () => pending.promise
  const save = vm.saveStyles()
  vm.styleValue.colors.primary = '#222222'
  pending.resolve(response({ version: 4, value: { colors: { primary: '#111111' } } }))
  await save
  assert.equal(vm.styleVersion, 4)
  assert.equal(vm.styleValue.colors.primary, '#222222')
  assert.equal(vm.styleDirty, true)
})

test('full site editor reports document and style load failures independently', async () => {
  const vm = instance(siteEditor)
  globalThis.fetch = async url => {
    if (String(url).includes('/builder?')) throw new Error('builder unavailable')
    return response({ version: 1, value: { colors: { primary: '#123456' } } })
  }
  await vm.loadAll()
  assert.equal(vm.documentsLoaded, false)
  assert.equal(vm.stylesLoaded, true)
  assert.match(vm.documentError, /builder unavailable/)
  assert.ok(vm.error)
})

test('extension package selection invalidates approval and ignores a late inspection response', async () => {
  const vm = instance(extensions), first = deferred(), second = deferred()
  let call = 0
  globalThis.fetch = async () => (++call === 1 ? first.promise : second.promise)
  vm.selectFile('package-a')
  const a = vm.inspect()
  vm.approved = true
  vm.selectFile('package-b')
  assert.equal(vm.approved, false)
  assert.equal(vm.stage, null)
  const b = vm.inspect()
  second.resolve(response({ stage: { id: 'B' }, review: { manifest: { id: 'b' } }, mode: 'install' }))
  await b
  first.resolve(response({ stage: { id: 'A' }, review: { manifest: { id: 'a' } }, mode: 'install' }))
  await a
  assert.equal(vm.stage.id, 'B')
  assert.equal(vm.review.manifest.id, 'b')
})
