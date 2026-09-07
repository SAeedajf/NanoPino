<template>
  <LPage icon="refresh-cw" class="cms-update-center">
    <template #actions>
      <div class="cms-card-actions">
        <select v-model="extensionId" :aria-label="t('updates_page.extension')">
          <option value="">{{ t('updates_page.select_extension') }}</option>
          <option v-for="item in extensions" :key="item.id" :value="item.id">{{ item.name || item.id }}</option>
        </select>
        <LButton icon="refresh-cw" variant="outline" :disabled="loading || !extensionId" @click="load">{{ t('common.refresh') }}</LButton>
        <LButton icon="save" :disabled="saving || !extensionId" @click="savePolicy">{{ saving ? t('updates_page.saving') : t('common.save') }}</LButton>
      </div>
    </template>

    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>
    <div v-if="notice" class="cms-alert cms-alert--success" aria-live="polite">{{ notice }}</div>

    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard :label="t('updates_page.policy')" :value="extensionId ? t('updates_page.loaded') : '—'" icon="sliders-horizontal" />
      <LStatCard :label="t('updates_page.history')" :value="history.length" icon="history" />
      <LStatCard :label="t('updates_page.recovery')" :value="recoveryPoints.length" icon="rotate-ccw" />
    </div>

    <LPanel>
      <template #header>{{ t('updates_page.policy') }}</template>
      <CmsPageState :state="loading ? 'loading' : extensionId ? 'ready' : 'empty'" :empty-title="t('updates_page.select_extension')" :empty-message="t('updates_page.select_extension_hint')">
        <div class="cms-control-form">
          <label>{{ t('updates_page.channel') }}
            <select v-model="policy.channel">
              <option v-for="channel in center.channels" :key="channel" :value="channel">{{ channelLabel(channel) }}</option>
            </select>
          </label>
          <label>{{ t('updates_page.auto_update') }}
            <select v-model="policy.auto_update">
              <option v-for="mode in center.autoUpdateModes" :key="mode" :value="mode">{{ autoModeLabel(mode) }}</option>
            </select>
          </label>
          <label class="cms-toggle-row"><input v-model="policy.require_signature" type="checkbox"><span>{{ t('updates_page.require_signature') }}</span></label>
          <label class="cms-toggle-row"><input v-model="policy.snapshot_before_update" type="checkbox"><span>{{ t('updates_page.snapshot_before') }}</span></label>
          <label class="cms-toggle-row"><input v-model="policy.health_check_required" type="checkbox"><span>{{ t('updates_page.health_required') }}</span></label>
          <label class="cms-toggle-row"><input v-model="policy.allow_downgrade" type="checkbox"><span>{{ t('updates_page.allow_downgrade') }}</span></label>
        </div>
      </CmsPageState>
    </LPanel>

    <LPanel>
      <template #header>{{ t('updates_page.history') }}</template>
      <CmsPageState :state="loading ? 'loading' : history.length ? 'ready' : 'empty'" :empty-title="t('updates_page.empty_title')" :empty-message="t('updates_page.empty_message')">
        <div class="cms-mobile-only cms-stack">
          <article v-for="item in history" :key="item.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ extensionName(item.extension_id) }}</strong><small>{{ item.from_version }} → {{ item.to_version }}</small><small>{{ formatTime(item.occurred_at) }}</small><small v-if="item.recovery_point_id">{{ t('updates_page.recovery_point',{id:item.recovery_point_id}) }}</small></div>
            <LBadge :severity="historySeverity(item.status)">{{ historyLabel(item.status) }}</LBadge>
          </article>
        </div>
        <LPanel flush bare class="cms-desktop-only">
          <LDataTable :value="history" data-key="id">
            <Column :header="t('updates_page.extension')"><template #body="{data:row}">{{ extensionName(row.extension_id) }}</template></Column>
            <Column field="from_version" :header="t('updates_page.from')" />
            <Column field="to_version" :header="t('updates_page.to')" />
            <Column :header="t('updates_page.result')"><template #body="{data:row}"><LBadge :severity="historySeverity(row.status)">{{ historyLabel(row.status) }}</LBadge></template></Column>
            <Column :header="t('updates_page.occurred')"><template #body="{data:row}">{{ formatTime(row.occurred_at) }}</template></Column>
            <Column :header="t('updates_page.recovery')"><template #body="{data:row}">{{ row.recovery_point_id || '—' }}</template></Column>
          </LDataTable>
        </LPanel>
      </CmsPageState>
    </LPanel>

    <LPanel>
      <template #header>{{ t('updates_page.recovery_points') }}</template>
      <CmsPageState :state="loading ? 'loading' : recoveryPoints.length ? 'ready' : 'empty'" empty-icon="rotate-ccw" :empty-title="t('recovery_page.empty_title')" :empty-message="t('recovery_page.empty_message')">
        <div class="cms-stack">
          <article v-for="item in recoveryPoints" :key="item.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ recoveryOperationLabel(item.operation) }}</strong><small>{{ formatTime(item.created_at) }}</small><details class="cms-muted"><summary>{{ t('updates_page.technical_details') }}</summary><code>{{ item.id }}</code></details></div>
            <LBadge :severity="item.status === 'ready' ? 'success' : 'warning'">{{ recoveryStatusLabel(item.status) }}</LBadge>
          </article>
        </div>
      </CmsPageState>
    </LPanel>
  </LPage>
