<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Fleet Management</h1>
        <p class="page-sub">Vehicles, drivers & transport partners</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['vehicles', 'providers', 'drivers']" />
        <Button v-if="activeTab !== 'history'" variant="primary" size="sm" @click="openAddModal">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">{{ addButtonText }}</span>
        </Button>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="stats-grid">
      <mini-stat label="Total vehicles" :value="vehicles.length" />
      <mini-stat label="On job" :value="vehicles.filter(v => v.status === 'on_job').length" tone="live" />
      <mini-stat label="Available" :value="vehicles.filter(v => v.status === 'available').length" tone="ok" />
      <mini-stat label="Maintenance" :value="vehicles.filter(v => v.status === 'maintenance').length" tone="warn" />
      <mini-stat label="Total drivers" :value="props.drivers.length" />
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button v-for="tab in tabs" :key="tab.value"
        :class="['tab', activeTab === tab.value ? 'tab--active' : '']"
        @click="activeTab = tab.value">
        {{ tab.label }}
        <span class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <div class="content-grid" :class="{ 'with-panel': selected }">
      <!-- Vehicles -->
      <template v-if="activeTab === 'vehicles'">
        <div class="table-card">
          <div class="table-header">
            <div>ID</div>
            <div>Type</div>
            <div>Capacity</div>
            <div>Plate</div>
            <div>Category</div>
            <div>Fuel</div>
            <div>Status</div>
          </div>
          <div v-for="v in vehicles" :key="v.code" class="table-row" :class="{ 'selected': selected?.code === v.code }" @click="selectVehicle(v)">
            <div class="cell-id">{{ v.code }}</div>
            <div class="cell-type">{{ v.vehicle_type }}</div>
            <div class="cell-capacity">{{ v.capacity }} seats</div>
            <div class="cell-plate">{{ v.plate_number || '—' }}</div>
            <div class="cell-category">{{ v.category || '—' }}</div>
            <div class="fuel-cell" :class="{ 'low': parseInt(v.fuel_level) < 50, 'medium': parseInt(v.fuel_level) >= 50 && parseInt(v.fuel_level) < 70 }">{{ v.fuel_level }}</div>
            <div><status-pill :tone="v.status === 'available' ? 'ok' : v.status === 'on_job' ? 'live' : 'neutral'">{{ v.status }}</status-pill></div>
          </div>
        </div>
      </template>

      <!-- Drivers -->
      <template v-else-if="activeTab === 'drivers'">
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th>Name</th><th>Phone</th><th>License</th><th>Status</th>
          </tr></thead>
          <tbody>
            <tr v-for="d in props.drivers" :key="d.id">
              <td>
                <div class="driver-row">
                  <div class="avatar">{{ initials(d.name || 'N/A') }}</div>
                  <div>
                    <div class="driver-name">{{ d.name || '—' }}</div>
                  </div>
                </div>
              </td>
              <td class="mono">{{ d.phone || '—' }}</td>
              <td class="mono">{{ d.license_number || '—' }}</td>
              <td><status-pill :tone="d.status === 'on_shift' ? 'ok' : d.status === 'available' ? 'primary' : 'neutral'">{{ d.status }}</status-pill></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

      <!-- Providers -->
      <template v-else-if="activeTab === 'providers'">
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th>Code</th><th>Provider</th><th>Vehicles</th><th>Drivers</th><th>Contact</th><th>Phone</th><th>Rating</th><th>Status</th>
          </tr></thead>
          <tbody>
            <tr v-for="p in props.providers" :key="p.id">
              <td class="mono">{{ p.code }}</td>
              <td>
                <div class="provider-name">{{ p.name }}</div>
                <div class="provider-type" v-if="p.notes">{{ p.notes }}</div>
              </td>
              <td>{{ p.total_vehicles }}</td>
              <td>{{ p.total_drivers }}</td>
              <td>{{ p.contact_person || '—' }}</td>
              <td class="mono">{{ p.phone || '—' }}</td>
              <td>⭐ {{ p.rating }}</td>
              <td><status-pill :tone="p.status === 'active' ? 'ok' : 'neutral'">{{ p.status }}</status-pill></td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

      <!-- History -->
      <template v-else>
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th>Date</th><th>Vehicle</th><th>Job</th><th>Driver</th><th>Notes</th>
          </tr></thead>
          <tbody>
            <tr v-for="h in history" :key="h.id">
              <td class="mono">{{ h.date }}</td>
              <td>{{ h.vehicle }}</td>
              <td class="mono">{{ h.job }}</td>
              <td>{{ h.driver }}</td>
              <td class="note-cell">{{ h.notes }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

      <!-- Vehicle detail panel -->
      <div v-if="selected" class="detail-panel">
        <div class="panel-header">
          <div>
            <div class="panel-kicker">{{ selected.code }}</div>
            <div class="panel-title">{{ selected.vehicle_type }}</div>
          </div>
          <button class="panel-close" @click="selected = null">✕</button>
        </div>
        <div class="panel-body">
          <div class="status-badge">
            <status-pill :tone="selected.status === 'available' ? 'ok' : 'live'">{{ selected.status }}</status-pill>
          </div>
          <div class="detail-grid">
            <div class="detail-item">
              <div class="detail-label">Plate Number</div>
              <div class="detail-val">{{ selected.plate_number || '—' }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Capacity</div>
              <div class="detail-val">{{ selected.capacity }} seats</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Category</div>
              <div class="detail-val">{{ selected.category || '—' }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Fuel</div>
              <div class="detail-val" :class="{ 'text-danger': parseInt(selected.fuel_level) < 50, 'text-warn': parseInt(selected.fuel_level) >= 50 && parseInt(selected.fuel_level) < 70 }">{{ selected.fuel_level }}</div>
            </div>
          </div>
          <div class="action-buttons">
            <Button variant="primary" size="md" @click="showAssign = true">Assign to job</Button>
            <Button variant="secondary" size="md">View history</Button>
            <Button variant="ghost" size="md" @click="selected = null">Close</Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div v-if="showAddVehicle" class="modal-backdrop" @click.self="showAddVehicle = false">
      <div class="vehicle-modal">
        <div class="modal-header">
          <h3 class="modal-title">Add New Vehicle</h3>
          <button class="modal-close" @click="showAddVehicle = false">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Vehicle Code</label>
              <input v-model="newVehicle.code" type="text" class="form-input" placeholder="e.g., Coach 01" />
            </div>
            <div class="form-group">
              <label class="form-label">Vehicle Type</label>
              <input v-model="newVehicle.vehicle_type" type="text" class="form-input" placeholder="e.g., Coach, Minivan" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Capacity</label>
              <input v-model="newVehicle.capacity" type="number" class="form-input" placeholder="Number of seats" />
            </div>
            <div class="form-group">
              <label class="form-label">Plate Number</label>
              <input v-model="newVehicle.plate_number" type="text" class="form-input" placeholder="e.g., ABC-1234" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Category</label>
              <select v-model="newVehicle.category" class="form-input">
                <option value="">Select category</option>
                <option value="Team">Team</option>
                <option value="Official">Official</option>
                <option value="VIP">VIP</option>
                <option value="Media">Media</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Fuel Level</label>
              <input v-model="newVehicle.fuel_level" type="text" class="form-input" placeholder="e.g., 75%" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Status</label>
              <select v-model="newVehicle.status" class="form-input">
                <option value="available">Available</option>
                <option value="on_job">On Job</option>
                <option value="maintenance">Maintenance</option>
                <option value="standby">Standby</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Provider</label>
              <select v-model="newVehicle.provider_id" class="form-input">
                <option value="">Select provider</option>
                <option v-for="p in props.providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea v-model="newVehicle.notes" class="form-input" rows="3" placeholder="Additional notes (optional)"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="showAddVehicle = false">Cancel</Button>
          <Button variant="primary" size="sm" @click="addVehicle">Add Vehicle</Button>
        </div>
      </div>
    </div>

    <!-- Assign to job modal -->
    <div v-if="showAssign" class="modal-backdrop" @click.self="showAssign = false">
      <div class="assign-modal">
        <div class="modal-title">Assign Vehicle</div>
        <div class="modal-subtitle">Assign <strong>{{ selected?.code }}</strong> to a job</div>
        <div class="job-list">
          <div v-for="j in availableJobs" :key="j.id" 
            class="job-option" 
            :class="{ 'selected': assignedJob === j.id }"
            @click="assignedJob = j.id">
            <div class="job-header">
              <span class="job-id">{{ j.id }}</span>
              <status-pill tone="neutral" size="sm">{{ j.phase }}</status-pill>
            </div>
            <div class="job-route">{{ j.route }}</div>
            <div class="job-window">{{ j.window }}</div>
          </div>
        </div>
        <div class="modal-actions">
          <Button variant="ghost" size="sm" @click="showAssign = false">Cancel</Button>
          <Button variant="primary" size="sm" @click="showAssign = false">Confirm assign</Button>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import MiniStat from '../Components/MiniStat.vue';
import Button from '../Components/Button.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  vehicles: {
    type: Array,
    required: true
  },
  providers: {
    type: Array,
    required: true
  },
  drivers: {
    type: Array,
    required: true
  }
});

