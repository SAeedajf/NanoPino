<template>
  <LPage icon="settings" class="cms-settings-center">
    <div class="settings-toolbar">
      <input v-model="search" class="settings-input" type="search" :placeholder="t('settings_page.search_placeholder')" :aria-label="t('a11y.settings_search')" />
      <LButton :disabled="!dirtyCount || savingAll" @click="saveAll">
        {{ savingAll ? t('settings_page.saving') : `${t('settings_page.save_all')}${dirtyCount ? ` (${dirtyCount})` : ''}` }}
      </LButton>
      <LButton severity="secondary" @click="load">{{ t('settings_page.refresh') }}</LButton>
    </div>

    <div v-if="error" class="settings-alert settings-alert--error">{{ error }}</div>
    <div v-if="notice" class="settings-alert settings-alert--ok">{{ notice }}</div>

    <div class="settings-shell">
      <aside class="settings-nav" :aria-label="t('a11y.settings_groups')">
        <button v-for="group in groups" :key="group.id" type="button" :class="{ active: activeGroup === group.id }" :aria-current="activeGroup === group.id ? 'page' : undefined" @click="selectGroup(group.id)">
          <LIcon :name="group.icon" :size="18" aria-hidden="true" />
          <span><strong>{{ group.label }} ({{ group.count }})</strong><small>{{ group.description }}</small></span>
        </button>
      </aside>

      <main class="settings-main">
        <select v-model="activeGroup" class="settings-mobile-select" :aria-label="t('a11y.settings_group_mobile')">
          <option v-for="group in groups" :key="group.id" :value="group.id">{{ group.label }} ({{ group.count }})</option>
        </select>
        <div class="settings-heading">
          <div><h2>{{ search ? t('settings_page.search_results') : activeMeta.label }}</h2><p>{{ search ? t('settings_page.results_for', { query: search }) : activeMeta.description }}</p></div>
          <div class="settings-badges"><LBadge severity="secondary">{{ t('settings_page.setting_count', { count: filtered.length }) }}</LBadge><LBadge :severity="dirtyCount ? 'warning' : 'success'">{{ dirtyCount ? t('settings_page.unsaved_count', { count: dirtyCount }) : t('settings_page.all_saved') }}</LBadge></div>
        </div>

        <article v-for="item in filtered" :key="item.key" class="setting-card" :class="{ dirty: isDirty(item) }">
          <div class="setting-top">
            <div><strong>{{ item.label || item.key }}</strong><p :id="`setting-help-${safeId(item.key)}`">{{ item.ui?.description || t('settings_page.registry_description') }}</p></div>
            <div class="settings-badges"><LBadge severity="secondary">{{ item.scope?.type || 'global' }}</LBadge><LBadge v-if="isDirty(item)" severity="warning">{{ t('settings_page.unsaved') }}</LBadge></div>
          </div>

          <div class="setting-control">
            <template v-if="item.ui?.component === 'design-tokens'">
              <div class="setting-linked"><span>{{ t('settings_page.design_tokens_note') }}</span><LButton severity="secondary" @click="go('/appearance')">{{ t('settings_page.go_appearance') }}</LButton></div>
            </template>
            <label v-else-if="item.type === 'boolean' || item.ui?.component === 'boolean'" class="setting-switch"><input v-model="drafts[item.key]" type="checkbox" :aria-label="item.label || item.key" :aria-describedby="`setting-help-${safeId(item.key)}`" /><span>{{ drafts[item.key] ? t('settings_page.enabled') : t('settings_page.disabled') }}</span></label>
            <textarea v-else-if="item.ui?.component === 'textarea'" v-model="drafts[item.key]" class="settings-input settings-textarea" :rows="item.ui?.rows || 3" :aria-label="item.label || item.key" :aria-describedby="`setting-help-${safeId(item.key)}`" />
            <select v-else-if="item.ui?.component === 'select'" v-model="drafts[item.key]" class="settings-input" :aria-label="item.label || item.key" :aria-describedby="`setting-help-${safeId(item.key)}`"><option v-for="opt in item.ui?.options || []" :key="opt.value" :value="opt.value">{{ opt.label }}</option></select>
            <select v-else-if="item.ui?.component === 'timezone'" v-model="drafts[item.key]" class="settings-input" :aria-label="item.label || item.key" :aria-describedby="`setting-help-${safeId(item.key)}`"><option v-for="zone in timezones" :key="zone" :value="zone">{{ zone }}</option></select>
            <textarea v-else-if="item.type === 'json'" v-model="drafts[item.key]" class="settings-input settings-textarea settings-mono" rows="8" :aria-label="item.label || item.key" :aria-describedby="`setting-help-${safeId(item.key)}`" />
            <input v-else v-model="drafts[item.key]" class="settings-input" :type="numeric(item) ? 'number' : item.sensitive ? 'password' : 'text'" :min="item.ui?.min" :max="item.ui?.max" :step="item.ui?.step" :aria-label="item.label || item.key" :aria-describedby="`setting-help-${safeId(item.key)}`" />
          </div>

          <div class="setting-actions"><LButton :disabled="!isDirty(item)" @click="saveOne(item)">{{ t('settings_page.save') }}</LButton><LButton severity="secondary" @click="reset(item)">{{ t('settings_page.reset') }}</LButton><small>{{ item.version ? t('settings_page.version', { version: item.version }) : t('settings_page.default_value') }}</small></div>
          <details class="setting-details"><summary>{{ t('settings_page.technical_details') }}</summary><div><code>{{ item.key }}</code><span>Owner: {{ item.owner }}</span><span>Type: {{ item.type }}</span><span>Scopes: {{ (item.scopes || []).join(', ') }}</span></div></details>
        </article>

        <div v-if="!loading && !filtered.length" class="settings-empty">{{ t('settings_page.empty') }}</div>
        <div v-if="loading" class="settings-empty" role="status" aria-live="polite">{{ t('settings_page.loading') }}</div>
      </main>
    </div>
  </LPage>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { LBadge, LButton, LIcon, LPage } from '@pinooxhq/luma/ui'
