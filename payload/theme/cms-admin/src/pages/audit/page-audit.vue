<template>
  <LPage icon="scroll-text">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard :label="t('audit_page.events')" :value="data.auditEvents.length" icon="scroll-text" />
      <LStatCard :label="t('audit_page.failed')" :value="failedCount" icon="triangle-alert" />
      <LStatCard :label="t('audit_page.denied')" :value="deniedCount" icon="shield-x" />
    </div>

    <label class="cms-audit-search">
      <span>{{ t('audit_page.search', {}, 'جست‌وجوی رویدادها') }}</span>
      <input v-model="query" type="search" />
    </label>
    <p role="status">{{ t('audit_page.count', { count: filtered.length }) }}</p>

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
            <div><dt>{{ t('audit_page.time', {}, 'زمان (UTC)') }}</dt><dd><time :datetime="timestamp(event).iso">{{ timestamp(event).text }}</time></dd></div>
            <div><dt>{{ t('audit_page.actor', {}, 'عامل') }}</dt><dd>{{ event.actorId ?? 'system' }}</dd></div>
            <div><dt>{{ t('audit_page.target', {}, 'هدف') }}</dt><dd>{{ target(event) }}</dd></div>
            <div><dt>{{ t('audit_page.scope', {}, 'دامنه') }}</dt><dd>{{ event.scopeType }}</dd></div>
          </dl>
        </article>
      </div>

      <LPanel flush bare class="cms-desktop-only">
        <LDataTable :value="filtered" paginator :rows="20" data-key="id">
          <Column :header="t('audit_page.time', {}, 'زمان (UTC)')"><template #body="{ data: row }"><time :datetime="timestamp(row).iso">{{ timestamp(row).text }}</time></template></Column>
          <Column field="action" :header="t('audit_page.action', {}, 'عملیات')" />
          <Column field="owner" :header="t('audit_page.owner', {}, 'مالک')" />
          <Column field="actorId" :header="t('audit_page.actor', {}, 'عامل')" />
          <Column :header="t('audit_page.outcome', {}, 'نتیجه')">
            <template #body="{ data: row }">
              <LBadge :severity="severity(row.outcome)">{{ row.outcome }}</LBadge>
            </template>
          </Column>
          <Column :header="t('audit_page.target', {}, 'هدف')">
            <template #body="{ data: row }">{{ target(row) }}</template>
          </Column>
          <Column field="correlationId" :header="t('audit_page.correlation', {}, 'شناسه پیگیری')" />
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
} from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t, cmsLocale } from '../../i18n/index.js'
import { auditTimestamp, filterAuditEvents } from '../../services/audit-presentation.js'

const data = readAdminBootData()
const query = ref('')

const failedCount = computed(() => data.auditEvents.filter((event) => event.outcome === 'failed').length)
const deniedCount = computed(() => data.auditEvents.filter((event) => event.outcome === 'denied').length)

const filtered = computed(() => filterAuditEvents(data.auditEvents, query.value))
const timestamp = (event) => auditTimestamp(event.occurredAt, cmsLocale())

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

<style scoped>
.cms-audit-search { display: grid; gap: .5rem; max-width: 40rem; }
.cms-audit-search input { min-height: 44px; padding: .6rem; font: inherit; color: inherit; background: transparent; border: 1px solid currentColor; border-radius: .5rem; }
time { unicode-bidi: isolate; }
</style>
