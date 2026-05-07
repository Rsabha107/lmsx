<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Contact Directory</h1>
        <p class="page-sub">{{ contacts.length }} people · {{ onlineCount }} on shift now</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['contacts']" />
        <Button variant="primary" size="sm" @click="openModal">
          <template #icon><span class="btn-icon">+</span></template>
          <span class="btn-text">Add contact</span>
        </Button>
      </div>
    </div>

    <!-- Grid View -->
    <div v-if="activeViewMode === 'grid'">
      <div class="table-controls-wrapper">
        <div class="left-controls">
          <div class="search-box">
            <svg-icon name="search" :size="14" />
            <input 
              v-model="search" 
              type="text" 
              class="search-input" 
              placeholder="Search by name or role…" 
            />
          </div>
          <select v-model="organizationFilter" class="filter-select">
            <option value="all">All Organizations</option>
            <option v-for="org in organizations" :key="org" :value="org">{{ org }}</option>
          </select>
          <select v-model="shiftStatusFilter" class="filter-select">
            <option value="all">All Status</option>
            <option value="on-shift">On Shift</option>
            <option value="off-duty">Off Duty</option>
          </select>
        </div>
        <div class="view-toggle" v-if="!isMobile">
          <button @click="viewMode = 'grid'" :class="['toggle-btn', viewMode === 'grid' ? 'toggle-btn--active' : '']" title="Grid view">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="1" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="9.5" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="1" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="9.5" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </button>
          <button @click="viewMode = 'table'" :class="['toggle-btn', viewMode === 'table' ? 'toggle-btn--active' : '']" title="Table view">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="1" y="2" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="1" y="6.75" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="1" y="11.5" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </button>
        </div>
      </div>
      
      <div class="contacts-grid">
      <div v-for="c in filtered" :key="c.phone" class="contact-card">
        <div class="cc-top">
          <div class="cc-avatar">{{ initials(c.name) }}</div>
          <div :class="['online-dot', c.on_shift ? 'online-dot--on' : '']" :title="c.on_shift ? 'On Shift' : 'Off Shift'" />
        </div>
        <div class="cc-name">{{ c.name }}</div>
        <div class="cc-role">{{ c.role }}</div>
        <div class="cc-org">{{ c.org }}</div>
        <div class="cc-actions">
          <a :href="`tel:${c.phone}`" class="cc-btn">
            <svg-icon name="phone" :size="15" /> Call
          </a>
          <span class="cc-phone mono">{{ c.phone }}</span>
        </div>
        <div class="cc-footer">
          <button class="cc-action-btn" @click="openEditModal(c)">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
              <path d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Edit
          </button>
          <button class="cc-action-btn" @click="openDeleteModal(c)">
            <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
              <path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Delete
          </button>
        </div>
      </div>
    </div>
    </div>

    <!-- Table View -->
    <div v-else>
      <div class="table-controls-wrapper">
        <div class="left-controls">
          <div class="search-box">
            <svg-icon name="search" :size="14" />
            <input 
              v-model="search" 
              type="text" 
              class="search-input" 
              placeholder="Search by name or role…" 
            />
          </div>
          <select v-model="organizationFilter" class="filter-select">
            <option value="all">All Organizations</option>
            <option v-for="org in organizations" :key="org" :value="org">{{ org }}</option>
          </select>
          <select v-model="shiftStatusFilter" class="filter-select">
            <option value="all">All Status</option>
            <option value="on-shift">On Shift</option>
            <option value="off-duty">Off Duty</option>
          </select>
        </div>
        <div class="right-controls">
          <div class="view-toggle" v-if="!isMobile">
            <button @click="viewMode = 'grid'" :class="['toggle-btn', viewMode === 'grid' ? 'toggle-btn--active' : '']" title="Grid view">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <rect x="1" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="9.5" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="1" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                <rect x="9.5" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              </svg>
            </button>
            <button @click="viewMode = 'table'" :class="['toggle-btn', viewMode === 'table' ? 'toggle-btn--active' : '']" title="Table view">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <rect x="1" y="2" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                <rect x="1" y="6.75" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                <rect x="1" y="11.5" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              </svg>
            </button>
          </div>
          <ColumnToggle 
            v-if="!isMobile"
            :columns="availableColumns" 
            :visible-columns="visibleColumns" 
            @toggle="toggleColumn"
          />
        </div>
      </div>
      
      <div class="table-card">
        <div class="table-header">
          <div>Contact</div>
          <div v-if="visibleColumns.role">Role</div>
          <div v-if="visibleColumns.organization">Organization</div>
          <div v-if="visibleColumns.phone">Phone</div>
          <div style="text-align: center;">Actions</div>
        </div>
        <div v-for="c in filtered" :key="c.phone" class="table-row">
          <div class="cell-contact">
            <div class="contact-avatar">{{ initials(c.name) }}</div>
            <div>
              <div class="contact-name">{{ c.name }}</div>
              <!-- <StatusPill :tone="c.on_shift ? 'live' : 'neutral'">
                {{ c.on_shift ? 'On Shift' : 'Off Shift' }}
              </StatusPill> -->
              <div :style="{ fontSize: '11px', fontWeight: '600', color: c.disabled ? 'var(--ink4)' : c.on_shift ? tokens.ok : 'var(--ink4)' }">
                ● {{ c.disabled ? 'Disabled' : c.on_shift ? 'On shift' : 'Off duty' }}
              </div>
            </div>
          </div>
          <div v-if="visibleColumns.role" class="cell-role">{{ c.role }}</div>
          <div v-if="visibleColumns.organization" class="cell-org">{{ c.org }}</div>
          <div v-if="visibleColumns.phone" class="cell-phone">
            <a :href="`tel:${c.phone}`" class="phone-link">{{ c.phone }}</a>
          </div>
          <div class="cell-actions">
            <TableActions 
              @edit="openEditModal(c)" 
              @delete="openDeleteModal(c)" 
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Add Contact Modal -->
    <div v-if="showAddModal" class="modal-backdrop" @click.self="closeModal">
      <div class="contact-modal">
        <div class="modal-header">
          <h3 class="modal-title">Add New Contact</h3>
          <button class="modal-close" @click="closeModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Full Name <span class="required">*</span></label>
            <input 
              v-model="newContact.name" 
              type="text" 
              :class="['form-input', { 'form-input--error': errors.name }]" 
              placeholder="Enter full name" 
            />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
          <div class="form-group">
            <label class="form-label">Role <span class="required">*</span></label>
            <input 
              v-model="newContact.role" 
              type="text" 
              :class="['form-input', { 'form-input--error': errors.role }]" 
              placeholder="e.g., Media Manager" 
            />
            <span v-if="errors.role" class="form-error">{{ errors.role }}</span>
          </div>
          <div class="form-group">
            <label class="form-label">Organization</label>
            <input v-model="newContact.org" type="text" class="form-input" placeholder="e.g., FIFA Media" />
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input v-model="newContact.phone" type="tel" class="form-input" placeholder="+33 6 12 34 56 78" />
          </div>
          <div class="form-group">
            <div class="toggle-field">
              <div class="toggle-switch" @click="newContact.on_shift = !newContact.on_shift" :class="{ 'toggle-switch--on': newContact.on_shift }">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">On-shift</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="addContact" :disabled="isSubmitting">
            {{ isSubmitting ? 'Adding...' : 'Add Contact' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Edit Contact Modal -->
    <div v-if="showEditModal" class="modal-backdrop" @click.self="closeEditModal">
      <div class="contact-modal">
        <div class="modal-header">
          <h3 class="modal-title">Edit Contact</h3>
          <button class="modal-close" @click="closeEditModal">✕</button>
        </div>
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Full Name <span class="required">*</span></label>
            <input 
              v-model="editContact.name" 
              type="text" 
              :class="['form-input', { 'form-input--error': errors.name }]" 
              placeholder="Enter full name" 
            />
            <span v-if="errors.name" class="form-error">{{ errors.name }}</span>
          </div>
          <div class="form-group">
            <label class="form-label">Role <span class="required">*</span></label>
            <input 
              v-model="editContact.role" 
              type="text" 
              :class="['form-input', { 'form-input--error': errors.role }]" 
              placeholder="e.g., Media Manager" 
            />
            <span v-if="errors.role" class="form-error">{{ errors.role }}</span>
          </div>
          <div class="form-group">
            <label class="form-label">Organization</label>
            <input v-model="editContact.org" type="text" class="form-input" placeholder="e.g., FIFA Media" />
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input v-model="editContact.phone" type="tel" class="form-input" placeholder="+33 6 12 34 56 78" />
          </div>
          <div class="form-group">
            <div class="toggle-field">
              <div class="toggle-switch" @click="editContact.on_shift = !editContact.on_shift" :class="{ 'toggle-switch--on': editContact.on_shift }">
                <div class="toggle-slider"></div>
              </div>
              <span class="toggle-label">On-shift</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeEditModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="updateContact" :disabled="isSubmitting">
            {{ isSubmitting ? 'Updating...' : 'Update Contact' }}
          </Button>
        </div>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-backdrop" @click.self="closeDeleteModal">
      <div class="delete-modal">
        <div class="modal-header">
          <h3 class="modal-title">Delete Contact</h3>
          <button class="modal-close" @click="closeDeleteModal">✕</button>
        </div>
        <div class="modal-body">
          <p style="margin: 0 0 12px; color: var(--ink2); font-size: 14px;">
            Are you sure you want to delete <strong>{{ contactToDelete?.name }}</strong>?
          </p>
          <p style="margin: 0; color: var(--ink3); font-size: 13px;">
            This action cannot be undone.
          </p>
        </div>
        <div class="modal-footer">
          <Button variant="ghost" size="sm" @click="closeDeleteModal" :disabled="isSubmitting">Cancel</Button>
          <Button variant="primary" size="sm" @click="deleteContact" :disabled="isSubmitting" style="background: #EF4444;">
            {{ isSubmitting ? 'Deleting...' : 'Delete' }}
          </Button>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AppLayout from '../Components/AppLayout.vue';
import { LMS_TOKENS } from '@/composables/useTokens'
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';
import StatusPill from '../Components/StatusPill.vue';
import TableActions from '../Components/TableActions.vue';
import ColumnToggle from '../Components/ColumnToggle.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  contacts: { type: Array, default: () => [] },
});

