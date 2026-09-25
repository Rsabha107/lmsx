<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Movement Time Offset Settings</h1>
        <p class="page-sub">Configure time offsets for different movement types</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['globalOverrides', 'eventOverrides']" />
      </div>
    </div>

    <!-- Info Banner -->
    <div class="info-banner" style="margin-bottom: 20px; padding: 12px 16px; background: #EFF6FF; border-left: 3px solid #3B82F6; border-radius: 4px;">
      <div style="display: flex; align-items: start; gap: 10px;">
        <svg-icon name="info-circle" :size="16" style="color: #3B82F6; margin-top: 2px;" />
        <div style="font-size: 13px; line-height: 1.5; color: #1E40AF;">
          <strong>Priority:</strong> Checkpoint-specific (event, then global) > Event-specific > Global default<br/>
          <strong>Checkpoint offsets:</strong> when set on a movement's first checkpoint, they drive that movement's own window — not just the checkpoint's display time<br/>
          <strong>Negative values</strong> = minutes <em>before</em> reference time (e.g., -180 = 3 hours before flight)
        </div>
      </div>
    </div>

    <!-- Global Defaults Section -->
    <div class="settings-card" style="margin-bottom: 24px;">
      <div class="card-header">
        <h2 class="card-title">
          <svg-icon name="globe" :size="18" style="color: #6B7280;" />
          Global Defaults
        </h2>
        <p class="card-subtitle">Global-scope offset overrides. A checkpoint offset on a movement's first checkpoint drives that movement's window.</p>
      </div>
      <div style="padding: 16px; border-bottom: 1px solid #E5E7EB;">
        <div style="display: flex; gap: 12px; align-items: end;">
          <div style="flex: 1;">
            <label class="form-label">Movement Type</label>
            <Select
              v-model="globalForm.movement_type"
              :options="movementTypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Choose type..."
              class="w-full"
            />
          </div>
          <div style="flex: 1;">
            <label class="form-label">Checkpoint (optional)</label>
            <Select
              v-model="globalForm.checkpoint_id"
              :options="checkpoints"
              optionLabel="name"
              optionValue="id"
              filter
              filterPlaceholder="Search checkpoints..."
              showClear
              placeholder="— None (movement-type default) —"
              class="w-full"
            />
          </div>
          <div style="width: 120px;">
            <label class="form-label">Offset (min)</label>
            <input
              v-model.number="globalForm.value"
              type="number"
              class="form-input"
              placeholder="-180"
              min="-999"
              max="999"
            />
          </div>
          <Button
            variant="primary"
            size="sm"
            :disabled="!globalForm.movement_type || globalForm.value === '' || addingGlobalOverride"
            :processing="addingGlobalOverride"
            @click="saveGlobalOverride"
          >
            Add Offset
          </Button>
        </div>
      </div>
      <div v-if="globalOverrides.length > 0" class="settings-table-container">
        <table class="settings-table">
          <thead>
            <tr>
              <th style="width: 140px;">Movement Type</th>
              <th style="width: 180px;">Checkpoint</th>
              <th style="width: 110px;">Offset (min)</th>
              <th style="width: 160px;">Time Before/After</th>
              <th>Description</th>
              <th style="width: 100px; text-align: center;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="setting in globalOverrides" :key="setting.id">
              <td>
                <Select
                  v-if="editingGlobalId === setting.id"
                  v-model="globalEditForm.movement_type"
                  :options="movementTypeOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full"
                />
                <span v-else class="movement-badge" :class="`badge-${getMovementTypeFromKey(setting.key)}`">
                  {{ formatMovementType(getMovementTypeFromKey(setting.key)) }}
                </span>
              </td>
              <td>
                <Select
                  v-if="editingGlobalId === setting.id"
                  v-model="globalEditForm.checkpoint_id"
                  :options="checkpoints"
                  optionLabel="name"
                  optionValue="id"
                  filter
                  filterPlaceholder="Search checkpoints..."
                  showClear
                  placeholder="— None —"
                  class="w-full"
                />
                <span v-else class="text-muted">{{ setting.checkpoint_name || '—' }}</span>
              </td>
              <td>
                <input
                  v-if="editingGlobalId === setting.id"
                  v-model.number="globalEditForm.value"
                  type="number"
                  class="inline-input"
                  style="width: 80px;"
                  min="-999"
                  max="999"
                />
                <span v-else class="mono">{{ setting.value }}</span>
              </td>
              <td class="text-muted">
                {{ formatTimeOffset(editingGlobalId === setting.id ? globalEditForm.value : setting.value) }}
              </td>
              <td>
                <input
                  v-if="editingGlobalId === setting.id"
                  v-model="globalEditForm.description"
                  type="text"
                  class="inline-input"
                  placeholder="Optional description"
                />
                <span v-else class="text-muted text-sm">{{ setting.description || '—' }}</span>
              </td>
              <td style="text-align: center;">
                <template v-if="editingGlobalId === setting.id">
                  <Button variant="primary" size="xs" @click="saveGlobalOverrideEdit(setting)" style="margin-right: 4px;">
                    Save
                  </Button>
                  <Button variant="ghost" size="xs" @click="cancelGlobalEdit">
                    Cancel
                  </Button>
                </template>
                <template v-else>
                  <Button variant="ghost" size="xs" @click="editGlobalRow(setting)" style="margin-right: 4px;">
                    <svg-icon name="pencil" :size="12" />
                  </Button>
                  <Button variant="ghost" size="xs" @click="confirmDeleteOverride(setting, 'global')">
                    <svg-icon name="trash" :size="12" style="color: #EF4444;" />
                  </Button>
                </template>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else style="padding: 32px; text-align: center; color: #9CA3AF;">
        No global overrides configured yet.
      </div>
    </div>

    <!-- Menu Visibility Section -->
    <div class="settings-card" style="margin-bottom: 24px;">
      <div class="card-header">
        <h2 class="card-title">
          <svg-icon name="menu" :size="18" style="color: #6B7280;" />
          Optional Features
        </h2>
        <p class="card-subtitle">Turn parts of the app on or off for everyone</p>
      </div>
      <div class="flag-row">
        <div class="flag-body">
          <span class="flag-label">Jobs (Mobile)</span>
          <span class="flag-desc">
            The touch-friendly jobs view for staff working on their phones. Turning it off hides the menu
            <em>and</em> closes <code>/jobs/mobile</code> — anyone who opens it gets a 403.
          </span>
          <span v-if="mobileOnlyUsers > 0" class="flag-warn">
            {{ mobileOnlyUsers }} user{{ mobileOnlyUsers !== 1 ? 's have' : ' has' }} no other screen — turning this
            off locks {{ mobileOnlyUsers !== 1 ? 'them' : 'them' }} out of the app entirely.
          </span>
        </div>
        <label class="switch" :class="{ 'switch--on': jobsMobileMenu, 'switch--busy': savingFlag === 'ui.jobs_mobile_menu' }">
          <input
            type="checkbox"
            :checked="jobsMobileMenu"
            :disabled="!!savingFlag"
            @change.prevent="requestToggle($event.target.checked)"
          />
          <span class="switch-track"><span class="switch-thumb" /></span>
          <span class="switch-text">{{ jobsMobileMenu ? 'On' : 'Off' }}</span>
        </label>
      </div>
      <div class="flag-row">
        <div class="flag-body">
          <span class="flag-label">Utilities</span>
          <span class="flag-desc">
            The Team and Match sheet converters, including AI reading of PDFs. Turning it off hides the menu and
            the converter links on the Event Teams and Matches pages, <em>and</em> closes <code>/utilities</code>
            — anyone who opens it gets a 403. Importing teams and matches is not affected.
          </span>
        </div>
        <label class="switch" :class="{ 'switch--on': utilitiesEnabled, 'switch--busy': savingFlag === 'ui.utilities' }">
          <input
            type="checkbox"
            :checked="utilitiesEnabled"
            :disabled="!!savingFlag"
            @change.prevent="setFlag('ui.utilities', $event.target.checked)"
          />
          <span class="switch-track"><span class="switch-thumb" /></span>
          <span class="switch-text">{{ utilitiesEnabled ? 'On' : 'Off' }}</span>
        </label>
      </div>
    </div>

    <!-- Confirm turning the mobile view off -->
    <Modal :show="showFlagConfirm" @close="cancelToggle" max-width="440px">
      <template #title>Turn off Jobs (Mobile)?</template>
      <p class="confirm-text">
        This hides the menu and closes <code>/jobs/mobile</code> for everyone.
      </p>
      <p v-if="mobileOnlyUsers > 0" class="confirm-warn">
        {{ mobileOnlyUsers }} user{{ mobileOnlyUsers !== 1 ? 's' : '' }} can only use that screen. They will be
        locked out of the app until it is turned back on — including anyone mid-shift right now.
      </p>
      <template #footer>
        <Button variant="secondary" size="sm" @click="cancelToggle">Cancel</Button>
        <Button variant="danger" size="sm" :processing="savingFlag === 'ui.jobs_mobile_menu'" @click="confirmToggleOff">
          Turn it off
        </Button>
      </template>
    </Modal>

    <!-- Event Overrides Section -->
    <div class="settings-card" style="margin-bottom: 24px;">
      <div class="card-header">
        <h2 class="card-title">
          <svg-icon name="calendar" :size="18" style="color: #6B7280;" />
          Event-Specific Overrides
        </h2>
        <p class="card-subtitle">Override offsets for specific events</p>
      </div>
      <div style="padding: 16px; border-bottom: 1px solid #E5E7EB;">
        <div style="display: flex; gap: 12px; align-items: end;">
          <div style="flex: 1;">
            <label class="form-label">Select Event</label>
            <Select
              v-model="selectedEventId"
              :options="events"
              optionLabel="name"
              optionValue="id"
              placeholder="Choose an event..."
              class="w-full"
            />
          </div>
          <div style="flex: 1;">
            <label class="form-label">Movement Type</label>
            <Select
              v-model="eventForm.movement_type"
              :options="movementTypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Choose type..."
              class="w-full"
            />
          </div>
          <div style="flex: 1;">
            <label class="form-label">Checkpoint (optional)</label>
            <Select
              v-model="eventForm.checkpoint_id"
              :options="checkpoints"
              optionLabel="name"
              optionValue="id"
              filter
              filterPlaceholder="Search checkpoints..."
              showClear
              placeholder="— None (movement-type override) —"
              class="w-full"
            />
          </div>
          <div style="width: 120px;">
            <label class="form-label">Offset (min)</label>
            <input
              v-model.number="eventForm.value"
              type="number"
              class="form-input"
              placeholder="-180"
              min="-999"
              max="999"
            />
          </div>
          <Button
            variant="primary"
            size="sm"
            :disabled="!selectedEventId || !eventForm.movement_type || eventForm.value === '' || addingEventOverride"
            :processing="addingEventOverride"
            @click="saveEventOverride"
          >
            Add Override
          </Button>
        </div>
      </div>
      <div v-if="Object.keys(eventOverrides).length > 0" class="overrides-list">
        <div v-for="(overrides, eventId) in eventOverrides" :key="eventId" class="override-group">
          <div class="override-group-header">
            <strong>{{ getEventName(eventId) }}</strong>
          </div>
          <div class="override-items">
            <div v-for="setting in overrides" :key="setting.id" class="override-item">
              <template v-if="editingEventOverrideId === setting.id">
                <div style="width: 140px;">
                  <Select
                    v-model="eventEditForm.movement_type"
                    :options="movementTypeOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                <div style="width: 160px;">
                  <Select
                    v-model="eventEditForm.checkpoint_id"
                    :options="checkpoints"
                    optionLabel="name"
                    optionValue="id"
                    filter
                    filterPlaceholder="Search checkpoints..."
                    showClear
                    placeholder="— None —"
                    class="w-full"
                  />
                </div>
              </template>
              <template v-else>
                <span class="movement-badge" :class="`badge-${getMovementTypeFromKey(setting.key)}`">
                  {{ formatMovementType(getMovementTypeFromKey(setting.key)) }}
                </span>
                <span v-if="setting.checkpoint_name" class="checkpoint-badge">
                  {{ setting.checkpoint_name }}
                </span>
              </template>

              <template v-if="editingEventOverrideId === setting.id">
                <input
                  v-model.number="eventEditForm.value"
                  type="number"
                  class="inline-input"
                  style="width: 80px;"
                  min="-999"
                  max="999"
                />
                <span class="text-muted text-sm">{{ formatTimeOffset(eventEditForm.value) }}</span>
                <Button
                  variant="primary"
                  size="xs"
                  @click="saveEventOverrideEdit(eventId, setting)"
                  style="margin-left: auto;"
                >
                  Save
                </Button>
                <Button
                  variant="ghost"
                  size="xs"
                  @click="cancelEventOverrideEdit"
                >
                  Cancel
                </Button>
              </template>
              <template v-else>
                <span class="mono">{{ setting.value }} min</span>
                <span class="text-muted text-sm">{{ formatTimeOffset(setting.value) }}</span>
                <Button
                  variant="ghost"
                  size="xs"
                  @click="editEventOverrideRow(eventId, setting)"
                  style="margin-left: auto;"
                >
                  <svg-icon name="pencil" :size="12" />
                </Button>
                <Button
                  variant="ghost"
                  size="xs"
                  @click="confirmDeleteOverride(setting, 'event', eventId)"
                >
                  <svg-icon name="trash" :size="12" style="color: #EF4444;" />
                </Button>
              </template>
            </div>
          </div>
        </div>
      </div>
      <div v-else style="padding: 32px; text-align: center; color: #9CA3AF;">
        No event-specific overrides configured yet.
      </div>
    </div>

    <!-- Confirm Offset Change Modal -->
    <Modal :show="showConfirmModal" @close="closeConfirmModal" max-width="480px" :closeable="!confirmProcessing">
      <template #title>{{ confirmModalType === 'delete' ? 'Confirm Deletion' : 'Confirm Offset Change' }}</template>

      <div>
        <p v-if="confirmModalType === 'global'" style="font-size: 14px; color: #374151; line-height: 1.5;">
          Setting the global offset for
          <strong>{{ formatMovementType(confirmGlobalInfo.movementType) }}</strong>
          <template v-if="confirmGlobalInfo.checkpointName"> (<strong>{{ confirmGlobalInfo.checkpointName }}</strong>)</template>
          <template v-if="confirmGlobalInfo.oldValue !== null">from <strong>{{ confirmGlobalInfo.oldValue }}</strong> to</template>
          <template v-else>to</template>
          <strong>{{ confirmGlobalInfo.newValue }}</strong> minutes.
        </p>
        <p v-else-if="confirmModalType === 'event'" style="font-size: 14px; color: #374151; line-height: 1.5;">
          Setting the offset for
          <strong>{{ formatMovementType(confirmEventInfo.movementType) }}</strong>
          on <strong>{{ confirmEventInfo.eventName }}</strong>
          from <strong>{{ confirmEventInfo.oldValue }}</strong> to <strong>{{ confirmEventInfo.newValue }}</strong> minutes.
        </p>
        <p v-else-if="confirmModalType === 'delete'" style="font-size: 14px; color: #374151; line-height: 1.5;">
          Deleting the offset for
          <strong>{{ formatMovementType(confirmDeleteInfo.movementType) }}</strong>
          <template v-if="confirmDeleteInfo.checkpointName"> (<strong>{{ confirmDeleteInfo.checkpointName }}</strong>)</template>
          <template v-if="confirmDeleteInfo.eventName"> on <strong>{{ confirmDeleteInfo.eventName }}</strong></template>
          — currently <strong>{{ confirmDeleteInfo.value }}</strong> minutes.
        </p>

        <div
          v-if="impactExcluded"
          style="margin-top: 12px; padding: 10px 12px; background: #F3F4F6; border-left: 3px solid #9CA3AF; border-radius: 4px; font-size: 13px; color: #4B5563;"
        >
          This movement type is not offset-driven — no movement windows will be recalculated.
        </div>
        <div
          v-else-if="impactNoActiveEvent"
          style="margin-top: 12px; padding: 10px 12px; background: #FEF3C7; border-left: 3px solid #F59E0B; border-radius: 4px; font-size: 13px; color: #92400E;"
        >
          No active event is selected, so no movement windows will be recalculated right now.
        </div>
        <div
          v-else
          style="margin-top: 12px; padding: 10px 12px; border-radius: 4px; font-size: 13px;"
          :style="impactCount > 0
            ? 'background: #FEF3C7; border-left: 3px solid #F59E0B; color: #92400E;'
            : 'background: #F3F4F6; border-left: 3px solid #9CA3AF; color: #4B5563;'"
        >
          This will recalculate the window for <strong>{{ impactCount }}</strong> movement(s)
          that don't yet have a job generated. Movements with a job already generated will not be affected.
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="closeConfirmModal" :disabled="confirmProcessing">Cancel</Button>
        <Button
          :variant="confirmModalType === 'delete' ? 'danger' : 'primary'"
          size="sm"
          @click="confirmSave"
          :processing="confirmProcessing"
          :disabled="confirmProcessing"
        >
          {{ confirmModalType === 'delete' ? 'Confirm & Delete' : 'Confirm & Save' }}
        </Button>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Components/AppLayout.vue';
