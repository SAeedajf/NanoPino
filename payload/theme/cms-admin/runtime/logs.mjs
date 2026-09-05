import { api, ui, input, select, message, tr, locale} from './common.mjs'

const CSS = `
.cms-ops{display:grid;gap:16px;direction:inherit}.cms-ops-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.cms-stat{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;padding:14px;background:var(--p-surface-0,#fff);display:grid;gap:5px}.cms-stat small{opacity:.65}.cms-stat strong{font-size:22px}.cms-stat.active strong{color:#dc2626}.cms-stat.ok strong{color:#16a34a}.cms-log-toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.cms-log-toolbar input{min-width:220px;flex:1}.cms-log-list{display:grid;gap:10px}.cms-log-row{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;padding:12px;display:grid;gap:9px;background:var(--p-surface-0,#fff)}.cms-log-row.active{border-color:#ef4444}.cms-log-row historical{opacity:.9}.cms-log-head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap}.cms-log-title{display:flex;gap:7px;align-items:center;flex-wrap:wrap}.cms-log-message{margin:0;line-height:1.75;overflow-wrap:anywhere}.cms-log-id{font:11px/1.5 monospace;direction:ltr;text-align:left;overflow-wrap:anywhere;background:var(--p-surface-100,#f3f4f6);padding:5px 7px;border-radius:7px}.cms-log-details{border-top:1px dashed var(--p-surface-300,#d1d5db);padding-top:9px}.cms-log-details summary{cursor:pointer;font-weight:600}.cms-context{margin-top:8px;direction:ltr;text-align:left;white-space:pre-wrap;overflow:auto;max-height:360px;padding:10px;background:#111827;color:#e5e7eb;border-radius:10px;font:11px/1.6 monospace}.cms-health-strip{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:12px;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px}.cms-privacy{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}.cms-privacy article{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px;padding:12px;display:grid;gap:5px}.cms-privacy small{opacity:.68;line-height:1.6}.cms-empty{text-align:center;padding:34px;border:1px dashed var(--p-surface-300,#d1d5db);border-radius:14px;opacity:.7}@media(max-width:860px){.cms-ops-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.cms-privacy{grid-template-columns:1fr}}@media(max-width:520px){.cms-ops-stats{grid-template-columns:1fr 1fr}.cms-stat strong{font-size:18px}.cms-log-toolbar>*{width:100%}}
`

