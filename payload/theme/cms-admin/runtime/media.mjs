import { api, ui, input, textarea, select, message, confirmFa, tr, locale } from './common.mjs'

const CSS = `
.cms-media-center{display:grid;gap:16px;direction:inherit}.cms-media-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.cms-media-toolbar .grow{flex:1;min-width:220px}.cms-media-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px}.cms-media-stat{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;padding:12px;display:grid;gap:4px;background:var(--p-surface-0,#fff)}.cms-media-stat strong{font-size:1.25rem}.cms-media-stat small{opacity:.68}.cms-media-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,390px);gap:14px;align-items:start}.cms-media-layout[data-detail="false"]{grid-template-columns:1fr}.cms-media-library{display:grid;gap:12px}.cms-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}.cms-media-list{display:grid;gap:8px}.cms-media-card{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;overflow:hidden;background:var(--p-surface-0,#fff);display:grid;cursor:pointer;min-width:0}.cms-media-card[data-selected="true"]{border-color:var(--p-primary-color,#2563eb);box-shadow:0 0 0 2px color-mix(in srgb,var(--p-primary-color,#2563eb) 18%,transparent)}.cms-media-preview{aspect-ratio:4/3;background:var(--p-surface-100,#f3f4f6);display:grid;place-items:center;overflow:hidden;position:relative}.cms-media-preview img{width:100%;height:100%;object-fit:cover}.cms-media-preview .kind{font-size:38px;opacity:.65}.cms-media-preview .warn{position:absolute;inset:8px auto auto 8px;background:#b45309;color:#fff;border-radius:999px;padding:4px 7px;font-size:10px}.cms-media-body{padding:10px;display:grid;gap:6px}.cms-media-body strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cms-media-meta{font-size:11px;opacity:.7;display:flex;gap:5px;flex-wrap:wrap}.cms-media-list-row{display:grid;grid-template-columns:64px minmax(180px,1.5fr) minmax(110px,.5fr) minmax(100px,.5fr) minmax(160px,.7fr);gap:10px;align-items:center;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px;padding:8px;background:var(--p-surface-0,#fff);cursor:pointer}.cms-media-list-row[data-selected="true"]{border-color:var(--p-primary-color,#2563eb)}.cms-media-list-thumb{width:58px;height:48px;border-radius:8px;background:var(--p-surface-100,#f3f4f6);overflow:hidden;display:grid;place-items:center}.cms-media-list-thumb img{width:100%;height:100%;object-fit:cover}.cms-media-detail{position:sticky;top:12px;display:grid;gap:12px}.cms-media-detail-preview{background:var(--p-surface-100,#f3f4f6);border-radius:12px;min-height:190px;max-height:320px;display:grid;place-items:center;overflow:hidden}.cms-media-detail-preview img,.cms-media-detail-preview video{max-width:100%;max-height:320px}.cms-media-detail-preview audio{width:90%}.cms-media-fields{display:grid;gap:10px}.cms-media-actions{display:flex;gap:8px;flex-wrap:wrap}.cms-media-info{display:grid;grid-template-columns:1fr 1fr;gap:7px;font-size:12px}.cms-media-info div{background:var(--p-surface-50,#f9fafb);border-radius:8px;padding:8px;overflow-wrap:anywhere}.cms-media-usage-list,.cms-media-variant-list,.cms-upload-queue{display:grid;gap:7px}.cms-media-usage,.cms-media-variant,.cms-upload-item{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:9px;padding:8px;font-size:12px;display:grid;gap:3px}.cms-upload-item{grid-template-columns:1fr auto;align-items:center}.cms-upload-item small{opacity:.7}.cms-upload-drop{border:2px dashed var(--p-surface-300,#d1d5db);border-radius:14px;padding:18px;text-align:center;display:grid;gap:7px}.cms-upload-drop.drag{border-color:var(--p-primary-color,#2563eb);background:var(--p-primary-50,#eef2ff)}.cms-media-empty{text-align:center;padding:36px 16px;border:1px dashed var(--p-surface-300,#d1d5db);border-radius:14px;opacity:.72}.cms-media-pagination{display:flex;justify-content:space-between;gap:8px;align-items:center;flex-wrap:wrap}.cms-media-progress{height:6px;border-radius:999px;background:var(--p-surface-200,#e5e7eb);overflow:hidden}.cms-media-progress span{display:block;height:100%;background:var(--p-primary-color,#2563eb)}
@media(max-width:1080px){.cms-media-stats{grid-template-columns:repeat(3,minmax(0,1fr))}.cms-media-layout{grid-template-columns:1fr}.cms-media-detail{position:static}}
@media(max-width:700px){.cms-media-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.cms-media-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cms-media-list-row{grid-template-columns:56px 1fr}.cms-media-list-row>[data-mobile-hide="true"]{display:none}.cms-media-toolbar>*{flex:1;min-width:140px}.cms-media-toolbar .grow{min-width:100%;flex-basis:100%}.cms-media-info{grid-template-columns:1fr}.cms-media-detail{margin-top:0}}
@media(max-width:420px){.cms-media-grid{grid-template-columns:1fr}.cms-media-stats{grid-template-columns:1fr 1fr}}
`

