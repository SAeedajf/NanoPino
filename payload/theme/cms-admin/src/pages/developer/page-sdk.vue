<template>
  <LPage icon="braces" class="cms-developer-sdk">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="SDK" :value="sdk.version" icon="braces" />
      <LStatCard label="Extension Types" :value="sdk.extensionTypes.length" icon="puzzle" />
      <LStatCard label="Kernel Minimum" :value="`#${sdk.minimum.kernelCode}`" icon="box" />
    </div>

    <LPanel>
      <template #header>Extension Architecture Contract</template>
      <div class="cms-sdk-contract-grid">
        <article>
          <LBadge severity="success">Pinoox Native</LBadge>
          <strong>AppRegister remains Runtime unit</strong>
          <small>{{ t('sdk_page.native_note') }}</small>
        </article>
        <article>
          <LBadge severity="info">CMS SDK</LBadge>
          <strong>Registry-based semantic extensions</strong>
          <small>{{ t('sdk_page.registry_note') }}</small>
        </article>
        <article>
          <LBadge severity="success">Ownership</LBadge>
          <strong>Owner enforced</strong>
          <small>{{ t('sdk_page.ownership_note') }}</small>
        </article>
        <article>
          <LBadge :severity="sdk.coreEdits ? 'danger' : 'success'">
            {{ sdk.coreEdits ? 'Core edit detected' : 'No Core edits' }}
          </LBadge>
          <strong>Core stays stable</strong>
          <small>{{ t('sdk_page.core_note') }}</small>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Compatibility</template>
      <div class="cms-sdk-minimums">
        <div><span>PHP</span><strong>{{ sdk.minimum.php }}</strong></div>
        <div><span>Pincore</span><strong>{{ sdk.minimum.pincore }}</strong></div>
        <div><span>Kernel Code</span><strong>{{ sdk.minimum.kernelCode }}</strong></div>
        <div><span>CMS</span><strong>{{ sdk.minimum.cms }}</strong></div>
        <div><span>Luma</span><strong>{{ sdk.minimum.luma }}</strong></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Starter Packages</template>
      <div class="cms-sdk-starters">
        <article v-for="starter in sdk.starters" :key="starter.id" class="cms-list-card cms-list-card--wide">
          <div>
            <strong>{{ starter.label }}</strong>
            <code>{{ starter.path }}</code>
          </div>
          <LBadge severity="secondary">Source included</LBadge>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>CMS Registries</template>
      <div class="cms-capability-cloud">
        <LBadge v-for="registry in sdk.registries" :key="registry" severity="secondary">
          {{ registry }}
        </LBadge>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Pinoox Native Registration</template>
      <div class="cms-capability-cloud">
        <LBadge v-for="method in sdk.nativeRegistration" :key="method" severity="info">
          {{ method }}
        </LBadge>
      </div>
      <p class="cms-muted">
        {{ t('sdk_page.loader_note') }}
      </p>
    </LPanel>

    <LPanel>
      <template #header>Extension API</template>
      <div class="cms-api-status">
        <LBadge severity="success">Canonical</LBadge>
        <code>{{ sdk.apiBase }}</code>
      </div>
      <p class="cms-muted">
        {{ t('sdk_page.api_note') }}
      </p>
    </LPanel>

    <LPanel>
      <template #header>Developer Quality Gates</template>
      <div class="cms-sdk-quality">
        <div>
          <LBadge :severity="sdk.packageValidator ? 'success' : 'danger'">Package Validator</LBadge>
          <span>Manifest + package layout + Core modification guard</span>
        </div>
        <div>
          <LBadge :severity="sdk.testHarness ? 'success' : 'danger'">Test Harness</LBadge>
          <span>Portable registry/native registration tests</span>
        </div>
        <div>
          <LBadge severity="success">Migration</LBadge>
          <span>Pinoox MigrationBase + package-aware DB connection</span>
        </div>
        <div>
          <LBadge severity="success">PINX</LBadge>
          <span>{{ t('sdk_page.pinx_note') }}</span>
        </div>
      </div>
    </LPanel>
  </LPage>
</template>

<script setup>
import { LBadge, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { t } from '../../i18n/index.js'

const sdk = readAdminBootData().developerSdk
</script>
