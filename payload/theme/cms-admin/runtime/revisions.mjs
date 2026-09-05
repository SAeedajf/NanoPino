import { api, ui, select, message, confirmFa, routeButton, tr} from './common.mjs'

function validId(value) {
  const id = Number(value)
  return Number.isSafeInteger(id) && id > 0 ? String(id) : ''
}

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge } = host
  return {
    name: 'CmsRevisionsControlPlane',
    data() {
      return { contentId: '', contents: [], items: [], error: '', notice: '', loading: false, loadingContents: false }
    },
    async mounted() {
      await this.loadContents()
      const requested = validId(new URLSearchParams(globalThis.location?.search || '').get('content'))
      if (requested && this.contents.some((item) => validId(item.id) === requested)) {
        this.contentId = requested
        await this.load()
      }
    },
    methods: {
      async loadContents() {
        this.loadingContents = true
        this.error = ''
        try {
          this.contents = (await api('/content?limit=100')).items || []
        } catch (e) {
          this.error = e.message
        } finally {
          this.loadingContents = false
        }
      },
      async choose(value) {
        this.contentId = validId(value)
        this.items = []
        this.notice = ''
        this.error = ''
        if (this.contentId) await this.load()
      },
      async load() {
        const id = validId(this.contentId)
        if (!id) {
          this.items = []
          return
        }
        this.loading = true
        this.error = ''
        try {
          this.items = (await api(`/content/${id}/revisions`)).items || []
        } catch (e) {
          this.error = e.message
        } finally {
          this.loading = false
        }
      },
      async restore(revision) {
        const contentId = validId(this.contentId)
        const revisionId = validId(revision?.id)
        if (!contentId || !revisionId) return
        if (!confirmFa(tr('revisions_page.restore_confirm','Restore Revision #:id?',{id:revisionId}))) return
        try {
          await api(`/content/${contentId}/revisions/${revisionId}/restore`, { method: 'POST', body: {} })
          this.notice = tr('revisions_page.restored','Revision #:id was restored.',{id:revisionId})
          await this.load()
        } catch (e) {
          this.error = e.message
        }
      },
    },
    render() {
      const options = [
        { value: '', label: this.loadingContents ? tr('revisions_page.loading_contents','Loading content…') : tr('revisions_page.choose_content','Choose content') },
        ...this.contents
          .map((item) => ({ value: validId(item.id), label: `${item.title || tr('revisions_page.untitled','Untitled')} · #${item.id}` }))
          .filter((item) => item.value),
      ]
      return h(LPage,{title:tr('routes.revisions.title','Revisions'),description:tr('routes.revisions.lead','View and restore content history without manually entering IDs')}, {
        default: () => h('div', { style: ui.page }, [
          message(h, this),
          h(LPanel, { title: tr('revisions_page.content_selector','Select content') }, {
            default: () => h('div', { style: ui.row }, [
              h('div', { style: { minWidth: '280px', flex: '1 1 320px' } }, [
                select(h, this.contentId, this.choose, options),
              ]),
              h(LButton, { label: tr('revisions_page.refresh_contents','Refresh content'), severity: 'secondary', onClick: this.loadContents }),
              routeButton(h, LButton, tr('revisions_page.manage_content','Manage content'), 'content'),
            ]),
          }),
          h(LPanel, { title: tr('revisions_page.history','History') }, {
            default: () => !this.contentId
              ? h('p', {}, tr('revisions_page.choose_history','Choose content above to view its history.'))
              : this.loading
                ? h('p', {}, tr('revisions_page.loading','Loading…'))
                : this.items.length === 0
                  ? h('p', {}, tr('revisions_page.empty','No Revision has been recorded for this content yet.'))
                  : h('div', { style: ui.grid }, this.items.map((revision) =>
                      h('article', { style: ui.card, key: revision.id }, [
                        h('div', { style: ui.row }, [
                          h(LBadge, { label: revision.kind || 'revision' }),
                          h('strong', {}, `#${revision.id}`),
                        ]),
                        h('small', {}, revision.created_at ? String(revision.created_at) : ''),
                        h(LButton, { label: tr('revisions_page.restore','Restore'), onClick: () => this.restore(revision) }),
                      ]),
                    )),
          }),
        ]),
      })
    },
  }
}
