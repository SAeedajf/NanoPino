<template>
  <LPage icon="history">
    <template #actions>
      <div class="cms-revision-actions">
        <LButton variant="outline" :disabled="loadingContents || loading" @click="refreshAll">{{ t('revisions_page.refresh') }}</LButton>
        <LButton variant="outline" @click="router.push('/content')">{{ t('revisions_page.manage_content') }}</LButton>
      </div>
    </template>

    <LPanel v-if="error" class="cms-inline-callout cms-inline-callout--danger" role="alert"><p>{{ error }}</p></LPanel>
    <LPanel v-if="notice" class="cms-inline-callout" role="status"><p>{{ notice }}</p></LPanel>

    <LPanel>
      <template #header>{{ t('revisions_page.content_selector') }}</template>
      <div class="cms-revision-search">
        <label>{{ t('revisions_page.search_content') }}
          <input v-model="contentSearch" type="search" :placeholder="t('revisions_page.search_placeholder')" @keyup.enter="searchContents(true)">
        </label>
        <LButton :disabled="loadingContents" @click="searchContents(true)">{{ t('revisions_page.search') }}</LButton>
      </div>

      <div v-if="loadingContents" class="cms-revision-state" role="status">{{ t('revisions_page.loading_contents') }}</div>
      <div v-else-if="!contents.length" class="cms-revision-state">
        <strong>{{ contentSearch ? t('revisions_page.no_content_match') : t('revisions_page.no_content_exists') }}</strong>
        <p>{{ contentSearch ? t('revisions_page.change_search') : t('revisions_page.create_first_content_help') }}</p>
        <LButton v-if="!contentSearch" @click="router.push('/content')">{{ t('revisions_page.create_content') }}</LButton>
      </div>
      <template v-else>
        <div class="cms-revision-content-list">
          <button
            v-for="item in contents"
            :key="item.id"
            type="button"
            class="cms-revision-content"
            :data-active="String(item.id)===String(contentId)"
            @click="choose(item)"
          >
            <strong>{{ item.title || t('revisions_page.untitled') }}</strong>
            <small>{{ item.type }} · #{{ item.id }}</small>
          </button>
        </div>
        <div class="cms-revision-paging">
          <LButton variant="outline" :disabled="contentPage.offset<=0 || loadingContents" @click="previousContents">{{ t('revisions_page.previous') }}</LButton>
          <span>{{ contentPage.total ? t('revisions_page.content_range',{from:contentPage.offset+1,to:Math.min(contentPage.total,contentPage.offset+contents.length),total:contentPage.total}) : t('revisions_page.content_range_empty') }}</span>
          <LButton variant="outline" :disabled="!contentPage.has_more || loadingContents" @click="nextContents">{{ t('revisions_page.next') }}</LButton>
        </div>
      </template>
    </LPanel>

    <LPanel v-if="selectedContent">
      <template #header>{{ t('revisions_page.history_for',{title:selectedContent.title || ('#'+selectedContent.id)}) }}</template>
      <div v-if="loading" class="cms-revision-state" role="status">{{ t('revisions_page.loading') }}</div>
      <div v-else-if="!items.length" class="cms-revision-state">
        <strong>{{ t('revisions_page.empty') }}</strong>
        <p>{{ t('revisions_page.empty_help') }}</p>
      </div>
      <div v-else class="cms-revision-grid">
        <article v-for="revision in items" :key="revision.id" class="cms-revision-card" :data-active="preview?.id===revision.id">
          <div class="cms-revision-head">
            <LBadge :severity="kindSeverity(revision.kind)">{{ kindLabel(revision.kind) }}</LBadge>
            <strong>#{{ revision.id }}</strong>
          </div>
          <small>{{ formatDate(revision.created_at) }}</small>
          <small v-if="revision.actor_id">{{ t('revisions_page.actor',{id:revision.actor_id}) }}</small>
          <LButton variant="outline" :disabled="previewLoading" @click="previewRevision(revision)">{{ t('revisions_page.preview_compare') }}</LButton>
        </article>
      </div>
    </LPanel>

    <LPanel v-if="preview && selectedContent">
      <template #header>{{ t('revisions_page.preview_title',{id:preview.id}) }}</template>
      <div class="cms-revision-summary">
        <div><small>{{ t('revisions_page.revision_kind') }}</small><strong>{{ kindLabel(preview.kind) }}</strong></div>
        <div><small>{{ t('revisions_page.created') }}</small><strong>{{ formatDate(preview.created_at) }}</strong></div>
        <div><small>{{ t('revisions_page.changed_fields') }}</small><strong>{{ diffRows.length }}</strong></div>
      </div>

      <div v-if="previewLoading" class="cms-revision-state" role="status">{{ t('revisions_page.loading_preview') }}</div>
      <div v-else-if="!diffRows.length" class="cms-revision-state">{{ t('revisions_page.no_difference') }}</div>
      <div v-else class="cms-revision-diff" role="table" :aria-label="t('revisions_page.diff_table')">
        <div class="cms-revision-diff-row cms-revision-diff-row--head" role="row">
          <strong>{{ t('revisions_page.field') }}</strong>
          <strong>{{ t('revisions_page.current_value') }}</strong>
          <strong>{{ t('revisions_page.revision_value') }}</strong>
        </div>
        <div v-for="row in diffRows" :key="row.path" class="cms-revision-diff-row" role="row">
          <code>{{ row.path }}</code>
          <pre>{{ displayValue(row.current) }}</pre>
          <pre>{{ displayValue(row.revision) }}</pre>
        </div>
      </div>

      <div class="cms-revision-actions">
        <LButton
          severity="danger"
          :disabled="restoring || preview.kind==='autosave'"
          @click="restorePreview"
        >{{ restoring ? t('revisions_page.restoring') : t('revisions_page.restore_this') }}</LButton>
        <span v-if="preview.kind==='autosave'" class="cms-muted">{{ t('revisions_page.autosave_restore_note') }}</span>
      </div>
    </LPanel>
  </LPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { LBadge, LButton, LPage, LPanel } from '@pinooxhq/luma/ui'
import { contentApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const route=useRoute(),router=useRouter()
const contentId=ref(''),contents=ref([]),selectedContent=ref(null),items=ref([]),preview=ref(null)
const contentSearch=ref(''),loadingContents=ref(false),loading=ref(false),previewLoading=ref(false),restoring=ref(false),error=ref(''),notice=ref('')
const contentPage=reactive({limit:25,offset:0,total:0,has_more:false})

onMounted(async()=>{
  await searchContents(true)
  const requested=positiveId(route.query.content)
  if(requested){
    let item=contents.value.find(row=>String(row.id)===requested)||null
    if(!item){try{item=(await contentApi.read(requested)).data}catch(e){error.value=e.message}}
    if(item)await choose(item)
  }
})

function positiveId(value){const id=Number(value);return Number.isSafeInteger(id)&&id>0?String(id):''}
function kindLabel(kind){return ({initial:t('revisions_page.kind_initial'),manual:t('revisions_page.kind_manual'),autosave:t('revisions_page.kind_autosave'),published:t('revisions_page.kind_published'),scheduled:t('revisions_page.kind_scheduled'),pre_restore:t('revisions_page.kind_pre_restore'),restored:t('revisions_page.kind_restored')})[kind]||kind}
function kindSeverity(kind){return kind==='published'||kind==='restored'?'success':kind==='autosave'?'info':kind==='pre_restore'?'warning':'secondary'}
function formatDate(value){if(!value)return'—';try{return new Date(value).toLocaleString()}catch{return String(value)}}
function displayValue(value){if(value===undefined)return'—';if(value===null)return'null';const text=typeof value==='string'?value:JSON.stringify(value,null,2);return String(text).length>700?String(text).slice(0,700)+'…':String(text)}

function comparableCurrent(row){return{site_id:row.site_id,content_type:row.type,status:row.status,title:row.title||'',slug:row.slug||'',excerpt:row.excerpt||'',author_id:row.author_id??null,parent_id:row.parent_id??null,locale:row.locale||'fa',document:row.document||{},metadata:row.metadata||{},fields:row.fields||{},relations:row.relations||{},terms:row.terms||{},published_at:row.published_at??null,scheduled_at:row.scheduled_at??null}}
function collectDiff(path,current,revision,out){
  if(JSON.stringify(current)===JSON.stringify(revision))return
  const currentObject=current&&typeof current==='object'&&!Array.isArray(current)
  const revisionObject=revision&&typeof revision==='object'&&!Array.isArray(revision)
  if(currentObject&&revisionObject){
    for(const key of [...new Set([...Object.keys(current),...Object.keys(revision)])].sort())collectDiff(path?path+'.'+key:key,current[key],revision[key],out)
    return
  }
  out.push({path:path||'$',current,revision})
}
const diffRows=computed(()=>{if(!preview.value?.snapshot||!selectedContent.value)return[];const rows=[];collectDiff('',comparableCurrent(selectedContent.value),preview.value.snapshot,rows);return rows.slice(0,200)})

async function searchContents(reset=false){
  if(reset)contentPage.offset=0
  loadingContents.value=true;error.value=''
  try{
    const r=await contentApi.list({search:contentSearch.value,limit:contentPage.limit,offset:contentPage.offset,projection:'list'})
    const data=r.data||r
    contents.value=data.items||[]
    Object.assign(contentPage,data.pagination||{})
  }catch(e){error.value=e.message}
  finally{loadingContents.value=false}
}
async function choose(item){
  const id=positiveId(item?.id);if(!id)return
  contentId.value=id;preview.value=null;notice.value='';error.value=''
  try{selectedContent.value=(await contentApi.read(id)).data;await loadRevisions();router.replace({query:{...route.query,content:id}})}
  catch(e){error.value=e.message}
}
async function loadRevisions(){
  if(!contentId.value)return
  loading.value=true;error.value=''
  try{items.value=((await contentApi.revisions(contentId.value)).data||{}).items||[]}
  catch(e){error.value=e.message}
  finally{loading.value=false}
}
async function previewRevision(revision){
  if(!contentId.value||!revision?.id)return
  previewLoading.value=true;error.value=''
  try{preview.value=(await contentApi.revision(contentId.value,revision.id)).data}
  catch(e){error.value=e.message}
  finally{previewLoading.value=false}
}
async function restorePreview(){
  if(!preview.value||preview.value.kind==='autosave'||restoring.value)return
  if(!confirm(t('revisions_page.restore_confirm',{id:preview.value.id})))return
  restoring.value=true;error.value='';notice.value=''
  try{
    await contentApi.restoreRevision(contentId.value,preview.value.id)
    selectedContent.value=(await contentApi.read(contentId.value)).data
    notice.value=t('revisions_page.restored',{id:preview.value.id})
    preview.value=null
    await loadRevisions()
  }catch(e){error.value=e.message}
  finally{restoring.value=false}
}
function nextContents(){if(!contentPage.has_more||loadingContents.value)return;contentPage.offset+=contentPage.limit;searchContents()}
function previousContents(){if(contentPage.offset<=0||loadingContents.value)return;contentPage.offset=Math.max(0,contentPage.offset-contentPage.limit);searchContents()}
async function refreshAll(){await searchContents();if(contentId.value){try{selectedContent.value=(await contentApi.read(contentId.value)).data;await loadRevisions()}catch(e){error.value=e.message}}}
</script>

<style scoped>
.cms-revision-actions,.cms-revision-paging,.cms-revision-head{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.cms-revision-search{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;align-items:end}.cms-revision-search label{display:grid;gap:6px}.cms-revision-search input{min-height:44px;border:1px solid var(--p-surface-300);border-radius:10px;padding:8px 10px;background:var(--p-surface-0);color:inherit}.cms-revision-state{padding:24px 14px;text-align:center;display:grid;gap:8px;border:1px dashed var(--p-surface-300);border-radius:12px}.cms-revision-content-list,.cms-revision-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px;margin-top:12px}.cms-revision-content,.cms-revision-card{border:1px solid var(--p-surface-200);border-radius:12px;padding:11px;background:var(--p-surface-0);color:inherit;text-align:start;display:grid;gap:7px}.cms-revision-content{cursor:pointer}.cms-revision-content[data-active="true"],.cms-revision-card[data-active="true"]{border-color:var(--p-primary-color);box-shadow:0 0 0 1px var(--p-primary-color)}.cms-revision-paging{justify-content:space-between;margin-top:12px}.cms-revision-summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.cms-revision-summary>div{display:grid;gap:4px;border:1px solid var(--p-surface-200);border-radius:10px;padding:10px}.cms-revision-diff{display:grid;gap:4px;margin:12px 0;max-height:560px;overflow:auto}.cms-revision-diff-row{display:grid;grid-template-columns:minmax(140px,.7fr) minmax(0,1fr) minmax(0,1fr);gap:8px;padding:8px;border-bottom:1px solid var(--p-surface-200)}.cms-revision-diff-row pre{white-space:pre-wrap;overflow-wrap:anywhere;margin:0;font:inherit}.cms-revision-diff-row code{overflow-wrap:anywhere}.cms-muted{opacity:.7}@media(max-width:720px){.cms-revision-search{grid-template-columns:1fr}.cms-revision-summary{grid-template-columns:1fr}.cms-revision-diff-row{grid-template-columns:1fr}.cms-revision-diff-row--head{display:none}.cms-revision-paging>*{flex:1}}
</style>
