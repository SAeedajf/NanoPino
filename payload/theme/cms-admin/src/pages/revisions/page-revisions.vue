<template>
  <LPage icon="history">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Schema" :value="`v${data.revisionSummary.schemaVersion}`" icon="file-json" />
      <LStatCard label="Revision Kind" :value="data.revisionSummary.kinds.length" icon="history" />
      <LStatCard label="Autosave" value="Non-destructive" icon="save" />
    </div>

    <LPanel>
      <template #header>Revision Lifecycle</template>
      <div class="cms-revision-kinds">
        <div v-for="kind in data.revisionSummary.kinds" :key="kind" class="cms-revision-kind">
          <LIcon :name="icon(kind)" :size="18" />
          <span>{{ label(kind) }}</span>
        </div>
      </div>
    </LPanel>

    <div class="cms-dashboard-grid">
      <LPanel>
        <template #header>Autosave</template>
        <p class="cms-muted">
          {{ t('revisions_page.autosave_note') }}
        </p>
      </LPanel>

      <LPanel>
        <template #header>Restore Safety</template>
        <p class="cms-muted">
          {{ t('revisions_page.restore_note') }}
        </p>
      </LPanel>
    </div>

    <LPanel>
      <template #header>History UI</template>
      <LEmptyPanel
        icon="history"
        :title="t('revisions_page.select_content')"
        :message="t('revisions_page.history_message')"
      />
    </LPanel>
  </LPage>
</template>

<script setup>
import { LEmptyPanel, LIcon, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'

const data = readAdminBootData()

function label(kind) {
  return ({
    initial: 'Initial',
    manual: 'Manual',
    autosave: 'Autosave',
    published: 'Published Snapshot',
    scheduled: 'Scheduled Snapshot',
    pre_restore: 'Pre-Restore',
    restored: 'Restored',
  })[kind] || kind
}

function icon(kind) {
  return ({
    initial: 'circle-dot',
    manual: 'save',
    autosave: 'timer-reset',
    published: 'send',
    scheduled: 'calendar-clock',
    pre_restore: 'shield',
    restored: 'rotate-ccw',
  })[kind] || 'history'
}
</script>
