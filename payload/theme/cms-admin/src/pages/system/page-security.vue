<template>
  <LPage icon="shield" class="cms-security-page">
    <template #actions><LButton icon="refresh-cw" variant="outline" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></template>
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>

    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="PASS" :value="center.posture.counts?.pass || 0" icon="shield-check" />
      <LStatCard label="WARNING" :value="center.posture.counts?.warning || 0" icon="triangle-alert" />
      <LStatCard label="FAIL" :value="center.posture.counts?.fail || 0" icon="shield-x" />
    </div>

    <LPanel>
      <template #header>Security Posture</template>
      <div class="cms-security-posture"><LBadge :severity="postureSeverity(center.posture.status)">{{ String(center.posture.status || 'warning').toUpperCase() }}</LBadge><span>{{ t('security_page.posture_note') }}</span></div>
      <div class="cms-security-control-grid">
        <article v-for="control in center.posture.controls || []" :key="control.id">
          <div class="cms-security-control-title"><strong>{{ control.label }}</strong><LBadge :severity="postureSeverity(control.status)">{{ control.status }}</LBadge></div>
          <small>{{ control.id }}</small><p>{{ control.message }}</p>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Runtime Gates</template>
      <div class="cms-security-gates">
        <div><span>CSRF Verifier</span><LBadge :severity="center.csrfBound ? 'success' : 'warning'">{{ center.csrfBound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Rate Limits</span><LBadge :severity="center.rateLimitsRegistered ? 'success' : 'warning'">{{ center.rateLimitsRegistered ? 'Registered' : 'Unbound' }}</LBadge></div>
        <div><span>Security Headers</span><LBadge :severity="center.headersBound ? 'success' : 'warning'">{{ center.headersBound ? 'Bound' : center.cspMode }}</LBadge></div>
        <div><span>SSRF Transport</span><LBadge :severity="center.ssrfTransportBound ? 'success' : 'warning'">{{ center.ssrfTransportBound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Implicit Platform Super</span><LBadge :severity="center.platformSuperTransition?.platform_super ? 'warning' : 'success'">{{ center.platformSuperTransition?.platform_super ? 'Enabled' : 'Disabled' }}</LBadge></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Platform Super Transition</template>
      <div class="cms-security-gates">
        <div><span>Platform accounts</span><strong>{{ center.platformSuperTransition?.total_platform_accounts ?? '—' }}</strong></div>
        <div><span>Explicit super accounts</span><strong>{{ center.platformSuperTransition?.explicit_super_accounts ?? '—' }}</strong></div>
        <div><span>Implicit-only accounts</span><strong>{{ center.platformSuperTransition?.implicit_only_accounts ?? '—' }}</strong></div>
        <div><span>Transition readiness</span><LBadge :severity="center.platformSuperTransition?.ready ? 'success' : 'warning'">{{ center.platformSuperTransition?.ready ? 'Ready for controlled cutover' : 'Not safe to disable' }}</LBadge></div>
      </div>
      <p class="cms-muted">NanoPino does not disable <code>platform_super</code> automatically. A controlled cutover is allowed only after every detected platform account has an explicit super role/group and the target admin session is verified.</p>
    </LPanel>

    <LPanel>
      <template #header>Rate Limit Profiles</template>
      <div class="cms-mobile-only cms-driver-cards"><article v-for="profile in center.rateLimits" :key="profile.name" class="cms-list-card cms-list-card--wide"><div><strong>{{ profile.name }}</strong><small>{{ profile.maxAttempts }} / {{ profile.decaySeconds }}s</small></div><LBadge severity="secondary">{{ profile.keyStrategy }}</LBadge></article></div>
      <LPanel flush bare class="cms-desktop-only"><LDataTable :value="center.rateLimits" data-key="name"><Column field="name" header="Profile" /><Column field="maxAttempts" header="Attempts" /><Column field="decaySeconds" header="Window (s)" /><Column field="keyStrategy" header="Key" /></LDataTable></LPanel>
    </LPanel>

    <LPanel>
      <template #header>API Security Matrix</template>
      <div class="cms-security-matrix"><article v-for="(row, name) in center.apiMatrix" :key="name"><div><strong>{{ name }}</strong><small>{{ row.scope }}</small></div><div class="cms-capability-cloud"><LBadge severity="info">{{ row.rateLimit }}</LBadge><LBadge :severity="row.sessionMutationRequiresCsrf ? 'warning' : 'secondary'">CSRF {{ row.sessionMutationRequiresCsrf ? 'required' : 'safe-read' }}</LBadge><LBadge :severity="row.siteScopeRequired ? 'success' : 'secondary'">{{ row.siteScopeRequired ? 'Site Scope' : 'Global/System' }}</LBadge></div></article></div>
    </LPanel>
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
    center.cspMode = data.runtime?.csp_mode || center.cspMode
    center.platformSuperTransition = data.platform_super_transition || center.platformSuperTransition
    center.rateLimits = Array.isArray(data.rate_limits) ? data.rate_limits : []
    center.apiMatrix = data.api_matrix || {}
    center.apiBound = true
  } catch (e) { error.value = e.message } finally { loading.value = false }
}
function postureSeverity(status) { if (status === 'pass') return 'success'; if (status === 'fail') return 'danger'; return 'warning' }
onMounted(load)
</script>
