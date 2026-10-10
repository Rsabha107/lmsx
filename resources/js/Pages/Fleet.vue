<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Fleet Management</h1>
        <p class="page-sub">Vehicles, drivers & transport partners</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['vehicles', 'providers', 'drivers', 'supervisors']" />
        <Button v-if="activeTab !== 'history' && (activeTab !== 'supervisors' || canAddSupervisors)" variant="primary" size="sm" @click="openAddModal">
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
      <mini-stat label="Drivers on job" :value="driversIn('on_job')" tone="live" />
      <mini-stat label="Drivers on shift" :value="driversIn('on_shift')" tone="ok" />
      <mini-stat label="Drivers idle" :value="driversIn('idle')" />
    </div>

    <!-- Tabs -->
    <div class="tabs">
      <button v-for="tab in tabs" :key="tab.value"
        :class="['tab', activeTab === tab.value ? 'tab--active' : '']"
        @click="selectTab(tab.value)">
        {{ tab.label }}
        <span class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <div v-if="bulkType && selectedIds.length" class="bulk-bar">
      <div class="bulk-group">
        <span class="bulk-count">{{ selectedLabel }} selected</span>
        <button v-if="!allSelected" class="bulk-link" @click="toggleAll(true)">Select all {{ currentRows.length }}</button>
        <button class="bulk-link" @click="clearSelection">Clear</button>
      </div>

      <div v-if="isAdmin && bulkType !== 'provider'" class="bulk-group bulk-group--assign">
        <span class="bulk-group-label">Move to provider</span>
        <select v-model="bulkProviderId" class="form-input bulk-select">
          <option :value="null">Choose provider…</option>
          <option v-for="p in props.providers" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
        <Button variant="secondary" size="sm" :disabled="!bulkProviderId || isSubmitting" @click="bulkAssignProvider">Apply</Button>
      </div>

      <div class="bulk-spacer"></div>
      <div class="bulk-group bulk-group--danger">
        <Button variant="danger" size="sm" :disabled="isSubmitting" @click="showBulkDelete = true">
          Delete {{ selectedLabel }}
        </Button>
      </div>
    </div>

    <div class="content-grid" :class="{ 'with-panel': selected }">
      <!-- Vehicles -->
      <template v-if="activeTab === 'vehicles'">
        <div class="table-card">
          <div class="table-header">
            <div @click.stop><input type="checkbox" :checked="allSelected" aria-label="Select all vehicles" @change="toggleAll($event.target.checked)" /></div>
            <div>ID</div>
            <div>Type</div>
            <div>Capacity</div>
            <div>Plate</div>
            <div>Provider</div>
            <div>Category</div>
            <div>Fuel</div>
            <div>Status</div>
            <div style="text-align: center;" :title="eventPool.name ? `Offered for ${eventPool.name}` : 'Pick an event first'">In event</div>
            <div style="text-align: center;">Actions</div>
          </div>
          <div v-for="v in vehicles" :key="v.code" class="table-row" :class="{ 'selected': selected?.code === v.code }" @click="selectVehicle(v)">
            <div @click.stop><input type="checkbox" :checked="isChecked(v.id)" :aria-label="`Select ${v.code}`" @change="toggleRow(v.id)" /></div>
            <div class="cell-id">{{ v.code }}</div>
            <div class="cell-type">{{ v.vehicle_type }}</div>
            <div class="cell-capacity">{{ v.capacity }} seats</div>
            <div class="cell-plate">{{ v.plate_number || '—' }}</div>
            <div class="cell-category">{{ v.provider?.name || '—' }}</div>
            <div class="cell-category">{{ v.category || '—' }}</div>
            <div class="fuel-cell" :class="{ 'low': parseInt(v.fuel_level) < 50, 'medium': parseInt(v.fuel_level) >= 50 && parseInt(v.fuel_level) < 70 }">{{ v.fuel_level }}</div>
            <div><status-pill :tone="v.status === 'available' ? 'ok' : v.status === 'on_job' ? 'live' : 'neutral'">{{ v.status }}</status-pill></div>
            <div style="text-align: center;" @click.stop>
              <input type="checkbox" :disabled="!eventPool.name" :checked="eventPool.vehicles.includes(v.id)"
                :aria-label="`${v.code} in ${eventPool.name || 'event'}`" @change="setPool('vehicle', v.id, $event.target.checked)" />
            </div>
            <div class="cell-actions" @click.stop>
              <TableActions @edit="openEditVehicle(v)" @delete="confirmDelete('vehicle', v)" />
            </div>
          </div>
        </div>
      </template>

      <!-- Drivers -->
      <template v-else-if="activeTab === 'drivers'">
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th class="cell-check"><input type="checkbox" :checked="allSelected" aria-label="Select all drivers" @change="toggleAll($event.target.checked)" /></th>
            <th>Name</th><th>Phone</th><th>License</th><th>Provider</th><th>Today</th><th style="text-align: center;" :title="eventPool.name ? `Offered for ${eventPool.name}` : 'Pick an event first'">In event</th><th style="text-align: center;">Actions</th>
          </tr></thead>
          <tbody>
            <tr v-for="d in props.drivers" :key="d.id">
              <td class="cell-check"><input type="checkbox" :checked="isChecked(d.id)" :aria-label="`Select ${d.name}`" @change="toggleRow(d.id)" /></td>
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
              <td>{{ d.provider?.name || '—' }}</td>
              <td>
                <status-pill :tone="driverTone(d.today)">{{ d.today?.label ?? d.status }}</status-pill>
                <div v-if="d.today?.detail" class="status-detail">{{ d.today.detail }}</div>
              </td>
              <td style="text-align: center;">
                <input type="checkbox" :disabled="!eventPool.name" :checked="eventPool.drivers.includes(d.id)"
                  :aria-label="`${d.name} in ${eventPool.name || 'event'}`" @change="setPool('driver', d.id, $event.target.checked)" />
              </td>
              <td class="cell-actions">
                <TableActions @edit="openEditDriver(d)" @delete="confirmDelete('driver', d)" />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

      <!-- Supervisors -->
      <template v-else-if="activeTab === 'supervisors'">
      <div class="table-card">
        <table class="data-table">
          <thead><tr><th>Name</th><th>Role</th><th>Phone</th><th>Email</th><th>Provider</th><th style="text-align: center;">Actions</th></tr></thead>
          <tbody>
            <tr v-for="s in props.supervisors" :key="s.id">
              <td>
                <div class="driver-row">
                  <div class="avatar">{{ initials(s.name || 'N/A') }}</div>
                  <div class="driver-name">{{ s.name }}</div>
                </div>
              </td>
              <td>{{ s.job_title || '—' }}</td>
              <td class="mono">{{ s.phone || '—' }}</td>
              <td class="mono">{{ s.email }}</td>
              <td>{{ s.provider || '—' }}</td>
              <td class="cell-actions">
                <TableActions hide-delete @edit="openEditSupervisor(s)" />
              </td>
            </tr>
            <tr v-if="!props.supervisors.length"><td colspan="6" class="note-cell">No field supervisors yet.</td></tr>
          </tbody>
        </table>
      </div>
    </template>

      <!-- Providers -->
      <template v-else-if="activeTab === 'providers'">
      <div class="table-card">
        <table class="data-table">
          <thead><tr>
            <th class="cell-check"><input type="checkbox" :checked="allSelected" aria-label="Select all providers" @change="toggleAll($event.target.checked)" /></th>
            <th>Code</th><th>Provider</th><th>Vehicles</th><th>Drivers</th><th>Contact</th><th>Phone</th><th>Rating</th><th>Status</th><th style="text-align: center;">Actions</th>
          </tr></thead>
          <tbody>
            <tr v-for="p in props.providers" :key="p.id">
              <td class="cell-check"><input type="checkbox" :checked="isChecked(p.id)" :aria-label="`Select ${p.name}`" @change="toggleRow(p.id)" /></td>
              <td class="mono">{{ p.code }}</td>
              <td>
                <div class="provider-name">{{ p.name }}</div>
                <div class="provider-type" v-if="p.notes">{{ p.notes }}</div>
              </td>
              <td>{{ p.vehicles_count }}</td>
              <td>{{ p.drivers_count }}</td>
              <td>{{ p.contact_person || '—' }}</td>
              <td class="mono">{{ p.phone || '—' }}</td>
              <td>⭐ {{ p.rating }}</td>
              <td><status-pill :tone="p.status === 'active' ? 'ok' : 'neutral'">{{ p.status }}</status-pill></td>
              <td class="cell-actions">
                <TableActions @edit="openEditProvider(p)" @delete="confirmDelete('provider', p)" />
              </td>
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
          <div class="panel-actions">
            <Button v-if="canAssignCrew" variant="primary" size="md" @click="router.visit('/crew-assignment')">Assign to job</Button>
            <Button variant="secondary" size="md">View history</Button>
            <Button variant="ghost" size="md" @click="selected = null">Close</Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Vehicle Modal (add + edit) -->
    <div v-if="showVehicleModal" v-dialog="closeVehicleModal" class="modal-backdrop" @click.self="closeVehicleModal">
      <div class="vehicle-modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ vehicleForm.id ? 'Edit Vehicle' : 'Add New Vehicle' }}</h3>
          <button class="modal-close" @click="closeVehicleModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Vehicle Code <span class="required">*</span></label>
              <input v-model="vehicleForm.code" type="text" class="form-input" :class="{ 'form-input--error': errors.code }" placeholder="e.g., Coach 01" />
              <span v-if="errors.code" class="form-error">{{ errors.code }}</span>
            </div>
            <div class="form-group">
              <label class="form-label">Vehicle Type <span class="required">*</span></label>
              <input v-model="vehicleForm.vehicle_type" type="text" class="form-input" :class="{ 'form-input--error': errors.vehicle_type }" placeholder="e.g., Coach, Minivan" />
              <span v-if="errors.vehicle_type" class="form-error">{{ errors.vehicle_type }}</span>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Capacity</label>
              <input v-model="vehicleForm.capacity" type="number" class="form-input" placeholder="Number of seats" />
            </div>
            <div class="form-group">
              <label class="form-label">Plate Number</label>
              <input v-model="vehicleForm.plate_number" type="text" class="form-input" placeholder="e.g., ABC-1234" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Category</label>
              <select v-model="vehicleForm.category" class="form-input">
                <option value="">Select category</option>
                <option value="Team">Team</option>
                <option value="Official">Official</option>
                <option value="VIP">VIP</option>
                <option value="Media">Media</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Fuel Level</label>
              <input v-model="vehicleForm.fuel_level" type="text" class="form-input" placeholder="e.g., 75%" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Status</label>
              <select v-model="vehicleForm.status" class="form-input">
                <option value="available">Available</option>
                <option value="on_job">On Job</option>
                <option value="maintenance">Maintenance</option>
                <option value="standby">Standby</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Provider <span class="required">*</span></label>
              <select v-model="vehicleForm.provider_id" class="form-input" :class="{ 'form-input--error': errors.provider_id }">
                <option :value="null">Select provider</option>
                <option v-for="p in props.providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
              <span v-if="errors.provider_id" class="form-error">{{ errors.provider_id }}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea v-model="vehicleForm.notes" class="form-input" rows="3" placeholder="Additional notes (optional)"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeVehicleModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="saveVehicle" :disabled="isSubmitting">
            {{ isSubmitting ? 'Saving…' : vehicleForm.id ? 'Update Vehicle' : 'Add Vehicle' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Driver Modal (add + edit) -->
    <div v-if="showDriverModal" v-dialog="closeDriverModal" class="modal-backdrop" @click.self="closeDriverModal">
      <div class="vehicle-modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ driverForm.id ? 'Edit Driver' : 'Add New Driver' }}</h3>
          <button class="modal-close" @click="closeDriverModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Name <span class="required">*</span></label>
            <input v-model="driverForm.name" type="text" class="form-input" :class="{ 'form-input--error': errors.name }" placeholder="Full name" />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input v-model="driverForm.phone" type="tel" class="form-input" placeholder="+974 …" />
            </div>
            <div class="form-group">
              <label class="form-label">License Number</label>
              <input v-model="driverForm.license_number" type="text" class="form-input" placeholder="e.g., DL-88213" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Availability</label>
              <select v-model="driverForm.status" class="form-input">
                <option value="available">Available</option>
                <option value="off">Off</option>
                <option value="rest">Rest day</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Provider</label>
              <select v-model="driverForm.provider_id" class="form-input">
                <option :value="null">Select provider</option>
                <option v-for="p in props.providers" :key="p.id" :value="p.id">{{ p.name }}</option>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeDriverModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="saveDriver" :disabled="isSubmitting">
            {{ isSubmitting ? 'Saving…' : driverForm.id ? 'Update Driver' : 'Add Driver' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Supervisor Modal (add + edit) -->
    <div v-if="showSupervisorModal" v-dialog="closeSupervisorModal" class="modal-backdrop" @click.self="closeSupervisorModal">
      <div class="vehicle-modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ supervisorForm.id ? 'Edit Field Supervisor' : 'Add Field Supervisor' }}</h3>
          <button class="modal-close" @click="closeSupervisorModal">✕</button>
        </div>
        <div class="modal-body">
          <div v-if="!supervisorForm.id && props.contacts.length" class="form-group">
            <label class="form-label">Start from a contact</label>
            <select class="form-input" :value="supervisorForm.contact_id ?? ''" @change="pickContact($event.target.value)">
              <option value="">— none —</option>
              <option v-for="c in props.contacts" :key="c.id" :value="c.id">{{ c.name }}{{ c.role ? ` · ${c.role}` : '' }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Name <span class="required">*</span></label>
            <input v-model="supervisorForm.name" type="text" class="form-input" :class="{ 'form-input--error': errors.name }" placeholder="Full name" />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Role</label>
              <RoleSelect v-model="supervisorForm.job_title" :roles="props.roles" class="form-input" :class="{ 'form-input--error': errors.job_title }" />
              <span v-if="errors.job_title" class="form-error">{{ errors.job_title }}</span>
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input v-model="supervisorForm.phone" type="tel" class="form-input" :class="{ 'form-input--error': errors.phone }" placeholder="+974 …" />
              <span v-if="errors.phone" class="form-error">{{ errors.phone }}</span>
            </div>
          </div>
          <template v-if="!supervisorForm.id">
            <div class="form-group">
              <label class="form-label">Email <span class="required">*</span></label>
              <input v-model="supervisorForm.email" type="email" class="form-input" :class="{ 'form-input--error': errors.email }" autocomplete="off" placeholder="name@company.com" />
              <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Password <span class="required">*</span></label>
                <input v-model="supervisorForm.password" type="password" class="form-input" :class="{ 'form-input--error': errors.password }" autocomplete="new-password" placeholder="At least 8 characters" />
                <span v-if="errors.password" class="form-error">{{ errors.password }}</span>
              </div>
              <div class="form-group">
                <label class="form-label">Provider</label>
                <select v-model="supervisorForm.provider_id" class="form-input" :class="{ 'form-input--error': errors.provider_id }">
                  <option :value="null">Select provider</option>
                  <option v-for="p in props.providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <span v-if="errors.provider_id" class="form-error">{{ errors.provider_id }}</span>
              </div>
            </div>
            <p class="form-hint">Signs in to the mobile app with this email and password, and can work the event you have open now.</p>
          </template>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeSupervisorModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="saveSupervisor" :disabled="isSubmitting">
            {{ isSubmitting ? 'Saving…' : supervisorForm.id ? 'Update Supervisor' : 'Add Supervisor' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Provider Modal (add + edit) -->
    <div v-if="showProviderModal" v-dialog="closeProviderModal" class="modal-backdrop" @click.self="closeProviderModal">
      <div class="vehicle-modal">
        <div class="modal-header">
          <h3 class="modal-title">{{ providerForm.id ? 'Edit Provider' : 'Add New Provider' }}</h3>
          <button class="modal-close" @click="closeProviderModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Code <span class="required">*</span></label>
              <input v-model="providerForm.code" type="text" class="form-input" :class="{ 'form-input--error': errors.code }" placeholder="e.g., TRX" />
              <span v-if="errors.code" class="form-error">{{ errors.code }}</span>
            </div>
            <div class="form-group">
              <label class="form-label">Name <span class="required">*</span></label>
              <input v-model="providerForm.name" type="text" class="form-input" :class="{ 'form-input--error': errors.name }" placeholder="Company name" />
              <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Contact Person</label>
              <input v-model="providerForm.contact_person" type="text" class="form-input" placeholder="Primary contact" />
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input v-model="providerForm.phone" type="tel" class="form-input" placeholder="+974 …" />
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email</label>
              <input v-model="providerForm.email" type="email" class="form-input" :class="{ 'form-input--error': errors.email }" placeholder="ops@provider.com" />
              <span v-if="errors.email" class="form-error">{{ errors.email }}</span>
            </div>
            <div class="form-group">
              <label class="form-label">Rating</label>
              <input v-model="providerForm.rating" type="number" step="0.1" min="0" max="9.9" class="form-input" placeholder="e.g., 4.5" />
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select v-model="providerForm.status" class="form-input">
              <option value="active">Active</option>
              <option value="standby">Standby</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Notes</label>
            <textarea v-model="providerForm.notes" class="form-input" rows="3" placeholder="Additional notes (optional)"></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeProviderModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="saveProvider" :disabled="isSubmitting">
            {{ isSubmitting ? 'Saving…' : providerForm.id ? 'Update Provider' : 'Add Provider' }}
          </Button>
        </div>
      </div>
    </div>

    <ConfirmModal
      :show="showBulkDelete"
      tone="danger"
      :title="`Delete ${selectedLabel}`"
      :message="`Permanently delete <strong>${selectedLabel}</strong>? Any still assigned to movements, jobs or crew will be skipped.`"
      :processing="isSubmitting"
      @close="showBulkDelete = false"
      @confirm="performBulkDelete"
    />

    <ConfirmModal
      :show="deleteTarget !== null"
      tone="danger"
      :title="`Delete ${deleteTarget?.type ?? ''}`"
      :message="deleteTarget ? `Are you sure you want to delete <strong>${deleteTarget.label}</strong>?` : ''"
      :processing="isSubmitting"
      @close="deleteTarget = null"
      @confirm="performDelete"
    />

    <ConfirmModal
      :show="blockedMessage !== ''"
      title="Cannot Delete"
      :message="blockedMessage"
      confirm-label="Got it"
      hide-cancel
      @close="blockedMessage = ''"
      @confirm="blockedMessage = ''"
    />


  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import MiniStat from '../Components/MiniStat.vue';
import Button from '../Components/Button.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import TableActions from '../Components/TableActions.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import RoleSelect from '../Components/RoleSelect.vue';

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
  },
  supervisors: {
    type: Array,
    default: () => []
  },
  canAddSupervisors: {
    type: Boolean,
    default: false
  },
  contacts: {
    type: Array,
    default: () => []
  },
  roles: {
    type: Array,
    default: () => []
  },
  eventPool: {
    type: Object,
    default: () => ({ name: null, vehicles: [], drivers: [] })
  }
});

function setPool(type, id, inPool) {
  router.post('/fleet/pool', { type, id, in_pool: inPool }, { preserveScroll: true, preserveState: true, only: ['eventPool'] });
}

const validTabs = ['vehicles', 'drivers', 'supervisors', 'providers', 'history'];

// Kept in the query string so a save (which redirects) or a browser refresh
// comes back to the tab the user was on.
function tabFromUrl() {
  const tab = new URLSearchParams(window.location.search).get('tab');
  return validTabs.includes(tab) ? tab : 'vehicles';
}

const activeTab = ref(tabFromUrl());

function selectTab(tab) {
  activeTab.value = tab;
}

watch(activeTab, (tab) => {
  const url = new URL(window.location.href);
  url.searchParams.set('tab', tab);
  window.history.replaceState(window.history.state, '', url);
});
const selected = ref(null);

const isSubmitting = ref(false);
const errors = ref({});
// Server-side guard messages (e.g. "still assigned to 3 jobs").
const blockedMessage = ref('');
const deleteTarget = ref(null);

const emptyVehicle = () => ({
  id: null,
  code: '',
  vehicle_type: '',
  capacity: null,
  plate_number: '',
  category: '',
  fuel_level: '100%',
  status: 'available',
  provider_id: null,
  notes: '',
});

const emptyDriver = () => ({
  id: null,
  name: '',
  phone: '',
  license_number: '',
  status: 'available',
  provider_id: null,
});

const emptySupervisor = () => ({
  id: null,
  contact_id: null,
  name: '',
  job_title: '',
  phone: '',
  email: '',
  password: '',
  provider_id: null,
});

const emptyProvider = () => ({
  id: null,
  code: '',
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  rating: null,
  status: 'active',
  notes: '',
});

const showVehicleModal = ref(false);
const showDriverModal = ref(false);
const showSupervisorModal = ref(false);
const showProviderModal = ref(false);
const vehicleForm = ref(emptyVehicle());
const driverForm = ref(emptyDriver());
const supervisorForm = ref(emptySupervisor());
const providerForm = ref(emptyProvider());



const history = [
  { id: 'h1', date: '18 Apr 14:18', vehicle: 'Coach 12', job: 'J-1042', driver: 'L. Fontaine', notes: 'Arrived on time, clean handover' },
  { id: 'h2', date: '18 Apr 13:40', vehicle: 'Coach 07', job: 'J-1038', driver: 'S. Reyes',    notes: 'Vehicle swap after pre-dispatch check' },
  { id: 'h3', date: '17 Apr 22:10', vehicle: 'Coach 03', job: 'J-1031', driver: 'A. Bakr',     notes: 'Night run, fuel refill needed' },
  { id: 'h4', date: '17 Apr 19:45', vehicle: 'Van 22',   job: 'J-1028', driver: 'K. Petrov',   notes: 'Media pool pickup, 3 extra bags' },
];

const tabs = computed(() => [
  { value: 'vehicles',  label: 'Vehicles',  count: props.vehicles.length },
  { value: 'drivers',   label: 'Drivers',   count: props.drivers.length },
  ...(props.canAddSupervisors ? [{ value: 'supervisors', label: 'Supervisors', count: props.supervisors.length }] : []),
  { value: 'providers', label: 'Providers', count: props.providers.length },
  { value: 'history',   label: 'History',   count: history.length },
]);

const addButtonText = computed(() => {
  switch (activeTab.value) {
    case 'vehicles': return 'Add vehicle';
    case 'drivers': return 'Add driver';
    case 'supervisors': return 'Add supervisor';
    case 'providers': return 'Add provider';
    default: return 'Add';
  }
});

function initials(name) {
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

const driverTones = { on_job: 'live', on_shift: 'ok', scheduled: 'primary', rest: 'warn', done: 'neutral', idle: 'neutral', off: 'neutral' };

function driverTone(today) {
  // Marked off or resting yet still on the schedule needs someone's attention.
  if (['off', 'rest'].includes(today?.state) && today.jobs_today > 0) return 'danger';
  return driverTones[today?.state] ?? 'neutral';
}

const driversIn = (state) => props.drivers.filter((d) => d.today?.state === state).length;

function selectVehicle(v) {
  selected.value = selected.value?.code === v.code ? null : v;
}

function openAddModal() {
  errors.value = {};
  if (activeTab.value === 'vehicles') {
    vehicleForm.value = emptyVehicle();
    showVehicleModal.value = true;
  } else if (activeTab.value === 'drivers') {
    driverForm.value = emptyDriver();
    showDriverModal.value = true;
  } else if (activeTab.value === 'supervisors') {
    supervisorForm.value = emptySupervisor();
    showSupervisorModal.value = true;
  } else if (activeTab.value === 'providers') {
    providerForm.value = emptyProvider();
    showProviderModal.value = true;
  }
}

/* --------------------------------- Vehicles -------------------------------- */

function openEditVehicle(v) {
  errors.value = {};
  vehicleForm.value = {
    id: v.id,
    code: v.code ?? '',
    vehicle_type: v.vehicle_type ?? '',
    capacity: v.capacity,
    plate_number: v.plate_number ?? '',
    category: v.category ?? '',
    fuel_level: v.fuel_level ?? '',
    status: v.status ?? 'available',
    provider_id: v.provider_id ?? null,
    notes: v.notes ?? '',
  };
  showVehicleModal.value = true;
}

function closeVehicleModal() {
  showVehicleModal.value = false;
  errors.value = {};
}

function saveVehicle() {
  submit(
    vehicleForm.value.id ? 'put' : 'post',
    vehicleForm.value.id ? `/fleet/vehicles/${vehicleForm.value.id}` : '/fleet/vehicles',
    vehicleForm.value,
    () => closeVehicleModal(),
  );
}

/* --------------------------------- Drivers --------------------------------- */

function openEditDriver(d) {
  errors.value = {};
  driverForm.value = {
    id: d.id,
    name: d.name ?? '',
    phone: d.phone ?? '',
    license_number: d.license_number ?? '',
    // On shift is now derived from the schedule; only the planning overrides are stored.
    status: ['off', 'rest'].includes(d.status) ? d.status : 'available',
    provider_id: d.provider_id ?? null,
  };
  showDriverModal.value = true;
}

function closeDriverModal() {
  showDriverModal.value = false;
  errors.value = {};
}

function saveDriver() {
  submit(
    driverForm.value.id ? 'put' : 'post',
    driverForm.value.id ? `/fleet/drivers/${driverForm.value.id}` : '/fleet/drivers',
    driverForm.value,
    () => closeDriverModal(),
  );
}

/* ------------------------------- Supervisors ------------------------------- */

// Starts the form from a contacts-directory entry; the entry is then tied to the new login.
function pickContact(id) {
  const contact = props.contacts.find((c) => c.id === Number(id));
  supervisorForm.value = {
    ...supervisorForm.value,
    contact_id: contact?.id ?? null,
    ...(contact ? { name: contact.name, job_title: contact.role ?? '', phone: contact.phone ?? '' } : {}),
  };
}

function openEditSupervisor(s) {
  errors.value = {};
  supervisorForm.value = { ...emptySupervisor(), id: s.id, name: s.name ?? '', job_title: s.job_title ?? '', phone: s.phone ?? '' };
  showSupervisorModal.value = true;
}

function closeSupervisorModal() {
  showSupervisorModal.value = false;
  errors.value = {};
}

function saveSupervisor() {
  const f = supervisorForm.value;
  submit(f.id ? 'put' : 'post', f.id ? `/fleet/supervisors/${f.id}` : '/fleet/supervisors', f, () => closeSupervisorModal());
}

/* -------------------------------- Providers -------------------------------- */

function openEditProvider(p) {
  errors.value = {};
  providerForm.value = {
    id: p.id,
    code: p.code ?? '',
    name: p.name ?? '',
    contact_person: p.contact_person ?? '',
    phone: p.phone ?? '',
    email: p.email ?? '',
    rating: p.rating,
    status: p.status ?? 'active',
    notes: p.notes ?? '',
  };
  showProviderModal.value = true;
}

function closeProviderModal() {
  showProviderModal.value = false;
  errors.value = {};
}

function saveProvider() {
  submit(
    providerForm.value.id ? 'put' : 'post',
    providerForm.value.id ? `/fleet/providers/${providerForm.value.id}` : '/fleet/providers',
    providerForm.value,
    () => closeProviderModal(),
  );
}

/* --------------------------------- Deleting -------------------------------- */

const deleteEndpoints = {
  vehicle: 'vehicles',
  driver: 'drivers',
  provider: 'providers',
};

function confirmDelete(type, row) {
  deleteTarget.value = {
    type,
    id: row.id,
    label: row.code || row.name || `#${row.id}`,
  };
}

function performDelete() {
  const target = deleteTarget.value;
  if (!target) return;

  isSubmitting.value = true;

  router.delete(`/fleet/${deleteEndpoints[target.type]}/${target.id}`, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (page) => {
      deleteTarget.value = null;
      // A blocked delete still resolves as a successful redirect, so surface
      // the guard message rather than pretending the row was removed.
      blockedMessage.value = page.props?.flash?.error ?? '';
      reloadFleet();
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}

/* ------------------------------ Bulk selection ----------------------------- */

const page = usePage();
const isAdmin = computed(() => page.props.auth?.can?.setups === true);
const canAssignCrew = computed(() => page.props.auth?.can?.['movements.assign-crew'] === true);
const bulkTypes = { vehicles: 'vehicle', drivers: 'driver', providers: 'provider' };
const bulkType = computed(() => bulkTypes[activeTab.value] ?? null);
const selection = ref({ vehicle: [], driver: [], provider: [] });
const showBulkDelete = ref(false);
const bulkProviderId = ref(null);

const selectedIds = computed(() => selection.value[bulkType.value] ?? []);
const currentRows = computed(() => props[activeTab.value] ?? []);
const allSelected = computed(() => currentRows.value.length > 0 && selectedIds.value.length === currentRows.value.length);

const isChecked = (id) => selectedIds.value.includes(id);

// e.g. "3 vehicles" - names the thing so a bulk action can't be misread as acting on another tab.
const selectedLabel = computed(() => {
  const n = selectedIds.value.length;
  return `${n} ${bulkType.value}${n === 1 ? '' : 's'}`;
});

function toggleRow(id) {
  const ids = selection.value[bulkType.value];
  selection.value[bulkType.value] = ids.includes(id) ? ids.filter((i) => i !== id) : [...ids, id];
}

function toggleAll(checked) {
  selection.value[bulkType.value] = checked ? currentRows.value.map((r) => r.id) : [];
}

function clearSelection() {
  selection.value[bulkType.value] = [];
  bulkProviderId.value = null;
}

function performBulkDelete() {
  isSubmitting.value = true;

  router.post('/fleet/bulk-delete', { type: bulkType.value, ids: selectedIds.value }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (page) => {
      showBulkDelete.value = false;
      blockedMessage.value = page.props?.flash?.error ?? '';
      clearSelection();
      reloadFleet();
    },
    onFinish: () => { isSubmitting.value = false; },
  });
}

function bulkAssignProvider() {
  isSubmitting.value = true;

  router.post('/fleet/bulk-provider', { type: bulkType.value, ids: selectedIds.value, provider_id: bulkProviderId.value }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => { clearSelection(); reloadFleet(); },
    onFinish: () => { isSubmitting.value = false; },
  });
}

/* --------------------------------- Shared ---------------------------------- */

function submit(method, url, payload, onDone) {
  errors.value = {};
  isSubmitting.value = true;

  router[method](url, payload, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      onDone();
      reloadFleet();
    },
    onError: (backendErrors) => {
      errors.value = backendErrors;
    },
    onFinish: () => {
      isSubmitting.value = false;
    },
  });
}

function reloadFleet() {
  router.reload({ only: ['vehicles', 'drivers', 'providers', 'supervisors', 'contacts'] });
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
  grid-template-columns: repeat(4, 1fr);
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
  display: grid; grid-template-columns: 28px 80px 1fr 100px 1fr 1fr 1fr 80px 90px 70px 110px;
  gap: 10px; padding: 10px 14px; border-bottom: 1px solid var(--border);
  font-size: 11px; font-weight: 700; color: var(--ink3);
  letter-spacing: 0.6px; text-transform: uppercase;
  background: var(--panel); position: sticky; top: 0;
}

.table-row {
  display: grid; grid-template-columns: 28px 80px 1fr 100px 1fr 1fr 1fr 80px 90px 70px 110px;
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

.cell-actions { display: flex; align-items: center; justify-content: center; gap: 6px; cursor: default; }
.data-table th.cell-check, .data-table td.cell-check { width: 36px; text-align: center; padding-right: 0; }

.bulk-bar {
  display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
  margin-bottom: 12px; padding: 8px 14px;
  background: var(--accent-soft); border: 1px solid var(--border); border-radius: 10px;
}
.bulk-count { font-size: 13px; font-weight: 600; color: var(--ink); }
.bulk-group { display: flex; align-items: center; gap: 10px; }
.bulk-group--assign { padding-left: 14px; border-left: 1px solid var(--border); }
.bulk-group--danger { padding-left: 14px; border-left: 1px solid var(--border); }
.bulk-group-label { font-size: 12px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.4px; }
.bulk-link { background: none; border: none; color: var(--accent); font-size: 12.5px; cursor: pointer; padding: 0; }
.bulk-spacer { flex: 1; }
.bulk-select { width: 200px; }
/* A flex <td> stops being a table cell and drops out of the column grid. */
td.cell-actions { display: table-cell; text-align: center; }
.required { color: #EF4444; margin-left: 2px; }
.form-input--error { border-color: #EF4444 !important; background: #FEF2F2; }
.form-error { display: block; color: #EF4444; font-size: 12px; margin-top: 4px; font-weight: 500; }
.form-hint { margin: 0; font-size: 12px; color: var(--ink3); }

.fleet-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px; }
.fleet-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 16px; cursor: pointer;
  transition: border-color 0.13s, box-shadow 0.13s;
}
.fleet-card:hover { border-color: var(--accent); box-shadow: 0 2px 8px var(--accent-ring); }
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
.status-detail { font-size: 11.5px; color: var(--ink3); margin-top: 3px; }
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

/* Scoped styles reach a child component's root node, so this must not be named
   .action-buttons - that is TableActions' root and it would stack its icons. */
.panel-actions {
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

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
}

.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 4px;
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
  box-shadow: 0 0 0 3px var(--accent-ring);
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

  .vehicle-modal {
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
