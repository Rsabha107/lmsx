<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Roles &amp; Permissions</h1>
        <p class="page-sub">Manage access control — roles, permissions, and their assignments</p>
      </div>
      <refresh-button :only="['roles', 'permissions']" />
    </div>

    <div class="access-grid">
      <!-- Categories -->
      <aside class="pane pane--categories">
        <div class="pane-head pane-head--tight">
          <span class="pane-eyebrow">Categories</span>
        </div>
        <button
          v-for="cat in categories"
          :key="cat.key"
          class="cat-item"
          :class="{ 'cat-item--active': category === cat.key }"
          @click="selectCategory(cat.key)"
        >
          <svg-icon :name="cat.icon" :size="16" class="cat-icon" />
          <span class="cat-body">
            <span class="cat-label">{{ cat.label }}</span>
            <span class="cat-desc">{{ cat.description }}</span>
          </span>
          <span class="cat-count" :class="{ 'cat-count--active': category === cat.key }">{{ cat.count }}</span>
        </button>
      </aside>

      <!-- Item list -->
      <section class="pane pane--list">
        <div class="pane-head">
          <div>
            <h2 class="pane-title">{{ activeCategory.label }}</h2>
            <p class="pane-meta">{{ filtered.length }} item{{ filtered.length !== 1 ? 's' : '' }}</p>
          </div>
          <button v-if="activeCategory.creatable" class="icon-btn" :aria-label="`New ${activeCategory.singular}`" @click="openCreate">
            <svg-icon name="plus" :size="15" />
          </button>
        </div>

        <div class="pane-search">
          <svg-icon name="search" :size="13" />
          <input v-model="search" type="text" class="pane-search-input" :placeholder="`Search ${activeCategory.label.toLowerCase()}…`" />
        </div>

        <div class="item-list">
          <p v-if="filtered.length === 0" class="item-empty">Nothing matches that search.</p>
          <button
            v-for="item in filtered"
            :key="item.id"
            class="item"
            :class="{ 'item--active': selected?.id === item.id }"
            @click="select(item)"
          >
            <span class="item-icon"><svg-icon :name="activeCategory.icon" :size="13" /></span>
            <span class="item-body">
              <span class="item-name">{{ item.name }}</span>
              <span class="item-meta">{{ item.guard_name }} · {{ formatDate(item.created_at) }}</span>
            </span>
            <svg-icon name="chevron" :size="13" class="item-chevron" />
          </button>
        </div>
      </section>

      <!-- Detail -->
      <section class="pane pane--detail">
        <div v-if="!selected" class="detail-empty">
          <svg-icon :name="activeCategory.icon" :size="30" />
          <p>Select {{ activeCategory.article }} {{ activeCategory.singular.toLowerCase() }} to edit{{ activeCategory.creatable ? ', or create a new one' : '' }}</p>
          <Button v-if="activeCategory.creatable" variant="secondary" size="sm" @click="openCreate">
            <template #icon><svg-icon name="plus" :size="14" /></template>
            New {{ activeCategory.singular }}
          </Button>
        </div>

        <template v-else>
          <div class="detail-head">
            <div>
              <h2 class="detail-title">{{ selected.name }}</h2>
              <p class="detail-meta">{{ selected.guard_name }} guard · created {{ formatDate(selected.created_at) }}</p>
            </div>
            <Button v-if="category !== 'assignments'" variant="ghost" size="sm" @click="openDelete(selected)">
              <template #icon><svg-icon name="trash" :size="14" /></template>
              Delete
            </Button>
          </div>

          <!-- Permission detail -->
          <template v-if="category === 'permissions'">
            <div class="form-group">
              <label class="form-label">Permission name</label>
              <input v-model="form.name" type="text" class="form-input" :class="{ 'input-error': errors.name }" />
              <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
              <span v-else class="field-hint">Used in code as <code>can('{{ form.name || 'name' }}')</code> — renaming it breaks any check still using the old name.</span>
            </div>

            <div class="form-group">
              <label class="form-label">Granted by {{ selected.roles.length }} role{{ selected.roles.length !== 1 ? 's' : '' }}</label>
              <div v-if="selected.roles.length" class="tag-row">
                <button v-for="r in selected.roles" :key="r.id" class="tag tag--link" @click="jumpToRole(r.id)">{{ r.name }}</button>
              </div>
              <p v-else class="muted-note">No role grants this permission, so nobody has it.</p>
            </div>

            <div class="detail-actions">
              <Button variant="primary" size="sm" :processing="processing" @click="savePermission">Save changes</Button>
            </div>
          </template>

          <!-- Role detail -->
          <template v-else-if="category === 'roles'">
            <div class="form-group">
              <label class="form-label">Role name</label>
              <input v-model="form.name" type="text" class="form-input" :class="{ 'input-error': errors.name }" />
              <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
            </div>

            <div class="form-group">
              <label class="form-label">{{ form.permissions.length }} of {{ permissions.length }} permissions</label>
              <p class="muted-note">
                Assign permissions under <strong>Permissions in Role</strong>.
              </p>
              <div v-if="selected.permissions.length" class="tag-row tag-row--wrap">
                <span v-for="p in selected.permissions" :key="p.id" class="tag">{{ p.name }}</span>
              </div>
              <p v-else class="muted-note">This role grants nothing yet.</p>
            </div>

            <div class="detail-actions">
              <Button variant="secondary" size="sm" @click="selectCategory('assignments', selected.id)">Edit permissions</Button>
              <Button variant="primary" size="sm" :processing="processing" @click="saveRole">Save changes</Button>
            </div>
          </template>

          <!-- Permissions in role -->
          <template v-else>
            <div class="assign-head">
              <p class="muted-note">
                {{ form.permissions.length }} of {{ permissions.length }} permissions granted to
                <strong>{{ selected.name }}</strong>.
              </p>
              <div class="assign-tools">
                <div class="pane-search pane-search--inline">
                  <svg-icon name="search" :size="13" />
                  <input v-model="permSearch" type="text" class="pane-search-input" placeholder="Filter permissions…" />
                </div>
                <button class="link-btn" @click="toggleAll(true)">Select all</button>
                <button class="link-btn" @click="toggleAll(false)">Clear</button>
              </div>
            </div>

            <div class="assign-groups">
              <div v-for="group in permissionGroups" :key="group.name" class="assign-group">
                <div class="assign-group-head">
                  <span class="assign-group-name">{{ group.name }}</span>
                  <span class="assign-group-count">{{ grantedIn(group) }}/{{ group.items.length }}</span>
                </div>
                <label v-for="p in group.items" :key="p.id" class="check-row">
                  <input type="checkbox" :value="p.id" v-model="form.permissions" />
                  <span class="check-name">{{ p.name }}</span>
                </label>
              </div>
              <p v-if="permissionGroups.length === 0" class="muted-note">No permissions match that filter.</p>
            </div>

            <div class="detail-actions detail-actions--sticky">
              <span v-if="dirty" class="dirty-note">Unsaved changes</span>
              <Button variant="primary" size="sm" :processing="processing" :disabled="!dirty" @click="saveRole">
                Save assignments
              </Button>
            </div>
          </template>
        </template>
      </section>
    </div>

    <!-- Create -->
    <Modal :show="showCreate" @close="showCreate = false" max-width="460px">
      <template #title>New {{ activeCategory.singular }}</template>
      <div class="form-group">
        <label class="form-label">{{ activeCategory.singular }} name</label>
        <input
          v-model="createForm.name"
          type="text"
          class="form-input"
          :class="{ 'input-error': errors.name }"
          :placeholder="category === 'roles' ? 'e.g. operations-manager' : 'e.g. fleet.manage'"
          @keyup.enter="submitCreate"
        />
        <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
      </div>
      <template #footer>
        <Button variant="secondary" size="sm" @click="showCreate = false">Cancel</Button>
        <Button variant="primary" size="sm" :processing="processing" @click="submitCreate">
          Create {{ activeCategory.singular }}
        </Button>
      </template>
    </Modal>

    <!-- Delete -->
    <Modal :show="showDelete" @close="showDelete = false" max-width="420px">
      <template #title>Delete {{ activeCategory.singular }}</template>
      <p class="confirm-text">
        Delete <strong>{{ deleting?.name }}</strong>? This cannot be undone.
      </p>
      <p v-if="category === 'roles' && deleting?.permissions?.length" class="confirm-warn">
        Anyone holding this role loses its {{ deleting.permissions.length }} permission{{ deleting.permissions.length !== 1 ? 's' : '' }} immediately.
      </p>
      <p v-if="category === 'permissions' && deleting?.roles?.length" class="confirm-warn">
        It is still granted by {{ deleting.roles.length }} role{{ deleting.roles.length !== 1 ? 's' : '' }}.
      </p>
      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">Cancel</Button>
        <Button variant="danger" size="sm" :processing="processing" @click="confirmDelete">Delete</Button>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Components/AppLayout.vue';