const tokens = LMS_TOKENS
const search = ref('');
const organizationFilter = ref('all');
const shiftStatusFilter = ref('all');
const viewMode = ref('table'); // 'grid' or 'table'
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);
const isMobile = computed(() => windowWidth.value < 768);

// Auto-switch to grid view on mobile
const activeViewMode = computed(() => {
  return isMobile.value ? 'grid' : viewMode.value;
});

function onResize() {
  windowWidth.value = window.innerWidth;
}

onMounted(() => {
  window.addEventListener('resize', onResize);
});

onUnmounted(() => {
  window.removeEventListener('resize', onResize);
});

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const isSubmitting = ref(false);
const errors = ref({});
const newContact = ref({
  name: '',
  role: '',
  org: '',
  phone: '',
  on_shift: false
});
const editContact = ref({
  id: null,
  name: '',
  role: '',
  org: '',
  phone: '',
  on_shift: false
});
const contactToDelete = ref(null);

const visibleColumns = ref({
  role: true,
  organization: true,
  phone: true,
});

const availableColumns = [
  { key: 'role', label: 'Role', required: false },
  { key: 'organization', label: 'Organization', required: false },
  { key: 'phone', label: 'Phone', required: false },
];

const filtered = computed(() => {
  let results = props.contacts;
  
  // Apply search filter
  const q = search.value.toLowerCase();
  if (q) {
    results = results.filter(c =>
      c.name.toLowerCase().includes(q) ||
      c.role.toLowerCase().includes(q) ||
      c.org.toLowerCase().includes(q)
    );
  }
  
  // Apply organization filter
  if (organizationFilter.value !== 'all') {
    results = results.filter(c => c.org === organizationFilter.value);
  }
  
  // Apply shift status filter
  if (shiftStatusFilter.value === 'on-shift') {
    results = results.filter(c => c.on_shift);
  } else if (shiftStatusFilter.value === 'off-duty') {
    results = results.filter(c => !c.on_shift);
  }
  
  return results;
});

