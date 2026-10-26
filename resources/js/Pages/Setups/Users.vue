<template>
  <app-layout>
    <div class="us">
      <header class="us-head">
        <div class="us-title">
          <h1 class="us-h1">Users</h1>
          <p class="us-sub">{{ subtitle }}</p>
        </div>
        <div class="us-actions">
          <refresh-button :only="['users', 'invitations', 'roles', 'events']" />
          <Button variant="secondary" size="md" @click="openForm(null)">Create manually</Button>
          <Button variant="primary" size="md" @click="openAdd()">+ Add user</Button>
        </div>
      </header>

      <div class="us-filters">
        <input v-model="q" type="search" class="us-search" placeholder="Search by name or email…" aria-label="Search users" />
        <div class="us-filter-group">
          <select v-model="role" :class="['us-select', { 'us-select--on': role !== 'all' }]" aria-label="Role">
            <option v-for="o in roleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <select v-model="ev" :class="['us-select', 'us-select--wide', { 'us-select--on': ev !== 'all' }]" aria-label="Event">
            <option v-for="o in eventOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <select v-model="prov" :class="['us-select', { 'us-select--on': prov !== 'all' }]" aria-label="Provider">
            <option v-for="o in providerOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
          </select>
          <div class="us-seg" role="group" aria-label="Status">
            <button v-for="o in statusOptions" :key="o.value" type="button" :aria-pressed="status === o.value"
              :class="['us-seg-btn', { 'us-seg-btn--on': status === o.value }]" @click="status = o.value">
              {{ o.label }} <span class="us-seg-count">{{ o.count }}</span>
            </button>
          </div>
          <button v-if="anyFilter" type="button" class="us-clear" @click="clearFilters">Clear filters</button>
        </div>
        <span v-if="anyFilter" class="us-shown">Showing {{ list.length }} of {{ rows.length }}</span>
      </div>

      <div class="us-body">
        <div class="us-table" role="table" aria-label="Users">
          <div class="us-row us-row--head" role="row">
            <span role="columnheader">User</span><span role="columnheader">Role</span><span role="columnheader">Events</span>
            <span role="columnheader">Provider</span><span role="columnheader"><span class="sr-only">Actions</span></span>
          </div>
          <div v-for="r in list" :key="r.key" role="row" tabindex="0"
            :class="['us-row', { 'us-row--sel': selected === r.key, 'us-row--off': r.status === 'inactive' }]"
            @click="selected = r.key" @keydown.enter="selected = r.key">
            <div class="us-user">
              <span class="us-avatar">{{ initials(r.name) }}</span>
              <div class="us-user-text">
                <div class="us-name-line">
                  <span class="us-name">{{ r.name }}</span>
                  <span v-if="r.status !== 'active'" :class="['us-tag', `us-tag--${r.status}`]">{{ r.status === 'invited' ? (r.invite_status === 'expired' ? 'EXPIRED' : 'INVITED') : 'INACTIVE' }}</span>
                  <span v-if="r.kind === 'user' && r.id === me" class="us-you">you</span>
                </div>
                <span class="us-email">{{ r.email }}</span>
              </div>
            </div>
            <span>
              <span v-if="r.roles.length" class="chip" :style="roleChip(r.roles[0].name)">{{ roleLabel(r.roles[0].name) }}{{ r.roles.length > 1 ? ` +${r.roles.length - 1}` : '' }}</span>
              <span v-else class="us-muted">No role</span>
            </span>
            <div class="us-events">
              <span :class="['us-ev-main', { 'us-muted': r.allEvents, 'us-bad': !r.allEvents && !r.events.length }]">
                {{ r.allEvents ? 'All events' : r.events.length ? `${r.events.length} event${r.events.length > 1 ? 's' : ''}` : 'No events' }}
              </span>
              <span v-if="!r.allEvents && r.events.length" class="us-ev-sub">{{ r.events.map((e) => e.short_name || e.name).join(' · ') }}</span>
            </div>
            <span :class="['us-prov', { 'us-muted': !r.provider }]">{{ r.provider || 'Internal' }}</span>
            <div class="us-row-actions" @click.stop>
              <button v-if="r.kind === 'user'" type="button" class="us-icon" :title="`Edit ${r.name}`" :aria-label="`Edit ${r.name}`" @click="openForm(r)">✎</button>
              <button v-if="!(r.kind === 'user' && r.id === me)" type="button" class="us-icon us-icon--danger"
                :title="r.kind === 'invite' ? 'Cancel invitation' : `Delete ${r.name}`" :aria-label="r.kind === 'invite' ? `Cancel invitation for ${r.email}` : `Delete ${r.name}`"
                @click="confirmDelete(r)">×</button>
            </div>
          </div>
          <div v-if="!list.length" class="us-empty">
            No users match.
            <button v-if="anyFilter" type="button" class="us-clear" @click="clearFilters">Clear filters</button>
          </div>
        </div>

        <UserDetailPanel v-if="selectedRow" :row="selectedRow" @close="selected = null" @edit="openForm" @status="statusAction" @delete="confirmDelete" />
      </div>
    </div>

    <AddUserModal v-if="addInitial" :initial="addInitial" :roles="roles" :events="events" :users="users"
      :fleet-providers="fleetProviders" :staff-domains="staffDomains" @close="addInitial = null" @done="afterAdd" />
    <UserFormModal v-if="formUser !== undefined" :user="formUser" :users="users" :roles="roles" :events="events"
      :fleet-providers="fleetProviders" :staff-domains="staffDomains" @close="formUser = undefined" @to-grant="toGrant" />

    <ConfirmModal :show="!!confirm" :title="confirm?.title" :message="confirm?.message" :confirm-label="confirm?.label"
      :tone="confirm?.tone" :processing="processing" @close="confirm = null" @confirm="runConfirm" />
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';
import Button from '../../Components/Button.vue';
import ConfirmModal from '../../Components/ConfirmModal.vue';
import RefreshButton from '../../Components/RefreshButton.vue';
import AddUserModal from '../../Components/Users/AddUserModal.vue';
import UserFormModal from '../../Components/Users/UserFormModal.vue';
import UserDetailPanel from '../../Components/Users/UserDetailPanel.vue';
import { roleLabel, roleChip, initials } from '../../Composables/userRoles';
import { useToast } from '../../Composables/useToast';

