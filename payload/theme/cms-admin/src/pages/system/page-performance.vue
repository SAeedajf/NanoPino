<template>
  <LPage icon="gauge" class="cms-performance-page">
    <template #actions><LButton icon="refresh-cw" variant="outline" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></template>
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>

    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Budget" :value="budgets.length" icon="target" />
      <LStatCard label="Queries" :value="snapshot.queries?.count ?? '—'" icon="database" />
      <LStatCard label="Memory" :value="formatBytes(snapshot.memory?.real_peak_bytes ?? snapshot.memory?.peak_bytes)" icon="memory-stick" />
    </div>

    <LPanel>
      <template #header>Performance Runtime</template>
      <div class="cms-performance-gates">
        <div><span>Query Probe</span><LBadge :severity="snapshot.queries?.bound ? 'success' : 'warning'">{{ snapshot.queries?.bound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Cache Telemetry</span><LBadge :severity="snapshot.cache ? 'success' : 'warning'">{{ snapshot.cache ? 'Live' : 'Unbound' }}</LBadge></div>
        <div><span>Extension Cost</span><LBadge :severity="snapshot.extensions ? 'success' : 'warning'">{{ snapshot.extensions ? 'Live' : 'Unbound' }}</LBadge></div>
        <div><span>Recent Samples</span><LBadge severity="secondary">{{ snapshot.recent_samples?.length || 0 }}</LBadge></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Performance Budgets — {{ snapshot.profile || center.profile }}</template>
      <CmsPageState :state="loading ? 'loading' : budgets.length ? 'ready' : 'empty'" empty-icon="target" :empty-title="t('performance_page.benchmark_note')" :empty-message="t('performance_page.unmeasured_note')">
        <div class="cms-mobile-only cms-performance-budget-cards">
          <article v-for="budget in budgets" :key="budget.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ budget.label || budget.id }}</strong><small>{{ budget.metric }}</small></div>
            <div class="cms-performance-budget-values"><LBadge :severity="budgetSeverity(budget.status)">{{ budget.status }}</LBadge><small>{{ valueOrDash(budget.value) }}</small></div>
          </article>
        </div>
        <LPanel flush bare class="cms-desktop-only">
          <LDataTable :value="budgets" data-key="id">
            <Column field="label" header="Budget" />
            <Column field="metric" header="Metric" />
            <Column field="value" header="Measured" />
            <Column field="target" header="Target" />
            <Column field="limit" header="Limit" />
            <Column field="status" header="Status" />
          </LDataTable>
        </LPanel>
      </CmsPageState>
    </LPanel>

    <div class="cms-performance-grid">
      <LPanel><template #header>Database / N+1</template><div class="cms-performance-detail"><span>Query count</span><strong>{{ valueOrDash(snapshot.queries?.count) }}</strong><span>Total DB time</span><strong>{{ valueOrDash(snapshot.queries?.total_ms) }} ms</strong><span>N+1 findings</span><strong>{{ snapshot.queries?.n_plus_one?.length || 0 }}</strong><span>Slow queries</span><strong>{{ snapshot.queries?.slow?.length || 0 }}</strong></div></LPanel>
      <LPanel><template #header>Cache Effectiveness</template><pre class="cms-code-preview">{{ pretty(snapshot.cache) }}</pre></LPanel>
      <LPanel><template #header>Extension Cost</template><pre class="cms-code-preview">{{ pretty(snapshot.extensions || []) }}</pre></LPanel>
      <LPanel><template #header>Recent Samples</template><pre class="cms-code-preview">{{ pretty(snapshot.recent_samples || []) }}</pre></LPanel>
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
