<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Airports</h1>
        <p class="page-sub">{{ airports.length }} airports · {{ inUseCount }} in use</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['airports']" />
        <Button v-if="canManage" variant="primary" size="sm" @click="openForm(null)">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">Add airport</span>
        </Button>
      </div>
    </div>

    <div class="table-controls-wrapper">
      <div class="search-box">
        <svg-icon name="search" :size="14" />
        <input
          v-model="search"
          type="text"
          class="search-input"
          placeholder="Search code, airport, city or country…"
        />
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <div>Code</div>
        <div>Airport</div>
        <div>City</div>
        <div>Country</div>
        <div>Used by</div>
        <div v-if="canManage" style="text-align: center;">Actions</div>
      </div>
      <div v-if="!filtered.length" class="empty-row">
        {{ airports.length ? 'No airports match your search.' : 'No airports yet.' }}
      </div>
      <div v-for="a in filtered" :key="a.id" class="table-row">
        <div><span class="code-badge">{{ a.code }}</span></div>
        <div class="airport-name">{{ a.name }}</div>
        <div class="muted">{{ a.city || '—' }}</div>
        <div class="muted">{{ a.country || '—' }}</div>
        <div class="usage">
          <template v-if="a.flights_count || a.teams_count">
            <span v-if="a.flights_count">{{ a.flights_count }} flight{{ a.flights_count === 1 ? '' : 's' }}</span>
            <span v-if="a.flights_count && a.teams_count"> · </span>
            <span v-if="a.teams_count">{{ a.teams_count }} team{{ a.teams_count === 1 ? '' : 's' }}</span>
          </template>
          <span v-else class="muted">Not used</span>
        </div>
        <div v-if="canManage" class="cell-actions">
          <TableActions @edit="openForm(a)" @delete="openDelete(a)" />
        </div>
      </div>
    </div>

    <!-- Add / Edit -->
    <Modal :show="showForm" @close="closeForm" max-width="480px">
      <template #title>{{ editing ? `Edit ${editing.code}` : 'Add Airport' }}</template>
      <form class="airport-form" @submit.prevent="submit">
        <div class="form-row">
          <div class="form-group form-group--code">
            <label class="form-label">Code <span class="required">*</span></label>
            <input
              v-model="form.code"
              type="text"
              maxlength="10"
              :class="['form-input', 'form-input--code', { 'form-input--error': errors.code }]"
              placeholder="DOH"
              autofocus
              @input="form.code = form.code.toUpperCase()"
            />
            <span v-if="errors.code" class="form-error">{{ errors.code }}</span>
          </div>
          <div class="form-group form-group--grow">
            <label class="form-label">Airport Name <span class="required">*</span></label>
            <input
              v-model="form.name"
              type="text"
              maxlength="255"
              :class="['form-input', { 'form-input--error': errors.name }]"
              placeholder="Hamad International Airport"
            />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group form-group--grow">
            <label class="form-label">City</label>
            <input v-model="form.city" type="text" maxlength="255" class="form-input" placeholder="Doha" />
            <span v-if="errors.city" class="form-error">{{ errors.city }}</span>
          </div>
          <div class="form-group form-group--grow">
            <label class="form-label">Country</label>
            <input v-model="form.country" type="text" maxlength="255" class="form-input" placeholder="Qatar" />
            <span v-if="errors.country" class="form-error">{{ errors.country }}</span>
          </div>
        </div>
        <p v-if="editing && (editing.flights_count || editing.teams_count)" class="form-hint">
          Changes apply to the {{ editing.flights_count }} flight(s) and {{ editing.teams_count }} team(s) using this airport.
        </p>
      </form>
      <template #footer>
        <Button variant="ghost" size="sm" :disabled="submitting" @click="closeForm">Cancel</Button>
        <Button variant="primary" size="sm" :processing="submitting" @click="submit">
          {{ editing ? 'Save changes' : 'Add airport' }}
        </Button>
      </template>
    </Modal>

    <!-- Delete: refused while the airport is in use -->
    <ConfirmModal
      :show="!!toDelete"
      :tone="toDeleteInUse ? 'primary' : 'danger'"
      :title="toDeleteInUse ? 'Airport in use' : 'Delete Airport'"
      :message="deleteMessage"
      :note="toDeleteInUse ? 'Point those flights and teams at another airport first, then delete this one.' : null"
      :confirm-label="toDeleteInUse ? 'OK' : 'Delete'"
      :hide-cancel="toDeleteInUse"
      :processing="submitting"
      @close="toDelete = null"
      @confirm="toDeleteInUse ? (toDelete = null) : confirmDelete()"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Modal from '../Components/Modal.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';
import TableActions from '../Components/TableActions.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import { useToast } from '@/Composables/useToast';

const props = defineProps({
  airports: { type: Array, default: () => [] },
});

const page = usePage();
const { success: showSuccessToast, error: showErrorToast } = useToast();
const canManage = computed(() => page.props.auth?.can?.['fleet.manage'] === true);

const search = ref('');
const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.airports;
  return props.airports.filter((a) =>
    [a.code, a.name, a.city, a.country].some((v) => (v || '').toLowerCase().includes(q))
  );
});
const inUseCount = computed(() => props.airports.filter((a) => a.flights_count || a.teams_count).length);

const showForm = ref(false);
const editing = ref(null);
const form = ref(blankForm());
const errors = ref({});
const submitting = ref(false);

