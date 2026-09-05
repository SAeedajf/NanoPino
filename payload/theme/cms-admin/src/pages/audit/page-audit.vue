<template>
  <LPage icon="scroll-text">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard :label="t('audit_page.events')" :value="data.auditEvents.length" icon="scroll-text" />
      <LStatCard :label="t('audit_page.failed')" :value="failedCount" icon="triangle-alert" />
      <LStatCard :label="t('audit_page.denied')" :value="deniedCount" icon="shield-x" />
    </div>

    <LTableToolbar
      v-model:search="query"
      :count-label="t('audit_page.count', { count: filtered.length })"
      :show-clear="!!query"
      @clear="query = ''"
    />

    <CmsPageState
      :state="filtered.length ? 'ready' : 'empty'"
      empty-icon="scroll-text"
      :empty-title="t('audit_page.empty_title')"
      :empty-message="t('audit_page.empty_message')"
    >
      <div class="cms-mobile-only cms-extension-card-list">
        <article v-for="event in filtered" :key="event.id" class="cms-extension-card">
          <div class="cms-extension-card__head">
            <div>
              <strong>{{ event.action }}</strong>
              <small>{{ event.owner }} · {{ event.correlationId || event.id }}</small>
            </div>
            <LBadge :severity="severity(event.outcome)">{{ event.outcome }}</LBadge>
          </div>
          <dl class="cms-meta-grid">
            <div><dt>Actor</dt><dd>{{ event.actorId ?? 'system' }}</dd></div>
            <div><dt>Target</dt><dd>{{ target(event) }}</dd></div>
            <div><dt>Scope</dt><dd>{{ event.scopeType }}</dd></div>
          </dl>
        </article>
      </div>

      <LPanel flush bare class="cms-desktop-only">
        <LDataTable :value="filtered" paginator :rows="20" data-key="id">
          <Column field="action" header="Action" />
          <Column field="owner" header="Owner" />
          <Column field="actorId" header="Actor" />
          <Column header="Outcome">
            <template #body="{ data: row }">
              <LBadge :severity="severity(row.outcome)">{{ row.outcome }}</LBadge>
            </template>
          </Column>
          <Column header="Target">
            <template #body="{ data: row }">{{ target(row) }}</template>
          </Column>
          <Column field="correlationId" header="Correlation" />
        </LDataTable>
      </LPanel>
    </CmsPageState>
  </LPage>
</template>

<script setup>
import { computed, ref } from 'vue'
import Column from 'primevue/column'
import {
  LBadge,
  LDataTable,
  LPage,
  LPanel,
  LStatCard,
  LTableToolbar,
} from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'

const data = readAdminBootData()
const query = ref('')

const failedCount = computed(() => data.auditEvents.filter((event) => event.outcome === 'failed').length)
const deniedCount = computed(() => data.auditEvents.filter((event) => event.outcome === 'denied').length)

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return data.auditEvents

  return data.auditEvents.filter((event) =>
    [event.action, event.owner, event.outcome, event.correlationId, event.targetType, event.targetId]
      .filter((value) => value !== null && value !== undefined)
      .some((value) => String(value).toLowerCase().includes(q)),
  )
})

function severity(outcome) {
  if (outcome === 'success') return 'success'
  if (outcome === 'denied') return 'warn'
  return 'danger'
}

function target(event) {
  if (!event.targetType) return '—'
  return `${event.targetType}:${event.targetId ?? '—'}`
}
</script>
