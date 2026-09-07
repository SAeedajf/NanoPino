import { api, data, ui, input, textarea, select, message, safeJson, confirmFa, tr, locale } from './common.mjs'

const CSS = `
.cms-builder-center{display:grid;gap:12px;direction:inherit}
.cms-builder-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;position:sticky;top:0;z-index:5;padding:10px;background:color-mix(in srgb,var(--p-surface-0,#fff) 94%,transparent);backdrop-filter:blur(12px);border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px}
.cms-builder-toolbar .grow{flex:1}.cms-builder-shell{display:grid;grid-template-columns:minmax(220px,280px) minmax(0,1fr) minmax(250px,320px);gap:12px;align-items:start}
.cms-builder-side{display:grid;gap:10px;position:sticky;top:76px;max-height:calc(100vh - 96px);overflow:auto}.cms-builder-canvas-wrap{display:grid;place-items:center;min-width:0;overflow:auto;padding:12px;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;background:var(--p-surface-50,#f9fafb)}
.cms-builder-frame{width:100%;min-height:560px;background:var(--p-surface-0,#fff);border:1px solid var(--p-surface-300,#d1d5db);border-radius:14px;padding:14px;transition:max-width .2s ease;box-sizing:border-box}.cms-builder-frame[data-viewport=tablet]{max-width:768px}.cms-builder-frame[data-viewport=mobile]{max-width:390px}.cms-builder-frame[data-viewport=desktop]{max-width:1200px}
.cms-builder-empty{display:grid;place-items:center;text-align:center;gap:8px;min-height:380px;opacity:.7}.cms-builder-node{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px;padding:10px;margin:8px 0;display:grid;gap:8px;background:var(--p-surface-0,#fff);cursor:pointer}.cms-builder-node.selected{outline:2px solid var(--p-primary-400,#818cf8);outline-offset:1px}.cms-builder-node.drop-before{box-shadow:inset 0 3px 0 var(--p-primary-500,#6366f1)}.cms-builder-node.drop-after{box-shadow:inset 0 -3px 0 var(--p-primary-500,#6366f1)}.cms-builder-node.drop-inside{background:var(--p-primary-50,#eef2ff);outline:2px dashed var(--p-primary-400,#818cf8)}.cms-builder-node-head{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}.cms-builder-node-children{padding-inline-start:16px;border-inline-start:2px solid var(--p-surface-200,#e5e7eb)}
.cms-builder-library{display:grid;gap:8px}.cms-builder-library button{width:100%;text-align:start;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:10px;padding:10px;background:var(--p-surface-0,#fff);cursor:pointer;color:inherit;display:grid;gap:2px}.cms-builder-library button:hover{border-color:var(--p-primary-300,#a5b4fc)}.cms-builder-library small{opacity:.65}.cms-builder-layer{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:9px;padding:8px;display:flex;gap:7px;align-items:center;cursor:pointer;margin-bottom:5px}.cms-builder-layer.selected{background:var(--p-primary-50,#eef2ff);border-color:var(--p-primary-300,#a5b4fc)}.cms-builder-drag-handle{cursor:grab;user-select:none;touch-action:none;padding:4px 6px;border-radius:7px}.cms-builder-drag-handle:active{cursor:grabbing;background:var(--p-surface-100,#f3f4f6)}.cms-builder-root-drop{min-height:28px;border:1px dashed transparent;border-radius:9px;display:grid;place-items:center;font-size:11px;opacity:.45}.cms-builder-root-drop.active{border-color:var(--p-primary-400,#818cf8);background:var(--p-primary-50,#eef2ff);opacity:1}.cms-builder-saving{animation:cms-builder-pulse 1.2s ease-in-out infinite}@keyframes cms-builder-pulse{50%{opacity:.5}}.cms-builder-layer code{font-size:10px;opacity:.65;direction:ltr}.cms-builder-layer-indent{border-inline-start:1px dashed var(--p-surface-300,#d1d5db);padding-inline-start:10px;margin-inline-start:8px}
.cms-builder-inspector{display:grid;gap:12px}.cms-builder-inspector-section{display:grid;gap:9px;padding:10px;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:11px}.cms-builder-inspector-section h4{margin:0;font-size:13px}.cms-builder-inspector label{display:grid;gap:5px;font-size:12px}.cms-builder-switch{display:flex!important;align-items:center;gap:8px;min-height:40px}.cms-builder-switch input{width:20px;height:20px}.cms-builder-style-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.cms-builder-style-grid label{min-width:0}.cms-builder-status{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.cms-builder-status code{font-size:10px}.cms-builder-revisions{display:grid;gap:7px}.cms-builder-revision{display:flex;justify-content:space-between;gap:8px;align-items:center;border-bottom:1px solid var(--p-surface-100,#f3f4f6);padding:7px 0}.cms-builder-preview{width:100%;height:560px;border:0;border-radius:12px;background:white}.cms-builder-json{min-height:300px!important}.cms-builder-mobile-tabs{display:none}
@media(max-width:1050px){.cms-builder-shell{grid-template-columns:220px minmax(0,1fr)}.cms-builder-side.end{grid-column:1/-1;position:static;max-height:none;grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:760px){.cms-builder-toolbar{position:static}.cms-builder-shell{grid-template-columns:1fr}.cms-builder-side{position:static;max-height:none}.cms-builder-side.start,.cms-builder-side.end{grid-column:auto;grid-template-columns:1fr}.cms-builder-frame{min-height:460px;padding:9px}.cms-builder-style-grid{grid-template-columns:1fr}.cms-builder-node-children{padding-inline-start:8px}.cms-builder-mobile-tabs{display:flex}}
`

