const FALLBACK_PLATFORM = Object.freeze({
  id: 'nanoshell',
  name: 'NanoShell',
  contract: 'nanoshell-platform-v1',
  version: 1,
  standalone: true,
  product: 'NanoPino',
  runtime: Object.freeze({
    execution: 'native-only',
    document: 'block-document-v1',
    api: 'cms-api-v1',
    identity_source: 'nanoshell-platform-contract',
  }),
  source_adapters: Object.freeze(['static-theme-import', 'static-asset-intake']),
})

function bootPlatform() {
  if (typeof window === 'undefined') return null
  return window.__PINOOX__?.cmsAdmin?.platform || null
}

let cachedInput = undefined
let cachedPlatform = null

function isNanoShellPlatform(value) {
  return Boolean(value && typeof value === 'object'
    && value.id === FALLBACK_PLATFORM.id
    && value.name === FALLBACK_PLATFORM.name
    && value.contract === FALLBACK_PLATFORM.contract
    && Number(value.version) === FALLBACK_PLATFORM.version
    && value.standalone === true
    && value.product === FALLBACK_PLATFORM.product
    && value.runtime?.execution === FALLBACK_PLATFORM.runtime.execution
    && value.runtime?.document === FALLBACK_PLATFORM.runtime.document
    && value.runtime?.api === FALLBACK_PLATFORM.runtime.api
    && value.runtime?.identity_source === FALLBACK_PLATFORM.runtime.identity_source
    && Array.isArray(value.source_adapters))
}

export function cmsPlatform() {
  const value = bootPlatform()
  if (value === cachedInput && cachedPlatform) return cachedPlatform
  if (!isNanoShellPlatform(value)) {
    cachedInput = value
    cachedPlatform = FALLBACK_PLATFORM
    return cachedPlatform
  }

  cachedInput = value
  cachedPlatform = Object.freeze({
    ...FALLBACK_PLATFORM,
    ...value,
    runtime: Object.freeze({ ...FALLBACK_PLATFORM.runtime, ...value.runtime }),
    source_adapters: Object.freeze([...value.source_adapters]),
  })
  return cachedPlatform
}

export function applyNanoShellIdentity() {
  if (typeof document === 'undefined') return
  const platform = cmsPlatform()
  document.documentElement.dataset.cmsPlatform = platform.id
  document.documentElement.dataset.cmsPlatformContract = platform.contract
}

export { FALLBACK_PLATFORM, isNanoShellPlatform }
