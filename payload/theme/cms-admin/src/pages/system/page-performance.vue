<template>
  <LPage icon="gauge" class="cms-performance-page">
    <template #actions><LButton icon="refresh-cw" variant="outline" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></template>
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>

    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard :label="t('performance_page.budget')" :value="budgets.length" icon="target" />
      <LStatCard :label="t('performance_page.queries')" :value="snapshot.queries?.count ?? '—'" icon="database" />
      <LStatCard :label="t('performance_page.memory')" :value="formatBytes(snapshot.memory?.real_peak_bytes ?? snapshot.memory?.peak_bytes)" icon="memory-stick" />
    </div>

    <LPanel>
      <template #header>{{ t('performance_page.runtime') }}</template>
      <div class="cms-performance-gates">
        <div><span>{{ t('performance_page.query_probe') }}</span><LBadge :severity="snapshot.queries?.bound ? 'success' : 'warning'">{{ snapshot.queries?.bound ? t('performance_page.live') : t('performance_page.unbound') }}</LBadge></div>
        <div><span>{{ t('performance_page.cache_telemetry') }}</span><LBadge :severity="snapshot.cache ? 'success' : 'warning'">{{ snapshot.cache ? t('performance_page.live') : t('performance_page.unbound') }}</LBadge></div>
        <div><span>{{ t('performance_page.extension_cost') }}</span><LBadge :severity="snapshot.extensions ? 'success' : 'warning'">{{ snapshot.extensions ? t('performance_page.live') : t('performance_page.unbound') }}</LBadge></div>
        <div><span>{{ t('performance_page.recent_samples') }}</span><LBadge severity="secondary">{{ snapshot.recent_samples?.length || 0 }}</LBadge></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('performance_page.budget_heading', { profile: profileLabel }) }}</template>
      <CmsPageState :state="loading ? 'loading' : budgets.length ? 'ready' : 'empty'" empty-icon="target" :empty-title="t('performance_page.benchmark_note')" :empty-message="t('performance_page.unmeasured_note')">
        <div class="cms-mobile-only cms-performance-budget-cards">
          <article v-for="budget in budgets" :key="budget.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ budget.label || budget.id }}</strong><small>{{ budget.metric }}</small></div>
            <div class="cms-performance-budget-values"><LBadge :severity="budgetSeverity(budget.status)">{{ statusLabel(budget.status) }}</LBadge><small>{{ metricValue(budget) }}</small></div>
          </article>
        </div>
        <LPanel flush bare class="cms-desktop-only">
          <LDataTable :value="budgets" data-key="id">
            <Column field="label" :header="t('performance_page.budget')" />
            <Column field="metric" :header="t('performance_page.metric')" />
            <Column :header="t('performance_page.measured')"><template #body="{ data: row }">{{ metricValue(row) }}</template></Column>
            <Column field="target" :header="t('performance_page.target')" />
            <Column field="limit" :header="t('performance_page.limit')" />
            <Column :header="t('performance_page.status')"><template #body="{ data: row }"><LBadge :severity="budgetSeverity(row.status)">{{ statusLabel(row.status) }}</LBadge></template></Column>
          </LDataTable>
        </LPanel>
      </CmsPageState>
    </LPanel>

    <div class="cms-performance-grid">
      <LPanel><template #header>{{ t('performance_page.database_n_plus_one') }}</template><div class="cms-performance-detail"><span>{{ t('performance_page.query_count') }}</span><strong>{{ valueOrDash(snapshot.queries?.count) }}</strong><span>{{ t('performance_page.total_db_time') }}</span><strong>{{ metricNumber(snapshot.queries?.total_ms) }} ms</strong><span>{{ t('performance_page.n_plus_one_findings') }}</span><strong>{{ snapshot.queries?.n_plus_one?.length || 0 }}</strong><span>{{ t('performance_page.slow_queries') }}</span><strong>{{ snapshot.queries?.slow?.length || 0 }}</strong></div></LPanel>
      <LPanel><template #header>{{ t('performance_page.cache_effectiveness') }}</template><pre class="cms-code-preview">{{ pretty(snapshot.cache) }}</pre></LPanel>
      <LPanel><template #header>{{ t('performance_page.extension_cost') }}</template><pre class="cms-code-preview">{{ pretty(snapshot.extensions || []) }}</pre></LPanel>
      <LPanel><template #header>{{ t('performance_page.recent_samples') }}</template><pre class="cms-code-preview">{{ pretty(snapshot.recent_samples || []) }}</pre></LPanel>
    </div>
  </LPage>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Column from 'primevue/column'
import { LBadge, LButton, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { performanceApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const center = readAdminBootData().performanceCenter
const snapshot = ref({})
const loading = ref(false)
const error = ref('')
const budgets = computed(() => snapshot.value.budget_evaluations || [])
const profileLabel = computed(() => {
  const profile = snapshot.value.profile || center.profile
  return profile === 'shared_hosting' ? t('performance_page.profile_shared_hosting') : profile
})

async function load() {
  loading.value = true
  error.value = ''
  try {
    const response = await performanceApi.status()
    snapshot.value = response.data || {}
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function budgetSeverity(status) {
  if (status === 'pass') return 'success'
  if (status === 'fail') return 'danger'
  if (status === 'warning') return 'warning'
  return 'secondary'
}
function valueOrDash(value) { return value === null || value === undefined ? '—' : value }
function statusLabel(status) {
  const key = `performance_page.${String(status || 'unmeasured')}`
  return t(key, {}, String(status || 'unmeasured'))
}
function metricNumber(value) {
  const number = Number(value)
  return Number.isFinite(number) ? number.toFixed(2) : '—'
}
function metricValue(budget) {
  if (budget.value === null || budget.value === undefined) return t('performance_page.unmeasured')
  if (['api_ms', 'db_ms', 'search_ms', 'boot_ms', 'extension_boot_ms', 'builder_render_ms', 'queue_job_ms'].includes(budget.metric)) return metricNumber(budget.value)
  return valueOrDash(budget.value)
}
function formatBytes(bytes) {
  const value = Number(bytes)
  if (!Number.isFinite(value) || value < 0) return '—'
  const units = ['B', 'KiB', 'MiB', 'GiB']; let size = value; let unit = 0
  while (size >= 1024 && unit < units.length - 1) { size /= 1024; unit += 1 }
  return `${size.toFixed(unit ? 1 : 0)} ${units[unit]}`
}
function pretty(value) { return JSON.stringify(value ?? {}, null, 2) }

onMounted(load)
</script>