const STYLE_FIELDS = {
  color: [
    ['color',tr('builder_page.color_text'),'color'],['backgroundColor',tr('builder_page.color_bg'),'color'],['borderColor',tr('builder_page.color_border'),'color'],
  ],
  typography: [
    ['fontSize',tr('builder_page.font_size'),'text'],['fontWeight',tr('builder_page.font_weight'),'text'],['lineHeight',tr('builder_page.line_height'),'text'],['letterSpacing',tr('builder_page.letter_spacing'),'text'],['textAlign',tr('builder_page.text_align'),'select',['right','center','left','justify']],
  ],
  spacing: [
    ['margin',tr('builder_page.margin'),'text'],['padding',tr('builder_page.padding'),'text'],['gap',tr('builder_page.gap'),'text'],
  ],
  dimensions: [
    ['width',tr('builder_page.width'),'text'],['height',tr('builder_page.height'),'text'],['minWidth',tr('builder_page.min_width'),'text'],['maxWidth',tr('builder_page.max_width'),'text'],['minHeight',tr('builder_page.min_height'),'text'],['maxHeight',tr('builder_page.max_height'),'text'],
  ],
  align: [
    ['display',tr('builder_page.display'),'select',['block','flex','grid','inline-block','none']],['alignItems',tr('builder_page.align_items'),'select',['stretch','start','center','end']],['justifyContent',tr('builder_page.justify'),'select',['start','center','end','space-between','space-around']],['gridTemplateColumns',tr('builder_page.grid_columns'),'text'],
  ],
}

function clone(value){return JSON.parse(JSON.stringify(value))}
function pathKey(path){return Array.isArray(path)?path.join('.') : ''}
function defFor(blocks,type){return (blocks||[]).find(b=>(b.name||b.id)===type)||null}
function defaultAttributes(def){const out={};for(const a of def?.attributes||[]){if(a.default!==undefined&&a.default!==null)out[a.name]=clone(a.default);else if(a.required)out[a.name]=a.type==='integer'||a.type==='number'?0:a.type==='boolean'?false:''}return out}
function uid(){return `block_${Date.now().toString(36)}_${Math.random().toString(36).slice(2,9)}`}
function freshIds(node){const copy=clone(node);copy.id=uid();copy.children=(copy.children||[]).map(freshIds);return copy}
function localeOptions(){return [{value:'fa',label:tr('builder_page.fa')},{value:'en',label:tr('builder_page.en')},{value:'ar',label:tr('builder_page.ar')}]}
function hasAbility(ability){const granted=data().currentUser?.abilities||[];return granted.includes('*')||granted.includes(ability)||granted.some(x=>typeof x==='string'&&x.endsWith('.*')&&ability.startsWith(x.slice(0,-1)))}

