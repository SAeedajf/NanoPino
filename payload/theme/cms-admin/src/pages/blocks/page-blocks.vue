<template>
  <LPage icon="blocks">
    <div class="cms-stat-grid">
      <LStatCard :label="t('blocks_page.registered')" :value="data.blockDefinitions.length" icon="blocks" />
      <LStatCard label="Core" :value="coreCount" icon="box" />
      <LStatCard :label="t('blocks_page.has_children')" :value="containerCount" icon="list-tree" />
      <LStatCard label="Responsive" :value="responsiveCount" icon="smartphone" />
    </div>

    <LPanel>
      <template #header>Block Contract</template>
      <div class="cms-capability-cloud">
        <LBadge severity="success">Versioned JSON</LBadge>
        <LBadge severity="success">Typed Attributes</LBadge>
        <LBadge severity="success">Registry Owned</LBadge>
        <LBadge severity="success">Migration Chain</LBadge>
        <LBadge severity="success">Responsive Rules</LBadge>
        <LBadge severity="success">Server Renderer</LBadge>
      </div>
      <p class="cms-muted">
        {{ t('blocks_page.source_note') }}
      </p>
    </LPanel>

    <LTableToolbar
      v-model:search="query"
      :count-label="t('blocks_page.count', { count: filtered.length })"
      :show-clear="!!query"
      @clear="query = ''"
    />

    <CmsPageState
      :state="filtered.length ? 'ready' : 'empty'"
      empty-icon="blocks"
      :empty-title="t('blocks_page.empty_title')"
      :empty-message="t('blocks_page.empty_message')"
    >
      <div class="cms-block-grid">
        <article v-for="block in filtered" :key="block.id" class="cms-block-card">
          <div class="cms-block-card__head">
            <LIcon :name="block.icon || 'box'" :size="22" />
            <div>
              <strong>{{ block.title }}</strong>
              <code>{{ block.name }}</code>
            </div>
            <LBadge severity="secondary">v{{ block.schemaVersion }}</LBadge>
          </div>

          <div class="cms-block-card__meta">
            <span>{{ block.category }}</span>
            <span>{{ Object.keys(block.attributes || {}).length }} attribute</span>
          </div>

          <div class="cms-capability-cloud">
            <LBadge v-if="block.allowsChildren" severity="info">Children</LBadge>
            <LBadge v-if="block.supports?.responsive" severity="success">Responsive</LBadge>
            <LBadge v-if="block.supports?.typography" severity="secondary">Typography</LBadge>
            <LBadge v-if="block.supports?.spacing" severity="secondary">Spacing</LBadge>
          </div>
        </article>
      </div>
    </CmsPageState>
  </LPage>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
  LBadge,
  LIcon,
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

const filtered = computed(() => {
  const q = query.value.trim().toLowerCase()
  if (!q) return data.blockDefinitions
  return data.blockDefinitions.filter((block) =>
    [block.name, block.title, block.category, block.owner]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(q)),
  )
})

const coreCount = computed(() =>
  data.blockDefinitions.filter((block) => block.owner === 'cms.core').length,
)
const containerCount = computed(() =>
  data.blockDefinitions.filter((block) => block.allowsChildren).length,
)
const responsiveCount = computed(() =>
  data.blockDefinitions.filter((block) => block.supports?.responsive).length,
)
</script>