const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  fleetProviders: { type: Array, default: () => [] },
  invitations: { type: Array, default: () => [] },
  staffDomains: { type: Array, default: () => [] },
});

const page = usePage();
const toast = useToast();
const me = computed(() => page.props.auth.user?.id);

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast.success(flash.success);
  if (flash?.error) toast.error(flash.error);
});

/* ------------------------------- Rows ------------------------------- */

const roleById = computed(() => new Map(props.roles.map((r) => [r.id, r])));
const eventById = computed(() => new Map(props.events.map((e) => [e.id, e])));
const providerName = (id) => props.fleetProviders.find((p) => p.id === id)?.name ?? '';
const isStaff = (email) => props.staffDomains.includes((email.split('@')[1] ?? '').toLowerCase());
const reachesAll = (roles) => roles.some((r) => roleById.value.get(r.id)?.all_events);

// Users and open (or expired) invitations share one list; an invitation shows as INVITED until accepted.
const rows = computed(() => [
  ...props.users.map((u) => ({
    ...u,
    key: `u${u.id}`,
    kind: 'user',
    events: u.events ?? [],
    provider: providerName(u.fleet_provider_id),
    status: u.is_active ? 'active' : 'inactive',
    staff: isStaff(u.email),
    allEvents: reachesAll(u.roles),
  })),
  ...props.invitations.map((i) => {
    const roles = i.role_ids.map((id) => roleById.value.get(id)).filter(Boolean);
    return {
      ...i,
      key: `i${i.id}`,
      kind: 'invite',
      name: i.name || i.email.split('@')[0],
      roles,
      events: i.event_ids.map((id) => eventById.value.get(id)).filter(Boolean),
      provider: providerName(i.fleet_provider_id),
      status: 'invited',
      invite_status: i.status,
      staff: false,
      allEvents: reachesAll(roles),
    };
  }),
].sort((a, b) => a.name.localeCompare(b.name)));

/* ------------------------------ Filters ----------------------------- */

const q = ref('');
const role = ref('all');
const ev = ref('all');
const prov = ref('all');
const status = ref('all');

const hasEvent = (r, id) => r.allEvents || r.events.some((e) => e.id === id);
function passes(r, skip) {
  const text = q.value.trim().toLowerCase();
  return (skip === 'role' || role.value === 'all' || r.roles.some((x) => x.id === role.value))
    && (skip === 'ev' || ev.value === 'all' || hasEvent(r, ev.value))
    && (skip === 'prov' || prov.value === 'all' || (prov.value === 'internal' ? !r.fleet_provider_id : r.fleet_provider_id === prov.value))
    && (skip === 'status' || status.value === 'all' || r.status === status.value)
    && (!text || r.name.toLowerCase().includes(text) || r.email.toLowerCase().includes(text));
}
const count = (skip, fn) => rows.value.filter((r) => passes(r, skip) && fn(r)).length;

const list = computed(() => rows.value.filter((r) => passes(r)));
const anyFilter = computed(() => role.value !== 'all' || ev.value !== 'all' || prov.value !== 'all' || status.value !== 'all' || !!q.value.trim());

