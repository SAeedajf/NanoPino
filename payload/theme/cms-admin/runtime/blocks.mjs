import { data, ui, routeButton, tr} from './common.mjs'

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge, LStatCard } = host
  return {
    name: 'CmsBlocksControlPlane',
    data() {
      return { items: data().blockDefinitions || [] }
    },
    render() {
      return h(LPage,{title:tr('routes.blocks.title','Blocks'),description:tr('routes.blocks.lead','Block Registry and safe Block/Block Pack operations')}, {
        default: () => h('div', { style: ui.page }, [
          h('div', { style: ui.grid }, [
            h(LStatCard, { label: tr('blocks_page.registered','Registered blocks'), value: String(this.items.length) }),
          ]),
          h(LPanel, { title: tr('blocks_page.operations','Operations') }, {
            default: () => h('div', { style: ui.page }, [
              h('p', {}, tr('blocks_page.operations_note','Block definitions are owned by their Registry/Extension. Manage Block Packs in Extensions and use blocks in Builder.')),
              h('div', { style: ui.row }, [
                routeButton(h, LButton, tr('blocks_page.open_builder','Open Builder'), 'builder'),
                routeButton(h, LButton, tr('blocks_page.manage_packs','Manage Block Packs'), 'extensions'),
              ]),
            ]),
          }),
          h('div', { style: ui.grid }, this.items.map((block) =>
            h('article', { style: ui.card, key: block.id || block.name }, [
              h('strong', {}, block.title || block.name || block.id),
              h('code', {}, block.name || block.id || ''),
              h('div', { style: ui.row }, [
                h(LBadge, { label: block.owner || 'unknown' }),
                h(LBadge, { label: `schema v${block.schemaVersion || block.schema_version || 1}` }),
              ]),
            ]),
          )),
        ]),
      })
    },
  }
}
