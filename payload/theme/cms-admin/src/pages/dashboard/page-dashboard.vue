<template>
  <LPage icon="layout-dashboard" header-tone="gradient">
    <SafeModeBanner :safe-mode="data.safeMode" />
    <div class="cms-stat-grid" :aria-label="t('dashboard.summary_aria')">
      <LStatCard :label="t('dashboard.content')" :value="data.summary.content" icon="files" />
      <LStatCard :label="t('dashboard.media')" :value="data.summary.media" icon="image" />
      <LStatCard :label="t('dashboard.extensions')" :value="data.summary.extensions" icon="blocks" />
      <LStatCard :label="t('dashboard.attention')" :value="data.summary.problems" icon="triangle-alert" />
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
        <CmsPageState :state="healthState">
          <ul class="cms-health-list">
            <li v-for="item in data.health.slice(0, 6)" :key="item.id" class="cms-health-row">
              <span>{{ item.label }}</span><LBadge :severity="severity(item.status)">{{ item.status }}</LBadge>
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
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import SafeModeBanner from '../../components/safe-mode-banner.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'
const data = readAdminBootData()
const router = useRouter()
const problemExtensions = computed(() => data.extensions.filter((item) => ['failed', 'quarantined', 'incompatible'].includes(item.status)))
const healthState = computed(() => data.health.length ? 'ready' : 'empty')
const go = (path) => router.push(path)
function severity(status) { if (status === 'ok' || status === 'healthy') return 'success'; if (status === 'warning') return 'warn'; return 'danger' }
</script>