import Button from '@/Components/Button.vue';
import RefreshButton from '@/Components/RefreshButton.vue';
import SvgIcon from '@/Components/SvgIcon.vue';
import Modal from '@/Components/Modal.vue';
import Select from 'primevue/select';
import { useToast } from '@/Composables/useToast';

const props = defineProps({
  movementTypes: Array,
  events: Array,
  eventOverrides: Object,
  globalOverrides: Array,
  activeEvent: Object,
  checkpoints: Array,
  uiFlags: { type: Object, default: () => ({}) },
  mobileOnlyUsers: { type: Number, default: 0 },
});

const savingFlag = ref(null); // key of the flag being saved
const showFlagConfirm = ref(false);
const jobsMobileMenu = computed(() => props.uiFlags?.jobsMobileMenu !== false);
const utilitiesEnabled = computed(() => props.uiFlags?.utilities !== false);

function requestToggle(enabled) {
  enabled ? setFlag('ui.jobs_mobile_menu', true) : (showFlagConfirm.value = true);
}

function cancelToggle() {
  showFlagConfirm.value = false;
}

function confirmToggleOff() {
  setFlag('ui.jobs_mobile_menu', false);
}

function setFlag(key, enabled) {
  savingFlag.value = key;
  router.post('/setups/settings/ui-flag', { key, enabled }, {
    preserveScroll: true,
    onFinish: () => { savingFlag.value = null; showFlagConfirm.value = false; },
  });
}

