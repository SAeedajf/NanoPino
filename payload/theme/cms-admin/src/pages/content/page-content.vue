<template>
  <LPage icon="files">
    <template #actions>
      <div class="cms-card-actions">
        <LButton icon="refresh-cw" variant="outline" shape="rounded" :disabled="loading" @click="loadContents()">{{ t('content_page.refresh') }}</LButton>
        <LButton icon="plus" shape="rounded" @click="startCreate">{{ t('content_page.add') }}</LButton>
      </div>
    </template>

    <LPanel v-if="error" class="cms-inline-callout cms-inline-callout--danger" role="alert" aria-live="assertive"><strong>{{ t('content_page.operation_failed') }}</strong><p>{{ error }}</p></LPanel>
    <LPanel v-if="notice" class="cms-inline-callout" role="status" aria-live="polite"><strong>{{ notice }}</strong></LPanel>

    <div class="cms-stat-grid">
      <LStatCard :label="t('content_page.current_view')" :value="items.length" icon="files" />
      <LStatCard :label="t('content_page.published')" :value="statusCount('published')" icon="circle-check" />
      <LStatCard :label="t('content_page.draft')" :value="statusCount('draft')" icon="file-pen" />
      <LStatCard label="Content Type" :value="types.length" icon="boxes" />
    </div>

    <div class="cms-content-tabs" role="tablist" :aria-label="t('a11y.content_status')">
      <button v-for="item in statusTabs" :key="item.value" type="button" role="tab" class="cms-content-tab" :data-active="statusFilter===item.value" :aria-selected="statusFilter===item.value ? 'true' : 'false'" :tabindex="statusFilter===item.value ? 0 : -1" @click="setStatus(item.value)" @keydown.left.prevent="moveStatusTab($event, -1)" @keydown.right.prevent="moveStatusTab($event, 1)">{{ item.label }}</button>
    </div>

    <LPanel v-if="editorOpen" class="cms-control-panel">
      <template #header>{{ editingId ? t('content_page.edit', { type: typeLabel(form.type), id: editingId }) : t('content_page.add_type', { type: typeLabel(form.type) }) }}</template>
      <div class="cms-content-editor">
        <form class="cms-control-form cms-content-editor__main" @submit.prevent="saveContent(false)">
          <label class="cms-control-form__wide">{{ t('content_page.title') }}
            <input v-model.trim="form.title" type="text" maxlength="255" required :placeholder="t('content_page.title_placeholder')">
          </label>
          <label class="cms-control-form__wide">{{ t('content_page.excerpt') }}
            <textarea v-model="form.excerpt" rows="4" maxlength="2000" />
          </label>

          <div v-for="field in currentFields" :key="field.key" class="cms-content-field cms-control-form__wide">
            <label>{{ field.label }}<span v-if="field.required"> *</span>
              <textarea v-if="['richtext','textarea'].includes(field.type)" v-model="form.fields[field.key]" :rows="field.type==='richtext'?10:5" />
              <select v-else-if="field.type==='select' && Object.keys(field.choices||{}).length" v-model="form.fields[field.key]">
                <option v-for="(text,key) in field.choices" :key="key" :value="key">{{ text }}</option>
              </select>
              <input v-else-if="field.type==='boolean'" v-model="form.fields[field.key]" type="checkbox" class="cms-check-input">
              <input v-else-if="['number','media'].includes(field.type)" v-model="form.fields[field.key]" type="number" :min="field.type==='media'?1:undefined">
              <textarea v-else-if="['json','repeater','group'].includes(field.type)" v-model="form.fields[field.key]" rows="6" dir="ltr" spellcheck="false" />
              <input v-else v-model="form.fields[field.key]" type="text" :placeholder="field.multiple || ['relation','gallery','taxonomy'].includes(field.type) ? t('content_page.ids_csv') : ''">
            </label>
            <small class="cms-muted">{{ field.type }} · {{ field.storage }}<template v-if="field.multiple"> · {{ t('content_page.multiple') }}</template></small>
          </div>

          <details class="cms-content-advanced cms-control-form__wide">
            <summary>{{ t('content_page.advanced') }}</summary>
            <label>Metadata JSON
              <textarea v-model="form.metadataJson" rows="7" dir="ltr" spellcheck="false" />
            </label>
          </details>
        </form>

        <aside class="cms-content-editor__side">
          <LPanel>
            <template #header>{{ t('content_page.publishing') }}</template>
            <p v-if="!editingId" class="cms-muted">{{ t('content_page.new_is_draft') }}</p>
            <div v-else class="cms-definition-head"><span>{{ t('content_page.status') }}</span><LBadge :severity="statusSeverity(currentEditing?.status)">{{ statusLabel(currentEditing?.status||'draft') }}</LBadge></div>
            <div class="cms-card-actions cms-card-actions--wrap">
              <LButton icon="save" shape="rounded" :disabled="saving" @click="saveContent(false)">{{ saving ? t('content_page.saving') : (editingId ? t('content_page.save_changes') : t('content_page.save_draft')) }}</LButton>
              <LButton v-if="currentEditing?.status !== 'trash'" icon="send" shape="rounded" :disabled="saving" @click="saveContent(true)">{{ currentEditing?.status === 'published' ? t('content_page.save_published') : t('content_page.save_publish') }}</LButton>
              <LButton variant="outline" severity="neutral" shape="rounded" @click="closeEditor">{{ t('content_page.cancel') }}</LButton>
            </div>
            <template v-if="editingId">
              <label class="cms-schedule-field">{{ t('content_page.schedule_label') }}
                <input v-model="scheduleAt" type="datetime-local">
              </label>
              <LButton variant="outline" shape="rounded" @click="scheduleContent">{{ t('content_page.schedule_action') }}</LButton>
            </template>
          </LPanel>

          <LPanel>
            <template #header>{{ t('content_page.properties') }}</template>
            <div class="cms-control-form cms-control-form--single">
              <label>{{ t('content_page.content_type') }}
                <select v-model="form.type" :disabled="!!editingId" @change="changeType">
                  <option v-for="type in types" :key="type.key" :value="type.key">{{ type.singular_label || type.label || type.key }}</option>
                </select>
              </label>
              <label>Slug
                <input v-model.trim="form.slug" type="text" maxlength="190" dir="ltr" :placeholder="t('content_page.slug_auto')">
              </label>
              <label>Locale
                <input v-model.trim="form.locale" type="text" maxlength="16" dir="ltr">
              </label>
              <label v-if="currentType?.hierarchical">{{ t('content_page.parent') }}
                <select v-model="form.parent_id"><option value="">{{ t('content_page.no_parent') }}</option><option v-for="parent in parentOptions" :key="parent.id" :value="parent.id">{{ parent.title || `#${parent.id}` }}</option></select>
              </label>
              <small class="cms-muted">Revision: {{ currentType?.revisions ? t('content_page.enabled') : t('content_page.disabled') }} · Taxonomy: {{ currentType?.taxonomies?.join(t('common.list_separator')) || t('content_page.none') }}</small>
            </div>
          </LPanel>
        </aside>
      </div>
    </LPanel>

    <LPanel class="cms-control-panel">
      <template #header>{{ t('content_page.list') }}</template>
      <div class="cms-control-filters cms-content-filters">
        <input v-model="query" type="search" :placeholder="t('content_page.search_placeholder')" :aria-label="t('a11y.content_search')" @keyup.enter="applyFilters">
        <select v-model="typeFilter" :aria-label="t('a11y.content_type')"><option value="">{{ t('content_page.all_types') }}</option><option v-for="type in types" :key="type.key" :value="type.key">{{ type.label || type.key }}</option></select>
        <input v-model.trim="localeFilter" type="text" placeholder="fa" dir="ltr" :aria-label="t('a11y.content_locale')">
        <LButton icon="search" variant="outline" shape="rounded" :disabled="loading" @click="applyFilters">{{ t('content_page.apply_filter') }}</LButton>
      </div>

      <div class="cms-content-bulk">
        <input type="checkbox" class="cms-check-input" :disabled="bulkBusy" :checked="allSelected" :aria-label="t('a11y.select_all_content')" @change="toggleAll">
        <strong>{{ t('content_page.selected', { count: selected.length }) }}</strong>
        <select v-model="bulkAction" :disabled="bulkBusy" :aria-label="t('a11y.bulk_action')"><option value="">{{ t('content_page.bulk_action') }}</option><option value="publish">{{ t('content_page.bulk_publish') }}</option><option value="restore">{{ t('content_page.bulk_restore') }}</option><option value="trash">{{ t('content_page.bulk_trash') }}</option></select>
        <LButton size="sm" shape="rounded" :disabled="bulkBusy || !bulkAction || !selected.length" @click="runBulk">{{ bulkBusy ? t('content_page.executing') : t('content_page.execute') }}</LButton>
      </div>

      <CmsPageState :state="loading ? 'loading' : items.length ? 'ready' : 'empty'" empty-icon="file-plus-2" :empty-title="t('content_page.empty_title')" :empty-message="t('content_page.empty_message')">
        <div class="cms-mobile-only cms-extension-card-list">
          <article v-for="item in items" :key="item.id" class="cms-extension-card">
            <div class="cms-extension-card__head">
              <input type="checkbox" class="cms-check-input" :disabled="bulkBusy" :checked="selected.includes(String(item.id))" :aria-label="`${t('a11y.select_content')} #${item.id}`" @change="toggleSelected(item.id)">
              <div><strong>{{ item.title || t('content_page.untitled', { id: item.id }) }}</strong><small>{{ typeLabel(item.type) }} · {{ item.slug || `#${item.id}` }}</small></div>
              <LBadge :severity="statusSeverity(item.status)">{{ statusLabel(item.status) }}</LBadge>
            </div>
            <small class="cms-muted">{{ t('content_page.last_change', { date: item.updated_at || '—' }) }}</small>
            <div class="cms-card-actions cms-card-actions--wrap"><LButton size="sm" variant="outline" shape="rounded" @click="editContent(item)">{{ t('content_page.edit').split(' :type')[0] }}</LButton><LButton size="sm" variant="outline" shape="rounded" @click="openHistory(item)">{{ t('content_page.history') }}</LButton><LButton v-if="item.status!=='published'&&item.status!=='trash'" size="sm" shape="rounded" @click="publishContent(item)">{{ t('content_page.bulk_publish') }}</LButton><LButton v-if="item.status==='trash'" size="sm" shape="rounded" @click="restoreContent(item)">{{ t('content_page.restore') }}</LButton><LButton v-else size="sm" variant="outline" severity="danger" shape="rounded" @click="trashContent(item)">{{ t('content_page.trash_short') }}</LButton></div>
          </article>
        </div>

        <LPanel flush bare class="cms-desktop-only">
          <LDataTable :value="items" data-key="id">
            <Column header=""><template #body="{data:row}"><input type="checkbox" class="cms-check-input" :disabled="bulkBusy" :checked="selected.includes(String(row.id))" :aria-label="`${t('a11y.select_content')} #${row.id}`" @change="toggleSelected(row.id)"></template></Column>
            <Column field="title" :header="t('content_page.title')"><template #body="{data:row}"><div><strong>{{ row.title || t('content_page.untitled', { id: row.id }) }}</strong><small class="cms-table-subline">{{ row.slug || `#${row.id}` }}</small></div></template></Column>
            <Column :header="t('content_page.type')"><template #body="{data:row}">{{ typeLabel(row.type) }}</template></Column>
            <Column :header="t('content_page.status')"><template #body="{data:row}"><LBadge :severity="statusSeverity(row.status)">{{ statusLabel(row.status) }}</LBadge></template></Column>
            <Column :header="t('content_page.bulk_action').replace('…','')"><template #body="{data:row}"><div class="cms-card-actions"><LButton size="sm" variant="outline" shape="rounded" @click="editContent(row)">{{ t('content_page.edit').split(' :type')[0] }}</LButton><LButton size="sm" variant="outline" shape="rounded" @click="openHistory(row)">{{ t('content_page.history') }}</LButton><LButton v-if="row.status!=='published'&&row.status!=='trash'" size="sm" shape="rounded" @click="publishContent(row)">{{ t('content_page.bulk_publish') }}</LButton><LButton v-if="row.status==='trash'" size="sm" shape="rounded" @click="restoreContent(row)">{{ t('content_page.restore') }}</LButton><LButton v-else size="sm" variant="outline" severity="danger" shape="rounded" @click="trashContent(row)">{{ t('content_page.trash_short') }}</LButton></div></template></Column>
          </LDataTable>
        </LPanel>
      </CmsPageState>

      <div class="cms-content-pagination"><span class="cms-muted">{{ t('content_page.items_offset', { count: pagination.returned || 0, offset: pagination.offset || 0 }) }}</span><div class="cms-card-actions"><LButton variant="outline" shape="rounded" :disabled="loading || pagination.offset<=0" @click="prevPage">{{ t('content_page.previous') }}</LButton><LButton variant="outline" shape="rounded" :disabled="loading || !pagination.has_more" @click="nextPage">{{ t('content_page.next') }}</LButton></div></div>
    </LPanel>
  </LPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import Column from 'primevue/column'
