import { readApiResponse } from '../../runtime/api-response.mjs'
import { t } from '../i18n/index.js'
function boot(){return typeof window!=='undefined'?(window.__PINOOX__||{}):{}}
export function cmsApiBase(){
  const b=boot()
  const configured=b.cmsAdmin?.apiBase||b.cmsAdmin?.data?.runtimeApi?.base||''
  if(typeof configured==='string'&&configured.startsWith('/')&&!configured.startsWith('//'))return configured.replace(/\/+$/,'')
  const mount=String(b.cmsAdmin?.mountPath||'/').replace(/^\/+|\/+$/g,'')
  return `${mount?`/${mount}`:''}/api/v1/cms`
}

function csrfToken(){
  return typeof window!=='undefined'?(window.__PINOOX__?.csrf||''):''
}
export async function cmsRequest(path,{method='GET',body=null,headers={},signal}={}){
  const options={method,credentials:'same-origin',headers:{Accept:'application/json',...headers},signal}
  const mutation=!['GET','HEAD','OPTIONS'].includes(method.toUpperCase())
  if(mutation){
    const token=csrfToken()
    if(token)options.headers['X-CSRF-TOKEN']=token
    options.headers['X-Correlation-ID']=crypto.randomUUID?.()||`${Date.now()}-${Math.random()}`
  }
  if(body instanceof FormData)options.body=body
  else if(body!==null&&body!==undefined){
    options.headers['Content-Type']='application/json'
    options.body=JSON.stringify(body)
  }
  const response=await fetch(`${cmsApiBase()}${path}`,options)
  const payload=await readApiResponse(response,(key,fallback)=>t(key,{},fallback),{method})
  return{status:response.status,data:payload?.data??payload,body:payload,headers:response.headers}
}
export const mediaApi={
  list:(params={})=>{const q=new URLSearchParams();Object.entries(params).forEach(([k,v])=>v!==''&&v!=null&&q.set(k,String(v)));return cmsRequest(`/media${q.size?`?${q}`:''}`)},
  upload:form=>cmsRequest('/media',{method:'POST',body:form}),
  read:id=>cmsRequest(`/media/${id}`),
  update:(id,payload)=>cmsRequest(`/media/${id}`,{method:'PATCH',body:payload}),
  remove:id=>cmsRequest(`/media/${id}`,{method:'DELETE'}),
}
export const settingsApi={
  read:(key,params=new URLSearchParams())=>cmsRequest(`/settings/${encodeURIComponent(key)}?${params}`),
  list:()=>cmsRequest('/settings'),
  update:(key,payload)=>cmsRequest(`/settings/${encodeURIComponent(key)}`,{method:'PUT',body:payload}),
  reset:(key,payload)=>cmsRequest(`/settings/${encodeURIComponent(key)}`,{method:'DELETE',body:payload}),
}
export const builderApi={
  list:(params={})=>{const q=new URLSearchParams();Object.entries(params).forEach(([k,v])=>v!==''&&v!=null&&q.set(k,String(v)));return cmsRequest(`/builder${q.size?`?${q}`:''}`)},
  open:payload=>cmsRequest('/builder/open',{method:'POST',body:payload}),
  create:payload=>cmsRequest('/builder',{method:'POST',body:payload}),
  read:id=>cmsRequest(`/builder/${id}`),
  save:(id,payload)=>cmsRequest(`/builder/${id}`,{method:'PUT',body:payload}),
  autosave:(id,payload)=>cmsRequest(`/builder/${id}/autosave`,{method:'POST',body:payload}),
  publish:(id,payload)=>cmsRequest(`/builder/${id}/publish`,{method:'POST',body:payload}),
  revisions:(id,limit=100)=>cmsRequest(`/builder/${id}/revisions?limit=${encodeURIComponent(limit)}`),
  restore:(id,revisionId,payload={})=>cmsRequest(`/builder/${id}/revisions/${revisionId}/restore`,{method:'POST',body:payload}),
  preview:payload=>cmsRequest('/builder/preview',{method:'POST',body:payload}),
}
export const globalBlockApi={
  list:(siteId=1)=>cmsRequest(`/builder/global-blocks?site_id=${encodeURIComponent(siteId)}`),
  read:id=>cmsRequest(`/builder/global-blocks/${encodeURIComponent(id)}`),
  create:payload=>cmsRequest('/builder/global-blocks',{method:'POST',body:payload}),
  update:(id,payload)=>cmsRequest(`/builder/global-blocks/${encodeURIComponent(id)}`,{method:'PUT',body:payload}),
}
export const extensionApi={
  list:()=>cmsRequest('/extensions'),
  inspect:form=>cmsRequest('/extensions/inspect',{method:'POST',body:form}),
  reviewTicket:(stageId,approved=false)=>cmsRequest('/extensions/review-ticket',{method:'POST',body:{stage_id:stageId,approved}}),
  install:(stageId,approved=false)=>cmsRequest('/extensions/install',{method:'POST',body:{stage_id:stageId,approved}}),
  activate:id=>cmsRequest(`/extensions/${encodeURIComponent(id)}/activate`,{method:'POST',body:{}}),
  deactivate:id=>cmsRequest(`/extensions/${encodeURIComponent(id)}/deactivate`,{method:'POST',body:{}}),
  update:(id,stageId,approved=false)=>cmsRequest(`/extensions/${encodeURIComponent(id)}/update`,{method:'POST',body:{stage_id:stageId,approved}}),
  rollback:(id,recoveryPointId)=>cmsRequest(`/extensions/${encodeURIComponent(id)}/rollback`,{method:'POST',body:{recovery_point_id:recoveryPointId}}),
  repair:id=>cmsRequest(`/extensions/${encodeURIComponent(id)}/repair`,{method:'POST',body:{}}),
  uninstall:id=>cmsRequest(`/extensions/${encodeURIComponent(id)}`,{method:'DELETE'}),
}