import Button from '../../Components/Button.vue';
import Modal from '../../Components/Modal.vue';
import SvgIcon from '../../Components/SvgIcon.vue';
import RefreshButton from '../../Components/RefreshButton.vue';

const props = defineProps({
  roles: { type: Array, default: () => [] },
  permissions: { type: Array, default: () => [] },
});

const category = ref('permissions');
const selected = ref(null);
const search = ref('');
const permSearch = ref('');
const processing = ref(false);
const showCreate = ref(false);
const showDelete = ref(false);
const deleting = ref(null);
const errors = ref({});
const form = ref({ name: '', permissions: [] });
const createForm = ref({ name: '' });
const savedSnapshot = ref('');

const categories = computed(() => [
  {
    key: 'permissions',
    label: 'Permissions',
    singular: 'Permission',
    article: 'a',
    description: 'Individual access rights',
    icon: 'key',
    count: props.permissions.length,
    creatable: true,
  },
  {
    key: 'roles',
    label: 'Roles',
    singular: 'Role',
    article: 'a',
    description: 'Named groups of permissions',
    icon: 'shield',
    count: props.roles.length,
    creatable: true,
  },
  {
    key: 'assignments',
    label: 'Permissions in Role',
    singular: 'Role',
    article: 'a',
    description: 'Assign permissions to roles',
    icon: 'settings',
    count: props.roles.length,
    creatable: false,
  },
]);