import { LBadge, LButton, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { contentApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const items=ref([]),types=ref([]),loading=ref(false),saving=ref(false),bulkBusy=ref(false),error=ref(''),notice=ref(''),editorOpen=ref(false),editingId=ref(null),query=ref(''),typeFilter=ref(''),statusFilter=ref(''),localeFilter=ref(''),selected=ref([]),bulkAction=ref(''),scheduleAt=ref('')
const pagination=reactive({limit:50,offset:0,returned:0,has_more:false})
const form=reactive({site_id:1,type:'post',title:'',slug:'',excerpt:'',locale:'fa',parent_id:'',fields:{},metadataJson:'{}'})
const statusTabs=[{value:'',label:t('content_page.all')},{value:'draft',label:t('content_page.draft')},{value:'scheduled',label:t('content_page.scheduled')},{value:'published',label:t('content_page.published')},{value:'trash',label:t('content_page.trash_short')}]
const currentType=computed(()=>types.value.find(item=>item.key===form.type)||null)
const currentFields=computed(()=>currentType.value?.fields||[])
const currentEditing=computed(()=>items.value.find(item=>String(item.id)===String(editingId.value))||null)
const parentOptions=computed(()=>items.value.filter(item=>item.type===form.type&&String(item.id)!==String(editingId.value)))
const allSelected=computed(()=>items.value.length>0&&items.value.every(item=>selected.value.includes(String(item.id))))
function message(e){return e?.message||t('content_page.unexpected_error')}
function defaultValue(field){if(field.default!==null&&field.default!==undefined)return field.default;if(field.multiple)return[];if(field.type==='boolean')return false;if(['json','group'].includes(field.type))return'{}';if(field.type==='repeater')return'[]';return''}
function resetForm(typeKey=''){const key=typeKey||types.value[0]?.key||'post';form.site_id=1;form.type=key;form.title='';form.slug='';form.excerpt='';form.locale='fa';form.parent_id='';form.metadataJson='{}';form.fields={};const descriptor=types.value.find(item=>item.key===key);for(const field of descriptor?.fields||[])form.fields[field.key]=defaultValue(field);editingId.value=null;scheduleAt.value=''}
async function loadContents(resetOffset=false){if(resetOffset)pagination.offset=0;loading.value=true;error.value='';try{const r=await contentApi.list({search:query.value,type:typeFilter.value,status:statusFilter.value,locale:localeFilter.value,limit:pagination.limit,offset:pagination.offset});items.value=r.data?.items||[];types.value=r.data?.types||types.value;Object.assign(pagination,r.data?.pagination||{});selected.value=selected.value.filter(id=>items.value.some(item=>String(item.id)===id));if(!types.value.some(item=>item.key===form.type))resetForm(types.value[0]?.key||'post')}catch(e){error.value=message(e)}finally{loading.value=false}}
function moveStatusTab(event, delta) {
  const tabs = [...(event.currentTarget?.parentElement?.querySelectorAll('[role=\"tab\"]') || [])]
  if (!tabs.length) return
  const current = tabs.indexOf(event.currentTarget)
  const rtl = document.documentElement.dir === 'rtl'
  const step = rtl ? -delta : delta
  const next = tabs[(current + step + tabs.length) % tabs.length]
  next?.focus()
  next?.click()
}
function setStatus(value){statusFilter.value=value;loadContents(true)}
function applyFilters(){loadContents(true)}
function startCreate(){resetForm(typeFilter.value||types.value[0]?.key||'post');editorOpen.value=true;error.value='';notice.value='';window.scrollTo({top:0,behavior:'smooth'})}
function changeType(){if(editingId.value)return;resetForm(form.type)}
function extractValue(item,field){if(field.storage==='document')return item.document?.[field.key]??defaultValue(field);if(field.storage==='relation')return (item.relations?.[field.key]||[]).join(', ');if(field.storage==='taxonomy')return (item.terms?.[field.key]||[]).join(', ');const value=item.fields?.[field.key]??defaultValue(field);return ['json','repeater','group'].includes(field.type)&&typeof value!=='string'?JSON.stringify(value,null,2):Array.isArray(value)?value.join(', '):value}
function editContent(item){editingId.value=item.id;form.site_id=item.site_id||1;form.type=item.type;form.title=item.title||'';form.slug=item.slug||'';form.excerpt=item.excerpt||'';form.locale=item.locale||'fa';form.parent_id=item.parent_id?String(item.parent_id):'';form.metadataJson=JSON.stringify(item.metadata||{},null,2);form.fields={};const descriptor=types.value.find(type=>type.key===item.type);for(const field of descriptor?.fields||[])form.fields[field.key]=extractValue(item,field);scheduleAt.value=item.scheduled_at?String(item.scheduled_at).slice(0,16):'';editorOpen.value=true;window.scrollTo({top:0,behavior:'smooth'})}
function closeEditor(){editorOpen.value=false;resetForm(typeFilter.value||types.value[0]?.key||'post')}
function ids(value){return String(value||'').split(',').map(v=>Number(v.trim())).filter(v=>Number.isSafeInteger(v)&&v>0)}
function normalizeField(field,value){if(field.multiple||['relation','gallery','taxonomy'].includes(field.type))return ids(value);if(field.type==='media')return value===''?null:Number(value);if(field.type==='number')return value===''?null:Number(value);if(field.type==='boolean')return Boolean(value);if(['json','repeater','group'].includes(field.type)){try{return JSON.parse(value|| (field.type==='repeater'?'[]':'{}'))}catch{throw new Error(t('content_page.json_invalid',{field:field.label}))}}return value}
function payload(){if(!form.title.trim())throw new Error(t('content_page.title_required'));let metadata={};try{metadata=form.metadataJson.trim()?JSON.parse(form.metadataJson):{}}catch{throw new Error(t('content_page.metadata_invalid'))}const fields={};for(const field of currentFields.value){const value=form.fields[field.key];const blank=value===''||value===null||value===undefined;if(!editingId.value&&blank&&!field.required)continue;fields[field.key]=normalizeField(field,value)}return{site_id:Number(form.site_id||1),type:form.type,title:form.title.trim(),slug:form.slug.trim(),excerpt:form.excerpt,locale:form.locale.trim()||'fa',parent_id:form.parent_id?Number(form.parent_id):null,fields,metadata}}
async function saveContent(publishAfter) {
  if (saving.value) return
  saving.value=true;error.value='';notice.value=''
  try {
    const p=payload()
    const r=editingId.value?await contentApi.update(editingId.value,p):await contentApi.create(p)
    const record=r.data||r
    const id=record?.id||editingId.value
    // Preserve the committed ID/version before the independently failing publish step.
    if (id) {
      editingId.value=id
      const index=items.value.findIndex(item=>String(item.id)===String(id))
      const saved={...(index>=0?items.value[index]:p),...record,id}
      if(index>=0)items.value.splice(index,1,saved)
      else items.value.unshift(saved)
    }
    const status=record?.status||currentEditing.value?.status||'draft'
    if(publishAfter&&id&&status!=='published')await contentApi.publish(id)
    notice.value=publishAfter?(status==='published'?t('content_page.saved_published'):t('content_page.saved_publish')):t('content_page.saved')
    closeEditor();await loadContents()
  } catch(e) { error.value=message(e) }
  finally { saving.value=false }
}
async function scheduleContent(){if(!editingId.value||!scheduleAt.value){error.value=t('content_page.schedule_required');return}error.value='';try{await contentApi.schedule(editingId.value,new Date(scheduleAt.value).toISOString());notice.value=t('content_page.scheduled_notice');await loadContents()}catch(e){error.value=message(e)}}
async function act(fn){error.value='';try{await fn();notice.value=t('content_page.operation_done');await loadContents()}catch(e){error.value=message(e)}}
const publishContent=i=>act(()=>contentApi.publish(i.id));const restoreContent=i=>act(()=>contentApi.restore(i.id));const trashContent=i=>{if(!confirm(t('content_page.trash_confirm')))return;return act(()=>contentApi.trash(i.id))}
function openHistory(item){const base=window.location.pathname.replace(/\/content\/?$/,'');window.history.pushState({},'',`${base}/revisions?content=${encodeURIComponent(item.id)}`);window.dispatchEvent(new PopStateEvent('popstate'))}
function toggleSelected(id){if(bulkBusy.value)return;const value=String(id);selected.value=selected.value.includes(value)?selected.value.filter(v=>v!==value):[...selected.value,value]}
function toggleAll(){if(bulkBusy.value)return;selected.value=allSelected.value?[]:items.value.map(item=>String(item.id))}
async function runBulk() {
  if(bulkBusy.value)return
  const action=bulkAction.value
  const ids=[...new Set(selected.value)]
  if(!['publish','restore','trash'].includes(action)||!ids.length)return
  if(!confirm(t('content_page.bulk_confirm',{count:ids.length})))return
  bulkBusy.value=true;error.value='';notice.value=''
  const completed=new Set()
  let failure=''
  try {
    for(const id of ids) {
      if(action==='publish')await contentApi.publish(id)
      else if(action==='restore')await contentApi.restore(id)
      else await contentApi.trash(id)
      completed.add(id)
    }
    notice.value=t('content_page.processed',{count:completed.size})
  } catch(e) {
    failure=`${message(e)} (${t('content_page.processed_before_error',{count:completed.size})})`
  } finally {
    selected.value=selected.value.filter(id=>!completed.has(id))
    await loadContents()
    if(failure)error.value=error.value?`${failure} — ${error.value}`:failure
    bulkBusy.value=false
  }
}
function nextPage(){if(!pagination.has_more)return;pagination.offset+=pagination.limit;loadContents()}
function prevPage(){if(pagination.offset<=0)return;pagination.offset=Math.max(0,pagination.offset-pagination.limit);loadContents()}
function typeLabel(key){const type=types.value.find(item=>item.key===key);return type?.singular_label||type?.label||key||'—'}
function statusCount(v){return items.value.filter(item=>item.status===v).length}
function statusLabel(v){return({draft:t('content_page.draft'),scheduled:t('content_page.scheduled_full'),published:t('content_page.published'),trash:t('content_page.trash_short')})[v]||v||t('content_page.unknown')}
function statusSeverity(v){if(v==='published')return'success';if(v==='scheduled')return'info';if(v==='trash')return'danger';return'secondary'}
onMounted(async()=>{resetForm();await loadContents()})
</script>

<style scoped>
.cms-content-tabs{display:flex;gap:.45rem;overflow:auto;padding:.1rem 0 .4rem}.cms-content-tab{border:1px solid var(--p-surface-300);border-radius:999px;background:transparent;padding:.55rem .8rem;white-space:nowrap;cursor:pointer}.cms-content-tab[data-active="true"]{background:var(--p-primary-color);border-color:var(--p-primary-color);color:#fff}.cms-content-editor{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(260px,.75fr);gap:1rem}.cms-content-editor__main,.cms-content-editor__side{display:grid;gap:.8rem;align-content:start}.cms-content-field{padding:.8rem;border:1px solid var(--p-surface-200);border-radius:.75rem}.cms-content-field>label{display:grid;gap:.45rem}.cms-content-advanced{padding:.75rem;border:1px solid var(--p-surface-200);border-radius:.75rem}.cms-content-advanced summary{cursor:pointer;font-weight:650}.cms-content-advanced label{display:grid;gap:.45rem;margin-top:.7rem}.cms-schedule-field{display:grid;gap:.45rem;margin-top:.8rem}.cms-content-filters{grid-template-columns:minmax(220px,1fr) 180px 120px auto}.cms-content-bulk{display:flex;gap:.6rem;align-items:center;flex-wrap:wrap;margin:.8rem 0;padding:.7rem;border:1px dashed var(--p-surface-300);border-radius:.75rem}.cms-check-input{width:1.2rem;height:1.2rem}.cms-table-subline{display:block;opacity:.65;margin-top:.2rem}.cms-content-pagination{display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;margin-top:.8rem}.cms-control-form--single{grid-template-columns:1fr}@media(max-width:900px){.cms-content-editor{grid-template-columns:1fr}.cms-content-filters{grid-template-columns:1fr 1fr}}@media(max-width:600px){.cms-content-filters{grid-template-columns:1fr}}
</style>
