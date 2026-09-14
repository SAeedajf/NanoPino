import { createRequestSignal, createRequestTimeoutError, readApiResponse } from './api-response.mjs'
export function boot(){return globalThis.window?.__PINOOX__||{}}
export function data(){return boot().cmsAdmin?.data||{}}
export function can(ability){
  const requested=String(ability||'').trim()
  if(!requested)return false
  const bootData=data()
  const authState=String(bootData.authState||'').trim().toLowerCase()
  if(authState==='anonymous'||(authState==='authenticated'&&(!bootData.currentUser||typeof bootData.currentUser!=='object')))return false
  if(!bootData.currentUser||typeof bootData.currentUser!=='object')return true
  const granted=Array.isArray(bootData.currentUser?.abilities)?bootData.currentUser.abilities:[]
  return granted.some(item=>{const value=String(item||'').trim();return value==='*'||value===requested||(value.endsWith('.*')&&requested.startsWith(value.slice(0,-1)))})
}

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
const FALLBACK_PLATFORM={id:'nanoshell',name:'NanoShell',contract:'nanoshell-platform-v1',version:1,standalone:true,product:'NanoPino',runtime:{execution:'native-only',document:'block-document-v1',api:'cms-api-v1',identity_source:'nanoshell-platform-contract'},source_adapters:['static-theme-import','static-asset-intake']}
let cachedPlatformInput,cachedPlatformValue
export function platform(){const value=boot().cmsAdmin?.platform;if(value===cachedPlatformInput&&cachedPlatformValue)return cachedPlatformValue;if(!value||typeof value!=='object'||value.id!==FALLBACK_PLATFORM.id||value.name!==FALLBACK_PLATFORM.name||value.contract!==FALLBACK_PLATFORM.contract||Number(value.version)!==FALLBACK_PLATFORM.version||value.runtime?.execution!==FALLBACK_PLATFORM.runtime.execution||value.runtime?.api!==FALLBACK_PLATFORM.runtime.api){cachedPlatformInput=value;cachedPlatformValue=FALLBACK_PLATFORM;return cachedPlatformValue}cachedPlatformInput=value;cachedPlatformValue=value;return cachedPlatformValue}
export function platformName(){return String(platform().name||FALLBACK_PLATFORM.name)}
export function publicSiteUrl(){const b=boot(),configured=b.cmsAdmin?.publicSiteUrl;if(typeof configured==='string'&&configured.startsWith('/')&&!configured.startsWith('//'))return configured;const mount=String(b.cmsAdmin?.mountPath||'/').replace(/^\/+|\/+$/g,'');return`${mount?`/${mount}`:''}/site`}
export function apiBase(){
  const b=boot()
  const configured=b.cmsAdmin?.apiBase||b.cmsAdmin?.data?.runtimeApi?.base||''
  if(typeof configured==='string'&&configured.startsWith('/')&&!configured.startsWith('//'))return configured.replace(/\/+$/,'')
  const mount=String(b.cmsAdmin?.mountPath||'/').replace(/^\/+|\/+$/g,'')
  return `${mount?`/${mount}`:''}/api/v1/cms`
}
function requestCorrelationId(){
  return globalThis.crypto?.randomUUID?.()||`${Date.now()}-${Math.random()}`
}
export async function api(path,{method='GET',body=null,form=null,signal,timeoutMs=15000}={}){
  const headers={Accept:'application/json','X-Correlation-ID':requestCorrelationId()}
  const mutation=!['GET','HEAD','OPTIONS'].includes(method.toUpperCase())
  if(mutation){
    const token=boot().csrf||''
    if(token)headers['X-CSRF-TOKEN']=token
  }
  const requestSignal=createRequestSignal(signal,timeoutMs)
  const options={method,credentials:'same-origin',headers,signal:requestSignal.signal}
  if(form)options.body=form
  else if(body!==null&&body!==undefined){headers['Content-Type']='application/json';options.body=JSON.stringify(body)}
  try {
    const res=await fetch(`${apiBase()}${path}`,options)
    const payload=await readApiResponse(res,tr,{method})
    return payload?.data??payload??{}
  } catch (error) {
    throw requestSignal.timedOut() ? createRequestTimeoutError(tr) : error
  } finally {
    requestSignal.dispose()
  }
}
export const ui={
  page:{display:'grid',gap:'16px'},
  row:{display:'flex',gap:'8px',alignItems:'center',flexWrap:'wrap'},
  grid:{display:'grid',gridTemplateColumns:'repeat(auto-fit,minmax(240px,1fr))',gap:'12px'},
  field:{display:'grid',gap:'6px'},
  input:{width:'100%',minHeight:'44px',padding:'var(--cms-space-2,9px) var(--cms-space-3,11px)',border:'1px solid var(--cms-border,var(--p-surface-300,#d1d5db))',borderRadius:'var(--cms-radius-md,10px)',background:'var(--cms-surface-panel,var(--p-surface-0,#fff))',color:'inherit',boxSizing:'border-box'},
  textarea:{width:'100%',minHeight:'110px',padding:'var(--cms-space-2,9px) var(--cms-space-3,11px)',border:'1px solid var(--cms-border,var(--p-surface-300,#d1d5db))',borderRadius:'var(--cms-radius-md,10px)',background:'var(--cms-surface-panel,var(--p-surface-0,#fff))',color:'inherit',boxSizing:'border-box',fontFamily:'inherit'},
  card:{padding:'var(--cms-space-3,12px)',border:'1px solid var(--cms-border,var(--p-surface-200,#e5e7eb))',borderRadius:'var(--cms-radius-md,12px)',display:'grid',gap:'var(--cms-space-2,8px)'},
  error:{padding:'var(--cms-space-2,10px) var(--cms-space-3,12px)',border:'1px solid var(--cms-danger,#ef4444)',borderRadius:'var(--cms-radius-md,10px)'},
  ok:{padding:'var(--cms-space-2,10px) var(--cms-space-3,12px)',border:'1px solid var(--cms-success,#16a34a)',borderRadius:'var(--cms-radius-md,10px)'},
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
  const key=String(path||'/').trim().replace(/^\/+|\/+$/g,'')
  const routes=Array.isArray(boot().cmsAdmin?.manifest?.routes)?boot().cmsAdmin.manifest.routes:[]
  const normalized=(value)=>String(value||'').trim().replace(/^\/+|\/+$/g,'')
  const route=routes.find((item)=>{
    const routePath=normalized(item?.path)
    const name=String(item?.name||'').replace(/^cms\./,'').replaceAll('_','-')
    const id=String(item?.id||'').replace(/^cms\./,'').replaceAll('_','-')
    return routePath===key
      || routePath.endsWith(`/${key}`)
      || name===key
      || id===key
  })
  const child=normalized(route?.path||key)
  const mount=String(boot().cmsAdmin?.mountPath||'').trim().replace(/^\/+|\/+$/g,'')
  const target=`${mount?`/${mount}`:''}${child?`/${child}`:'/' }`.replace(/\/{2,}/g,'/')
  window.history.pushState({},'',target)
  window.dispatchEvent(new PopStateEvent('popstate'))
}
export function confirmFa(text){return globalThis.confirm?.(text)===true}
