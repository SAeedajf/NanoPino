import{api,ui,message,confirmFa,tr}from'./common.mjs'

const statusSeverity=v=>v==='active'?'success':['failed','quarantined','incompatible'].includes(v)?'danger':v==='updating'?'warning':'secondary'
const decisionSeverity=v=>v==='allow'?'success':v==='approval_required'?'warning':'danger'
const riskSeverity=v=>['critical','high'].includes(v)?'danger':v==='medium'?'warning':'success'
const trustSeverity=v=>v==='verified'?'success':v==='invalid'?'danger':'warning'

export function createComponent(host){
 const{h,LPage,LPanel,LButton,LBadge}=host
 return{
  name:'CmsExtensionsControlPlane',
  data(){return{items:[],file:null,stage:null,review:null,mode:null,approved:false,error:'',notice:'',loading:false,installing:false,busy:{id:'',action:''},inspectionRequest:0}},
  computed:{
   manifest(){return this.review?.package?.manifest||{}},
   trust(){return this.review?.package?.trust||{}},
   reviewData(){return this.review?.review||{}},
   dependencyIssues(){return this.reviewData.dependencies?.issues||[]},
   permissions(){return this.reviewData.permissions?.items||[]},
  },
  mounted(){this.load()},
  methods:{
   async load(){this.loading=true;this.error='';try{this.items=(await api('/extensions')).items||[]}catch(e){this.error=e.message}finally{this.loading=false}},
   selectFile(file){this.file=file||null;this.stage=null;this.review=null;this.mode=null;this.approved=false;this.error='';this.inspectionRequest++},
   async inspect(){if(!this.file)return;const request=this.inspectionRequest,file=this.file;this.installing=true;this.error='';try{const form=new FormData();form.append('file',file);const d=await api('/extensions/inspect',{method:'POST',form});if(request!==this.inspectionRequest)return;this.stage=d.stage;this.review=d.review;this.mode=d.mode;this.approved=false}catch(e){if(request===this.inspectionRequest)this.error=e.message}finally{if(request===this.inspectionRequest)this.installing=false}},
   clearReview(){this.inspectionRequest++;this.file=null;this.stage=null;this.review=null;this.mode=null;this.approved=false},
   async execute(){
    if(!this.stage||!this.approved||this.reviewData.decision==='block')return
    this.installing=true;this.error='';this.notice=''
    try{
     if(this.mode==='update'){
      const id=this.manifest.identifier
      if(!id)throw new Error(tr('extensions_page.review_identity_missing'))
      await api(`/extensions/${encodeURIComponent(id)}/update`,{method:'POST',body:{stage_id:this.stage.id,approved:true}})
      this.notice=tr('extensions_page.installed_update')
     }else{
      await api('/extensions/install',{method:'POST',body:{stage_id:this.stage.id,approved:true}})
      this.notice=tr('extensions_page.installed_extension')
     }
     this.clearReview();await this.load()
    }catch(e){this.error=e.message}finally{this.installing=false}
   },
   isCore(x){return x?.type==='core-module'},
   statusLabel(v){return({active:tr('extensions_page.active'),inactive:tr('extensions_page.inactive'),installed:tr('extensions_page.installed'),failed:tr('extensions_page.failed'),quarantined:tr('extensions_page.quarantined'),incompatible:tr('extensions_page.incompatible'),updating:tr('extensions_page.updating')})[v]||v},
   typeLabel(v){return({'core-module':tr('extensions_page.type_core'),module:tr('extensions_page.type_module'),plugin:tr('extensions_page.type_plugin'),integration:tr('extensions_page.type_integration'),theme:tr('extensions_page.type_theme'),'admin-extension':tr('extensions_page.type_admin'),block:tr('extensions_page.type_block'),'block-package':tr('extensions_page.type_block_pack'),driver:tr('extensions_page.type_driver'),'language-pack':tr('extensions_page.type_language')})[v]||v},
   decisionLabel(v){return({allow:tr('extensions_page.decision_allow'),approval_required:tr('extensions_page.decision_approval'),block:tr('extensions_page.decision_block')})[v]||v},
   trustLabel(v){return({verified:tr('extensions_page.trust_verified'),unsigned:tr('extensions_page.trust_unsigned'),invalid:tr('extensions_page.trust_invalid'),unknown:tr('extensions_page.trust_unknown')})[v]||v||tr('extensions_page.trust_unknown')},
   riskLabel(v){return({low:tr('extensions_page.risk_low'),medium:tr('extensions_page.risk_medium'),high:tr('extensions_page.risk_high'),critical:tr('extensions_page.risk_critical')})[v]||v||tr('extensions_page.risk_low')},
   async action(x,a){
    const id=x.id||x.package;if(!id)return
    if(this.isCore(x)&&['deactivate','uninstall'].includes(a)){this.error=tr('extensions_page.core_action_blocked');return}
    if((a==='uninstall'||a==='repair')&&!confirmFa(tr('extensions_page.action_confirm','',{action:a==='uninstall'?tr('extensions_page.delete'):tr('extensions_page.repair'),id:x.name||id})))return
    this.busy={id,action:a};this.error='';this.notice=''
    try{await api(`/extensions/${encodeURIComponent(id)}${a==='uninstall'?'':`/${a}`}`,{method:a==='uninstall'?'DELETE':'POST',body:a==='uninstall'?null:{}});this.notice=tr('extensions_page.operation_done');await this.load()}catch(e){this.error=e.message}finally{this.busy={id:'',action:''}}
   },
   reviewPanel(){
    if(!this.review)return null
    const decision=this.reviewData.decision||'block',risk=this.reviewData.permissions?.highest_risk||'low'
    return h('div',{class:'cms-extension-review'},[
     h('div',{class:'cms-definition-head'},[
      h('div',{},[h('strong',{},this.manifest.name||this.manifest.identifier||tr('extensions_page.package')),h('small',{style:{display:'block',opacity:.7}},`${this.manifest.identifier||'—'} · ${this.manifest.version||'—'}`)]),
      h('div',{style:ui.row},[h(LBadge,{label:this.mode==='update'?tr('extensions_page.mode_update'):tr('extensions_page.mode_install'),severity:this.mode==='update'?'info':'success'}),h(LBadge,{label:this.decisionLabel(decision),severity:decisionSeverity(decision)})])
     ]),
     h('div',{class:'cms-extension-review-grid'},[
      h('article',{},[h('strong',{},tr('extensions_page.trust')),h(LBadge,{label:this.trustLabel(this.trust.level),severity:trustSeverity(this.trust.level)}),h('small',{},this.trust.assurance||tr('extensions_page.no_assurance'))]),
      h('article',{},[h('strong',{},tr('extensions_page.dependencies')),h(LBadge,{label:this.dependencyIssues.length?tr('extensions_page.issue_count','',{count:this.dependencyIssues.length}):tr('extensions_page.ready'),severity:this.dependencyIssues.length?'danger':'success'})]),
      h('article',{},[h('strong',{},tr('extensions_page.permissions')),h(LBadge,{label:this.riskLabel(risk),severity:riskSeverity(risk)}),h('small',{},tr('extensions_page.permission_count','',{count:this.permissions.length}))]),
      h('article',{},[h('strong',{},tr('extensions_page.package_security')),h(LBadge,{label:this.review?.package?.security?.ok===false?tr('extensions_page.blocked'):tr('extensions_page.checked'),severity:this.review?.package?.security?.ok===false?'danger':'success'})]),
     ]),
     ...(this.reviewData.warnings||[]).map(w=>h('div',{class:'cms-alert cms-alert--warning'},w)),
     ...this.dependencyIssues.map(issue=>h('div',{class:`cms-alert ${issue.blocking?'cms-alert--danger':'cms-alert--warning'}`},[h('strong',{},issue.target||issue.code),h('span',{},issue.message)])),
     this.permissions.length?h('details',{},[h('summary',{},tr('extensions_page.permission_details')),h('div',{style:ui.page},this.permissions.map(p=>h('div',{style:ui.row},[h('code',{},p.permission),h(LBadge,{label:this.riskLabel(p.risk),severity:riskSeverity(p.risk)}),h('small',{},p.description||'')])))]):null,
     h('label',{style:ui.row},[h('input',{type:'checkbox',checked:this.approved,disabled:decision==='block',onChange:e=>this.approved=e.target.checked}),h('span',{},tr('extensions_page.review_approve'))]),
     h('div',{style:ui.row},[h(LButton,{label:this.installing?tr('extensions_page.running'):this.mode==='update'?tr('extensions_page.run_update'):tr('extensions_page.install_package'),disabled:this.installing||!this.approved||decision==='block',onClick:this.execute}),h(LButton,{label:tr('extensions_page.cancel'),severity:'secondary',onClick:this.clearReview})])
    ])
   },
   row(x){
    const id=x.id||x.package,status=x.status||'installed',rowBusy=this.busy.id===id
    return h(LPanel,{title:x.name||x.package||id,key:id},{default:()=>h('div',{style:ui.page},[
     h('div',{style:ui.row},[h(LBadge,{label:this.statusLabel(status),severity:statusSeverity(status)}),h(LBadge,{label:this.typeLabel(x.type),severity:'secondary'}),this.isCore(x)?h(LBadge,{label:tr('extensions_page.core_protected'),severity:'info'}):null]),
     h('small',{},x.publisher||''),h('small',{},tr('extensions_page.version')+' '+(x.version||'—')),
     h('details',{},[h('summary',{},tr('extensions_page.technical_details')),h('code',{},id||'')]),
     h('div',{style:ui.row},[
      this.isCore(x)&&status==='active'?null:status==='active'?h(LButton,{label:rowBusy&&this.busy.action==='deactivate'?tr('extensions_page.running'):tr('extensions_page.deactivate'),disabled:rowBusy,onClick:()=>this.action(x,'deactivate')}):h(LButton,{label:rowBusy&&this.busy.action==='activate'?tr('extensions_page.running'):tr('extensions_page.activate'),disabled:rowBusy,onClick:()=>this.action(x,'activate')}),
      h(LButton,{label:rowBusy&&this.busy.action==='repair'?tr('extensions_page.running'):tr('extensions_page.repair'),severity:'secondary',disabled:rowBusy,onClick:()=>this.action(x,'repair')}),
      !this.isCore(x)?h(LButton,{label:rowBusy&&this.busy.action==='uninstall'?tr('extensions_page.running'):tr('extensions_page.delete'),severity:'danger',disabled:rowBusy,onClick:()=>this.action(x,'uninstall')}):null
     ])
    ])})
   }
  },
  render(){return h(LPage,{title:tr('routes.extensions.title'),description:tr('routes.extensions.lead')},{default:()=>h('div',{style:ui.page},[
   message(h,this),
   h(LPanel,{title:tr('extensions_page.install_update')},{default:()=>h('div',{style:ui.page},[
    h('input',{type:'file',accept:'.pinx,.zip','aria-label':tr('a11y.extension_package',tr('extensions_page.select_extension')),onChange:e=>this.selectFile(e.target.files?.[0]||null)}),
    h(LButton,{label:this.installing?tr('extensions_page.running'):tr('extensions_page.inspect'),disabled:!this.file||this.installing,onClick:this.inspect}),
    this.reviewPanel()
   ])}),
   h('div',{style:ui.grid},this.items.map(x=>this.row(x)))
  ])})}
 }
}
