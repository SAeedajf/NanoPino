<template>
  <LPage icon="braces" class="cms-developer-sdk">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="SDK" :value="sdk.version" icon="braces" />
      <LStatCard :label="t('sdk_page.extension_types')" :value="sdk.extensionTypes.length" icon="puzzle" />
      <LStatCard :label="t('sdk_page.kernel_minimum')" :value="`#${sdk.minimum.kernelCode}`" icon="box" />
    </div>

    <LPanel>
      <template #header>{{ t('sdk_page.starter_generator') }}</template>
      <p class="cms-muted">{{ t('sdk_page.starter_generator_help') }}</p>

      <div class="cms-control-form">
        <label>{{ t('sdk_page.type') }}
          <select v-model="starter.type">
            <option v-for="type in sdk.extensionTypes" :key="type" :value="type">{{ typeLabel(type) }}</option>
          </select>
        </label>
        <label>{{ t('sdk_page.package') }}
          <input v-model.trim="starter.package" type="text" dir="ltr" autocomplete="off" placeholder="com_vendor_example">
        </label>
        <label>{{ t('sdk_page.name') }}
          <input v-model.trim="starter.name" type="text" autocomplete="off">
        </label>
        <label>{{ t('sdk_page.publisher') }}
          <input v-model.trim="starter.publisher" type="text" dir="ltr" autocomplete="off">
        </label>
        <label>{{ t('sdk_page.version') }}
          <input v-model.trim="starter.version" type="text" dir="ltr" autocomplete="off">
        </label>
        <label>{{ t('sdk_page.version_code') }}
          <input v-model.number="starter.version_code" type="number" min="1" max="2147483647" inputmode="numeric">
        </label>
        <label v-if="starter.type==='theme'">{{ t('sdk_page.target_app') }}
          <input v-model.trim="starter.target_app" type="text" dir="ltr" autocomplete="off">
        </label>
        <label v-if="starter.type==='theme'">{{ t('sdk_page.theme_name') }}
          <input v-model.trim="starter.theme_name" type="text" dir="ltr" autocomplete="off" placeholder="example">
        </label>
        <label class="cms-control-form__wide">{{ t('sdk_page.description') }}
          <textarea v-model.trim="starter.description" rows="3"></textarea>
        </label>
      </div>

      <div class="cms-card-actions">
        <LButton icon="package-plus" shape="rounded" :disabled="generating || !canGenerate" @click="generateStarter">
          {{ generating ? t('sdk_page.generating') : t('sdk_page.generate_starter') }}
        </LButton>
      </div>
      <p v-if="generatorError" class="cms-alert cms-alert--danger" role="alert">{{ generatorError }}</p>

      <div v-if="starterResult" class="cms-stack">
        <article class="cms-list-card cms-list-card--wide">
          <div>
            <strong>{{ starterResult.starter?.name }}</strong>
            <small>{{ starterResult.starter?.package }} · {{ typeLabel(starterResult.starter?.type) }} · {{ starterResult.starter?.version }}</small>
          </div>
          <div class="cms-card-actions">
            <LButton v-if="starterResult.archive" icon="download" size="sm" shape="rounded" @click="downloadArchive">{{ t('sdk_page.download_zip') }}</LButton>
            <LBadge v-else severity="warning">{{ t('sdk_page.zip_unavailable') }}</LBadge>
          </div>
        </article>

        <LPanel flush bare>
          <template #header>{{ t('sdk_page.generated_files') }}</template>
          <div class="cms-stack">
            <article v-for="file in starterResult.files || []" :key="file.path" class="cms-list-card cms-list-card--wide">
              <div>
                <strong><code>{{ file.path }}</code></strong>
                <small>{{ file.bytes }} B · SHA-256 {{ shortHash(file.sha256) }}</small>
              </div>
              <LButton variant="outline" severity="neutral" size="sm" shape="rounded" icon="download" @click="downloadSource(file)">{{ t('sdk_page.download_file') }}</LButton>
            </article>
          </div>
        </LPanel>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.start_steps') }}</template>
      <ol class="cms-sdk-steps">
        <li>{{ t('sdk_page.step_generate') }}</li>
        <li>{{ t('sdk_page.step_customize') }}</li>
        <li>{{ t('sdk_page.step_test') }}</li>
        <li>{{ t('sdk_page.step_build') }}</li>
        <li>{{ t('sdk_page.step_install') }}</li>
      </ol>
      <p class="cms-muted">{{ t('sdk_page.shared_hosting_note') }}</p>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.architecture_contract') }}</template>
      <div class="cms-sdk-contract-grid">
        <article>
          <LBadge severity="success">Pinoox Native</LBadge>
          <strong>AppRegister</strong>
          <small>{{ t('sdk_page.native_note') }}</small>
        </article>
        <article>
          <LBadge severity="info">CMS SDK</LBadge>
          <strong>Registry-based</strong>
          <small>{{ t('sdk_page.registry_note') }}</small>
        </article>
        <article>
          <LBadge severity="success">{{ t('sdk_page.ownership') }}</LBadge>
          <strong>{{ t('sdk_page.owner_enforced') }}</strong>
          <small>{{ t('sdk_page.ownership_note') }}</small>
        </article>
        <article>
          <LBadge :severity="sdk.coreEdits ? 'danger' : 'success'">
            {{ sdk.coreEdits ? t('sdk_page.core_edit_detected') : t('sdk_page.no_core_edits') }}
          </LBadge>
          <strong>{{ t('sdk_page.core_stable') }}</strong>
          <small>{{ t('sdk_page.core_note') }}</small>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.compatibility') }}</template>
      <div class="cms-sdk-minimums">
        <div><span>PHP</span><strong>{{ sdk.minimum.php }}</strong></div>
        <div><span>Pincore</span><strong>{{ sdk.minimum.pincore }}</strong></div>
        <div><span>Kernel Code</span><strong>{{ sdk.minimum.kernelCode }}</strong></div>
        <div><span>CMS</span><strong>{{ sdk.minimum.cms }}</strong></div>
        <div><span>Luma</span><strong>{{ sdk.minimum.luma }}</strong></div>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.registries') }}</template>
      <div class="cms-capability-cloud">
        <LBadge v-for="registry in sdk.registries" :key="registry" severity="secondary">{{ registry }}</LBadge>
      </div>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.native_registration') }}</template>
      <div class="cms-capability-cloud">
        <LBadge v-for="method in sdk.nativeRegistration" :key="method" severity="info">{{ method }}</LBadge>
      </div>
      <p class="cms-muted">{{ t('sdk_page.loader_note') }}</p>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.extension_api') }}</template>
      <div class="cms-api-status">
        <LBadge severity="success">{{ t('sdk_page.canonical') }}</LBadge>
        <code>{{ sdk.apiBase }}</code>
      </div>
      <p class="cms-muted">{{ t('sdk_page.api_note') }}</p>
    </LPanel>

    <LPanel>
      <template #header>{{ t('sdk_page.quality_gates') }}</template>
      <div class="cms-sdk-quality">
        <div><LBadge :severity="sdk.packageValidator ? 'success' : 'danger'">{{ t('sdk_page.package_validator') }}</LBadge><span>{{ t('sdk_page.package_validator_note') }}</span></div>
        <div><LBadge :severity="sdk.testHarness ? 'success' : 'danger'">{{ t('sdk_page.test_harness') }}</LBadge><span>{{ t('sdk_page.test_harness_note') }}</span></div>
        <div><LBadge severity="success">{{ t('sdk_page.migration') }}</LBadge><span>{{ t('sdk_page.migration_note') }}</span></div>
        <div><LBadge severity="success">PINX</LBadge><span>{{ t('sdk_page.pinx_note') }}</span></div>
      </div>
    </LPanel>
  </LPage>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { LBadge, LButton, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import { readAdminBootData } from '../../services/admin-provider.js'
import { developerApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const sdk = readAdminBootData().developerSdk
const generating = ref(false)
const generatorError = ref('')
const starterResult = ref(null)
const starter = reactive({
  type: sdk.extensionTypes.includes('plugin') ? 'plugin' : (sdk.extensionTypes[0] || 'plugin'),
  package: 'com_example_plugin',
  name: 'Example Plugin',
  publisher: 'example-developer',
  version: '0.1.0',
  version_code: 100,
  description: '',
  target_app: 'com_pinoox_cms',
  theme_name: 'example',
})

const canGenerate = computed(() =>
  sdk.extensionTypes.includes(starter.type)
  && /^com_[a-z0-9][a-z0-9_]*$/.test(starter.package)
  && starter.name.length > 0
  && /^[a-z0-9][a-z0-9._-]{1,126}$/.test(starter.publisher)
  && starter.version.length > 0
  && Number.isInteger(Number(starter.version_code))
  && Number(starter.version_code) > 0
  && (starter.type !== 'theme'
    || (/^com_[a-z0-9][a-z0-9_]*$/.test(starter.target_app)
      && /^[a-z0-9][a-z0-9_-]{0,63}$/.test(starter.theme_name)))
)

function typeLabel(value) {
  return ({
    module: t('extensions_page.type_module'),
    plugin: t('extensions_page.type_plugin'),
    integration: t('extensions_page.type_integration'),
    theme: t('extensions_page.type_theme'),
    'admin-extension': t('extensions_page.type_admin'),
    block: t('extensions_page.type_block'),
    'block-package': t('extensions_page.type_block_pack'),
    driver: t('extensions_page.type_driver'),
    'language-pack': t('extensions_page.type_language'),
  })[value] || value || '—'
}

async function generateStarter() {
  if (!canGenerate.value) return
  generating.value = true
  generatorError.value = ''
  starterResult.value = null
  try {
    const payload = { ...starter }
    if (payload.type !== 'theme') {
      delete payload.target_app
      delete payload.theme_name
    }
    const response = await developerApi.generateStarter(payload)
    starterResult.value = response.data || null
  } catch (error) {
    generatorError.value = error.message
  } finally {
    generating.value = false
  }
}

function shortHash(value) {
  const text = String(value || '')
  return text.length > 18 ? `${text.slice(0, 10)}…${text.slice(-8)}` : text
}

function saveBlob(blob, filename) {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = url
  anchor.download = filename
  anchor.rel = 'noopener'
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(url)
}

function downloadArchive() {
  const archive = starterResult.value?.archive
  if (!archive?.content || archive.encoding !== 'base64') return
  const raw = atob(archive.content)
  const bytes = new Uint8Array(raw.length)
  for (let index = 0; index < raw.length; index += 1) bytes[index] = raw.charCodeAt(index)
  saveBlob(new Blob([bytes], { type: archive.mime || 'application/zip' }), archive.filename || 'nanopino-starter.zip')
}

function downloadSource(file) {
  if (!file?.path || typeof file.content !== 'string') return
  const filename = file.path.split('/').pop() || 'starter.txt'
  saveBlob(new Blob([file.content], { type: 'text/plain;charset=utf-8' }), filename)
}
</script>
