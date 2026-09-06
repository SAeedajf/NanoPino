import { api, ui, message, confirmFa, tr} from './common.mjs'

const statusFa={active:tr('users_page.active'),inactive:tr('users_page.inactive'),suspend:tr('users_page.suspend'),pending:tr('users_page.pending')}
const esc=(v)=>String(v??'')
const idOf=(u)=>Number(u?.user_id||u?.id||0)
const can=(current,ability)=>{
  const granted=Array.isArray(current?.abilities)?current.abilities:[]
  return granted.some((item)=>item==='*'||item===ability||(item.endsWith('.*')&&ability.startsWith(item.slice(0,-1))))
}

export function createComponent(host){
  const {h,LPage,LPanel,LButton,LBadge,LStatCard}=host
  return {
    name:'CmsUsersCapabilitiesCenter',
    data(){return{
      items:[],roles:[],roleTemplates:[],capabilities:[],summary:{total:0,active:0,inactive:0,suspend:0,pending:0},current:null,
      pagination:{limit:50,offset:0,returned:0,total:0,has_more:false},
      filters:{q:'',status:'',role:''},loading:false,saving:false,error:'',notice:'',editing:null,selected:null,searchTimer:null,editorOpen:false,
      form:{username:'',password:'',fname:'',lname:'',email:'',mobile:''},view:'users'
    }},
    mounted(){this.load()},
    methods:{
      isSelf(user){return idOf(user)===idOf(this.current)},
      allowed(ability){return can(this.current,ability)},
      queryString(){const p=new URLSearchParams({limit:String(this.pagination.limit),offset:String(this.pagination.offset)});if(this.filters.q.trim())p.set('q',this.filters.q.trim());if(this.filters.status)p.set('status',this.filters.status);if(this.filters.role)p.set('role',this.filters.role);return p.toString()},
      async load(reset=false){if(reset)this.pagination.offset=0;this.loading=true;this.error='';try{const data=await api(`/users?${this.queryString()}`);this.items=data.items||[];this.roles=data.roles||[];this.roleTemplates=data.role_templates||[];this.capabilities=data.capabilities||[];this.summary=data.summary||this.summary;this.current=data.current||null;this.pagination={...this.pagination,...(data.pagination||{})};if(this.selected){this.selected=this.items.find(u=>idOf(u)===idOf(this.selected))||null}}catch(e){this.error=e.message}finally{this.loading=false}},
      search(){clearTimeout(this.searchTimer);this.searchTimer=setTimeout(()=>this.load(true),260)},
      resetForm(){this.editing=null;this.editorOpen=false;this.form={username:'',password:'',fname:'',lname:'',email:'',mobile:''}},
      startCreate(){this.resetForm();this.editorOpen=true;this.selected=null;globalThis.window?.scrollTo?.({top:0,behavior:'smooth'})},
      edit(user){this.editing=idOf(user);this.editorOpen=true;this.selected=user;this.form={username:user.username||'',password:'',fname:user.fname||'',lname:user.lname||'',email:user.email||'',mobile:user.mobile||''};globalThis.window?.scrollTo?.({top:0,behavior:'smooth'})},
      async save(){this.error='';this.notice='';if(!this.form.username.trim()){this.error=tr('users_page.username_required');return}if(!this.editing&&String(this.form.password).length<8){this.error=tr('users_page.password_min');return}this.saving=true;try{const body={...this.form};if(this.editing){delete body.password;await api(`/users/${this.editing}`,{method:'PATCH',body})}else{await api('/users',{method:'POST',body})}this.notice=this.editing?tr('users_page.user_updated'):tr('users_page.user_created');this.resetForm();await this.load()}catch(e){this.error=e.message}finally{this.saving=false}},
      async setStatus(user,status){if(this.isSelf(user)&&status!=='active'){this.error=tr('users_page.self_status_block');return}try{await api(`/users/${idOf(user)}/status`,{method:'PATCH',body:{status}});this.notice=tr('users_page.status_changed');await this.load()}catch(e){this.error=e.message}},
      async toggleRole(user,role,attach){if(!attach&&this.isSelf(user)){this.error=tr('users_page.self_role_block');return}try{await api(`/users/${idOf(user)}/roles/${encodeURIComponent(role.key)}`,{method:attach?'POST':'DELETE',body:attach?{}:undefined});this.notice=attach?tr('users_page.role_added'):tr('users_page.role_removed');await this.load()}catch(e){this.error=e.message}},
      async revoke(user){if(!confirmFa(this.isSelf(user)?tr('users_page.revoke_self_confirm'):tr('users_page.revoke_confirm')))return;try{const r=await api(`/users/${idOf(user)}/sessions/revoke`,{method:'POST',body:{}});this.notice=tr('users_page.revoked','',{count:Number(r.revoked||0)})}catch(e){this.error=e.message}},
      async remove(user){if(this.isSelf(user)){this.error=tr('users_page.self_delete_block');return}if(!confirmFa(tr('users_page.delete_named_confirm','',{name:user.name||user.username||idOf(user)})))return;try{await api(`/users/${idOf(user)}`,{method:'DELETE'});this.notice=tr('users_page.user_deleted');this.selected=null;await this.load()}catch(e){this.error=e.message}},
      next(){if(!this.pagination.has_more)return;this.pagination.offset+=this.pagination.limit;this.load()},
      prev(){this.pagination.offset=Math.max(0,this.pagination.offset-this.pagination.limit);this.load()},
      roleHas(user,key){return (user?.roles||[]).includes(key)},
      capabilityGroup(key){return String(key).split('.')[0]||'other'},
    },
    render(){
      const stat=(label,value)=>h(LStatCard,{label,value:String(value??0)})
      const select=(value,onChange,options,labelText)=>h('label',{class:'uc-field'},[h('span',{},labelText),h('select',{value,onChange:e=>onChange(e.target.value)},[h('option',{value:''},tr('users_page.all')),...options.map(o=>h('option',{value:o.value},o.label))])])
      const roleOptions=this.roles.map(r=>({value:r.key,label:r.name||r.key}))
      const statusOptions=Object.entries(statusFa).map(([value,label])=>({value,label}))
      const userList=h('div',{class:'uc-list'},this.items.map(user=>{
        const self=this.isSelf(user),roles=user.roles||[]
        return h('article',{class:['uc-user',this.selected&&idOf(this.selected)===idOf(user)?'is-selected':''],key:idOf(user)},[
          h('button',{type:'button',class:'uc-user__main uc-user-select','aria-pressed':Boolean(this.selected&&idOf(this.selected)===idOf(user)),onClick:()=>{this.selected=user}},[
            h('span',{class:'uc-avatar','aria-hidden':'true'},esc((user.name||user.username||'?')).slice(0,1).toUpperCase()),
            h('span',{class:'uc-identity-text'},[h('strong',{},user.name||[user.fname,user.lname].filter(Boolean).join(' ')||user.username||`#${idOf(user)}`),h('small',{},user.email||user.mobile||user.username||`#${idOf(user)}`)])
          ]),
          h('div',{class:'uc-badges'},[h(LBadge,{label:statusFa[user.status]||user.status||tr('users_page.unknown')}),self?h(LBadge,{label:tr('users_page.current_account')}):null,...roles.slice(0,3).map(r=>h(LBadge,{label:r,key:r})),roles.length>3?h('small',{},`+${roles.length-3}`):null]),
          h('div',{class:'uc-actions',onClick:e=>e.stopPropagation()},[
            this.allowed('users.update')?h(LButton,{label:tr('users_page.edit'),size:'sm',onClick:()=>this.edit(user)}):null,
            this.allowed('users.sessions.revoke')?h(LButton,{label:tr('users_page.revoke_session'),size:'sm',severity:'secondary',onClick:()=>this.revoke(user)}):null,
            this.allowed('users.delete')&&!self?h(LButton,{label:tr('users_page.delete'),size:'sm',severity:'danger',onClick:()=>this.remove(user)}):null,
          ])
        ])
      }))
      const selected=this.selected
      const detail=selected?h(LPanel,{title:tr('users_page.access_security')}, {default:()=>h('div',{class:'uc-stack'},[
        h('div',{class:'uc-detail-head'},[h('div',{},[h('strong',{},selected.name||selected.username||`#${idOf(selected)}`),h('small',{},`ID ${idOf(selected)} · ${selected.email||tr('users_page.no_email')}`)]),h(LBadge,{label:statusFa[selected.status]||selected.status||tr('users_page.unknown')})]),
        h('div',{class:'uc-status-row'},Object.entries(statusFa).map(([key,text])=>h(LButton,{label:text,size:'sm',severity:selected.status===key?'primary':'secondary',disabled:!this.allowed('users.update')||(this.isSelf(selected)&&key!=='active'),onClick:()=>this.setStatus(selected,key)}))),
        h('section',{},[h('h4',{},tr('users_page.roles')),h('div',{class:'uc-role-grid'},this.roles.map(role=>{const has=this.roleHas(selected,role.key);return h('div',{class:'uc-role'},[h('div',{},[h('strong',{},role.name||role.key),h('small',{},`${role.key} · ${(role.permissions||[]).length} capability`)]),this.allowed('users.roles.manage')?h(LButton,{label:has?tr('users_page.remove_role'):tr('users_page.add_role'),size:'sm',severity:has?'danger':'secondary',disabled:has&&this.isSelf(selected),onClick:()=>this.toggleRole(selected,role,!has)}):null])}))]),
        h('section',{},[h('h4',{},tr('users_page.effective_capabilities')),h('div',{class:'uc-cap-cloud'},[...(selected.abilities||[])].sort().map(c=>h(LBadge,{label:c,key:c}))),h('small',{},tr('users_page.rbac_full_note'))]),
      ])}):h(LPanel,{title:tr('users_page.detail')},{default:()=>h('p',{class:'uc-muted'},tr('users_page.select_user'))})

      const roleCenter=h('div',{class:'uc-role-center'},[
        h(LPanel,{title:tr('users_page.installed_roles_short')}, {default:()=>h('div',{class:'uc-role-grid'},this.roles.map(r=>h('article',{class:'uc-role-card'},[h('strong',{},r.name||r.key),h('code',{},r.key),h('p',{},r.description||tr('users_page.no_description')),h(LBadge,{label:`${(r.permissions||[]).length} capability`}),h('details',{},[h('summary',{},tr('users_page.view_permissions')),h('div',{class:'uc-cap-cloud'},(r.permissions||[]).map(c=>h(LBadge,{label:c,key:c})))])])))}),
        h(LPanel,{title:tr('users_page.role_templates')}, {default:()=>h('div',{class:'uc-role-grid'},this.roleTemplates.map(r=>h('article',{class:'uc-role-card'},[h('strong',{},r.name||r.key),h('code',{},r.key),h('p',{},r.description||''),h(LBadge,{label:`${(r.capabilities||[]).length} capability`})])))}),
        h(LPanel,{title:tr('users_page.registry')}, {default:()=>h('div',{class:'uc-cap-registry'},this.capabilities.map(c=>h('div',{class:'uc-cap-row'},[h('code',{},c.key),h('span',{},c.description||''),h('small',{},c.owner||'')])))}),
      ])

      return h(LPage,{title:tr('routes.users.title'),description:tr('routes.users.lead')}, {default:()=>h('div',{class:'uc-page'},[
        h('style',{},`.uc-page{display:grid;gap:16px}.uc-toolbar,.uc-tabs,.uc-actions,.uc-badges,.uc-status-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.uc-stats{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}.uc-filters{display:grid;grid-template-columns:minmax(220px,2fr) repeat(2,minmax(150px,1fr));gap:10px}.uc-field{display:grid;gap:5px}.uc-field>span,.uc-muted,.uc-page small{color:var(--p-text-muted-color,#64748b)}.uc-field input,.uc-field select,.uc-editor input{inline-size:100%;min-block-size:42px;border:1px solid var(--p-content-border-color,#d7dce3);border-radius:10px;padding:8px 10px;background:var(--p-content-background,#fff);color:inherit}.uc-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.75fr);gap:14px;align-items:start}.uc-list,.uc-stack,.uc-role-center{display:grid;gap:10px}.uc-user{display:grid;grid-template-columns:minmax(230px,1fr) minmax(180px,.8fr) auto;gap:12px;align-items:center;border:1px solid var(--p-content-border-color,#e2e8f0);border-radius:14px;padding:12px}.uc-user-select{min-inline-size:0;min-block-size:44px;inline-size:100%;border:0;border-radius:8px;padding:5px;background:transparent;color:inherit;font:inherit;text-align:start;cursor:pointer}.uc-identity-text{min-inline-size:0;overflow-wrap:anywhere}.uc-user-select:hover{background:var(--p-content-hover-background,#eef2ff)}.uc-user.is-selected{outline:2px solid var(--p-primary-color,#6366f1)}.uc-user__main,.uc-detail-head,.uc-role{display:flex;gap:10px;align-items:center;justify-content:space-between}.uc-user__main>div:last-child,.uc-detail-head>div{display:grid;gap:3px}.uc-avatar{inline-size:42px;block-size:42px;border-radius:50%;display:grid;place-items:center;background:var(--p-content-hover-background,#eef2ff);font-weight:700}.uc-role-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.uc-role,.uc-role-card,.uc-cap-row{border:1px solid var(--p-content-border-color,#e2e8f0);border-radius:12px;padding:10px}.uc-role-card{display:grid;gap:7px}.uc-cap-cloud{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.uc-cap-registry{display:grid;gap:6px;max-block-size:520px;overflow:auto}.uc-cap-row{display:grid;grid-template-columns:minmax(180px,.7fr) minmax(240px,1fr) auto;gap:8px;align-items:center}.uc-editor{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.uc-pager{display:flex;justify-content:space-between;gap:8px;align-items:center}@media(max-width:900px){.uc-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.uc-layout{grid-template-columns:1fr}.uc-user{grid-template-columns:1fr}.uc-cap-row{grid-template-columns:1fr}.uc-role-grid{grid-template-columns:1fr}}@media(max-width:620px){.uc-filters,.uc-editor{grid-template-columns:1fr}.uc-toolbar>*{flex:1}.uc-stats{grid-template-columns:1fr 1fr}.uc-user{padding:10px}.uc-actions>*{flex:1}}`),
        message(h,this),
        h('div',{class:'uc-toolbar'},[h(LButton,{label:tr('users_page.users'),severity:this.view==='users'?'primary':'secondary',onClick:()=>this.view='users'}),h(LButton,{label:tr('users_page.roles_caps'),severity:this.view==='roles'?'primary':'secondary',onClick:()=>this.view='roles'}),this.allowed('users.create')?h(LButton,{label:tr('users_page.add_user'),onClick:this.startCreate}):null,h(LButton,{label:tr('users_page.refresh'),severity:'secondary',disabled:this.loading,onClick:()=>this.load()}),]),
        h('div',{class:'uc-stats'},[stat(tr('users_page.total'),this.summary.total),stat(tr('users_page.active'),this.summary.active),stat(tr('users_page.inactive'),this.summary.inactive),stat(tr('users_page.suspend'),this.summary.suspend),stat(tr('users_page.pending'),this.summary.pending)]),
        this.view==='roles'?roleCenter:h('div',{class:'uc-stack'},[
          !this.editorOpen?null:h(LPanel,{title:this.editing?tr('users_page.edit_user'):tr('users_page.new_user')}, {default:()=>h('div',{class:'uc-stack'},[h('div',{class:'uc-editor'},[[tr('users_page.username'),'username','text'],[tr('users_page.first_name'),'fname','text'],[tr('users_page.last_name'),'lname','text'],[tr('users_page.email'),'email','email'],[tr('users_page.mobile'),'mobile','tel'],...(!this.editing?[[tr('users_page.password'),'password','password']]:[])].map(([text,key,type])=>h('label',{class:'uc-field'},[h('span',{},text),h('input',{type,value:this.form[key],onInput:e=>this.form[key]=e.target.value})]))),h('div',{class:'uc-actions'},[h(LButton,{label:this.saving?tr('users_page.saving'):this.editing?tr('users_page.save_changes'):tr('users_page.create_user'),disabled:this.saving,onClick:this.save}),h(LButton,{label:tr('users_page.cancel'),severity:'secondary',onClick:this.resetForm})])])}),
          h(LPanel,{title:tr('users_page.list')}, {default:()=>h('div',{class:'uc-stack'},[h('div',{class:'uc-filters'},[h('label',{class:'uc-field'},[h('span',{},tr('users_page.search')),h('input',{type:'search',value:this.filters.q,placeholder:tr('users_page.search_placeholder'),onInput:e=>{this.filters.q=e.target.value;this.search()}})]),select(this.filters.status,v=>{this.filters.status=v;this.load(true)},statusOptions,tr('users_page.status')),select(this.filters.role,v=>{this.filters.role=v;this.load(true)},roleOptions,tr('users_page.roles'))]),this.loading?h('p',{},tr('users_page.loading')):this.items.length?userList:h('p',{class:'uc-muted'},tr('users_page.not_found')),h('div',{class:'uc-pager'},[h(LButton,{label:tr('users_page.previous'),severity:'secondary',disabled:this.pagination.offset<=0||this.loading,onClick:this.prev}),h('small',{},tr('users_page.count','',{count:this.pagination.total||0,range:`${this.pagination.offset+1}-${Math.min(this.pagination.offset+this.pagination.returned,this.pagination.total||0)}`})),h(LButton,{label:tr('users_page.next'),severity:'secondary',disabled:!this.pagination.has_more||this.loading,onClick:this.next})])])}),
          detail,
        ])
      ])})
    }
  }
}
