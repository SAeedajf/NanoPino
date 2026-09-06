<template>
  <LPage icon="users">
    <template #actions>
      <div class="cms-card-actions">
        <LButton :variant="view==='users'?'solid':'outline'" shape="rounded" @click="view='users'">{{ t('users_page.users') }}</LButton>
        <LButton :variant="view==='roles'?'solid':'outline'" shape="rounded" @click="view='roles'">{{ t('users_page.roles_caps') }}</LButton>
        <LButton v-if="allowed('users.create')" icon="user-plus" shape="rounded" @click="startCreate">{{ t('users_page.add_user') }}</LButton>
        <LButton icon="refresh-cw" variant="outline" shape="rounded" :disabled="loading" @click="loadUsers(false)">{{ t('users_page.refresh') }}</LButton>
      </div>
    </template>

    <div class="cms-stat-grid cms-stat-grid--five">
      <LStatCard :label="t('users_page.total')" :value="summary.total" icon="users"/>
      <LStatCard :label="t('users_page.active')" :value="summary.active" icon="user-check"/>
      <LStatCard :label="t('users_page.inactive')" :value="summary.inactive" icon="user-x"/>
      <LStatCard :label="t('users_page.suspend')" :value="summary.suspend" icon="shield-alert"/>
      <LStatCard :label="t('users_page.pending')" :value="summary.pending" icon="clock"/>
    </div>

    <LPanel v-if="error" class="cms-inline-callout cms-inline-callout--danger" role="alert" aria-live="assertive"><strong>{{ t('users_page.error') }}</strong><p>{{ error }}</p></LPanel>
    <LPanel v-if="notice" class="cms-inline-callout cms-inline-callout--success" role="status" aria-live="polite"><strong>{{ t('users_page.done') }}</strong><p>{{ notice }}</p></LPanel>

    <template v-if="view==='users'">
      <LPanel v-if="editorOpen" class="cms-control-panel">
        <template #header>{{ editingId ? t('users_page.edit_user') : t('users_page.new_user') }}</template>
        <form class="cms-control-form" @submit.prevent="saveUser">
          <label>{{ t('users_page.username') }}<input v-model.trim="form.username" type="text" maxlength="190" required></label>
          <label>{{ t('users_page.first_name') }}<input v-model.trim="form.fname" type="text" maxlength="100"></label>
          <label>{{ t('users_page.last_name') }}<input v-model.trim="form.lname" type="text" maxlength="100"></label>
          <label>{{ t('users_page.email') }}<input v-model.trim="form.email" type="email" maxlength="190"></label>
          <label>{{ t('users_page.mobile') }}<input v-model.trim="form.mobile" type="tel" maxlength="30"></label>
          <label v-if="!editingId">{{ t('users_page.password') }}<input v-model="form.password" type="password" minlength="8" required autocomplete="new-password"></label>
          <div class="cms-control-actions cms-control-form__wide">
            <LButton type="submit" icon="save" shape="rounded" :disabled="saving">{{ saving ? t('users_page.saving') : t('users_page.save') }}</LButton>
            <LButton type="button" variant="outline" severity="neutral" shape="rounded" @click="closeEditor">{{ t('users_page.cancel') }}</LButton>
          </div>
        </form>
      </LPanel>

      <div class="cms-control-filters cms-user-filters">
        <label>{{ t('users_page.search') }}<input v-model="filters.q" type="search" :placeholder="t('users_page.search_placeholder')" @input="debouncedLoad"></label>
        <label>{{ t('users_page.status') }}<select v-model="filters.status" @change="loadUsers(true)"><option value="">{{ t('users_page.all_statuses') }}</option><option v-for="s in statuses" :key="s" :value="s">{{ statusLabel(s) }}</option></select></label>
        <label>{{ t('users_page.roles') }}<select v-model="filters.role" @change="loadUsers(true)"><option value="">{{ t('users_page.all_roles') }}</option><option v-for="role in roles" :key="role.key" :value="role.key">{{ role.name||role.key }}</option></select></label>
      </div>

      <div class="cms-users-layout">
        <LPanel flush bare>
          <CmsPageState :state="loading ? 'loading' : users.length ? 'ready' : 'empty'" empty-icon="user-round-x" :empty-title="t('users_page.empty')">
            <div class="cms-user-list">
              <article v-for="user in users" :key="user.id" class="cms-user-row" :class="{'is-selected': selectedId===idOf(user)}">
                <button type="button" class="cms-user-row__identity cms-user-select" :aria-pressed="selectedId===idOf(user)" @click="selectUser(user)"><span class="cms-user-avatar" aria-hidden="true">{{ initial(user) }}</span><span class="cms-user-identity-text"><strong>{{ user.name || user.username || `#${idOf(user)}` }}</strong><small>{{ user.email || user.mobile || user.username || `#${idOf(user)}` }}</small></span></button>
                <div class="cms-capability-cloud"><LBadge :severity="user.status==='active'?'success':'warn'">{{ statusLabel(user.status) }}</LBadge><LBadge v-if="isSelf(user)" severity="info">{{ t('users_page.current_account') }}</LBadge><LBadge v-for="role in (user.roles||[]).slice(0,3)" :key="role" severity="secondary">{{ role }}</LBadge></div>
                <div class="cms-card-actions" @click.stop><LButton v-if="allowed('users.update')" size="sm" variant="outline" shape="rounded" @click="edit(user)">{{ t('users_page.edit') }}</LButton><LButton v-if="allowed('users.sessions.revoke')" size="sm" variant="outline" shape="rounded" @click="revoke(user)">{{ t('users_page.revoke_session') }}</LButton><LButton v-if="allowed('users.delete')&&!isSelf(user)" size="sm" variant="outline" severity="danger" shape="rounded" @click="remove(user)">{{ t('users_page.delete') }}</LButton></div>
              </article>
            </div>
          </CmsPageState>
          <div class="cms-pagination-bar"><LButton variant="outline" shape="rounded" :disabled="pagination.offset<=0||loading" @click="prevPage">{{ t('users_page.previous') }}</LButton><small>{{ t('users_page.count', { count: pagination.total, range: pageRange }) }}</small><LButton variant="outline" shape="rounded" :disabled="!pagination.has_more||loading" @click="nextPage">{{ t('users_page.next') }}</LButton></div>
        </LPanel>

        <LPanel>
          <template #header>{{ t('users_page.access_security') }}</template>
          <div v-if="selected" class="cms-stack">
            <div class="cms-user-detail-head"><div><strong>{{ selected.name||selected.username||`#${selected.id}` }}</strong><small>ID {{ selected.id }} · {{ selected.email||t('users_page.no_email') }}</small></div><LBadge>{{ statusLabel(selected.status) }}</LBadge></div>
            <div class="cms-card-actions cms-card-actions--wrap"><LButton v-for="s in statuses" :key="s" size="sm" :variant="selected.status===s?'solid':'outline'" shape="rounded" :disabled="!allowed('users.update')||(isSelf(selected)&&s!=='active')" @click="changeStatus(selected,s)">{{ statusLabel(s) }}</LButton></div>
            <section><h4>{{ t('users_page.roles') }}</h4><div class="cms-role-grid"><article v-for="role in roles" :key="role.key" class="cms-list-card"><div><strong>{{ role.name||role.key }}</strong><small>{{ role.key }} · {{ role.permissions?.length||0 }} capability</small></div><LButton v-if="allowed('users.roles.manage')" size="sm" :severity="hasRole(selected,role.key)?'danger':'neutral'" variant="outline" shape="rounded" :disabled="hasRole(selected,role.key)&&isSelf(selected)" @click="toggleRole(selected,role)">{{ hasRole(selected,role.key) ? t('users_page.remove') : t('users_page.add') }}</LButton></article></div></section>
            <section><h4>{{ t('users_page.effective_capabilities') }}</h4><div class="cms-capability-cloud"><LBadge v-for="cap in [...(selected.abilities||[])].sort()" :key="cap" severity="secondary">{{ cap }}</LBadge></div><small>{{ t('users_page.rbac_note') }}</small></section>
          </div>
          <p v-else class="cms-muted">{{ t('users_page.select_user') }}</p>
        </LPanel>
      </div>
    </template>

    <template v-else>
      <LPanel><template #header>{{ t('users_page.installed_roles') }}</template><div class="cms-role-catalog"><article v-for="role in roles" :key="role.key" class="cms-list-card cms-list-card--wide"><div><strong>{{ role.name||role.key }}</strong><code>{{ role.key }}</code><p>{{ role.description||t('users_page.no_description') }}</p><details><summary>{{ t('users_page.capabilities') }}</summary><div class="cms-capability-cloud"><LBadge v-for="cap in role.permissions||[]" :key="cap" severity="secondary">{{ cap }}</LBadge></div></details></div><LBadge severity="secondary">{{ role.permissions?.length||0 }}</LBadge></article></div></LPanel>
      <LPanel><template #header>{{ t('users_page.role_templates') }}</template><div class="cms-role-catalog"><article v-for="role in roleTemplates" :key="role.key" class="cms-list-card cms-list-card--wide"><div><strong>{{ role.name||role.key }}</strong><code>{{ role.key }}</code><p>{{ role.description }}</p></div><LBadge severity="secondary">{{ role.capabilities?.length||0 }} capability</LBadge></article></div></LPanel>
      <LPanel><template #header>{{ t('users_page.registry') }}</template><div class="cms-capability-registry"><div v-for="cap in capabilities" :key="cap.key" class="cms-capability-row"><code>{{ cap.key }}</code><span>{{ cap.description }}</span><small>{{ cap.owner }}</small></div></div></LPanel>
    </template>
  </LPage>
