<template>
  <LPage icon="activity" class="cms-health-page">
    <div class="cms-stat-grid">
      <LStatCard label="PHP" :value="runtime.php || '—'" icon="code" />
      <LStatCard label="Pinoox" :value="runtime.pinoox || '—'" icon="box" />
      <LStatCard label="CMS" :value="runtime.cms || '—'" icon="blocks" />
      <LStatCard label="Health" :value="health.overall || 'unknown'" icon="heart-pulse" />
    </div>
    <div class="cms-system-actions"><LButton @click="load">{{ t('system_page.refresh_health') }}</LButton><LButton severity="secondary" @click="go('/system/logs')">{{ t('system_page.logs') }}</LButton><LButton severity="secondary" @click="go('/system/recovery')">{{ t('system_page.recovery') }}</LButton></div>
    <LPanel><template #header>Health Checks</template><div class="cms-check-grid"><article v-for="row in health.checks || []" :key="row.id" class="cms-check"><div><strong>{{ labels[row.id] || row.id }}</strong><LBadge :severity="severity(row.status)">{{ row.status }}</LBadge></div><p>{{ row.message }}</p><small>{{ Number.isFinite(Number(row.durationMs)) ? `${Number(row.durationMs).toFixed(2)} ms` : '—' }}</small><details v-if="row.details && Object.keys(row.details).length"><summary>{{ t('system_page.technical_details') }}</summary><pre>{{ JSON.stringify(row.details, null, 2) }}</pre></details></article></div></LPanel>
    <LPanel><template #header>{{ t('system_page.operational_status') }}</template><div class="cms-contract-list"><div class="cms-contract-row"><strong>{{ t('system_page.active_errors') }}</strong><LBadge :severity="Number(logs.active_errors || 0) ? 'danger' : 'success'">{{ logs.active_errors ?? 0 }}</LBadge></div><div class="cms-contract-row"><strong>{{ t('system_page.historical_errors') }}</strong><span>{{ logs.historical_errors ?? 0 }}</span></div><div class="cms-contract-row"><strong>Structured Logs</strong><span>{{ logs.available_records ?? 0 }}</span></div></div></LPanel>
  </LPage>
</template>
<script setup>
import { onMounted, ref } from 'vue'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { systemApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'
const runtime=readAdminBootData().runtime||{},health=ref({overall:'unknown',checks:[]}),logs=ref({})
const labels={'system.php':'PHP','system.memory':'Memory','system.disk':'Disk','system.database':'Database','system.cache':'Cache','system.storage':'Storage','system.scheduler':'Scheduler','system.queue':'Queue','system.extensions':'Extensions','system.kernel_compatibility':'Kernel Compatibility','security.posture':'Security Posture'}
const severity=s=>s==='ok'?'success':s==='error'?'danger':s==='warning'?'warning':'secondary'
async function load(){const [h,l]=await Promise.all([systemApi.health(),systemApi.logs({limit:20,active_window:900})]);health.value=h.data||health.value;logs.value=l.data?.summary||{}}
function go(path){history.pushState({},'',path);dispatchEvent(new PopStateEvent('popstate'))}
onMounted(load)
</script>
<style scoped>
.cms-system-actions{display:flex;gap:.55rem;flex-wrap:wrap;margin:1rem 0}.cms-check-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:.65rem}.cms-check{border:1px solid var(--p-surface-200);border-radius:.8rem;padding:.7rem;display:grid;gap:.45rem}.cms-check>div{display:flex;justify-content:space-between;gap:.5rem;align-items:center}.cms-check p{margin:0;opacity:.72}.cms-check pre{direction:ltr;text-align:left;white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:.55rem;border-radius:.55rem;max-height:16rem;overflow:auto}
</style>