// Get unique organizations for filter dropdown
const organizations = computed(() => {
  const orgs = [...new Set(props.contacts.map(c => c.org).filter(Boolean))];
  return orgs.sort();
});

const onlineCount = computed(() => props.contacts.filter(c => c.on_shift).length);

function initials(name) {
  return name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
}

function toggleColumn(key) {
  visibleColumns.value[key] = !visibleColumns.value[key];
}

function openModal() {
  errors.value = {};
  showAddModal.value = true;
}

function closeModal() {
  errors.value = {};
  showAddModal.value = false;
}

function addContact() {
  // Clear previous errors
  errors.value = {};

  // Validate required fields
  if (!newContact.value.name || !newContact.value.name.trim()) {
    errors.value.name = 'Full name is required';
  }
  if (!newContact.value.role || !newContact.value.role.trim()) {
    errors.value.role = 'Role is required';
  }

  // If there are errors, stop submission
  if (Object.keys(errors.value).length > 0) {
    return;
  }

  isSubmitting.value = true;

  router.post('/contacts', newContact.value, {
    preserveScroll: true,
    onSuccess: () => {
      showAddModal.value = false;
      errors.value = {};
      isSubmitting.value = false;
      // Reset form
      newContact.value = {
        name: '',
        role: '',
        org: '',
        phone: '',
        on_shift: false
      };
      // Reload the contacts list
      router.reload({ only: ['contacts'] });
    },
    onError: (backendErrors) => {
      console.error('Failed to add contact:', backendErrors);
      // Map backend errors to our errors object
      errors.value = backendErrors;
      isSubmitting.value = false;
    }
  });
}

