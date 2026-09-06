<template>
  <LPage icon="server" class="cms-infrastructure-page">
    <template #actions><LButton icon="refresh-cw" variant="outline" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></template>
    <div v-if="error" class="cms-alert cms-alert--danger" role="alert">{{ error }}</div>
    <div v-if="notice" class="cms-alert cms-alert--success" aria-live="polite">{{ notice }}</div>

    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Drivers" :value="snapshot.drivers?.length || 0" icon="plug-zap" />
      <LStatCard label="Queue" :value="queue.healthy === false ? 'Attention' : 'Live'" icon="list-todo" />
      <LStatCard label="Failed Jobs" :value="queue.stats?.failed || 0" icon="triangle-alert" />
    </div>

    <div class="cms-infrastructure-grid">
      <LPanel><template #header>Search</template><pre class="cms-code-preview">{{ pretty(snapshot.search) }}</pre></LPanel>
      <LPanel><template #header>Cache</template><pre class="cms-code-preview">{{ pretty(snapshot.cache) }}</pre></LPanel>
      <LPanel><template #header>Storage</template><pre class="cms-code-preview">{{ pretty(snapshot.storage) }}</pre></LPanel>
      <LPanel><template #header>Scheduler</template><pre class="cms-code-preview">{{ pretty(snapshot.scheduler) }}</pre></LPanel>
    </div>

    <LPanel>
      <template #header>Cache Control</template>
      <div class="cms-control-form">
        <label>Tag<input v-model.trim="cacheTag" placeholder="content:site:1"></label>
        <LButton :disabled="busy || !cacheTag" @click="invalidateTag">Invalidate tag</LButton>
        <label>Layer
          <select v-model="cacheLayer">
            <option v-for="layer in boot.cacheLayers" :key="layer" :value="layer">{{ layer }}</option>
          </select>
        </label>
        <LButton variant="outline" :disabled="busy || !cacheLayer" @click="invalidateLayer">Invalidate layer</LButton>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Queue</template>
      <CmsPageState :state="loading ? 'loading' : queue.recent?.length ? 'ready' : 'empty'" empty-icon="list-todo" empty-title="Queue is empty" empty-message="No recent queue jobs are waiting for attention.">
        <div class="cms-stack">
          <article v-for="job in queue.recent || []" :key="job.id" class="cms-list-card cms-list-card--wide">
            <div><strong>{{ job.id }}</strong><small>{{ job.name || job.type || job.status }}</small><small v-if="job.last_error">{{ job.last_error }}</small></div>
            <div class="cms-card-actions">
              <LBadge :severity="jobSeverity(job.status)">{{ job.status }}</LBadge>
              <LButton v-if="['failed','dead'].includes(job.status)" size="sm" variant="outline" :disabled="busy === job.id" @click="retry(job.id)">Retry</LButton>
            </div>
          </article>
        </div>
      </CmsPageState>
    </LPanel>

    <LPanel>
      <template #header>Registered Drivers</template>
      <div class="cms-mobile-only cms-driver-cards">
        <article v-for="driver in snapshot.drivers || []" :key="driver.id" class="cms-list-card cms-list-card--wide">
          <div><strong>{{ driver.label }}</strong><small>{{ driver.id }}</small></div><LBadge severity="secondary">{{ driver.kind }}</LBadge>
        </article>
      </div>
      <LPanel flush bare class="cms-desktop-only">
        <LDataTable :value="snapshot.drivers || []" data-key="id">
          <Column field="label" header="Driver" /><Column field="id" header="ID" /><Column field="kind" header="Kind" /><Column field="owner" header="Owner" />
        </LDataTable>
      </LPanel>
    </LPanel>
  </LPage>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Column from 'primevue/column'
import { LBadge, LButton, LDataTable, LPage, LPanel, LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { readAdminBootData } from '../../services/admin-provider.js'
import { infrastructureApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const boot = readAdminBootData().infrastructure
const snapshot = ref({})
const queue = ref({})
const loading = ref(false)
const busy = ref('')
const error = ref('')
const notice = ref('')
const cacheTag = ref('')
const cacheLayer = ref(boot.cacheLayers?.[0] || 'object')

async function load() {
  loading.value = true; error.value = ''
  try {
    const [status, jobs] = await Promise.all([infrastructureApi.status(), infrastructureApi.queue()])
    snapshot.value = status.data || {}
    queue.value = jobs.data || {}
  } catch (e) { error.value = e.message } finally { loading.value = false }
}
async function retry(id) {
  busy.value = id; error.value = ''; notice.value = ''
  try { await infrastructureApi.retryQueue(id); notice.value = 'Queue job returned to pending state.'; await load() }
  catch (e) { error.value = e.message } finally { busy.value = '' }
}
async function invalidateTag() {
  busy.value = 'tag'; error.value = ''; notice.value = ''
  try { await infrastructureApi.invalidateTag(cacheTag.value); notice.value = 'Cache tag invalidated.'; await load() }
  catch (e) { error.value = e.message } finally { busy.value = '' }
}
async function invalidateLayer() {
  busy.value = 'layer'; error.value = ''; notice.value = ''
  try { await infrastructureApi.invalidateLayer(cacheLayer.value); notice.value = 'Cache layer invalidated.'; await load() }
  catch (e) { error.value = e.message } finally { busy.value = '' }
}
function jobSeverity(status) { if (status === 'failed' || status === 'dead') return 'danger'; if (status === 'pending' || status === 'running') return 'warning'; if (status === 'completed') return 'success'; return 'secondary' }
function pretty(value) { return JSON.stringify(value ?? {}, null, 2) }
onMounted(load)
</script>
