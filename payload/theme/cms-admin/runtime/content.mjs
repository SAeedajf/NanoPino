import {
  api,
  ui,
  label,
  input,
  textarea,
  select,
  message,
  confirmFa,
  navigate, tr} from './common.mjs'

function validContentId(value) {
  const id = Number(value)
  return Number.isSafeInteger(id) && id > 0 ? String(id) : ''
}
function clone(value){return JSON.parse(JSON.stringify(value))}
function meaningful(value){if(Array.isArray(value))return value.length>0;if(value&&typeof value==='object')return Object.keys(value).length>0;return String(value??'').trim()!==''}

function parseJson(value, labelText) {
  if (value === '' || value === null || value === undefined) return {}
  if (typeof value !== 'string') return value
  try {
    const parsed = JSON.parse(value)
    if (parsed === null || Array.isArray(parsed) || typeof parsed !== 'object') {
      const error = new Error(tr('content_page.json_object_required', '', { field: labelText }))
      error.code = 'cms.content.invalid_json_object'
      throw error
    }
    return parsed
  } catch (error) {
    if (error?.code === 'cms.content.invalid_json_object') throw error
    throw new Error(tr('content_page.invalid_value', '', { field: labelText }))
  }
}

function csvIds(value) {
  if (Array.isArray(value)) return value.map(Number).filter((id) => Number.isSafeInteger(id) && id > 0)
  return String(value || '')
    .split(',')
    .map((item) => Number(item.trim()))
    .filter((id) => Number.isSafeInteger(id) && id > 0)
}

