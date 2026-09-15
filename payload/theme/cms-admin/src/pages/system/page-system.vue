<template>
  <LPage icon="activity" class="cms-health-page">
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">
      <strong>{{ t('state.error_title') }}</strong><p>{{ error }}</p>
      <LButton variant="outline" :loading="loading" @click="load">{{ t('state.retry') }}</LButton>
    </div>
    <div class="cms-stat-grid">
      <LStatCard :label="t('system_page.php')" :value="runtime.php || '—'" icon="code" />
      <LStatCard :label="t('system_page.pinoox')" :value="runtime.pinoox || '—'" icon="box" />
      <LStatCard :label="t('system_page.cms')" :value="runtime.cms || '—'" icon="blocks" />
      <LStatCard :label="t('system_page.health')" :value="health.overall || 'unknown'" icon="heart-pulse" />
    </div>
    <LPanel class="cms-platform-card"><template #header>{{ platform.name }}</template><div class="cms-contract-list"><div class="cms-contract-row"><strong>{{ platform.contract }}</strong><span>v{{ platform.version }}</span></div><div class="cms-contract-row"><strong>{{ platform.runtime?.execution }}</strong><span>{{ platform.runtime?.api }}</span></div><div class="cms-contract-row"><strong>{{ platform.compatibility?.host_bindings || '—' }}</strong><span>min v{{ platform.compatibility?.minimum_version || '—' }}</span></div><div class="cms-contract-row cms-contract-row--stack"><strong>Capabilities</strong><div class="cms-capability-cloud"><LBadge v-for="capability in platform.capabilities || []" :key="capability" severity="secondary">{{ capability }}</LBadge></div></div></div></LPanel>
    <div class="cms-system-actions"><LButton :loading="loading" :disabled="loading" @click="load">{{ t('system_page.refresh_health') }}</LButton><LButton severity="secondary" @click="go('/system/logs')">{{ t('system_page.logs') }}</LButton><LButton severity="secondary" @click="go('/system/recovery')">{{ t('system_page.recovery') }}</LButton></div>
    <LPanel><template #header>{{ t('system_page.health_checks') }}</template><div v-if="loading" class="cms-muted" role="status" aria-live="polite">{{ t('system_page.checking') }}</div><div v-else class="cms-check-grid"><article v-for="row in health.checks || []" :key="row.id" class="cms-check"><div><strong>{{ labels[row.id] || row.id }}</strong><LBadge :severity="severity(row.status)">{{ row.status }}</LBadge></div><p>{{ row.message }}</p><small>{{ Number.isFinite(Number(row.durationMs)) ? `${Number(row.durationMs).toFixed(2)} ms` : '—' }}</small><details v-if="row.details && Object.keys(row.details).length"><summary>{{ t('system_page.technical_details') }}</summary><pre>{{ JSON.stringify(row.details, null, 2) }}</pre></details></article></div></LPanel>
    <LPanel><template #header>{{ t('system_page.operational_status') }}</template><div class="cms-contract-list"><div class="cms-contract-row"><strong>{{ t('system_page.active_errors') }}</strong><LBadge :severity="Number(logs.active_errors || 0) ? 'danger' : 'success'">{{ logs.active_errors ?? 0 }}</LBadge></div><div class="cms-contract-row"><strong>{{ t('system_page.historical_errors') }}</strong><span>{{ logs.historical_errors ?? 0 }}</span></div><div class="cms-contract-row"><strong>{{ t('system_page.structured_logs') }}</strong><span>{{ logs.available_records ?? 0 }}</span></div></div></LPanel>
    <LPanel><template #header>{{ t('system_page.health_history') }}</template><div v-if="historyRows.length" class="cms-contract-list"><div v-for="row in historyRows" :key="row.recorded_at" class="cms-contract-row"><strong>{{ formatHistoryTime(row.recorded_at) }}</strong><LBadge :severity="severity(row.overall)">{{ row.overall }}</LBadge><span>{{ row.results?.length || 0 }} {{ t('system_page.checks_count') }}</span></div></div><p v-else class="cms-muted">{{ t('system_page.health_history_empty') }}</p></LPanel>
  </LPage>
</template>
<script setup>
import { onMounted, ref } from 'vue'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { systemApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'
const boot=readAdminBootData(),runtime=boot.runtime||{},platform=boot.platform||{},health=ref({overall:'unknown',checks:[]}),logs=ref({}),historyRows=ref([]),loading=ref(false),error=ref('')
const labels={'system.php':t('system_page.php'),'system.memory':t('system_page.memory'),'system.disk':t('system_page.disk'),'system.database':t('system_page.database'),'system.cache':t('system_page.cache'),'system.storage':t('system_page.storage'),'system.scheduler':t('system_page.scheduler'),'system.queue':t('system_page.queue'),'system.extensions':t('system_page.extensions'),'system.kernel_compatibility':t('system_page.kernel_compatibility'),'security.posture':t('system_page.security_posture')}
const severity=s=>s==='ok'?'success':s==='error'?'danger':s==='warning'?'warning':'secondary'
async function load(){loading.value=true;error.value='';try{const results=await Promise.allSettled([systemApi.health(),systemApi.logs({limit:20,active_window:900}),systemApi.healthHistory(20)]);const [h,l,history]=results;if(h.status==='fulfilled')health.value=h.value.data||health.value;if(l.status==='fulfilled')logs.value=l.value.data?.summary||{};if(history.status==='fulfilled')historyRows.value=history.value.data?.items||[];const failures=results.filter(result=>result.status==='rejected');if(failures.length)error.value=t('system_page.partial_load_failed',{count:failures.length})}catch(e){error.value=e.message}finally{loading.value=false}}
function formatHistoryTime(value){const n=Number(value);return Number.isFinite(n)?new Date(n*1000).toLocaleString():'—'}
function go(path){history.pushState({},'',path);dispatchEvent(new PopStateEvent('popstate'))}
onMounted(load)
</script>
<style scoped>
.cms-system-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin:1rem 0}.cms-check-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:.65rem}.cms-check{border:1px solid var(--p-surface-200);border-radius:.8rem;padding:.7rem;display:grid;gap:.45rem}.cms-check>div{display:flex;justify-content:space-between;gap:.5rem;align-items:center}.cms-check p{margin:0;opacity:.72}.cms-check pre{direction:ltr;text-align:left;white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:.55rem;border-radius:.55rem;max-height:16rem;overflow:auto}
</style>