const roleOptions = computed(() => [
  { value: 'all', label: `All roles (${count('role', () => true)})` },
  ...props.roles.map((x) => ({ value: x.id, label: `${roleLabel(x.name)} (${count('role', (r) => r.roles.some((y) => y.id === x.id))})` })),
]);
const eventOptions = computed(() => [
  { value: 'all', label: 'All events' },
  ...props.events.map((e) => ({ value: e.id, label: `${e.name} (${count('ev', (r) => hasEvent(r, e.id))})` })),
]);
const providerOptions = computed(() => [
  { value: 'all', label: 'All providers' },
  ...props.fleetProviders.map((p) => ({ value: p.id, label: `${p.name} (${count('prov', (r) => r.fleet_provider_id === p.id)})` })),
  { value: 'internal', label: `Internal (${count('prov', (r) => !r.fleet_provider_id)})` },
]);
const statusOptions = computed(() => [
  ['all', 'All', () => true], ['active', 'Active', (r) => r.status === 'active'],
  ['invited', 'Invited', (r) => r.status === 'invited'], ['inactive', 'Inactive', (r) => r.status === 'inactive'],
].map(([value, label, fn]) => ({ value, label, count: count('status', fn) })));

function clearFilters() {
  q.value = ''; role.value = 'all'; ev.value = 'all'; prov.value = 'all'; status.value = 'all';
}

const subtitle = computed(() => {
  const n = (s) => rows.value.filter((r) => r.status === s).length;
  return `${props.users.length} users · ${n('active')} active · ${n('invited')} invited · ${n('inactive')} inactive`;
});

/* ----------------------------- Selection ---------------------------- */

const selected = ref(null);
const selectedRow = computed(() => rows.value.find((r) => r.key === selected.value) ?? null);

/* ------------------------------ Dialogs ----------------------------- */

const addInitial = ref(null);
const formUser = ref(undefined); // undefined = closed, null = new, object = edit

function openAdd(initial = {}) {
  addInitial.value = { ...initial };
}
function openForm(row) {
  formUser.value = row ? props.users.find((u) => u.id === row.id) ?? null : null;
}
function toGrant(prefill) {
  formUser.value = undefined;
  openAdd(prefill);
}
function afterAdd(email) {
  addInitial.value = null;
  clearFilters();
  // Select the new or updated account (or its invitation) once the list reloads.
  const row = rows.value.find((r) => r.email.toLowerCase() === email);
  selected.value = row?.key ?? null;
}
watch(rows, () => {
  if (selected.value && !selectedRow.value) selected.value = null;
});

/* --------------------------- Confirmations -------------------------- */

const confirm = ref(null);
const processing = ref(false);
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function statusAction(row) {
  if (row.kind === 'invite') {
    return router.post(`/setups/users/invitations/${row.id}/resend`, {}, { preserveScroll: true });
  }
  if (row.status === 'inactive') {
    return router.post(`/setups/users/${row.id}/status`, { active: true }, { preserveScroll: true });
  }
  confirm.value = {
    title: 'Deactivate user',
    message: `<strong>${esc(row.name)}</strong> will be signed out everywhere, including the mobile app, and can't sign in until reactivated. Their history and crew assignments stay; they are no longer offered as crew or as an email recipient.`,
    label: 'Deactivate',
    tone: 'danger',
    run: () => router.post(`/setups/users/${row.id}/status`, { active: false }, { preserveScroll: true, onFinish: done }),
  };
}

function confirmDelete(row) {
  confirm.value = row.kind === 'invite'
    ? {
        title: 'Cancel invitation',
        message: `The link sent to <strong>${esc(row.email)}</strong> stops working. You can invite them again later.`,
        label: 'Cancel invitation',
        tone: 'danger',
        run: () => router.delete(`/setups/users/invitations/${row.id}`, { preserveScroll: true, onFinish: done }),
      }
    : {
        title: 'Delete user',
        message: `Delete <strong>${esc(row.name)}</strong> (${esc(row.email)})? This can't be undone. To stop them signing in but keep the account, deactivate it instead.`,
        label: 'Delete user',
        tone: 'danger',
        run: () => router.delete(`/setups/users/${row.id}`, { preserveScroll: true, onFinish: done }),
      };
}

function runConfirm() {
  processing.value = true;
  confirm.value.run();
}
function done() {
  processing.value = false;
  confirm.value = null;
}
</script>

<style scoped>
.us { display: flex; flex-direction: column; gap: 18px; }
.us-head {
  display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap;
  padding-bottom: 16px; border-bottom: 1px solid var(--border);
}
.us-title { display: flex; flex-direction: column; gap: 4px; }
.us-h1 { font-size: 20px; font-weight: 600; color: var(--ink); margin: 0; }
.us-sub { font-size: 13px; color: var(--ink2); margin: 0; }
.us-actions { display: flex; gap: 8px; align-items: center; }

