<template>
  <LPage icon="scroll-text" class="cms-logs-page">
    <div class="cms-stat-grid cms-stat-grid--four">
      <LStatCard :label="t('logs_page.active_recent')" :value="summary.active_errors ?? 0" icon="circle-alert" />
      <LStatCard :label="t('logs_page.historical')" :value="summary.historical_errors ?? 0" icon="history" />
      <LStatCard :label="t('logs_page.warnings')" :value="summary.warnings ?? 0" icon="triangle-alert" />
      <LStatCard :label="t('logs_page.health')" :value="health.overall || 'unknown'" icon="heart-pulse" />
    </div>

    <LPanel>
      <template #header>{{ t('logs_page.operational_status') }}</template>
      <div class="cms-log-status-row">
        <LBadge :severity="healthSeverity(health.overall)">Health: {{ health.overall || 'unknown' }}</LBadge>
        <span>{{ Number(summary.active_errors || 0) === 0 ? t('logs_page.no_active') : t('logs_page.needs_review') }}</span>
        <LButton :disabled="exporting" severity="secondary" @click="exportBundle">{{ exporting ? t('logs_page.exporting') : t('logs_page.support_bundle') }}</LButton>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('logs_page.filters') }}</template>
      <div class="cms-log-toolbar">
        <input v-model="filters.q" type="search" :placeholder="t('logs_page.search_placeholder')" />
        <select v-model="filters.level"><option value="">{{ t('logs_page.all_levels') }}</option><option value="error">Error</option><option value="warning">Warning</option><option value="info">Info</option><option value="debug">Debug</option></select>
        <select v-model="filters.channel"><option value="">{{ t('logs_page.all_channels') }}</option><option v-for="channel in summary.channels || []" :key="channel" :value="channel">{{ channel }}</option></select>
        <LButton @click="load">{{ t('logs_page.apply') }}</LButton><LButton severity="secondary" @click="load">{{ t('logs_page.refresh') }}</LButton>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Structured Logs ({{ summary.returned_records ?? logs.length }})</template>
      <div class="cms-log-list">
        <article v-for="row in logs" :key="`${row.timestamp}:${row.correlation_id}:${row.message}`" class="cms-log-card" :class="row.activity">
          <div class="cms-log-head"><div><LBadge :severity="levelSeverity(row.level)">{{ row.level }}</LBadge><LBadge severity="secondary">{{ row.activity === 'active' ? t('logs_page.activity_active') : row.activity === 'historical' ? t('logs_page.activity_historical') : t('logs_page.activity_normal') }}</LBadge><strong>{{ row.channel }}</strong></div><small>{{ formatTime(row.timestamp) }}</small></div>
          <p>{{ row.message }}</p>
          <code v-if="row.error_id || row.correlation_id">{{ row.error_id || row.correlation_id }}</code>
          <details :open="showTechnical"><summary>{{ t('logs_page.technical_details') }}</summary><div v-if="row.context?.exception"><strong>Exception:</strong> <code>{{ row.context.exception }}</code></div><div v-if="row.context?.source_file"><strong>Source:</strong> <code>{{ row.context.source_file }}:{{ row.context.source_line || '—' }}</code></div><div><strong>Correlation ID:</strong> <code>{{ row.correlation_id || '—' }}</code></div><pre>{{ JSON.stringify(row.context || {}, null, 2) }}</pre></details>
        </article>
        <div v-if="!loading && !logs.length" class="cms-empty-state">{{ t('logs_page.empty') }}</div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('logs_page.privacy_boundary') }}</template>
      <div class="cms-log-privacy-grid"><article><LBadge severity="success">REDACT</LBadge><strong>Secrets</strong><small>Password / Token / Authorization / API Key / Private Key</small></article><article><LBadge severity="success">BOUND</LBadge><strong>Correlation ID</strong><small>Request → Audit → Log → Queue</small></article><article><LBadge severity="warning">NO CONTENT</LBadge><strong>Support Export</strong><small>{{ t('logs_page.privacy_no_content') }}</small></article></div>
    </LPanel>
  </LPage>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { settingsApi, systemApi } from '../../services/cms-api.js'
import { t, cmsLocale } from '../../i18n/index.js'
const logs=ref([]),summary=ref({}),health=ref({}),loading=ref(false),exporting=ref(false),showTechnical=ref(false),pageSize=ref(50),activeMinutes=ref(15)
const filters=reactive({q:'',level:'',channel:''})
function levelSeverity(level){return level==='error'?'danger':level==='warning'?'warning':level==='info'?'info':'secondary'}
function healthSeverity(status){return status==='ok'?'success':status==='error'?'danger':status==='warning'?'warning':'secondary'}
function formatTime(timestamp){const n=Number(timestamp);return Number.isFinite(n)?new Date(n*1000).toLocaleString(cmsLocale()):'—'}
async function preferences(){try{const r=await settingsApi.list();const by=Object.fromEntries((r.data?.items||[]).map(i=>[i.key,i.value]));pageSize.value=Math.max(20,Math.min(200,Number(by['diagnostics.logs_page_size'])||50));activeMinutes.value=Math.max(5,Math.min(1440,Number(by['diagnostics.active_error_window_minutes'])||15));showTechnical.value=Boolean(by['diagnostics.show_technical_details'])}catch{}}
async function load(){loading.value=true;try{const r=await systemApi.logs({limit:pageSize.value,active_window:activeMinutes.value*60,...filters});logs.value=r.data?.logs||[];summary.value=r.data?.summary||{};health.value=r.data?.health||{}}finally{loading.value=false}}
function downloadJson(name,value){const blob=new Blob([JSON.stringify(value,null,2)],{type:'application/json;charset=utf-8'});const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download=name;document.body.appendChild(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),1000)}
async function exportBundle(){exporting.value=true;try{const r=await systemApi.supportBundle();downloadJson(`nanopino-support-${Date.now()}.json`,r.data?.bundle||r.data)}finally{exporting.value=false}}
onMounted(async()=>{await preferences();await load()})
</script>

<style scoped>
.cms-log-status-row,.cms-log-toolbar,.cms-log-head,.cms-log-head>div{display:flex;gap:.55rem;align-items:center;flex-wrap:wrap}.cms-log-status-row span{flex:1}.cms-log-toolbar input,.cms-log-toolbar select{min-height:2.7rem;border:1px solid var(--p-surface-300);border-radius:.65rem;padding:.5rem;background:var(--p-surface-0);color:inherit}.cms-log-toolbar input{flex:1;min-width:14rem}.cms-log-list{display:grid;gap:.65rem}.cms-log-card{border:1px solid var(--p-surface-200);border-radius:.85rem;padding:.75rem;display:grid;gap:.55rem}.cms-log-card.active{border-color:#ef4444}.cms-log-head{justify-content:space-between}.cms-log-card p{margin:0;line-height:1.7}.cms-log-card>code{direction:ltr;text-align:left;background:var(--p-surface-100);padding:.35rem .45rem;border-radius:.45rem;overflow-wrap:anywhere}.cms-log-card details{border-top:1px dashed var(--p-surface-300);padding-top:.5rem}.cms-log-card pre{direction:ltr;text-align:left;white-space:pre-wrap;max-height:22rem;overflow:auto;background:#111827;color:#e5e7eb;padding:.65rem;border-radius:.6rem}.cms-stat-grid--four{grid-template-columns:repeat(4,minmax(0,1fr))}@media(max-width:820px){.cms-stat-grid--four{grid-template-columns:repeat(2,minmax(0,1fr))}.cms-log-toolbar>*{width:100%}}
</style>