function openEditModal(contact) {
  errors.value = {};
  editContact.value = {
    id: contact.id,
    name: contact.name,
    role: contact.role,
    org: contact.org,
    phone: contact.phone,
    on_shift: contact.on_shift
  };
  showEditModal.value = true;
}

function closeEditModal() {
  errors.value = {};
  showEditModal.value = false;
}

function updateContact() {
  // Clear previous errors
  errors.value = {};

  // Validate required fields
  if (!editContact.value.name || !editContact.value.name.trim()) {
    errors.value.name = 'Full name is required';
  }
  if (!editContact.value.role || !editContact.value.role.trim()) {
    errors.value.role = 'Role is required';
  }

  // If there are errors, stop submission
  if (Object.keys(errors.value).length > 0) {
    return;
  }

  isSubmitting.value = true;

  router.put(`/contacts/${editContact.value.id}`, editContact.value, {
    preserveScroll: true,
    onSuccess: () => {
      showEditModal.value = false;
      errors.value = {};
      isSubmitting.value = false;
      // Reload the contacts list
      router.reload({ only: ['contacts'] });
    },
    onError: (backendErrors) => {
      console.error('Failed to update contact:', backendErrors);
      errors.value = backendErrors;
      isSubmitting.value = false;
    }
  });
}

function openDeleteModal(contact) {
  contactToDelete.value = contact;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  contactToDelete.value = null;
  showDeleteModal.value = false;
}

function deleteContact() {
  if (!contactToDelete.value) return;

  isSubmitting.value = true;

  router.delete(`/contacts/${contactToDelete.value.id}`, {
    preserveScroll: true,
    onSuccess: () => {
      showDeleteModal.value = false;
      contactToDelete.value = null;
      isSubmitting.value = false;
      // Reload the contacts list
      router.reload({ only: ['contacts'] });
    },
    onError: (error) => {
      console.error('Failed to delete contact:', error);
      isSubmitting.value = false;
    }
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

.header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
}

.view-toggle {
  display: flex;
  gap: 2px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 3px;
}

.toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: var(--ink3);
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.13s;
}

.toggle-btn:hover {
  color: var(--ink);
  background: var(--surface);
}

.toggle-btn--active {
  background: var(--surface);
  color: var(--accent);
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.search-input {
  padding: 8px 12px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--ink); font-size: 13px; width: 240px; font-family: inherit;
}
.search-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }

@media (max-width: 768px) {
  .search-input {
    width: 100%;
  }
}

/* Grid View */
.contacts-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px;
}

