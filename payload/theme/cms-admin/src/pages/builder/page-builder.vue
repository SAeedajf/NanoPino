<template>
  <LPage icon="panels-top-left" class="cms-builder-page">
    <template #actions>
      <div class="cms-card-actions">
        <LButton icon="folder-open" variant="outline" shape="rounded" :loading="busy" @click="openDocument">{{ t('builder_page.open') }}</LButton>
        <LButton icon="undo-2" variant="outline" shape="rounded" :disabled="!canUndo" @click="undo">{{ t('builder_page.undo') }}</LButton>
        <LButton icon="redo-2" variant="outline" shape="rounded" :disabled="!canRedo" @click="redo">{{ t('builder_page.redo') }}</LButton>
        <LButton icon="eye" variant="outline" shape="rounded" :disabled="busy||!canPreview" @click="preview">{{ t('builder_page.preview') }}</LButton>
        <LButton icon="save" shape="rounded" :disabled="!record||busy||!canEdit" @click="save">{{ t('builder_page.save') }}</LButton>
        <LButton icon="send" shape="rounded" :disabled="!record||busy||!canPublish" @click="publish">{{ t('builder_page.publish') }}</LButton>
      </div>
    </template>

    <div v-if="error" class="cms-alert cms-alert--danger">{{ error }}</div>
    <div v-if="notice" class="cms-alert cms-alert--success">{{ notice }}</div>

    <div class="cms-stat-grid">
      <LStatCard :label="t('builder_page.status')" :value="record?.status || t('builder_page.not_open')" icon="circle-dot" />
      <LStatCard :label="t('builder_page.version')" :value="String(record?.version || '—')" icon="git-branch" />
      <LStatCard :label="t('builder_page.blocks')" :value="String(blockCount)" icon="blocks" />
      <LStatCard :label="t('builder_page.state')" :value="dirty ? t('builder_page.unsaved') : t('builder_page.synced')" icon="save" />
    </div>

    <LPanel><template #header>{{ t('builder_page.target') }}</template>
      <div class="cms-control-form">
        <label>{{ t('builder_page.type') }}<select v-model="target.type"><option value="content">Content</option><option value="template">Template</option><option value="template_part">Template Part</option><option value="site">Site</option></select></label>
        <label>Key<input v-model.trim="target.key" type="text" dir="ltr"></label>
        <label>Locale<input v-model.trim="target.locale" type="text" dir="ltr"></label>
        <label>Site ID<input v-model.number="target.site_id" type="number" min="1"></label>
      </div>
    </LPanel>

    <div class="cms-builder-shell cms-builder-shell--control">
      <aside class="cms-builder-sidebar cms-builder-sidebar--start is-mobile-active">
        <div class="cms-builder-panel-scroll">
          <div class="cms-builder-panel-head"><strong>{{ t('builder_page.block_library') }}</strong><LBadge severity="secondary">{{ filteredBlocks.length }}</LBadge></div>
          <input v-model.trim="blockSearch" class="cms-control-input" type="search" :placeholder="t('builder_page.block_search')" :aria-label="t('a11y.builder_block_search')">
          <div class="cms-builder-block-library">
            <button v-for="block in filteredBlocks" :key="block.id" type="button" class="cms-builder-block-item" :disabled="!canEdit" @click="insertBlock(block)">
              <LIcon :name="block.icon||'box'" :size="18"/><span><strong>{{ block.title }}</strong><small>{{ block.name }}</small></span>
            </button>
          </div>
          <div class="cms-builder-panel-head"><strong>{{ t('builder_page.layers') }}</strong></div>
          <div class="cms-stack"><button v-for="row in flatLayers" :key="row.node.id" type="button" class="cms-builder-block-item" :class="{active:pathKey(row.path)===pathKey(selectedPath)}" :style="{paddingInlineStart:`${10+row.depth*14}px`}" @click="selectedPath=[...row.path]"><span><strong>{{ row.node.type }}</strong><small>{{ row.node.id }}</small></span></button></div>
        </div>
      </aside>

      <main class="cms-builder-canvas-wrap">
        <div class="cms-builder-canvas-frame" :data-viewport="viewport">
          <div v-if="!document.blocks.length" class="cms-builder-empty-panel"><LIcon name="blocks" :size="32"/><strong>{{ t('builder_page.canvas_empty') }}</strong><p>{{ t('builder_page.add_block') }}</p></div>
          <div v-else class="cms-builder-canvas cms-builder-canvas--interactive">
            <BuilderNode v-for="(node,index) in document.blocks" :key="node.id" :node="node" :path="[index]" :selected-path="selectedPath" :start-drag="startDrag" :drop-class="dropClass" @select="selectedPath=$event" />
          </div>
          <div class="cms-builder-root-drop" :class="{active:drag.active&&drag.mode==='root'}" data-builder-root-drop="1">{{ drag.active ? t('builder_page.drop_root') : '' }}</div>
        </div>
      </main>

      <aside class="cms-builder-sidebar cms-builder-sidebar--end is-mobile-active">
        <div class="cms-builder-panel-scroll">
          <div class="cms-builder-panel-head"><strong>{{ t('builder_page.inspector') }}</strong><LBadge severity="secondary">{{ viewport }}</LBadge></div>
          <template v-if="selectedNode && selectedDefinition">
            <div class="cms-stack">
              <label v-for="field in selectedDefinition.attributes||[]" :key="field.name">{{ field.name }}
                <select v-if="field.rules?.enum" :value="selectedNode.attributes?.[field.name] ?? field.default ?? ''" :disabled="!canEdit" @change="updateAttribute(field.name,$event.target.value)"><option v-for="value in field.rules.enum" :key="value" :value="value">{{ value }}</option></select>
                <input v-else-if="field.type!=='boolean'" :value="selectedNode.attributes?.[field.name] ?? field.default ?? ''" :type="field.type==='integer'||field.type==='number'?'number':field.type==='url'?'url':'text'" :disabled="!canEdit" @input="updateAttribute(field.name,field.type==='integer'||field.type==='number'?Number($event.target.value):$event.target.value)">
                <input v-else type="checkbox" :checked="Boolean(selectedNode.attributes?.[field.name])" :disabled="!canEdit" @change="updateAttribute(field.name,$event.target.checked)">
              </label>
            </div>
            <div class="cms-stack">
              <strong>{{ t('builder_page.responsive_styles', { viewport }) }}</strong>
              <label>{{ t('builder_page.text_color') }}<input type="color" :value="styleValue('color')||'#111827'" :disabled="!canEdit" @input="updateStyle('color',$event.target.value)"></label>
              <label>{{ t('builder_page.background') }}<input type="color" :value="styleValue('backgroundColor')||'#ffffff'" :disabled="!canEdit" @input="updateStyle('backgroundColor',$event.target.value)"></label>
              <label>{{ t('builder_page.font_size') }}<input type="text" :value="styleValue('fontSize')" placeholder="16px" :disabled="!canEdit" @input="updateStyle('fontSize',$event.target.value)"></label>
              <label>{{ t('builder_page.padding') }}<input type="text" :value="styleValue('padding')" placeholder="16px" :disabled="!canEdit" @input="updateStyle('padding',$event.target.value)"></label>
              <label>{{ t('builder_page.gap') }}<input type="text" :value="styleValue('gap')" placeholder="12px" :disabled="!canEdit" @input="updateStyle('gap',$event.target.value)"></label>
            </div>
            <div class="cms-card-actions"><LButton size="sm" variant="outline" :disabled="!canEdit" :aria-label="t('a11y.builder_move_up')" @click="moveSelected(-1)">↑</LButton><LButton size="sm" variant="outline" :disabled="!canEdit" :aria-label="t('a11y.builder_move_down')" @click="moveSelected(1)">↓</LButton><LButton size="sm" variant="outline" :disabled="!canEdit" @click="nestIntoPrevious">{{ t('builder_page.nest') }}</LButton><LButton size="sm" variant="outline" :disabled="!canEdit" @click="moveOut">{{ t('builder_page.move_out') }}</LButton><LButton size="sm" variant="outline" :disabled="!canEdit" @click="duplicateSelected">{{ t('builder_page.duplicate') }}</LButton><LButton size="sm" severity="danger" variant="outline" :disabled="!canEdit" @click="removeSelected">{{ t('builder_page.delete') }}</LButton></div>
          </template>
          <p v-else class="cms-muted">{{ t('builder_page.select_block') }}</p>
          <div class="cms-builder-viewports" role="group" :aria-label="t('a11y.builder_viewport')"><LButton v-for="v in viewports" :key="v" size="sm" shape="rounded" :variant="viewport===v?'solid':'outline'" :aria-pressed="viewport===v" @click="viewport=v">{{ v }}</LButton></div>
        </div>
      </aside>
    </div>

    <LPanel v-if="previewHtml"><template #header>{{ t('builder_page.preview_title', { viewport }) }}</template><iframe class="cms-builder-preview-frame" :srcdoc="previewHtml" sandbox="" title="Builder preview" :style="{maxWidth:viewport==='mobile'?'390px':viewport==='tablet'?'768px':'100%',margin:'0 auto'}" /></LPanel>
    <LPanel v-if="record"><template #header>{{ t('builder_page.revision_history') }}</template><div class="cms-card-actions"><LButton variant="outline" :disabled="!canEdit" @click="autosave">{{ t('builder_page.autosave') }}</LButton><LButton variant="outline" @click="loadRevisions">{{ t('builder_page.load') }}</LButton></div><div class="cms-stack"><article v-for="rev in revisions" :key="rev.id" class="cms-list-card cms-list-card--wide"><div><strong>{{ rev.kind }}</strong><small>#{{ rev.id }} · {{ rev.created_at }}</small></div><LButton size="sm" variant="outline" :disabled="!canEdit" @click="restoreRevision(rev)">{{ t('builder_page.restore') }}</LButton></article></div></LPanel>
    <details><summary>{{ t('builder_page.advanced_json') }}</summary><textarea v-model="advancedJson" class="cms-builder-json-editor" rows="18" dir="ltr" spellcheck="false" :disabled="!canEdit" @input="syncFromJson" /></details>
  </LPage>
