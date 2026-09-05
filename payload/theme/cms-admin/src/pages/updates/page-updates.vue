<template>
  <LPage icon="refresh-cw" class="cms-update-center">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Policy" :value="center.policies.length" icon="sliders-horizontal" />
      <LStatCard label="History" :value="center.history.length" icon="history" />
      <LStatCard label="API" :value="center.apiBound ? 'Bound' : 'Contract'" icon="plug" />
    </div>

    <LPanel>
      <template #header>Update Boundary</template>
      <div class="cms-update-boundary-grid">
        <article>
          <LBadge severity="info">Extension</LBadge>
          <strong>{{ center.boundary.extension }}</strong>
          <small>Snapshot → Staging → Migration/Lifecycle → Health → Commit/Rollback</small>
        </article>
        <article>
          <LBadge severity="warning">Platform / Pincore</LBadge>
          <strong>{{ center.boundary.platform }}</strong>
          <small>{{ t('updates_page.platform_boundary') }}</small>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Default Update Policy</template>
      <div class="cms-update-policy-grid">
        <div>
          <span>Channel</span>
          <strong>stable</strong>
        </div>
        <div>
          <span>Auto Update</span>
          <strong>disabled</strong>
        </div>
        <div>
          <span>Snapshot</span>
          <strong>required</strong>
        </div>
        <div>
          <span>Health Check</span>
          <strong>required</strong>
        </div>
      </div>
      <p class="cms-muted">
        {{ t('updates_page.policy_note') }}
      </p>
    </LPanel>

    <LPanel>
      <template #header>Snapshot Retention</template>
      <div class="cms-retention-grid">
        <div><strong>{{ center.retention.maxPerExtension }}</strong><small>{{ t('updates_page.max_per_extension') }}</small></div>
        <div><strong>{{ center.retention.maxAgeDays }}</strong><small>{{ t('updates_page.retention_days') }}</small></div>
        <div><strong>{{ center.retention.minimumReadyPoints }}</strong><small>{{ t('updates_page.minimum_ready') }}</small></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Update History</template>
      <CmsPageState
        :state="center.history.length ? 'ready' : 'empty'"
        empty-icon="history"
        :empty-title="t('updates_page.empty_title')"
        :empty-message="t('updates_page.empty_message')"
      >
        <div class="cms-mobile-only cms-stack">
          <article v-for="item in center.history" :key="item.id" class="cms-list-card cms-list-card--wide">
            <div>
              <strong>{{ item.extension_id }}</strong>
              <small>{{ item.from_version }} → {{ item.to_version }}</small>
            </div>
            <LBadge :severity="historySeverity(item.status)">{{ item.status }}</LBadge>
          </article>
        </div>

        <LPanel flush bare class="cms-desktop-only">
          <LDataTable :value="center.history" data-key="id">
            <Column field="extension_id" header="Extension" />
            <Column field="from_version" :header="t('updates_page.from')" />
            <Column field="to_version" :header="t('updates_page.to')" />
            <Column field="status" :header="t('updates_page.result')" />
            <Column field="recovery_point_id" header="Recovery Point" />
          </LDataTable>
        </LPanel>
      </CmsPageState>
    </LPanel>

    <LPanel>
      <template #header>Update API v1</template>
      <div class="cms-api-status">
        <LBadge :severity="center.apiBound ? 'success' : 'warning'">
          {{ center.apiBound ? 'Bound' : 'Contract only' }}
        </LBadge>
        <span>/api/v1/cms/updates</span>
      </div>
      <div class="cms-contract-list cms-contract-list--api">
        <div v-for="route in center.api" :key="`${route.method}:${route.path}`" class="cms-contract-row">
          <LBadge severity="secondary">{{ route.method }}</LBadge>
          <code>{{ route.path }}</code>
          <small>{{ route.capability }}</small>
        </div>
      </div>
    </LPanel>
  </LPage>
</template>

<script setup>
import Column from 'primevue/column'
import { LBadge, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'

const data = readAdminBootData()
const center = data.updateCenter

function historySeverity(status) {
  if (status === 'succeeded') return 'success'
  if (status === 'recovery_required') return 'warning'
  if (status === 'failed') return 'danger'
  return 'secondary'
}
</script>
