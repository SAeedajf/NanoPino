import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { createComponent as content } from '../runtime/content.mjs'
import { createComponent as media } from '../runtime/media.mjs'
import { createComponent as revisions } from '../runtime/revisions.mjs'

const h=(tag,props={},children=[])=>({tag,props,children})
function instance(factory){
  const component=factory({h})
  const vm=component.data()
  for(const [key,fn] of Object.entries(component.methods||{}))vm[key]=fn.bind(vm)
  for(const [key,fn] of Object.entries(component.computed||{}))Object.defineProperty(vm,key,{configurable:true,get:fn.bind(vm)})
  return vm
}
const response=data=>({ok:true,status:200,json:async()=>({success:true,data})})

test('P1 core post authoring registers taxonomy fields instead of hard-coded UI-only terms',()=>{
  const fields=readFileSync(new URL('../../../Cms/Field/CoreFields.php',import.meta.url),'utf8')
  const types=readFileSync(new URL('../../../Cms/Content/CoreContentTypes.php',import.meta.url),'utf8')
  assert.match(fields,/FieldType::Taxonomy/)
  assert.match(fields,/'taxonomy' => 'category'/)
  assert.match(fields,/'taxonomy' => 'tag'/)
  assert.match(types,/fields: \['content', 'featured_media', 'related_content', 'categories', 'tags'\]/)
})

test('taxonomy picker uses the versioned term API and applies selected IDs as structured values',async()=>{
  const vm=instance(content)
  vm.types=[{key:'post',fields:[{key:'categories',label:'Categories',type:'taxonomy',storage:'taxonomy',multiple:true,taxonomy:'category'}]}]
  vm.form={site_id:1,type:'post',title:'Post',slug:'',excerpt:'',locale:'fa',parent_id:'',fields:{categories:[]},metadataJson:'{}'}
  let requested=''
  globalThis.fetch=async url=>{requested=String(url);return response({items:[{id:7,name:'News',slug:'news'}],pagination:{limit:24,offset:0,total:1,has_more:false}})}
  vm.openFieldPicker(vm.types[0].fields[0])
  await vm.loadPicker(true)
  assert.match(requested,/\/taxonomies\/category\/terms\?/)
  assert.equal(vm.picker.items[0].name,'News')
  vm.togglePickerItem(vm.picker.items[0])
  vm.applyPicker()
  assert.deepEqual(vm.form.fields.categories,[7])
})

test('parent picker searches independently of the current content page and excludes the edited record',async()=>{
  const vm=instance(content)
  vm.types=[{key:'page',hierarchical:true,fields:[]}]
  vm.editing='9'
  vm.form={site_id:1,type:'page',title:'Child',slug:'',excerpt:'',locale:'fa',parent_id:'',fields:{},metadataJson:'{}'}
  globalThis.fetch=async()=>response({items:[{id:9,type:'page',title:'Self'},{id:31,type:'page',title:'Remote parent'}],pagination:{limit:24,offset:0,total:2,has_more:false}})
  vm.openParentPicker()
  await vm.loadPicker(true)
  assert.deepEqual(vm.picker.items.map(item=>item.id),[31])
  vm.togglePickerItem(vm.picker.items[0])
  assert.equal(vm.form.parent_id,'31')
  assert.equal(vm.parentLabel,'Remote parent')
})

test('media upload rows can be retried or removed independently',async()=>{
  const vm=instance(media)
  const firstFile=new Blob(['a'],{type:'image/jpeg'});Object.defineProperty(firstFile,'name',{value:'a.jpg'})
  const secondFile=new Blob(['b'],{type:'image/jpeg'});Object.defineProperty(secondFile,'name',{value:'b.jpg'})
  const failed={id:'a',file:firstFile,name:'a.jpg',size:firstFile.size,status:'error',error:'network'}
  const other={id:'b',file:secondFile,name:'b.jpg',size:secondFile.size,status:'error',error:'network'}
  vm.uploads=[failed,other]
  vm.load=async()=>{}
  globalThis.fetch=async()=>response({id:44})
  failed.status='queued'
  await vm.runUploads()
  assert.equal(failed.status,'done')
  assert.equal(other.status,'error')
  vm.cancelUpload(other)
  assert.deepEqual(vm.uploads.map(row=>row.id),['a'])
})

test('revision content search is paginated and not capped to the first 100 records',async()=>{
  const vm=instance(revisions)
  let requested=''
  globalThis.fetch=async url=>{requested=String(url);return response({items:[{id:151,type:'post',title:'Later content'}],pagination:{limit:25,offset:125,total:151,has_more:true}})}
  vm.contentPage.offset=125
  await vm.searchContents()
  assert.match(requested,/limit=25/)
  assert.match(requested,/offset=125/)
  assert.match(requested,/projection=list/)
  assert.equal(vm.contents[0].id,151)
})

test('revision preview computes differences against current canonical content before restore',()=>{
  const vm=instance(revisions)
  vm.selectedContent={id:3,site_id:1,type:'post',status:'draft',title:'Current',slug:'current',excerpt:'new',locale:'fa',document:{content:'current body'},metadata:{},fields:{},relations:{},terms:{}}
  vm.preview={id:8,kind:'manual',snapshot:{site_id:1,content_type:'post',status:'draft',title:'Older',slug:'current',excerpt:'old',author_id:null,parent_id:null,locale:'fa',document:{content:'old body'},metadata:{},fields:{},relations:{},terms:{},published_at:null,scheduled_at:null}}
  const paths=vm.diffRows().map(row=>row.path)
  assert.ok(paths.includes('title'))
  assert.ok(paths.includes('excerpt'))
  assert.ok(paths.includes('document.content'))
})

test('P1 runtime routes and machine contracts cover taxonomy terms and revision preview',()=>{
  const manifest=readFileSync(new URL('../../../Cms/Runtime/CmsRuntimeApiManifest.php',import.meta.url),'utf8')
  const contentContract=JSON.parse(readFileSync(new URL('../../../resources/api/content-v1.json',import.meta.url),'utf8'))
  const taxonomyContract=JSON.parse(readFileSync(new URL('../../../resources/api/taxonomy-v1.json',import.meta.url),'utf8'))
  assert.match(manifest,/\/taxonomies\/\{key\}\/terms/)
  assert.match(manifest,/cms\.content\.revision\.show/)
  assert.ok(contentContract.routes.some(route=>route.method==='GET'&&route.path==='/api/v1/cms/content/{id}/revisions/{revisionId}'))
  assert.ok(taxonomyContract.routes.some(route=>route.path==='/api/v1/cms/taxonomies/{key}/terms'))
})
