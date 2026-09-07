<template>
  <LPage icon="blocks">
    <div class="cms-stat-grid">
      <LStatCard :label="t('blocks_page.registered')" :value="data.blockDefinitions.length" icon="blocks" />
      <LStatCard :label="t('blocks_page.core')" :value="coreCount" icon="box" />
      <LStatCard :label="t('blocks_page.has_children')" :value="containerCount" icon="list-tree" />
      <LStatCard :label="t('blocks_page.responsive')" :value="responsiveCount" icon="smartphone" />
    </div>

    <LPanel>
      <template #header>{{ t('blocks_page.contract') }}</template>
      <div class="cms-capability-cloud">
        <LBadge severity="success">{{ t('blocks_page.versioned_json') }}</LBadge>
        <LBadge severity="success">{{ t('blocks_page.typed_attributes') }}</LBadge>
        <LBadge severity="success">{{ t('blocks_page.registry_owned') }}</LBadge>
        <LBadge severity="success">{{ t('blocks_page.migration_chain') }}</LBadge>
        <LBadge severity="success">{{ t('blocks_page.responsive_rules') }}</LBadge>
        <LBadge severity="success">{{ t('blocks_page.server_renderer') }}</LBadge>
      </div>
      <p class="cms-muted">{{ t('blocks_page.source_note') }}</p>
      <div class="cms-card-actions">
        <LButton size="sm" @click="router.push('/builder')">{{ t('blocks_page.open_builder') }}</LButton>
        <LButton size="sm" variant="outline" @click="router.push('/extensions')">{{ t('blocks_page.manage_packs') }}</LButton>
      </div>
    </LPanel>

    <LPanel v-if="missingEssentials.length" class="cms-inline-callout">
      <template #header>{{ t('blocks_page.extend_library') }}</template>
      <p>{{ t('blocks_page.extend_library_help') }}</p>
      <div class="cms-capability-cloud"><LBadge v-for="item in missingEssentials" :key="item.key" severity="secondary">{{ essentialLabel(item) }}</LBadge></div>
      <LButton size="sm" variant="outline" @click="router.push('/extensions')">{{ t('blocks_page.install_block_pack') }}</LButton>
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
          <div class="cms-block-card__preview" :data-kind="previewKind(block)">
            <LIcon :name="block.icon || 'box'" :size="26" />
            <strong>{{ block.title }}</strong>
            <span v-if="previewKind(block)==='text'">{{ t('blocks_page.preview_text') }}</span>
            <button v-else-if="previewKind(block)==='button'" type="button" disabled>{{ t('blocks_page.preview_button') }}</button>
            <span v-else>{{ t('blocks_page.preview_generic') }}</span>
          </div>
          <div class="cms-block-card__head">
            <LIcon :name="block.icon || 'box'" :size="22" />
            <div>
              <strong>{{ block.title }}</strong>
              <code>{{ block.name }}</code>
            </div>
            <LBadge severity="secondary">v{{ block.schemaVersion }}</LBadge>
          </div>

          <div class="cms-block-card__meta">
            <span>{{ t('blocks_page.category', { value: block.category }) }}</span>
            <span>{{ t('blocks_page.attribute_count', { count: Object.keys(block.attributes || {}).length }) }}</span>
          </div>

          <div class="cms-capability-cloud">
            <LBadge v-if="block.allowsChildren" severity="info">{{ t('blocks_page.children') }}</LBadge>
            <LBadge v-if="block.supports?.responsive" severity="success">{{ t('blocks_page.responsive') }}</LBadge>
            <LBadge v-if="block.supports?.typography" severity="secondary">{{ t('blocks_page.typography') }}</LBadge>
            <LBadge v-if="block.supports?.spacing" severity="secondary">{{ t('blocks_page.spacing') }}</LBadge>
          </div>
        </article>
      </div>
    </CmsPageState>
  </LPage>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  LBadge,
  LButton,
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
const router = useRouter()
const query = ref('')
const essentialBlocks=[
  {key:'image',tokens:['image']},
  {key:'gallery',tokens:['gallery']},
  {key:'video',tokens:['video']},
  {key:'navigation',tokens:['navigation','menu']},
  {key:'form',tokens:['form']},
]

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

const missingEssentials = computed(() => {
  const searchable=data.blockDefinitions.map(block=>`${block.name||''} ${block.title||''} ${block.category||''}`.toLowerCase())
  return essentialBlocks.filter(item=>!searchable.some(text=>item.tokens.some(token=>text.includes(token))))
})
function essentialLabel(item){
  return ({
    image:t('blocks_page.essential_image'),
    gallery:t('blocks_page.essential_gallery'),
    video:t('blocks_page.essential_video'),
    navigation:t('blocks_page.essential_navigation'),
    form:t('blocks_page.essential_form'),
  })[item.key] || item.key
}
function previewKind(block){
  const name=String(block.name||'')
  if(name.includes('heading')||name.includes('paragraph'))return 'text'
  if(name.includes('button'))return 'button'
  if(name.includes('section'))return 'layout'
  return 'generic'
}
</script>
