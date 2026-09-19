<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Users</h1>
        <p class="page-sub">{{ users.length }} user{{ users.length !== 1 ? 's' : '' }} registered</p>
      </div>
      <div class="header-actions">
        <refresh-button :only="['users', 'roles', 'events']" />
        <Button variant="primary" size="sm" @click="openCreate">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">New User</span>
        </Button>
      </div>
    </div>

    <div class="table-controls-wrapper">
      <div class="left-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input v-model="search" type="text" class="search-input" placeholder="Search by name or email…" />
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Roles</th>
            <th>Events</th>
            <th style="width:88px;text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="5" class="empty-row">No users found.</td>
          </tr>
          <tr v-for="user in filtered" :key="user.id">
            <td>
              <div class="user-cell">
                <div class="avatar">{{ initials(user.name) }}</div>
                <span class="user-name">{{ user.name }}</span>
                <span v-if="user.id === $page.props.auth.user?.id" class="you-badge">you</span>
              </div>
            </td>
            <td class="email-cell">{{ user.email }}</td>
            <td>
              <div class="role-tags">
                <span v-for="r in user.roles" :key="r.id" class="role-tag">{{ r.name }}</span>
                <span v-if="!user.roles.length" class="no-roles">— none —</span>
              </div>
            </td>
            <td>
              <div class="role-tags">
                <span v-for="e in user.events" :key="e.id" class="event-tag">{{ e.short_name || e.name }}</span>
                <span v-if="!user.events?.length" class="no-roles" title="Unrestricted — this user can reach every event">all events</span>
              </div>
            </td>
            <td>
              <TableActions @edit="openEdit(user)" @delete="openDelete(user)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit Modal -->
    <Modal :show="showModal" @close="closeModal" max-width="520px">
      <template #title>{{ editingUser ? 'Edit User' : 'New User' }}</template>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="user-name">Full Name</label>
          <input id="user-name" v-model="form.name" type="text" class="form-input"
            :class="{ 'input-error': errors.name }"
            :aria-invalid="errors.name ? 'true' : undefined"
            :aria-describedby="errors.name ? 'user-name-error' : undefined"
            placeholder="Jane Smith" />
          <span v-if="errors.name" id="user-name-error" class="error-msg">{{ errors.name }}</span>
        </div>
        <div class="form-group">
          <label class="form-label" for="user-email">Email Address</label>
          <input id="user-email" v-model="form.email" type="email" class="form-input"
            :class="{ 'input-error': errors.email }"
            :aria-invalid="errors.email ? 'true' : undefined"
            :aria-describedby="errors.email ? 'user-email-error' : undefined"
            placeholder="jane@example.com" />
          <span v-if="errors.email" id="user-email-error" class="error-msg">{{ errors.email }}</span>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="user-password">
          Password
          <span v-if="editingUser" class="form-label-hint">— leave blank to keep current</span>
        </label>
        <div class="pw-wrap">
          <input id="user-password" v-model="form.password" :type="showPw ? 'text' : 'password'" class="form-input pw-input"
            :class="{ 'input-error': errors.password }"
            :aria-invalid="errors.password ? 'true' : undefined"
            :aria-describedby="errors.password ? 'user-password-error' : undefined"
            :placeholder="editingUser ? '••••••••' : 'Min. 8 characters'" />
          <button
            type="button"
            class="pw-toggle"
            :aria-label="showPw ? 'Hide password' : 'Show password'"
            :aria-pressed="showPw"
            @click="showPw = !showPw"
          >
            <svg v-if="showPw" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
            <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <span v-if="errors.password" id="user-password-error" class="error-msg">{{ errors.password }}</span>
      </div>

      <div class="form-group">
        <div class="picker-head">
          <label class="form-label">Roles</label>
          <span class="picker-summary">{{ form.roles.length }} selected</span>
        </div>
        <div class="picker">
          <button
            v-for="r in roles"
            :key="r.id"
            type="button"
            :class="['picker-tile', { 'picker-tile--on': form.roles.includes(r.id) }]"
            @click="toggle(form.roles, r.id)"
          >
            <span class="picker-check" aria-hidden="true">
              <svg v-if="form.roles.includes(r.id)" width="11" height="11" viewBox="0 0 12 12" fill="none"
                stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="2 6 5 9 10 3" />
              </svg>
            </span>
            <span class="picker-body">
              <span class="picker-name">{{ roleLabel(r.name) }}</span>
              <span class="picker-meta">{{ roleHint(r.name) }}</span>
            </span>
          </button>
          <span v-if="!roles.length" class="no-roles">No roles defined yet.</span>
        </div>
      </div>

      <div class="form-group">
        <div class="picker-head">
          <label class="form-label">Events</label>
          <div class="picker-actions">
            <span :class="['picker-summary', { 'picker-summary--all': !form.events.length }]">
              {{ form.events.length ? `${form.events.length} of ${events.length}` : 'All events' }}
            </span>
            <button v-if="form.events.length" type="button" class="link-btn" @click="form.events = []">Clear</button>
            <button v-else-if="events.length" type="button" class="link-btn" @click="form.events = events.map(e => e.id)">
              Select all
            </button>
          </div>
        </div>

        <div v-if="events.length > 6" class="picker-search">
          <svg-icon name="search" :size="13" />
          <input v-model="eventSearch" type="text" placeholder="Filter events…" />
        </div>

        <div class="picker picker--list">
          <button
            v-for="e in filteredEvents"
            :key="e.id"
            type="button"
            :class="['picker-tile', { 'picker-tile--on': form.events.includes(e.id) }]"
            @click="toggle(form.events, e.id)"
          >
            <span class="picker-check" aria-hidden="true">
              <svg v-if="form.events.includes(e.id)" width="11" height="11" viewBox="0 0 12 12" fill="none"
                stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="2 6 5 9 10 3" />
              </svg>
            </span>
            <span class="picker-body">
              <span class="picker-name">{{ e.name }}</span>
              <span v-if="e.short_name" class="picker-meta">{{ e.short_name }}</span>
            </span>
          </button>
          <span v-if="!events.length" class="no-roles">No events defined yet.</span>
          <span v-else-if="!filteredEvents.length" class="no-roles">No events match “{{ eventSearch }}”.</span>
        </div>
        <p class="picker-note">
          Leave empty to give access to every event, including ones created later.
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="closeModal">Cancel</Button>
        <Button variant="primary" size="sm" :processing="processing" @click="submitUser">
          {{ editingUser ? 'Save Changes' : 'Create User' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirm Modal -->
    <Modal :show="showDelete" @close="showDelete = false" max-width="400px">
      <template #title>Delete User</template>
      <p class="confirm-text">
        Are you sure you want to delete <strong>{{ deletingUser?.name }}</strong>
        ({{ deletingUser?.email }})? This action cannot be undone.
      </p>
      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">Cancel</Button>
        <button class="btn-danger" :disabled="processing" @click="confirmDelete">
          <span v-if="processing" class="danger-spinner" />
          <span v-else>Delete User</span>
        </button>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';
import Button from '../../Components/Button.vue';
import Modal from '../../Components/Modal.vue';
import SvgIcon from '../../Components/SvgIcon.vue';
import RefreshButton from '../../Components/RefreshButton.vue';
import TableActions from '../../Components/TableActions.vue';

const props = defineProps({
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
});

const search       = ref('');
const showModal    = ref(false);
const showDelete   = ref(false);
const processing   = ref(false);
const showPw       = ref(false);
const editingUser  = ref(null);
const deletingUser = ref(null);
const errors       = ref({});
const form         = ref({ name: '', email: '', password: '', roles: [], events: [] });

const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return props.users.filter(u =>
    u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
  );
});