const activeTab = ref('vehicles');
const selected = ref(null);
const showAssign = ref(false);
const assignedJob = ref(null);
const showAddVehicle = ref(false);
const newVehicle = ref({
  code: '',
  vehicle_type: '',
  capacity: null,
  plate_number: '',
  category: '',
  fuel_level: '100%',
  status: 'available',
  provider_id: null,
  notes: '',
  is_active: 1
});





const history = [
  { id: 'h1', date: '18 Apr 14:18', vehicle: 'Coach 12', job: 'J-1042', driver: 'L. Fontaine', notes: 'Arrived on time, clean handover' },
  { id: 'h2', date: '18 Apr 13:40', vehicle: 'Coach 07', job: 'J-1038', driver: 'S. Reyes',    notes: 'Vehicle swap after pre-dispatch check' },
  { id: 'h3', date: '17 Apr 22:10', vehicle: 'Coach 03', job: 'J-1031', driver: 'A. Bakr',     notes: 'Night run, fuel refill needed' },
  { id: 'h4', date: '17 Apr 19:45', vehicle: 'Van 22',   job: 'J-1028', driver: 'K. Petrov',   notes: 'Media pool pickup, 3 extra bags' },
];

const availableJobs = [
  { id: 'J-1055', phase: 'Queued', route: 'Airport → Team Hotel', window: 'Today 16:30–17:00' },
  { id: 'J-1056', phase: 'Queued', route: 'Stadium → Airport', window: 'Today 22:00–22:30' },
  { id: 'J-1057', phase: 'Planned', route: 'Hotel → Training Ground', window: 'Tomorrow 09:00–09:30' },
];