const activeCategory = computed(() => categories.value.find(c => c.key === category.value));

const items = computed(() => (category.value === 'permissions' ? props.permissions : props.roles));

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return q ? items.value.filter(i => i.name.toLowerCase().includes(q)) : items.value;
});

// Permission names are dotted/dashed namespaces (fleet.manage, approve-leave);
// the first segment is the closest thing to a category the data actually has.
const permissionGroups = computed(() => {
  const q = permSearch.value.trim().toLowerCase();
  const groups = new Map();

  for (const p of props.permissions) {
    if (q && !p.name.toLowerCase().includes(q)) continue;
    const name = p.name.split(/[.\-]/)[0];
    if (!groups.has(name)) groups.set(name, { name, items: [] });
    groups.get(name).items.push(p);
  }

  return [...groups.values()];
});

const dirty = computed(() => snapshot() !== savedSnapshot.value);

function snapshot() {
  return JSON.stringify({ name: form.value.name, permissions: [...form.value.permissions].sort() });
}

function selectCategory(key, selectId = null) {
  category.value = key;
  search.value = '';
  permSearch.value = '';
  const next = selectId
    ? (key === 'permissions' ? props.permissions : props.roles).find(i => i.id === selectId)
    : null;
  next ? select(next) : (selected.value = null);
}

function select(item) {
  selected.value = item;
  errors.value = {};
  form.value = {
    name: item.name,
    permissions: (item.permissions ?? []).map(p => p.id),
  };
  savedSnapshot.value = snapshot();
}

function jumpToRole(id) {
  selectCategory('roles', id);
}

function toggleAll(all) {
  form.value.permissions = all ? permissionGroups.value.flatMap(g => g.items.map(p => p.id)) : [];
}

function grantedIn(group) {
  return group.items.filter(p => form.value.permissions.includes(p.id)).length;
}

function openCreate() {
  createForm.value = { name: '' };
  errors.value = {};
  showCreate.value = true;
}

function submitCreate() {
  const url = category.value === 'permissions' ? '/setups/permissions' : '/setups/roles';
  processing.value = true;
  errors.value = {};

  router.post(url, createForm.value, {
    onSuccess: () => { processing.value = false; showCreate.value = false; selected.value = null; },
    onError: (e) => { processing.value = false; errors.value = e; },
  });
}