const eventSearch = ref('');

const filteredEvents = computed(() => {
  const q = eventSearch.value.trim().toLowerCase();
  if (!q) return props.events;
  return props.events.filter(e =>
    e.name.toLowerCase().includes(q) || (e.short_name ?? '').toLowerCase().includes(q)
  );
});

// What each role actually unlocks, so the picker isn't just opaque slugs.
const ROLE_HINTS = {
  admin: 'Full access, including setups',
  ground_control: 'All jobs and movements',
  transport: 'Jobs in their functional area',
  team_services: 'Jobs in their functional area',
  venue_ops: 'Jobs in their functional area',
};

function roleLabel(name) {
  return name.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function roleHint(name) {
  return ROLE_HINTS[name] ?? 'Custom role';
}

function toggle(list, id) {
  const i = list.indexOf(id);
  if (i === -1) list.push(id);
  else list.splice(i, 1);
}

function initials(name) {
  return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
}

function openCreate() {
  editingUser.value = null;
  form.value = { name: '', email: '', password: '', roles: [], events: [] };
  errors.value = {};
  eventSearch.value = '';
  showPw.value = false;
  showModal.value = true;
}

function openEdit(user) {
  editingUser.value = user;
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    roles: user.roles.map(r => r.id),
    events: (user.events ?? []).map(e => e.id),
  };
  errors.value = {};
  eventSearch.value = '';
  showPw.value = false;
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingUser.value = null;
  errors.value = {};
}