// Flash message toasts
const page = usePage();
const { success: showSuccessToast, error: showErrorToast } = useToast();

watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) showSuccessToast(flash.success);
    if (flash?.error) showErrorToast(flash.error);
  },
  { deep: true }
);

// Global override add-form state
const globalForm = ref({ movement_type: '', checkpoint_id: '', value: '', description: '' });

// Global override inline-edit state (existing rows)
const editingGlobalId = ref(null);
const globalEditForm = ref({ movement_type: '', checkpoint_id: '', value: '', description: '' });

// Populated by whichever global-override save flow (add or edit) opens the
// confirmation modal.
const confirmGlobalInfo = ref({ movementType: '', checkpointName: null, oldValue: null, newValue: null });

// Event override state
const selectedEventId = ref('');
const eventForm = ref({ movement_type: '', value: '', description: '', checkpoint_id: '' });

// Event override inline-edit state (existing rows)
const editingEventOverrideId = ref(null);
const eventEditForm = ref({ movement_type: '', checkpoint_id: '', value: '', description: '' });

// Populated by whichever event-override save flow (add or edit) opens the
// confirmation modal, so the modal doesn't need to know which one triggered it.
const confirmEventInfo = ref({ eventName: '', movementType: '', oldValue: null, newValue: null });

// Confirmation modal state (global/event offset changes, and deletions)
const showConfirmModal = ref(false);
const confirmModalType = ref(null); // 'global' | 'event' | 'delete'
const confirmProcessing = ref(false);
const impactCount = ref(0);
const impactNoActiveEvent = ref(false);
const impactExcluded = ref(false);
const pendingSaveFn = ref(null);