export const userApi={
  list:(params={})=>{const q=new URLSearchParams(Object.entries(params).filter(([,v])=>v!==undefined&&v!==null&&v!==''));return cmsRequest(`/users${q.size?`?${q}`:''}`)},
  create:payload=>cmsRequest('/users',{method:'POST',body:payload}),
  update:(id,payload)=>cmsRequest(`/users/${id}`,{method:'PATCH',body:payload}),
  setStatus:(id,status)=>cmsRequest(`/users/${id}/status`,{method:'PATCH',body:{status}}),
  assignRole:(id,role)=>cmsRequest(`/users/${id}/roles/${encodeURIComponent(role)}`,{method:'POST',body:{}}),
  detachRole:(id,role)=>cmsRequest(`/users/${id}/roles/${encodeURIComponent(role)}`,{method:'DELETE'}),
  revokeSessions:id=>cmsRequest(`/users/${id}/sessions/revoke`,{method:'POST',body:{}}),
  remove:id=>cmsRequest(`/users/${id}`,{method:'DELETE'}),
}

export const themeApi={
  list:()=>cmsRequest('/themes'),
  patterns:(packageName,themeName)=>cmsRequest(`/themes/${encodeURIComponent(packageName)}/${encodeURIComponent(themeName)}/patterns`),
  activate:(packageName,themeName,context=null)=>cmsRequest('/themes/activate',{method:'POST',body:{package:packageName,theme:themeName,context}}),
}
export const recoveryApi={
  restore:id=>cmsRequest(`/recovery/points/${encodeURIComponent(id)}/restore`,{method:'POST',body:{}}),
  disableSafeMode:()=>cmsRequest('/recovery/safe-mode/disable',{method:'POST',body:{}}),
}
export const updateApi={
  policy:id=>cmsRequest(`/updates/${encodeURIComponent(id)}/policy`),
  savePolicy:(id,payload)=>cmsRequest(`/updates/${encodeURIComponent(id)}/policy`,{method:'PUT',body:payload}),
  history:(id,limit=100)=>cmsRequest(`/updates/${encodeURIComponent(id)}/history?limit=${encodeURIComponent(limit)}`),
  recoveryPoints:id=>cmsRequest(`/updates/${encodeURIComponent(id)}/recovery-points`),
}
export const infrastructureApi={
  status:()=>cmsRequest('/system/infrastructure'),
  queue:()=>cmsRequest('/system/infrastructure/queue'),
  retryQueue:id=>cmsRequest(`/system/infrastructure/queue/${encodeURIComponent(id)}/retry`,{method:'POST',body:{}}),
  invalidateTag:tag=>cmsRequest('/system/infrastructure/cache/invalidate-tag',{method:'POST',body:{tag}}),
  invalidateLayer:layer=>cmsRequest('/system/infrastructure/cache/invalidate-layer',{method:'POST',body:{layer}}),
}
export const performanceApi={status:()=>cmsRequest('/system/performance')}
export const searchApi={
  search:(params={})=>{const q=new URLSearchParams();Object.entries(params).forEach(([k,v])=>{if(v===undefined||v===null||v==='')return;if(Array.isArray(v))q.set(k,v.join(','));else q.set(k,String(v))});return cmsRequest(`/search${q.size?`?${q}`:''}`)},
}
export const systemApi={
  health:()=>cmsRequest('/system/health'),
  healthHistory:(limit=20)=>cmsRequest(`/system/health/history?limit=${encodeURIComponent(limit)}`),
  logs:(params={})=>{const q=new URLSearchParams();Object.entries(params).forEach(([k,v])=>v!==''&&v!=null&&q.set(k,String(v)));return cmsRequest(`/system/logs${q.size?`?${q}`:''}`)},
  supportBundle:()=>cmsRequest('/system/support-bundle',{method:'POST',body:{}}),
}
export const contentApi={
  list:(params={})=>{const q=new URLSearchParams();Object.entries(params).forEach(([k,v])=>v!==''&&v!=null&&q.set(k,String(v)));return cmsRequest(`/content${q.size?`?${q}`:''}`)},
  create:payload=>cmsRequest('/content',{method:'POST',body:payload}),
  read:id=>cmsRequest(`/content/${id}`),
  update:(id,payload)=>cmsRequest(`/content/${id}`,{method:'PUT',body:payload}),
  publish:id=>cmsRequest(`/content/${id}/publish`,{method:'POST',body:{}}),
  schedule:(id,publishAt)=>cmsRequest(`/content/${id}/schedule`,{method:'POST',body:{publish_at:publishAt}}),
  revisions:id=>cmsRequest(`/content/${id}/revisions`),
  revision:(id,revisionId)=>cmsRequest(`/content/${id}/revisions/${revisionId}`),
  restoreRevision:(id,revisionId)=>cmsRequest(`/content/${id}/revisions/${revisionId}/restore`,{method:'POST',body:{}}),
  trash:id=>cmsRequest(`/content/${id}`,{method:'DELETE'}),
  restore:id=>cmsRequest(`/content/${id}/restore`,{method:'POST',body:{}}),
}
export const taxonomyApi={
  terms:(key,params={})=>{const q=new URLSearchParams();Object.entries(params).forEach(([k,v])=>v!==''&&v!=null&&q.set(k,String(v)));return cmsRequest(`/taxonomies/${encodeURIComponent(key)}/terms${q.size?`?${q}`:''}`)},
}
export const securityApi={status:()=>cmsRequest('/system/security')}