import { t } from '../../i18n/index.js'
import { settingsApi } from '../../services/cms-api.js'

const GROUPS={general:{label:t('settings_page.general'),icon:'settings-2',description:t('settings_page.general_desc')},localization:{label:t('settings_page.localization'),icon:'languages',description:t('settings_page.localization_desc')},admin:{label:t('settings_page.admin'),icon:'panel-top',description:t('settings_page.admin_desc')},diagnostics:{label:t('settings_page.diagnostics'),icon:'scan-search',description:t('settings_page.diagnostics_desc')},api:{label:t('settings_page.api'),icon:'waypoints',description:t('settings_page.api_desc')},appearance:{label:t('settings_page.appearance'),icon:'palette',description:t('settings_page.appearance_desc')}}
const items=ref([]),drafts=ref({}),originals=ref({}),activeGroup=ref('general'),search=ref(''),loading=ref(false),savingAll=ref(false),error=ref(''),notice=ref('')
const meta=id=>GROUPS[id]||{label:id||t('settings_page.other'),icon:'circle-help',description:t('settings_page.core_extension_settings')}
const groups=computed(()=>{const ids=[...new Set(items.value.map(i=>i.group))];return ids.map(id=>({id,...meta(id),count:items.value.filter(i=>i.group===id).length}))})
const activeMeta=computed(()=>meta(activeGroup.value))
const filtered=computed(()=>{const q=search.value.trim().toLowerCase();return items.value.filter(i=>q?`${i.label} ${i.key} ${i.group} ${i.ui?.description||''} ${(i.ui?.keywords||[]).join(' ')}`.toLowerCase().includes(q):i.group===activeGroup.value)})
const dirtyCount=computed(()=>items.value.filter(isDirty).length)
const timezones=computed(()=>{try{return Intl.supportedValuesOf?.('timeZone')||['UTC','Asia/Tehran','Europe/Helsinki']}catch{return['UTC','Asia/Tehran','Europe/Helsinki']}})
function safeId(value){return String(value||'setting').replace(/[^A-Za-z0-9_-]+/g,'-')}
function clone(item){if(item.type==='json')return JSON.stringify(item.value??{},null,2);if(item.type==='string_list')return Array.isArray(item.value)?item.value.join(', '):'';return item.value}
function comparable(item,v){if(item.type==='json'){try{return JSON.stringify(JSON.parse(v))}catch{return String(v)}}if(item.type==='boolean')return v?'1':'0';if(['integer','number','float'].includes(item.type))return String(Number(v));return String(v??'')}
function isDirty(item){return comparable(item,drafts.value[item.key])!==originals.value[item.key]}
function numeric(item){return ['integer','number','float'].includes(item.type)}
function valueOf(item){const v=drafts.value[item.key];if(item.type==='json')return JSON.parse(v||'{}');if(item.type==='string_list')return String(v||'').split(',').map(x=>x.trim()).filter(Boolean);if(item.type==='boolean')return Boolean(v);if(numeric(item))return Number(v);return v}
async function load(){loading.value=true;error.value='';try{const r=await settingsApi.list();items.value=(r.data?.items||[]).slice().sort((a,b)=>String(a.group).localeCompare(String(b.group))||Number(a.ui?.order||999)-Number(b.ui?.order||999));const d={},o={};for(const i of items.value){d[i.key]=clone(i);o[i.key]=comparable(i,d[i.key])}drafts.value=d;originals.value=o;if(!groups.value.some(g=>g.id===activeGroup.value))activeGroup.value=groups.value[0]?.id||'general'}catch(e){error.value=e.message}finally{loading.value=false}}
async function save(item){const s=item.scope||{};await settingsApi.update(item.key,{value:valueOf(item),scope_type:s.type||'site',scope_id:s.id??1,expected_version:item.version||null})}
async function saveOne(item){try{await save(item);notice.value=t('settings_page.saved_one',{label:item.label||item.key});await load()}catch(e){error.value=e.message}}
async function saveAll(){const dirty=items.value.filter(isDirty);if(!dirty.length)return;savingAll.value=true;try{for(const item of dirty)await save(item);notice.value=t('settings_page.saved_many',{count:dirty.length});await load()}catch(e){error.value=e.message}finally{savingAll.value=false}}
async function reset(item){try{const s=item.scope||{};await settingsApi.reset(item.key,{scope_type:s.type||'site',scope_id:s.id??1,expected_version:item.version||null});notice.value=t('settings_page.reset_notice',{label:item.label||item.key});await load()}catch(e){error.value=e.message}}
function selectGroup(id){activeGroup.value=id;search.value=''}
function go(path){history.pushState({},'',path);dispatchEvent(new PopStateEvent('popstate'))}
onMounted(load)
</script>

