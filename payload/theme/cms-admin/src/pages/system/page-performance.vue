<template>
  <LPage icon="gauge" class="cms-performance-page">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Budget" :value="center.budgets.length" icon="target" />
      <LStatCard label="Profile" :value="center.profile" icon="server" />
      <LStatCard label="API" :value="center.apiBound ? 'Bound' : 'Contract'" icon="waypoints" />
    </div>

    <LPanel>
      <template #header>Performance Runtime Gates</template>
      <div class="cms-performance-gates">
        <div><span>Runtime Profiler</span><LBadge :severity="center.runtimeProfilerBound ? 'success' : 'warning'">{{ center.runtimeProfilerBound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Query Probe</span><LBadge :severity="center.queryProbeBound ? 'success' : 'warning'">{{ center.queryProbeBound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Cache Telemetry</span><LBadge :severity="center.cacheTelemetryBound ? 'success' : 'warning'">{{ center.cacheTelemetryBound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Extension Cost</span><LBadge :severity="center.extensionCostBound ? 'success' : 'warning'">{{ center.extensionCostBound ? 'Bound' : 'Unbound' }}</LBadge></div>
      </div>
      <p class="cms-muted">
        {{ t('performance_page.unmeasured_note') }}
      </p>
    </LPanel>

    <LPanel>
      <template #header>Performance Budgets — Shared Hosting</template>

      <div class="cms-mobile-only cms-performance-budget-cards">
        <article v-for="budget in center.budgets" :key="budget.id" class="cms-list-card cms-list-card--wide">
          <div>
            <strong>{{ budget.label }}</strong>
            <small>{{ budget.metric }}</small>
          </div>
          <div class="cms-performance-budget-values">
            <LBadge :severity="budgetSeverity(budget.status)">{{ budget.status }}</LBadge>
            <small>target {{ formatBudget(budget.target, budget.unit) }} · limit {{ formatBudget(budget.limit, budget.unit) }}</small>
          </div>
        </article>
      </div>

      <LPanel flush bare class="cms-desktop-only">
        <LDataTable :value="center.budgets" data-key="id">
          <Column field="label" header="Budget" />
          <Column field="metric" header="Metric" />
          <Column header="Measured">
            <template #body="{ data }">{{ data.value === null ? 'Unmeasured' : formatBudget(data.value, data.unit) }}</template>
          </Column>
          <Column header="Target">
            <template #body="{ data }">{{ formatBudget(data.target, data.unit) }}</template>
          </Column>
          <Column header="Limit">
            <template #body="{ data }">{{ formatBudget(data.limit, data.unit) }}</template>
          </Column>
          <Column header="Status">
            <template #body="{ data }"><LBadge :severity="budgetSeverity(data.status)">{{ data.status }}</LBadge></template>
          </Column>
        </LDataTable>
      </LPanel>
    </LPanel>

    <div class="cms-performance-grid">
      <LPanel>
        <template #header>Database / N+1</template>
        <div class="cms-performance-detail">
          <span>Query count</span><strong>{{ valueOrDash(center.query.count) }}</strong>
          <span>Total DB time</span><strong>{{ center.query.totalMs === null ? '—' : `${center.query.totalMs} ms` }}</strong>
          <span>N+1 findings</span><strong>{{ center.query.nPlusOne.length }}</strong>
          <span>Slow queries</span><strong>{{ center.query.slow.length }}</strong>
        </div>
        <small class="cms-muted">{{ center.queryProbeBound ? t('performance_page.query_bound') : t('performance_page.query_unbound') }}</small>
      </LPanel>

      <LPanel>
        <template #header>Cache Effectiveness</template>
        <div class="cms-performance-detail">
          <span>Hits</span><strong>{{ valueOrDash(center.cache.hits) }}</strong>
          <span>Misses</span><strong>{{ valueOrDash(center.cache.misses) }}</strong>
          <span>Hit ratio</span><strong>{{ center.cache.hitRatio === null ? '—' : `${Math.round(center.cache.hitRatio * 100)}%` }}</strong>
        </div>
      </LPanel>

      <LPanel>
        <template #header>Memory</template>
        <div class="cms-performance-detail">
          <span>Peak</span><strong>{{ center.memory.peakBytes === null ? '—' : formatBytes(center.memory.peakBytes) }}</strong>
        </div>
      </LPanel>

      <LPanel>
        <template #header>Shared Hosting Profile</template>
        <div class="cms-performance-detail">
          <span>Worker required</span><strong>{{ center.sharedHosting.workerRequired ? 'Yes' : 'No' }}</strong>
          <span>Queue fallback</span><strong>{{ center.sharedHosting.queueFallback }}</strong>
          <span>External Search</span><strong>{{ center.sharedHosting.externalSearchRequired ? 'Required' : 'Optional' }}</strong>
          <span>Default Cache</span><strong>{{ center.sharedHosting.defaultCache }}</strong>
        </div>
      </LPanel>
    </div>

    <LPanel>
      <template #header>Portable Benchmarks</template>
      <div v-if="center.benchmarks.length" class="cms-contract-list">
        <div v-for="row in center.benchmarks" :key="row.name" class="cms-contract-row">
          <strong>{{ row.name }}</strong>
          <span>P50 {{ row.p50_ms }} ms</span>
          <span>P95 {{ row.p95_ms }} ms</span>
          <span>P99 {{ row.p99_ms }} ms</span>
        </div>
      </div>
      <div v-else class="cms-empty-state">
        {{ t('performance_page.benchmark_note') }}
      </div>
    </LPanel>

    <LPanel>
      <template #header>Performance API v1</template>
      <div class="cms-api-status">
        <LBadge :severity="center.apiBound ? 'success' : 'warning'">
          {{ center.apiBound ? 'Bound' : 'Contract only' }}
        </LBadge>
        <span>/api/v1/cms/system/performance</span>
      </div>
      <div class="cms-contract-list cms-contract-list--api">
        <div v-for="route in center.api" :key="`${route.method}:${route.path}`" class="cms-contract-row">
          <LBadge severity="secondary">{{ route.method }}</LBadge>
          <code>{{ route.path }}</code>
          <small>{{ route.capability }} · {{ route.rate_limit }}</small>
        </div>
      </div>
    </LPanel>
  </LPage>
</template>

<script setup>
import Column from 'primevue/column'
import { LBadge, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'

const center = readAdminBootData().performanceCenter

function budgetSeverity(status) {
  if (status === 'pass') return 'success'
  if (status === 'fail') return 'danger'
  if (status === 'warning') return 'warning'
  return 'secondary'
}

function valueOrDash(value) {
  return value === null || value === undefined ? '—' : value
}

function formatBytes(bytes) {
  const value = Number(bytes)
  if (!Number.isFinite(value) || value < 0) return '—'
  const units = ['B', 'KiB', 'MiB', 'GiB']
  let size = value
  let unit = 0
  while (size >= 1024 && unit < units.length - 1) {
    size /= 1024
    unit += 1
  }
  return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`
}

function formatBudget(value, unit) {
  if (unit === 'bytes') return formatBytes(value)
  if (unit === 'ratio') return `${Math.round(Number(value) * 100)}%`
  return `${value} ${unit || ''}`.trim()
}
</script>
