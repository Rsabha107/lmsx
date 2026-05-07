<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Permissions</h1>
        <p class="page-sub">{{ permissions.length }} permission{{ permissions.length !== 1 ? 's' : '' }} defined</p>
      </div>
      <div class="header-actions">
        <refresh-button :only="['permissions']" />
        <Button variant="primary" size="sm" @click="openCreate">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">New Permission</span>
        </Button>
      </div>
    </div>

    <div class="table-controls-wrapper">
      <div class="left-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input v-model="search" type="text" class="search-input" placeholder="Search permissions…" />
        </div>
      </div>
    </div>

    <div class="table-wrap">
      <table class="data-table">
        <thead>
          <tr>
            <th>Permission</th>
            <th>Used by Roles</th>
            <th style="width:88px;text-align:center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="filtered.length === 0">
            <td colspan="3" class="empty-row">No permissions found.</td>
          </tr>
          <tr v-for="perm in filtered" :key="perm.id">
            <td>
              <span class="perm-name">{{ perm.name }}</span>
            </td>
            <td>
              <span class="role-count">{{ perm.roles_count }} role{{ perm.roles_count !== 1 ? 's' : '' }}</span>
            </td>
            <td>
              <TableActions @edit="openEdit(perm)" @delete="openDelete(perm)" />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Create / Edit Modal -->
    <Modal :show="showModal" @close="closeModal" max-width="440px">
      <template #title>{{ editingPerm ? 'Edit Permission' : 'New Permission' }}</template>
      <div class="form-group">
        <label class="form-label">Permission Name</label>
        <input
          v-model="form.name"
          type="text"
          class="form-input"
          :class="{ 'input-error': errors.name }"
          placeholder="e.g. jobs.create"
        />
        <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
        <span class="form-hint">Use dot notation to group related permissions: jobs.create, jobs.update, fleet.view…</span>
      </div>
      <template #footer>
        <Button variant="secondary" size="sm" @click="closeModal">Cancel</Button>
        <Button variant="primary" size="sm" :processing="processing" @click="submitPerm">
          {{ editingPerm ? 'Save Changes' : 'Create Permission' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirm Modal -->
    <Modal :show="showDelete" @close="showDelete = false" max-width="400px">
      <template #title>Delete Permission</template>
      <p class="confirm-text">
        Are you sure you want to delete <strong>{{ deletingPerm?.name }}</strong>?
        It will be removed from all roles. This cannot be undone.
      </p>
      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">Cancel</Button>
        <button class="btn-danger" :disabled="processing" @click="confirmDelete">
          <span v-if="processing" class="danger-spinner" />
          <span v-else>Delete Permission</span>
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
  permissions: { type: Array, default: () => [] },
});

const search      = ref('');
const showModal   = ref(false);
const showDelete  = ref(false);
const processing  = ref(false);
const editingPerm  = ref(null);
const deletingPerm = ref(null);
const errors = ref({});
const form   = ref({ name: '' });

const filtered = computed(() => {
  const q = search.value.toLowerCase();
  return props.permissions.filter(p => p.name.toLowerCase().includes(q));
});

function openCreate() {
  editingPerm.value = null;
  form.value = { name: '' };
  errors.value = {};
  showModal.value = true;
}

function openEdit(perm) {
  editingPerm.value = perm;
  form.value = { name: perm.name };
  errors.value = {};
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
  editingPerm.value = null;
  errors.value = {};
}

function submitPerm() {
  processing.value = true;
  errors.value = {};

  if (editingPerm.value) {
    router.put(`/setups/permissions/${editingPerm.value.id}`, form.value, {
      onSuccess: () => { processing.value = false; closeModal(); },
      onError:   (e) => { processing.value = false; errors.value = e; },
    });
  } else {
    router.post('/setups/permissions', form.value, {
      onSuccess: () => { processing.value = false; closeModal(); },
      onError:   (e) => { processing.value = false; errors.value = e; },
    });
  }
}

function openDelete(perm) {
  deletingPerm.value = perm;
  showDelete.value = true;
}

function confirmDelete() {
  processing.value = true;
  router.delete(`/setups/permissions/${deletingPerm.value.id}`, {
    onSuccess: () => { processing.value = false; showDelete.value = false; deletingPerm.value = null; },
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
  font-size: 13px; color: var(--ink); width: 200px;
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

.perm-name {
  font-weight: 600; color: var(--ink);
  font-family: 'JetBrains Mono', monospace; font-size: 13px;
}
.role-count { font-size: 12.5px; color: var(--ink3); }

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
.error-msg  { font-size: 12px; color: var(--danger); margin-top: 4px; display: block; }
.form-hint  { font-size: 11.5px; color: var(--ink4); margin-top: 5px; display: block; }

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
