<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Base Camp Hotels</h1>
        <p class="page-sub">{{ hotels.length }} hotels · {{ activeCount }} active</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['hotels']" />
        <Button variant="primary" size="sm" @click="openModal">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">Add hotel</span>
        </Button>
      </div>
    </div>

    <div class="table-controls-wrapper">
      <div class="left-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input
            v-model="search"
            type="text"
            class="search-input"
            placeholder="Search by hotel name…"
          />
        </div>
        <select v-model="statusFilter" class="filter-select">
          <option value="all">All Status</option>
          <option value="active">Active</option>
          <option value="disabled">Disabled</option>
        </select>
      </div>
    </div>

    <div class="table-card">
      <div class="table-header">
        <div>Hotel</div>
        <div>Status</div>
        <div style="text-align: center;">Actions</div>
      </div>
      <div v-if="!filtered.length" class="empty-row">No hotels found.</div>
      <div v-for="h in filtered" :key="h.id" class="table-row">
        <div class="cell-hotel">
          <div class="hotel-avatar">{{ initials(h.name) }}</div>
          <div class="hotel-name">{{ h.name }}</div>
        </div>
        <div class="cell-status">
          <div :style="{ fontSize: '11px', fontWeight: '600', color: h.disabled ? 'var(--ink4)' : tokens.ok }">
            ● {{ h.disabled ? 'Disabled' : 'Active' }}
          </div>
        </div>
        <div class="cell-actions">
          <TableActions @edit="openEditModal(h)" @delete="openDeleteModal(h)" />
        </div>
      </div>
    </div>

    <!-- Add Hotel Modal -->
    <div v-if="showAddModal" class="modal-backdrop" @click.self="closeModal">
      <div class="hotel-modal">
        <div class="modal-header">
          <h3 class="modal-title">Add Base Camp Hotel</h3>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Hotel Name <span class="required">*</span></label>
            <input
              v-model="newHotel.name"
              type="text"
              :class="['form-input', { 'form-input--error': errors.name }]"
              placeholder="e.g., Hilton Casablanca"
              @keyup.enter="addHotel"
            />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
          <div class="form-group">
            <div class="toggle-field">
              <div class="toggle-switch" @click="newHotel.disabled = !newHotel.disabled" :class="{ 'toggle-switch--on': newHotel.disabled }">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">Disabled</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="addHotel" :disabled="isSubmitting">
            {{ isSubmitting ? 'Adding...' : 'Add Hotel' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Edit Hotel Modal -->
    <div v-if="showEditModal" class="modal-backdrop" @click.self="closeEditModal">
      <div class="hotel-modal">
        <div class="modal-header">
          <h3 class="modal-title">Edit Base Camp Hotel</h3>
          <button class="modal-close" @click="closeEditModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Hotel Name <span class="required">*</span></label>
            <input
              v-model="editHotel.name"
              type="text"
              :class="['form-input', { 'form-input--error': errors.name }]"
              placeholder="e.g., Hilton Casablanca"
              @keyup.enter="updateHotel"
            />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
          <div class="form-group">
            <div class="toggle-field">
              <div class="toggle-switch" @click="editHotel.disabled = !editHotel.disabled" :class="{ 'toggle-switch--on': editHotel.disabled }">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">Disabled</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeEditModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="updateHotel" :disabled="isSubmitting">
            {{ isSubmitting ? 'Updating...' : 'Update Hotel' }}
          </Button>
        </div>
      </div>
    </div>

    <ConfirmModal
      :show="showDeleteModal"
      tone="danger"
      title="Delete Base Camp Hotel"
      :message="hotelToDelete ? `Are you sure you want to delete <strong>${hotelToDelete.name}</strong>?` : ''"
      :processing="isSubmitting"
      @close="closeDeleteModal"
      @confirm="deleteHotel"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '../Components/AppLayout.vue';
import { LMS_TOKENS } from '@/Composables/useTokens.js';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';
import TableActions from '../Components/TableActions.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  hotels: { type: Array, default: () => [] },
});

