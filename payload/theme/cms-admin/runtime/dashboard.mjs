import{data,ui,routeButton,tr}from'./common.mjs'
export function createComponent(host){const{h,LPage,LPanel,LBadge,LStatCard,LButton}=host;return{name:'CmsControlPlaneDashboard',data(){return{d:data()}},render(){const d=this.d,s=d.summary||{},health=d.healthCenter||{};return h(LPage,{title:tr('routes.dashboard.title'),description:tr('routes.dashboard.lead')},{default:()=>h('div',{style:ui.page},[
 h('div',{style:ui.grid,'aria-label':tr('dashboard.summary_aria')},[
  h(LStatCard,{label:tr('dashboard.content'),value:String(s.content??0)}),h(LStatCard,{label:tr('dashboard.media'),value:String(s.media??0)}),h(LStatCard,{label:tr('dashboard.extensions'),value:String(s.extensions??0)}),h(LStatCard,{label:tr('dashboard.problems'),value:String(s.problems??0)})
 ]),
 h(LPanel,{title:tr('dashboard.quick_actions')},{default:()=>h('div',{style:ui.row},[
  routeButton(h,LButton,tr('dashboard.manage_content'),'content'),routeButton(h,LButton,tr('dashboard.media'),'media'),routeButton(h,LButton,tr('dashboard.builder'),'builder'),routeButton(h,LButton,tr('dashboard.theme'),'appearance'),routeButton(h,LButton,tr('dashboard.extensions'),'extensions'),routeButton(h,LButton,tr('dashboard.users'),'users'),routeButton(h,LButton,tr('dashboard.settings'),'settings')
 ])}),
 h(LPanel,{title:tr('dashboard.system_health')},{default:()=>h('div',{style:ui.row},[h(LBadge,{label:health.overall||tr('common.unknown')}),h('span',{},tr('dashboard.monitoring_hint'))])})
])})}}}