export function createComponent(host){
  const {h,LPage,LPanel,LButton,LBadge,LStatCard}=host
  return {
    name:'CmsVisualSiteBuilderCenter',
    data(){return{
      record:null,target:{site_id:1,type:'template',key:'home',locale:'fa'},document:{version:1,blocks:[]},blocks:data().blockDefinitions||[],revisions:[],previewHtml:'',viewport:'desktop',selectedPath:null,blockSearch:'',panel:'blocks',error:'',notice:'',busy:false,dirty:false,undoStack:[],redoStack:[],lastAutosave:null,advancedJson:'',jsonValid:true,autosaveInFlight:false,autosaveTimer:null,autosaveDebounce:null,advancedSyncTimer:null,lastAutosaveSignature:'',lastAutosaveAt:0,lastMutationKey:'',lastSnapshotAt:0,contentTargets:[],contentQuery:'',contentLoading:false,drag:{active:false,sourceId:null,overId:null,mode:null,pointerId:null},
    }},
    computed:{
      selectedNode(){return this.nodeAt(this.selectedPath)},
      selectedDefinition(){return this.selectedNode?defFor(this.blocks,this.selectedNode.type):null},
      filteredBlocks(){const q=this.blockSearch.trim().toLowerCase();return this.blocks.filter(b=>!q||`${b.title||''} ${b.name||''} ${b.category||''}`.toLowerCase().includes(q))},
      canUndo(){return this.undoStack.length>0&&this.canEdit},canRedo(){return this.redoStack.length>0&&this.canEdit},
      canEdit(){return hasAbility('builder.edit')},canPublish(){return hasAbility('builder.publish')},canPreview(){return hasAbility('builder.preview')},
      blockCount(){let n=0;const walk=xs=>(xs||[]).forEach(x=>{n++;walk(x.children)});walk(this.document.blocks);return n},
      templateTargets(){return [...new Set(data().fullSiteEditor?.templateKinds||['index','home','page','single','archive','taxonomy','search','404'])].map(key=>({key,label:({index:tr('site_editor_page.index'),home:tr('site_editor_page.home'),page:tr('site_editor_page.page'),single:tr('site_editor_page.single'),archive:tr('site_editor_page.archive'),taxonomy:tr('site_editor_page.taxonomy'),search:tr('site_editor_page.search'),'404':'404'})[key]||key}))},
      partTargets(){return [...new Set(data().fullSiteEditor?.templateParts||['header','footer'])].map(key=>({key,label:key==='header'?tr('site_editor_page.header'):key==='footer'?tr('site_editor_page.footer'):key}))},
      targetReady(){return Number(this.target.site_id)>0&&['content','template','template_part','site'].includes(this.target.type)&&Boolean(String(this.target.key||'').trim())},
      targetSummary(){if(this.target.type==='content'){const id=String(this.target.key||'').split(':').at(-1),row=this.contentTargets.find(item=>String(item.id)===id);return row?.title||tr('builder_page.content_target_selected')}if(this.target.type==='template')return this.templateTargets.find(item=>item.key===this.target.key)?.label||this.target.key;if(this.target.type==='template_part')return this.partTargets.find(item=>item.key===this.target.key)?.label||this.target.key;return tr('builder_page.whole_site')},
    },
    mounted(){
      if(!document.getElementById('cms-builder-center-css')){const s=document.createElement('style');s.id='cms-builder-center-css';s.textContent=CSS;document.head.appendChild(s)}
      const params=new URLSearchParams(globalThis.location?.search||'');for(const k of ['type','key','locale'])if(params.get(k))this.target[k]=params.get(k);if(params.get('site_id'))this.target.site_id=Number(params.get('site_id'))||1
      this.syncAdvanced(true);this.autosaveTimer=setInterval(()=>this.backgroundAutosave({silent:true}),30000)
      this._pointerMove=e=>this.dragMove(e);this._pointerUp=e=>this.dragEnd(e);this._pointerCancel=e=>this.dragCancel(e);this._beforeUnload=e=>{if(this.dirty){e.preventDefault();e.returnValue=''}};this._visibility=()=>{if(document.visibilityState==='hidden')this.backgroundAutosave({silent:true,force:true})}
      globalThis.addEventListener?.('pointermove',this._pointerMove,{passive:false});globalThis.addEventListener?.('pointerup',this._pointerUp);globalThis.addEventListener?.('pointercancel',this._pointerCancel);globalThis.addEventListener?.('beforeunload',this._beforeUnload);document.addEventListener?.('visibilitychange',this._visibility)
      if(this.target.type==='content')this.loadContentTargets();if(params.get('create')==='1')this.createDocument();else if(params.get('open')==='1')this.openExistingDocument()
    },
    beforeUnmount(){if(this.autosaveTimer)clearInterval(this.autosaveTimer);if(this.autosaveDebounce)clearTimeout(this.autosaveDebounce);if(this.advancedSyncTimer)clearTimeout(this.advancedSyncTimer);globalThis.removeEventListener?.('pointermove',this._pointerMove);globalThis.removeEventListener?.('pointerup',this._pointerUp);globalThis.removeEventListener?.('pointercancel',this._pointerCancel);globalThis.removeEventListener?.('beforeunload',this._beforeUnload);document.removeEventListener?.('visibilitychange',this._visibility)},
    methods:{
      nodeAt(path){if(!Array.isArray(path))return null;let list=this.document.blocks,node=null;for(const index of path){node=list?.[index];if(!node)return null;list=node.children||[]}return node},
      parentList(path){if(!Array.isArray(path)||!path.length)return null;let list=this.document.blocks;for(const index of path.slice(0,-1)){const n=list[index];if(!n)return null;n.children=Array.isArray(n.children)?n.children:[];list=n.children}return list},
      selectPath(path){this.selectedPath=Array.isArray(path)?[...path]:null;this.panel='inspector'},
      documentSignature(){return JSON.stringify(this.document)},
      snapshot(key='structural'){const now=Date.now(),merge=key!=='structural'&&this.lastMutationKey===key&&now-this.lastSnapshotAt<700;if(!merge){this.undoStack.push(clone(this.document));if(this.undoStack.length>100)this.undoStack.shift();this.redoStack=[];this.lastSnapshotAt=now}this.lastMutationKey=key},
      scheduleAutosave(){if(this.autosaveDebounce)clearTimeout(this.autosaveDebounce);this.autosaveDebounce=setTimeout(()=>this.backgroundAutosave({silent:true}),8000)},
      mutation(fn,key='structural'){if(!this.canEdit){this.error=tr('builder_page.edit_permission');return}this.snapshot(key);fn();this.dirty=true;this.syncAdvanced();this.scheduleAutosave()},
      undo(){if(!this.canUndo)return;this.redoStack.push(clone(this.document));this.document=this.undoStack.pop();this.selectedPath=null;this.dirty=true;this.lastMutationKey='';this.syncAdvanced(true);this.scheduleAutosave()},
      redo(){if(!this.canRedo)return;this.undoStack.push(clone(this.document));this.document=this.redoStack.pop();this.selectedPath=null;this.dirty=true;this.lastMutationKey='';this.syncAdvanced(true);this.scheduleAutosave()},
      syncAdvanced(force=false){if(this.advancedSyncTimer)clearTimeout(this.advancedSyncTimer);const apply=()=>{this.advancedJson=JSON.stringify(this.document,null,2);this.jsonValid=true;this.advancedSyncTimer=null};if(force)apply();else this.advancedSyncTimer=setTimeout(apply,120)},
      syncFromAdvanced(value){this.advancedJson=value;try{const parsed=JSON.parse(value);if(!parsed||typeof parsed!=='object'||!Array.isArray(parsed.blocks))throw new Error();this.snapshot('advanced-json');this.document=parsed;this.selectedPath=null;this.dirty=true;this.jsonValid=true;this.scheduleAutosave()}catch{this.jsonValid=false}},
      canAccept(parentDef,type){if(!parentDef?.allowsChildren)return false;const allowed=parentDef.allowedChildren||[];return !allowed.length||allowed.includes('*')||allowed.includes(type)},
      insertBlock(def){
        const node={id:uid(),type:def.name||def.id,version:def.schemaVersion||def.version||1,attributes:defaultAttributes(def),styles:{},responsive:{},children:[],slot:null}
        this.mutation(()=>{const selected=this.selectedNode,sd=this.selectedDefinition;if(selected&&this.canAccept(sd,node.type)){selected.children=Array.isArray(selected.children)?selected.children:[];selected.children.push(node);this.selectedPath=[...this.selectedPath,selected.children.length-1]}else{this.document.blocks.push(node);this.selectedPath=[this.document.blocks.length-1]}})
      },
      removeSelected(){if(!this.selectedPath)return;if(!confirmFa(tr('builder_page.remove_confirm')))return;this.mutation(()=>{const list=this.parentList(this.selectedPath);if(list)list.splice(this.selectedPath.at(-1),1);this.selectedPath=null})},
      duplicateSelected(){const node=this.selectedNode;if(!node||!this.selectedPath)return;this.mutation(()=>{const list=this.parentList(this.selectedPath),i=this.selectedPath.at(-1);if(list){list.splice(i+1,0,freshIds(node));this.selectedPath=[...this.selectedPath.slice(0,-1),i+1]}})},
      moveSelected(delta){if(!this.selectedPath)return;const list=this.parentList(this.selectedPath),i=this.selectedPath.at(-1),j=i+delta;if(!list||j<0||j>=list.length)return;this.mutation(()=>{const [node]=list.splice(i,1);list.splice(j,0,node);this.selectedPath=[...this.selectedPath.slice(0,-1),j]})},
      moveOut(){if(!this.selectedPath||this.selectedPath.length<2)return;this.mutation(()=>{const path=[...this.selectedPath],list=this.parentList(path),i=path.at(-1),node=list.splice(i,1)[0],parentPath=path.slice(0,-1),grand=this.parentList(parentPath),parentIndex=parentPath.at(-1);grand.splice(parentIndex+1,0,node);this.selectedPath=[...parentPath.slice(0,-1),parentIndex+1]})},
      nestIntoPrevious(){if(!this.selectedPath)return;const list=this.parentList(this.selectedPath),i=this.selectedPath.at(-1);if(!list||i<1)return;const node=list[i],prev=list[i-1],pd=defFor(this.blocks,prev.type);if(!this.canAccept(pd,node.type)){this.error=tr('builder_page.previous_child_denied');return}this.mutation(()=>{list.splice(i,1);prev.children=Array.isArray(prev.children)?prev.children:[];prev.children.push(node);this.selectedPath=[...this.selectedPath.slice(0,-1),i-1,prev.children.length-1]})},
      updateAttribute(key,value){const node=this.selectedNode;if(!node)return;this.mutation(()=>{node.attributes=node.attributes||{};node.attributes[key]=value},`attr:${node.id}:${key}`)},
      styleBag(){const node=this.selectedNode;if(!node)return null;if(this.viewport==='desktop'){node.styles=node.styles||{};return node.styles}node.responsive=node.responsive||{};node.responsive[this.viewport]=node.responsive[this.viewport]||{};return node.responsive[this.viewport]},
      updateStyle(key,value){const node=this.selectedNode;if(!node)return;this.mutation(()=>{const bag=this.styleBag();if(value===''||value===null||value===undefined)delete bag[key];else bag[key]=value},`style:${node.id}:${this.viewport}:${key}`)},
      styleValue(key){const node=this.selectedNode;if(!node)return '';const bag=this.viewport==='desktop'?(node.styles||{}):(node.responsive?.[this.viewport]||{});return bag[key]??''},
      typeHuman(type){return ({post:tr('builder_page.content_post'),page:tr('builder_page.content_page')})[type]||type},
      resetTargetKey(){
        this.record=null;this.document={version:1,blocks:[]};this.selectedPath=null;this.dirty=false
        if(this.target.type==='template')this.target.key=this.templateTargets[0]?.key||'home'
        else if(this.target.type==='template_part')this.target.key=this.partTargets[0]?.key||'header'
        else if(this.target.type==='site')this.target.key='site'
        else{this.target.key='';this.loadContentTargets()}
      },
      async loadContentTargets(){
        if(this.target.type!=='content')return
        this.contentLoading=true;this.error=''
        try{
          const q=new URLSearchParams({site_id:String(this.target.site_id||1),locale:this.target.locale||'fa',limit:'50',projection:'list'})
          if(this.contentQuery.trim())q.set('search',this.contentQuery.trim())
          const result=await api('/content?'+q)
          this.contentTargets=result.items||[]
        }catch(e){this.error=e.message;this.contentTargets=[]}finally{this.contentLoading=false}
      },
      applyOpenedRecord(record){
        if(!record?.id)throw new Error(tr('builder_page.invalid_response'))
        this.record=record;this.target={...record.target};this.document=clone(record.document||{version:1,blocks:[]});this.selectedPath=null;this.undoStack=[];this.redoStack=[];this.dirty=false;this.lastAutosaveSignature=this.documentSignature();this.syncAdvanced(true)
      },
      async run(fn){this.busy=true;this.error='';try{return await fn()}catch(e){this.error=e.message;throw e}finally{this.busy=false}},
      async findExistingTarget(){
        if(!this.targetReady)throw new Error(tr('builder_page.target_required'))
        const list=await api(`/builder?site_id=${Number(this.target.site_id||1)}&type=${encodeURIComponent(this.target.type)}&locale=${encodeURIComponent(this.target.locale||'fa')}&limit=200`)
        return (list?.items||[]).find(x=>x.target?.key===this.target.key&&x.target?.type===this.target.type&&x.target?.locale===this.target.locale)||null
      },
      async openExistingDocument(){
        try{
          await this.run(async()=>{const existing=await this.findExistingTarget();if(!existing)throw new Error(tr('builder_page.no_existing_target'));const record=await api(`/builder/${existing.id}`);this.applyOpenedRecord(record)})
          this.notice=tr('builder_page.existing_opened');await this.loadRevisions()
        }catch{}
      },
      async createDocument(){
        if(!this.canEdit){this.error=tr('builder_page.edit_permission');return}
        try{
          await this.run(async()=>{const existing=await this.findExistingTarget();if(existing)throw new Error(tr('builder_page.target_already_exists'));const record=await api('/builder',{method:'POST',body:{target:{...this.target},document:{version:1,blocks:[]}}});this.applyOpenedRecord(record)})
          this.notice=tr('builder_page.override_created');await this.loadRevisions()
        }catch{}
      },
      // A response acknowledges the submitted snapshot, never edits made while it was pending.
      acceptSavedRecord(record,submitted,recordId){
        if(this.record?.id!==recordId)return false
        const unchanged=this.documentSignature()===JSON.stringify(submitted)
        const persisted=clone(record.document||submitted)
        this.record=record
        if(unchanged)this.document=clone(persisted)
        this.lastAutosaveSignature=JSON.stringify(persisted)
        this.dirty=this.documentSignature()!==this.lastAutosaveSignature
        this.syncAdvanced(true)
        if(this.dirty)this.scheduleAutosave()
        return true
      },
      async saveInternal(){
        if(!this.canEdit)throw new Error(tr('builder_page.edit_permission'))
        if(!this.record)throw new Error(tr('builder_page.open_first'))
        const recordId=this.record.id,submitted=clone(this.document),version=this.record.version
        const record=await api(`/builder/${recordId}`,{method:'PUT',body:{document:submitted,expected_version:version}})
        this.acceptSavedRecord(record,submitted,recordId)
        return record
      },
      async save(){if(this.busy)return;try{await this.run(()=>this.saveInternal());this.notice=tr('builder_page.saved');await this.loadRevisions()}catch{}},
      async autosave(options={}){
        const silent=Boolean(options.silent),force=Boolean(options.force)
        if(this.busy||this.autosaveInFlight||!this.canEdit||!this.record||!this.jsonValid)return null
        const recordId=this.record.id,submitted=clone(this.document),signature=JSON.stringify(submitted)
        if(!force&&signature===this.lastAutosaveSignature)return null
        this.autosaveInFlight=true
        try{
          const rev=await api(`/builder/${recordId}/autosave`,{method:'POST',body:{document:submitted}})
          if(this.record?.id!==recordId)return rev
          this.lastAutosaveSignature=signature;this.lastAutosaveAt=Date.now();this.lastAutosave=rev?.created_at||new Date().toLocaleTimeString(locale())
          if(!silent)this.notice=tr('builder_page.autosaved')
          return rev
        }catch(e){if(!silent)this.error=e.message;throw e}finally{this.autosaveInFlight=false}
      },
      async backgroundAutosave(options={}){if(this.busy||!this.record||!this.dirty||!this.jsonValid)return;try{await this.autosave({silent:options.silent!==false,force:Boolean(options.force)})}catch{}},
      async publish(){
        if(this.busy||!this.canPublish||!this.record)return
        const recordId=this.record.id
        try{
          await this.run(async()=>{
            if(this.dirty)await this.saveInternal()
            if(this.record?.id!==recordId)throw new Error(tr('builder_page.open_first'))
            const submitted=clone(this.record.document||this.document),version=this.record.version
            const record=await api(`/builder/${recordId}/publish`,{method:'POST',body:{expected_version:version}})
            this.acceptSavedRecord(record,submitted,recordId)
          })
          this.notice=tr('builder_page.published');await this.loadRevisions()
        }catch{}
      },
      async preview(){if(!this.canPreview){this.error=tr('builder_page.preview_denied');return}try{const result=await this.run(()=>api('/builder/preview',{method:'POST',body:{site_id:Number(this.target.site_id||1),locale:this.target.locale||'fa',document:this.document}}));this.previewHtml=result?.html||'';this.notice=this.previewHtml?tr('builder_page.preview_updated'):tr('builder_page.renderer_ran')}catch{}},
      async loadRevisions(){if(!this.record)return;try{const r=await api(`/builder/${this.record.id}/revisions?limit=50`);this.revisions=Array.isArray(r)?r:(r?.items||r?.revisions||[])}catch(e){this.error=e.message}},
      async restore(rev){if(!this.record||!confirmFa(tr('builder_page.restore_confirm','',{id:rev.id})))return;try{const record=await this.run(()=>api(`/builder/${this.record.id}/revisions/${rev.id}/restore`,{method:'POST',body:{expected_version:this.record.version}}));this.record=record;this.document=clone(record.document||{version:1,blocks:[]});this.selectedPath=null;this.undoStack=[];this.redoStack=[];this.dirty=false;this.lastAutosaveSignature=this.documentSignature();this.syncAdvanced(true);this.notice=tr('builder_page.restored');await this.loadRevisions()}catch{}},
      findPathById(id,nodes=this.document.blocks,path=[]){for(let i=0;i<(nodes||[]).length;i++){const p=[...path,i],node=nodes[i];if(node.id===id)return p;const found=this.findPathById(id,node.children||[],p);if(found)return found}return null},
      pathFromAttr(value){if(value==='')return[];if(typeof value!=='string')return null;const parts=value.split('.').map(Number);return parts.every(Number.isInteger)?parts:null},
      pathIsPrefix(parent,child){return Array.isArray(parent)&&Array.isArray(child)&&parent.length<=child.length&&parent.every((v,i)=>child[i]===v)},
      dragStart(e,path){if(!this.canEdit||!Array.isArray(path))return;e.preventDefault();e.stopPropagation();const node=this.nodeAt(path);if(!node)return;this.drag={active:true,sourceId:node.id,overId:null,mode:null,pointerId:e.pointerId};try{e.currentTarget?.setPointerCapture?.(e.pointerId)}catch{}},
      dragMove(e){if(!this.drag.active||e.pointerId!==this.drag.pointerId)return;e.preventDefault();const el=document.elementFromPoint?.(e.clientX,e.clientY)?.closest?.('[data-builder-node-id],[data-builder-root-drop]');if(!el){this.drag.overId=null;this.drag.mode=null;return}if(el.hasAttribute('data-builder-root-drop')){this.drag.overId=null;this.drag.mode='root';return}const id=el.getAttribute('data-builder-node-id');if(!id||id===this.drag.sourceId){this.drag.overId=null;this.drag.mode=null;return}const sourcePath=this.findPathById(this.drag.sourceId),targetPath=this.findPathById(id);if(!sourcePath||!targetPath||this.pathIsPrefix(sourcePath,targetPath)){this.drag.overId=null;this.drag.mode=null;return}const rect=el.getBoundingClientRect(),ratio=rect.height?((e.clientY-rect.top)/rect.height):.5;let mode=ratio<.28?'before':ratio>.72?'after':'inside';if(mode==='inside'){const target=this.nodeAt(targetPath),source=this.nodeAt(sourcePath),def=defFor(this.blocks,target?.type);if(!source||!this.canAccept(def,source.type))mode=ratio<.5?'before':'after'}this.drag.overId=id;this.drag.mode=mode},
      dragCancel(e){if(!this.drag.active||e.pointerId!==this.drag.pointerId)return;this.drag={active:false,sourceId:null,overId:null,mode:null,pointerId:null}},
      dragEnd(e){if(!this.drag.active||e.pointerId!==this.drag.pointerId)return;const state={...this.drag};this.drag={active:false,sourceId:null,overId:null,mode:null,pointerId:null};if(!state.mode)return;this.moveByDrop(state.sourceId,state.overId,state.mode)},
      moveByDrop(sourceId,targetId,mode){const sourcePath=this.findPathById(sourceId);if(!sourcePath)return;const source=this.nodeAt(sourcePath);if(!source)return;this.mutation(()=>{let currentSourcePath=this.findPathById(sourceId),sourceList=this.parentList(currentSourcePath);if(!sourceList)return;const sourceIndex=currentSourcePath.at(-1),node=sourceList.splice(sourceIndex,1)[0];if(mode==='root'||targetId===null){this.document.blocks.push(node);this.selectedPath=[this.document.blocks.length-1];return}const targetPath=this.findPathById(targetId);if(!targetPath){sourceList.splice(Math.min(sourceIndex,sourceList.length),0,node);return}const target=this.nodeAt(targetPath);if(mode==='inside'){const def=defFor(this.blocks,target?.type);if(target&&this.canAccept(def,node.type)){target.children=Array.isArray(target.children)?target.children:[];target.children.push(node);this.selectedPath=[...targetPath,target.children.length-1];return}}const list=this.parentList(targetPath);if(!list){this.document.blocks.push(node);this.selectedPath=[this.document.blocks.length-1];return}const ti=targetPath.at(-1),at=mode==='after'?ti+1:ti;list.splice(at,0,node);this.selectedPath=[...targetPath.slice(0,-1),at]},'structural')},
      dropClass(id){if(!this.drag.active||this.drag.overId!==id)return'';return this.drag.mode?` drop-${this.drag.mode}`:''},
      renderAttribute(field){const node=this.selectedNode,value=node?.attributes?.[field.name]??field.default??'';if(field.type==='boolean')return h('label',{class:'cms-builder-switch'},[h('input',{type:'checkbox',checked:Boolean(value),disabled:!this.canEdit,onChange:e=>this.updateAttribute(field.name,e.target.checked)}),h('span',{},field.name)]);if(field.rules?.enum)return h('label',{},[h('span',{},field.name),select(h,String(value),v=>this.updateAttribute(field.name,v),field.rules.enum.map(v=>({value:v,label:String(v)})))]);const type=field.type==='integer'||field.type==='number'?'number':field.type==='url'?'url':'text';return h('label',{},[h('span',{},field.name+(field.required?' *':'')),input(h,value,v=>this.updateAttribute(field.name,type==='number'?Number(v):v),type,{min:field.rules?.min,max:field.rules?.max,disabled:!this.canEdit})])},
      renderStyleField(field){const [key,labelText,type,options]=field;const value=this.styleValue(key);if(type==='select')return h('label',{},[h('span',{},labelText),select(h,String(value),v=>this.updateStyle(key,v),[{value:'',label:tr('builder_page.default')},...(options||[]).map(v=>({value:v,label:v}))])]);return h('label',{},[h('span',{},labelText),input(h,value,v=>this.updateStyle(key,v),type==='color'?'color':'text')])},
      renderInspector(){
        const def=this.selectedDefinition,node=this.selectedNode
        if(!node||!def)return h('div',{class:'cms-builder-empty',style:{minHeight:'180px'}},[h('strong',{},tr('builder_page.no_block_selected')),h('span',{},tr('builder_page.select_canvas_layer'))])
        const sections=[]
        if(def.attributes?.length){
          sections.push(h('section',{class:'cms-builder-inspector-section'},[
            h('h4',{},tr('builder_page.content_attributes')),
            ...def.attributes.map(f=>this.renderAttribute(f)),
          ]))
        }
        for(const group of Object.keys(STYLE_FIELDS)){
          if(def.supports?.[group]){
            sections.push(h('section',{class:'cms-builder-inspector-section'},[
              h('h4',{},`${group} · ${this.viewport}`),
              h('div',{class:'cms-builder-style-grid'},STYLE_FIELDS[group].map(f=>this.renderStyleField(f))),
            ]))
          }
        }
        return h('div',{class:'cms-builder-inspector'},[
          h('div',{class:'cms-builder-status'},[h(LBadge,{label:def.title||def.name}),h('code',{},node.id)]),
          h('div',{style:ui.row},[
            h(LButton,{label:'↑',severity:'secondary','aria-label':tr('a11y.builder_move_up',tr('builder_page.move_up')),onClick:()=>this.moveSelected(-1)}),
            h(LButton,{label:'↓',severity:'secondary','aria-label':tr('a11y.builder_move_down',tr('builder_page.move_down')),onClick:()=>this.moveSelected(1)}),
            h(LButton,{label:tr('builder_page.nest'),severity:'secondary',onClick:this.nestIntoPrevious}),
            h(LButton,{label:tr('builder_page.move_out'),severity:'secondary',onClick:this.moveOut}),
            h(LButton,{label:tr('builder_page.duplicate'),severity:'secondary',onClick:this.duplicateSelected}),
            h(LButton,{label:tr('builder_page.delete'),severity:'danger',onClick:this.removeSelected}),
          ]),
          ...sections,
        ])
      },
      renderCanvasNodes(nodes,path=[]){return (nodes||[]).map((node,index)=>{const p=[...path,index],def=defFor(this.blocks,node.type),selected=pathKey(p)===pathKey(this.selectedPath);return h('article',{class:`cms-builder-node${selected?' selected':''}${this.dropClass(node.id)}`,key:node.id,role:'button',tabindex:0,'aria-pressed':selected,'data-builder-node-id':node.id,'data-builder-drop-path':pathKey(p),onClick:e=>{e.stopPropagation();this.selectPath(p)},onKeydown:e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();e.stopPropagation();this.selectPath(p)}}},[h('div',{class:'cms-builder-node-head'},[h('div',{},[h('strong',{},def?.title||node.type),h('small',{style:{display:'block',opacity:.6}},node.id)]),h('div',{style:ui.row},[this.canEdit?h('span',{class:'cms-builder-drag-handle',title:tr('builder_page.drag_hint'),onPointerdown:e=>this.dragStart(e,p)},'⋮⋮'):null,h(LBadge,{label:`v${node.version||1}`}),node.children?.length?h(LBadge,{label:tr('builder_page.child_count','',{count:node.children.length}),severity:'secondary'}):null])]),node.attributes?.text?h('p',{style:{margin:0}},String(node.attributes.text).slice(0,180)):node.attributes?.label?h('p',{style:{margin:0}},String(node.attributes.label)):null,node.children?.length?h('div',{class:'cms-builder-node-children'},this.renderCanvasNodes(node.children,p)):null])})},
      renderLayers(nodes,path=[]){return (nodes||[]).map((node,index)=>{const p=[...path,index],selected=pathKey(p)===pathKey(this.selectedPath),def=defFor(this.blocks,node.type);return h('div',{key:node.id},[h('div',{class:`cms-builder-layer${selected?' selected':''}${this.dropClass(node.id)}`,role:'button',tabindex:0,'aria-pressed':selected,'data-builder-node-id':node.id,onClick:()=>this.selectPath(p),onKeydown:e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();this.selectPath(p)}}},[this.canEdit?h('span',{class:'cms-builder-drag-handle',onPointerdown:e=>this.dragStart(e,p)},'⋮⋮'):null,h('div',{style:{minWidth:0,flex:1}},[h('strong',{},def?.title||node.type),h('code',{style:{display:'block'}},node.id)])]),node.children?.length?h('div',{class:'cms-builder-layer-indent'},this.renderLayers(node.children,p)):null])})},
    },
    render(){
      const status=this.record?.status||'not-open';
      return h(LPage,{title:tr('routes.builder.title'),description:tr('routes.builder.lead')},{default:()=>h('div',{class:'cms-builder-center'},[
        message(h,this),
        h('div',{class:'cms-builder-toolbar'},[
          h(LButton,{label:tr('builder_page.open_create'),severity:'secondary',disabled:this.busy,onClick:this.open}),
          h(LButton,{label:tr('builder_page.undo'),severity:'secondary',disabled:!this.canUndo,onClick:this.undo}),h(LButton,{label:tr('builder_page.redo'),severity:'secondary',disabled:!this.canRedo,onClick:this.redo}),
          h('div',{class:'grow'}),
          h('div',{role:'group','aria-label':tr('a11y.builder_viewport',tr('builder_page.viewport')),style:ui.row},['desktop','tablet','mobile'].map(v=>h(LButton,{label:v,severity:this.viewport===v?'primary':'secondary','aria-pressed':this.viewport===v,onClick:()=>this.viewport=v}))),
          h(LButton,{label:tr('builder_page.preview'),severity:'secondary',disabled:this.busy||!this.canPreview,onClick:this.preview}),h(LButton,{label:tr('builder_page.save'),disabled:!this.record||this.busy||!this.canEdit,onClick:this.save}),h(LButton,{label:tr('builder_page.publish'),disabled:!this.record||this.busy||!this.canPublish,onClick:this.publish}),
        ]),
        h('div',{style:ui.grid},[
          h(LStatCard,{label:tr('builder_page.status'),value:status}),h(LStatCard,{label:tr('builder_page.version'),value:String(this.record?.version||'—')}),h(LStatCard,{label:tr('builder_page.blocks'),value:String(this.blockCount)}),h(LStatCard,{label:tr('builder_page.autosave'),value:String(this.lastAutosave||'—')}),
        ]),
        h(LPanel,{title:tr('builder_page.target')},{default:()=>h('div',{style:ui.row},[
          select(h,this.target.type,v=>this.target.type=v,[{value:'content',label:'Content'},{value:'template',label:'Template'},{value:'template_part',label:'Template Part'},{value:'site',label:'Site'}]),
          input(h,this.target.key,v=>this.target.key=v,'text',{placeholder:'home / page:42',style:{maxWidth:'240px'}}),select(h,this.target.locale,v=>this.target.locale=v,localeOptions()),input(h,this.target.site_id,v=>this.target.site_id=Number(v)||1,'number',{min:1,style:{maxWidth:'90px'}}),
          this.record?h('div',{class:'cms-builder-status'},[h(LBadge,{label:this.dirty?tr('builder_page.unsaved'):tr('builder_page.synced'),severity:this.dirty?'warning':'success'}),h('code',{},`#${this.record.id}`)]):null,
        ])}),
        h('div',{class:'cms-builder-shell'},[
          h('aside',{class:'cms-builder-side start'},[
            h(LPanel,{title:tr('builder_page.block_library')},{default:()=>h('div',{class:'cms-builder-library'},[
              input(h,this.blockSearch,v=>this.blockSearch=v,'search',{placeholder:tr('builder_page.block_search'),'aria-label':tr('a11y.builder_block_search',tr('builder_page.block_search'))}),...this.filteredBlocks.map(b=>h('button',{type:'button',disabled:!this.canEdit,onClick:()=>this.insertBlock(b)},[h('strong',{},b.title||b.name),h('small',{},`${b.category||'block'} · ${b.name||b.id}`)])),
            ])}),
            h(LPanel,{title:tr('builder_page.layers')},{default:()=>this.document.blocks.length?h('div',{},this.renderLayers(this.document.blocks)):h('p',{style:{opacity:.65}},tr('builder_page.document_empty'))}),
          ]),
          h('main',{class:'cms-builder-canvas-wrap',onClick:()=>this.selectPath(null)},[h('div',{class:'cms-builder-frame','data-viewport':this.viewport},[...(this.document.blocks.length?this.renderCanvasNodes(this.document.blocks):[h('div',{class:'cms-builder-empty'},[h('strong',{},tr('builder_page.canvas_empty')),h('span',{},tr('builder_page.add_block'))])]),h('div',{class:`cms-builder-root-drop${this.drag.active&&this.drag.mode==='root'?' active':''}`,'data-builder-root-drop':'1'},this.drag.active?tr('builder_page.drop_root'):'')])]),
          h('aside',{class:'cms-builder-side end'},[
            h(LPanel,{title:`${tr('builder_page.inspector')} · ${this.viewport}`},{default:()=>this.renderInspector()}),
            h(LPanel,{title:tr('builder_page.revision_history')},{default:()=>h('div',{class:'cms-builder-revisions'},[
              h('div',{style:ui.row},[h(LButton,{label:tr('builder_page.autosave'),severity:'secondary',disabled:!this.record||!this.canEdit,onClick:this.autosave}),h(LButton,{label:tr('builder_page.load_history'),severity:'secondary',disabled:!this.record,onClick:this.loadRevisions})]),
              ...this.revisions.slice(0,20).map(r=>h('div',{class:'cms-builder-revision'},[h('div',{},[h('strong',{},r.kind||'revision'),h('small',{style:{display:'block',opacity:.65}},`#${r.id} · ${r.created_at||''}`)]),h(LButton,{label:tr('builder_page.restore'),severity:'secondary',onClick:()=>this.restore(r)})])),
            ])}),
          ]),
        ]),
        this.previewHtml?h(LPanel,{title:tr('builder_page.preview_title','',{viewport:this.viewport})},{default:()=>h('iframe',{class:'cms-builder-preview',sandbox:'',srcdoc:this.previewHtml,title:'Builder preview',style:{maxWidth:this.viewport==='mobile'?'390px':this.viewport==='tablet'?'768px':'100%',margin:'0 auto'}})}):null,
        h('details',{},[h('summary',{style:{cursor:'pointer',fontWeight:600}},tr('builder_page.advanced_json')),h('div',{style:{marginTop:'10px'}},[h(LBadge,{label:this.jsonValid?tr('builder_page.valid'):tr('builder_page.invalid'),severity:this.jsonValid?'success':'danger'}),textarea(h,this.advancedJson,v=>this.syncFromAdvanced(v),{class:'cms-builder-json',style:ui.mono,spellcheck:'false',disabled:!this.canEdit})])]),
      ])})
    },
  }
}
