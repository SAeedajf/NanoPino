import { data, ui, routeButton, tr} from './common.mjs'

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge, LStatCard } = host
  return {
    name: 'CmsUpdatesControlPlane',
    data() {
      return { center: data().updateCenter || {} }
    },
    render() {
      const center = this.center
      return h(LPage,{title:tr('routes.updates.title','Updates'),description:tr('routes.updates.lead','Operational Update/Rollback entry; updates execute transactionally from Extension Center.')}, {
        default: () => h('div', { style: ui.page }, [
          h('div', { style: ui.grid }, [
            h(LStatCard, { label: 'Policy', value: String((center.policies || []).length) }),
            h(LStatCard, { label: 'History', value: String((center.history || []).length) }),
          ]),
          h(LPanel, { title: tr('updates_page.operations','Update operations') }, {
            default: () => h('div', { style: ui.page }, [
              h('p', {}, tr('updates_page.operation_note','Upload the new PINX package in Extension Center. Review, Snapshot and Rollback Gate are applied before execution.')),
              h('div', { style: ui.row }, [
                routeButton(h, LButton, tr('updates_page.open_extensions','Open Extension Center'), 'extensions'),
                routeButton(h, LButton, 'Recovery / Rollback', 'recovery'),
              ]),
              h(LBadge, { label: 'Snapshot + Review + Atomic Operation' }),
            ]),
          }),
        ]),
      })
    },
  }
}
