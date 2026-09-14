<template>
  <LPage icon="tags">
    <div class="cms-taxonomy-toolbar">
      <label>
        <span>{{ t('taxonomies_page.taxonomy') }}</span>
        <select v-model="activeKey" class="cms-input" :disabled="loading">
          <option v-for="taxonomy in taxonomies" :key="taxonomy.key" :value="taxonomy.key">{{ taxonomy.label }}</option>
        </select>
      </label>
      <label>
        <span>{{ t('taxonomies_page.search') }}</span>
        <input v-model="search" class="cms-input" type="search" :placeholder="t('taxonomies_page.search_placeholder')" @keydown.enter="loadTerms">
      </label>
      <LButton variant="outline" :disabled="loading" @click="loadTerms">{{ t('taxonomies_page.refresh') }}</LButton>
    </div>

    <div v-if="error" class="cms-inline-callout cms-inline-callout--danger" role="alert">
      <strong>{{ t('state.error_title') }}</strong><span>{{ error }}</span>
    </div>
    <div v-if="notice" class="cms-inline-callout cms-inline-callout--success" role="status" aria-live="polite">{{ notice }}</div>

    <div class="cms-taxonomy-layout">
      <LPanel>
        <template #header>
          <div class="cms-panel-heading"><div><strong>{{ activeTaxonomy?.label || t('taxonomies_page.terms') }}</strong><small>{{ t('taxonomies_page.term_count', { count: pagination.total || terms.length }) }}</small></div><LBadge severity="secondary">{{ activeTaxonomy?.hierarchical ? t('taxonomies_page.hierarchical') : t('taxonomies_page.flat') }}</LBadge></div>
        </template>
        <div v-if="loading" class="cms-page-state" role="status" aria-live="polite">{{ t('taxonomies_page.loading') }}</div>
        <div v-else-if="!terms.length" class="cms-page-state"><strong>{{ t('taxonomies_page.empty_title') }}</strong><p>{{ t('taxonomies_page.empty_message') }}</p></div>
        <div v-else class="cms-taxonomy-list">
          <article v-for="term in terms" :key="term.id" class="cms-taxonomy-row">
            <div class="cms-taxonomy-row__identity"><strong>{{ term.name }}</strong><small><code>{{ term.slug }}</code> · #{{ term.id }}</small><small v-if="term.parent_id">{{ t('taxonomies_page.parent', { id: term.parent_id }) }}</small></div>
            <div class="cms-card-actions">
              <LButton size="sm" variant="outline" :disabled="!canManage || busy" @click="editTerm(term)">{{ t('taxonomies_page.edit') }}</LButton>
              <LButton size="sm" variant="outline" severity="danger" :disabled="!canManage || busy" @click="removeTerm(term)">{{ t('taxonomies_page.delete') }}</LButton>
            </div>
          </article>
        </div>
        <div v-if="pagination.total > pagination.limit" class="cms-taxonomy-pagination"><LButton variant="outline" :disabled="pagination.offset <= 0 || loading" @click="previousPage">{{ t('taxonomies_page.previous') }}</LButton><span>{{ t('taxonomies_page.range', { from: pagination.total ? pagination.offset + 1 : 0, to: pagination.offset + terms.length, total: pagination.total }) }}</span><LButton variant="outline" :disabled="!pagination.has_more || loading" @click="nextPage">{{ t('taxonomies_page.next') }}</LButton></div>
      </LPanel>

      <LPanel class="cms-taxonomy-editor">
        <template #header>{{ editingId ? t('taxonomies_page.edit_term') : t('taxonomies_page.add_term') }}</template>
        <form class="cms-control-form cms-control-form--single" @submit.prevent="saveTerm">
          <label>{{ t('taxonomies_page.name') }}<input v-model="form.name" class="cms-input" required maxlength="255" :disabled="!canManage || busy"></label>
          <label>{{ t('taxonomies_page.slug') }}<input v-model="form.slug" class="cms-input" maxlength="255" :placeholder="t('taxonomies_page.slug_placeholder')" :disabled="!canManage || busy"></label>
          <label>{{ t('taxonomies_page.description') }}<textarea v-model="form.description" class="cms-input" rows="4" maxlength="5000" :disabled="!canManage || busy" /></label>
          <label v-if="activeTaxonomy?.hierarchical">{{ t('taxonomies_page.parent_term') }}<select v-model="form.parent_id" class="cms-input" :disabled="!canManage || busy"><option value="">{{ t('taxonomies_page.no_parent') }}</option><option v-for="parent in parentOptions" :key="parent.id" :value="String(parent.id)">{{ parent.name }}</option></select></label>
          <div class="cms-card-actions"><LButton type="submit" :disabled="!canManage || busy || !form.name.trim()">{{ busy ? t('taxonomies_page.saving') : t('taxonomies_page.save') }}</LButton><LButton v-if="editingId" type="button" variant="outline" :disabled="busy" @click="resetForm">{{ t('taxonomies_page.cancel') }}</LButton></div>
          <p v-if="!canManage" class="cms-muted">{{ t('taxonomies_page.read_only') }}</p>
        </form>
      </LPanel>
    </div>
  </LPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { LBadge, LButton, LPage, LPanel } from '@pinooxhq/luma/ui'