function savePermission() {
  processing.value = true;
  errors.value = {};

  router.put(`/setups/permissions/${selected.value.id}`, { name: form.value.name }, {
    onSuccess: () => { processing.value = false; },
    onError: (e) => { processing.value = false; errors.value = e; },
  });
}

function saveRole() {
  processing.value = true;
  errors.value = {};

  router.put(`/setups/roles/${selected.value.id}`, form.value, {
    onSuccess: () => { processing.value = false; savedSnapshot.value = snapshot(); },
    onError: (e) => { processing.value = false; errors.value = e; },
  });
}

function openDelete(item) {
  deleting.value = item;
  showDelete.value = true;
}

function confirmDelete() {
  const base = category.value === 'permissions' ? '/setups/permissions' : '/setups/roles';
  processing.value = true;

  router.delete(`${base}/${deleting.value.id}`, {
    onSuccess: () => {
      processing.value = false;
      showDelete.value = false;
      deleting.value = null;
      selected.value = null;
    },
    onError: () => { processing.value = false; },
  });
}

function formatDate(value) {
  if (!value) return '—';
  return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

// Props are replaced wholesale after every save; re-point at the fresh object
// so the detail pane doesn't keep showing a stale copy of the record.
watch(() => [props.roles, props.permissions], () => {
  if (!selected.value) return;
  const fresh = items.value.find(i => i.id === selected.value.id);
  fresh ? select(fresh) : (selected.value = null);
});
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  margin-bottom: 18px; gap: 12px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.access-grid {
  display: grid;
  grid-template-columns: 240px 300px minmax(0, 1fr);
  gap: 14px;
  align-items: start;
  height: calc(100vh - 190px);
  min-height: 460px;
}

.pane {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; height: 100%;
  display: flex; flex-direction: column; overflow: hidden;
}
.pane--categories { padding: 10px; gap: 2px; }
.pane--detail { padding: 18px; overflow-y: auto; }

.pane-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 10px; padding: 14px 14px 10px;
}
.pane-head--tight { padding: 4px 6px 8px; }
.pane-eyebrow {
  font-size: 10.5px; font-weight: 700; letter-spacing: .08em;
  text-transform: uppercase; color: var(--ink4);
}
.pane-title { font-size: 14px; font-weight: 650; color: var(--ink); margin: 0; }
.pane-meta { font-size: 11.5px; color: var(--ink3); margin: 2px 0 0; }

.icon-btn {
  flex: 0 0 auto; width: 26px; height: 26px;
  display: grid; place-items: center; cursor: pointer;
  border: 1px solid var(--border); border-radius: 7px;
  background: var(--surface); color: var(--ink3);
}
.icon-btn:hover { border-color: var(--accent); color: var(--accent); }