</template>
<script setup>
import { computed,defineComponent,h,onBeforeUnmount,onMounted,reactive,ref } from 'vue';import { useRoute } from 'vue-router';import { LBadge,LButton,LIcon,LPage,LPanel,LStatCard } from '@pinooxhq/luma/ui';import { readAdminBootData } from '../../services/admin-provider.js';import { builderApi } from '../../services/cms-api.js';import { t } from '../../i18n/index.js'
const boot=readAdminBootData(),route=useRoute(),target=reactive({site_id:Number(route.query.site_id||1),type:String(route.query.type||'template'),key:String(route.query.key||'home'),locale:String(route.query.locale||'fa')}),record=ref(null),document=reactive({version:1,blocks:[]}),busy=ref(false),autosaveInFlight=ref(false),error=ref(''),notice=ref(''),previewHtml=ref(''),revisions=ref([]),viewport=ref('desktop'),selectedPath=ref(null),blockSearch=ref(''),dirty=ref(false),undoStack=ref([]),redoStack=ref([]),advancedJson=ref(''),timer=ref(null),autosaveDebounce=ref(null),advancedSyncTimer=ref(null),lastAutosaveSignature=ref(''),lastMutationKey=ref(''),lastSnapshotAt=ref(0),drag=reactive({active:false,sourceId:null,overId:null,mode:null,pointerId:null}),viewports=['desktop','tablet','mobile']
const abilities=boot.currentUser?.abilities||[];const hasAbility=a=>abilities.includes('*')||abilities.includes(a)||abilities.some(x=>typeof x==='string'&&x.endsWith('.*')&&a.startsWith(x.slice(0,-1)));const canEdit=computed(()=>hasAbility('builder.edit')),canPublish=computed(()=>hasAbility('builder.publish')),canPreview=computed(()=>hasAbility('builder.preview'))
const pathKey=p=>Array.isArray(p)?p.join('.'):'';const filteredBlocks=computed(()=>{const q=blockSearch.value.toLowerCase();return (boot.blockDefinitions||[]).filter(b=>!q||`${b.title} ${b.name} ${b.category}`.toLowerCase().includes(q))});const flatLayers=computed(()=>{const rows=[];const walk=(xs,d=0,path=[])=>(xs||[]).forEach((n,i)=>{const p=[...path,i];rows.push({node:n,depth:d,path:p});walk(n.children,d+1,p)});walk(document.blocks);return rows});const selectedNode=computed(()=>nodeAt(selectedPath.value));const selectedDefinition=computed(()=>(boot.blockDefinitions||[]).find(b=>(b.name||b.id)===selectedNode.value?.type)||null);const blockCount=computed(()=>flatLayers.value.length),canUndo=computed(()=>undoStack.value.length>0&&canEdit.value),canRedo=computed(()=>redoStack.value.length>0&&canEdit.value)
const BuilderNode=defineComponent({name:'BuilderNode',props:{node:Object,path:Array,selectedPath:Array,startDrag:Function,dropClass:Function},emits:['select'],setup(props,{emit}){return()=>h('article',{class:['cms-builder-live-block',{selected:pathKey(props.path)===pathKey(props.selectedPath)},props.dropClass?.(props.node.id)||''],'data-builder-node-id':props.node.id,onClick:e=>{e.stopPropagation();emit('select',[...props.path])}},[h('div',{},[h('span',{class:'cms-builder-drag-handle',onPointerdown:e=>props.startDrag?.(e,props.path)},'⋮⋮'),h('strong',{},props.node.type),h('small',{},props.node.id),props.node.attributes?.text?h('p',{},String(props.node.attributes.text)):props.node.attributes?.label?h('p',{},String(props.node.attributes.label)):null]),...(props.node.children||[]).map((child,i)=>h(BuilderNode,{node:child,path:[...props.path,i],selectedPath:props.selectedPath,startDrag:props.startDrag,dropClass:props.dropClass,onSelect:p=>emit('select',p)}))])}})
function clone(v){return JSON.parse(JSON.stringify(v))}function nodeAt(path){if(!Array.isArray(path))return null;let list=document.blocks,node=null;for(const i of path){node=list?.[i];if(!node)return null;list=node.children||[]}return node}function parentList(path){if(!Array.isArray(path)||!path.length)return null;let list=document.blocks;for(const i of path.slice(0,-1)){const n=list[i];if(!n)return null;n.children=Array.isArray(n.children)?n.children:[];list=n.children}return list}function documentSignature(){return JSON.stringify({version:document.version,blocks:document.blocks})}function syncAdvanced(force=false){if(advancedSyncTimer.value)clearTimeout(advancedSyncTimer.value);const apply=()=>{advancedJson.value=JSON.stringify({version:document.version,blocks:document.blocks},null,2)};if(force){apply();return}advancedSyncTimer.value=setTimeout(apply,120)}function snapshot(key='structural'){const now=Date.now(),merge=key!=='structural'&&lastMutationKey.value===key&&now-lastSnapshotAt.value<700;if(!merge){undoStack.value.push(clone({version:document.version,blocks:document.blocks}));if(undoStack.value.length>100)undoStack.value.shift();redoStack.value=[];lastSnapshotAt.value=now}lastMutationKey.value=key}function scheduleAutosave(){if(autosaveDebounce.value)clearTimeout(autosaveDebounce.value);autosaveDebounce.value=setTimeout(()=>autosave(true),8000)}function mutate(fn,key='structural'){if(!canEdit.value){error.value=t('builder_page.edit_denied');return}snapshot(key);fn();dirty.value=true;syncAdvanced();scheduleAutosave()}function defaultAttributes(b){const x={};for(const a of b.attributes||[])if(a.default!=null)x[a.name]=clone(a.default);else if(a.required)x[a.name]=a.type==='boolean'?false:a.type==='number'||a.type==='integer'?0:'';return x}function uid(){return `block_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,8)}`}function canAccept(parent,type){const def=(boot.blockDefinitions||[]).find(b=>(b.name||b.id)===parent?.type);if(!def?.allowsChildren)return false;const a=def.allowedChildren||[];return !a.length||a.includes('*')||a.includes(type)}function insertBlock(b){mutate(()=>{const node={id:uid(),type:b.name||b.id,version:b.schemaVersion||b.version||1,attributes:defaultAttributes(b),styles:{},responsive:{},children:[],slot:null};const parent=selectedNode.value;if(parent&&canAccept(parent,node.type)){parent.children ||= [];parent.children.push(node);selectedPath.value=[...selectedPath.value,parent.children.length-1]}else{document.blocks.push(node);selectedPath.value=[document.blocks.length-1]}})}function moveSelected(d){const path=selectedPath.value,list=parentList(path),i=path?.at(-1),j=i+d;if(!list||j<0||j>=list.length)return;mutate(()=>{const[n]=list.splice(i,1);list.splice(j,0,n);selectedPath.value=[...path.slice(0,-1),j]})}function removeSelected(){const path=selectedPath.value,list=parentList(path);if(!list)return;mutate(()=>{list.splice(path.at(-1),1);selectedPath.value=null})}function freshIds(n){const x=clone(n);x.id=uid();x.children=(x.children||[]).map(freshIds);return x}function duplicateSelected(){const path=selectedPath.value,list=parentList(path),node=selectedNode.value;if(!list||!node)return;mutate(()=>{const i=path.at(-1);list.splice(i+1,0,freshIds(node));selectedPath.value=[...path.slice(0,-1),i+1]})}function nestIntoPrevious(){const path=selectedPath.value,list=parentList(path),i=path?.at(-1);if(!list||i<1)return;const node=list[i],prev=list[i-1];if(!canAccept(prev,node.type)){error.value=t('builder_page.previous_child_denied');return}mutate(()=>{list.splice(i,1);prev.children ||= [];prev.children.push(node);selectedPath.value=[...path.slice(0,-1),i-1,prev.children.length-1]})}function moveOut(){const path=selectedPath.value;if(!path||path.length<2)return;mutate(()=>{const list=parentList(path),node=list.splice(path.at(-1),1)[0],parentPath=path.slice(0,-1),grand=parentList(parentPath),pi=parentPath.at(-1);grand.splice(pi+1,0,node);selectedPath.value=[...parentPath.slice(0,-1),pi+1]})}function updateAttribute(k,v){if(!selectedNode.value)return;const id=selectedNode.value.id;mutate(()=>{selectedNode.value.attributes ||= {};selectedNode.value.attributes[k]=v},`attr:${id}:${k}`)}function styleBag(){const n=selectedNode.value;if(!n)return null;if(viewport.value==='desktop'){n.styles ||= {};return n.styles}n.responsive ||= {};n.responsive[viewport.value] ||= {};return n.responsive[viewport.value]}function updateStyle(k,v){const id=selectedNode.value?.id;mutate(()=>{const b=styleBag();if(!b)return;if(v===''||v==null)delete b[k];else b[k]=v},`style:${id}:${viewport.value}:${k}`)}function styleValue(k){const n=selectedNode.value;if(!n)return '';return viewport.value==='desktop'?(n.styles?.[k]??''):(n.responsive?.[viewport.value]?.[k]??'')}function undo(){if(!canUndo.value)return;redoStack.value.push(clone({version:document.version,blocks:document.blocks}));const x=undoStack.value.pop();document.version=x.version;document.blocks.splice(0,document.blocks.length,...x.blocks);selectedPath.value=null;dirty.value=true;syncAdvanced()}function redo(){if(!canRedo.value)return;undoStack.value.push(clone({version:document.version,blocks:document.blocks}));const x=redoStack.value.pop();document.version=x.version;document.blocks.splice(0,document.blocks.length,...x.blocks);selectedPath.value=null;dirty.value=true;syncAdvanced()}function syncFromJson(){if(!canEdit.value)return;try{const x=JSON.parse(advancedJson.value);if(!Array.isArray(x.blocks))return;snapshot();document.version=x.version||1;document.blocks.splice(0,document.blocks.length,...x.blocks);selectedPath.value=null;dirty.value=true}catch{}}
function applyRecord(rec){record.value=rec;Object.assign(target,rec.target||{});document.version=rec.document?.version||1;document.blocks.splice(0,document.blocks.length,...(rec.document?.blocks||[]));dirty.value=false;undoStack.value=[];redoStack.value=[];selectedPath.value=null;lastAutosaveSignature.value=documentSignature();syncAdvanced(true)}
async function run(fn){busy.value=true;error.value='';try{return await fn()}finally{busy.value=false}}async function openDocument(){try{await run(async()=>{const listing=await builderApi.list({site_id:target.site_id,type:target.type,locale:target.locale,limit:200});const data=listing.data||listing;const found=(data.items||[]).find(x=>x.target?.key===target.key&&x.target?.type===target.type&&x.target?.locale===target.locale);if(found){const read=await builderApi.read(found.id);applyRecord(read.data||read)}else if(canEdit.value){const opened=await builderApi.open({target:{...target},document:{version:1,blocks:[]}});applyRecord(opened.data||opened)}else{throw new Error(t('builder_page.override_denied'))}});await loadRevisions()}catch(e){error.value=e.message}}// Only acknowledge the submitted snapshot; newer local edits remain dirty.
function acceptSavedRecord(rec,submitted,recordId){
  if(record.value?.id!==recordId)return false
  const unchanged=documentSignature()===JSON.stringify(submitted)
  const persisted=clone(rec.document||submitted)
  record.value=rec
  if(unchanged){document.version=persisted.version;document.blocks.splice(0,document.blocks.length,...persisted.blocks)}
  lastAutosaveSignature.value=JSON.stringify({version:persisted.version,blocks:persisted.blocks})
  dirty.value=documentSignature()!==lastAutosaveSignature.value
  syncAdvanced(true)
  if(dirty.value)scheduleAutosave()
  return true
}
async function saveInternal(){
  if(!canEdit.value||!record.value)throw new Error(t('builder_page.edit_denied'))
  const recordId=record.value.id,submitted=clone({version:document.version,blocks:document.blocks}),version=record.value.version
  const response=await builderApi.save(recordId,{document:submitted,expected_version:version})
  const saved=response.data||response
  acceptSavedRecord(saved,submitted,recordId)
  return saved
}
async function save(){if(busy.value)return;try{await run(saveInternal);notice.value=t('builder_page.saved_short')}catch(e){error.value=e.message}}
async function autosave(silent=false){
  if(busy.value||autosaveInFlight.value||!record.value||!dirty.value||!canEdit.value)return
  const recordId=record.value.id,submitted=clone({version:document.version,blocks:document.blocks}),signature=JSON.stringify(submitted)
  if(signature===lastAutosaveSignature.value)return
  autosaveInFlight.value=true
  try{await builderApi.autosave(recordId,{document:submitted});if(record.value?.id!==recordId)return;lastAutosaveSignature.value=signature;if(!silent)notice.value=t('builder_page.autosaved')}
  catch(e){if(!silent)error.value=e.message}finally{autosaveInFlight.value=false}
}
async function publish(){
  if(busy.value||!canPublish.value||!record.value)return
  const recordId=record.value.id
  try{
    await run(async()=>{
      if(dirty.value)await saveInternal()
      if(record.value?.id!==recordId)throw new Error(t('builder_page.edit_denied'))
      const submitted=clone(record.value.document||{version:document.version,blocks:document.blocks}),version=record.value.version
      const response=await builderApi.publish(recordId,{expected_version:version})
      acceptSavedRecord(response.data||response,submitted,recordId)
    })
    notice.value=t('builder_page.published_short');await loadRevisions()
  }catch(e){error.value=e.message}
}
async function preview(){if(!canPreview.value)return;try{const r=await run(()=>builderApi.preview({site_id:target.site_id,locale:target.locale,document:{version:document.version,blocks:document.blocks}}));previewHtml.value=(r.data||r).html||''}catch(e){error.value=e.message}}async function loadRevisions(){if(!record.value)return;try{const r=await builderApi.revisions(record.value.id,50);const x=r.data||r;revisions.value=Array.isArray(x)?x:(x.items||x.revisions||[])}catch(e){error.value=e.message}}async function restoreRevision(rev){if(!canEdit.value)return;try{const r=await builderApi.restore(record.value.id,rev.id,{expected_version:record.value.version});applyRecord(r.data||r);await loadRevisions()}catch(e){error.value=e.message}}
function findPathById(id,nodes=document.blocks,path=[]){for(let i=0;i<(nodes||[]).length;i++){const p=[...path,i],n=nodes[i];if(n.id===id)return p;const x=findPathById(id,n.children||[],p);if(x)return x}return null}function pathPrefix(a,b){return Array.isArray(a)&&Array.isArray(b)&&a.length<=b.length&&a.every((v,i)=>b[i]===v)}function dropClass(id){return drag.active&&drag.overId===id&&drag.mode?`drop-${drag.mode}`:''}function startDrag(e,path){if(!canEdit.value)return;e.preventDefault();e.stopPropagation();const n=nodeAt(path);if(!n)return;try{e.currentTarget?.setPointerCapture?.(e.pointerId)}catch{}Object.assign(drag,{active:true,sourceId:n.id,overId:null,mode:null,pointerId:e.pointerId})}function dragMove(e){if(!drag.active||e.pointerId!==drag.pointerId)return;e.preventDefault();const el=globalThis.document?.elementFromPoint?.(e.clientX,e.clientY)?.closest?.('[data-builder-node-id],[data-builder-root-drop]');if(!el){drag.overId=null;drag.mode=null;return}if(el.hasAttribute('data-builder-root-drop')){drag.overId=null;drag.mode='root';return}const id=el.getAttribute('data-builder-node-id');if(!id||id===drag.sourceId){drag.overId=null;drag.mode=null;return}const sp=findPathById(drag.sourceId),tp=findPathById(id);if(!sp||!tp||pathPrefix(sp,tp)){drag.overId=null;drag.mode=null;return}const r=el.getBoundingClientRect(),ratio=r.height?(e.clientY-r.top)/r.height:.5;let mode=ratio<.28?'before':ratio>.72?'after':'inside';if(mode==='inside'){const t=nodeAt(tp),src=nodeAt(sp);if(!src||!canAccept(t,src.type))mode=ratio<.5?'before':'after'}drag.overId=id;drag.mode=mode}function moveByDrop(sourceId,targetId,mode){const sp=findPathById(sourceId);if(!sp)return;mutate(()=>{const current=findPathById(sourceId),sourceList=parentList(current),si=current?.at(-1),node=sourceList?.splice(si,1)?.[0];if(!node)return;if(mode==='root'||targetId===null){document.blocks.push(node);selectedPath.value=[document.blocks.length-1];return}const tp=findPathById(targetId),target=nodeAt(tp);if(!tp){sourceList.splice(Math.min(si,sourceList.length),0,node);return}if(mode==='inside'&&target&&canAccept(target,node.type)){target.children ||= [];target.children.push(node);selectedPath.value=[...tp,target.children.length-1];return}const list=parentList(tp),ti=tp.at(-1),at=mode==='after'?ti+1:ti;if(list){list.splice(at,0,node);selectedPath.value=[...tp.slice(0,-1),at]}else{document.blocks.push(node);selectedPath.value=[document.blocks.length-1]}},'structural')}function dragCancel(e){if(!drag.active||e.pointerId!==drag.pointerId)return;Object.assign(drag,{active:false,sourceId:null,overId:null,mode:null,pointerId:null})}function dragEnd(e){if(!drag.active||e.pointerId!==drag.pointerId)return;const sourceId=drag.sourceId,targetId=drag.overId,mode=drag.mode;Object.assign(drag,{active:false,sourceId:null,overId:null,mode:null,pointerId:null});if(!mode)return;moveByDrop(sourceId,targetId,mode)}const beforeUnload=e=>{if(dirty.value){e.preventDefault();e.returnValue=''}};const visibilityAutosave=()=>{if(globalThis.document?.visibilityState==='hidden'&&dirty.value)autosave(true)};onMounted(()=>{syncAdvanced(true);timer.value=setInterval(()=>autosave(true),30000);globalThis.addEventListener('pointermove',dragMove,{passive:false});globalThis.addEventListener('pointerup',dragEnd);globalThis.addEventListener('pointercancel',dragCancel);globalThis.addEventListener('beforeunload',beforeUnload);globalThis.document?.addEventListener?.('visibilitychange',visibilityAutosave);if(route.query.open==='1'||route.query.type||route.query.key)openDocument()});onBeforeUnmount(()=>{if(timer.value)clearInterval(timer.value);if(autosaveDebounce.value)clearTimeout(autosaveDebounce.value);if(advancedSyncTimer.value)clearTimeout(advancedSyncTimer.value);globalThis.removeEventListener('pointermove',dragMove);globalThis.removeEventListener('pointerup',dragEnd);globalThis.removeEventListener('pointercancel',dragCancel);globalThis.removeEventListener('beforeunload',beforeUnload);globalThis.document?.removeEventListener?.('visibilitychange',visibilityAutosave)})
</script>