function levelSeverity(level) {
  if (level === 'error') return 'danger'
  if (level === 'warning') return 'warning'
  if (level === 'info') return 'info'
  return 'secondary'
}
function healthSeverity(status) { return status === 'ok' ? 'success' : status === 'error' ? 'danger' : status === 'warning' ? 'warning' : 'secondary' }
function formatTime(timestamp) {
  const n = Number(timestamp); if (!Number.isFinite(n)) return '—'
  return new Date(n * 1000).toLocaleString(locale())
}
function formatAge(seconds) {
  const s = Math.max(0, Number(seconds) || 0)
  if (s < 60) return tr('logs_page.seconds_ago',':count seconds ago',{count:Math.round(s)})
  if (s < 3600) return tr('logs_page.minutes_ago',':count minutes ago',{count:Math.round(s / 60)})
  if (s < 86400) return tr('logs_page.hours_ago',':count hours ago',{count:Math.round(s / 3600)})
  return tr('logs_page.days_ago',':count days ago',{count:Math.round(s / 86400)})
}
function downloadJson(name, value) {
  const blob = new Blob([JSON.stringify(value, null, 2)], { type: 'application/json;charset=utf-8' })
  const url = URL.createObjectURL(blob); const a = document.createElement('a'); a.href = url; a.download = name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(url), 1000)
}

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge } = host
  return {
    name: 'CmsOperationalHealthCenter',
    data() { return { logs: [], summary: {}, health: {}, privacy: {}, loading: false, exporting: false, error: '', notice: '', search: '', level: '', channel: '', pageSize: 50, activeMinutes: 15, showTechnical: false, selected: null } },
    mounted() { this.loadPreferences().finally(() => this.load()) },
    methods: {
      async loadPreferences() {
        try {
          const data = await api('/settings'); const items = data.items || []
          const byKey = Object.fromEntries(items.map(i => [i.key, i.value]))
          const size = Number(byKey['diagnostics.logs_page_size']); if (Number.isFinite(size)) this.pageSize = Math.max(20, Math.min(200, size))
          const mins = Number(byKey['diagnostics.active_error_window_minutes']); if (Number.isFinite(mins)) this.activeMinutes = Math.max(5, Math.min(1440, mins))
          this.showTechnical = Boolean(byKey['diagnostics.show_technical_details'])
        } catch {}
      },
      async load() {
        this.loading = true; this.error = ''; this.notice = ''
        try {
          const q = new URLSearchParams({ limit: String(this.pageSize), active_window: String(this.activeMinutes * 60) })
          if (this.search.trim()) q.set('q', this.search.trim())
          if (this.level) q.set('level', this.level)
          if (this.channel) q.set('channel', this.channel)
          const data = await api(`/system/logs?${q}`)
          this.logs = data.logs || []; this.summary = data.summary || {}; this.health = data.health || {}; this.privacy = data.privacy || {}
          if (this.selected) this.selected = this.logs.find(row => row.correlation_id === this.selected.correlation_id) || null
        } catch (e) { this.error = e.message } finally { this.loading = false }
      },
      async exportBundle() {
        this.exporting = true; this.error = ''; this.notice = ''
        try { const data = await api('/system/support-bundle', { method: 'POST', body: {} }); downloadJson(`nanopino-support-${new Date().toISOString().replace(/[:.]/g,'-')}.json`, data.bundle || data); this.notice = tr('logs_page.bundle_ready','A safe Support Bundle was created.') }
        catch (e) { this.error = e.message } finally { this.exporting = false }
      },
      toggle(row) { this.selected = this.selected?.correlation_id === row.correlation_id ? null : row },
      contextOf(row) { return JSON.stringify(row.context || {}, null, 2) },
    },
    render() {
      const channels = Array.isArray(this.summary.channels) ? this.summary.channels : []
      const stats = h('div', { class: 'cms-ops-stats' }, [
        h('article', { class: `cms-stat ${Number(this.summary.active_errors || 0) ? 'active' : 'ok'}` }, [h('small', {}, tr('logs_page.active_recent','Active / recent errors')), h('strong', {}, String(this.summary.active_errors ?? 0)), h('span', {}, tr('logs_page.active_window','Last :count minutes',{count:this.activeMinutes}))]),
        h('article', { class: 'cms-stat' }, [h('small', {}, tr('logs_page.historical','Historical errors')), h('strong', {}, String(this.summary.historical_errors ?? 0)), h('span', {}, tr('logs_page.history_retained','Retained for historical review'))]),
        h('article', { class: 'cms-stat' }, [h('small', {}, 'Warning'), h('strong', {}, String(this.summary.warnings ?? 0)), h('span', {}, tr('logs_page.tail_warnings','Warnings present in the tail'))]),
        h('article', { class: 'cms-stat' }, [h('small', {}, 'Health'), h('strong', {}, String(this.health.overall || 'unknown')), h('span', {}, tr('logs_page.available_records',':count records available',{count:this.summary.available_records ?? 0}))]),
      ])

      const list = this.loading ? h('p', {}, tr('logs_page.loading','Loading logs…')) : this.logs.length ? h('div', { class: 'cms-log-list' }, this.logs.map(row => {
        const open = this.selected?.correlation_id === row.correlation_id || this.showTechnical
        return h('article', { class: `cms-log-row ${row.activity || ''}`, key: `${row.timestamp}:${row.correlation_id}:${row.message}` }, [
          h('div', { class: 'cms-log-head' }, [h('div', { class: 'cms-log-title' }, [h(LBadge, { label: row.level || 'log', severity: levelSeverity(row.level) }), h(LBadge, { label: row.activity === 'active' ? tr('logs_page.activity_active','Active / recent') : row.activity === 'historical' ? tr('logs_page.activity_historical','Historical') : tr('logs_page.activity_normal','Normal'), severity: row.activity === 'active' ? 'danger' : 'secondary' }), h('strong', {}, row.channel || 'cms')]), h('div', {}, [h('small', {}, formatTime(row.timestamp)), h('br'), h('small', { style: { opacity: .65 } }, formatAge(row.age_seconds))])]),
          h('p', { class: 'cms-log-message' }, row.message || '—'),
          row.error_id ? h('code', { class: 'cms-log-id' }, row.error_id) : row.correlation_id ? h('code', { class: 'cms-log-id' }, row.correlation_id) : null,
          h('div', { style: { display: 'flex', gap: '8px', flexWrap: 'wrap' } }, [h(LButton, { label: open ? tr('logs_page.close_details','Close details') : tr('logs_page.details','Details'), severity: 'secondary', onClick: () => this.toggle(row) })]),
          open ? h('div', { class: 'cms-log-details' }, [h('div', { style: { display: 'grid', gap: '5px', fontSize: '12px' } }, [row.context?.exception ? h('div', {}, [h('strong', {}, 'Exception: '), h('code', {}, row.context.exception)]) : null, row.context?.source_file ? h('div', {}, [h('strong', {}, 'Source: '), h('code', {}, `${row.context.source_file}:${row.context.source_line || '—'}`)]) : null, h('div', {}, [h('strong', {}, 'Correlation ID: '), h('code', {}, row.correlation_id || '—')])]), h('pre', { class: 'cms-context' }, this.contextOf(row))]) : null,
        ])
      })) : h('div', { class: 'cms-empty' }, tr('logs_page.empty','No Structured Log matches the current filters.'))

      return h(LPage,{title:tr('routes.logs.title','System health & logs'),description:tr('routes.logs.lead','Current health, active errors, history, Correlation ID and safe Support Bundle')}, { default: () => h('div', { class: 'cms-ops' }, [h('style', {}, CSS), message(h, this), stats, h('div', { class: 'cms-health-strip' }, [h(LBadge, { label: `Health: ${this.health.overall || 'unknown'}`, severity: healthSeverity(this.health.overall) }), h('span', {}, Number(this.summary.active_errors || 0) === 0 ? tr('logs_page.no_active','No active/recent error is present in the current tail.') : tr('logs_page.needs_review','Recent errors need review.')), h(LButton, { label: this.exporting ? tr('logs_page.exporting','Building…') : tr('logs_page.support_bundle','Download Support Bundle'), severity: 'secondary', disabled: this.exporting, onClick: this.exportBundle })]), h(LPanel, { title: tr('logs_page.filters','Log filters') }, { default: () => h('div', { class: 'cms-log-toolbar' }, [input(h, this.search, v => this.search = v, 'search', { placeholder: tr('logs_page.search_placeholder','Error ID, Correlation ID, or text…') }), select(h, this.level, v => this.level = v, [{ value: '', label: tr('logs_page.all_levels','All levels') }, { value: 'error', label: 'Error' }, { value: 'warning', label: 'Warning' }, { value: 'info', label: 'Info' }, { value: 'debug', label: 'Debug' }]), select(h, this.channel, v => this.channel = v, [{ value: '', label: tr('logs_page.all_channels','All channels') }, ...channels.map(v => ({ value: v, label: v }))]), h(LButton, { label: tr('logs_page.apply','Apply'), onClick: this.load }), h(LButton, { label: tr('logs_page.refresh','Refresh'), severity: 'secondary', onClick: this.load })]) }), h(LPanel, { title: `Structured Logs (${this.summary.returned_records ?? this.logs.length})` }, { default: () => list }), h(LPanel, { title: 'Diagnostic Privacy Boundary' }, { default: () => h('div', { class: 'cms-privacy' }, [h('article', {}, [h(LBadge, { label: 'REDACT', severity: 'success' }), h('strong', {}, 'Secrets'), h('small', {}, tr('logs_page.privacy_secrets','Password, Token, Authorization, API Key and Private Key are removed before storage.'))]), h('article', {}, [h(LBadge, { label: 'BOUND', severity: 'success' }), h('strong', {}, 'Correlation ID'), h('small', {}, tr('logs_page.privacy_correlation','Used to connect Request → Audit → Log → Queue.'))]), h('article', {}, [h(LBadge, { label: 'NO CONTENT', severity: 'warning' }), h('strong', {}, 'Support Export'), h('small', {}, tr('logs_page.privacy_export','Content/Media bytes and credentials are not included in the diagnostic Bundle.'))])]) })]) })
    },
  }
}
