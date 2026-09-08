import { readApiResponse } from './api-response.mjs'
export function boot(){return globalThis.window?.__PINOOX__||{}}
export function data(){return boot().cmsAdmin?.data||{}}

export function locale(){return String(boot().cmsAdmin?.i18n?.locale||boot().locale||'fa')}
export function direction(){return String(boot().cmsAdmin?.i18n?.direction||boot().direction||'rtl')}
export function messages(){return boot().cmsAdmin?.i18n?.messages||{}}
export function tr(key,fallback='',replace={}){
  const parts=String(key||'').split('.').filter(Boolean)
  let value=messages()
  for(const part of parts){if(value&&typeof value==='object'&&Object.prototype.hasOwnProperty.call(value,part))value=value[part];else{value=undefined;break}}
  let text=typeof value==='string'?value:String(fallback||key||'')
  for(const [name,replacement] of Object.entries(replace||{}))text=text.replaceAll(`:${name}`,String(replacement))
  return text
}
export function brandName(){return String(boot().cmsAdmin?.brand?.name||tr('brand.name','NanoPino'))}
export function apiBase(){
  const b=boot()
  const configured=b.cmsAdmin?.apiBase||b.cmsAdmin?.data?.runtimeApi?.base||''
  if(typeof configured==='string'&&configured.startsWith('/')&&!configured.startsWith('//'))return configured.replace(/\/+$/,'')
  const mount=String(b.cmsAdmin?.mountPath||'/').replace(/^\/+|\/+$/g,'')
  return `${mount?`/${mount}`:''}/api/v1/cms`
}
export async function api(path,{method='GET',body=null,form=null}={}){
  const headers={Accept:'application/json'}
  const mutation=!['GET','HEAD','OPTIONS'].includes(method.toUpperCase())
  if(mutation){
    const token=boot().csrf||''
    if(token)headers['X-CSRF-TOKEN']=token
    headers['X-Correlation-ID']=globalThis.crypto?.randomUUID?.()||`${Date.now()}-${Math.random()}`
  }
  const options={method,credentials:'same-origin',headers}
  if(form)options.body=form
  else if(body!==null&&body!==undefined){headers['Content-Type']='application/json';options.body=JSON.stringify(body)}
  const res=await fetch(`${apiBase()}${path}`,options)
  const payload=await readApiResponse(res,tr,{method})
  return payload?.data??payload??{}
}
export const ui={
  page:{display:'grid',gap:'16px'},
  row:{display:'flex',gap:'8px',alignItems:'center',flexWrap:'wrap'},
  grid:{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(240px,1fr))',gap:'12px'},
  field:{display:'grid',gap:'6px'},
  input:{width:'100%',minHeight:'44px',padding:'9px 11px',border:'1px solid var(--p-surface-300,#d1d5db)',borderRadius:'10px',background:'var(--p-surface-0,#fff)',color:'inherit',boxSizing:'border-box'},
  textarea:{width:'100%',minHeight:'110px',padding:'9px 11px',border:'1px solid var(--p-surface-300,#d1d5db)',borderRadius:'10px',background:'var(--p-surface-0,#fff)',color:'inherit',boxSizing:'border-box',fontFamily:'inherit'},
  card:{padding:'12px',border:'1px solid var(--p-surface-200,#e5e7eb)',borderRadius:'12px',display:'grid',gap:'8px'},
  error:{padding:'10px 12px',border:'1px solid #ef4444',borderRadius:'10px'},
  ok:{padding:'10px 12px',border:'1px solid #16a34a',borderRadius:'10px'},
  mono:{direction:'ltr',textAlign:'left',fontFamily:'monospace',fontSize:'12px',whiteSpace:'pre-wrap',overflowWrap:'anywhere'},
}
export function label(h,text,child){return h('label',{style:ui.field},[h('span',{},text),child])}
export function input(h,value,onInput,type='text',extra={}){return h('input',{...extra,type,value:value??'',style:{...ui.input,...(extra.style||{})},onInput:e=>onInput(e.target.value)})}
export function textarea(h,value,onInput,extra={}){return h('textarea',{...extra,value:value??'',style:{...ui.textarea,...(extra.style||{})},onInput:e=>onInput(e.target.value)})}
export function select(h,value,onChange,options,extra={}){return h('select',{...extra,value,style:{...ui.input,...(extra.style||{})},onChange:e=>onChange(e.target.value)},options.map(o=>h('option',{value:o.value},o.label)))}
export function message(h,state){if(state.error)return h('div',{style:ui.error,role:'alert'},state.error);if(state.notice)return h('div',{style:ui.ok,role:'status'},state.notice);return null}
export function safeJson(value,fallback={}){try{return typeof value==='string'?JSON.parse(value):value}catch{return fallback}}
export function routeButton(h,LButton,label,path){return h(LButton,{label,onClick:()=>navigate(path)})}
export function navigate(path){
  const clean=String(path||'/').replace(/^\/+|\/+$/g,'')
  const current=window.location.pathname.replace(/\/+$/,'')
  const known=['appearance/site-editor','developer/sdk','content','media','appearance','extensions','users','settings','builder','site-editor','recovery','system','security','performance','logs','updates','revisions','blocks','audit','infrastructure']
  let base=current
  for(const item of known.sort((a,b)=>b.length-a.length)){
    if(base.endsWith('/'+item)){base=base.slice(0,-item.length-1);break}
  }
  const target=clean?`${base}/${clean}`:base||'/'
  window.history.pushState({},'',target)
  window.dispatchEvent(new PopStateEvent('popstate'))
}
export function confirmFa(text){return globalThis.confirm?.(text)===true}