// Populated by confirmDeleteOverride() so the modal can describe what's being deleted.
const confirmDeleteInfo = ref({ movementType: '', checkpointName: null, eventName: null, value: null });

// "Add Override" button processing state — covers the preview-impact fetch
// that runs before the confirmation modal opens.
const addingGlobalOverride = ref(false);
const addingEventOverride = ref(false);

// {label, value} pairs for the PrimeVue Select movement-type dropdowns
const movementTypeOptions = computed(() =>
  props.movementTypes.map(type => ({ label: formatMovementType(type), value: type }))
);

const derivedOldEventValue = computed(() => {
  if (!selectedEventId.value || !eventForm.value.movement_type) return null;
  const key = `movement_offset.${eventForm.value.movement_type}`;
  const existingEventOverride = (props.eventOverrides[selectedEventId.value] || []).find(s => s.key === key && !s.checkpoint_id);
  if (existingEventOverride) return existingEventOverride.value;
  const globalDefault = props.globalOverrides.find(s => s.key === key && !s.checkpoint_id);
  return globalDefault ? globalDefault.value : null;
});

// Add a new global override (plain movement-type default, or checkpoint-
// specific if a checkpoint is picked).
async function saveGlobalOverride() {
  const checkpointId = globalForm.value.checkpoint_id || null;

  addingGlobalOverride.value = true;
  try {
    const { data } = await axios.post('/setups/settings/preview-impact', {
      movement_type: globalForm.value.movement_type,
      scope: 'global',
    });
    impactCount.value = data.count;
    impactNoActiveEvent.value = !!data.no_active_event;
    impactExcluded.value = !!data.excluded;
    confirmGlobalInfo.value = {
      movementType: globalForm.value.movement_type,
      checkpointName: checkpointId ? getCheckpointName(checkpointId) : null,
      oldValue: null,
      newValue: globalForm.value.value,
    };
    confirmModalType.value = 'global';
    pendingSaveFn.value = doSaveGlobalOverride;
    showConfirmModal.value = true;
  } finally {
    addingGlobalOverride.value = false;
  }
}

