<template>
  <LPage icon="images">
    <template #actions>
      <input ref="fileInput" class="cms-visually-hidden" type="file" multiple :accept="accept" :aria-label="t('a11y.media_upload')" @change="onFileChange">
      <LButton icon="upload" shape="rounded" :disabled="uploading" @click="fileInput?.click()">
        {{ uploading ? t('media_page.uploading') : t('media_page.upload_media') }}
      </LButton>
    </template>

    <div class="cms-stat-grid">
      <LStatCard :label="t('media_page.all_media')" :value="libraryLoaded ? (summary.total ?? 0) : '—'" icon="images" />
      <LStatCard :label="t('media_page.image')" :value="libraryLoaded ? (summary.image ?? 0) : '—'" icon="image" />
      <LStatCard :label="t('media_page.video')" :value="libraryLoaded ? (summary.video ?? 0) : '—'" icon="video" />
      <LStatCard :label="t('media_page.missing_alt')" :value="libraryLoaded ? (summary.missing_alt ?? 0) : '—'" icon="circle-alert" />
    </div>

    <LPanel>
      <template #header>{{ t('media_page.secure_upload') }}</template>
      <div
        class="cms-media-drop"
        :class="{ 'is-dragging': dragging }"
        @dragover.prevent="dragging=true"
        @dragleave="dragging=false"
        @drop.prevent="onDrop"
      >
        <strong>{{ t('media_page.drop_files') }}</strong>
        <small>{{ t('media_page.max_policy', { max: policy.max_mb || 20, extensions: (policy.allowed_extensions || []).join(', ') || t('media_page.server_policy') }) }}</small>
        <LButton variant="outline" shape="rounded" :disabled="uploading" @click="fileInput?.click()">{{ t('media_page.select_files') }}</LButton>
      </div>
      <div v-if="uploadQueue.length" class="cms-upload-queue">
        <article v-for="row in uploadQueue" :key="row.id" class="cms-upload-row">
          <div><strong>{{ row.name }}</strong><small>{{ formatBytes(row.size) }}</small></div>
          <LBadge :severity="row.status==='done'?'success':row.status==='error'||row.status==='rejected'?'danger':'secondary'">{{ row.status }}</LBadge>
          <small v-if="row.error" class="cms-text-danger">{{ row.error }}</small>
          <div class="cms-card-actions">
            <LButton v-if="row.status==='error'" size="sm" variant="outline" :disabled="uploading" @click="retryUpload(row)">{{ t('media_page.retry_file') }}</LButton>
            <LButton v-if="row.status==='queued'||row.status==='error'" size="sm" variant="outline" severity="neutral" :disabled="row.status==='uploading'" @click="cancelUpload(row)">{{ t('media_page.cancel_file') }}</LButton>
          </div>
        </article>
      </div>
    </LPanel>

    <LPanel v-if="notice || error" class="cms-inline-callout" :role="error ? 'alert' : 'status'" :aria-live="error ? 'assertive' : 'polite'">
      <template #header>{{ t('routes.media.title') }}</template>
      <p v-if="notice">{{ notice }}</p>
      <p v-if="error" class="cms-text-danger">{{ error }}</p>
    </LPanel>

    <LPanel>
      <template #header>{{ t('media_page.library') }}</template>
      <div class="cms-media-toolbar">
        <input v-model="query" class="cms-input" type="search" :placeholder="t('media_page.search_placeholder')" :aria-label="t('a11y.media_search')" @input="queueSearch">
        <select v-model="kind" class="cms-input" :aria-label="t('a11y.media_kind')" @change="applyFilters"><option value="">{{ t('media_page.all_kinds') }}</option><option v-for="item in kinds" :key="item" :value="item">{{ kindLabel(item) }}</option></select>
        <LButton variant="outline" severity="neutral" shape="rounded" @click="view=view==='grid'?'list':'grid'">{{ view==='grid' ? t('media_page.list_view') : t('media_page.grid_view') }}</LButton>
        <LButton variant="outline" severity="neutral" shape="rounded" @click="loadMedia">{{ t('media_page.refresh') }}</LButton>
      </div>
    </LPanel>

    <div class="cms-media-layout" :class="{ 'has-detail': selected }">
      <section>
        <div v-if="loading" class="cms-page-state" role="status" aria-live="polite">{{ t('media_page.loading_library') }}</div>
        <div v-else-if="error && !libraryLoaded" class="cms-page-state cms-page-state--error" role="alert"><strong>{{ t('media_page.load_failed') }}</strong><p>{{ t('media_page.load_failed_hint') }}</p><LButton variant="outline" @click="loadMedia">{{ t('media_page.refresh') }}</LButton></div>
        <div v-else-if="!assets.length" class="cms-page-state">{{ t('media_page.not_found') }}</div>
        <div v-else-if="view==='grid'" class="cms-media-grid">
          <article v-for="asset in assets" :key="asset.id" class="cms-media-card" :class="{ selected:selected?.id===asset.id }" role="button" tabindex="0" :aria-pressed="selected?.id===asset.id ? 'true' : 'false'" @click="openAsset(asset)" @keydown.enter.prevent="openAsset(asset)" @keydown.space.prevent="openAsset(asset)">
            <div class="cms-media-card__preview">
              <img v-if="asset.kind==='image' && (asset.thumb || asset.url)" :src="asset.thumb || asset.url" :alt="asset.alt || ''">
              <LIcon v-else :name="kindIcon(asset.kind)" :size="30" />
              <LBadge v-if="asset.kind==='image' && !asset.alt" severity="warning" class="cms-media-alt-badge">{{ t('media_page.missing_alt') }}</LBadge>
            </div>
            <div class="cms-media-card__body"><strong>{{ titleOf(asset) }}</strong><small>{{ asset.mime }} · {{ formatBytes(asset.size) }}</small></div>
          </article>
        </div>
        <div v-else class="cms-media-list">
          <article v-for="asset in assets" :key="asset.id" class="cms-media-list-row" :class="{ selected:selected?.id===asset.id }" role="button" tabindex="0" :aria-pressed="selected?.id===asset.id ? 'true' : 'false'" @click="openAsset(asset)" @keydown.enter.prevent="openAsset(asset)" @keydown.space.prevent="openAsset(asset)">
            <div class="cms-media-list-thumb"><img v-if="asset.kind==='image' && (asset.thumb||asset.url)" :src="asset.thumb||asset.url" :alt="asset.alt||''"><LIcon v-else :name="kindIcon(asset.kind)" /></div>
            <div><strong>{{ titleOf(asset) }}</strong><small>{{ asset.original_name }}</small><LBadge v-if="asset.kind==='image' && !asset.alt" severity="warning">{{ t('media_page.missing_alt') }}</LBadge></div>
            <span>{{ kindLabel(asset.kind) }}</span><span>{{ formatBytes(asset.size) }}</span>
          </article>
        </div>
        <div class="cms-media-pagination"><LButton variant="outline" :disabled="pagination.offset<=0" @click="prevPage">{{ t('media_page.previous') }}</LButton><span>{{ mediaRange }}</span><LButton variant="outline" :disabled="!pagination.has_more" @click="nextPage">{{ t('media_page.next') }}</LButton></div>
      </section>

      <aside v-if="selected" class="cms-media-detail">
        <LPanel>
          <template #header>{{ t('media_page.details') }}</template>
          <div class="cms-media-detail-preview"><img v-if="selected.kind==='image' && selected.url" :src="selected.url" :alt="draft.alt"><video v-else-if="selected.kind==='video' && selected.url" controls :src="selected.url"/><audio v-else-if="selected.kind==='audio' && selected.url" controls :src="selected.url"/><LIcon v-else :name="kindIcon(selected.kind)" :size="42" /></div>
          <div class="cms-form-grid"><label>{{ t('media_page.title') }}<input v-model="draft.title" class="cms-input" @input="draftDirty=true"></label><label v-if="selected.kind==='image'">{{ t('media_page.alt') }}<input v-model="draft.alt" class="cms-input" :placeholder="t('media_page.alt_placeholder')" @input="draftDirty=true"></label><label>{{ t('media_page.caption') }}<textarea v-model="draft.caption" class="cms-input" rows="3" @input="draftDirty=true"/></label><label>{{ t('media_page.description') }}<textarea v-model="draft.description" class="cms-input" rows="4" @input="draftDirty=true"/></label></div>
          <div class="cms-card-actions"><LButton :disabled="saving" @click="saveMetadata">{{ saving ? t('media_page.saving') : t('media_page.save') }}</LButton><LButton variant="outline" @click="copyUrl">{{ t('media_page.copy_url') }}</LButton><LButton variant="outline" @click="closeAsset">{{ t('media_page.close') }}</LButton><LButton variant="outline" severity="danger" @click="removeAsset">{{ t('media_page.delete') }}</LButton></div>
        </LPanel>
        <LPanel><template #header>{{ t('media_page.usages', { count: selected.usage_count || 0 }) }}</template><article v-for="usage in selected.usages || []" :key="usage.id" class="cms-usage-row"><strong>{{ usage.resource_type }} #{{ usage.resource_id }}</strong><small>{{ usage.context }}</small></article><p v-if="!(selected.usages||[]).length" class="cms-muted">{{ t('media_page.no_usages') }}</p></LPanel>
        <LPanel><template #header>{{ t('media_page.variants', { count: selected.variant_count || 0 }) }}</template><article v-for="variant in selected.variants || []" :key="variant.id" class="cms-usage-row"><strong>{{ variant.variant_key || variant.key }}</strong><small>{{ variant.width || '—' }}×{{ variant.height || '—' }} · {{ formatBytes(variant.size) }}</small></article><p v-if="!(selected.variants||[]).length" class="cms-muted">{{ t('media_page.no_variants') }}</p></LPanel>
      </aside>
    </div>
  </LPage>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { LBadge, LButton, LIcon, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { mediaApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const assets=ref([]), summary=ref({}), policy=ref({allowed_extensions:[],max_mb:20}), pagination=ref({limit:48,offset:0,returned:0,has_more:false}), kinds=ref(['image','video','audio','document'])
const query=ref(''), kind=ref(''), view=ref('grid'), loading=ref(false), libraryLoaded=ref(false), uploading=ref(false), dragging=ref(false), notice=ref(''), error=ref(''), fileInput=ref(null), uploadQueue=ref([]), selected=ref(null), saving=ref(false), draft=ref({title:'',alt:'',caption:'',description:''}), draftDirty=ref(false)
let timer=null, libraryRequest=0, selectionRequest=0
const accept=computed(()=>(policy.value.allowed_extensions||[]).map(v=>'.'+v).join(','))
const mediaRange=computed(()=>{const total=Number(pagination.value.total??summary.value.total??0);if(total<=0)return t('media_page.range_empty');const from=Math.min(total,Number(pagination.value.offset||0)+1),to=Math.min(total,Number(pagination.value.offset||0)+Number(pagination.value.returned||assets.value.length||0));return t('media_page.range',{from,to,total})})
onMounted(loadMedia)
async function loadMedia(){const request=++libraryRequest;loading.value=true;error.value='';try{const response=await mediaApi.list({q:query.value,kind:kind.value,limit:pagination.value.limit,offset:pagination.value.offset});if(request!==libraryRequest)return;const data=response.data||{};assets.value=data.items||[];summary.value=data.summary||{};policy.value=data.upload_policy||policy.value;pagination.value={...pagination.value,...(data.pagination||{})};kinds.value=data.kinds||kinds.value;libraryLoaded.value=true}catch(e){if(request===libraryRequest)error.value=e.message}finally{if(request===libraryRequest)loading.value=false}}
function queueSearch(){clearTimeout(timer);pagination.value.offset=0;timer=setTimeout(loadMedia,350)}
function confirmDiscardDraft(){return !draftDirty.value||confirm(t('media_page.discard_draft_confirm'))}
function clearSelection(){selected.value=null;draft.value={title:'',alt:'',caption:'',description:''};draftDirty.value=false;selectionRequest++}
function applyFilters(){if(!confirmDiscardDraft())return;pagination.value.offset=0;clearSelection();loadMedia()}
async function openAsset(asset){
  if(!asset?.id||String(asset.id)===String(selected.value?.id))return
  if(!confirmDiscardDraft())return
  const request=++selectionRequest;error.value=''
  try{const response=await mediaApi.read(asset.id);if(request!==selectionRequest)return;selected.value=response.data||response;draft.value={title:selected.value.title||'',alt:selected.value.alt||'',caption:selected.value.caption||'',description:selected.value.description||''};draftDirty.value=false}
  catch(e){if(request===selectionRequest)error.value=e.message}
}
function closeAsset(){if(!confirmDiscardDraft())return;clearSelection()}
async function saveMetadata(){
  if(!selected.value||saving.value)return
  const id=selected.value.id,submitted=JSON.stringify(draft.value),body=JSON.parse(submitted)
  saving.value=true;error.value='';notice.value=''
  try{
    const response=await mediaApi.update(id,body),updated=response.data||response
    if(String(selected.value?.id)===String(id)){
      selected.value={...selected.value,...updated}
      if(JSON.stringify(draft.value)===submitted){draft.value={title:updated.title||'',alt:updated.alt||'',caption:updated.caption||'',description:updated.description||''};draftDirty.value=false;notice.value=t('media_page.saved')}
      else{draftDirty.value=true;notice.value=t('media_page.saved_newer_draft')}
    }
    await loadMedia()
  }catch(e){error.value=e.message}finally{saving.value=false}
}
async function removeAsset(){if(!selected.value||!confirm(t('media_page.delete_confirm',{title:titleOf(selected.value)})))return;try{await mediaApi.remove(selected.value.id);clearSelection();notice.value=t('media_page.deleted');await loadMedia()}catch(e){if(e.code==='MEDIA_IN_USE'&&e.details?.usages){selected.value={...selected.value,in_use:true,usage_count:e.details.usages.length,usages:e.details.usages};error.value=t('media_page.in_use')}else error.value=e.message}}
async function copyUrl(){if(!selected.value?.url)return;try{await navigator.clipboard.writeText(selected.value.url);notice.value=t('media_page.url_copied')}catch{error.value=t('media_page.url_copy_failed')}}
function onDrop(event){dragging.value=false;addFiles([...(event.dataTransfer.files||[])])}function onFileChange(event){addFiles([...(event.target.files||[])]);event.target.value=''}
function addFiles(files){const allowed=new Set((policy.value.allowed_extensions||[]).map(v=>String(v).toLowerCase()));const max=Number(policy.value.max_bytes||0);for(const file of files){const ext=String(file.name.split('.').pop()||'').toLowerCase();let status='queued',rowError='';if(allowed.size&&!allowed.has(ext)){status='rejected';rowError=t('media_page.extension_rejected')}else if(max&&file.size>max){status='rejected';rowError=t('media_page.size_rejected',{max:policy.value.max_mb||Math.round(max/1048576)})}uploadQueue.value.push({id:`${Date.now()}-${Math.random()}`,file,name:file.name,size:file.size,status,error:rowError})}runUploads()}
async function runUploads(){if(uploading.value)return;uploading.value=true;let done=0;try{for(const row of uploadQueue.value){if(row.status!=='queued')continue;row.status='uploading';row.error='';try{const form=new FormData();form.append('file',row.file,row.file.name);form.append('site_id','1');form.append('public','1');const response=await mediaApi.upload(form);row.assetId=(response.data||response).id;row.status='done';done++}catch(e){row.status='error';row.error=e.message}}if(done){notice.value=t('media_page.uploaded_count',{count:done});pagination.value.offset=0;await loadMedia()}}finally{uploading.value=false}}
function retryUpload(row){if(!row||row.status!=='error'||uploading.value)return;row.status='queued';row.error='';runUploads()}
function cancelUpload(row){if(!row||row.status==='uploading')return;uploadQueue.value=uploadQueue.value.filter(item=>item.id!==row.id)}
function nextPage(){if(!pagination.value.has_more||!confirmDiscardDraft())return;pagination.value.offset+=pagination.value.limit;clearSelection();loadMedia()}function prevPage(){if(!confirmDiscardDraft())return;pagination.value.offset=Math.max(0,pagination.value.offset-pagination.value.limit);clearSelection();loadMedia()}
function titleOf(a){return a?.title||a?.original_name||`#${a?.id||'—'}`}function kindLabel(v){return({image:t('media_page.image'),video:t('media_page.video'),audio:t('media_page.audio'),document:t('media_page.document')})[v]||v}function kindIcon(v){return({image:'image',video:'video',audio:'audio-lines',document:'file-text'})[v]||'file'}function formatBytes(v){const n=Number(v||0);return n<1024?`${n} B`:n<1048576?`${(n/1024).toFixed(1)} KB`:n<1073741824?`${(n/1048576).toFixed(1)} MB`:`${(n/1073741824).toFixed(2)} GB`}
</script>

<style scoped>
.cms-media-drop{border:2px dashed var(--p-surface-300);border-radius:14px;padding:20px;display:grid;gap:8px;text-align:center}.cms-media-drop.is-dragging{border-color:var(--p-primary-color);background:var(--p-primary-50)}.cms-upload-queue,.cms-media-list,.cms-form-grid{display:grid;gap:8px}.cms-upload-row,.cms-usage-row{border:1px solid var(--p-surface-200);border-radius:10px;padding:9px;display:grid;gap:3px}.cms-upload-row>div{display:grid}.cms-upload-row small,.cms-usage-row small{opacity:.7}.cms-media-toolbar{display:flex;gap:8px;flex-wrap:wrap}.cms-input{min-height:44px;border:1px solid var(--p-surface-300);border-radius:10px;padding:8px 10px;background:var(--p-surface-0);color:inherit}.cms-media-toolbar .cms-input:first-child{flex:1;min-width:240px}.cms-media-layout{display:grid;grid-template-columns:1fr;gap:14px}.cms-media-layout.has-detail{grid-template-columns:minmax(0,1fr) minmax(300px,390px)}.cms-media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px}.cms-media-card{border:1px solid var(--p-surface-200);border-radius:14px;overflow:hidden;background:var(--p-surface-0);cursor:pointer}.cms-media-card.selected,.cms-media-list-row.selected{border-color:var(--p-primary-color)}.cms-media-card__preview{aspect-ratio:4/3;background:var(--p-surface-100);display:grid;place-items:center;position:relative;overflow:hidden}.cms-media-card__preview img{width:100%;height:100%;object-fit:cover}.cms-media-alt-badge{position:absolute;top:8px;left:8px}.cms-media-card__body{padding:10px;display:grid;gap:5px}.cms-media-card__body small{opacity:.7}.cms-media-list-row{display:grid;grid-template-columns:60px 1fr 120px 100px;gap:8px;align-items:center;border:1px solid var(--p-surface-200);border-radius:12px;padding:8px;cursor:pointer}.cms-media-list-thumb{width:56px;height:48px;display:grid;place-items:center;background:var(--p-surface-100);border-radius:8px;overflow:hidden}.cms-media-list-thumb img{width:100%;height:100%;object-fit:cover}.cms-media-detail{display:grid;gap:12px;position:sticky;top:12px}.cms-media-detail-preview{min-height:180px;background:var(--p-surface-100);border-radius:12px;display:grid;place-items:center;overflow:hidden}.cms-media-detail-preview img,.cms-media-detail-preview video{max-width:100%;max-height:320px}.cms-media-detail-preview audio{width:90%}.cms-media-pagination{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:12px}.cms-card-actions{display:flex;gap:8px;flex-wrap:wrap}.cms-text-danger{color:#b91c1c}.cms-page-state{text-align:center;padding:36px;border:1px dashed var(--p-surface-300);border-radius:14px}.cms-muted{opacity:.7}@media(max-width:980px){.cms-media-layout.has-detail{grid-template-columns:1fr}.cms-media-detail{position:static}}@media(max-width:620px){.cms-media-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.cms-media-list-row{grid-template-columns:56px 1fr}.cms-media-list-row>span{display:none}.cms-media-toolbar>*{width:100%}}@media(max-width:400px){.cms-media-grid{grid-template-columns:1fr}}
</style>
