<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Venues</h1>
        <p class="page-sub">{{ venues.length }} venue{{ venues.length !== 1 ? 's' : '' }}</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['venues']" />
        <Button variant="primary" size="sm" @click="openAddModal">
          <template #icon>
            <svg-icon name="plus" :size="14" style="color:#fff;" />
          </template>
          Add Venue
        </Button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <mini-stat label="Total Venues" :value="venues.length" />
      <mini-stat label="Stadiums" :value="countByType('stadium')" tone="primary" />
      <mini-stat label="Training Grounds" :value="countByType('training_ground')" tone="ok" />
      <mini-stat label="Hotels" :value="countByType('hotel')" />
    </div>

    <!-- Table controls -->
    <div class="table-header">
      <div class="table-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input v-model="searchQuery" type="text" placeholder="Search venues…" class="search-input" />
        </div>
        <select v-model="filterType" class="filter-select">
          <option value="">All Types</option>
          <option value="stadium">Stadium</option>
          <option value="training_ground">Training Ground</option>
          <option value="hotel">Hotel</option>
          <option value="conference">Conference</option>
          <option value="other">Other</option>
        </select>
      </div>
    </div>

    <!-- Table + Detail panel -->
    <div class="venues-container">
      <div class="table-card" :class="{ 'with-panel': selectedVenue }">
        <div style="overflow-x:auto;">
          <table class="venues-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>City</th>
                <th>Country</th>
                <th>Type</th>
                <th class="center">Capacity</th>
                <th class="center" style="width:100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="venue in filteredVenues"
                :key="venue.id"
                class="table-row"
                :class="{ 'table-row--selected': selectedVenue?.id === venue.id }"
                @click="selectVenue(venue)"
              >
                <td class="venue-name-cell">
                  <div class="venue-name-primary">{{ venue.name }}</div>
                </td>
                <td>{{ venue.city || '—' }}</td>
                <td>
                  <span v-if="venue.country" style="display: inline-flex; align-items: center; gap: 6px">
                    <flag-icon :code="venue.country_code" :fallback="venue.country.flag" />
                    {{ venue.country.country_name }}</span
                  >
                  <span v-else>—</span>
                </td>
                <td>
                  <span :class="['type-pill', `type-pill--${venue.type}`]">{{ formatType(venue.type) }}</span>
                </td>
                <td class="center mono">{{ venue.capacity ? venue.capacity.toLocaleString() : '—' }}</td>
                <td class="actions-cell" @click.stop>
                  <TableActions
                    @edit="editVenue(venue)"
                    @delete="openDeleteModal(venue)"
                  />
                </td>
              </tr>
              <tr v-if="filteredVenues.length === 0">
                <td colspan="6" style="text-align:center;padding:40px;color:var(--ink3);">No venues found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Detail panel -->
      <transition name="slide-card">
        <div v-if="selectedVenue" class="detail-card">
          <div class="detail-card-header">
            <div>
              <h3 class="detail-card-venue-name">{{ selectedVenue.name }}</h3>
              <div style="font-size:12px;color:var(--ink3);margin-top:2px;">
                <span :class="['type-pill', `type-pill--${selectedVenue.type}`]">{{ formatType(selectedVenue.type) }}</span>
              </div>
            </div>
            <button @click="selectedVenue = null" class="detail-card-close">
              <svg-icon name="x" :size="18" />
            </button>
          </div>

          <div class="detail-card-content">
            <div class="detail-section">
              <h4 class="detail-section-title">Venue Info</h4>
              <div class="detail-row">
                <span class="detail-label">City</span>
                <span class="detail-value">{{ selectedVenue.city || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Country</span>
                <span class="detail-value">
                  <span v-if="selectedVenue.country" style="display: inline-flex; align-items: center; gap: 6px">
                    <flag-icon :code="selectedVenue.country_code" :fallback="selectedVenue.country.flag" />
                    {{ selectedVenue.country.country_name }}</span
                  >
                  <span v-else>—</span>
                </span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Type</span>
                <span class="detail-value">{{ formatType(selectedVenue.type) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Capacity</span>
                <span class="detail-value mono">{{ selectedVenue.capacity ? selectedVenue.capacity.toLocaleString() : '—' }}</span>
              </div>
              <div v-if="selectedVenue.address" class="detail-row">
                <span class="detail-label">Address</span>
                <span class="detail-value">{{ selectedVenue.address }}</span>
              </div>
              <div v-if="selectedVenue.notes" class="detail-row">
                <span class="detail-label">Notes</span>
                <span class="detail-value">{{ selectedVenue.notes }}</span>
              </div>
            </div>
          </div>

          <div class="detail-card-footer">
            <Button variant="secondary" size="sm" @click="selectedVenue = null">Close</Button>
            <Button variant="primary" size="sm" @click="editVenue(selectedVenue); selectedVenue = null">Edit</Button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Add / Edit Venue Modal -->
    <Modal :show="showVenueModal" @close="showVenueModal = false" max-width="560px">
      <template #title>{{ editingVenue ? 'Edit Venue' : 'Add Venue' }}</template>
      <form @submit.prevent="submitVenue" class="venue-form">
        <div class="form-group">
          <label class="form-label">Venue Name <span class="required">*</span></label>
          <input v-model="form.name" type="text" class="form-input" placeholder="Al Bayt Stadium" required />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">City</label>
            <input v-model="form.city" type="text" class="form-input" placeholder="Doha" maxlength="255" />
          </div>
          <div class="form-group">
            <label class="form-label">Country</label>
            <select v-model="form.country_code" class="form-select">
              <option value="">— None —</option>
              <option v-for="c in countries" :key="c.country_code" :value="c.country_code">
                {{ c.flag }} {{ c.country_name }}
              </option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Type <span class="required">*</span></label>
            <select v-model="form.type" class="form-select" required>
              <option value="stadium">Stadium</option>
              <option value="training_ground">Training Ground</option>
              <option value="hotel">Hotel</option>
              <option value="conference">Conference</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Capacity</label>
            <input v-model="form.capacity" type="number" class="form-input" placeholder="60000" min="0" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input v-model="form.address" type="text" class="form-input" placeholder="Al Khor, Qatar" maxlength="500" />
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="form.notes" class="form-input" rows="3" />
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showVenueModal = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">
            {{ processing ? 'Saving…' : (editingVenue ? 'Save Changes' : 'Create Venue') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirm Modal -->
    <DeleteConfirmModal
      :show="showDeleteModal"
      title="Venue"
      :message="venueToDelete ? `Are you sure you want to delete <strong>${venueToDelete.name}</strong>?` : ''"
      :processing="deleting"
      @close="showDeleteModal = false; venueToDelete = null;"
      @confirm="confirmDelete"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout       from '../Components/AppLayout.vue';
import MiniStat        from '../Components/MiniStat.vue';
import SvgIcon         from '../Components/SvgIcon.vue';
import Button          from '../Components/Button.vue';
import Modal           from '../Components/Modal.vue';
import DeleteConfirmModal from '../Components/DeleteConfirmModal.vue';
import TableActions    from '../Components/TableActions.vue';
import RefreshButton   from '../Components/RefreshButton.vue';
import FlagIcon        from '../Components/FlagIcon.vue';

const props = defineProps({
  venues:    { type: Array, required: true },
  countries: { type: Array, required: true },
});

// ── State ──────────────────────────────────────────────────────────────────
const searchQuery   = ref('');
const filterType    = ref('');
const selectedVenue = ref(null);

const showVenueModal  = ref(false);
const showDeleteModal = ref(false);
const processing      = ref(false);
const deleting        = ref(false);
const editingVenue    = ref(null);
const venueToDelete   = ref(null);

const form = ref(emptyForm());

function emptyForm() {
  return { 
    name: '', 
    city: '', 
    country_code: '', 
    type: 'stadium', 
    capacity: '', 
    address: '', 
    notes: '' 
  };
}

// ── Computed ───────────────────────────────────────────────────────────────
const filteredVenues = computed(() => {
  return props.venues.filter(v => {
    const q = searchQuery.value.toLowerCase();
    const matchQ = !q || 
      v.name.toLowerCase().includes(q) || 
      (v.city || '').toLowerCase().includes(q) ||
      (v.address || '').toLowerCase().includes(q);
    const matchT = !filterType.value || v.type === filterType.value;
    return matchQ && matchT;
  });
});

// ── Helpers ────────────────────────────────────────────────────────────────
function countByType(t) {
  return props.venues.filter(v => v.type === t).length;
}

function formatType(type) {
  const types = {
    'stadium': 'Stadium',
    'training_ground': 'Training Ground',
    'hotel': 'Hotel',
    'conference': 'Conference',
    'other': 'Other'
  };
  return types[type] || type;
}

function selectVenue(venue) {
  selectedVenue.value = selectedVenue.value?.id === venue.id ? null : venue;
}

// ── Venue CRUD ─────────────────────────────────────────────────────────────
function openAddModal() {
  editingVenue.value = null;
  form.value = emptyForm();
  showVenueModal.value = true;
}

function editVenue(venue) {
  editingVenue.value = venue;
  form.value = {
    name:         venue.name         || '',
    city:         venue.city         || '',
    country_code: venue.country_code || '',
    type:         venue.type         || 'stadium',
    capacity:     venue.capacity     || '',
    address:      venue.address      || '',
    notes:        venue.notes        || '',
  };
  showVenueModal.value = true;
}

function submitVenue() {
  processing.value = true;
  const url    = editingVenue.value ? `/venues/${editingVenue.value.id}` : '/venues';
  const method = editingVenue.value ? 'put' : 'post';
  router[method](url, form.value, {
    onFinish: () => { processing.value = false; showVenueModal.value = false; },
  });
}

function openDeleteModal(venue) {
  venueToDelete.value = venue;
  showDeleteModal.value = true;
}

function confirmDelete() {
  deleting.value = true;
  router.delete(`/venues/${venueToDelete.value.id}`, {
    onFinish: () => { deleting.value = false; showDeleteModal.value = false; venueToDelete.value = null; },
  });
}
</script>

<style scoped>
/* Reuse the same design tokens as Events */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title  { font-size:22px; font-weight:700; color:var(--ink); margin:0; }
.page-sub    { font-size:13px; color:var(--ink3); margin:2px 0 0; }
.header-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media(max-width:768px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }

.table-header   { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
.table-controls { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.search-box     { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 12px; }
.search-input   { background:transparent; border:none; outline:none; font-size:13px; color:var(--ink); width:200px; }
.filter-select  { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 10px; font-size:13px; color:var(--ink); cursor:pointer; }

.venues-container { display:flex; gap:16px; align-items:flex-start; }
.table-card       { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; flex:1; min-width:0; }
.table-card.with-panel { flex:1.4; }

.venues-table { width:100%; border-collapse:collapse; font-size:13px; }
.venues-table thead th { padding:10px 14px; text-align:left; font-size:11px; font-weight:600; color:var(--ink3); text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--border); white-space:nowrap; background:var(--panel); }
.venues-table thead th.center { text-align:center; }
.venues-table tbody td { padding:11px 14px; border-bottom:1px solid var(--border); color:var(--ink2); vertical-align:middle; }
.venues-table tbody tr:last-child td { border-bottom:none; }
.table-row { cursor:pointer; transition:background .12s; }
.table-row:hover   { background:var(--panel); }
.table-row--selected { background:var(--accent-soft) !important; }
.venue-name-primary { font-weight:600; color:var(--ink); }
.mono { font-family:monospace; }
.center { text-align:center; }
.actions-cell { padding:8px 14px !important; }

/* Type pills */
.type-pill { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; text-transform:capitalize; }
.type-pill--stadium         { background:#EFF6FF; color:#1d4ed8; }
.type-pill--training_ground { background:#F0FDF4; color:#15803d; }
.type-pill--hotel           { background:#FEF3C7; color:#92400e; }
.type-pill--conference      { background:#F3E8FF; color:#7e22ce; }
.type-pill--other           { background:var(--panel); color:var(--ink3); }

/* Detail card */
.detail-card { width:320px; flex-shrink:0; background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; }
.detail-card-header { display:flex; align-items:flex-start; justify-content:space-between; padding:16px; border-bottom:1px solid var(--border); }
.detail-card-venue-name { font-size:15px; font-weight:700; color:var(--ink); margin:0; }
.detail-card-close { background:none; border:none; cursor:pointer; color:var(--ink3); padding:2px; border-radius:4px; }
.detail-card-close:hover { background:var(--panel); }
.detail-card-content { padding:0 16px; overflow-y:auto; max-height:calc(100vh - 260px); }
.detail-card-footer { padding:12px 16px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; }
.detail-section { padding:14px 0; border-bottom:1px solid var(--border); }
.detail-section:last-child { border-bottom:none; }
.detail-section-title { font-size:11px; font-weight:700; color:var(--ink3); text-transform:uppercase; letter-spacing:.06em; margin:0 0 10px; }
.detail-row   { display:flex; justify-content:space-between; gap:12px; margin-bottom:7px; font-size:13px; }
.detail-label { color:var(--ink3); flex-shrink:0; }
.detail-value { color:var(--ink); text-align:right; }

/* Form */
.venue-form  { display:flex; flex-direction:column; gap:14px; }
.form-row   { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-group { display:flex; flex-direction:column; gap:4px; }
.form-label { font-size:12px; font-weight:600; color:var(--ink2); }
.required   { color:#ef4444; }
.form-input, .form-select { background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:8px 10px; font-size:13px; color:var(--ink); outline:none; }
.form-input:focus, .form-select:focus { border-color:var(--accent); }
.form-actions { display:flex; justify-content:flex-end; gap:8px; padding-top:4px; }

/* Slide-in transition */
.slide-card-enter-active, .slide-card-leave-active { transition:all .2s ease; }
.slide-card-enter-from, .slide-card-leave-to { opacity:0; transform:translateX(20px); }
</style>