function submitUser() {
  processing.value = true;
  errors.value = {};

  if (editingUser.value) {
    router.put(`/setups/users/${editingUser.value.id}`, form.value, {
      onSuccess: () => { processing.value = false; closeModal(); },
      onError:   (e) => { processing.value = false; errors.value = e; },
    });
  } else {
    router.post('/setups/users', form.value, {
      onSuccess: () => { processing.value = false; closeModal(); },
      onError:   (e) => { processing.value = false; errors.value = e; },
    });
  }
}

function openDelete(user) {
  deletingUser.value = user;
  showDelete.value = true;
}

function confirmDelete() {
  processing.value = true;
  router.delete(`/setups/users/${deletingUser.value.id}`, {
    onSuccess: () => { processing.value = false; showDelete.value = false; deletingUser.value = null; },
    onError:   () => { processing.value = false; },
  });
}
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 20px; gap: 12px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub   { font-size: 13px; color: var(--ink3); margin: 0; }
.header-actions { display: flex; align-items: center; gap: 8px; }
.btn-icon { font-size: 14px; line-height: 1; }
.btn-text { font-size: 12px; }

.table-controls-wrapper {
  display: flex; align-items: center; justify-content: space-between;
  gap: 10px; margin-bottom: 14px; flex-wrap: wrap;
}
.left-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.search-box {
  display: flex; align-items: center; gap: 6px;
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 8px; padding: 5px 10px; color: var(--ink3);
}
.search-input {
  border: none; outline: none; background: transparent;
  font-size: 13px; color: var(--ink); width: 220px;
}
.search-input::placeholder { color: var(--ink4); }

.table-wrap {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}
.data-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.data-table th {
  background: var(--panel); text-align: left;
  padding: 10px 14px; font-size: 11.5px; font-weight: 600;
  color: var(--ink3); border-bottom: 1px solid var(--border);
  text-transform: uppercase; letter-spacing: 0.04em;
}
.data-table td { padding: 10px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--panel); }
.empty-row { text-align: center; color: var(--ink4); padding: 28px; }

/* User cell */
.user-cell { display: flex; align-items: center; gap: 10px; }
.avatar {
  width: 30px; height: 30px; border-radius: 50%; flex-shrink: 0;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.user-name { font-weight: 600; color: var(--ink); }
.you-badge {
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
  background: var(--ok-soft); color: var(--ok);
  padding: 1px 6px; border-radius: 20px;
}
.email-cell { color: var(--ink3); font-size: 13px; }

/* Role tags */
.role-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.role-tag {
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 20px;
}
.no-roles { font-size: 12px; color: var(--ink4); font-style: italic; }
.event-tag {
  background: var(--panel); color: var(--ink2); border: 1px solid var(--border);
  font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 20px;
}

/* Form */
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
@media (max-width: 500px) { .form-row { grid-template-columns: 1fr; } }

.form-group { margin-bottom: 16px; }
.form-label {
  display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px;
}
.form-label-hint { font-weight: 400; color: var(--ink4); font-size: 11.5px; }

.form-input {
  width: 100%; padding: 8px 10px; font-size: 13.5px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--bg); color: var(--ink); outline: none;
  transition: border-color 0.15s; box-sizing: border-box; font-family: inherit;
}
.form-input:focus { border-color: var(--accent); }
.input-error { border-color: var(--danger) !important; }
.error-msg { font-size: 12px; color: var(--danger); margin-top: 4px; display: block; }