function blankForm() {
  return { code: '', name: '', city: '', country: '' };
}

function openForm(airport) {
  editing.value = airport;
  errors.value = {};
  form.value = airport
    ? { code: airport.code, name: airport.name, city: airport.city || '', country: airport.country || '' }
    : blankForm();
  showForm.value = true;
}

function closeForm() {
  if (submitting.value) return;
  showForm.value = false;
}

function submit() {
  errors.value = {};
  if (!form.value.code.trim()) errors.value.code = 'Code is required.';
  if (!form.value.name.trim()) errors.value.name = 'Airport name is required.';
  if (Object.keys(errors.value).length) return;

  submitting.value = true;
  const url = editing.value ? `/airports/${editing.value.id}` : '/airports';
  router[editing.value ? 'put' : 'post'](url, form.value, {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false; },
    onError: (e) => { errors.value = e; },
    onFinish: () => { submitting.value = false; },
  });
}

const toDelete = ref(null);
const toDeleteInUse = computed(() => !!(toDelete.value && (toDelete.value.flights_count || toDelete.value.teams_count)));
const deleteMessage = computed(() => {
  const a = toDelete.value;
  if (!a) return '';
  return toDeleteInUse.value
    ? `<strong>${a.code}</strong> is used by ${a.flights_count} flight(s) and ${a.teams_count} team(s), so it can't be deleted.`
    : `Delete <strong>${a.code}</strong> · ${a.name}?`;
});

function openDelete(airport) {
  toDelete.value = airport;
}

function confirmDelete() {
  submitting.value = true;
  router.delete(`/airports/${toDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { toDelete.value = null; },
    onError: (e) => { showErrorToast(e.airport || 'Could not delete that airport.'); toDelete.value = null; },
    onFinish: () => { submitting.value = false; },
  });
}

watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) showSuccessToast(flash.success);
    if (flash?.error) showErrorToast(flash.error);
  },
  { deep: true }
);
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.header-actions { display: flex; align-items: center; gap: 10px; }
.btn-icon { font-size: 18px; font-weight: 300; line-height: 1; }

.table-controls-wrapper { display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap; }
.search-box {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 12px; background: var(--surface);
  border: 1px solid var(--border); border-radius: 7px;
  transition: border-color 0.13s;
}
.search-box:focus-within { border-color: var(--accent); }
.search-box svg { color: var(--ink3); flex-shrink: 0; }
.search-input {
  border: none; background: none; outline: none;
  font-size: 13px; color: var(--ink); width: 280px; padding: 0;
}
.search-input::placeholder { color: var(--ink4); }

.table-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.table-header, .table-row {
  display: grid;
  grid-template-columns: 90px 3fr 1.5fr 1.5fr 1.6fr 110px;
  gap: 12px; padding: 12px 16px; align-items: center;
}
.table-header {
  padding: 10px 16px; border-bottom: 1px solid var(--border);
  font-size: 11px; font-weight: 700; color: var(--ink3);
  letter-spacing: 0.6px; text-transform: uppercase; background: var(--panel);
}
.table-row { border-bottom: 1px solid var(--border); font-size: 13px; transition: background-color 0.13s; }
.table-row:hover { background: var(--panel); }
.table-row:last-child { border-bottom: none; }
.empty-row { padding: 24px 16px; text-align: center; font-size: 13px; color: var(--ink4); }

.code-badge {
  display: inline-block; padding: 3px 8px; border-radius: 6px;
  background: var(--accent-soft); color: var(--accent-fg, var(--accent));
  font-family: var(--mono); font-size: 12px; font-weight: 700; letter-spacing: .5px;
}
.airport-name { font-weight: 600; color: var(--ink); }
.muted { color: var(--ink3); }
.usage { font-size: 12px; color: var(--ink2, var(--ink)); }
.cell-actions { display: flex; justify-content: center; }

.airport-form { display: flex; flex-direction: column; gap: 16px; }
.form-row { display: flex; gap: 12px; }
.form-group { display: flex; flex-direction: column; min-width: 0; }
.form-group--code { width: 110px; flex: none; }
.form-group--grow { flex: 1; }
.form-label { font-size: 13px; font-weight: 600; color: var(--ink2); margin-bottom: 6px; }
.required { color: #EF4444; margin-left: 2px; }
.form-input {
  width: 100%; padding: 9px 12px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--surface); color: var(--ink);
  font-size: 14px; font-family: inherit; transition: all 0.13s;
}
.form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
.form-input::placeholder { color: var(--ink4); }
.form-input--code { font-family: var(--mono); font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
.form-input--error { border-color: #EF4444 !important; background: #FEF2F2; }
.form-error { color: #EF4444; font-size: 12px; margin-top: 5px; font-weight: 500; }
.form-hint { margin: 0; font-size: 12px; color: var(--ink3); }

@media (max-width: 768px) {
  .search-box, .search-input { width: 100%; }
  .btn-text { display: none; }
  .table-header { display: none; }
  .table-row { grid-template-columns: 70px 1fr auto; row-gap: 4px; }
  .table-row > :nth-child(3), .table-row > :nth-child(4), .table-row > :nth-child(5) { grid-column: 2; font-size: 12px; }
  .form-row { flex-direction: column; }
  .form-group--code { width: 100%; }
}
</style>
