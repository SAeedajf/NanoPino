import { api, ui, input, message, confirmFa, routeButton, tr } from './common.mjs'

function validId(value){const id=Number(value);return Number.isSafeInteger(id)&&id>0?String(id):''}
function kindLabel(kind){return ({initial:tr('revisions_page.kind_initial'),manual:tr('revisions_page.kind_manual'),autosave:tr('revisions_page.kind_autosave'),published:tr('revisions_page.kind_published'),scheduled:tr('revisions_page.kind_scheduled'),pre_restore:tr('revisions_page.kind_pre_restore'),restored:tr('revisions_page.kind_restored')})[kind]||kind}
function kindSeverity(kind){return kind==='published'||kind==='restored'?'success':kind==='autosave'?'info':kind==='pre_restore'?'warning':'secondary'}
function formatted(value){if(!value)return'—';try{return new Date(value).toLocaleString()}catch{return String(value)}}
function displayValue(value){if(value===undefined)return'—';if(value===null)return'null';const text=typeof value==='string'?value:JSON.stringify(value,null,2);return String(text).length>700?String(text).slice(0,700)+'…':String(text)}
function currentSnapshot(row){return{site_id:row?.site_id,content_type:row?.type,status:row?.status,title:row?.title||'',slug:row?.slug||'',excerpt:row?.excerpt||'',author_id:row?.author_id??null,parent_id:row?.parent_id??null,locale:row?.locale||'fa',document:row?.document||{},metadata:row?.metadata||{},fields:row?.fields||{},relations:row?.relations||{},terms:row?.terms||{},published_at:row?.published_at??null,scheduled_at:row?.scheduled_at??null}}
function collectDiff(path,current,revision,out){
  if(JSON.stringify(current)===JSON.stringify(revision))return
  const a=current&&typeof current==='object'&&!Array.isArray(current),b=revision&&typeof revision==='object'&&!Array.isArray(revision)
  if(a&&b){for(const key of [...new Set([...Object.keys(current),...Object.keys(revision)])].sort())collectDiff(path?path+'.'+key:key,current[key],revision[key],out);return}
  out.push({path:path||'$',current,revision})
}
const CSS=`
.cms-revision-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end}.cms-revision-content-list,.cms-revision-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px}.cms-revision-content,.cms-revision-card{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px;padding:11px;background:var(--p-surface-0,#fff);color:inherit;text-align:start;display:grid;gap:7px}.cms-revision-content{cursor:pointer}.cms-revision-content[data-active="true"],.cms-revision-card[data-active="true"]{border-color:var(--p-primary-color,#2563eb)}.cms-revision-actions,.cms-revision-paging,.cms-revision-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.cms-revision-paging{justify-content:space-between}.cms-revision-state{padding:24px 14px;text-align:center;display:grid;gap:8px;border:1px dashed var(--p-surface-300,#d1d5db);border-radius:12px}.cms-revision-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.cms-revision-summary>div{display:grid;gap:4px;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:10px;padding:10px}.cms-revision-diff{display:grid;gap:4px;max-height:560px;overflow:auto}.cms-revision-diff-row{display:grid;grid-template-columns:minmax(140px,.7fr) minmax(0,1fr) minmax(0,1fr);gap:8px;padding:8px;border-bottom:1px solid var(--p-surface-200,#e5e7eb)}.cms-revision-diff-row pre{white-space:pre-wrap;overflow-wrap:anywhere;margin:0;font:inherit}.cms-revision-muted{opacity:.7}@media(max-width:720px){.cms-revision-search,.cms-revision-summary,.cms-revision-diff-row{grid-template-columns:1fr}.cms-revision-diff-head{display:none}.cms-revision-paging>*{flex:1}}
`

