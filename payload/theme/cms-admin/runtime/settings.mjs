import { api, ui, input, textarea, select, message, safeJson, navigate, tr} from './common.mjs'

const GROUPS = {
  general: { label: () => tr('settings_page.general'), icon: 'settings-2', description: () => tr('settings_page.general_desc') },
  localization: { label: () => tr('settings_page.localization'), icon: 'languages', description: () => tr('settings_page.localization_desc') },
  admin: { label: () => tr('settings_page.admin'), icon: 'panel-top', description: () => tr('settings_page.admin_desc') },
  diagnostics: { label: () => tr('settings_page.diagnostics'), icon: 'scan-search', description: () => tr('settings_page.diagnostics_desc') },
  api: { label: () => tr('settings_page.api'), icon: 'waypoints', description: () => tr('settings_page.api_desc') },
  appearance: { label: () => tr('settings_page.appearance'), icon: 'palette', description: () => tr('settings_page.appearance_desc') },
}

const CSS = `
.cms-settings-center{display:grid;gap:16px;direction:inherit}
.cms-settings-toolbar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;position:sticky;top:0;z-index:3;padding:10px;background:color-mix(in srgb,var(--p-surface-0,#fff) 92%,transparent);backdrop-filter:blur(12px);border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px}
.cms-settings-toolbar input{min-width:220px;flex:1}
.cms-settings-shell{display:grid;grid-template-columns:minmax(190px,240px) minmax(0,1fr);gap:16px;align-items:start}
.cms-settings-nav{display:grid;gap:6px;position:sticky;top:76px}
.cms-settings-nav button{width:100%;text-align:start;border:1px solid transparent;background:transparent;border-radius:12px;padding:11px 12px;display:grid;grid-template-columns:30px 1fr;gap:8px;cursor:pointer;color:inherit}
.cms-settings-nav button:hover{background:var(--p-surface-100,#f3f4f6)}
.cms-settings-nav button.active{background:var(--p-primary-50,#eef2ff);border-color:var(--p-primary-200,#c7d2fe)}
.cms-settings-nav strong,.cms-settings-nav small{display:block}.cms-settings-nav small{opacity:.68;margin-top:2px;font-size:11px}
.cms-settings-mobile-select{display:none}
.cms-settings-section{display:grid;gap:12px}
.cms-settings-section-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap}
.cms-settings-section-head h2{margin:0;font-size:20px}.cms-settings-section-head p{margin:5px 0 0;opacity:.7}
.cms-setting-card{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;padding:14px;display:grid;gap:12px;background:var(--p-surface-0,#fff)}
.cms-setting-card.dirty{border-color:var(--p-primary-400,#818cf8);box-shadow:0 0 0 1px color-mix(in srgb,var(--p-primary-400,#818cf8) 30%,transparent)}
.cms-setting-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}.cms-setting-title strong{font-size:15px}.cms-setting-title p{margin:4px 0 0;opacity:.7;line-height:1.7;font-size:12px;max-width:760px}
.cms-setting-meta{display:flex;gap:6px;align-items:center;flex-wrap:wrap}.cms-setting-key{font:11px/1.5 monospace;direction:ltr;background:var(--p-surface-100,#f3f4f6);padding:3px 7px;border-radius:7px}
.cms-setting-control{max-width:760px}.cms-setting-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.cms-setting-actions small{opacity:.65;margin-inline-start:auto}
.cms-switch{display:flex;align-items:center;gap:10px;cursor:pointer;min-height:44px}.cms-switch input{width:22px;height:22px}
.cms-advanced{border-top:1px dashed var(--p-surface-300,#d1d5db);padding-top:10px}.cms-advanced summary{cursor:pointer;font-size:12px;opacity:.75}.cms-advanced-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;margin-top:8px;font-size:11px}.cms-advanced-grid div{padding:8px;background:var(--p-surface-50,#f9fafb);border-radius:8px;overflow-wrap:anywhere}
.cms-empty{text-align:center;padding:36px 16px;border:1px dashed var(--p-surface-300,#d1d5db);border-radius:14px;opacity:.72}
.cms-settings-summary{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
@media(max-width:780px){.cms-settings-shell{grid-template-columns:1fr}.cms-settings-nav{display:none}.cms-settings-mobile-select{display:block}.cms-settings-toolbar{position:static}.cms-setting-card{padding:12px}.cms-settings-section-head h2{font-size:18px}}
`

