import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent as createBuilder } from '../runtime/builder.mjs'
import { createComponent as createAppearance } from '../runtime/appearance.mjs'
import { createComponent as createBlocks } from '../runtime/blocks.mjs'
import { createComponent as createSiteEditor } from '../runtime/site-editor.mjs'

const read=(file)=>readFileSync(new URL(`../${file}`,import.meta.url),'utf8')
const readPackage=(file)=>readFileSync(new URL(`../../../${file}`,import.meta.url),'utf8')
const fake=()=>({})

test('P2 builder splits non-mutating open from explicit create in Vue and runtime',()=>{
  const vue=read('src/pages/builder/page-builder.vue')
  const runtime=read('runtime/builder.mjs')
  assert.match(vue,/async function openExistingDocument\(/)
  assert.match(vue,/async function createDocument\(/)
  assert.match(vue,/builderApi\.create\(/)
  assert.doesNotMatch(vue,/builderApi\.open\(/)
  assert.match(runtime,/async openExistingDocument\(\)/)
  assert.match(runtime,/async createDocument\(\)/)
  assert.match(runtime,/api\('\/builder',\{method:'POST'/)
  assert.doesNotMatch(runtime,/api\('\/builder\/open'/)
})

test('P2 builder does not enable authoring before a document is open',()=>{
  const vue=read('src/pages/builder/page-builder.vue')
  const runtime=read('runtime/builder.mjs')
  assert.match(vue,/:disabled="!canEdit\|\|!record"/)
  assert.match(vue,/open_before_edit/)
  assert.match(runtime,/disabled:!this\.canEdit\|\|!this\.record/)
  assert.match(runtime,/builder_page\.open_before_edit/)
  assert.match(vue,/close_dirty_confirm/)
  assert.match(runtime,/close_dirty_confirm/)
})

test('P2 builder target UI hides raw target keys behind named selectors and technical details',()=>{
  const vue=read('src/pages/builder/page-builder.vue')
  const runtime=read('runtime/builder.mjs')
  for(const source of [vue,runtime]){
    assert.match(source,/target_content/)
    assert.match(source,/target_template/)
    assert.match(source,/target_part/)
    assert.match(source,/target_site/)
    assert.match(source,/content_search_placeholder/)
    assert.match(source,/technical_target/)
    assert.doesNotMatch(source,/page:42/)
  }
})

test('P2 Persian builder copy localizes primary authoring controls',()=>{
  const fa=readPackage('lang/fa/admin.lang.php')
  assert.match(fa,/'undo'=>'واگرد'/)
  assert.match(fa,/'redo'=>'ازنو'/)
  assert.match(fa,/'preview'=>'پیش‌نمایش'/)
  assert.match(fa,/'block_library'=>'کتابخانه بلوک'/)
  assert.match(fa,/'layers'=>'لایه‌ها'/)
  assert.match(fa,/'inspector'=>'ویژگی‌ها'/)
  assert.match(fa,/'desktop'=>'دسکتاپ'/)
  assert.match(fa,/'tablet'=>'تبلت'/)
  assert.match(fa,/'mobile'=>'موبایل'/)
})

test('P2 theme center preserves Pinoox per-package stacks and scopes NanoPino site actions',()=>{
  const controller=readPackage('Controller/AdminController.php')
  const app=readPackage('app.php')
  const vue=read('src/pages/appearance/page-appearance.vue')
  const runtime=read('runtime/appearance.mjs')
  assert.match(app,/'package'\s*=>\s*'com_pinoox_cms'/)
  assert.match(controller,/\$siteThemePackage = \(string\)\(\$appMetadata\['package'\]/)
  assert.match(controller,/'sitePackage' => \$siteThemePackage/)
  for(const source of [vue,runtime]){
    assert.match(source,/sitePackage/)
    assert.match(source,/site_theme_active/)
    assert.match(source,/package_theme_active/)
    assert.match(source,/isSiteTheme/)
    assert.match(source,/no_preview/)
  }
  assert.match(vue,/isActive\(theme\)&&isSiteTheme\(theme\)/)
  assert.match(runtime,/isActive&&this\.isSiteTheme\(x\)/)
})

test('P2 block catalog stays extension-first and recognizes third-party essential capabilities',()=>{
  const vue=read('src/pages/blocks/page-blocks.vue')
  const runtime=read('runtime/blocks.mjs')
  for(const source of [vue,runtime]){
    assert.match(source,/install_block_pack/)
    assert.match(source,/essential_image/)
    assert.match(source,/essential_gallery/)
    assert.match(source,/essential_video/)
    assert.match(source,/essential_navigation/)
    assert.match(source,/essential_form/)
    assert.match(source,/tokens:\['navigation','menu'\]/)
    assert.match(source,/previewKind/)
  }
  assert.doesNotMatch(vue,/\['core\/image','core\/gallery','core\/video','core\/navigation','core\/form'\]/)
})

test('P2 full site editor preview reflects base font size and does not render an empty responsive contract',()=>{
  const vue=read('src/pages/site-editor/page-site-editor.vue')
  const runtime=read('runtime/site-editor.mjs')
  assert.match(vue,/fontSize:tokens\.fontSizes\.base\|\|'16px'/)
  assert.match(runtime,/baseSize=this\.token\('fontSizes\.base','16px'\)/)
  assert.match(runtime,/fontSize:baseSize/)
  assert.match(vue,/documentsLoaded && \(site\.breakpointFallbacks\|\|\[\]\)\.length/)
  assert.match(runtime,/this\.documentsLoaded&&\(this\.site\.breakpointFallbacks\|\|\[\]\)\.length/)
  assert.match(vue,/preview_only/)
  assert.match(runtime,/preview_only/)
  assert.match(vue,/\[doc\(\{type,key\}\)\?'open':'create'\]:1/)
})

test('P2 runtime control planes expose the expected operational methods',()=>{
  const builder=createBuilder({h:fake,LPage:fake,LPanel:fake,LButton:fake,LBadge:fake,LStatCard:fake})
  assert.equal(typeof builder.methods.openExistingDocument,'function')
  assert.equal(typeof builder.methods.createDocument,'function')
  assert.equal(typeof builder.methods.closeDocument,'function')
  const appearance=createAppearance({h:fake,LPage:fake,LPanel:fake,LButton:fake,LBadge:fake,LStatCard:fake})
  assert.equal(typeof appearance.methods.isSiteTheme,'function')
  const blocks=createBlocks({h:fake,LPage:fake,LPanel:fake,LButton:fake,LBadge:fake,LStatCard:fake})
  assert.equal(typeof blocks.methods.previewKind,'function')
  const site=createSiteEditor({h:fake,LPage:fake,LPanel:fake,LButton:fake,LBadge:fake,LStatCard:fake})
  assert.equal(typeof site.methods.open,'function')
})
