import test from 'node:test'
import assert from 'node:assert/strict'
import { createComponent as settings } from '../runtime/settings.mjs'
import { createComponent as builder } from '../runtime/builder.mjs'
import { createComponent as content } from '../runtime/content.mjs'
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