const tabs = computed(() => [
  { value: 'vehicles',  label: 'Vehicles',  count: props.vehicles.length },
  { value: 'drivers',   label: 'Drivers',   count: props.drivers.length },
  { value: 'providers', label: 'Providers', count: props.providers.length },
  { value: 'history',   label: 'History',   count: history.length },
]);

const addButtonText = computed(() => {
  switch (activeTab.value) {
    case 'vehicles': return 'Add vehicle';
    case 'drivers': return 'Add driver';
    case 'providers': return 'Add provider';
    default: return 'Add';
  }
});

function initials(name) {
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

function selectVehicle(v) {
  selected.value = selected.value?.code === v.code ? null : v;
}

function openAddModal() {
  if (activeTab.value === 'vehicles') {
    showAddVehicle.value = true;
  }
  // TODO: Add modals for drivers and providers
}

function addVehicle() {
  // TODO: Send to backend API
  console.log('Adding vehicle:', newVehicle.value);
  showAddVehicle.value = false;
  // Reset form
  newVehicle.value = {
    code: '',
    vehicle_type: '',
    capacity: null,
    plate_number: '',
    category: '',
    fuel_level: '100%',
    status: 'available',
    provider_id: null,
    notes: '',
    is_active: 1
  };
}
</script>

<style scoped>
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 16px; }
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}

