import { api, data, ui, message, confirmFa, tr} from './common.mjs'

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge, LStatCard } = host
  return {
    name: 'CmsRecoveryControlPlane',
    data() {
      const boot = data()
      return {
        points: Array.isArray(boot.recoveryPoints) ? boot.recoveryPoints : [],
        safeMode: boot.safeMode || { enabled: false },
        error: '',
        notice: '',
        busy: false,
      }
    },
    methods: {
      async restore(point) {
        if (!point?.id || !confirmFa(tr('recovery_page.restore_confirm','Restore Recovery Point :id?',{id:point.id}))) return
        this.busy = true
        this.error = ''
        try {
          await api(`/recovery/points/${encodeURIComponent(point.id)}/restore`, { method: 'POST', body: {} })
          this.notice = tr('recovery_page.restored','Recovery completed successfully. The page will reload to read the new state.')
          globalThis.setTimeout?.(() => globalThis.location?.reload?.(), 500)
        } catch (e) {
          this.error = e.message
        } finally {
          this.busy = false
        }
      },
      async disableSafeMode() {
        if (!confirmFa(tr('recovery_page.disable_confirm','Request exit from Safe Mode? Health Guard must approve the state.'))) return
        this.busy = true
        this.error = ''
        try {
          this.safeMode = await api('/recovery/safe-mode/disable', { method: 'POST', body: {} })
          this.notice = tr('recovery_page.disabled','Safe Mode was disabled.')
        } catch (e) {
          this.error = e.message
        } finally {
          this.busy = false
        }
      },
    },
    render() {
      return h(LPage,{title:tr('routes.recovery.title','Recovery & Safe Mode'),description:tr('routes.recovery.lead','Recovery Point rollback and safe Safe Mode control')}, {
        default: () => h('div', { style: ui.page }, [
          message(h, this),
          h('div', { style: ui.grid }, [
            h(LStatCard, { label: 'Recovery Point', value: String(this.points.length) }),
            h(LStatCard, { label: 'Safe Mode', value: this.safeMode.enabled ? tr('recovery_page.on','On') : tr('recovery_page.off','Off') }),
          ]),
          h(LPanel, { title: tr('recovery_page.points','Recovery Points') }, {
            default: () => h('div', { style: ui.grid }, this.points.map((point) =>
              h('article', { style: ui.card, key: point.id }, [
                h('strong', {}, point.extensionId || point.extension_id || point.id),
                h('div', { style: ui.row }, [
                  h(LBadge, { label: point.status || 'unknown' }),
                  h('code', {}, point.id),
                  h('small', {}, point.operation || ''),
                ]),
                h(LButton, {
                  label: tr('recovery_page.restore','Restore'),
                  disabled: this.busy || point.restorable === false,
                  onClick: () => this.restore(point),
                }),
              ]),
            )),
          }),
          h(LPanel, { title: 'Safe Mode' }, {
            default: () => h('div', { style: ui.page }, [
              h('p', {}, tr('recovery_page.guard_note','Safe Mode can only be exited after Health Guard passes.')),
              this.safeMode.enabled
                ? h(LButton, { label: tr('recovery_page.exit','Exit Safe Mode'), disabled: this.busy, onClick: this.disableSafeMode })
                : h(LBadge, { label: tr('recovery_page.off_badge','Safe Mode is off') }),
            ]),
          }),
        ]),
      })
    },
  }
}
