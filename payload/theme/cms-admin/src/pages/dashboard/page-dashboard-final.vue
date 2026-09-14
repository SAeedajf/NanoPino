<template>
  <LPage icon="layout-dashboard" header-tone="gradient">
    <SafeModeBanner :safe-mode="boot.safeMode" />
    <template #actions>
      <div class="cms-card-actions">
        <a class="cms-dashboard-public-site" :href="publicSiteUrl" target="_blank" rel="noopener noreferrer" :aria-label="`${t('dashboard.view_site')} — ${publicSiteUrl}`">
          <LIcon name="external-link" aria-hidden="true" />
          <span><strong>{{ t('dashboard.view_site') }}</strong><small>{{ publicSiteUrl }}</small></span>
        </a>
        <LButton icon="refresh-cw" variant="outline" shape="rounded" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton>
        <LButton icon="file-plus-2" shape="rounded" @click="go('/content')">{{ t('dashboard.manage_content') }}</LButton>
      </div>
    </template>

    <section class="cms-dashboard-hero">
      <div><span class="cms-dashboard-eyebrow">{{ t('brand.name') }}</span><h1>{{ t('routes.dashboard.title') }}</h1><p>{{ t('routes.dashboard.lead') }}</p></div>
      <div class="cms-dashboard-hero__meta"><span class="cms-dashboard-status" :data-status="summary.problems ? 'attention' : 'healthy'"><span class="cms-dashboard-status__dot" aria-hidden="true" />{{ summary.problems ? t('dashboard.attention') : t('dashboard.all_stable') }}</span><span class="cms-dashboard-updated">{{ lastUpdated || t('state.loading_title') }}</span></div>
    </section>

    <LPanel v-if="error" class="cms-inline-callout cms-inline-callout--danger" role="alert"><strong>{{ t('state.error_title') }}</strong><p>{{ error }}</p><LButton icon="refresh-cw" variant="outline" shape="rounded" :loading="loading" @click="load">{{ t('common.refresh') }}</LButton></LPanel>
    <LPanel v-else-if="summary.problems" class="cms-dashboard-attention" role="status" aria-live="polite"><div><strong>{{ t('dashboard.attention') }}</strong><p>{{ t('dashboard.monitoring_hint') }}</p></div><LButton icon="shield-alert" variant="outline" shape="rounded" @click="go('/system')">{{ t('dashboard.manage') }}</LButton></LPanel>
    <div class="cms-dashboard-kpis" :aria-label="t('dashboard.summary_aria')">
      <button v-for="item in kpis" :key="item.key" type="button" class="cms-dashboard-kpi" :class="`cms-dashboard-kpi--${item.tone}`" @click="go(item.path)"><div class="cms-dashboard-kpi__top"><span>{{ item.label }}</span><span class="cms-dashboard-kpi__icon"><LIcon :name="item.icon" /></span></div><strong>{{ formatNumber(item.value) }}</strong><small>{{ item.hint }} <LIcon name="arrow-up-left" /></small></button>
    </div>

    <div class="cms-dashboard-main-grid">
      <LPanel class="cms-dashboard-chart-panel"><template #header><div class="cms-panel-heading"><div><strong>{{ t('dashboard.inventory') }}</strong><small>{{ t('dashboard.inventory_hint') }}</small></div><LBadge severity="success">{{ t('dashboard.system_health') }}</LBadge></div></template><div class="cms-dashboard-chart" role="img" :aria-label="t('dashboard.inventory_aria')"><div class="cms-dashboard-chart__grid"><span v-for="line in 4" :key="line" /></div><div class="cms-dashboard-chart__bars"><span v-for="(value,index) in chartBars" :key="chartLabels[index]" class="cms-dashboard-bar" :style="{height: `${value}%`}" :title="`${chartLabels[index]}: ${chartValues[index]}`" :aria-label="`${chartLabels[index]}: ${chartValues[index]}`"><b /></span></div><div class="cms-dashboard-chart__labels"><span v-for="label in chartLabels" :key="label">{{ label }}</span></div></div><div class="cms-dashboard-chart-footer"><span><i class="cms-dot cms-dot--indigo" />{{ t('dashboard.inventory') }}</span><span>{{ t('dashboard.monitoring_hint') }}</span></div></LPanel>
      <LPanel class="cms-dashboard-activity-panel"><template #header><div class="cms-panel-heading"><div><strong>{{ t('dashboard.system_health') }}</strong><small>{{ t('dashboard.monitoring_hint') }}</small></div><button class="cms-text-action" type="button" @click="go('/system')">{{ t('dashboard.manage') }} <LIcon name="arrow-up-left" /></button></div></template><CmsPageState :state="loading ? 'loading' : health.length ? 'ready' : 'empty'"><div class="cms-dashboard-activity-list"><div v-for="item in health.slice(0, 5)" :key="item.id" class="cms-dashboard-activity-row"><span class="cms-dashboard-activity-icon" :data-status="item.status"><LIcon :name="statusIcon(item.status)" /></span><div><strong>{{ item.label || item.id }}</strong><small>{{ item.status }}</small></div><LBadge :severity="severity(item.status)">{{ item.status }}</LBadge></div></div></CmsPageState></LPanel>
    </div>

    <div class="cms-dashboard-lower-grid">
      <LPanel><template #header><div class="cms-panel-heading"><div><strong>{{ t('dashboard.manage_content') }}</strong><small>{{ t('routes.content.lead') }}</small></div><button class="cms-text-action" type="button" @click="go('/content')">{{ t('dashboard.manage') }} <LIcon name="arrow-up-left" /></button></div></template><CmsPageState :state="loading ? 'loading' : recentContent.length ? 'ready' : 'empty'"><div class="cms-dashboard-recent-list"><button v-for="item in recentContent.slice(0, 4)" :key="item.id" type="button" class="cms-dashboard-recent-row" @click="go('/content')"><span class="cms-dashboard-recent-icon"><LIcon name="file-text" /></span><span><strong>{{ item.title || item.name || `#${item.id}` }}</strong><small>{{ item.status || item.type || '—' }}</small></span><LIcon name="arrow-up-left" /></button></div></CmsPageState></LPanel>
      <LPanel><template #header><div class="cms-panel-heading"><div><strong>{{ t('dashboard.extensions') }}</strong><small>{{ t('routes.extensions.lead') }}</small></div><button class="cms-text-action" type="button" @click="go('/extensions')">{{ t('dashboard.manage') }} <LIcon name="arrow-up-left" /></button></div></template><CmsPageState :state="loading ? 'loading' : extensions.length ? 'ready' : 'empty'"><div class="cms-dashboard-extension-list"><div v-for="item in extensions.slice(0, 4)" :key="item.id" class="cms-dashboard-extension-row"><span class="cms-dashboard-extension-mark"><LIcon name="blocks" /></span><span><strong>{{ item.name || item.id }}</strong><small>{{ item.version || item.status || '—' }}</small></span><LBadge :severity="severity(item.status)">{{ item.status }}</LBadge></div></div></CmsPageState></LPanel>
    </div>

    <LPanel class="cms-dashboard-quick-panel"><template #header><div class="cms-panel-heading"><div><strong>{{ t('dashboard.quick_actions') }}</strong><small>{{ t('dashboard.control_plane_hint') }}</small></div></div></template><div class="cms-dashboard-quick-grid"><button v-for="item in quickActions" :key="item.path" type="button" class="cms-dashboard-quick-card" @click="go(item.path)"><span class="cms-dashboard-quick-card__icon"><LIcon :name="item.icon" /></span><span><strong>{{ item.label }}</strong><small>{{ item.hint }}</small></span><LIcon name="arrow-up-left" /></button></div></LPanel>
  </LPage>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { LBadge, LButton, LIcon, LPage, LPanel } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import SafeModeBanner from '../../components/safe-mode-banner.vue'