.tabs { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid var(--border); }
.tab {
  padding: 9px 16px; background: none; border: none;
  border-bottom: 2px solid transparent; margin-bottom: -1px;
  font-size: 13.5px; font-weight: 500; color: var(--ink3); cursor: pointer;
  display: flex; align-items: center; gap: 6px;
}
.tab:hover { color: var(--ink); }
.tab--active { color: var(--accent); border-bottom-color: var(--accent); }
.tab-count {
  background: var(--panel); border: 1px solid var(--border);
  border-radius: 10px; padding: 0 6px; font-size: 11px; font-weight: 700;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
  transition: grid-template-columns 0.2s;
}

.content-grid.with-panel {
  grid-template-columns: 1fr 300px;
}

.table-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}

.table-header {
  display: grid; grid-template-columns: 80px 1fr 100px 1fr 1fr 80px 90px;
  gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border);
  font-size: 11px; font-weight: 700; color: var(--ink3);
  letter-spacing: 0.6px; text-transform: uppercase;
  background: var(--panel); position: sticky; top: 0;
}

.table-row {
  display: grid; grid-template-columns: 80px 1fr 100px 1fr 1fr 80px 90px;
  gap: 10px; padding: 12px 14px; cursor: pointer;
  border-bottom: 1px solid var(--border); align-items: center;
  transition: background-color 0.13s;
  border-left: 3px solid transparent;
}

.table-row:hover { background: var(--panel); }
.table-row:last-child { border-bottom: none; }
.table-row.selected {
  background: var(--accent-soft);
  border-left-color: var(--accent);
}

.cell-id { font-family: var(--font-mono, monospace); font-size: 11px; color: var(--ink); font-weight: 700; }
.cell-type { font-size: 12px; color: var(--ink); font-weight: 600; }
.cell-capacity { font-size: 12px; color: var(--ink2); }
.cell-plate { font-family: var(--font-mono, monospace); font-size: 11px; color: var(--ink2); }
.cell-category { font-size: 12px; color: var(--ink2); font-weight: 500; }
.fuel-cell { font-family: var(--font-mono, monospace); font-size: 12px; font-weight: 600; color: var(--ink2); }
.fuel-cell.low { color: #dc2626; }
.fuel-cell.medium { color: #ca8a04; }

.fleet-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.fleet-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 16px; cursor: pointer;
  transition: border-color 0.13s, box-shadow 0.13s;
}
.fleet-card:hover { border-color: var(--accent); box-shadow: 0 2px 8px rgba(99,102,241,0.08); }
.fc-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; }
.fleet-icon { font-size: 22px; }
.fc-id { font-size: 16px; font-weight: 700; color: var(--ink); margin-bottom: 2px; }
.fc-type { font-size: 12.5px; color: var(--ink3); margin-bottom: 4px; }
.fc-provider { font-size: 12px; color: var(--ink4); margin-bottom: 8px; }
.fc-meta { display: flex; justify-content: space-between; font-size: 12px; color: var(--ink3); }

.data-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.data-table th {
  padding: 9px 14px; text-align: left;
  font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--ink3); border-bottom: 1px solid var(--border); background: var(--panel);
  white-space: nowrap;
}
.data-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--panel); }

