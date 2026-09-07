import { data, ui, input, routeButton, tr} from './common.mjs'

export function createComponent(host) {
  const { h, LPage, LPanel, LButton, LBadge, LStatCard } = host
  return {
    name: 'CmsBlocksControlPlane',
    data() {
      return { items: data().blockDefinitions || [], query:'' }
    },
    computed:{
      filtered(){const q=this.query.trim().toLowerCase();return this.items.filter(block=>!q||[block.name,block.title,block.category,block.owner].filter(Boolean).some(value=>String(value).toLowerCase().includes(q)))},
      missingEssentials(){const definitions=[{key:'image',tokens:['image']},{key:'gallery',tokens:['gallery']},{key:'video',tokens:['video']},{key:'navigation',tokens:['navigation','menu']},{key:'form',tokens:['form']}],searchable=this.items.map(block=>`${block.name||''} ${block.title||''} ${block.category||''}`.toLowerCase());return definitions.filter(item=>!searchable.some(text=>item.tokens.some(token=>text.includes(token))))},
    },
    methods:{
      essentialLabel(item){return ({image:tr('blocks_page.essential_image'),gallery:tr('blocks_page.essential_gallery'),video:tr('blocks_page.essential_video'),navigation:tr('blocks_page.essential_navigation'),form:tr('blocks_page.essential_form')})[item.key]||item.key},
      previewKind(block){const name=String(block.name||'');if(name.includes('heading')||name.includes('paragraph'))return'text';if(name.includes('button'))return'button';if(name.includes('section'))return'layout';return'generic'},
    },
    render() {
      return h(LPage,{title:tr('routes.blocks.title','Blocks'),description:tr('routes.blocks.lead','Block Registry and safe Block/Block Pack operations')}, {
        default: () => h('div', { style: ui.page }, [
          h('div', { style: ui.grid }, [
            h(LStatCard, { label: tr('blocks_page.registered','Registered blocks'), value: String(this.items.length) }),
          ]),
          h(LPanel, { title: tr('blocks_page.contract') }, {
            default: () => h('div', { style: ui.page }, [
              h('div',{style:ui.row},[
                h(LBadge,{label:tr('blocks_page.versioned_json'),severity:'success'}),h(LBadge,{label:tr('blocks_page.typed_attributes'),severity:'success'}),h(LBadge,{label:tr('blocks_page.registry_owned'),severity:'success'}),h(LBadge,{label:tr('blocks_page.migration_chain'),severity:'success'}),h(LBadge,{label:tr('blocks_page.responsive_rules'),severity:'success'}),h(LBadge,{label:tr('blocks_page.server_renderer'),severity:'success'})
              ]),
              h('p', {}, tr('blocks_page.operations_note')),
              h('div', { style: ui.row }, [
                routeButton(h, LButton, tr('blocks_page.open_builder'), 'builder'),
                routeButton(h, LButton, tr('blocks_page.manage_packs'), 'extensions'),
              ]),
            ]),
          }),
          this.missingEssentials.length?h(LPanel,{title:tr('blocks_page.extend_library')},{default:()=>h('div',{style:ui.page},[
            h('p',{},tr('blocks_page.extend_library_help')),h('div',{style:ui.row},this.missingEssentials.map(item=>h(LBadge,{label:this.essentialLabel(item),severity:'secondary'}))),routeButton(h,LButton,tr('blocks_page.install_block_pack'),'extensions')
          ])}):null,
          input(h,this.query,v=>this.query=v,'search',{placeholder:tr('blocks_page.search_placeholder'),'aria-label':tr('blocks_page.search')}),
          h('div', { style: ui.grid }, this.filtered.map((block) =>
            h('article', { style: ui.card, key: block.id || block.name }, [
              h('div',{class:'cms-block-runtime-preview','data-kind':this.previewKind(block)},[h('strong',{},block.title||block.name),h('span',{},this.previewKind(block)==='button'?tr('blocks_page.preview_button'):this.previewKind(block)==='text'?tr('blocks_page.preview_text'):tr('blocks_page.preview_generic'))]),
              h('strong', {}, block.title || block.name || block.id),
              h('details',{},[h('summary',{},tr('blocks_page.technical_details')),h('code', {}, block.name || block.id || '')]),
              h('div', { style: ui.row }, [
                h(LBadge, { label: tr('blocks_page.category','',{value:block.category||'—'}) }),
                h(LBadge, { label: tr('blocks_page.attribute_count','',{count:Object.keys(block.attributes||{}).length}) }),
                block.allowsChildren?h(LBadge,{label:tr('blocks_page.children'),severity:'info'}):null,
                block.supports?.responsive?h(LBadge,{label:tr('blocks_page.responsive'),severity:'success'}):null,
              ]),
            ]),
          )),
        ]),
      })
    },
  }
}