</template>

<script setup>
import { computed,onMounted,reactive,ref } from 'vue'
import { LBadge,LButton,LPage,LPanel,LStatCard } from '@pinooxhq/luma/ui'
import CmsPageState from '../../components/cms-page-state.vue'
import { userApi } from '../../services/cms-api.js'
import { t } from '../../i18n/index.js'

const users=ref([]),roles=ref([]),roleTemplates=ref([]),capabilities=ref([]),current=ref(null),statuses=ref(['active','inactive','suspend','pending'])
const summary=reactive({total:0,active:0,inactive:0,suspend:0,pending:0}),pagination=reactive({limit:50,offset:0,returned:0,total:0,has_more:false})
const filters=reactive({q:'',status:'',role:''}),loading=ref(false),saving=ref(false),error=ref(''),notice=ref(''),view=ref('users'),editorOpen=ref(false),editingId=ref(null),selected=ref(null),timer=ref(null)
const form=reactive({fname:'',lname:'',username:'',email:'',mobile:'',password:''})
const selectedId=computed(()=>selected.value?.id||selected.value?.user_id||0)
const pageRange=computed(()=>pagination.total?`${pagination.offset+1}-${Math.min(pagination.offset+pagination.returned,pagination.total)}`:'0')
const statusFa={active:t('users_page.active'),inactive:t('users_page.inactive'),suspend:t('users_page.suspend'),pending:t('users_page.pending')}
const statusLabel=s=>statusFa[s]||s||t('users_page.unknown');const idOf=u=>Number(u?.id||u?.user_id||0);const isSelf=u=>idOf(u)===idOf(current.value);const hasRole=(u,key)=>(u?.roles||[]).includes(key);const initial=u=>String(u?.name||u?.username||'?').slice(0,1).toUpperCase()
function allowed(ability){const granted=current.value?.abilities||[];return granted.some(item=>item==='*'||item===ability||(item.endsWith('.*')&&ability.startsWith(item.slice(0,-1))))}
async function loadUsers(reset=false){if(reset)pagination.offset=0;loading.value=true;error.value='';try{const r=await userApi.list({limit:pagination.limit,offset:pagination.offset,q:filters.q.trim(),status:filters.status,role:filters.role});const d=r.data||r;users.value=d.items||[];roles.value=d.roles||[];roleTemplates.value=d.role_templates||[];capabilities.value=d.capabilities||[];current.value=d.current||null;statuses.value=d.statuses||statuses.value;Object.assign(summary,d.summary||{});Object.assign(pagination,d.pagination||{});if(selected.value)selected.value=users.value.find(u=>idOf(u)===selectedId.value)||null}catch(e){error.value=e.message}finally{loading.value=false}}
function debouncedLoad(){clearTimeout(timer.value);timer.value=setTimeout(()=>loadUsers(true),260)}
function startCreate(){editingId.value=null;editorOpen.value=true;Object.assign(form,{fname:'',lname:'',username:'',email:'',mobile:'',password:''});window.scrollTo({top:0,behavior:'smooth'})}
function closeEditor(){editorOpen.value=false;editingId.value=null}
function edit(u){selected.value=u;editingId.value=idOf(u);editorOpen.value=true;Object.assign(form,{fname:u.fname||'',lname:u.lname||'',username:u.username||'',email:u.email||'',mobile:u.mobile||'',password:''});window.scrollTo({top:0,behavior:'smooth'})}
async function saveUser(){saving.value=true;error.value='';notice.value='';try{if(editingId.value)await userApi.update(editingId.value,{fname:form.fname,lname:form.lname,username:form.username,email:form.email,mobile:form.mobile});else await userApi.create({...form});notice.value=editingId.value?t('users_page.user_updated'):t('users_page.user_created');closeEditor();await loadUsers(false)}catch(e){error.value=e.message}finally{saving.value=false}}
function selectUser(u){selected.value=u}
async function changeStatus(u,status){if(isSelf(u)&&status!=='active'){error.value=t('users_page.self_status_block');return}try{await userApi.setStatus(idOf(u),status);notice.value=t('users_page.status_changed');await loadUsers(false)}catch(e){error.value=e.message}}
async function toggleRole(u,role){const has=hasRole(u,role.key);if(has&&isSelf(u)){error.value=t('users_page.self_role_block');return}try{if(has)await userApi.detachRole(idOf(u),role.key);else await userApi.assignRole(idOf(u),role.key);notice.value=has?t('users_page.role_removed'):t('users_page.role_added');await loadUsers(false)}catch(e){error.value=e.message}}
async function revoke(u){if(!window.confirm(isSelf(u)?t('users_page.revoke_self_confirm'):t('users_page.revoke_confirm')))return;try{const r=await userApi.revokeSessions(idOf(u));notice.value=t('users_page.revoked',{count:Number(r.data?.revoked??r.revoked??0)})}catch(e){error.value=e.message}}
async function remove(u){if(isSelf(u)){error.value=t('users_page.self_delete_block');return}if(!window.confirm(t('users_page.delete_confirm')))return;try{await userApi.remove(idOf(u));notice.value=t('users_page.user_deleted');selected.value=null;await loadUsers(false)}catch(e){error.value=e.message}}
function nextPage(){if(!pagination.has_more)return;pagination.offset+=pagination.limit;loadUsers(false)}function prevPage(){pagination.offset=Math.max(0,pagination.offset-pagination.limit);loadUsers(false)}
onMounted(()=>loadUsers(false))
</script>

