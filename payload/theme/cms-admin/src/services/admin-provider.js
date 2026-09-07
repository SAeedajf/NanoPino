import { t } from '../i18n/index.js'

const fallback = {
  extensions: [],
  extensionCenter: {
    api: [],
    apiBound: false,
    marketplace: { connected: false, providers: [] },
    upload: { maxBytes: 104857600, accept: ['.pinx', '.zip'] },
    installFlow: ['upload','inspect','trust','compatibility','dependencies','permissions','snapshot','staging','migration','install','health','activate'],
    trustNotice: 'Cryptographic verification confirms integrity/authenticity, not code safety.',
  },
  updateCenter: {
    api: [],
    apiBound: false,
    channels: ['stable', 'beta', 'development'],
    autoUpdateModes: ['disabled', 'security_only', 'patch_only', 'enabled'],
    policies: [],
    history: [],
    retention: { maxPerExtension: 5, maxAgeDays: 30, minimumReadyPoints: 1 },
    boundary: {
      extension: 'CMS transactional update + PINX',
      platform: 'PlatformUpdater / Pinroll deployment boundary',
    },
  },
  recoveryCenter: {
    api: [],
    apiBound: false,
    safeModeExitRequiresHealth: true,
    uninstallSnapshotRequired: true,
    repairSnapshotRequired: true,
  },
  infrastructure: {
    api: [],
    apiBound: false,
    drivers: [],
    cacheLayers: ['object','query','page','api','builder_render'],
    search: { active: null, fallback: 'search.database', remote: ['search.meilisearch','search.typesense'], api: [], apiBound: false },
    cache: { active: 'cache.pinoox', nativeStores: ['file','redis'], tagInvalidation: 'generation-clock' },
    queue: { active: 'queue.file', mode: 'auto', sharedHostingFallback: 'sync', stats: {} },
    storage: { active: 'storage.pinoox', nativeDrivers: ['local','ftp','sftp','s3','custom'] },
    scheduler: { provider: 'Pinoox ScheduleRegistry', role: 'trigger-only', queueDrain: 'everyMinute + withoutOverlapping' },
  },
  healthCenter: {
    api: [],
    apiBound: false,
    overall: 'unknown',
    historyBound: false,
    logsBound: false,
    supportBundleBound: false,
    checks: [],
    logs: [],
    kernel: {
      runtime: { code: null, version: null, source: 'unbound' },
      compatibility: { compatible: false, minimum: { code: 232, version: '3.14.0' }, current: { code: null, version: null }, optional_features_unavailable: [], primitives: [] },
    },
  },
  developerSdk: {
    version: 'v1',
    minimum: { php: '>=8.2', pincore: '>=3.14.0', kernelCode: 232, cms: '>=0.21.0', luma: '>=0.4.10' },
    extensionTypes: ['module','plugin','integration','theme','admin-extension','block','block-package','driver','language-pack'],
    registries: [],
    nativeRegistration: [],
    apiBase: '/api/v1/extensions/{package}',
    starters: [],
    packageValidator: true,
    testHarness: true,
    coreEdits: false,
  },
  performanceCenter: {
    api: [],
    apiBound: false,
    profile: 'shared_hosting',
    budgets: [],
    metrics: {},
    queryProbeBound: false,
    cacheTelemetryBound: false,
    extensionCostBound: false,
    runtimeProfilerBound: false,
    benchmarks: [],
    query: { count: null, totalMs: null, nPlusOne: [], slow: [] },
    cache: { hits: null, misses: null, hitRatio: null },
    memory: { peakBytes: null },
    sharedHosting: {
      workerRequired: false,
      queueFallback: 'sync',
      externalSearchRequired: false,
      defaultCache: 'Pinoox File/Redis',
    },
  },
  securityCenter: {
    api: [],
    apiBound: false,
    posture: { status: 'warning', counts: { pass: 0, warning: 0, fail: 0 }, controls: [] },
    cspMode: 'report-only',
    headersBound: false,
    csrfBound: false,
    rateLimitsRegistered: false,
    ssrfTransportBound: false,
    rateLimits: [],
    apiMatrix: {},
    native: {
      rateLimiter: 'Pinoox RateLimiter + ThrottleFlow',
      responseEvent: 'Pinoox AppResponseEvent',
    },
  },
  users: [],
  roles: [],
  roleTemplates: [],
  settingDefinitions: [],
  contentTypeDefinitions: [],
  blockDefinitions: [],
  builderDataSources: [],
  builder: {
    documents: [],
    activeDocument: null,
    viewports: ['desktop','tablet','mobile'],
    panels: ['blocks','layers','inspector','history'],
    historyLimit: 100,
  },
  fieldDefinitions: [],
  taxonomyDefinitions: [],
  mediaAssets: [],
  themes: [],
  appearance: {
    active: null,
    stack: [],
    contexts: [],
    templateKinds: ['home','page','single','archive','taxonomy','search','404','part'],
    designRoots: ['colors','typography','fontFamilies','fontSizes','spacing','containers','breakpoints','grid','radius','shadows','buttons','forms','links','motion','icons','darkMode','rtl','accessibility'],
  },
  fullSiteEditor: {
    templateKinds: ['index','home','page','single','archive','taxonomy','search','404'],
    templateParts: ['header','footer'],
    breakpointFallbacks: [
      { id: 'sm', minWidth: '36rem' },
      { id: 'md', minWidth: '48rem' },
      { id: 'lg', minWidth: '64rem' },
      { id: 'xl', minWidth: '80rem' },
    ],
    patterns: [],
    globalBlocks: [],
    builderApi: [],
    apiBound: false,
  },
  revisionSummary: { schemaVersion: 1, kinds: ['initial','manual','autosave','published','scheduled','pre_restore','restored'] },
  auditEvents: [],
  currentUser: null,
  recoveryPoints: [],
  health: [],
  summary: {
    content: 0,
    media: 0,
    extensions: 0,
    problems: 0,
  },
  safeMode: {
    enabled: false,
    quarantined: [],
    reason: null,
  },
}

