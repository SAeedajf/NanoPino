(() => {
  const root = document.documentElement
  const expected = root.dataset.cmsFrontendReady === '1'
  if (!expected) return

  let settled = false
  let timer = null

  const text = {
    title: 'رابط مدیریت CMS اجرا نشد',
    runtime: 'فایل‌های Frontend پیدا شدند، اما Vue/Luma در مرورگر با خطا متوقف شد.',
    asset: 'یکی از فایل‌های JavaScript یا CSS رابط مدیریت از طریق وب‌سرور بارگذاری نشد.',
    timeout: 'فایل‌های Frontend بارگذاری شدند، اما CMS در زمان مورد انتظار راه‌اندازی نشد.',
  }

  function codeNode(code) {
    const el = document.createElement('code')
    el.className = 'cms-boot-code'
    el.textContent = code
    return el
  }

  function paragraph(value, muted = false) {
    const el = document.createElement('p')
    if (muted) el.className = 'cms-boot-muted'
    el.textContent = value
    return el
  }

  function button(label, handler) {
    const el = document.createElement('button')
    el.type = 'button'
    el.className = 'cms-boot-action'
    el.textContent = label
    el.addEventListener('click', handler)
    return el
  }

  function renderFailure(code, message) {
    if (settled) return
    settled = true
    if (timer) clearTimeout(timer)

    root.dataset.cmsBoot = 'failed'

    const mount = document.getElementById('app')
    if (!mount) return

    const shell = document.createElement('main')
    shell.className = 'cms-boot-shell'
    shell.setAttribute('role', 'alert')

    const card = document.createElement('section')
    card.className = 'cms-boot-card'

    const title = document.createElement('h1')
    title.textContent = text.title
    card.append(title)
    card.append(paragraph(message))
    card.append(codeNode(code))
    card.append(paragraph(
      'این خطا دیگر به‌صورت صفحه سفید مخفی نمی‌شود. ابتدا یک‌بار تلاش مجدد کنید؛ اگر باقی ماند، وضعیت Frontend و فایل‌های dist را بررسی کنید.',
      true,
    ))

    const actions = document.createElement('div')
    actions.className = 'cms-boot-actions'
    actions.append(
      button('تلاش مجدد', () => window.location.reload()),
      button('کپی کد خطا', async () => {
        try {
          await navigator.clipboard.writeText(code)
        } catch {
          // Clipboard may be unavailable. The code remains visible in the page.
        }
      }),
    )
    card.append(actions)

    shell.append(card)
    mount.replaceChildren(shell)
  }

  window.addEventListener('pinoox-cms:boot-ready', () => {
    if (settled) return
    settled = true
    if (timer) clearTimeout(timer)
    root.dataset.cmsBoot = 'ready'
  }, { once: true })

  window.addEventListener('pinoox-cms:boot-failed', (event) => {
    renderFailure(
      event?.detail?.code || 'cms.admin.runtime_boot_failed',
      text.runtime,
    )
  }, { once: true })

  window.addEventListener('error', (event) => {
    const target = event.target
    if (!target || !['SCRIPT', 'LINK'].includes(target.tagName)) return
    renderFailure('cms.admin.browser_asset_load_failed', text.asset)
  }, true)

  window.addEventListener('unhandledrejection', () => {
    renderFailure('cms.admin.runtime_unhandled_rejection', text.runtime)
  })

  timer = window.setTimeout(() => {
    renderFailure('cms.admin.runtime_boot_timeout', text.timeout)
  }, 12000)
})()