<style scoped>
.cms-user-filters{display:grid;grid-template-columns:minmax(220px,2fr) repeat(2,minmax(150px,1fr));gap:.65rem}.cms-user-filters>label{display:grid;gap:.35rem;min-inline-size:0}.cms-user-filters input,.cms-user-filters select{min-block-size:2.7rem;border:1px solid var(--p-content-border-color);border-radius:.7rem;padding:.55rem .7rem;background:var(--p-content-background);color:inherit}.cms-users-layout{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(300px,.75fr);gap:1rem;align-items:start}.cms-user-list,.cms-role-catalog{display:grid;gap:.65rem}.cms-user-row{display:grid;grid-template-columns:minmax(230px,1fr) minmax(170px,.8fr) auto;gap:.75rem;align-items:center;border:1px solid var(--p-content-border-color);border-radius:.9rem;padding:.75rem}.cms-user-select{min-inline-size:0;min-block-size:44px;inline-size:100%;border:0;border-radius:.5rem;padding:.35rem;background:transparent;color:inherit;font:inherit;text-align:start;cursor:pointer}.cms-user-identity-text{min-inline-size:0;overflow-wrap:anywhere}.cms-user-select:hover{background:var(--p-content-hover-background)}.cms-user-row.is-selected{outline:2px solid var(--p-primary-color)}.cms-user-row__identity,.cms-user-detail-head{display:flex;gap:.65rem;align-items:center;justify-content:space-between}.cms-user-row__identity>.cms-user-identity-text,.cms-user-detail-head>div{display:grid;gap:.2rem}.cms-user-avatar{inline-size:2.6rem;block-size:2.6rem;border-radius:50%;display:grid;place-items:center;background:var(--p-content-hover-background);font-weight:700}.cms-role-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem}.cms-capability-cloud{display:flex;flex-wrap:wrap;gap:.35rem}.cms-pagination-bar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding-top:.75rem}.cms-capability-registry{display:grid;gap:.35rem;max-block-size:34rem;overflow:auto}.cms-capability-row{display:grid;grid-template-columns:minmax(170px,.7fr) minmax(220px,1fr) auto;gap:.6rem;border:1px solid var(--p-content-border-color);border-radius:.7rem;padding:.55rem}.cms-muted,small{color:var(--p-text-muted-color)}@media(max-width:900px){.cms-users-layout{grid-template-columns:1fr}.cms-user-row{grid-template-columns:1fr}.cms-role-grid{grid-template-columns:1fr}.cms-capability-row{grid-template-columns:1fr}}@media(max-width:640px){.cms-user-filters{grid-template-columns:1fr}.cms-pagination-bar{flex-wrap:wrap}.cms-stat-grid--five{grid-template-columns:repeat(2,minmax(0,1fr))}}
</style>