.pw-wrap { position: relative; }
.pw-input { padding-right: 36px; }
.pw-toggle {
  position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--ink4); padding: 4px; display: flex; transition: color 0.13s;
}
.pw-toggle:hover { color: var(--ink); }

/* Role / event pickers */
.picker-head {
  display: flex; align-items: baseline; justify-content: space-between;
  gap: 8px; margin-bottom: 6px;
}
.picker-head .form-label { margin: 0; }
.picker-actions { display: flex; align-items: center; gap: 8px; }
.picker-summary { font-size: 11.5px; color: var(--ink4); }
.picker-summary--all { color: var(--accent-fg); font-weight: 600; }
.link-btn {
  background: none; border: none; padding: 0; cursor: pointer;
  font-size: 11.5px; font-weight: 600; color: var(--accent); font-family: inherit;
}
.link-btn:hover { text-decoration: underline; }

.picker-search {
  display: flex; align-items: center; gap: 7px;
  border: 1px solid var(--border); border-radius: 8px;
  padding: 6px 10px; margin-bottom: 6px; background: var(--surface);
}
.picker-search:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
.picker-search svg { color: var(--ink3); flex-shrink: 0; }
.picker-search input {
  border: none; outline: none; background: none; width: 100%;
  font-size: 13px; color: var(--ink); font-family: inherit;
}

.picker {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 6px; border: 1px solid var(--border); border-radius: 8px;
  padding: 8px; background: var(--bg);
}
/* Event names are long, so they get one full-width row each. */
.picker--list { grid-template-columns: 1fr; max-height: 190px; overflow-y: auto; }

.picker-tile {
  display: flex; align-items: flex-start; gap: 9px; text-align: left;
  padding: 8px 10px; border-radius: 7px; cursor: pointer;
  border: 1px solid transparent; background: var(--surface);
  font-family: inherit; transition: border-color 0.13s, background 0.13s;
}
.picker-tile:hover { border-color: var(--border-strong); }
.picker-tile--on { border-color: var(--accent); background: var(--accent-soft); }

.picker-check {
  width: 16px; height: 16px; border-radius: 5px; flex-shrink: 0; margin-top: 1px;
  border: 1.5px solid var(--border-strong); background: var(--surface);
  display: flex; align-items: center; justify-content: center; color: #fff;
}
.picker-tile--on .picker-check { background: var(--accent); border-color: var(--accent); }

.picker-body { display: flex; flex-direction: column; min-width: 0; }
.picker-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.picker-meta { font-size: 11px; color: var(--ink3); }
.picker-note { font-size: 11.5px; color: var(--ink4); margin: 6px 0 0; }

/* Confirm */
.confirm-text { font-size: 14px; color: var(--ink3); line-height: 1.55; margin: 0; }
.confirm-text strong { color: var(--ink); }

.btn-danger {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  background: var(--danger); color: #fff; border: none; border-radius: 8px;
  padding: 5px 12px; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: opacity 0.15s; font-family: inherit;
}
.btn-danger:hover:not(:disabled) { opacity: 0.88; }
.btn-danger:disabled { opacity: 0.5; cursor: not-allowed; }
.danger-spinner {
  display: inline-block; width: 12px; height: 12px;
  border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
  border-radius: 50%; animation: dspin 0.6s linear infinite;
}
@keyframes dspin { to { transform: rotate(360deg); } }
</style>