.us-filters { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.us-search {
  flex: 0 1 320px; min-width: 220px; height: 36px; padding: 0 12px; border: 1px solid var(--border); border-radius: 8px;
  font-size: 13px; outline: none; background: var(--surface); color: var(--ink); font-family: inherit;
}
.us-search:focus { border-color: var(--ink); }
.us-filter-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.us-select {
  height: 32px; padding: 0 28px 0 10px; border-radius: 8px; font-size: 12px; font-weight: 500; cursor: pointer;
  border: 1px solid var(--border); background-color: var(--surface); color: var(--ink); font-family: inherit;
}
.us-select--wide { max-width: 240px; }
.us-select--on { border-color: var(--ink); background-color: var(--panel); }
.us-seg { display: flex; gap: 2px; padding: 2px; background: var(--panel); border-radius: 8px; }
.us-seg-btn {
  height: 28px; padding: 0 10px; border: none; border-radius: 6px; font-size: 12px; font-weight: 500;
  cursor: pointer; background: transparent; color: var(--ink3); font-family: inherit;
}
.us-seg-btn--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }
.us-seg-count { opacity: 0.55; }
.us-clear { height: 32px; padding: 0 8px; border: none; background: none; font-size: 12px; font-weight: 500; color: var(--accent); cursor: pointer; font-family: inherit; }
.us-shown { margin-left: auto; font-size: 12px; color: var(--ink3); }

.us-body { display: flex; gap: 16px; flex-wrap: wrap; align-items: flex-start; }
.us-table { flex: 1 1 640px; min-width: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface); }
.us-row {
  display: grid; grid-template-columns: minmax(0, 1.5fr) 130px minmax(0, 1.3fr) minmax(0, 1fr) 84px; gap: 14px;
  align-items: center; padding: 11px 16px; border-bottom: 1px solid var(--border); cursor: pointer; outline: none;
}
.us-row:last-child { border-bottom: none; }
.us-row:hover, .us-row:focus-visible { background: var(--panel); }
.us-row--head {
  padding: 10px 16px; background: var(--panel); cursor: default; font-size: 10px; font-weight: 600;
  letter-spacing: 0.06em; color: var(--ink3); text-transform: uppercase;
}
.us-row--sel, .us-row--sel:hover { background: color-mix(in srgb, var(--accent) 5%, var(--surface)); box-shadow: inset 3px 0 0 var(--accent); }

.us-user { display: flex; align-items: center; gap: 10px; min-width: 0; }
.us-avatar {
  width: 32px; height: 32px; border-radius: 50%; flex: none; font-size: 11px; font-weight: 600;
  background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent);
  display: flex; align-items: center; justify-content: center;
}
.us-row--off .us-avatar { background: var(--panel); color: var(--ink4); }
.us-user-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.us-name-line { display: flex; align-items: center; gap: 6px; min-width: 0; }
.us-name { font-size: 13px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.us-row--off .us-name { color: var(--ink3); }
.us-email { font-size: 12px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.us-tag { flex: none; font-size: 10px; font-weight: 600; letter-spacing: 0.04em; padding: 1px 6px; border-radius: 999px; border: 1px solid var(--border); }
.us-tag--invited { background: color-mix(in srgb, var(--warn) 10%, transparent); color: var(--warn); border-color: color-mix(in srgb, var(--warn) 35%, transparent); }
.us-tag--inactive { background: var(--panel); color: var(--ink3); }
.us-you { flex: none; font-size: 10px; font-weight: 700; color: var(--ok); background: var(--ok-soft); padding: 1px 6px; border-radius: 999px; }

.chip { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.us-events { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.us-ev-main { font-size: 12px; font-weight: 500; color: var(--ink); }
.us-ev-sub { font-size: 11px; color: var(--ink4); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.us-prov { font-size: 12px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.us-muted { color: var(--ink4) !important; font-size: 12px; }
.us-bad { color: var(--danger); }

.us-row-actions { display: flex; gap: 2px; justify-content: flex-end; }
.us-icon {
  width: 28px; height: 28px; border: none; background: transparent; border-radius: 6px; color: var(--ink2);
  cursor: pointer; font-size: 13px; font-family: inherit;
}
.us-icon:hover { background: var(--panel); }
.us-icon--danger { color: var(--danger); font-size: 15px; }
.us-icon--danger:hover { background: color-mix(in srgb, var(--danger) 8%, transparent); }
.us-empty { padding: 28px; text-align: center; font-size: 13px; color: var(--ink4); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
</style>