<style scoped>
.cms-settings-center{direction:inherit}.settings-toolbar{display:flex;gap:.65rem;align-items:center;flex-wrap:wrap;margin-bottom:1rem}.settings-toolbar .settings-input{flex:1;min-width:14rem}.settings-shell{display:grid;grid-template-columns:14rem minmax(0,1fr);gap:1rem}.settings-nav{display:grid;gap:.35rem;align-content:start}.settings-nav button{text-align:start;border:1px solid transparent;background:transparent;border-radius:.8rem;padding:.7rem;display:grid;grid-template-columns:1.7rem 1fr;gap:.45rem;color:inherit}.settings-nav button.active{background:var(--p-primary-50);border-color:var(--p-primary-200)}.settings-nav small{display:block;opacity:.65;margin-top:.2rem}.settings-main{display:grid;gap:.75rem}.settings-mobile-select{display:none}.settings-heading,.setting-top,.setting-actions,.settings-badges{display:flex;gap:.6rem;align-items:flex-start;justify-content:space-between;flex-wrap:wrap}.settings-heading h2{margin:0}.settings-heading p,.setting-top p{margin:.3rem 0 0;opacity:.68}.setting-card{border:1px solid var(--p-surface-200);border-radius:.9rem;padding:.85rem;display:grid;gap:.75rem}.setting-card.dirty{border-color:var(--p-primary-400)}.settings-input{width:100%;min-height:2.7rem;padding:.55rem .7rem;border:1px solid var(--p-surface-300);border-radius:.65rem;background:var(--p-surface-0);color:inherit;box-sizing:border-box}.settings-textarea{min-height:6rem}.settings-mono{direction:ltr;text-align:left;font-family:monospace}.setting-switch{display:flex;gap:.6rem;align-items:center;min-height:2.7rem}.setting-switch input{width:1.35rem;height:1.35rem}.setting-actions small{margin-inline-start:auto;opacity:.6}.setting-details{border-top:1px dashed var(--p-surface-300);padding-top:.6rem;font-size:.75rem}.setting-details div{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.5rem}.setting-linked,.settings-alert,.settings-empty{padding:.75rem;border:1px solid var(--p-surface-200);border-radius:.75rem}.setting-linked{display:flex;justify-content:space-between;align-items:center;gap:.6rem}.settings-alert--error{border-color:#ef4444}.settings-alert--ok{border-color:#16a34a}.settings-empty{text-align:center;opacity:.7}@media(max-width:780px){.settings-shell{grid-template-columns:1fr}.settings-nav{display:none}.settings-mobile-select{display:block}.setting-card{padding:.75rem}}
</style>
