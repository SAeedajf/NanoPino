import{api,data,ui,message,routeButton,select,tr}from'./common.mjs'

const defaultPolicy=()=>({channel:'stable',auto_update:'disabled',require_signature:false,allow_downgrade:false,snapshot_before_update:true,health_check_required:true})
const historySeverity=v=>v==='succeeded'?'success':v==='failed'?'danger':v==='recovery_required'?'warning':'secondary'

export function createComponent(host){
 const{h,LPage,LPanel,LButton,LBadge,LStatCard}=host
 return{
  name:'CmsUpdatesControlPlane',
  data(){return{center:data().updateCenter||{},extensions:[],extensionId:'',policy:defaultPolicy(),history:[],points:[],loading:false,saving:false,error:'',notice:''}},
  mounted(){this.initialize()},
  methods:{
   async initialize(){try{const list=await api('/extensions');this.extensions=list.items||[];if(!this.extensionId&&this.extensions.length)this.extensionId=this.extensions[0].id||this.extensions[0].package||'';if(this.extensionId)await this.load()}catch(e){this.error=e.message}},
   extensionName(id){return this.extensions.find(x=>x.id===id||x.package===id)?.name||id},
   channelLabel(v){return({stable:tr('updates_page.channel_stable'),beta:tr('updates_page.channel_beta'),development:tr('updates_page.channel_development')})[v]||v},
   autoLabel(v){return({disabled:tr('updates_page.auto_disabled'),security_only:tr('updates_page.auto_security'),patch_only:tr('updates_page.auto_patch'),enabled:tr('updates_page.auto_enabled')})[v]||v},
   historyLabel(v){return({started:tr('updates_page.status_started'),succeeded:tr('updates_page.status_succeeded'),failed:tr('updates_page.status_failed'),recovery_required:tr('updates_page.status_recovery')})[v]||v},
   pointStatus(v){return({ready:tr('updates_page.recovery_ready'),creating:tr('updates_page.recovery_creating'),failed:tr('updates_page.recovery_failed'),restored:tr('updates_page.recovery_restored')})[v]||v||tr('updates_page.unknown')},
   operationLabel(v){return({update:tr('updates_page.operation_update'),uninstall:tr('updates_page.operation_uninstall'),repair:tr('updates_page.operation_repair'),rollback:tr('updates_page.operation_rollback')})[v]||v||tr('updates_page.unknown')},
   formatTime(v){const n=Number(v||0);if(!n)return'—';try{return new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(n*1000))}catch{return new Date(n*1000).toLocaleString()}},
   async load(){if(!this.extensionId)return;this.loading=true;this.error='';this.notice='';try{const[p,hist,pts]=await Promise.all([api(`/updates/${encodeURIComponent(this.extensionId)}/policy`),api(`/updates/${encodeURIComponent(this.extensionId)}/history?limit=100`),api(`/updates/${encodeURIComponent(this.extensionId)}/recovery-points`)]);this.policy={...defaultPolicy(),...(p||{})};this.history=Array.isArray(hist)?hist:[];this.points=Array.isArray(pts)?pts:[]}catch(e){this.error=e.message}finally{this.loading=false}},
   async save(){if(!this.extensionId)return;this.saving=true;this.error='';this.notice='';try{this.policy=await api(`/updates/${encodeURIComponent(this.extensionId)}/policy`,{method:'PUT',body:{...this.policy}});this.notice=tr('updates_page.saved')}catch(e){this.error=e.message}finally{this.saving=false}},
   toggle(key,value){this.policy={...this.policy,[key]:Boolean(value)}},
   historyCard(item){return h('article',{style:ui.card,key:item.id},[h('div',{style:ui.row},[h('strong',{},this.extensionName(item.extension_id)),h(LBadge,{label:this.historyLabel(item.status),severity:historySeverity(item.status)})]),h('span',{},`${item.from_version||'—'} → ${item.to_version||'—'}`),h('small',{},this.formatTime(item.occurred_at)),item.recovery_point_id?h('small',{},tr('updates_page.recovery_point','',{id:item.recovery_point_id})):null,h('details',{},[h('summary',{},tr('updates_page.technical_details')),h('code',{},item.id)])])},
   pointCard(item){return h('article',{style:ui.card,key:item.id},[h('div',{style:ui.row},[h('strong',{},this.operationLabel(item.operation)),h(LBadge,{label:this.pointStatus(item.status),severity:item.status==='ready'?'success':item.status==='failed'?'danger':'warning'})]),h('small',{},this.formatTime(item.created_at)),h('details',{},[h('summary',{},tr('updates_page.technical_details')),h('code',{},item.id)])])},
  },
  render(){return h(LPage,{title:tr('routes.updates.title'),description:tr('routes.updates.lead')},{default:()=>h('div',{style:ui.page},[
   message(h,this),
   h('div',{style:ui.row},[
    select(h,this.extensionId,v=>{this.extensionId=v;this.load()},[{value:'',label:tr('updates_page.select_extension')},...this.extensions.map(x=>({value:x.id||x.package,label:x.name||x.package||x.id}))]),
    h(LButton,{label:tr('common.refresh'),severity:'secondary',disabled:this.loading||!this.extensionId,onClick:this.load}),
    h(LButton,{label:this.saving?tr('updates_page.saving'):tr('common.save'),disabled:this.saving||!this.extensionId,onClick:this.save})
   ]),
   h('div',{style:ui.grid},[
    h(LStatCard,{label:tr('updates_page.policy'),value:this.extensionId?tr('updates_page.loaded'):'—'}),
    h(LStatCard,{label:tr('updates_page.history'),value:String(this.history.length)}),
    h(LStatCard,{label:tr('updates_page.recovery'),value:String(this.points.length)})
   ]),
   h(LPanel,{title:tr('updates_page.policy')},{default:()=>this.extensionId?h('div',{style:ui.page},[
    h('div',{style:ui.grid},[
     h('label',{style:ui.field},[h('span',{},tr('updates_page.channel')),select(h,this.policy.channel,v=>this.policy={...this.policy,channel:v},(this.center.channels||['stable','beta','development']).map(v=>({value:v,label:this.channelLabel(v)})))]),
     h('label',{style:ui.field},[h('span',{},tr('updates_page.auto_update')),select(h,this.policy.auto_update,v=>this.policy={...this.policy,auto_update:v},(this.center.autoUpdateModes||['disabled','security_only','patch_only','enabled']).map(v=>({value:v,label:this.autoLabel(v)})))])
    ]),
    ...[['require_signature','require_signature'],['snapshot_before_update','snapshot_before'],['health_check_required','health_required'],['allow_downgrade','allow_downgrade']].map(([key,label])=>h('label',{style:ui.row},[h('input',{type:'checkbox',checked:Boolean(this.policy[key]),onChange:e=>this.toggle(key,e.target.checked)}),h('span',{},tr(`updates_page.${label}`))])),
    h('small',{},tr('updates_page.policy_note'))
   ]):h('p',{},tr('updates_page.select_extension_hint'))}),
   h(LPanel,{title:tr('updates_page.history')},{default:()=>this.history.length?h('div',{style:ui.grid},this.history.map(x=>this.historyCard(x))):h('p',{},tr('updates_page.empty_message'))}),
   h(LPanel,{title:tr('updates_page.recovery_points')},{default:()=>this.points.length?h('div',{style:ui.grid},this.points.map(x=>this.pointCard(x))):h('p',{},tr('recovery_page.empty_message'))}),
   h(LPanel,{title:tr('updates_page.operations')},{default:()=>h('div',{style:ui.page},[h('p',{},tr('updates_page.operation_note')),h('div',{style:ui.row},[routeButton(h,LButton,tr('updates_page.open_extensions'),'extensions'),routeButton(h,LButton,tr('updates_page.open_recovery'),'recovery')])])})
  ])})}
 }
}
