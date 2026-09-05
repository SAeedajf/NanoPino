<template>
  <LPage icon="server" class="cms-infrastructure-page">
    <div class="cms-stat-grid cms-stat-grid--three">
      <LStatCard label="Driver" :value="infra.drivers.length" icon="plug-zap" />
      <LStatCard label="Queue Mode" :value="infra.queue.mode" icon="list-todo" />
      <LStatCard label="API" :value="infra.apiBound ? 'Bound' : 'Contract'" icon="waypoints" />
    </div>

    <LPanel>
      <template #header>Runtime Boundary</template>
      <div class="cms-infrastructure-boundary">
        <article>
          <LBadge severity="success">Reuse</LBadge>
          <strong>Cache → Pinoox Cache</strong>
          <small>{{ t('infrastructure_page.cache_note') }}</small>
        </article>
        <article>
          <LBadge severity="success">Reuse</LBadge>
          <strong>Storage → Pinoox Storage</strong>
          <small>{{ t('infrastructure_page.storage_note') }}</small>
        </article>
        <article>
          <LBadge severity="success">Reuse</LBadge>
          <strong>Scheduler → Pinoox ScheduleRegistry</strong>
          <small>{{ t('infrastructure_page.scheduler_note') }}</small>
        </article>
        <article>
          <LBadge severity="info">CMS</LBadge>
          <strong>Queue → CMS Durable Queue</strong>
          <small>{{ t('infrastructure_page.queue_note') }}</small>
        </article>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Drivers</template>

      <div class="cms-mobile-only cms-driver-cards">
        <article v-for="driver in infra.drivers" :key="driver.id" class="cms-list-card cms-list-card--wide">
          <div>
            <strong>{{ driver.label }}</strong>
            <small>{{ driver.id }}</small>
          </div>
          <LBadge severity="secondary">{{ driver.kind }}</LBadge>
        </article>
      </div>

      <LPanel flush bare class="cms-desktop-only">
        <LDataTable :value="infra.drivers" data-key="id">
          <Column field="label" header="Driver" />
          <Column field="id" header="ID" />
          <Column field="kind" header="Kind" />
          <Column field="owner" header="Owner" />
        </LDataTable>
      </LPanel>
    </LPanel>

    <div class="cms-infrastructure-grid">
      <LPanel>
        <template #header>Search</template>
        <div class="cms-infrastructure-detail">
          <span>Fallback</span><strong>{{ infra.search.fallback }}</strong>
          <span>Remote adapters</span>
          <div class="cms-capability-cloud">
            <LBadge v-for="driver in infra.search.remote" :key="driver" severity="secondary">{{ driver }}</LBadge>
          </div>
          <small>{{ t('infrastructure_page.remote_provider_note') }}</small>
          <div class="cms-api-status">
            <LBadge :severity="infra.search.apiBound ? 'success' : 'warning'">
              {{ infra.search.apiBound ? 'Search API Bound' : 'Search API Contract' }}
            </LBadge>
            <code>/api/v1/cms/search</code>
          </div>
        </div>
      </LPanel>

      <LPanel>
        <template #header>Cache</template>
        <div class="cms-infrastructure-detail">
          <span>Adapter</span><strong>{{ infra.cache.active }}</strong>
          <span>Stores</span><strong>{{ infra.cache.nativeStores.join(' / ') }}</strong>
          <span>Invalidation</span><strong>{{ infra.cache.tagInvalidation }}</strong>
        </div>
      </LPanel>

      <LPanel>
        <template #header>Queue</template>
        <div class="cms-infrastructure-detail">
          <span>Driver</span><strong>{{ infra.queue.active }}</strong>
          <span>Mode</span><strong>{{ infra.queue.mode }}</strong>
          <span>{{ t('infrastructure_page.without_worker') }}</span><strong>{{ infra.queue.sharedHostingFallback }}</strong>
          <small>{{ t('infrastructure_page.async_fail_closed') }}</small>
        </div>
      </LPanel>

      <LPanel>
        <template #header>Storage</template>
        <div class="cms-infrastructure-detail">
          <span>Adapter</span><strong>{{ infra.storage.active }}</strong>
          <span>Native drivers</span>
          <div class="cms-capability-cloud">
            <LBadge v-for="driver in infra.storage.nativeDrivers" :key="driver" severity="secondary">{{ driver }}</LBadge>
          </div>
        </div>
      </LPanel>
    </div>

    <LPanel>
      <template #header>Cache Layers</template>
      <div class="cms-capability-cloud">
        <LBadge v-for="layer in infra.cacheLayers" :key="layer" severity="info">{{ layer }}</LBadge>
      </div>
    </LPanel>

    <LPanel>
      <template #header>Scheduler / Queue Bridge</template>
      <div class="cms-api-status">
        <LBadge severity="success">Native Scheduler</LBadge>
        <span>{{ infra.scheduler.provider }}</span>
      </div>
      <p class="cms-muted">
        {{ infra.scheduler.queueDrain }} — {{ t('infrastructure_page.scheduler_footer') }}
      </p>
    </LPanel>

    <LPanel>
      <template #header>Infrastructure API v1</template>
      <div class="cms-api-status">
        <LBadge :severity="infra.apiBound ? 'success' : 'warning'">
          {{ infra.apiBound ? 'Bound' : 'Contract only' }}
        </LBadge>
        <span>/api/v1/cms/system/infrastructure</span>
      </div>
      <div class="cms-contract-list cms-contract-list--api">
        <div v-for="route in infra.api" :key="`${route.method}:${route.path}`" class="cms-contract-row">
          <LBadge severity="secondary">{{ route.method }}</LBadge>
          <code>{{ route.path }}</code>
          <small>{{ route.capability }}</small>
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

const data = readAdminBootData()
const infra = data.infrastructure
</script>