.contact-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 18px;
}
.cc-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 12px; }
.cc-avatar {
  width: 44px; height: 44px; border-radius: 50%;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 14px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
}
.online-dot {
  width: 10px; height: 10px; border-radius: 50%;
  background: var(--border); border: 2px solid var(--surface);
  margin-top: 2px;
}
.online-dot--on { background: var(--ok); }
.cc-name { font-size: 14px; font-weight: 700; color: var(--ink); margin-bottom: 2px; }
.cc-role { font-size: 12px; color: var(--ink3); margin-bottom: 2px; }
.cc-org  { font-size: 11.5px; color: var(--ink4); margin-bottom: 12px; }
.cc-actions { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.cc-btn {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 5px 12px; border-radius: 6px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 12.5px; font-weight: 600; text-decoration: none;
  transition: background 0.13s;
}
.cc-btn:hover { background: var(--accent); color: #fff; }
.cc-phone { font-size: 11.5px; color: var(--ink4); }

/* Table View */
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

.filter-select:hover {
  border-color: var(--accent);
}

.filter-select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

.right-controls {
  display: flex;
  align-items: center;
  gap: 8px;
}

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

.search-box:focus-within {
  border-color: var(--accent);
}

.search-box svg {
  color: var(--ink3);
  flex-shrink: 0;
}

.search-box .search-input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: var(--ink);
  width: 240px;
  padding: 0;
}

@media (max-width: 768px) {
  .search-box .search-input {
    width: 100%;
  }
}

.search-box .search-input::placeholder {
  color: var(--ink4);
}

.table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.table-header {
  display: grid;
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 120px;
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
  grid-template-columns: 2fr 1.5fr 1.5fr 1.2fr 120px;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  align-items: center;
  transition: background-color 0.13s;
}

.table-row:hover {
  background: var(--panel);
}

.table-row:last-child {
  border-bottom: none;
}

.cell-contact {
  display: flex;
  align-items: center;
  gap: 12px;
}

.contact-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.contact-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 4px;
}

.cell-role {
  font-size: 12px;
  color: var(--ink2);
}

.cell-org {
  font-size: 12px;
  color: var(--ink3);
}

.cell-phone {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
}

.phone-link {
  color: var(--ink2);
  text-decoration: none;
  transition: color 0.13s;
}

.phone-link:hover {
  color: var(--accent);
}

.cell-actions {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.cc-footer {
  display: flex;
  gap: 8px;
  padding-top: 10px;
  margin-top: 10px;
  border-top: 1px solid var(--border);
}

.cc-action-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: transparent;
  color: var(--ink3);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.13s;
  font-family: inherit;
}

.cc-action-btn:hover {
  background: var(--panel);
  color: var(--ink);
  border-color: var(--ink3);
}

.btn-icon {
  font-size: 18px;
  font-weight: 300;
  line-height: 1;
}

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

.contact-modal {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.delete-modal {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}

.modal-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
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

.form-group {
  margin-bottom: 20px;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink2);
  margin-bottom: 8px;
}

.required {
  color: #EF4444;
  margin-left: 2px;
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

.form-input--error {
  border-color: #EF4444 !important;
  background: #FEF2F2;
}

.form-input--error:focus {
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12) !important;
}

.form-error {
  display: block;
  color: #EF4444;
  font-size: 12px;
  margin-top: 6px;
  font-weight: 500;
}

.form-input::placeholder {
  color: var(--ink4);
}

.form-checkbox {
  display: flex;
  align-items: center;
  gap: 10px;
  font-size: 14px;
  color: var(--ink2);
  cursor: pointer;
  user-select: none;
}

.form-checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
  accent-color: var(--accent);
}

.toggle-field {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 12px;
  padding: 12px 0;
}

.toggle-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink2);
}

.toggle-switch {
  position: relative;
  width: 44px;
  height: 24px;
  background: var(--border);
  border-radius: 24px;
  cursor: pointer;
  transition: background 0.3s ease;
}

.toggle-switch--on {
  background: var(--accent);
}

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

.toggle-switch--on .toggle-slider {
  transform: translateX(20px);
}

.mono { font-family: var(--font-mono, monospace); }

/* Mobile Responsive */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    width: 100%;
  }

  .table-controls-wrapper {
    flex-direction: column;
    gap: 12px;
    align-items: stretch;
  }

  .search-box {
    width: 100%;
  }

  .left-controls {
    width: 100%;
    flex-direction: column;
  }

  .filter-select {
    width: 100%;
  }

  .right-controls {
    width: 100%;
    justify-content: space-between;
  }

  /* Hide button text on mobile, show only icon */
  .btn-text {
    display: none;
  }

  /* Adjust grid for mobile */
  .contacts-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .page-title {
    font-size: 18px;
  }

  .page-sub {
    font-size: 12px;
  }

  .contact-modal {
    max-width: 100%;
    margin: 0;
    border-radius: 12px 12px 0 0;
    max-height: 90vh;
  }

  .modal-body {
    max-height: 60vh;
  }
}
</style>
