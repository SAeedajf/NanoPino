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
        extensions: Array.isArray(boot.extensions) ? boot.extensions : [],
        error: '',
        notice: '',
        busy: '',
      }
    },
    methods: {
      extensionName(id){return this.extensions.find(x=>x.id===id||x.package===id)?.name||id},
      pointStatus(v){return({ready:tr('recovery_page.ready'),creating:tr('recovery_page.creating'),failed:tr('recovery_page.failed'),restored:tr('recovery_page.restored_status')})[v]||v||tr('recovery_page.unknown')},
      async restore(point) {
        if (!point?.id || !confirmFa(tr('recovery_page.restore_confirm','Restore Recovery Point :id?',{id:point.id}))) return
        this.busy = point.id
        this.error = ''
        try {
          await api(`/recovery/points/${encodeURIComponent(point.id)}/restore`, { method: 'POST', body: {} })
          this.notice = tr('recovery_page.restored','Recovery completed successfully. The page will reload to read the new state.')
          globalThis.setTimeout?.(() => globalThis.location?.reload?.(), 500)
        } catch (e) {
          this.error = e.message
        } finally {
          this.busy = ''
        }
      },
      async disableSafeMode() {
        if (!confirmFa(tr('recovery_page.disable_confirm','Request exit from Safe Mode? Health Guard must approve the state.'))) return
        this.busy = 'safe-mode'
        this.error = ''
        try {
          this.safeMode = await api('/recovery/safe-mode/disable', { method: 'POST', body: {} })
          this.notice = tr('recovery_page.disabled','Safe Mode was disabled.')
        } catch (e) {
          this.error = e.message
        } finally {
          this.busy = ''
        }
      },
    },
    render() {
      return h(LPage,{title:tr('routes.recovery.title','Recovery & Safe Mode'),description:tr('routes.recovery.lead','Recovery Point rollback and safe Safe Mode control')}, {
        default: () => h('div', { style: ui.page }, [
          message(h, this),
          h('div', { style: ui.grid }, [
            h(LStatCard, { label: tr('recovery_page.recovery_point'), value: String(this.points.length) }),
            h(LStatCard, { label: tr('recovery_page.safe_mode'), value: this.safeMode.enabled ? tr('recovery_page.on','On') : tr('recovery_page.off','Off') }),
          ]),
          h(LPanel, { title: tr('recovery_page.points','Recovery Points') }, {
            default: () => h('div', { style: ui.grid }, this.points.map((point) =>
              h('article', { style: ui.card, key: point.id }, [
                h('strong', {}, this.extensionName(point.extensionId || point.extension_id || point.id)),
                h('div', { style: ui.row }, [
                  h(LBadge, { label: this.pointStatus(point.status), severity: point.status==='ready'?'success':point.status==='failed'?'danger':'warning' }),
                  h('small', {}, point.operation || ''),
                ]),
                h('details',{},[h('summary',{},tr('recovery_page.technical_details')),h('code', {}, point.id)]),
                h(LButton, {
                  label: this.busy===point.id?tr('recovery_page.working'):tr('recovery_page.restore','Restore'),
                  disabled: Boolean(this.busy) || point.restorable === false,
                  onClick: () => this.restore(point),
                }),
              ]),
            )),
          }),
          h(LPanel, { title: tr('recovery_page.safe_mode') }, {
            default: () => h('div', { style: ui.page }, [
              h('p', {}, tr('recovery_page.guard_note','Safe Mode can only be exited after Health Guard passes.')),
              this.safeMode.enabled?h('div',{style:ui.page},[
                h('div',{style:ui.card},[h('strong',{},tr('recovery_page.reason')),h('span',{},this.safeMode.reason||tr('recovery_page.reason_unknown'))]),
                (this.safeMode.quarantined||[]).length?h('div',{style:ui.page},[h('strong',{},tr('recovery_page.quarantined_extensions')),h('div',{style:ui.row},this.safeMode.quarantined.map(id=>h(LBadge,{label:this.extensionName(id),severity:'danger'})))]):null,
                (this.safeMode.recoveryPointId||this.safeMode.recovery_point_id)?h('details',{},[h('summary',{},tr('recovery_page.recovery_point')),h('code',{},this.safeMode.recoveryPointId||this.safeMode.recovery_point_id)]):null
              ]):null,
              this.safeMode.enabled
                ? h(LButton, { label: this.busy==='safe-mode'?tr('recovery_page.working'):tr('recovery_page.exit','Exit Safe Mode'), disabled: Boolean(this.busy), onClick: this.disableSafeMode })
                : h(LBadge, { label: tr('recovery_page.off_badge','Safe Mode is off'), severity:'success' }),
            ]),
          }),
        ]),
      })
    },
  }
}
