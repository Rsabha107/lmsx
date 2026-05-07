<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Roles</h1>
        <p class="page-sub">{{ roles.length }} role{{ roles.length !== 1 ? 's' : '' }} configured</p>
      </div>
      <div class="header-actions">
        <refresh-button :only="['roles', 'permissions']" />
        <Button variant="primary" size="sm" @click="openCreate">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">New Role</span>
        </Button>
      </div>
    </div>

    <div class="table-controls-wrapper">
      <div class="left-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input v-model="search" type="text" class="search-input" placeholder="Search roles…" />
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Role</th>
            <th>Permissions</th>
            <th style="width:88px;text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="3" class="empty-row">No roles found.</td>
          </tr>
          <tr v-for="role in filtered" :key="role.id">
            <td>
              <span class="role-name">{{ role.name }}</span>
            </td>
            <td>
              <div class="perm-tags">
                <span v-for="p in role.permissions" :key="p.id" class="perm-tag">{{ p.name }}</span>
                <span v-if="!role.permissions.length" class="no-perms">— none —</span>
              </div>
            </td>
            <td>
              <TableActions @edit="openEdit(role)" @delete="openDelete(role)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit Modal -->
    <Modal :show="showModal" @close="closeModal" max-width="540px">
      <template #title>{{ editingRole ? 'Edit Role' : 'New Role' }}</template>
      <div class="form-group">
        <label class="form-label">Role Name</label>
        <input
          v-model="form.name"
          type="text"
          class="form-input"
          :class="{ 'input-error': errors.name }"
          placeholder="e.g. operations-manager"
        />
        <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
      </div>
      <div class="form-group">
        <label class="form-label">Permissions</label>
        <div class="perm-checklist">
          <label v-for="p in permissions" :key="p.id" class="perm-check-item">
            <input type="checkbox" :value="p.id" v-model="form.permissions" class="perm-checkbox" />
            <span>{{ p.name }}</span>
          </label>
          <span v-if="!permissions.length" class="no-perms">No permissions defined yet.</span>
        </div>
      </div>
      <template #footer>
        <Button variant="secondary" size="sm" @click="closeModal">Cancel</Button>
        <Button variant="primary" size="sm" :processing="processing" @click="submitRole">
          {{ editingRole ? 'Save Changes' : 'Create Role' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirm Modal -->
    <Modal :show="showDelete" @close="showDelete = false" max-width="400px">
      <template #title>Delete Role</template>
      <p class="confirm-text">
        Are you sure you want to delete <strong>{{ deletingRole?.name }}</strong>?
        This action cannot be undone.
      </p>
      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">Cancel</Button>
        <button class="btn-danger" :disabled="processing" @click="confirmDelete">
          <span v-if="processing" class="danger-spinner" />
          <span v-else>Delete Role</span>
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
  roles:       { type: Array, default: () => [] },
  permissions: { type: Array, default: () => [] },
});

const search      = ref('');
const showModal   = ref(false);
const showDelete  = ref(false);
const processing  = ref(false);
const editingRole  = ref(null);
const deletingRole = ref(null);
const errors = ref({});
const form   = ref({ name: '', permissions: [] });

const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return props.roles.filter(r => r.name.toLowerCase().includes(q));
});

function openCreate() {
  editingRole.value = null;
  form.value = { name: '', permissions: [] };
  errors.value = {};
  showModal.value = true;
}

function openEdit(role) {
  editingRole.value = role;
  form.value = { name: role.name, permissions: role.permissions.map(p => p.id) };
  errors.value = {};
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingRole.value = null;
  errors.value = {};
}

function submitRole() {
  processing.value = true;
  errors.value = {};

  if (editingRole.value) {
    router.put(`/setups/roles/${editingRole.value.id}`, form.value, {
      onSuccess: () => { processing.value = false; closeModal(); },
      onError:   (e) => { processing.value = false; errors.value = e; },
    });
  } else {
    router.post('/setups/roles', form.value, {
      onSuccess: () => { processing.value = false; closeModal(); },
      onError:   (e) => { processing.value = false; errors.value = e; },
    });
  }
}

function openDelete(role) {
  deletingRole.value = role;
  showDelete.value = true;
}

function confirmDelete() {
  processing.value = true;
  router.delete(`/setups/roles/${deletingRole.value.id}`, {
    onSuccess: () => { processing.value = false; showDelete.value = false; deletingRole.value = null; },
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
  font-size: 13px; color: var(--ink); width: 180px;
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
.data-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:hover { background: var(--panel); }
.empty-row { text-align: center; color: var(--ink4); padding: 28px; }

.role-name { font-weight: 600; color: var(--ink); }

.perm-tags { display: flex; flex-wrap: wrap; gap: 4px; }
.perm-tag {
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 11px; font-weight: 500; padding: 2px 7px; border-radius: 20px;
}
.no-perms { font-size: 12px; color: var(--ink4); font-style: italic; }

.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
.form-input {
  width: 100%; padding: 8px 10px; font-size: 13.5px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--bg); color: var(--ink); outline: none;
  transition: border-color 0.15s; box-sizing: border-box;
}
.form-input:focus { border-color: var(--accent); }
.input-error { border-color: var(--danger) !important; }
.error-msg { font-size: 12px; color: var(--danger); margin-top: 4px; display: block; }

.perm-checklist {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 4px; max-height: 220px; overflow-y: auto;
  border: 1px solid var(--border); border-radius: 8px; padding: 10px;
  background: var(--bg);
}
.perm-check-item {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: var(--ink); cursor: pointer;
  padding: 5px 6px; border-radius: 6px; user-select: none;
}
.perm-check-item:hover { background: var(--panel); }
.perm-checkbox { accent-color: var(--accent); width: 14px; height: 14px; cursor: pointer; flex-shrink: 0; }

.confirm-text { font-size: 14px; color: var(--ink3); line-height: 1.5; margin: 0; }
.confirm-text strong { color: var(--ink); }

.btn-danger {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  background: #EF4444; color: #fff; border: none; border-radius: 8px;
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