/* Categories */
.cat-item {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 10px; border: 0; border-radius: 8px; cursor: pointer;
  background: transparent; text-align: left; color: var(--ink);
}
.cat-item:hover { background: var(--panel); }
.cat-item--active { background: var(--panel); box-shadow: inset 2px 0 0 var(--accent); }
.cat-icon { flex: 0 0 auto; color: var(--ink3); }
.cat-item--active .cat-icon { color: var(--accent); }
.cat-body { flex: 1 1 auto; min-width: 0; }
.cat-label { display: block; font-size: 13px; font-weight: 600; }
.cat-desc {
  display: block; font-size: 11px; color: var(--ink3);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.cat-count {
  flex: 0 0 auto; font-size: 11px; font-weight: 650;
  padding: 2px 8px; border-radius: 999px;
  background: var(--border); color: var(--ink3);
}
.cat-count--active { background: var(--accent); color: #fff; }

/* Item list */
.pane-search {
  display: flex; align-items: center; gap: 6px;
  margin: 0 14px 8px; padding: 5px 9px;
  border: 1px solid var(--border); border-radius: 8px; color: var(--ink3);
}
.pane-search--inline { margin: 0; flex: 1 1 160px; }
.pane-search-input {
  border: 0; outline: none; background: transparent; width: 100%;
  font-size: 12.5px; color: var(--ink);
}
.pane-search-input::placeholder { color: var(--ink4); }

.item-list { flex: 1 1 auto; overflow-y: auto; padding: 0 8px 8px; }
.item-empty { font-size: 12.5px; color: var(--ink3); padding: 10px 6px; margin: 0; }

.item {
  display: flex; align-items: center; gap: 10px; width: 100%;
  padding: 9px 8px; margin-bottom: 2px; cursor: pointer;
  background: transparent; border: 0; border-radius: 8px;
  text-align: left; color: var(--ink);
}
.item:hover { background: var(--panel); }
.item--active { background: var(--panel); box-shadow: inset 2px 0 0 var(--accent); }
.item-icon {
  flex: 0 0 auto; width: 26px; height: 26px;
  display: grid; place-items: center;
  border-radius: 7px; background: var(--panel); color: var(--ink3);
}
.item--active .item-icon { background: var(--surface); color: var(--accent); }
.item-body { flex: 1 1 auto; min-width: 0; }
.item-name {
  display: block; font-size: 12.5px; font-weight: 600;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.item--active .item-name { color: var(--accent); }
.item-meta { display: block; font-size: 11px; color: var(--ink3); }
.item-chevron { flex: 0 0 auto; color: var(--ink4); }

/* Detail */
.detail-empty {
  flex: 1 1 auto; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 10px;
  color: var(--ink4); text-align: center;
}
.detail-empty p { font-size: 13px; color: var(--ink3); margin: 0; max-width: 220px; }

.detail-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; padding-bottom: 14px; margin-bottom: 16px;
  border-bottom: 1px solid var(--border);
}
.detail-title { font-size: 16px; font-weight: 650; color: var(--ink); margin: 0; word-break: break-word; }
.detail-meta { font-size: 11.5px; color: var(--ink3); margin: 3px 0 0; }

.form-group { margin-bottom: 18px; }
.form-label {
  display: block; font-size: 12px; font-weight: 600;
  color: var(--ink2, var(--ink)); margin-bottom: 6px;
}
.form-input {
  width: 100%; padding: 8px 10px; font-size: 13px;
  color: var(--ink); background: var(--surface);
  border: 1px solid var(--border); border-radius: 8px; outline: none;
}
.form-input:focus { border-color: var(--accent); }
.input-error { border-color: #b91c1c; }
.error-msg { display: block; font-size: 11.5px; color: #b91c1c; margin-top: 4px; }
.field-hint { display: block; font-size: 11.5px; color: var(--ink3); margin-top: 5px; }
.field-hint code { font-size: 11px; }

.muted-note { font-size: 12.5px; color: var(--ink3); margin: 0 0 8px; }

.tag-row { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 2px; }
.tag-row--wrap { flex-wrap: wrap; overflow: visible; }
.tag {
  font-size: 11.5px; padding: 3px 9px; border-radius: 999px; white-space: nowrap;
  background: var(--panel); border: 1px solid var(--border); color: var(--ink3);
}
.tag--link { cursor: pointer; }
.tag--link:hover { border-color: var(--accent); color: var(--accent); }

.detail-actions { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
.detail-actions--sticky {
  position: sticky; bottom: -18px; gap: 12px;
  padding: 12px 0; margin-top: 4px;
  background: var(--surface); border-top: 1px solid var(--border);
}
.dirty-note { margin-right: auto; font-size: 12px; color: #b45309; }

.assign-head { margin-bottom: 14px; }
.assign-tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.link-btn {
  background: none; border: 0; cursor: pointer; padding: 0;
  font-size: 12px; color: var(--accent);
}
.link-btn:hover { text-decoration: underline; }

.assign-groups {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 14px; align-items: start;
}
.assign-group { border: 1px solid var(--border); border-radius: 8px; padding: 10px; }
.assign-group-head {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid var(--border);
}
.assign-group-name { font-size: 12px; font-weight: 650; color: var(--ink); }
.assign-group-count { font-size: 11px; color: var(--ink3); }
.check-row {
  display: flex; align-items: center; gap: 8px;
  padding: 4px 2px; font-size: 12.5px; color: var(--ink); cursor: pointer;
}
.check-row:hover { color: var(--accent); }
.check-name { word-break: break-word; }

.confirm-text { font-size: 13.5px; color: var(--ink); margin: 0; }
.confirm-warn { font-size: 12.5px; color: #b45309; margin: 10px 0 0; }

@media (max-width: 1100px) {
  .access-grid { grid-template-columns: 1fr; height: auto; }
  .pane { height: auto; max-height: 420px; }
}
</style>
