<template>
  <LPage icon="shield" class="cms-security-page">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="PASS" :value="center.posture.counts.pass || 0" icon="shield-check" />
      <LStatCard label="WARNING" :value="center.posture.counts.warning || 0" icon="triangle-alert" />
      <LStatCard label="FAIL" :value="center.posture.counts.fail || 0" icon="shield-x" />
    </div>

    <LPanel>
      <template #header>Security Posture</template>
      <div class="cms-security-posture">
        <LBadge :severity="postureSeverity(center.posture.status)">
          {{ String(center.posture.status || 'warning').toUpperCase() }}
        </LBadge>
        <span>{{ t('security_page.posture_note') }}</span>
      </div>

      <div class="cms-security-control-grid">
        <article v-for="control in center.posture.controls" :key="control.id">
          <div class="cms-security-control-title">
            <strong>{{ control.label }}</strong>
            <LBadge :severity="postureSeverity(control.status)">{{ control.status }}</LBadge>
          </div>
          <small>{{ control.id }}</small>
          <p>{{ control.message }}</p>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Native Pinoox Security Reuse</template>
      <div class="cms-security-native-grid">
        <article>
          <LBadge severity="success">REUSE</LBadge>
          <strong>{{ center.native.rateLimiter }}</strong>
          <small>{{ t('security_page.rate_limit_native') }}</small>
        </article>
        <article>
          <LBadge severity="success">REUSE</LBadge>
          <strong>{{ center.native.responseEvent }}</strong>
          <small>{{ t('security_page.headers_native') }}</small>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Runtime Gates</template>
      <div class="cms-security-gates">
        <div><span>CSRF Verifier</span><LBadge :severity="center.csrfBound ? 'success' : 'warning'">{{ center.csrfBound ? 'Bound' : 'Unbound' }}</LBadge></div>
        <div><span>Rate Limits</span><LBadge :severity="center.rateLimitsRegistered ? 'success' : 'warning'">{{ center.rateLimitsRegistered ? 'Registered' : 'Contract' }}</LBadge></div>
        <div><span>Security Headers</span><LBadge :severity="center.headersBound ? 'success' : 'warning'">{{ center.headersBound ? 'Bound' : center.cspMode }}</LBadge></div>
        <div><span>SSRF Transport</span><LBadge :severity="center.ssrfTransportBound ? 'success' : 'warning'">{{ center.ssrfTransportBound ? 'Bound' : 'Unbound' }}</LBadge></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Rate Limit Profiles</template>

      <div class="cms-mobile-only cms-driver-cards">
        <article v-for="profile in center.rateLimits" :key="profile.name" class="cms-list-card cms-list-card--wide">
          <div>
            <strong>{{ profile.name }}</strong>
            <small>{{ profile.maxAttempts }} / {{ profile.decaySeconds }}s</small>
          </div>
          <LBadge severity="secondary">{{ profile.keyStrategy }}</LBadge>
        </article>
      </div>

      <LPanel flush bare class="cms-desktop-only">
        <LDataTable :value="center.rateLimits" data-key="name">
          <Column field="name" header="Profile" />
          <Column field="maxAttempts" header="Attempts" />
          <Column field="decaySeconds" header="Window (s)" />
          <Column field="keyStrategy" header="Key" />
        </LDataTable>
      </LPanel>
    </LPanel>

    <LPanel>
      <template #header>API Security Matrix</template>
      <div class="cms-security-matrix">
        <article v-for="(row, name) in center.apiMatrix" :key="name">
          <div>
            <strong>{{ name }}</strong>
            <small>{{ row.scope }}</small>
          </div>
          <div class="cms-capability-cloud">
            <LBadge severity="info">{{ row.rateLimit }}</LBadge>
            <LBadge :severity="row.sessionMutationRequiresCsrf ? 'warning' : 'secondary'">
              CSRF {{ row.sessionMutationRequiresCsrf ? 'required' : 'safe-read' }}
            </LBadge>
            <LBadge :severity="row.siteScopeRequired ? 'success' : 'secondary'">
              {{ row.siteScopeRequired ? 'Site Scope' : 'Global/System' }}
            </LBadge>
          </div>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Security API v1</template>
      <div class="cms-api-status">
        <LBadge :severity="center.apiBound ? 'success' : 'warning'">
          {{ center.apiBound ? 'Bound' : 'Contract only' }}
        </LBadge>
        <span>/api/v1/cms/system/security</span>
      </div>
      <div class="cms-contract-list cms-contract-list--api">
        <div v-for="route in center.api" :key="`${route.method}:${route.path}`" class="cms-contract-row">
          <LBadge severity="secondary">{{ route.method }}</LBadge>
          <code>{{ route.path }}</code>
          <small>{{ route.capability }} · {{ route.rate_limit }}</small>
        </div>
      </div>
    </LPanel>
  </LPage>
</template>

<script setup>
import Column from 'primevue/column'
import { LBadge, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'

const center = readAdminBootData().securityCenter

function postureSeverity(status) {
  if (status === 'pass') return 'success'
  if (status === 'fail') return 'danger'
  return 'warning'
}
</script>