export function createComponent(host){
  const {h,LPage,LPanel,LButton,LBadge}=host
  return{
    name:'CmsRevisionsControlPlane',
    data(){return{contentId:'',contents:[],selectedContent:null,items:[],preview:null,contentSearch:'',contentPage:{limit:25,offset:0,total:0,has_more:false},loadingContents:false,loading:false,previewLoading:false,restoring:false,error:'',notice:''}},
    async mounted(){
      await this.searchContents(true)
      const requested=validId(new URLSearchParams(globalThis.location?.search||'').get('content'))
      if(requested){
        let item=this.contents.find(row=>validId(row.id)===requested)||null
        if(!item){try{item=await api(`/content/${requested}`)}catch(e){this.error=e.message}}
        if(item)await this.choose(item)
      }
    },
    methods:{
      async searchContents(reset=false){
        if(reset)this.contentPage.offset=0
        this.loadingContents=true;this.error=''
        try{
          const q=new URLSearchParams({limit:String(this.contentPage.limit),offset:String(this.contentPage.offset),projection:'list'})
          if(this.contentSearch.trim())q.set('search',this.contentSearch.trim())
          const result=await api(`/content?${q}`)
          this.contents=result.items||[];this.contentPage={...this.contentPage,...(result.pagination||{})}
        }catch(e){this.error=e.message}
        finally{this.loadingContents=false}
      },
      async choose(item){
        const id=validId(item?.id);if(!id)return
        this.contentId=id;this.preview=null;this.notice='';this.error=''
        try{
          this.selectedContent=await api(`/content/${id}`)
          await this.load()
          if(globalThis.history?.replaceState){const url=new URL(globalThis.location.href);url.searchParams.set('content',id);globalThis.history.replaceState({},'',url)}
        }catch(e){this.error=e.message}
      },
      async load(){
        if(!this.contentId)return
        this.loading=true;this.error=''
        try{this.items=(await api(`/content/${this.contentId}/revisions`)).items||[]}
        catch(e){this.error=e.message}
        finally{this.loading=false}
      },
      async previewRevision(revision){
        const revisionId=validId(revision?.id);if(!this.contentId||!revisionId)return
        this.previewLoading=true;this.error=''
        try{this.preview=await api(`/content/${this.contentId}/revisions/${revisionId}`)}
        catch(e){this.error=e.message}
        finally{this.previewLoading=false}
      },
      diffRows(){
        if(!this.preview?.snapshot||!this.selectedContent)return[]
        const rows=[];collectDiff('',currentSnapshot(this.selectedContent),this.preview.snapshot,rows);return rows.slice(0,200)
      },
      async restorePreview(){
        const revisionId=validId(this.preview?.id)
        if(!revisionId||this.preview?.kind==='autosave'||this.restoring)return
        if(!confirmFa(tr('revisions_page.restore_confirm','',{id:revisionId})))return
        this.restoring=true;this.error='';this.notice=''
        try{
          await api(`/content/${this.contentId}/revisions/${revisionId}/restore`,{method:'POST',body:{}})
          this.selectedContent=await api(`/content/${this.contentId}`)
          this.notice=tr('revisions_page.restored','',{id:revisionId});this.preview=null;await this.load()
        }catch(e){this.error=e.message}
        finally{this.restoring=false}
      },
      nextContents(){if(!this.contentPage.has_more||this.loadingContents)return;this.contentPage.offset+=this.contentPage.limit;this.searchContents()},
      previousContents(){if(this.contentPage.offset<=0||this.loadingContents)return;this.contentPage.offset=Math.max(0,this.contentPage.offset-this.contentPage.limit);this.searchContents()},
      async refreshAll(){await this.searchContents();if(this.contentId){try{this.selectedContent=await api(`/content/${this.contentId}`);await this.load()}catch(e){this.error=e.message}}},
    },
    render(){
      const diff=this.diffRows()
      const selector=h(LPanel,{title:tr('revisions_page.content_selector')},{default:()=>h('div',{style:ui.page},[
        h('div',{class:'cms-revision-search'},[
          h('label',{style:ui.field},[h('span',{},tr('revisions_page.search_content')),input(h,this.contentSearch,v=>this.contentSearch=v,'search',{placeholder:tr('revisions_page.search_placeholder'),onKeyup:e=>{if(e.key==='Enter')this.searchContents(true)}})]),
          h(LButton,{label:tr('revisions_page.search'),disabled:this.loadingContents,onClick:()=>this.searchContents(true)})
        ]),
        this.loadingContents?h('div',{class:'cms-revision-state',role:'status'},tr('revisions_page.loading_contents')):
        !this.contents.length?h('div',{class:'cms-revision-state'},[
          h('strong',{},this.contentSearch?tr('revisions_page.no_content_match'):tr('revisions_page.no_content_exists')),
          h('p',{},this.contentSearch?tr('revisions_page.change_search'):tr('revisions_page.create_first_content_help')),
          !this.contentSearch?routeButton(h,LButton,tr('revisions_page.create_content'),'content'):null
        ]):h('div',{style:ui.page},[
          h('div',{class:'cms-revision-content-list'},this.contents.map(item=>h('button',{type:'button',class:'cms-revision-content','data-active':String(validId(item.id)===this.contentId),onClick:()=>this.choose(item)},[h('strong',{},item.title||tr('revisions_page.untitled')),h('small',{},`${item.type} · #${item.id}`)]))),
          h('div',{class:'cms-revision-paging'},[
            h(LButton,{label:tr('revisions_page.previous'),severity:'secondary',disabled:this.contentPage.offset<=0||this.loadingContents,onClick:this.previousContents}),
            h('span',{},this.contentPage.total?tr('revisions_page.content_range','',{from:this.contentPage.offset+1,to:Math.min(this.contentPage.total,this.contentPage.offset+this.contents.length),total:this.contentPage.total}):tr('revisions_page.content_range_empty')),
            h(LButton,{label:tr('revisions_page.next'),severity:'secondary',disabled:!this.contentPage.has_more||this.loadingContents,onClick:this.nextContents})
          ])
        ])
      ])})
      const history=this.selectedContent?h(LPanel,{title:tr('revisions_page.history_for','',{title:this.selectedContent.title||'#'+this.selectedContent.id})},{default:()=>this.loading?h('div',{class:'cms-revision-state'},tr('revisions_page.loading')):!this.items.length?h('div',{class:'cms-revision-state'},[h('strong',{},tr('revisions_page.empty')),h('p',{},tr('revisions_page.empty_help'))]):h('div',{class:'cms-revision-grid'},this.items.map(rev=>h('article',{class:'cms-revision-card','data-active':String(this.preview?.id===rev.id)},[
        h('div',{class:'cms-revision-head'},[h(LBadge,{label:kindLabel(rev.kind),severity:kindSeverity(rev.kind)}),h('strong',{},`#${rev.id}`)]),h('small',{},formatted(rev.created_at)),rev.actor_id?h('small',{},tr('revisions_page.actor','',{id:rev.actor_id})):null,h(LButton,{label:tr('revisions_page.preview_compare'),severity:'secondary',disabled:this.previewLoading,onClick:()=>this.previewRevision(rev)})
      ])))}):null
      const preview=this.preview&&this.selectedContent?h(LPanel,{title:tr('revisions_page.preview_title','',{id:this.preview.id})},{default:()=>h('div',{style:ui.page},[
        h('div',{class:'cms-revision-summary'},[
          h('div',{},[h('small',{},tr('revisions_page.revision_kind')),h('strong',{},kindLabel(this.preview.kind))]),
          h('div',{},[h('small',{},tr('revisions_page.created')),h('strong',{},formatted(this.preview.created_at))]),
          h('div',{},[h('small',{},tr('revisions_page.changed_fields')),h('strong',{},String(diff.length))])
        ]),
        this.previewLoading?h('div',{class:'cms-revision-state'},tr('revisions_page.loading_preview')):
        !diff.length?h('div',{class:'cms-revision-state'},tr('revisions_page.no_difference')):
        h('div',{class:'cms-revision-diff',role:'table','aria-label':tr('revisions_page.diff_table')},[
          h('div',{class:'cms-revision-diff-row cms-revision-diff-head'},[h('strong',{},tr('revisions_page.field')),h('strong',{},tr('revisions_page.current_value')),h('strong',{},tr('revisions_page.revision_value'))]),
          ...diff.map(row=>h('div',{class:'cms-revision-diff-row',key:row.path},[h('code',{},row.path),h('pre',{},displayValue(row.current)),h('pre',{},displayValue(row.revision))]))
        ]),
        h('div',{class:'cms-revision-actions'},[h(LButton,{label:this.restoring?tr('revisions_page.restoring'):tr('revisions_page.restore_this'),severity:'danger',disabled:this.restoring||this.preview.kind==='autosave',onClick:this.restorePreview}),this.preview.kind==='autosave'?h('span',{class:'cms-revision-muted'},tr('revisions_page.autosave_restore_note')):null])
      ])}):null
      return h(LPage,{title:tr('routes.revisions.title'),description:tr('routes.revisions.lead')},{default:()=>h('div',{style:ui.page},[
        h('style',{},CSS),message(h,this),h('div',{class:'cms-revision-actions'},[h(LButton,{label:tr('revisions_page.refresh'),severity:'secondary',disabled:this.loadingContents||this.loading,onClick:this.refreshAll}),routeButton(h,LButton,tr('revisions_page.manage_content'),'content')]),selector,history,preview
      ])})
    }
  }
}
