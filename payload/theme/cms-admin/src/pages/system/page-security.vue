<template>
  <LPage icon="shield" class="cms-security-page">
    <template #actions><LButton icon="refresh-cw" variant="outline" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></template>
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert" aria-live="assertive">{{ error }}</div>

    <div class="cms-security-content" :aria-busy="loading ? 'true' : 'false'">
      <div class="cms-stat-grid cms-stat-grid--three">
        <LStatCard :label="t('security_page.pass')" :value="center.posture.counts?.pass || 0" icon="shield-check" />
        <LStatCard :label="t('security_page.warning')" :value="center.posture.counts?.warning || 0" icon="triangle-alert" />
        <LStatCard :label="t('security_page.fail')" :value="center.posture.counts?.fail || 0" icon="shield-x" />
      </div>

      <LPanel>
        <template #header>{{ t('security_page.posture_title') }}</template>
        <div class="cms-security-posture"><LBadge :severity="postureSeverity(center.posture.status)">{{ statusLabel(center.posture.status) }}</LBadge><span>{{ postureNote(center.posture.status) }}</span></div>
        <div class="cms-security-control-grid">
          <article v-for="control in center.posture.controls || []" :key="control.id">
            <div class="cms-security-control-title"><strong>{{ control.label }}</strong><LBadge :severity="postureSeverity(control.status)">{{ statusLabel(control.status) }}</LBadge></div>
            <small>{{ control.id }}</small><p>{{ control.message }}</p>
          </article>
        </div>
      </LPanel>

      <LPanel>
        <template #header>{{ t('security_page.runtime_gates') }}</template>
        <div class="cms-security-gates">
          <div><span>{{ t('security_page.csrf_verifier') }}</span><LBadge :severity="center.csrfBound ? 'success' : 'warning'">{{ center.csrfBound ? t('security_page.bound') : t('security_page.unbound') }}</LBadge></div>
          <div><span>{{ t('security_page.rate_limits') }}</span><LBadge :severity="center.rateLimitsRegistered ? 'success' : 'warning'">{{ center.rateLimitsRegistered ? t('security_page.registered') : t('security_page.unbound') }}</LBadge></div>
          <div><span>{{ t('security_page.security_headers') }}</span><LBadge :severity="center.headersBound ? 'success' : 'warning'">{{ center.headersBound ? t('security_page.bound') : center.cspMode }}</LBadge></div>
          <div><span>{{ t('security_page.ssrf_transport') }}</span><LBadge :severity="center.ssrfTransportBound || center.ssrfGuardReady ? 'success' : 'warning'">{{ center.ssrfTransportBound ? t('security_page.bound') : center.ssrfGuardReady ? t('security_page.database_fallback') : t('security_page.unbound') }}</LBadge></div>
          <div><span>{{ t('security_page.implicit_platform_super') }}</span><LBadge :severity="center.platformSuperTransition?.platform_super ? 'warning' : 'success'">{{ center.platformSuperTransition?.platform_super ? t('security_page.enabled') : t('security_page.disabled') }}</LBadge></div>
        </div>
      </LPanel>

      <LPanel>
        <template #header>{{ t('security_page.platform_super_transition') }}</template>
        <div class="cms-security-gates">
          <div><span>{{ t('security_page.platform_accounts') }}</span><strong>{{ center.platformSuperTransition?.total_platform_accounts ?? '—' }}</strong></div>
          <div><span>{{ t('security_page.explicit_super_accounts') }}</span><strong>{{ center.platformSuperTransition?.explicit_super_accounts ?? '—' }}</strong></div>
          <div><span>{{ t('security_page.implicit_only_accounts') }}</span><strong>{{ center.platformSuperTransition?.implicit_only_accounts ?? '—' }}</strong></div>
          <div><span>{{ t('security_page.transition_readiness') }}</span><LBadge :severity="transitionSeverity(center.platformSuperTransition)">{{ transitionLabel(center.platformSuperTransition) }}</LBadge></div>
        </div>
        <p class="cms-muted">{{ transitionNote(center.platformSuperTransition) }}</p>
      </LPanel>

      <LPanel>
        <template #header>{{ t('security_page.rate_limit_profiles') }}</template>
        <div class="cms-mobile-only cms-driver-cards"><article v-for="profile in center.rateLimits" :key="profile.name" class="cms-list-card cms-list-card--wide"><div><strong>{{ profile.name }}</strong><small>{{ profile.maxAttempts }} / {{ profile.decaySeconds }}s</small></div><LBadge severity="secondary">{{ profile.keyStrategy }}</LBadge></article></div>
        <LPanel flush bare class="cms-desktop-only"><LDataTable :value="center.rateLimits" data-key="name"><Column field="name" :header="t('security_page.profile')" /><Column field="maxAttempts" :header="t('security_page.attempts')" /><Column field="decaySeconds" :header="t('security_page.window_seconds')" /><Column field="keyStrategy" :header="t('security_page.key')" /></LDataTable></LPanel>
      </LPanel>

      <LPanel>
        <template #header>{{ t('security_page.api_security_matrix') }}</template>
        <div class="cms-security-matrix"><article v-for="(row, name) in center.apiMatrix" :key="name"><div><strong>{{ name }}</strong><small>{{ row.scope }}</small></div><div class="cms-capability-cloud"><LBadge severity="info">{{ row.rateLimit }}</LBadge><LBadge :severity="row.sessionMutationRequiresCsrf ? 'warning' : 'secondary'">{{ row.sessionMutationRequiresCsrf ? t('security_page.csrf_required') : t('security_page.safe_read') }}</LBadge><LBadge :severity="row.siteScopeRequired ? 'success' : 'secondary'">{{ row.siteScopeRequired ? t('security_page.site_scope') : t('security_page.global_system') }}</LBadge></div></article></div>
      </LPanel>
    </div>
  </LPage>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import Column from 'primevue/column'
