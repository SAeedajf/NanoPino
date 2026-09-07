import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent as createBuilder } from '../runtime/builder.mjs'
import { createComponent as createSiteEditor } from '../runtime/site-editor.mjs'
import { navigate } from '../runtime/common.mjs'

const read = (file) => readFileSync(new URL(`../${file}`, import.meta.url), 'utf8')
const fake = () => ({})

test('runtime visual builder exposes the operational editing surface', () => {
  const component = createBuilder({ h: fake, LPage: fake, LPanel: fake, LButton: fake, LBadge: fake, LStatCard: fake })
  assert.equal(component.name, 'CmsVisualSiteBuilderCenter')
  assert.equal(typeof component.methods.insertBlock, 'function')
  assert.equal(typeof component.methods.undo, 'function')
  assert.equal(typeof component.methods.redo, 'function')
  assert.equal(typeof component.methods.autosave, 'function')
  assert.equal(typeof component.methods.preview, 'function')
  assert.equal(typeof component.methods.restore, 'function')
})

test('runtime builder defaults to a valid Pinoox CMS target and rejects the legacy page target contract', () => {
  const runtime = read('runtime/builder.mjs')
  assert.match(runtime, /type:'template',key:'home'/)
  assert.match(runtime, /value:'content'/)
  assert.match(runtime, /value:'template'/)
  assert.match(runtime, /value:'template_part'/)
  assert.match(runtime, /value:'site'/)
  assert.doesNotMatch(runtime, /type:'page'/)
  assert.doesNotMatch(runtime, /value:'page'/)
})

test('runtime builder has read inventory path, schema inspector, responsive styles and safe publish', () => {
  const runtime = read('runtime/builder.mjs')
  assert.match(runtime, /api\(`\/builder\?site_id=/)
  assert.match(runtime, /selectedDefinition/)
  assert.match(runtime, /responsive\[this\.viewport\]/)
  assert.match(runtime, /builder_page\.block_library/)
  assert.match(runtime, /builder_page\.layers/)
  assert.match(runtime, /builder_page\.inspector/)
  assert.match(runtime, /setInterval\(\(\)=>this\.backgroundAutosave\(\{silent:true\}\),30000\)/)
  assert.match(runtime, /if\(this\.dirty\)await this\.saveInternal\(\)/)
  assert.match(runtime, /\/builder\/preview/)
  assert.match(runtime, /\/revisions\/\$\{rev\.id\}\/restore/)
  assert.match(runtime, /builder_page\.advanced_json/)
})

test('builder interaction layer is pointer/touch friendly and avoids duplicate autosave chatter', () => {
  const runtime = read('runtime/builder.mjs')
  const builderPage = read('src/pages/builder/page-builder.vue')
  for (const source of [runtime, builderPage]) {
    assert.match(source, /pointermove/)
    assert.match(source, /pointerup/)
    assert.match(source, /lastAutosaveSignature/)
    assert.match(source, /8000/)
    assert.match(source, /beforeunload/)
    assert.match(source, /dropClass/)
  }
  assert.match(runtime, /drop-(before|after|inside)/)
  assert.match(runtime, /touch-action:none/)
  assert.match(read('src/styles/admin.scss'), /touch-action:none/)
  assert.match(runtime, /findPathById/)
  assert.match(runtime, /moveByDrop/)
  assert.match(runtime, /lastMutationKey/)
})

test('full site editor exposes template inventory and global style override operations', () => {
  const component = createSiteEditor({ h: fake, LPage: fake, LPanel: fake, LButton: fake, LBadge: fake, LStatCard: fake })
  assert.equal(component.name, 'CmsFullSiteEditorCenter')
  assert.equal(typeof component.methods.loadDocuments, 'function')
  assert.equal(typeof component.methods.saveStyles, 'function')
  assert.equal(typeof component.methods.resetStyles, 'function')

  const runtime = read('runtime/site-editor.mjs')
  assert.match(runtime, /api\(`\/builder\?site_id=/)
  assert.match(runtime, /theme\.design\.overrides/)
  assert.match(runtime, /themes\.customize/)
  assert.match(runtime, /template_part/)
  assert.match(runtime, /header/)
  assert.match(runtime, /footer/)
  assert.match(runtime, /theme-fallback/)
})

test('canonical Vue and API sources preserve the new builder and site-editor contracts', () => {
  const builderPage = read('src/pages/builder/page-builder.vue')
  const sitePage = read('src/pages/site-editor/page-site-editor.vue')
  const api = read('src/services/cms-api.js')
  assert.match(builderPage, /builderApi\.list/)
  assert.match(builderPage, /setInterval\(\(\)=>autosave\(true\),30000\)/)
  assert.match(builderPage, /if\(dirty\.value\)await saveInternal\(\)/)
  assert.match(sitePage, /builderApi\.list/)
  assert.match(sitePage, /theme\.design\.overrides/)
  assert.match(api, /list:\(params=\{\}\)=>\{const q=new URLSearchParams/)
})

test('nested runtime navigation resolves registered paths from the active admin mount', () => {
  const previousWindow = globalThis.window
  const previousPopStateEvent = globalThis.PopStateEvent
  const pushed = []
  globalThis.PopStateEvent = class PopStateEvent { constructor(type) { this.type = type } }
  globalThis.window = {
    __PINOOX__: {
      cmsAdmin: {
        mountPath: '/manager',
        manifest: {
          routes: [
            { id: 'cms.site_editor', name: 'cms.site_editor', path: '/appearance/site-editor' },
            { id: 'cms.updates', name: 'cms.updates', path: '/extensions/updates' },
          ],
        },
      },
    },
    history: { pushState: (_state, _title, path) => pushed.push(path) },
    dispatchEvent: () => {},
  }

  try {
    navigate('site-editor')
    navigate('updates')
    assert.deepEqual(pushed, ['/manager/appearance/site-editor', '/manager/extensions/updates'])
  } finally {
    globalThis.window = previousWindow
    globalThis.PopStateEvent = previousPopStateEvent
  }
})