const tokens = LMS_TOKENS;
const search = ref('');
const statusFilter = ref('all');

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const isSubmitting = ref(false);
const errors = ref({});

const newHotel = ref({ name: '', disabled: false });
const editHotel = ref({ id: null, name: '', disabled: false });
const hotelToDelete = ref(null);

const filtered = computed(() => {
  let results = props.hotels;

  const q = search.value.trim().toLowerCase();
  if (q) {
    results = results.filter(h => (h.name || '').toLowerCase().includes(q));
  }

  if (statusFilter.value === 'active') {
    results = results.filter(h => !h.disabled);
  } else if (statusFilter.value === 'disabled') {
    results = results.filter(h => h.disabled);
  }

  return results;
});

const activeCount = computed(() => props.hotels.filter(h => !h.disabled).length);

function initials(name) {
  return (name || '').split(' ').map(w => w[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

function openModal() {
  errors.value = {};
  newHotel.value = { name: '', disabled: false };
  showAddModal.value = true;
}

function closeModal() {
  errors.value = {};
  showAddModal.value = false;
}

function addHotel() {
  errors.value = {};

  if (!newHotel.value.name || !newHotel.value.name.trim()) {
    errors.value.name = 'Hotel name is required';
    return;
  }

  isSubmitting.value = true;

  router.post('/base-camp-hotels', newHotel.value, {
    preserveScroll: true,
    onSuccess: () => {
      showAddModal.value = false;
      errors.value = {};
      isSubmitting.value = false;
      newHotel.value = { name: '', disabled: false };
      router.reload({ only: ['hotels'] });
    },
    onError: (backendErrors) => {
      errors.value = backendErrors;
      isSubmitting.value = false;
    },
  });
}

function openEditModal(hotel) {
  errors.value = {};
  editHotel.value = { id: hotel.id, name: hotel.name, disabled: !!hotel.disabled };
  showEditModal.value = true;
}

function closeEditModal() {
  errors.value = {};
  showEditModal.value = false;
}

function updateHotel() {
  errors.value = {};

  if (!editHotel.value.name || !editHotel.value.name.trim()) {
    errors.value.name = 'Hotel name is required';
    return;
  }

  isSubmitting.value = true;

  router.put(`/base-camp-hotels/${editHotel.value.id}`, editHotel.value, {
    preserveScroll: true,
    onSuccess: () => {
      showEditModal.value = false;
      errors.value = {};
      isSubmitting.value = false;
      router.reload({ only: ['hotels'] });
    },
    onError: (backendErrors) => {
      errors.value = backendErrors;
      isSubmitting.value = false;
    },
  });
}

function openDeleteModal(hotel) {
  hotelToDelete.value = hotel;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  hotelToDelete.value = null;
  showDeleteModal.value = false;
}

function deleteHotel() {
  if (!hotelToDelete.value) return;

  isSubmitting.value = true;

  router.delete(`/base-camp-hotels/${hotelToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteModal.value = false;
      hotelToDelete.value = null;
      isSubmitting.value = false;
      router.reload({ only: ['hotels'] });
    },
    onError: () => {
      isSubmitting.value = false;
    },
  });
}
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.header-actions { display: flex; align-items: center; gap: 10px; }

.table-controls-wrapper {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.left-controls {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  flex: 1;
}

.filter-select {
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--surface);
  color: var(--ink2);
  font-size: 13px;
  font-family: inherit;
  cursor: pointer;
  transition: all 0.13s;
  outline: none;
}
.filter-select:hover { border-color: var(--accent); }
.filter-select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  transition: border-color 0.13s;
}
.search-box:focus-within { border-color: var(--accent); }
.search-box svg { color: var(--ink3); flex-shrink: 0; }
.search-box .search-input {
  border: none; background: none; outline: none;
  font-size: 13px; color: var(--ink); width: 240px; padding: 0;
}
.search-box .search-input::placeholder { color: var(--ink4); }

.table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.table-header {
  display: grid;
  grid-template-columns: 3fr 1.2fr 120px;
  gap: 12px;
  padding: 10px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 11px;
  font-weight: 700;
  color: var(--ink3);
  letter-spacing: 0.6px;
  text-transform: uppercase;
  background: var(--panel);
}

.table-row {
  display: grid;
  grid-template-columns: 3fr 1.2fr 120px;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  align-items: center;
  transition: background-color 0.13s;
}
.table-row:hover { background: var(--panel); }
.table-row:last-child { border-bottom: none; }

.empty-row {
  padding: 24px 16px;
  text-align: center;
  font-size: 13px;
  color: var(--ink4);
}

.cell-hotel { display: flex; align-items: center; gap: 12px; }

.hotel-avatar {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.hotel-name { font-size: 13px; font-weight: 600; color: var(--ink); }

.cell-actions { display: flex; align-items: center; justify-content: center; gap: 6px; }

.btn-icon { font-size: 18px; font-weight: 300; line-height: 1; }

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.hotel-modal {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}

.modal-title { font-size: 18px; font-weight: 700; color: var(--ink); margin: 0; }

.modal-close {
  background: none;
  border: none;
  font-size: 20px;
  color: var(--ink3);
  cursor: pointer;
  padding: 4px 8px;
  line-height: 1;
  border-radius: 4px;
  transition: all 0.13s;
}
.modal-close:hover { background: var(--panel); color: var(--ink); }

.modal-body { padding: 24px; max-height: 70vh; overflow-y: auto; }

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 10px;
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  background: var(--panel);
  border-radius: 0 0 12px 12px;
}

.form-group { margin-bottom: 20px; }
.form-group:last-child { margin-bottom: 0; }

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink2);
  margin-bottom: 8px;
}

.required { color: #EF4444; margin-left: 2px; }

.form-input {
  width: 100%;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-family: inherit;
  transition: all 0.13s;
}
.form-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
.form-input--error { border-color: #EF4444 !important; background: #FEF2F2; }
.form-input--error:focus { box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12) !important; }
.form-input::placeholder { color: var(--ink4); }

.form-error {
  display: block;
  color: #EF4444;
  font-size: 12px;
  margin-top: 6px;
  font-weight: 500;
}

.toggle-field { display: flex; align-items: center; justify-content: flex-start; gap: 12px; padding: 12px 0; }
.toggle-label { font-size: 14px; font-weight: 600; color: var(--ink2); }

.toggle-switch {
  position: relative;
  width: 44px;
  height: 24px;
  background: var(--border);
  border-radius: 24px;
  cursor: pointer;
  transition: background 0.3s ease;
}
.toggle-switch--on { background: var(--accent); }

.toggle-slider {
  position: absolute;
  top: 2px;
  left: 2px;
  width: 20px;
  height: 20px;
  background: #fff;
  border-radius: 50%;
  transition: transform 0.3s ease;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}
.toggle-switch--on .toggle-slider { transform: translateX(20px); }

@media (max-width: 768px) {
  .page-header { flex-direction: column; align-items: stretch; }
  .header-actions { width: 100%; }
  .table-controls-wrapper { flex-direction: column; gap: 12px; align-items: stretch; }
  .search-box { width: 100%; }
  .search-box .search-input { width: 100%; }
  .left-controls { width: 100%; flex-direction: column; }
  .filter-select { width: 100%; }
  .btn-text { display: none; }
  .table-header { display: none; }
  .table-row { grid-template-columns: 1fr auto; row-gap: 6px; }
}

@media (max-width: 480px) {
  .page-title { font-size: 18px; }
  .page-sub { font-size: 12px; }
  .hotel-modal { max-width: 100%; margin: 0; border-radius: 12px 12px 0 0; max-height: 90vh; }
  .modal-body { max-height: 60vh; }
}
</style>