function doSaveGlobalOverride() {
  confirmProcessing.value = true;
  router.post('/setups/settings/global', {
    movement_type: globalForm.value.movement_type,
    value: globalForm.value.value,
    description: globalForm.value.description,
    checkpoint_id: globalForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      globalForm.value = { movement_type: '', checkpoint_id: '', value: '', description: '' };
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Edit an existing global override (inline, row-level). Movement type and
// checkpoint are both editable — saved by row ID so switching either one
// updates this row instead of creating a new one.
function editGlobalRow(setting) {
  editingGlobalId.value = setting.id;
  globalEditForm.value = {
    movement_type: getMovementTypeFromKey(setting.key),
    checkpoint_id: setting.checkpoint_id || '',
    value: setting.value,
    description: setting.description || '',
  };
}

function cancelGlobalEdit() {
  editingGlobalId.value = null;
  globalEditForm.value = { movement_type: '', checkpoint_id: '', value: '', description: '' };
}

async function saveGlobalOverrideEdit(setting) {
  const checkpointId = globalEditForm.value.checkpoint_id || null;

  const { data } = await axios.post('/setups/settings/preview-impact', {
    movement_type: globalEditForm.value.movement_type,
    scope: 'global',
  });
  impactCount.value = data.count;
  impactNoActiveEvent.value = !!data.no_active_event;
  impactExcluded.value = !!data.excluded;
  confirmGlobalInfo.value = {
    movementType: globalEditForm.value.movement_type,
    checkpointName: checkpointId ? getCheckpointName(checkpointId) : null,
    oldValue: setting.value,
    newValue: globalEditForm.value.value,
  };
  confirmModalType.value = 'global';
  pendingSaveFn.value = () => doSaveGlobalOverrideEdit(setting);
  showConfirmModal.value = true;
}

function doSaveGlobalOverrideEdit(setting) {
  confirmProcessing.value = true;
  router.post('/setups/settings/global', {
    id: setting.id,
    movement_type: globalEditForm.value.movement_type,
    value: globalEditForm.value.value,
    description: globalEditForm.value.description,
    checkpoint_id: globalEditForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      cancelGlobalEdit();
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Save event override (new). A checkpoint-specific override on a
// movement's first checkpoint can drive that movement's window_start, so
// it goes through the same impact preview/confirmation as a plain override.
async function saveEventOverride() {
  addingEventOverride.value = true;
  try {
    const { data } = await axios.post('/setups/settings/preview-impact', {
      movement_type: eventForm.value.movement_type,
      scope: 'event',
      event_id: selectedEventId.value,
    });
    impactCount.value = data.count;
    impactNoActiveEvent.value = !!data.no_active_event;
    impactExcluded.value = !!data.excluded;
    confirmEventInfo.value = {
      eventName: getEventName(selectedEventId.value),
      movementType: eventForm.value.movement_type,
      oldValue: derivedOldEventValue.value,
      newValue: eventForm.value.value,
    };
    confirmModalType.value = 'event';
    pendingSaveFn.value = doSaveEventOverride;
    showConfirmModal.value = true;
  } finally {
    addingEventOverride.value = false;
  }
}

function doSaveEventOverride() {
  confirmProcessing.value = true;
  router.post('/setups/settings/event', {
    event_id: selectedEventId.value,
    movement_type: eventForm.value.movement_type,
    value: eventForm.value.value,
    description: eventForm.value.description,
    checkpoint_id: eventForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      eventForm.value = { movement_type: '', value: '', description: '', checkpoint_id: '' };
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Edit an existing event override (inline, row-level). Movement type and
// checkpoint are both editable here — saved by row ID so switching either
// one updates this row instead of creating a new one.
function editEventOverrideRow(eventId, setting) {
  editingEventOverrideId.value = setting.id;
  eventEditForm.value = {
    movement_type: getMovementTypeFromKey(setting.key),
    checkpoint_id: setting.checkpoint_id || '',
    value: setting.value,
    description: setting.description || '',
  };
}

function cancelEventOverrideEdit() {
  editingEventOverrideId.value = null;
  eventEditForm.value = { movement_type: '', checkpoint_id: '', value: '', description: '' };
}

async function saveEventOverrideEdit(eventId, setting) {
  const { data } = await axios.post('/setups/settings/preview-impact', {
    movement_type: eventEditForm.value.movement_type,
    scope: 'event',
    event_id: eventId,
  });
  impactCount.value = data.count;
  impactNoActiveEvent.value = !!data.no_active_event;
  impactExcluded.value = !!data.excluded;
  confirmEventInfo.value = {
    eventName: getEventName(eventId),
    movementType: eventEditForm.value.movement_type,
    oldValue: setting.value,
    newValue: eventEditForm.value.value,
  };
  confirmModalType.value = 'event';
  pendingSaveFn.value = () => doSaveEventOverrideEdit(eventId, setting);
  showConfirmModal.value = true;
}

function doSaveEventOverrideEdit(eventId, setting) {
  confirmProcessing.value = true;
  router.post('/setups/settings/event', {
    id: setting.id,
    event_id: eventId,
    movement_type: eventEditForm.value.movement_type,
    value: eventEditForm.value.value,
    description: eventEditForm.value.description,
    checkpoint_id: eventEditForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      cancelEventOverrideEdit();
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

function confirmSave() {
  if (pendingSaveFn.value) pendingSaveFn.value();
}

function closeConfirmModal() {
  showConfirmModal.value = false;
  confirmModalType.value = null;
  pendingSaveFn.value = null;
  confirmProcessing.value = false;
}

// Delete override — shows the same impact preview/confirmation as a save,
// since deleting also triggers a movement-window recompute.
async function confirmDeleteOverride(setting, scope, eventId = null) {
  const movementType = getMovementTypeFromKey(setting.key);

  const { data } = await axios.post('/setups/settings/preview-impact', {
    movement_type: movementType,
    scope,
    event_id: eventId,
  });
  impactCount.value = data.count;
  impactNoActiveEvent.value = !!data.no_active_event;
  impactExcluded.value = !!data.excluded;
  confirmDeleteInfo.value = {
    movementType,
    checkpointName: setting.checkpoint_name || null,
    eventName: eventId ? getEventName(eventId) : null,
    value: setting.value,
  };
  confirmModalType.value = 'delete';
  pendingSaveFn.value = () => doDeleteOverride(setting.id);
  showConfirmModal.value = true;
}

function doDeleteOverride(id) {
  confirmProcessing.value = true;
  router.delete(`/setups/settings/${id}`, {
    preserveScroll: true,
    onSuccess: () => closeConfirmModal(),
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Helper functions
function formatMovementType(type) {
  return type.split('_').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function formatTimeOffset(minutes) {
  if (!minutes && minutes !== 0) return '—';
  const absMinutes = Math.abs(minutes);
  const direction = minutes < 0 ? 'before' : 'after';
  if (absMinutes >= 60) {
    const hours = Math.floor(absMinutes / 60);
    const mins = absMinutes % 60;
    return `${hours}:${String(mins).padStart(2, '0')} ${direction}`;
  }
  return `${absMinutes} min ${direction}`;
}

function getMovementTypeFromKey(key) {
  return key.replace('movement_offset.', '');
}

function getEventName(eventId) {
  const event = props.events.find(e => e.id == eventId);
  return event ? event.name : `Event #${eventId}`;
}

function getCheckpointName(checkpointId) {
  const checkpoint = props.checkpoints.find(c => c.id == checkpointId);
  return checkpoint ? checkpoint.name : `Checkpoint #${checkpointId}`;
}
</script>

<style scoped>
.settings-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  overflow: hidden;
}

.card-header {
  padding: 20px 24px;
  border-bottom: 1px solid #E5E7EB;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}

.card-subtitle {
  font-size: 13px;
  color: #6B7280;
  margin: 4px 0 0 0;
}

.flag-row {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 24px; padding: 18px 24px; flex-wrap: wrap;
}
.flag-row + .flag-row { border-top: 1px solid #E5E7EB; }
.flag-body { flex: 1 1 320px; min-width: 0; }
.flag-label { display: block; font-size: 14px; font-weight: 600; color: #111827; }
.flag-desc { display: block; font-size: 12.5px; line-height: 1.55; color: #6B7280; margin-top: 4px; max-width: 620px; }
.flag-desc code { font-size: 11.5px; background: #F3F4F6; padding: 1px 4px; border-radius: 3px; }
.flag-warn { display: block; font-size: 12.5px; color: #B45309; margin-top: 8px; }

.confirm-text { font-size: 13.5px; color: #111827; margin: 0; }
.confirm-text code { font-size: 12px; background: #F3F4F6; padding: 1px 4px; border-radius: 3px; }
.confirm-warn { font-size: 12.5px; line-height: 1.55; color: #B45309; margin: 10px 0 0; }

.switch { display: flex; align-items: center; gap: 10px; cursor: pointer; flex: 0 0 auto; }
.switch input { position: absolute; opacity: 0; width: 0; height: 0; }
.switch-track {
  width: 42px; height: 24px; border-radius: 999px;
  background: #D1D5DB; position: relative; transition: background .15s;
}
.switch-thumb {
  position: absolute; top: 3px; left: 3px;
  width: 18px; height: 18px; border-radius: 50%;
  background: white; box-shadow: 0 1px 2px rgba(0,0,0,.25);
  transition: transform .15s;
}
.switch--on .switch-track { background: var(--accent, #7A1836); }
.switch--on .switch-thumb { transform: translateX(18px); }
.switch--busy { opacity: .6; cursor: progress; }
.switch-text { font-size: 12.5px; font-weight: 600; color: #6B7280; min-width: 46px; }

.settings-table-container {
  overflow-x: auto;
}

.settings-table {
  width: 100%;
  border-collapse: collapse;
}

.settings-table th {
  background: #F9FAFB;
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #6B7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid #E5E7EB;
}

.settings-table td {
  padding: 14px 16px;
  border-bottom: 1px solid #F3F4F6;
  font-size: 14px;
}

.settings-table tbody tr:hover {
  background: #F9FAFB;
}

.movement-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  text-transform: capitalize;
}

.badge-arrival {
  background: #DBEAFE;
  color: #1E40AF;
}

.badge-departure {
  background: #FEE2E2;
  color: #991B1B;
}

.badge-match {
  background: #D1FAE5;
  color: #065F46;
}

.badge-transfer {
  background: #E0E7FF;
  color: #3730A3;
}

.badge-training {
  background: #FEF3C7;
  color: #92400E;
}

.badge-daily_ops {
  background: #F3E8FF;
  color: #6B21A8;
}

.checkpoint-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  font-size: 12px;
  font-weight: 500;
  background: #E5E7EB;
  color: #374151;
}

.inline-input {
  padding: 6px 10px;
  border: 1px solid #D1D5DB;
  border-radius: 4px;
  font-size: 14px;
  font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
}

.inline-input:focus {
  outline: none;
  border-color: #3B82F6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: #374151;
  margin-bottom: 6px;
}

.form-select, .form-input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #D1D5DB;
  border-radius: 6px;
  font-size: 14px;
  background: white;
}

.form-select:focus, .form-input:focus {
  outline: none;
  border-color: #3B82F6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.overrides-list {
  padding: 16px 24px;
}

.override-group {
  margin-bottom: 16px;
}

.override-group:last-child {
  margin-bottom: 0;
}

.override-group-header {
  font-size: 14px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 8px;
  padding-bottom: 6px;
  border-bottom: 2px solid #E5E7EB;
}

.override-items {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.override-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  background: #F9FAFB;
  border-radius: 4px;
}

.text-muted {
  color: #6B7280;
}

.text-sm {
  font-size: 13px;
}

.mono {
  font-family: 'SF Mono', 'Consolas', 'Monaco', monospace;
}
</style>
