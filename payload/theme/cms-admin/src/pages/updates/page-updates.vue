<template>
  <LPage icon="refresh-cw" class="cms-update-center">
    <template #actions>
      <div class="cms-card-actions">
        <select v-model="extensionId" :aria-label="t('updates_page.extension')">
          <option value="">{{ t('updates_page.select_extension') }}</option>
          <option v-for="item in extensions" :key="item.id" :value="item.id">{{ item.name || item.id }}</option>
        </select>
        <LButton icon="refresh-cw" variant="outline" :disabled="loading || !extensionId" @click="load">{{ t('common.refresh') }}</LButton>
        <LButton icon="save" :disabled="saving || !extensionId" @click="savePolicy">{{ saving ? t('common.saving') : t('common.save') }}</LButton>
      </div>
    </template>

    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>
    <div v-if="notice" class="cms-alert cms-alert--success" aria-live="polite">{{ notice }}</div>

    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Policy" :value="extensionId ? 'Live' : '—'" icon="sliders-horizontal" />
      <LStatCard label="History" :value="history.length" icon="history" />
      <LStatCard label="Recovery" :value="recoveryPoints.length" icon="rotate-ccw" />
    </div>

    <LPanel>
      <template #header>Update Policy</template>
      <CmsPageState :state="loading ? 'loading' : extensionId ? 'ready' : 'empty'" :empty-title="t('updates_page.select_extension')" :empty-message="t('updates_page.select_extension_hint')">
        <div class="cms-control-form">
          <label>Channel
            <select v-model="policy.channel">
              <option v-for="channel in center.channels" :key="channel" :value="channel">{{ channel }}</option>
            </select>
          </label>
          <label>Auto Update
            <select v-model="policy.auto_update">
              <option v-for="mode in center.autoUpdateModes" :key="mode" :value="mode">{{ mode }}</option>
            </select>
          </label>
          <label class="cms-toggle-row"><input v-model="policy.require_signature" type="checkbox"><span>Require signature</span></label>
          <label class="cms-toggle-row"><input v-model="policy.snapshot_before_update" type="checkbox"><span>Snapshot before update</span></label>
          <label class="cms-toggle-row"><input v-model="policy.health_check_required" type="checkbox"><span>Health check required</span></label>
          <label class="cms-toggle-row"><input v-model="policy.allow_downgrade" type="checkbox"><span>Allow downgrade</span></label>
        </div>
      </CmsPageState>
    </LPanel>

    <LPanel>
      <template #header>Update History</template>
      <CmsPageState :state="loading ? 'loading' : history.length ? 'ready' : 'empty'" :empty-title="t('updates_page.empty_title')" :empty-message="t('updates_page.empty_message')">
        <div class="cms-mobile-only cms-stack">
          <article v-for="item in history" :key="item.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ item.extension_id }}</strong><small>{{ item.from_version }} → {{ item.to_version }}</small></div>
            <LBadge :severity="historySeverity(item.status)">{{ item.status }}</LBadge>
          </article>
        </div>
        <LPanel flush bare class="cms-desktop-only">
          <LDataTable :value="history" data-key="id">
            <Column field="extension_id" header="Extension" />
            <Column field="from_version" :header="t('updates_page.from')" />
            <Column field="to_version" :header="t('updates_page.to')" />
            <Column field="status" :header="t('updates_page.result')" />
            <Column field="recovery_point_id" header="Recovery Point" />
          </LDataTable>
        </LPanel>
      </CmsPageState>
    </LPanel>

    <LPanel>
      <template #header>Recovery Points</template>
      <CmsPageState :state="loading ? 'loading' : recoveryPoints.length ? 'ready' : 'empty'" empty-icon="rotate-ccw" :empty-title="t('recovery_page.empty_title')" :empty-message="t('recovery_page.empty_message')">
        <div class="cms-stack">
          <article v-for="item in recoveryPoints" :key="item.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ item.id }}</strong><small>{{ item.operation || item.status }}</small></div>
            <LBadge :severity="item.status === 'ready' ? 'success' : 'warning'">{{ item.status || 'unknown' }}</LBadge>
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
    notice.value = t('common.saved')
  } catch (e) {
    error.value = e.message
  } finally {
    saving.value = false
  }
}

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