function groupInfo(id) {
  const meta = GROUPS[id]; return meta ? { label: meta.label(), icon: meta.icon, description: meta.description() } : { label: id || tr('settings_page.other'), icon: 'circle-help', description: tr('settings_page.core_extension_settings') }
}

function cloneDraft(item) {
  if (item.type === 'json') return JSON.stringify(item.value ?? {}, null, 2)
  if (item.type === 'string_list') return Array.isArray(item.value) ? item.value.join(', ') : ''
  return item.value
}

function comparable(item, value) {
  if (item.type === 'json') return JSON.stringify(safeJson(value, {}))
  if (item.type === 'string_list') return JSON.stringify(String(value ?? '').split(',').map(v => v.trim()).filter(Boolean))
  if (item.type === 'boolean') return Boolean(value) ? '1' : '0'
  if (item.type === 'integer' || item.type === 'number' || item.type === 'float') return String(Number(value))
  return String(value ?? '')
}

function timezoneOptions() {
  const fallback = ['UTC','Asia/Tehran','Europe/Helsinki','Europe/Berlin','Europe/London','America/New_York','Asia/Dubai','Asia/Istanbul']
  try {
    const list = Intl.supportedValuesOf?.('timeZone')
    return Array.isArray(list) && list.length ? list : fallback
  } catch { return fallback }
}

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge, LIcon } = host
  return {
    name: 'CmsSettingsCenter',
    data() {
      return { items: [], drafts: {}, originals: {}, activeGroup: 'general', search: '', loading: false, savingAll: false, error: '', notice: '' }
    },
    computed: {
      groups() {
        const set = new Map()
        for (const item of this.items) if (!set.has(item.group)) set.set(item.group, groupInfo(item.group))
        return [...set.entries()].map(([id, meta]) => ({ id, ...meta, count: this.items.filter(i => i.group === id).length }))
      },
      filtered() {
        const q = this.search.trim().toLowerCase()
        return this.items.filter(item => {
          if (!q && item.group !== this.activeGroup) return false
          if (!q) return true
          const keywords = Array.isArray(item.ui?.keywords) ? item.ui.keywords.join(' ') : ''
          return `${item.label} ${item.key} ${item.group} ${item.ui?.description || ''} ${keywords}`.toLowerCase().includes(q)
        })
      },
      dirtyCount() { return this.items.filter(item => this.isDirty(item)).length },
    },
    mounted() { this.load() },
    methods: {
      async load() {
        this.loading = true; this.error = ''; this.notice = ''
        try {
          const data = await api('/settings')
          this.items = (data.items || []).slice().sort((a,b) => (a.group || '').localeCompare(b.group || '') || Number(a.ui?.order || 999) - Number(b.ui?.order || 999))
          this.drafts = {}; this.originals = {}
          for (const item of this.items) {
            const value = cloneDraft(item); this.drafts[item.key] = value; this.originals[item.key] = comparable(item, value)
          }
          if (!this.groups.some(g => g.id === this.activeGroup)) this.activeGroup = this.groups[0]?.id || 'general'
        } catch (e) { this.error = e.message } finally { this.loading = false }
      },
      isDirty(item) { return comparable(item, this.drafts[item.key]) !== this.originals[item.key] },
      valueOf(item) {
        const value = this.drafts[item.key]
        if (item.type === 'boolean') return value === true || value === 'true' || value === 1 || value === '1'
        if (item.type === 'number' || item.type === 'integer' || item.type === 'float') return Number(value)
        if (item.type === 'json') return safeJson(value, {})
        if (item.type === 'string_list') return String(value ?? '').split(',').map(v => v.trim()).filter(Boolean)
        return value
      },
      async save(item, quiet = false) {
        const scope = item.scope || {}
        await api(`/settings/${encodeURIComponent(item.key)}`, { method: 'PUT', body: { value: this.valueOf(item), scope_type: scope.type || 'site', scope_id: scope.id ?? 1, expected_version: item.version || null } })
        if (!quiet) this.notice = tr('settings_page.saved_one','',{label:item.label||item.key})
      },
      async saveAll() {
        const dirty = this.items.filter(item => this.isDirty(item)); if (!dirty.length) return
        this.savingAll = true; this.error = ''; this.notice = ''
        try { for (const item of dirty) await this.save(item, true); this.notice = tr('settings_page.saved_many','',{count:dirty.length}); await this.load() }
        catch (e) { this.error = e.message } finally { this.savingAll = false }
      },
      async saveOne(item) {
        this.error = ''; this.notice = ''
        try { await this.save(item); await this.load() } catch (e) { this.error = e.message }
      },
      async reset(item) {
        this.error = ''; this.notice = ''
        try {
          const scope = item.scope || {}
          await api(`/settings/${encodeURIComponent(item.key)}`, { method: 'DELETE', body: { scope_type: scope.type || 'site', scope_id: scope.id ?? 1, expected_version: item.version || null } })
          this.notice = tr('settings_page.reset_notice','',{label:item.label||item.key}); await this.load()
        } catch (e) { this.error = e.message }
      },
      renderControl(item) {
        const component = item.ui?.component || 'text'; const value = this.drafts[item.key]
        if (component === 'design-tokens') return h('div', { style: ui.card }, [h('p', { style: { margin: 0, opacity: .75 } }, tr('settings_page.design_tokens_runtime_note')), h(LButton, { label: tr('settings_page.go_appearance'), severity: 'secondary', onClick: () => navigate('appearance') })])
        if (item.type === 'boolean' || component === 'boolean') return h('label', { class: 'cms-switch' }, [h('input', { type: 'checkbox', checked: Boolean(value), onChange: e => this.drafts[item.key] = e.target.checked }), h('span', {}, Boolean(value) ? tr('settings_page.enabled') : tr('settings_page.disabled'))])
        if (component === 'textarea') return textarea(h, value, v => this.drafts[item.key] = v, { rows: item.ui?.rows || 3 })
        if (component === 'select' && Array.isArray(item.ui?.options)) return select(h, value, v => this.drafts[item.key] = v, item.ui.options)
        if (component === 'timezone') return select(h, value, v => this.drafts[item.key] = v, timezoneOptions().map(v => ({ value: v, label: v })))
        if (item.type === 'json') return textarea(h, value, v => this.drafts[item.key] = v, { style: ui.mono, rows: 8 })
        if (item.type === 'string_list') return input(h, value, v => this.drafts[item.key] = v, 'text', { placeholder: tr('settings_page.string_list_placeholder') })
        const extra = { min: item.ui?.min, max: item.ui?.max, step: item.ui?.step, placeholder: item.ui?.placeholder }
        return input(h, value, v => this.drafts[item.key] = v, item.sensitive ? 'password' : (item.type === 'integer' || item.type === 'number' || item.type === 'float' ? 'number' : 'text'), extra)
      },
      renderCard(item) {
        const scope = item.scope || {}
        return h('article', { class: `cms-setting-card${this.isDirty(item) ? ' dirty' : ''}`, key: item.key }, [
          h('div', { class: 'cms-setting-top' }, [
            h('div', { class: 'cms-setting-title' }, [h('strong', {}, item.label || item.key), h('p', {}, item.ui?.description || tr('settings_page.registry_description'))]),
            h('div', { class: 'cms-setting-meta' }, [h(LBadge, { label: scope.type || 'global', severity: 'secondary' }), this.isDirty(item) ? h(LBadge, { label: tr('settings_page.unsaved'), severity: 'warning' }) : null]),
          ]),
          h('div', { class: 'cms-setting-control' }, [this.renderControl(item)]),
          item.ui?.suffix ? h('small', { style: { opacity: .7 } }, item.ui.suffix) : null,
          h('div', { class: 'cms-setting-actions' }, [
            h(LButton, { label: tr('settings_page.save'), disabled: !this.isDirty(item), onClick: () => this.saveOne(item) }),
            h(LButton, { label: tr('settings_page.reset'), severity: 'secondary', onClick: () => this.reset(item) }),
            h('small', {}, item.version ? tr('settings_page.version','',{version:item.version}) : tr('settings_page.default_value')),
          ]),
          h('details', { class: 'cms-advanced', open: Boolean(item.ui?.advanced) }, [h('summary', {}, tr('settings_page.technical_details')), h('div', { class: 'cms-advanced-grid' }, [
            h('div', {}, [h('strong', {}, 'Key'), h('br'), h('code', { class: 'cms-setting-key' }, item.key)]),
            h('div', {}, [h('strong', {}, 'Owner'), h('br'), h('span', {}, item.owner || '—')]),
            h('div', {}, [h('strong', {}, 'Type'), h('br'), h('span', {}, item.type)]),
            h('div', {}, [h('strong', {}, 'Scopes'), h('br'), h('span', {}, (item.scopes || []).join(', '))]),
          ])]),
        ])
      },
    },
    render() {
      const activeMeta = groupInfo(this.activeGroup)
      const content = this.loading ? h('p', {role:'status','aria-live':'polite'}, tr('settings_page.loading')) : h('div', { class: 'cms-settings-shell' }, [
        h('aside', { class: 'cms-settings-nav', 'aria-label': tr('settings_page.groups_label') }, this.groups.map(group => h('button', { type: 'button', class: group.id === this.activeGroup ? 'active' : '', onClick: () => { this.activeGroup = group.id; this.search = '' } }, [LIcon ? h(LIcon,{name:group.icon,size:'sm','aria-hidden':'true'}) : h('span',{'aria-hidden':'true'},''), h('span', {}, [h('strong', {}, `${group.label} (${group.count})`), h('small', {}, group.description)])]))),
        h('main', { class: 'cms-settings-section' }, [
          h('select', { class: 'cms-settings-mobile-select', 'aria-label':tr('a11y.settings_group_mobile',tr('settings_page.group_mobile')), style: ui.input, value: this.activeGroup, onChange: e => { this.activeGroup = e.target.value; this.search = '' } }, this.groups.map(g => h('option', { value: g.id }, `${g.label} (${g.count})`))),
          h('div', { class: 'cms-settings-section-head' }, [h('div', {}, [h('h2', {}, this.search ? tr('settings_page.search_results') : activeMeta.label), h('p', {}, this.search ? tr('settings_page.results_for','',{query:this.search}) : activeMeta.description)]), h('div', { class: 'cms-settings-summary' }, [h(LBadge, { label: tr('settings_page.setting_count','',{count:this.filtered.length}), severity: 'secondary' }), this.dirtyCount ? h(LBadge, { label: tr('settings_page.unsaved_count','',{count:this.dirtyCount}), severity: 'warning' }) : h(LBadge, { label: tr('settings_page.all_saved'), severity: 'success' })])]),
          this.filtered.length ? h('div', { style: { display: 'grid', gap: '12px' } }, this.filtered.map(item => this.renderCard(item))) : h('div', { class: 'cms-empty' }, tr('settings_page.empty')),
        ]),
      ])

      return h(LPage,{title:tr('routes.settings.title'),description:tr('routes.settings.lead')}, { default: () => h('div', { class: 'cms-settings-center' }, [h('style', {}, CSS), message(h, this), h('div', { class: 'cms-settings-toolbar' }, [input(h, this.search, v => this.search = v, 'search', { placeholder: tr('settings_page.search_placeholder'), 'aria-label': tr('a11y.settings_search') }), h(LButton, { label: this.savingAll ? tr('settings_page.saving') : `${tr('settings_page.save_all')}${this.dirtyCount ? ` (${this.dirtyCount})` : ''}`, disabled: !this.dirtyCount || this.savingAll, onClick: this.saveAll }), h(LButton, { label: tr('settings_page.refresh'), severity: 'secondary', onClick: this.load })]), content]) })
    },
  }
}
