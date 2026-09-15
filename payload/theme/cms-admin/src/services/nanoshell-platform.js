const FALLBACK_PLATFORM = Object.freeze({
  id: 'nanoshell',
  name: 'NanoShell',
  contract: 'nanoshell-platform-v1',
  version: 1,
  standalone: true,
  product: 'NanoPino',
  capabilities: Object.freeze([
    'auth.rbac',
    'content.editorial',
    'storage.native',
    'theme.native',
    'builder.native',
    'extensions.signed',
    'recovery.safe-mode',
  ]),
  compatibility: Object.freeze({
    contract: 'nanoshell-platform-v1',
    minimum_version: 1,
    host_bindings: 'pinoox-native',
  }),
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
    && Number.isInteger(value.version)
    && value.version === FALLBACK_PLATFORM.version
    && value.standalone === true
    && value.product === FALLBACK_PLATFORM.product
    && Array.isArray(value.capabilities)
    && FALLBACK_PLATFORM.capabilities.every((capability) => value.capabilities.includes(capability))
    && value.compatibility?.contract === FALLBACK_PLATFORM.compatibility.contract
    && Number.isInteger(value.compatibility?.minimum_version)
    && value.compatibility.minimum_version >= 1
    && value.compatibility.minimum_version <= FALLBACK_PLATFORM.version
    && value.compatibility?.host_bindings === FALLBACK_PLATFORM.compatibility.host_bindings
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
    capabilities: Object.freeze([...value.capabilities]),
    compatibility: Object.freeze({ ...FALLBACK_PLATFORM.compatibility, ...value.compatibility }),
    runtime: Object.freeze({ ...FALLBACK_PLATFORM.runtime, ...value.runtime }),
    source_adapters: Object.freeze([...value.source_adapters]),
  })
  return cachedPlatform
}

export function cmsPlatformSupports(capability) {
  const requested = String(capability || '').trim()
  return Boolean(requested && cmsPlatform().capabilities.includes(requested))
}

export function applyNanoShellIdentity() {
  if (typeof document === 'undefined') return
  const platform = cmsPlatform()
  document.documentElement.dataset.cmsPlatform = platform.id
  document.documentElement.dataset.cmsPlatformContract = platform.contract
}

export { FALLBACK_PLATFORM, isNanoShellPlatform }
