<template>
  <LPage icon="layout-dashboard" header-tone="gradient">
    <SafeModeBanner :safe-mode="boot.safeMode" />
    <template #actions><LButton icon="refresh-cw" variant="outline" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></template>
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>

    <div class="cms-stat-grid" :aria-label="t('dashboard.summary_aria')">
      <LStatCard :label="t('dashboard.content')" :value="summary.content" icon="files" />
      <LStatCard :label="t('dashboard.media')" :value="summary.media" icon="image" />
      <LStatCard :label="t('dashboard.extensions')" :value="summary.extensions" icon="blocks" />
      <LStatCard :label="t('dashboard.attention')" :value="summary.problems" icon="triangle-alert" />
    </div>

    <LPanel class="cms-control-panel">
      <template #header>{{ t('dashboard.quick_actions') }}</template>
      <div class="cms-quick-actions">
        <LButton icon="file-plus-2" shape="rounded" @click="go('/content')">{{ t('dashboard.manage_content') }}</LButton>
        <LButton icon="image-plus" variant="outline" shape="rounded" @click="go('/media')">{{ t('dashboard.media') }}</LButton>
        <LButton icon="panels-top-left" variant="outline" shape="rounded" @click="go('/builder')">{{ t('dashboard.builder') }}</LButton>
        <LButton icon="palette" variant="outline" shape="rounded" @click="go('/appearance')">{{ t('dashboard.theme') }}</LButton>
        <LButton icon="package-plus" variant="outline" shape="rounded" @click="go('/extensions')">{{ t('dashboard.extensions') }}</LButton>
        <LButton icon="users" variant="outline" shape="rounded" @click="go('/users')">{{ t('dashboard.users') }}</LButton>
        <LButton icon="settings" variant="outline" shape="rounded" @click="go('/settings')">{{ t('dashboard.settings') }}</LButton>
      </div>
      <p class="cms-muted">{{ t('dashboard.control_plane_hint') }}</p>
    </LPanel>

    <div class="cms-dashboard-grid">
      <LPanel>
        <template #header>{{ t('dashboard.system_health') }}</template>
        <CmsPageState :state="loading ? 'loading' : health.length ? 'ready' : 'empty'">
          <ul class="cms-health-list">
            <li v-for="item in health.slice(0, 6)" :key="item.id" class="cms-health-row">
              <span>{{ item.label || item.id }}</span><LBadge :severity="severity(item.status)">{{ item.status }}</LBadge>
            </li>
          </ul>
        </CmsPageState>
      </LPanel>

      <LPanel>
        <template #header>{{ t('dashboard.extension_attention') }}</template>
        <CmsPageState :state="problemExtensions.length ? 'ready' : 'empty'" empty-icon="circle-check" :empty-title="t('dashboard.all_stable')" :empty-message="t('dashboard.no_problem_extensions')">
          <div class="cms-stack">
            <div v-for="item in problemExtensions.slice(0, 5)" :key="item.id" class="cms-list-card">
              <div><strong>{{ item.name }}</strong><small>{{ item.status }}</small></div>
              <div class="cms-card-actions"><LBadge severity="danger">{{ item.type }}</LBadge><LButton size="sm" variant="outline" shape="rounded" @click="go('/extensions')">{{ t('dashboard.manage') }}</LButton></div>
            </div>
          </div>
        </CmsPageState>
      </LPanel>
    </div>
  </LPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import SafeModeBanner from '../../components/safe-mode-banner.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { contentApi, extensionApi, mediaApi, systemApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const boot = readAdminBootData()
const router = useRouter()
const extensions = ref([])
const health = ref([])
const logsSummary = ref({})
const loading = ref(false)
const error = ref('')
const summary = reactive({ content: 0, media: 0, extensions: 0, problems: 0 })

const problemExtensions = computed(() =>
  extensions.value.filter((item) => ['failed', 'quarantined', 'incompatible'].includes(item.status)),
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const [content, media, extension, healthResponse, logs] = await Promise.all([
      contentApi.list({ limit: 1, projection: 'list' }),
      mediaApi.list({ limit: 1 }),
      extensionApi.list(),
      systemApi.health(),
      systemApi.logs({ limit: 1, active_window: 900 }),
    ])

    const contentData = content.data || {}
    const mediaData = media.data || {}
    extensions.value = extension.data?.items || []
    health.value = healthResponse.data?.checks || []
    logsSummary.value = logs.data?.summary || {}

    summary.content = Number(contentData.pagination?.total || 0)
    summary.media = Number(mediaData.summary?.total || 0)
    summary.extensions = extensions.value.length

    const failedHealth = health.value.filter((item) => ['error', 'fail'].includes(item.status)).length
    summary.problems =
      problemExtensions.value.length
      + failedHealth
      + Number(logsSummary.value.active_errors || 0)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const go = (path) => router.push(path)
function severity(status) {
  if (status === 'ok' || status === 'healthy' || status === 'pass') return 'success'
  if (status === 'warning') return 'warning'
  return 'danger'
}

onMounted(load)
</script>