function kindIcon(kind){ return ({ image:'image', video:'video', audio:'audio-lines', document:'file-text' })[kind] || 'file' }
function kindLabel(kind){ return ({ image:tr('media_page.image'), video:tr('media_page.video'), audio:tr('media_page.audio'), document:tr('media_page.document') })[kind] || kind || tr('media_page.file') }
function bytes(value){ const n=Number(value||0); if(n<1024)return `${n} B`; if(n<1048576)return `${(n/1024).toFixed(1)} KB`; if(n<1073741824)return `${(n/1048576).toFixed(1)} MB`; return `${(n/1073741824).toFixed(2)} GB` }
function localizedDate(value){ if(!value)return '—'; try{return new Date(value).toLocaleString(locale())}catch{return String(value)} }
function safeName(value){ return String(value||'').replace(/[\u0000-\u001f]/g,'').slice(0,255) }
function mediaTitle(asset){ return asset?.title || asset?.original_name || `#${asset?.id||'—'}` }

export function createComponent(host){
  const { h, LPage, LPanel, LButton, LBadge } = host
  return {
    name:'CmsMediaAdminCenter',
    data(){return{
      items:[], summary:{}, pagination:{limit:48,offset:0,returned:0,has_more:false}, policy:{allowed_extensions:[],max_bytes:0,max_mb:0}, kinds:['image','video','audio','document'],
      query:'', kind:'', view:'grid', loading:false, libraryLoaded:false, error:'', notice:'', detail:null, detailLoading:false, saving:false, deleting:false,
      uploads:[], uploading:false, drag:false,
      form:{title:'',alt:'',caption:'',description:'',focal_x:'',focal_y:''}, draftDirty:false,
      searchTimer:null, libraryRequest:0, selectionRequest:0,
    }},
    mounted(){ this.load() },
    beforeUnmount(){ if(this.searchTimer)clearTimeout(this.searchTimer) },
    methods:{
      async load(resetDetail=false){
        const request=++this.libraryRequest;this.loading=true;this.error='';
        try{
          const q=new URLSearchParams({limit:String(this.pagination.limit),offset:String(this.pagination.offset)})
          if(this.query.trim())q.set('q',this.query.trim()); if(this.kind)q.set('kind',this.kind)
          const data=await api(`/media?${q}`)
          if(request!==this.libraryRequest)return
          this.items=Array.isArray(data.items)?data.items:[];this.summary=data.summary||{};this.pagination={...this.pagination,...(data.pagination||{})};this.policy=data.upload_policy||this.policy;this.kinds=Array.isArray(data.kinds)?data.kinds:this.kinds;this.libraryLoaded=true
          if(resetDetail)this.clearDetail()
          else if(this.detail){ const updated=this.items.find(i=>Number(i.id)===Number(this.detail.id)); if(updated)this.detail={...this.detail,...updated} }
        }catch(e){if(request===this.libraryRequest)this.error=e.message}finally{if(request===this.libraryRequest)this.loading=false}
      },
      queueSearch(){ if(this.searchTimer)clearTimeout(this.searchTimer); this.pagination.offset=0; this.searchTimer=setTimeout(()=>this.load(false),350) },
      changeKind(value){if(!this.confirmDiscardDraft())return;this.kind=value;this.pagination.offset=0;this.load(true)},
      setView(value){this.view=value},
      confirmDiscardDraft(){return !this.draftDirty||confirmFa(tr('media_page.discard_draft_confirm'))},
      clearDetail(){this.detail=null;this.form={title:'',alt:'',caption:'',description:'',focal_x:'',focal_y:''};this.draftDirty=false;this.selectionRequest++},
      async openDetail(asset){
        if(!asset?.id||Number(asset.id)===Number(this.detail?.id))return
        if(!this.confirmDiscardDraft())return
        const request=++this.selectionRequest;this.detailLoading=true;this.error='';
        try{const detail=await api(`/media/${asset.id}`);if(request!==this.selectionRequest)return;this.detail=detail;this.form={title:detail.title||'',alt:detail.alt||'',caption:detail.caption||'',description:detail.description||'',focal_x:detail.focal_x??'',focal_y:detail.focal_y??''};this.draftDirty=false}
        catch(e){if(request===this.selectionRequest)this.error=e.message}finally{if(request===this.selectionRequest)this.detailLoading=false}
      },
      closeDetail(){if(!this.confirmDiscardDraft())return;this.clearDetail()},
      async saveMetadata(){
        if(!this.detail?.id||this.saving)return
        const id=this.detail.id
        const body={title:String(this.form.title||''),alt:String(this.form.alt||''),caption:String(this.form.caption||''),description:String(this.form.description||'')}
        if(this.detail.kind==='image'){body.focal_x=this.form.focal_x===''?null:Number(this.form.focal_x);body.focal_y=this.form.focal_y===''?null:Number(this.form.focal_y)}
        const submitted=JSON.stringify({form:this.form,body})
        this.saving=true;this.error='';this.notice=''
        try{
          const updated=await api(`/media/${id}`,{method:'PATCH',body})
          if(Number(this.detail?.id)===Number(id)){
            this.detail={...this.detail,...updated}
            if(JSON.stringify({form:this.form,body})===submitted){this.form={title:updated.title||'',alt:updated.alt||'',caption:updated.caption||'',description:updated.description||'',focal_x:updated.focal_x??'',focal_y:updated.focal_y??''};this.draftDirty=false;this.notice=tr('media_page.saved')}
            else{this.draftDirty=true;this.notice=tr('media_page.saved_newer_draft')}
          }
          await this.load(false)
        }catch(e){this.error=e.message}finally{this.saving=false}
      },
      async remove(){
        if(!this.detail?.id||this.deleting)return;if(!confirmFa(tr('media_page.delete_native_confirm','',{title:mediaTitle(this.detail)})))return
        this.deleting=true;this.error='';this.notice=''
        try{await api(`/media/${this.detail.id}`,{method:'DELETE'});this.notice=tr('media_page.deleted');this.clearDetail();await this.load(true)}
        catch(e){
          if(e.code==='MEDIA_IN_USE'&&Array.isArray(e.details?.usages)){this.detail={...this.detail,in_use:true,usage_count:e.details.usages.length,usages:e.details.usages};this.error=tr('media_page.in_use')}
          else this.error=e.message
        }finally{this.deleting=false}
      },
      async copyUrl(){const url=this.detail?.url;if(!url)return;try{await navigator.clipboard.writeText(url);this.notice=tr('media_page.url_copied')}catch{this.error=tr('media_page.url_copy_failed')}},
      pickFiles(){this.$refs?.fileInput?.click?.()},
      onDrop(event){event.preventDefault();this.drag=false;this.addFiles([...event.dataTransfer.files||[]])},
      onFileChange(event){const files=[...(event.target.files||[])];event.target.value='';this.addFiles(files)},
      addFiles(files){
        const allowed=new Set((this.policy.allowed_extensions||[]).map(v=>String(v).toLowerCase()))
        const max=Number(this.policy.max_bytes||0)
        const rows=[]
        for(const file of files){
          const ext=String(file.name.split('.').pop()||'').toLowerCase();let status='queued',error=''
          if(allowed.size&&(!ext||!allowed.has(ext))){status='rejected';error=tr('media_page.extension_rejected')}
          else if(max>0&&file.size>max){status='rejected';error=tr('media_page.size_rejected','',{max:this.policy.max_mb||Math.round(max/1048576)})}
          rows.push({id:`${Date.now()}-${Math.random()}`,file,name:safeName(file.name),size:file.size,status,error})
        }
        this.uploads=[...this.uploads,...rows];if(rows.some(r=>r.status==='queued'))this.runUploads()
      },
      async runUploads(){
        if(this.uploading)return;this.uploading=true;let completed=0
        try{
          for(const row of this.uploads){
            if(row.status!=='queued')continue;row.status='uploading';row.error=''
            try{const form=new FormData();form.append('file',row.file,row.file.name);form.append('site_id','1');form.append('public','1');const created=await api('/media',{method:'POST',form});row.status='done';row.assetId=created.id;completed++}
            catch(e){row.status='error';row.error=e.message}
          }
          if(completed){this.notice=tr('media_page.uploaded_count','',{count:completed});this.pagination.offset=0;await this.load(true)}
        }finally{this.uploading=false}
      },
      retryUpload(row){if(!row||row.status!=='error'||this.uploading)return;row.status='queued';row.error='';this.runUploads()},
      cancelUpload(row){if(!row||row.status==='uploading')return;this.uploads=this.uploads.filter(item=>item.id!==row.id)},
      clearUploads(){if(this.uploading)return;this.uploads=[]},
      nextPage(){if(this.pagination.has_more&&!this.loading&&this.confirmDiscardDraft()){this.pagination.offset+=this.pagination.limit;this.load(true)}},
      prevPage(){if(this.pagination.offset>0&&!this.loading&&this.confirmDiscardDraft()){this.pagination.offset=Math.max(0,this.pagination.offset-this.pagination.limit);this.load(true)}},
      dimensions(asset){return asset?.width&&asset?.height?`${asset.width}×${asset.height}`:'—'},
      kindIcon,kindLabel,bytes,localizedDate,mediaTitle,
    },
    render(){
      const stat=(label,value,sub='')=>h('article',{class:'cms-media-stat'},[h('small',{},label),h('strong',{},String(value??0)),sub?h('span',{style:{fontSize:'11px',opacity:.65}},sub):null])
      const unknown='—',known=(value)=>this.libraryLoaded?(value??0):unknown
      const stats=h('div',{class:'cms-media-stats'},[
        stat(tr('media_page.all_media'),known(this.summary.total)),stat(tr('media_page.image'),known(this.summary.image)),stat(tr('media_page.video'),known(this.summary.video)),stat(tr('media_page.audio_document'),this.libraryLoaded?(Number(this.summary.audio||0)+Number(this.summary.document||0)):unknown),stat(tr('media_page.missing_alt'),known(this.summary.missing_alt),tr('media_page.missing_alt_hint')),stat(tr('media_page.registered_space'),this.libraryLoaded?bytes(this.summary.total_bytes||0):unknown),
      ])
      const filters=h(LPanel,{title:tr('media_page.library')},{default:()=>h('div',{class:'cms-media-toolbar'},[
        input(h,this.query,v=>{this.query=v;this.queueSearch()},'search',{class:'grow',placeholder:tr('media_page.search_placeholder')}),
        select(h,this.kind,v=>this.changeKind(v),[{value:'',label:tr('media_page.all_kinds')},...this.kinds.map(v=>({value:v,label:kindLabel(v)}))]),
        h(LButton,{label:this.view==='grid'?tr('media_page.list_view'):tr('media_page.grid_view'),severity:'secondary',onClick:()=>this.setView(this.view==='grid'?'list':'grid')}),
        h(LButton,{label:tr('media_page.refresh'),severity:'secondary',disabled:this.loading,onClick:()=>this.load(false)}),
      ])})
      const upload=h(LPanel,{title:tr('media_page.secure_upload')},{default:()=>h('div',{style:{display:'grid',gap:'10px'}},[
        h('input',{ref:'fileInput',type:'file',multiple:true,'aria-label':tr('a11y.media_upload',tr('media_page.upload_input')),style:{display:'none'},accept:(this.policy.allowed_extensions||[]).map(v=>'.'+v).join(','),onChange:this.onFileChange}),
        h('div',{class:`cms-upload-drop ${this.drag?'drag':''}`,onDragover:e=>{e.preventDefault();this.drag=true},onDragleave:()=>this.drag=false,onDrop:this.onDrop},[
          h('strong',{},tr('media_page.drop_files')),h('small',{},tr('media_page.max_policy','',{max:this.policy.max_mb||20,extensions:(this.policy.allowed_extensions||[]).join(', ')||tr('media_page.server_policy')})),h(LButton,{label:this.uploading?tr('media_page.uploading'):tr('media_page.select_files'),disabled:this.uploading,onClick:this.pickFiles})
        ]),
        this.uploads.length?h('div',{class:'cms-upload-queue'},this.uploads.map(row=>h('article',{class:'cms-upload-item',key:row.id},[h('div',{},[h('strong',{},row.name),h('small',{},`${bytes(row.size)} · ${row.status}`),row.error?h('small',{style:{color:'#b91c1c'}},row.error):null]),h(LBadge,{label:row.status,severity:row.status==='done'?'success':row.status==='error'||row.status==='rejected'?'danger':row.status==='uploading'?'info':'secondary'}),row.status==='error'?h(LButton,{label:tr('media_page.retry_file'),severity:'secondary',disabled:this.uploading,onClick:()=>this.retryUpload(row)}):null,(row.status==='queued'||row.status==='error')?h(LButton,{label:tr('media_page.cancel_file'),severity:'secondary',onClick:()=>this.cancelUpload(row)}):null]))):null,
        this.uploads.length?h('div',{class:'cms-media-actions'},[h(LButton,{label:tr('media_page.clear_queue'),severity:'secondary',disabled:this.uploading,onClick:this.clearUploads})]):null,
      ])})
      let collection=null
      if(this.loading) collection=h('p',{role:'status','aria-live':'polite'},tr('media_page.loading_library'))
      else if(this.error&&!this.libraryLoaded) collection=h('div',{class:'cms-media-empty',role:'alert'},[h('strong',{},tr('media_page.load_failed')),h('p',{},tr('media_page.load_failed_hint')),h(LButton,{label:tr('media_page.refresh'),severity:'secondary',onClick:()=>this.load(false)})])
      else if(!this.items.length) collection=h('div',{class:'cms-media-empty'},[h('strong',{},tr('media_page.not_found')),h('p',{},this.query||this.kind?tr('media_page.filtered_empty'):tr('media_page.first_upload'))])
      else if(this.view==='list') collection=h('div',{class:'cms-media-list'},this.items.map(asset=>h('article',{class:'cms-media-list-row',key:asset.id,role:'button',tabindex:0,'aria-pressed':String(Number(this.detail?.id)===Number(asset.id)),'data-selected':Number(this.detail?.id)===Number(asset.id),onClick:()=>this.openDetail(asset),onKeydown:e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();this.openDetail(asset)}}},[
        h('div',{class:'cms-media-list-thumb'},[asset.kind==='image'&&(asset.thumb||asset.url)?h('img',{src:asset.thumb||asset.url,alt:asset.alt||''}):h('span',{},kindIcon(asset.kind))]),
        h('div',{style:{minWidth:0,display:'grid',gap:'4px'}},[h('strong',{style:{display:'block',overflow:'hidden',textOverflow:'ellipsis',whiteSpace:'nowrap'}},mediaTitle(asset)),h('small',{style:{opacity:.65}},asset.original_name||''),asset.kind==='image'&&!String(asset.alt||'').trim()?h(LBadge,{label:tr('media_page.missing_alt'),severity:'warning'}):null]),
        h('span',{'data-mobile-hide':'true'},kindLabel(asset.kind)),h('span',{'data-mobile-hide':'true'},bytes(asset.size)),h('div',{'data-mobile-hide':'true'},[asset.kind==='image'&&!asset.alt?h(LBadge,{label:tr('media_page.missing_alt'),severity:'warning'}):h(LBadge,{label:asset.status||'ready',severity:'success'})])
      ])))
      else collection=h('div',{class:'cms-media-grid'},this.items.map(asset=>h('article',{class:'cms-media-card',key:asset.id,role:'button',tabindex:0,'aria-pressed':String(Number(this.detail?.id)===Number(asset.id)),'data-selected':Number(this.detail?.id)===Number(asset.id),onClick:()=>this.openDetail(asset),onKeydown:e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();this.openDetail(asset)}}},[
        h('div',{class:'cms-media-preview'},[asset.kind==='image'&&(asset.thumb||asset.url)?h('img',{src:asset.thumb||asset.url,alt:asset.alt||''}):h('span',{class:'kind'},kindIcon(asset.kind)),asset.kind==='image'&&!String(asset.alt||'').trim()?h('span',{class:'warn'},tr('media_page.no_alt')):null]),
        h('div',{class:'cms-media-body'},[h('strong',{},mediaTitle(asset)),h('div',{class:'cms-media-meta'},[h('span',{},kindLabel(asset.kind)),h('span',{},'·'),h('span',{},bytes(asset.size)),asset.width?h('span',{},`· ${this.dimensions(asset)}`):null]),h('div',{class:'cms-media-meta'},[h('span',{},asset.mime||'')])])
      ])))
      const total=Number(this.pagination.total??this.summary.total??0)
      const range=total<=0?tr('media_page.range_empty'):tr('media_page.range','',{from:Math.min(total,this.pagination.offset+1),to:Math.min(total,this.pagination.offset+(this.pagination.returned||this.items.length||0)),total})
      const library=h('div',{class:'cms-media-library'},[collection,h('div',{class:'cms-media-pagination'},[h(LButton,{label:tr('media_page.previous'),severity:'secondary',disabled:this.pagination.offset<=0||this.loading,onClick:this.prevPage}),h('span',{},range),h(LButton,{label:tr('media_page.next'),severity:'secondary',disabled:!this.pagination.has_more||this.loading,onClick:this.nextPage})])])
      const detail=this.detail?h(LPanel,{title:tr('media_page.details')},{default:()=>h('div',{class:'cms-media-detail'},[
        h('div',{class:'cms-media-detail-preview'},[this.detail.kind==='image'&&this.detail.url?h('img',{src:this.detail.url,alt:this.form.alt||''}):this.detail.kind==='video'&&this.detail.url?h('video',{src:this.detail.url,controls:true}):this.detail.kind==='audio'&&this.detail.url?h('audio',{src:this.detail.url,controls:true}):h('span',{style:{fontSize:'44px'}},kindIcon(this.detail.kind))]),
        h('div',{class:'cms-media-info'},[h('div',{},[h('strong',{},tr('media_page.filename')),h('br'),h('span',{},this.detail.original_name||'—')]),h('div',{},[h('strong',{},'MIME'),h('br'),h('span',{},this.detail.mime||'—')]),h('div',{},[h('strong',{},tr('media_page.size')),h('br'),h('span',{},bytes(this.detail.size))]),h('div',{},[h('strong',{},tr('media_page.dimensions')),h('br'),h('span',{},this.dimensions(this.detail))]),h('div',{},[h('strong',{},tr('media_page.created')),h('br'),h('span',{},localizedDate(this.detail.created_at))]),h('div',{},[h('strong',{},tr('media_page.usage')),h('br'),h('span',{},String(this.detail.usage_count??0))])]),
        h('div',{class:'cms-media-fields'},[
          h('label',{style:ui.field},[h('span',{},tr('media_page.title')),input(h,this.form.title,v=>{this.form.title=v;this.draftDirty=true})]),
          this.detail.kind==='image'?h('label',{style:ui.field},[h('span',{},tr('media_page.alt')),input(h,this.form.alt,v=>{this.form.alt=v;this.draftDirty=true},'text',{placeholder:tr('media_page.alt_placeholder')})]):null,
          h('label',{style:ui.field},[h('span',{},tr('media_page.caption')),textarea(h,this.form.caption,v=>{this.form.caption=v;this.draftDirty=true},{rows:3})]),
          h('label',{style:ui.field},[h('span',{},tr('media_page.description')),textarea(h,this.form.description,v=>{this.form.description=v;this.draftDirty=true},{rows:4})]),
          this.detail.kind==='image'?h('div',{style:{display:'grid',gridTemplateColumns:'1fr 1fr',gap:'8px'}},[h('label',{style:ui.field},[h('span',{},tr('media_page.focal_x')),input(h,this.form.focal_x,v=>{this.form.focal_x=v;this.draftDirty=true},'number',{min:'0',max:'1',step:'0.01'})]),h('label',{style:ui.field},[h('span',{},tr('media_page.focal_y')),input(h,this.form.focal_y,v=>{this.form.focal_y=v;this.draftDirty=true},'number',{min:'0',max:'1',step:'0.01'})])]):null,
        ]),
        h('div',{class:'cms-media-actions'},[h(LButton,{label:this.saving?tr('media_page.saving'):tr('media_page.save'),disabled:this.saving,onClick:this.saveMetadata}),this.detail.url?h(LButton,{label:tr('media_page.copy_url'),severity:'secondary',onClick:this.copyUrl}):null,h(LButton,{label:tr('media_page.close'),severity:'secondary',onClick:this.closeDetail}),h(LButton,{label:this.deleting?tr('media_page.deleting'):tr('media_page.delete'),severity:'danger',disabled:this.deleting,onClick:this.remove})]),
        h(LPanel,{title:tr('media_page.usages','',{count:this.detail.usage_count??0})},{default:()=>this.detail.usages?.length?h('div',{class:'cms-media-usage-list'},this.detail.usages.map(u=>h('article',{class:'cms-media-usage',key:u.id},[h('strong',{},`${u.resource_type} #${u.resource_id}`),h('span',{},`Context: ${u.context}`)]))):h('p',{style:{opacity:.7}},tr('media_page.no_usages'))}),
        h(LPanel,{title:tr('media_page.variants','',{count:this.detail.variant_count??0})},{default:()=>this.detail.variants?.length?h('div',{class:'cms-media-variant-list'},this.detail.variants.map(v=>h('article',{class:'cms-media-variant',key:v.id},[h('strong',{},v.variant_key||v.key),h('span',{},`${v.width||'—'}×${v.height||'—'} · ${bytes(v.size||0)} · ${v.mime||'—'}`)]))):h('p',{style:{opacity:.7}},tr('media_page.no_variants'))}),
      ])}):null
      return h(LPage,{title:tr('routes.media.title'),description:tr('routes.media.lead')},{default:()=>h('div',{class:'cms-media-center'},[h('style',{},CSS),message(h,this),stats,upload,filters,h('div',{class:'cms-media-layout','data-detail':Boolean(this.detail)},[library,detail])])})
    },
  }
}