import { readAdminBootData, canAdmin } from '../../services/admin-provider.js'
import { taxonomyApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const boot = readAdminBootData()
const taxonomies = computed(() => (boot.taxonomyDefinitions || []).map((item) => ({ ...item, key: item.key || item.identifier })).filter((item) => item.key))
const activeKey = ref(taxonomies.value[0]?.key || 'category')
const activeTaxonomy = computed(() => taxonomies.value.find((item) => item.key === activeKey.value) || null)
const terms = ref([])
const search = ref('')
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const editingId = ref(null)
const pagination = reactive({ limit: 50, offset: 0, total: 0, has_more: false })
const form = reactive({ name: '', slug: '', description: '', parent_id: '' })
const canManage = computed(() => canAdmin('taxonomy.manage'))
const parentOptions = computed(() => terms.value.filter((term) => String(term.id) !== String(editingId.value)))

onMounted(loadTerms)
watch(activeKey, () => { resetForm(); pagination.offset = 0; loadTerms() })

function resetForm() {
  editingId.value = null
  Object.assign(form, { name: '', slug: '', description: '', parent_id: '' })
}

async function loadTerms() {
  if (loading.value || !activeKey.value) return
  loading.value = true; error.value = ''; notice.value = ''
  try {
    const response = await taxonomyApi.terms(activeKey.value, { site_id: 1, locale: 'fa', search: search.value, limit: pagination.limit, offset: pagination.offset })
    const data = response.data || {}
    terms.value = Array.isArray(data.items) ? data.items : []
    Object.assign(pagination, data.pagination || {})
  } catch (e) { error.value = e.message || t('state.error_message') } finally { loading.value = false }
}

function editTerm(term) {
  editingId.value = term.id
  Object.assign(form, { name: term.name || '', slug: term.slug || '', description: term.description || '', parent_id: term.parent_id ? String(term.parent_id) : '' })
}

async function saveTerm() {
  if (!canManage.value || busy.value || !form.name.trim()) return
  busy.value = true; error.value = ''; notice.value = ''
  try {
    const payload = { name: form.name.trim(), slug: form.slug.trim(), description: form.description, parent_id: form.parent_id ? Number(form.parent_id) : null, locale: 'fa', site_id: 1 }
    if (editingId.value) await taxonomyApi.update(activeKey.value, editingId.value, payload)
    else await taxonomyApi.create(activeKey.value, payload)
    notice.value = editingId.value ? t('taxonomies_page.updated') : t('taxonomies_page.created')
    resetForm(); pagination.offset = 0; await loadTerms()
  } catch (e) { error.value = e.message || t('state.error_message') } finally { busy.value = false }
}

async function removeTerm(term) {
  if (!canManage.value || busy.value || !confirm(t('taxonomies_page.delete_confirm', { name: term.name }))) return
  busy.value = true; error.value = ''; notice.value = ''
  try { await taxonomyApi.delete(activeKey.value, term.id); notice.value = t('taxonomies_page.deleted'); if (String(editingId.value) === String(term.id)) resetForm(); await loadTerms() }
  catch (e) { error.value = e.message || t('state.error_message') } finally { busy.value = false }
}

function nextPage() { if (!pagination.has_more) return; pagination.offset += pagination.limit; loadTerms() }
function previousPage() { pagination.offset = Math.max(0, pagination.offset - pagination.limit); loadTerms() }
</script>

<style scoped>
.cms-taxonomy-toolbar { display:grid; grid-template-columns:minmax(12rem,.65fr) minmax(16rem,1.5fr) auto; gap:.75rem; align-items:end; margin-block-end:1rem; }
.cms-taxonomy-toolbar label,.cms-taxonomy-editor form label { display:grid; gap:.4rem; }
.cms-taxonomy-toolbar label span { font-size:.8rem; font-weight:650; }
.cms-taxonomy-layout { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(18rem,.65fr); gap:1rem; align-items:start; }
.cms-taxonomy-list { display:grid; gap:.55rem; }
.cms-taxonomy-row { display:flex; justify-content:space-between; align-items:center; gap:1rem; padding:.8rem; border:1px solid var(--p-surface-200); border-radius:.75rem; }
.cms-taxonomy-row__identity { display:grid; gap:.25rem; min-width:0; }
.cms-taxonomy-row__identity small { opacity:.7; overflow-wrap:anywhere; }
.cms-taxonomy-pagination { display:flex; justify-content:space-between; align-items:center; gap:.75rem; margin-block-start:1rem; font-size:.8rem; }
@media (max-width:800px) { .cms-taxonomy-toolbar,.cms-taxonomy-layout { grid-template-columns:1fr; } .cms-taxonomy-row { align-items:stretch; flex-direction:column; } .cms-taxonomy-row .cms-card-actions { justify-content:flex-start; } }
</style>