export function readAdminBootData() {
  const boot = typeof window !== 'undefined' ? window.__PINOOX__ || {} : {}
  const data = boot.cmsAdmin?.data || {}

  return {
    ...fallback,
    ...data,
    summary: { ...fallback.summary, ...(data.summary || {}) },
    safeMode: { ...fallback.safeMode, ...(data.safeMode || {}) },
    extensions: Array.isArray(data.extensions) ? data.extensions : [],
    extensionCenter: data.extensionCenter && typeof data.extensionCenter === 'object'
      ? { ...fallback.extensionCenter, ...data.extensionCenter,
          marketplace: { ...fallback.extensionCenter.marketplace, ...(data.extensionCenter.marketplace || {}) },
          upload: { ...fallback.extensionCenter.upload, ...(data.extensionCenter.upload || {}) },
        }
      : fallback.extensionCenter,
    updateCenter: data.updateCenter && typeof data.updateCenter === 'object'
      ? {
          ...fallback.updateCenter,
          ...data.updateCenter,
          retention: { ...fallback.updateCenter.retention, ...(data.updateCenter.retention || {}) },
          boundary: { ...fallback.updateCenter.boundary, ...(data.updateCenter.boundary || {}) },
        }
      : fallback.updateCenter,
    recoveryCenter: data.recoveryCenter && typeof data.recoveryCenter === 'object'
      ? { ...fallback.recoveryCenter, ...data.recoveryCenter }
      : fallback.recoveryCenter,
    infrastructure: data.infrastructure && typeof data.infrastructure === 'object'
      ? {
          ...fallback.infrastructure,
          ...data.infrastructure,
          search: { ...fallback.infrastructure.search, ...(data.infrastructure.search || {}) },
          cache: { ...fallback.infrastructure.cache, ...(data.infrastructure.cache || {}) },
          queue: { ...fallback.infrastructure.queue, ...(data.infrastructure.queue || {}) },
          storage: { ...fallback.infrastructure.storage, ...(data.infrastructure.storage || {}) },
          scheduler: { ...fallback.infrastructure.scheduler, ...(data.infrastructure.scheduler || {}) },
          drivers: Array.isArray(data.infrastructure.drivers) ? data.infrastructure.drivers : [],
        }
      : fallback.infrastructure,
    healthCenter: data.healthCenter && typeof data.healthCenter === 'object'
      ? {
          ...fallback.healthCenter,
          ...data.healthCenter,
          checks: Array.isArray(data.healthCenter.checks) ? data.healthCenter.checks : [],
          logs: Array.isArray(data.healthCenter.logs) ? data.healthCenter.logs : [],
          kernel: {
            ...fallback.healthCenter.kernel,
            ...(data.healthCenter.kernel || {}),
            runtime: { ...fallback.healthCenter.kernel.runtime, ...(data.healthCenter.kernel?.runtime || {}) },
            compatibility: { ...fallback.healthCenter.kernel.compatibility, ...(data.healthCenter.kernel?.compatibility || {}) },
          },
        }
      : fallback.healthCenter,
    developerSdk: data.developerSdk && typeof data.developerSdk === 'object'
      ? {
          ...fallback.developerSdk,
          ...data.developerSdk,
          minimum: { ...fallback.developerSdk.minimum, ...(data.developerSdk.minimum || {}) },
          extensionTypes: Array.isArray(data.developerSdk.extensionTypes) ? data.developerSdk.extensionTypes : fallback.developerSdk.extensionTypes,
          registries: Array.isArray(data.developerSdk.registries) ? data.developerSdk.registries : [],
          nativeRegistration: Array.isArray(data.developerSdk.nativeRegistration) ? data.developerSdk.nativeRegistration : [],
          starters: Array.isArray(data.developerSdk.starters) ? data.developerSdk.starters : [],
        }
      : fallback.developerSdk,
    performanceCenter: data.performanceCenter && typeof data.performanceCenter === 'object'
      ? {
          ...fallback.performanceCenter,
          ...data.performanceCenter,
          budgets: Array.isArray(data.performanceCenter.budgets) ? data.performanceCenter.budgets : [],
          benchmarks: Array.isArray(data.performanceCenter.benchmarks) ? data.performanceCenter.benchmarks : [],
          query: { ...fallback.performanceCenter.query, ...(data.performanceCenter.query || {}) },
          cache: { ...fallback.performanceCenter.cache, ...(data.performanceCenter.cache || {}) },
          memory: { ...fallback.performanceCenter.memory, ...(data.performanceCenter.memory || {}) },
          sharedHosting: { ...fallback.performanceCenter.sharedHosting, ...(data.performanceCenter.sharedHosting || {}) },
        }
      : fallback.performanceCenter,
    securityCenter: data.securityCenter && typeof data.securityCenter === 'object'
      ? {
          ...fallback.securityCenter,
          ...data.securityCenter,
          posture: data.securityCenter.posture && typeof data.securityCenter.posture === 'object'
            ? { ...fallback.securityCenter.posture, ...data.securityCenter.posture }
            : fallback.securityCenter.posture,
          native: { ...fallback.securityCenter.native, ...(data.securityCenter.native || {}) },
          rateLimits: Array.isArray(data.securityCenter.rateLimits) ? data.securityCenter.rateLimits : [],
          apiMatrix: data.securityCenter.apiMatrix && typeof data.securityCenter.apiMatrix === 'object'
            ? data.securityCenter.apiMatrix
            : {},
        }
      : fallback.securityCenter,
    users: Array.isArray(data.users) ? data.users : [],
    roles: Array.isArray(data.roles) ? data.roles : [],
    roleTemplates: Array.isArray(data.roleTemplates) ? data.roleTemplates : [],
    settingDefinitions: Array.isArray(data.settingDefinitions) ? data.settingDefinitions : [],
    contentTypeDefinitions: Array.isArray(data.contentTypeDefinitions) ? data.contentTypeDefinitions : [],
    blockDefinitions: Array.isArray(data.blockDefinitions) ? data.blockDefinitions : [],
    builderDataSources: Array.isArray(data.builderDataSources) ? data.builderDataSources : [],
    builder: data.builder && typeof data.builder === 'object'
      ? { ...fallback.builder, ...data.builder }
      : fallback.builder,
    fieldDefinitions: Array.isArray(data.fieldDefinitions) ? data.fieldDefinitions : [],
    taxonomyDefinitions: Array.isArray(data.taxonomyDefinitions) ? data.taxonomyDefinitions : [],
    mediaAssets: Array.isArray(data.mediaAssets) ? data.mediaAssets : [],
    themes: Array.isArray(data.themes) ? data.themes : [],
    appearance: data.appearance && typeof data.appearance === 'object'
      ? { ...fallback.appearance, ...data.appearance }
      : fallback.appearance,
    fullSiteEditor: data.fullSiteEditor && typeof data.fullSiteEditor === 'object'
      ? { ...fallback.fullSiteEditor, ...data.fullSiteEditor }
      : fallback.fullSiteEditor,
    revisionSummary: data.revisionSummary && typeof data.revisionSummary === 'object'
      ? data.revisionSummary
      : fallback.revisionSummary,
    auditEvents: Array.isArray(data.auditEvents) ? data.auditEvents : [],
    currentUser: data.currentUser && typeof data.currentUser === 'object' ? data.currentUser : null,
    recoveryPoints: Array.isArray(data.recoveryPoints) ? data.recoveryPoints : [],
    health: Array.isArray(data.health) ? data.health : [],
  }
}

export async function performAdminAction(action, payload = {}) {
  const boot = typeof window !== 'undefined' ? window.__PINOOX__ || {} : {}
  const endpoint = boot.cmsAdmin?.actions?.[action]

  if (!endpoint) {
    throw new Error(t('common.operation_not_bound', {}, 'This operation is not yet bound to a real NanoPino API.'))
  }

  const response = await fetch(endpoint, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': boot.csrf || '',
    },
    body: JSON.stringify(payload),
  })

  const result = await response.json()
  if (!response.ok || result?.success === false) {
    throw new Error(result?.error?.message || t('common.operation_failed', {}, 'Operation failed.'))
  }

  return result
}
