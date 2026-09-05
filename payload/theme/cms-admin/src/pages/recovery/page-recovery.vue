<template>
  <LPage icon="shield-check" class="cms-recovery-center">
    <SafeModeBanner :safe-mode="data.safeMode" />
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard :label="t('recovery_page.recovery_point')" :value="data.recoveryPoints.length" icon="history" />
      <LStatCard :label="t('recovery_page.quarantine')" :value="data.safeMode.quarantined?.length || 0" icon="shield-alert" />
      <LStatCard :label="t('recovery_page.safe_mode')" :value="data.safeMode.enabled ? t('recovery_page.enabled') : t('recovery_page.disabled')" icon="shield-check" />
    </div>
    <LPanel>
      <template #header>{{ t('recovery_page.policy') }}</template>
      <div class="cms-recovery-policy-grid">
        <article><LBadge severity="info">{{ t('recovery_page.update') }}</LBadge><strong>{{ t('recovery_page.snapshot_required') }}</strong><small>{{ t('recovery_page.update_note') }}</small></article>
        <article><LBadge severity="warning">{{ t('recovery_page.uninstall') }}</LBadge><strong>{{ center.uninstallSnapshotRequired ? t('recovery_page.snapshot_required') : t('recovery_page.optional') }}</strong><small>{{ t('recovery_page.uninstall_note') }}</small></article>
        <article><LBadge severity="secondary">{{ t('recovery_page.repair') }}</LBadge><strong>{{ center.repairSnapshotRequired ? t('recovery_page.snapshot_required') : t('recovery_page.optional') }}</strong><small>{{ t('recovery_page.repair_note') }}</small></article>
      </div>
    </LPanel>
    <LPanel>
      <template #header>{{ t('recovery_page.points') }}</template>
      <CmsPageState :state="data.recoveryPoints.length ? 'ready' : 'empty'" empty-icon="history" :empty-title="t('recovery_page.empty_title')" :empty-message="t('recovery_page.empty_message')">
        <div class="cms-stack">
          <article v-for="point in data.recoveryPoints" :key="point.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ point.extensionId || point.extension_id }}</strong><small>{{ point.id }} · {{ point.operation }}</small><small v-if="point.safeModeTarget">{{ t('recovery_page.safe_mode_target') }}</small></div>
            <div class="cms-card-actions"><LBadge :severity="point.status === 'ready' ? 'success' : 'warning'">{{ point.status }}</LBadge><LButton variant="outline" severity="neutral" size="sm" shape="rounded" :disabled="!center.apiBound || point.restorable === false" @click="requestRestore(point)">{{ t('recovery_page.restore') }}</LButton></div>
          </article>
        </div>
      </CmsPageState>
    </LPanel>
    <LPanel>
      <template #header>{{ t('recovery_page.safe_mode_control') }}</template>
      <p class="cms-muted">{{ t('recovery_page.safe_mode_explanation') }}</p>
      <div class="luma-actions">
        <LButton v-if="data.safeMode.enabled" variant="outline" severity="danger" shape="rounded" :disabled="!center.apiBound" @click="requestSafeModeDisable">{{ t('recovery_page.disable_request') }}</LButton>
        <LBadge v-else severity="success">{{ t('recovery_page.safe_mode_off') }}</LBadge>
      </div>
      <small v-if="!center.apiBound" class="cms-muted">{{ t('recovery_page.api_unbound') }}</small>
      <p v-if="actionMessage" class="cms-action-message" role="status" aria-live="polite">{{ actionMessage }}</p>
    </LPanel>
    <LPanel>
      <template #header>{{ t('recovery_page.api_title') }}</template>
      <div class="cms-api-status"><LBadge :severity="center.apiBound ? 'success' : 'warning'">{{ center.apiBound ? t('recovery_page.bound') : t('recovery_page.contract_only') }}</LBadge><span>/api/v1/cms/recovery</span></div>
      <div class="cms-contract-list cms-contract-list--api"><div v-for="route in center.api" :key="`${route.method}:${route.path}`" class="cms-contract-row"><LBadge severity="secondary">{{ route.method }}</LBadge><code>{{ route.path }}</code><small>{{ route.capability }}</small></div></div>
    </LPanel>
  </LPage>
</template>
<script setup>
import { ref } from 'vue'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import SafeModeBanner from '../../components/safe-mode-banner.vue'
import { performAdminAction, readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'
const data = readAdminBootData()
const center = data.recoveryCenter
const actionMessage = ref('')
async function requestRestore(point) { try { await performAdminAction('restoreRecoveryPoint', { id: point.id }); actionMessage.value = t('recovery_page.restore_requested') } catch (error) { actionMessage.value = error.message } }
async function requestSafeModeDisable() { try { await performAdminAction('disableSafeMode'); actionMessage.value = t('recovery_page.disable_requested') } catch (error) { actionMessage.value = error.message } }
</script>