function toLocalDateTime(value) {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  const pad = (v) => String(v).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const styles = `
.cms-content-shell{display:grid;gap:16px}.cms-content-top{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}.cms-content-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}.cms-content-stat{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;padding:12px;display:grid;gap:4px;background:var(--p-surface-0,#fff)}.cms-content-stat strong{font-size:1.35rem}.cms-content-tabs{display:flex;gap:6px;overflow:auto;padding-bottom:2px}.cms-content-tab{border:1px solid var(--p-surface-300,#d1d5db);background:transparent;color:inherit;border-radius:999px;padding:8px 12px;white-space:nowrap;cursor:pointer}.cms-content-tab[data-active="true"]{background:var(--p-primary-color,#2563eb);border-color:var(--p-primary-color,#2563eb);color:#fff}.cms-content-filterbar{display:grid;grid-template-columns:minmax(220px,1fr) 180px 150px auto;gap:8px;align-items:end}.cms-content-bulk{display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px;border:1px dashed var(--p-surface-300,#d1d5db);border-radius:12px}.cms-content-list{display:grid;gap:10px}.cms-content-row{display:grid;grid-template-columns:auto minmax(220px,1.5fr) minmax(120px,.7fr) minmax(120px,.7fr) minmax(260px,1fr);gap:10px;align-items:center;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:14px;padding:12px;background:var(--p-surface-0,#fff)}.cms-content-title{display:grid;gap:4px;min-width:0}.cms-content-title strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.cms-content-title small{opacity:.7;overflow-wrap:anywhere}.cms-content-actions{display:flex;gap:6px;justify-content:flex-end;flex-wrap:wrap}.cms-content-editor{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(260px,.75fr);gap:14px}.cms-content-editor-main,.cms-content-editor-side{display:grid;gap:12px;align-content:start}.cms-content-fieldset{display:grid;gap:10px;padding:12px;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px}.cms-content-fieldset legend{padding:0 6px;font-weight:700}.cms-content-field-label{display:grid;gap:3px}.cms-content-technical{font-size:.78rem;opacity:.72}.cms-content-technical summary{cursor:pointer}.cms-richtext{border:1px solid var(--p-surface-300,#d1d5db);border-radius:12px;overflow:hidden}.cms-richtext-toolbar{display:flex;gap:5px;flex-wrap:wrap;padding:7px;border-bottom:1px solid var(--p-surface-200,#e5e7eb)}.cms-richtext-toolbar button{min-width:42px;min-height:40px;border:1px solid var(--p-surface-300,#d1d5db);border-radius:8px;background:var(--p-surface-0,#fff);color:inherit;cursor:pointer}.cms-richtext-editor{min-height:220px;padding:12px;outline:none;line-height:1.8}.cms-richtext-editor img{max-width:100%;height:auto}.cms-resource-value{display:grid;gap:8px}.cms-resource-chips{display:flex;gap:7px;flex-wrap:wrap}.cms-resource-chip{display:inline-flex;gap:6px;align-items:center;min-height:38px;padding:5px 9px;border:1px solid var(--p-surface-300,#d1d5db);border-radius:999px}.cms-resource-chip img{width:30px;height:30px;object-fit:cover;border-radius:50%}.cms-resource-chip button{border:0;background:transparent;color:inherit;cursor:pointer}.cms-resource-picker-toolbar{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:8px;align-items:end}.cms-resource-picker-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:10px}.cms-resource-picker-item{min-height:72px;display:grid;grid-template-columns:54px minmax(0,1fr) auto;gap:8px;align-items:center;padding:8px;border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px;background:var(--p-surface-0,#fff);color:inherit;text-align:start;cursor:pointer}.cms-resource-picker-item[data-selected="true"]{border-color:var(--p-primary-color,#2563eb)}.cms-resource-picker-item img{width:54px;height:54px;object-fit:cover;border-radius:8px}.cms-content-empty{padding:28px 16px;text-align:center;border:1px dashed var(--p-surface-300,#d1d5db);border-radius:14px}.cms-content-pagination{display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap}.cms-content-check{width:20px;height:20px}.cms-content-muted{opacity:.7;font-size:.86rem}.cms-content-advanced{border:1px solid var(--p-surface-200,#e5e7eb);border-radius:12px;padding:10px}.cms-content-advanced summary{cursor:pointer;font-weight:600}.cms-content-statusline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
@media(max-width:960px){.cms-content-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.cms-content-filterbar{grid-template-columns:1fr 1fr}.cms-content-editor{grid-template-columns:1fr}.cms-content-row{grid-template-columns:auto 1fr}.cms-content-row>[data-mobile-hide="true"]{display:none}.cms-content-actions{grid-column:2;justify-content:flex-start}}
@media(max-width:600px){.cms-content-filterbar,.cms-resource-picker-toolbar{grid-template-columns:1fr}.cms-content-stats{grid-template-columns:1fr 1fr}.cms-content-row{padding:10px}.cms-content-actions{grid-column:1 / -1}.cms-content-top>*{width:100%}.cms-content-top .cms-content-actions{width:auto}}
`

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge } = host

  return {
    name: 'CmsContentAdminCenter',
    data() {
      return {
        items: [],
        types: [],
        pagination: { limit: 50, offset: 0, returned: 0, has_more: false },
        loading: false,
        saving: false,
        bulkBusy: false,
        error: '',
        notice: '',
        search: '',
        status: '',
        type: '',
        locale: '',
        selected: [],
        bulkAction: '',
        editorOpen: false,
        editing: null,
        scheduleAt: '',
        previousType: 'post',
        parentLabel: '',
        resourceCache: {},
        picker: { open:false, kind:'', fieldKey:'', title:'', query:'', items:[], loading:false, error:'', multiple:false, taxonomy:'', targetTypes:[], selected:[], pagination:{limit:24,offset:0,total:0,has_more:false}, richField:'' },
        form: {
          site_id: 1, type: 'post', title: '', slug: '', excerpt: '', locale: 'fa', parent_id: '', fields: {}, metadataJson: '{}',
        },
      }
    },
    mounted() {
      this.resetForm()
      this.load()
    },
    methods: {
      emptyForm(typeKey = '') {
        const resolved = typeKey || this.types?.[0]?.key || 'post'
        const descriptor = this.types?.find((item) => item.key === resolved)
        const fields = {}
        for (const field of descriptor?.fields || []) {
          if (field.default !== null && field.default !== undefined) fields[field.key] = field.multiple && !Array.isArray(field.default) ? [field.default] : field.default
          else if (field.multiple) fields[field.key] = []
          else if (field.type === 'boolean') fields[field.key] = false
          else fields[field.key] = ''
        }
        return { site_id: 1, type: resolved, title: '', slug: '', excerpt: '', locale: 'fa', parent_id: '', fields, metadataJson: '{}' }
      },
      resetForm(typeKey = '') {
        this.form = this.emptyForm(typeKey)
        this.editing = null
        this.scheduleAt = ''
        this.previousType = this.form.type
        this.parentLabel = ''
        this.closePicker()
      },
      draftSignature() {
        return JSON.stringify({ ...clone(this.form), scheduleAt: this.scheduleAt })
      },
      async load(resetOffset = false) {
        if (resetOffset) this.pagination.offset = 0
        this.loading = true
        this.error = ''
        try {
          const q = new URLSearchParams({
            limit: String(this.pagination.limit || 50),
            offset: String(this.pagination.offset || 0),
          })
          if (this.search.trim()) q.set('search', this.search.trim())
          if (this.status) q.set('status', this.status)
          if (this.type) q.set('type', this.type)
          if (this.locale.trim()) q.set('locale', this.locale.trim())
          const result = await api(`/content?${q}`)
          this.items = result.items || []
          this.types = result.types || this.types || []
          this.pagination = { ...this.pagination, ...(result.pagination || {}) }
          this.selected = this.selected.filter((id) => this.items.some((item) => validContentId(item.id) === id))
          if (!this.form?.type || !this.types.some((item) => item.key === this.form.type)) this.resetForm(this.types?.[0]?.key || 'post')
        } catch (e) {
          this.error = e.message
        } finally {
          this.loading = false
        }
      },
      typeDescriptor(key = this.form.type) {
        return this.types.find((item) => item.key === key) || null
      },
      typeLabel(key) {
        return this.types.find((item) => item.key === key)?.singular_label || this.types.find((item) => item.key === key)?.label || key || '—'
      },
      statusLabel(value) {
        return ({ draft: tr('content_page.draft'), published: tr('content_page.published'), scheduled: tr('content_page.scheduled_full'), trash: tr('content_page.trash_short') })[value] || value || tr('content_page.unknown')
      },
      statusSeverity(value) {
        if (value === 'published') return 'success'
        if (value === 'scheduled') return 'info'
        if (value === 'trash') return 'danger'
        return 'secondary'
      },
      statusCount(value) {
        return this.items.filter((item) => item.status === value).length
      },
      startCreate() {
        if(this.status==='trash'){this.status='';this.notice=tr('content_page.create_left_trash')}
        this.resetForm(this.type || this.types?.[0]?.key || 'post')
        this.editorOpen = true
        this.error = ''
        globalThis.window?.scrollTo?.({ top: 0, behavior: 'smooth' })
      },
      fieldValueFromItem(item, field) {
        if (field.storage === 'document') return item.document?.[field.key] ?? field.default ?? ''
        if (field.storage === 'relation') return item.relations?.[field.key] ?? []
        if (field.storage === 'taxonomy') return item.terms?.[field.taxonomy || field.key] ?? item.terms?.[field.key] ?? []
        return item.fields?.[field.key] ?? field.default ?? (field.multiple ? [] : '')
      },
      edit(item) {
        const id = validContentId(item?.id)
        if (!id) return
        const descriptor = this.typeDescriptor(item.type)
        const fields = {}
        for (const field of descriptor?.fields || []) fields[field.key] = this.fieldValueFromItem(item, field)
        this.editing = id
        this.previousType = item.type || this.types?.[0]?.key || 'post'
        this.parentLabel = ''
        this.form = {
          site_id: item.site_id || 1,
          type: item.type || this.types?.[0]?.key || 'post',
          title: item.title || '',
          slug: item.slug || '',
          excerpt: item.excerpt || '',
          locale: item.locale || 'fa',
          parent_id: item.parent_id ? String(item.parent_id) : '',
          fields,
          metadataJson: JSON.stringify(item.metadata ?? {}, null, 2),
        }
        this.scheduleAt = toLocalDateTime(item.scheduled_at)
        this.editorOpen = true
        this.error = ''
        this.notice = ''
        if(this.form.parent_id)this.hydrateParent(this.form.parent_id)
        this.hydrateMediaFields()
        globalThis.window?.scrollTo?.({ top: 0, behavior: 'smooth' })
      },
      closeEditor() {
        this.editorOpen = false
        this.resetForm(this.type || this.types?.[0]?.key || 'post')
      },
      changeFormType(value) {
        if (this.editing) return
        const next = value || this.previousType
        const previous = this.previousType || this.form.type || next
        if (next === previous) return
        const previousDescriptor = this.typeDescriptor(previous)
        const nextDescriptor = this.typeDescriptor(next)
        const nextKeys = new Set((nextDescriptor?.fields || []).map((field) => field.key))
        const removed = (previousDescriptor?.fields || []).filter((field) => !nextKeys.has(field.key) && meaningful(this.form.fields?.[field.key]))
        const dropsParent = Boolean(this.form.parent_id) && !nextDescriptor?.hierarchical
        if ((removed.length || dropsParent) && !confirmFa(tr('content_page.change_type_confirm', '', { count: removed.length + (dropsParent ? 1 : 0) }))) return
        const current = clone(this.form)
        const fields = {}
        for (const field of nextDescriptor?.fields || []) {
          if (Object.prototype.hasOwnProperty.call(current.fields || {}, field.key)) fields[field.key] = current.fields[field.key]
          else if (field.default !== null && field.default !== undefined) fields[field.key] = field.multiple && !Array.isArray(field.default) ? [field.default] : field.default
          else if (field.multiple) fields[field.key] = []
          else if (field.type === 'boolean') fields[field.key] = false
          else fields[field.key] = ''
        }
        this.form = { ...current, type: next, parent_id: nextDescriptor?.hierarchical ? current.parent_id : '', fields }
        this.previousType = next
      },
      normalizeField(field, value) {
        if (field.multiple || ['relation', 'gallery', 'taxonomy'].includes(field.type)) return csvIds(value)
        if (field.type === 'media') {
          if (value === '' || value === null || value === undefined) return null
          const id = Number(value)
          return Number.isSafeInteger(id) && id > 0 ? id : value
        }
        if (field.type === 'number') return value === '' ? null : Number(value)
        if (field.type === 'boolean') return Boolean(value)
        if (['json', 'repeater', 'group'].includes(field.type) && typeof value === 'string') {
          try { return JSON.parse(value || (field.type === 'repeater' ? '[]' : '{}')) } catch { throw new Error(tr('content_page.json_invalid','',{field:field.label||field.key})) }
        }
        return value
      },
      payload() {
        const descriptor = this.typeDescriptor()
        if (!descriptor) throw new Error(tr('content_page.type_required'))
        if (!String(this.form.title || '').trim()) throw new Error(tr('content_page.title_required'))
        const fields = {}
        for (const field of descriptor.fields || []) {
          const value = this.form.fields?.[field.key]
          const blank = value === '' || value === null || value === undefined
          if (!this.editing && blank && !field.required) continue
          fields[field.key] = this.normalizeField(field, value)
        }
        return {
          site_id: Number(this.form.site_id || 1),
          type: this.form.type,
          title: String(this.form.title || '').trim(),
          slug: String(this.form.slug || '').trim(),
          excerpt: String(this.form.excerpt || ''),
          locale: String(this.form.locale || 'fa').trim() || 'fa',
          parent_id: this.form.parent_id ? Number(this.form.parent_id) : null,
          fields,
          metadata: parseJson(this.form.metadataJson, 'Metadata'),
        }
      },
      async save(publishAfter = false) {
        if (this.saving) return
        this.saving = true
        this.error = ''
        this.notice = ''
        const submittedDraft = this.draftSignature()
        try {
          const payload = this.payload()
          const editingId = validContentId(this.editing)
          const record = editingId
            ? await api(`/content/${editingId}`, { method: 'PUT', body: payload })
            : await api('/content', { method: 'POST', body: payload })
          const id = validContentId(record?.id || editingId)
          // Retain the committed record before publishing: a failed publish must retry an update.
          if (id) {
            this.editing = id
            const index = this.items.findIndex((item) => validContentId(item.id) === id)
            const saved = { ...(index >= 0 ? this.items[index] : payload), ...record, id: Number(id) }
            if (index >= 0) this.items.splice(index, 1, saved)
            else this.items.unshift(saved)
          }
          const currentStatus = record?.status || this.items.find((item) => validContentId(item.id) === id)?.status || 'draft'
          if (publishAfter && id && currentStatus !== 'published') await api(`/content/${id}/publish`, { method: 'POST', body: {} })
          this.notice = publishAfter ? (currentStatus === 'published' ? tr('content_page.saved_published') : tr('content_page.saved_publish')) : tr('content_page.saved')
          const unchanged = this.draftSignature() === submittedDraft
          if (unchanged) {
            this.editorOpen = false
            this.resetForm(this.type || this.types?.[0]?.key || 'post')
          } else {
            this.notice = tr('content_page.saved_newer_draft')
          }
          await this.load()
        } catch (e) {
          this.error = e.message
        } finally {
          this.saving = false
        }
      },
      async schedule() {
        const id = validContentId(this.editing)
        if (!id) return
        if (this.saving) return
        if (!this.scheduleAt) { this.error = tr('content_page.schedule_required'); return }
        this.saving = true
        this.error = ''
        this.notice = ''
        const submittedDraft = this.draftSignature()
        const publishAt = new Date(this.scheduleAt).toISOString()
        try {
          const payload = this.payload()
          const record = await api(`/content/${id}`, { method: 'PUT', body: payload })
          const index = this.items.findIndex((item) => validContentId(item.id) === id)
          if (index >= 0) this.items.splice(index, 1, { ...this.items[index], ...record, id: Number(id) })
          await api(`/content/${id}/schedule`, { method: 'POST', body: { publish_at: publishAt } })
          this.notice = this.draftSignature() === submittedDraft ? tr('content_page.scheduled_notice') : tr('content_page.scheduled_newer_draft')
          await this.load()
        } catch (e) { this.error = e.message }
        finally { this.saving = false }
      },
      async act(id, action, skipConfirm = false) {
        const contentId = validContentId(id)
        if (!contentId) return
        this.error = ''
        try {
          if (action === 'trash') {
            if (!skipConfirm && !confirmFa(tr('content_page.trash_confirm'))) return
            await api(`/content/${contentId}`, { method: 'DELETE' })
          } else {
            await api(`/content/${contentId}/${action}`, { method: 'POST', body: {} })
          }
          this.notice = tr('content_page.operation_done')
          await this.load()
        } catch (e) { this.error = e.message }
      },
      toggleSelected(id) {
        if (this.bulkBusy) return
        const value = validContentId(id)
        if (!value) return
        this.selected = this.selected.includes(value) ? this.selected.filter((item) => item !== value) : [...this.selected, value]
      },
      toggleAll() {
        if (this.bulkBusy) return
        const ids = this.items.map((item) => validContentId(item.id)).filter(Boolean)
        const all = ids.length > 0 && ids.every((id) => this.selected.includes(id))
        this.selected = all ? [] : ids
      },
      async runBulk() {
        if (this.bulkBusy) return
        const action = this.bulkAction
        const ids = [...new Set(this.selected.map(validContentId).filter(Boolean))]
        if (!['publish', 'restore', 'trash'].includes(action) || !ids.length) return
        const labelText = ({ publish: tr('content_page.bulk_publish'), trash: tr('content_page.bulk_trash'), restore: tr('content_page.bulk_restore') })[action]
        if (!confirmFa(tr('content_page.bulk_action_confirm','',{action:labelText,count:ids.length}))) return
        this.bulkBusy = true
        this.error = ''
        this.notice = ''
        const completed = new Set()
        let failure = ''
        try {
          for (const id of ids) {
            if (action === 'trash') await api(`/content/${id}`, { method: 'DELETE' })
            else await api(`/content/${id}/${action}`, { method: 'POST', body: {} })
            completed.add(id)
          }
          this.notice = tr('content_page.processed_success','',{count:completed.size})
        } catch (e) {
          failure = `${e.message} (${tr('content_page.before_error','',{count:completed.size})})`
        } finally {
          // Successful rows must not be submitted again after a partial failure.
          this.selected = this.selected.filter((id) => !completed.has(validContentId(id)))
          await this.load()
          if (failure) this.error = this.error ? `${failure} — ${this.error}` : failure
          this.bulkBusy = false
        }
      },
      nextPage() {
        if (!this.pagination.has_more || this.loading) return
        this.pagination.offset += this.pagination.limit
        this.load()
      },
      prevPage() {
        if (this.pagination.offset <= 0 || this.loading) return
        this.pagination.offset = Math.max(0, this.pagination.offset - this.pagination.limit)
        this.load()
      },
      resourceKey(kind, id) { return kind + ':' + id },
      resourceInfo(kind, id) { return this.resourceCache[this.resourceKey(kind,id)] || null },
      rememberResource(kind, item) {
        if(!item?.id)return
        this.resourceCache={...this.resourceCache,[this.resourceKey(kind,item.id)]:{...item}}
      },
      fieldHint(field) {
        return ({richtext:tr('content_page.hint_richtext'),media:tr('content_page.hint_media'),gallery:tr('content_page.hint_gallery'),relation:tr('content_page.hint_relation'),taxonomy:tr('content_page.hint_taxonomy'),textarea:tr('content_page.hint_textarea'),json:tr('content_page.hint_structured'),repeater:tr('content_page.hint_structured'),group:tr('content_page.hint_structured')})[field.type]||tr('content_page.hint_standard')
      },
      selectedIds(field){return csvIds(this.form.fields?.[field.key])},
      selectedResourceLabel(field,id){return field.type==='taxonomy'?(this.resourceInfo('taxonomy',id)?.name||tr('content_page.term_item','',{id})):(this.resourceInfo('content',id)?.title||tr('content_page.content_item','',{id}))},
      removeFieldSelection(field,id){
        const current=this.selectedIds(field).filter(value=>String(value)!==String(id))
        this.form.fields={...(this.form.fields||{}),[field.key]:field.type==='media'&&!field.multiple?'':current}
      },
      clearParent(){this.form.parent_id='';this.parentLabel=''},
      async hydrateParent(id){if(!id)return;try{const row=await api('/content/'+id);this.rememberResource('content',row);this.parentLabel=row?.title||tr('content_page.parent_item','',{id})}catch{}},
      hydrateMediaFields(){
        for(const field of (this.typeDescriptor()?.fields||[]).filter(row=>row.type==='media'||row.type==='gallery')){
          for(const id of this.selectedIds(field).slice(0,20)){
            if(this.resourceInfo('media',id))continue
            api('/media/'+id).then(row=>this.rememberResource('media',row)).catch(()=>{})
          }
        }
      },
      safeUrl(value){
        try{const url=new URL(String(value||''),globalThis.location?.origin||'http://localhost');return ['http:','https:'].includes(url.protocol)?url.href:''}catch{return''}
      },
      safeRichHtml(value){
        const html=String(value||'')
        if(typeof DOMParser==='undefined')return html.replace(/</g,'&lt;').replace(/>/g,'&gt;')
        const doc=new DOMParser().parseFromString('<div>'+html+'</div>','text/html'),root=doc.body.firstElementChild
        const allowed=new Set(['DIV','P','BR','STRONG','B','EM','I','U','S','UL','OL','LI','A','H2','H3','H4','BLOCKQUOTE','CODE','PRE','IMG'])
        for(const el of [...root.querySelectorAll('*')]){
          if(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','SVG','MATH'].includes(el.tagName)){el.remove();continue}
          if(!allowed.has(el.tagName)){el.replaceWith(...el.childNodes);continue}
          const originalHref=el.getAttribute?.('href')||'',originalSrc=el.getAttribute?.('src')||'',originalAlt=el.getAttribute?.('alt')||''
          for(const attr of [...el.attributes])el.removeAttribute(attr.name)
          if(el.tagName==='A'){const href=this.safeUrl(originalHref);if(href){el.setAttribute('href',href);el.setAttribute('rel','noopener noreferrer')}}
          if(el.tagName==='IMG'){const src=this.safeUrl(originalSrc);if(!src){el.remove();continue}el.setAttribute('src',src);el.setAttribute('alt',originalAlt.slice(0,300))}
        }
        return root.innerHTML
      },
      richEditor(fieldKey){return globalThis.document?.querySelector?.('[data-rich-field="'+fieldKey+'"]')||null},
      syncRichEditor(fieldKey){const el=this.richEditor(fieldKey);if(el)this.form.fields={...(this.form.fields||{}),[fieldKey]:el.innerHTML}},
      pastePlainText(fieldKey,event){event.preventDefault();const value=event.clipboardData?.getData('text/plain')||'';globalThis.document?.execCommand?.('insertText',false,value);this.syncRichEditor(fieldKey)},
      formatRichText(fieldKey,command,value=null){const el=this.richEditor(fieldKey);if(!el)return;el.focus();globalThis.document?.execCommand?.(command,false,value);this.syncRichEditor(fieldKey)},
      createRichLink(fieldKey){const raw=globalThis.prompt?.(tr('content_page.link_prompt'),'https://');if(!raw)return;const href=this.safeUrl(raw);if(!href){this.error=tr('content_page.link_invalid');return}this.formatRichText(fieldKey,'createLink',href)},
      closePicker(){this.picker={open:false,kind:'',fieldKey:'',title:'',query:'',items:[],loading:false,error:'',multiple:false,taxonomy:'',targetTypes:[],selected:[],pagination:{limit:24,offset:0,total:0,has_more:false},richField:''}},
      openPicker(config){
        this.closePicker()
        this.picker={...this.picker,open:true,...config,selected:(config.selected||[]).map(String),pagination:{limit:24,offset:0,total:0,has_more:false}}
        this.$nextTick?.(()=>this.loadPicker(true))
      },
      openFieldPicker(field){
        const current=this.selectedIds(field)
        if(field.type==='media')return this.openPicker({kind:'media',fieldKey:field.key,title:field.label,multiple:Boolean(field.multiple),selected:current})
        if(field.type==='gallery')return this.openPicker({kind:'media',fieldKey:field.key,title:field.label,multiple:true,selected:current})
        if(field.type==='relation')return this.openPicker({kind:'content',fieldKey:field.key,title:field.label,multiple:Boolean(field.multiple),selected:current,targetTypes:field.target_types||[]})
        if(field.type==='taxonomy')return this.openPicker({kind:'taxonomy',fieldKey:field.key,title:field.label,multiple:Boolean(field.multiple),selected:current,taxonomy:field.taxonomy||''})
      },
      openRichMedia(field){this.openPicker({kind:'rich-media',fieldKey:field.key,title:tr('content_page.insert_image'),multiple:false,richField:field.key})},
      openParentPicker(){this.openPicker({kind:'parent',fieldKey:'parent_id',title:tr('content_page.choose_parent'),multiple:false,selected:this.form.parent_id?[String(this.form.parent_id)]:[]})},
      fieldControl(field) {
        const value = this.form.fields?.[field.key]
        const set = (next) => { this.form.fields = { ...(this.form.fields || {}), [field.key]: next } }
        if (field.type === 'boolean') {
          return h('input', { type: 'checkbox', checked: Boolean(value), style: { width: '22px', height: '22px' }, onChange: (e) => set(e.target.checked) })
        }
        if (field.type === 'select' && field.choices && Object.keys(field.choices).length) {
          return select(h, value ?? '', set, Object.entries(field.choices).map(([key, text]) => ({ value: key, label: String(text) })))
        }
        if (['richtext', 'textarea'].includes(field.type)) return textarea(h, value ?? '', set, { rows: field.type === 'richtext' ? 10 : 5 })
        if (['json', 'repeater', 'group'].includes(field.type)) return textarea(h, typeof value === 'string' ? value : JSON.stringify(value ?? (field.type === 'repeater' ? [] : {}), null, 2), set, { style: ui.mono })
        if (field.multiple || ['relation', 'gallery', 'taxonomy'].includes(field.type)) return input(h, Array.isArray(value) ? value.join(', ') : value ?? '', set, 'text', { placeholder: tr('content_page.ids_csv') })
        if (['number', 'media'].includes(field.type)) return input(h, value ?? '', set, 'number', { min: field.type === 'media' ? '1' : undefined })
        if (field.type === 'date') return input(h, toLocalDateTime(value), set, 'datetime-local')
        return input(h, value ?? '', set)
      },
    },
    render() {
      const descriptor = this.typeDescriptor()
      const typeOptions = [{ value: '', label: tr('content_page.all_types') }, ...this.types.map((item) => ({ value: item.key, label: item.label || item.key }))]
      const currentTypeOptions = this.types.map((item) => ({ value: item.key, label: item.singular_label || item.label || item.key }))
      const parentOptions = [{ value: '', label: tr('content_page.no_parent') }, ...this.items
        .filter((item) => item.type === this.form.type && validContentId(item.id) !== validContentId(this.editing))
        .map((item) => ({ value: validContentId(item.id), label: `${item.title || tr('content_page.untitled','',{id:item.id})} · #${item.id}` }))]
      const allSelected = this.items.length > 0 && this.items.every((item) => this.selected.includes(validContentId(item.id)))

      const editor = this.editorOpen
        ? h(LPanel, { title: this.editing ? tr('content_page.edit','',{type:this.typeLabel(this.form.type),id:this.editing}) : tr('content_page.add_type','',{type:this.typeLabel(this.form.type)}) }, {
            default: () => h('div', { class: 'cms-content-editor' }, [
              h('div', { class: 'cms-content-editor-main' }, [
                label(h, tr('content_page.title'), input(h, this.form.title, (v) => (this.form.title = v), 'text', { maxlength: '255', placeholder: tr('content_page.title_placeholder') })),
                label(h, tr('content_page.excerpt'), textarea(h, this.form.excerpt, (v) => (this.form.excerpt = v), { rows: 4, maxlength: '2000' })),
                ...(descriptor?.fields || []).map((field) => h('div', { class: 'cms-content-fieldset', key: field.key }, [
                  label(h, `${field.label}${field.required ? ' *' : ''}`, this.fieldControl(field)),
                  h('small', { class: 'cms-content-muted' }, `${field.type} · ${field.storage}${field.multiple ? ` · ${tr('content_page.multiple')}` : ''}`),
                ])),
                h('details', { class: 'cms-content-advanced' }, [
                  h('summary', {}, tr('content_page.advanced')),
                  h('div', { style: { ...ui.page, marginTop: '10px' } }, [
                    label(h, 'Metadata JSON', textarea(h, this.form.metadataJson, (v) => (this.form.metadataJson = v), { style: ui.mono, rows: 7 })),
                  ]),
                ]),
              ]),
              h('aside', { class: 'cms-content-editor-side' }, [
                h('fieldset', { class: 'cms-content-fieldset' }, [
                  h('legend', {}, tr('content_page.publishing')),
                  this.editing ? h('div', { class: 'cms-content-statusline' }, [h('span', {}, `${tr('content_page.status')}:`), h(LBadge, { label: this.statusLabel(this.items.find((i) => validContentId(i.id) === validContentId(this.editing))?.status || 'draft') })]) : h('small', { class: 'cms-content-muted' }, tr('content_page.new_is_draft')),
                  h('div', { class: 'cms-content-actions' }, [
                    h(LButton, { label: this.saving ? tr('content_page.saving') : (this.editing ? tr('content_page.save_changes') : tr('content_page.save_draft')), disabled: this.saving, onClick: () => this.save(false) }),
                    this.items.find((i) => validContentId(i.id) === validContentId(this.editing))?.status !== 'trash'
                      ? h(LButton, { label: this.items.find((i) => validContentId(i.id) === validContentId(this.editing))?.status === 'published' ? tr('content_page.save_published') : tr('content_page.save_publish'), disabled: this.saving, onClick: () => this.save(true) })
                      : null,
                    h(LButton, { label: tr('content_page.cancel'), severity: 'secondary', onClick: this.closeEditor }),
                  ]),
                  this.editing ? h('div', { style: ui.page }, [
                    label(h, tr('content_page.schedule_label'), input(h, this.scheduleAt, (v) => (this.scheduleAt = v), 'datetime-local')),
                    h(LButton, { label: tr('content_page.schedule_action'), severity: 'secondary', onClick: this.schedule }),
                  ]) : null,
                ]),
                h('fieldset', { class: 'cms-content-fieldset' }, [
                  h('legend', {}, tr('content_page.properties')),
                  label(h, tr('content_page.content_type'), select(h, this.form.type, this.changeFormType, currentTypeOptions)),
                  label(h, tr('content_page.slug'), input(h, this.form.slug, (v) => (this.form.slug = v), 'text', { dir: 'ltr', placeholder: tr('content_page.slug_auto') })),
                  label(h, tr('content_page.language'), input(h, this.form.locale, (v) => (this.form.locale = v), 'text', { dir: 'ltr', maxlength: '16' })),
                  descriptor?.hierarchical ? label(h, tr('content_page.parent'), select(h, this.form.parent_id, (v) => (this.form.parent_id = v), parentOptions)) : null,
                  h('small', { class: 'cms-content-muted' }, `Revision: ${descriptor?.revisions ? tr('content_page.enabled') : tr('content_page.disabled')} · Taxonomy: ${(descriptor?.taxonomies || []).join(tr('common.list_separator')) || tr('content_page.none')}`),
                ]),
              ]),
            ]),
          })
        : null

      const rows = this.loading
        ? h('div', { class: 'cms-content-empty' }, tr('state.loading_message'))
        : this.items.length === 0
          ? h('div', { class: 'cms-content-empty' }, [h('strong', {}, tr('content_page.empty_title')), h('p', { class: 'cms-content-muted' }, tr('content_page.empty_message')), h(LButton, { label: tr('content_page.add'), onClick: this.startCreate })])
          : h('div', { class: 'cms-content-list' }, this.items.map((item) => {
              const id = validContentId(item.id)
              return h('article', { class: 'cms-content-row', key: id }, [
                h('input', { class: 'cms-content-check', type: 'checkbox', disabled: this.bulkBusy, checked: this.selected.includes(id), 'aria-label': `${tr('a11y.select_content')}: ${item.title || id}`, onChange: () => this.toggleSelected(id) }),
                h('div', { class: 'cms-content-title' }, [
                  h('strong', {}, item.title || tr('content_page.untitled','',{id})),
                  h('small', {}, `${item.slug || tr('content_page.no_slug')} · #${id}`),
                  h('small', { class: 'cms-content-muted' }, item.updated_at ? tr('content_page.last_change','',{date:String(item.updated_at)}) : ''),
                ]),
                h('div', { 'data-mobile-hide': 'true' }, this.typeLabel(item.type)),
                h('div', { 'data-mobile-hide': 'true' }, [h(LBadge, { label: this.statusLabel(item.status), severity: this.statusSeverity(item.status) })]),
                h('div', { class: 'cms-content-actions' }, [
                  h(LButton, { label: tr('content_page.edit').split(' :type')[0], onClick: () => this.edit(item) }),
                  id ? h(LButton, { label: tr('content_page.history'), severity: 'secondary', onClick: () => navigate(`revisions?content=${id}`) }) : null,
                  item.status !== 'published' && item.status !== 'trash' ? h(LButton, { label: tr('content_page.bulk_publish'), onClick: () => this.act(id, 'publish') }) : null,
                  item.status === 'trash'
                    ? h(LButton, { label: tr('content_page.restore'), onClick: () => this.act(id, 'restore') })
                    : h(LButton, { label: tr('content_page.trash_short'), severity: 'danger', onClick: () => this.act(id, 'trash') }),
                ]),
              ])
            }))

      return h(LPage,{title:tr('routes.content.title'),description:tr('routes.content.lead')}, {
        default: () => h('div', { class: 'cms-content-shell' }, [
          h('style', {}, styles),
          message(h, this),
          h('div', { class: 'cms-content-top' }, [
            h('div', {}, [h('strong', {}, tr('content_page.admin_center')), h('div', { class: 'cms-content-muted' }, tr('content_page.daily_management'))]),
            h('div', { class: 'cms-content-actions' }, [
              h(LButton, { label: tr('content_page.refresh'), severity: 'secondary', disabled: this.loading, onClick: () => this.load() }),
              h(LButton, { label: tr('content_page.add'), onClick: this.startCreate }),
            ]),
          ]),
          h('div', { class: 'cms-content-stats' }, [
            h('div', { class: 'cms-content-stat' }, [h('small', {}, tr('content_page.current_view')), h('strong', {}, String(this.items.length)), h('span', { class: 'cms-content-muted' }, tr('content_page.current_offset','',{offset:this.pagination.offset}))]),
            h('div', { class: 'cms-content-stat' }, [h('small', {}, tr('content_page.published_page')), h('strong', {}, String(this.statusCount('published')))]),
            h('div', { class: 'cms-content-stat' }, [h('small', {}, tr('content_page.draft_page')), h('strong', {}, String(this.statusCount('draft')))]),
            h('div', { class: 'cms-content-stat' }, [h('small', {}, tr('content_page.active_type')), h('strong', {}, String(this.types.length))]),
          ]),
          h('div', { class: 'cms-content-tabs', role: 'tablist', 'aria-label': tr('a11y.content_status') }, [
            ...[
              ['', tr('content_page.all')], ['draft', tr('content_page.draft')], ['scheduled', tr('content_page.scheduled')], ['published', tr('content_page.published')], ['trash', tr('content_page.trash_short')],
            ].map(([value, text]) => {
              const selected = this.status === value
              return h('button', {
                type: 'button', role: 'tab', class: 'cms-content-tab', 'data-active': String(selected),
                'aria-selected': String(selected), tabindex: selected ? 0 : -1,
                onClick: () => { this.status = value; this.load(true) },
                onKeydown: (event) => {
                  if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return
                  event.preventDefault()
                  const tabs = [...(event.currentTarget?.parentElement?.querySelectorAll('[role=\"tab\"]') || [])]
                  const current = tabs.indexOf(event.currentTarget)
                  const rtl = document.documentElement.dir === 'rtl'
                  let delta = event.key === 'ArrowRight' ? 1 : -1
                  if (rtl) delta *= -1
                  const next = tabs[(current + delta + tabs.length) % tabs.length]
                  next?.focus(); next?.click()
                },
              }, text)
            }),
          ]),
          editor,
          h(LPanel, { title: tr('content_page.list') }, {
            default: () => h('div', { style: ui.page }, [
              h('div', { class: 'cms-content-filterbar' }, [
                label(h, tr('content_page.search'), input(h, this.search, (v) => (this.search = v), 'search', { placeholder: tr('content_page.search_placeholder'), onKeyup: (e) => { if (e.key === 'Enter') this.load(true) } })),
                label(h, tr('content_page.type'), select(h, this.type, (v) => (this.type = v), typeOptions)),
                label(h, tr('content_page.language'), input(h, this.locale, (v) => (this.locale = v), 'text', { placeholder: 'fa', dir: 'ltr' })),
                h(LButton, { label: tr('content_page.apply_filter'), disabled: this.loading, onClick: () => this.load(true) }),
              ]),
              h('div', { class: 'cms-content-bulk' }, [
                h('input', { class: 'cms-content-check', type: 'checkbox', disabled: this.bulkBusy, checked: allSelected, onChange: this.toggleAll, 'aria-label': tr('a11y.select_all_content') }),
                h('strong', {}, tr('content_page.selected','',{count:this.selected.length})),
                select(h, this.bulkAction, (v) => (this.bulkAction = v), [
                  { value: '', label: tr('content_page.bulk_action') },
                  { value: 'publish', label: tr('content_page.bulk_publish') },
                  { value: 'restore', label: tr('content_page.bulk_restore') },
                  { value: 'trash', label: tr('content_page.bulk_trash') },
                ], { disabled: this.bulkBusy, 'aria-label': tr('a11y.bulk_action') }),
                h(LButton, { label: this.bulkBusy ? tr('content_page.executing') : tr('content_page.execute'), disabled: this.bulkBusy || !this.bulkAction || !this.selected.length, onClick: this.runBulk }),
              ]),
              rows,
              h('div', { class: 'cms-content-pagination' }, [
                h('span', { class: 'cms-content-muted' }, tr('content_page.items_offset','',{count:this.pagination.returned||0,offset:this.pagination.offset||0})),
                h('div', { class: 'cms-content-actions' }, [
                  h(LButton, { label: tr('content_page.previous'), severity: 'secondary', disabled: this.loading || this.pagination.offset <= 0, onClick: this.prevPage }),
                  h(LButton, { label: tr('content_page.next'), severity: 'secondary', disabled: this.loading || !this.pagination.has_more, onClick: this.nextPage }),
                ]),
              ]),
            ]),
          }),
        ]),
      })
    },
  }
}