</template>

<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import Column from 'primevue/column'
import { LBadge, LButton, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { extensionApi, updateApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const boot = readAdminBootData()
const center = boot.updateCenter
const extensions = ref([])
const extensionId = ref('')
const history = ref([])
const recoveryPoints = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const notice = ref('')
const policy = reactive(defaultPolicy())

function defaultPolicy() {
  return {
    channel: 'stable',
    auto_update: 'disabled',
    require_signature: false,
    allow_downgrade: false,
    snapshot_before_update: true,
    health_check_required: true,
  }
}

function applyPolicy(value = {}) {
  Object.assign(policy, defaultPolicy(), value || {})
}

async function loadExtensions() {
  const response = await extensionApi.list()
  extensions.value = response.data?.items || []
  if (!extensionId.value && extensions.value.length) extensionId.value = extensions.value[0].id
}

async function load() {
  if (!extensionId.value) return
  loading.value = true
  error.value = ''
  try {
    const [p, h, r] = await Promise.all([
      updateApi.policy(extensionId.value),
      updateApi.history(extensionId.value, 100),
      updateApi.recoveryPoints(extensionId.value),
    ])
    applyPolicy(p.data || {})
    history.value = Array.isArray(h.data) ? h.data : []
    recoveryPoints.value = Array.isArray(r.data) ? r.data : []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function savePolicy() {
  if (!extensionId.value) return
  saving.value = true
  error.value = ''
  notice.value = ''
  try {
    const response = await updateApi.savePolicy(extensionId.value, { ...policy })
    applyPolicy(response.data || {})
    notice.value = t('updates_page.saved')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

function extensionName(id){return extensions.value.find(item=>item.id===id||item.package===id)?.name||id}
function channelLabel(value){return ({stable:t('updates_page.channel_stable'),beta:t('updates_page.channel_beta'),development:t('updates_page.channel_development')})[value]||value}
function autoModeLabel(value){return ({disabled:t('updates_page.auto_disabled'),security_only:t('updates_page.auto_security'),patch_only:t('updates_page.auto_patch'),enabled:t('updates_page.auto_enabled')})[value]||value}
function historyLabel(value){return ({started:t('updates_page.status_started'),succeeded:t('updates_page.status_succeeded'),failed:t('updates_page.status_failed'),recovery_required:t('updates_page.status_recovery')})[value]||value}
function recoveryStatusLabel(value){return ({ready:t('updates_page.recovery_ready'),creating:t('updates_page.recovery_creating'),failed:t('updates_page.recovery_failed'),restored:t('updates_page.recovery_restored')})[value]||value||t('updates_page.unknown')}
function recoveryOperationLabel(value){return ({update:t('updates_page.operation_update'),uninstall:t('updates_page.operation_uninstall'),repair:t('updates_page.operation_repair'),rollback:t('updates_page.operation_rollback')})[value]||value||t('updates_page.unknown')}
function formatTime(value){const n=Number(value||0);if(!n)return'—';try{return new Intl.DateTimeFormat(undefined,{dateStyle:'medium',timeStyle:'short'}).format(new Date(n*1000))}catch{return new Date(n*1000).toLocaleString()}}
function historySeverity(status) {
  if (status === 'succeeded') return 'success'
  if (status === 'recovery_required') return 'warning'
  if (status === 'failed') return 'danger'
  return 'secondary'
}

watch(extensionId, () => load())
onMounted(async () => {
  try {
    await loadExtensions()
    await load()
  } catch (e) {
    error.value = e.message
  }
})
</script>