import { publicSiteUrl as resolvePublicSiteUrl, readAdminBootData } from '../../services/admin-provider.js'
import { contentApi, extensionApi, mediaApi, systemApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const boot=readAdminBootData(),router=useRouter(),publicSiteUrl=resolvePublicSiteUrl(),extensions=ref([]),health=ref([]),recentContent=ref([]),loading=ref(false),error=ref(''),lastUpdated=ref('')
const summary=reactive({content:0,media:0,extensions:0,problems:0})
const chartItems=computed(()=>[
  {key:'content',label:t('dashboard.content'),value:summary.content},
  {key:'media',label:t('dashboard.media'),value:summary.media},
  {key:'extensions',label:t('dashboard.extensions'),value:summary.extensions},
  {key:'problems',label:t('dashboard.attention'),value:summary.problems},
])
const chartMax=computed(()=>Math.max(1,...chartItems.value.map(item=>Number(item.value)||0)))
const chartBars=computed(()=>chartItems.value.map(item=>Math.max(12,Math.round((Number(item.value)||0)/chartMax.value*100))))
const chartLabels=computed(()=>chartItems.value.map(item=>item.label))
const chartValues=computed(()=>chartItems.value.map(item=>item.value))
const problemExtensions=computed(()=>extensions.value.filter(item=>['failed','quarantined','incompatible'].includes(item.status)))
const kpis=computed(()=>[{key:'content',path:'/content',tone:'indigo',icon:'files',label:t('dashboard.content'),value:summary.content,hint:t('dashboard.manage_content')},{key:'media',path:'/media',tone:'mint',icon:'image',label:t('dashboard.media'),value:summary.media,hint:t('dashboard.media')},{key:'extensions',path:'/extensions',tone:'amber',icon:'blocks',label:t('dashboard.extensions'),value:summary.extensions,hint:t('dashboard.quick_actions')},{key:'problems',path:'/system',tone:'rose',icon:'triangle-alert',label:t('dashboard.attention'),value:summary.problems,hint:summary.problems?t('dashboard.manage'):t('dashboard.all_stable')}])
const quickActions=computed(()=>[{path:'/content',icon:'file-plus-2',label:t('dashboard.manage_content'),hint:t('routes.content.lead')},{path:'/media',icon:'image-plus',label:t('dashboard.media'),hint:t('routes.media.lead')},{path:'/builder',icon:'panels-top-left',label:t('dashboard.builder'),hint:t('routes.builder.lead')},{path:'/appearance',icon:'palette',label:t('dashboard.theme'),hint:t('routes.appearance.lead')}])
async function load(){loading.value=true;error.value='';try{const results=await Promise.allSettled([contentApi.list({limit:5,projection:'list'}),mediaApi.list({limit:1}),extensionApi.list(),systemApi.health(),systemApi.logs({limit:1,active_window:900})]);const [content,media,extension,healthResponse,logs]=results;const failures=results.filter(result=>result.status==='rejected');if(content.status==='fulfilled'){const c=content.value.data||{};recentContent.value=c.items||[];summary.content=Number(c.pagination?.total||0)}if(media.status==='fulfilled'){const m=media.value.data||{};summary.media=Number(m.summary?.total||0)}if(extension.status==='fulfilled')extensions.value=extension.value.data?.items||[];if(healthResponse.status==='fulfilled')health.value=healthResponse.value.data?.checks||[];const ls=logs.status==='fulfilled'?logs.value.data?.summary||{}:{};summary.extensions=extensions.value.length;summary.problems=problemExtensions.value.length+health.value.filter(item=>['error','fail'].includes(item.status)).length+Number(ls.active_errors||0)+failures.length;if(results.some(result=>result.status==='fulfilled'))lastUpdated.value=new Intl.DateTimeFormat(undefined,{hour:'2-digit',minute:'2-digit'}).format(new Date());if(failures.length)error.value=t('dashboard.partial_load_failed',{count:failures.length})}catch(e){error.value=e.message}finally{loading.value=false}}
const go=path=>router.push(path),formatNumber=value=>new Intl.NumberFormat().format(Number(value||0));function severity(status){if(['ok','healthy','pass','active','enabled','installed'].includes(status))return'success';if(status==='warning')return'warning';return'danger'}function statusIcon(status){if(['ok','healthy','pass'].includes(status))return'circle-check';if(status==='warning')return'triangle-alert';return'circle-x'}onMounted(load)
</script>