.driver-row { display: flex; align-items: center; gap: 10px; }
.avatar {
  width: 32px; height: 32px; border-radius: 50%;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.driver-name { font-weight: 600; color: var(--ink); }
.driver-phone { font-size: 11.5px; color: var(--ink3); }
.provider-name { font-weight: 600; color: var(--ink); }
.provider-type { font-size: 11.5px; color: var(--ink3); }
.note-cell { color: var(--ink3); font-size: 12.5px; }
.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); }

/* Detail Panel */
.detail-panel {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: fit-content;
}

.panel-header {
  padding: 16px;
  border-bottom: 1px solid var(--border);
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.panel-kicker {
  font-size: 11px;
  color: var(--ink3);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  margin-bottom: 2px;
}

.panel-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
}

.panel-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--ink3);
  font-size: 16px;
  padding: 0 4px;
  transition: color 0.2s;
}

.panel-close:hover {
  color: var(--ink);
}

.panel-body {
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.status-badge {
  align-self: flex-start;
}

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: 11px; color: var(--ink3); font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; margin-bottom: 4px; }
.detail-val { font-size: 13px; font-weight: 600; color: var(--ink); }
.detail-val.text-danger { color: #dc2626; }
.detail-val.text-warn { color: #ca8a04; }

.action-buttons {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 4px;
}

.btn-icon {
  font-size: 14px;
  font-weight: 700;
  line-height: 1;
}

/* Assign Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.assign-modal {
  background: var(--surface);
  border-radius: 14px;
  padding: 20px;
  width: 380px;
  box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 4px;
}

.modal-subtitle {
  font-size: 12px;
  color: var(--ink3);
  margin-bottom: 16px;
}

.modal-subtitle strong {
  color: var(--ink2);
}

.job-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 16px;
}

.job-option {
  padding: 10px 12px;
  border-radius: 8px;
  border: 1px solid var(--border);
  background: var(--panel);
  cursor: pointer;
  transition: all 0.2s;
}

.job-option:hover {
  border-color: var(--accent);
}

.job-option.selected {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.job-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 4px;
}

.job-id {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}

.job-route {
  font-size: 12px;
  color: var(--ink2);
  margin-top: 4px;
}

.job-window {
  font-size: 11px;
  color: var(--ink3);
  margin-top: 2px;
}

.modal-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
}

/* Vehicle Modal */
.vehicle-modal {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 560px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}

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

.modal-close:hover {
  background: var(--panel);
  color: var(--ink);
}

.modal-body {
  padding: 24px;
  max-height: 70vh;
  overflow-y: auto;
}

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

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
  margin-bottom: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink2);
  margin-bottom: 8px;
}

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

.form-input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.form-input::placeholder {
  color: var(--ink4);
}

select.form-input {
  cursor: pointer;
}

textarea.form-input {
  resize: vertical;
  min-height: 80px;
}

/* Mobile Responsive */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    justify-content: flex-end;
  }

  /* Hide button text on mobile, show only icon */
  .btn-text {
    display: none;
  }

  /* Make tables scroll on mobile */
  .table-card {
    overflow-x: auto;
  }

  .table-header,
  .table-row {
    min-width: 700px;
  }

  /* Adjust stats grid for mobile */
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  /* Hide detail panel on mobile, stack content */
  .content-grid.with-panel {
    grid-template-columns: 1fr;
  }

  .detail-panel {
    position: fixed;
    inset: 0;
    z-index: 50;
    border-radius: 0;
    height: 100vh;
  }
}

@media (max-width: 480px) {
  .page-title {
    font-size: 18px;
  }

  .page-sub {
    font-size: 12px;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .vehicle-modal,
  .assign-modal {
    max-width: 100%;
    margin: 0;
    border-radius: 12px 12px 0 0;
    max-height: 90vh;
  }

  .modal-body {
    max-height: 60vh;
  }

  .form-row {
    grid-template-columns: 1fr;
  }
}
</style>