import { LBadge, LButton, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { securityApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const boot = readAdminBootData().securityCenter
const center = reactive({ ...boot, posture: { ...(boot.posture || {}), counts: { ...(boot.posture?.counts || {}) }, controls: [...(boot.posture?.controls || [])] }, rateLimits: [...(boot.rateLimits || [])], apiMatrix: { ...(boot.apiMatrix || {}) }, platformSuperTransition: boot.platformSuperTransition || null })
const loading = ref(false)
const error = ref('')

async function load() {
  loading.value = true; error.value = ''
  try {
    const response = await securityApi.status()
    const data = response.data || {}
    center.posture = data.posture || center.posture
    center.csrfBound = Boolean(data.runtime?.csrf)
    center.rateLimitsRegistered = Boolean(data.runtime?.rate_limits)
    center.headersBound = Boolean(data.runtime?.security_headers)
    center.ssrfTransportBound = Boolean(data.runtime?.ssrf_transport)
    center.ssrfGuardReady = Boolean(data.runtime?.ssrf_guard_ready)
    center.cspMode = data.runtime?.csp_mode || center.cspMode
    center.platformSuperTransition = data.platform_super_transition || center.platformSuperTransition
    center.rateLimits = Array.isArray(data.rate_limits) ? data.rate_limits : []
    center.apiMatrix = data.api_matrix || {}
    center.apiBound = true
  } catch (e) { error.value = e.message } finally { loading.value = false }
}
function postureSeverity(status) { if (status === 'pass') return 'success'; if (status === 'fail') return 'danger'; return 'warning' }
function statusLabel(status) { return t({ pass: 'security_page.pass', warning: 'security_page.warning', fail: 'security_page.fail' }[status] || 'security_page.unknown') }
function postureNote(status) { return status === 'pass' ? t('security_page.posture_healthy') : t('security_page.posture_note') }
function transitionLabel(transition) {
  if (transition?.cutover_complete) return t('security_page.cutover_complete')
  if (transition?.ready) return t('security_page.ready_for_cutover')
  return t('security_page.not_safe_to_disable')
}
function transitionSeverity(transition) { return transition?.cutover_complete || transition?.ready ? 'success' : 'warning' }
function transitionNote(transition) {
  if (transition?.cutover_complete) return t('security_page.cutover_note')
  return t('security_page.cutover_guard_note')
}
onMounted(load)
</script>
