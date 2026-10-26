<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Library</h1>
        <p class="page-subtitle">Manage reusable checkpoints, checkpoint templates, and movement templates</p>
      </div>
      <div class="page-header-actions">
        <RefreshButton :only="['checkpoints', 'checkpointTemplates', 'movementTemplates']" />
        <Button v-if="activeTab === 'checkpoints'" variant="primary" size="sm" @click="checkpointsTab?.openNew()">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New Checkpoint
        </Button>
        <Button v-if="activeTab === 'checkpoint-templates'" variant="secondary" size="sm" @click="openCopyFromEvent('checkpoint-template')">
          <template #icon><svg-icon name="copy" :size="14" /></template>
          Copy from Event
        </Button>
        <Button v-if="activeTab === 'checkpoint-templates'" variant="primary" size="sm" @click="checkpointTemplateEditor = { template: null }">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New Checkpoint Template
        </Button>
        <Button v-if="activeTab === 'movement-templates'" variant="secondary" size="sm" @click="openCopyFromEvent('movement-template')">
          <template #icon><svg-icon name="copy" :size="14" /></template>
          Copy from Event
        </Button>
        <Button v-if="activeTab === 'movement-templates'" variant="primary" size="sm" @click="movementEditor = { template: null }">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New Movement Template
        </Button>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display: flex; margin-bottom: 12px; border-bottom: 1px solid var(--border);">
      <button 
        v-for="tab in tabs" 
        :key="tab.id" 
        @click="activeTab = tab.id"
        class="tab"
        :class="{ 'tab--active': activeTab === tab.id }"
      >
        {{ tab.label }}
        <span class="tab-count">{{ tab.count }}</span>
      </button>
    </div>

    <!-- Checkpoints Tab -->
    <CheckpointsTab
      v-if="activeTab === 'checkpoints'"
      ref="checkpointsTab"
      v-model:selected="selectedCheckpointIds"
      :checkpoints="checkpoints"
      :templates="checkpointTemplates"
      :has-active-event="hasActiveEvent"
      @delete="deleteCheckpoint"
      @bulk-delete="deleteSelectedCheckpoints"
    />

    <!-- Checkpoint Templates Tab -->
    <div v-if="activeTab === 'checkpoint-templates'">
      <NoActiveEvent v-if="!hasActiveEvent" what="checkpoint templates" compact />
      <CheckpointTemplatesTab
        v-else
        :templates="checkpointTemplates"
        @edit="editCheckpointTemplate"
        @duplicate="duplicateCheckpointTemplate"
        @delete="deleteCheckpointTemplate"
        @open-movement-template="openMovementTemplate"
      />
    </div>

    <!-- Movement Templates Tab -->
    <div v-if="activeTab === 'movement-templates'">
      <NoActiveEvent v-if="!hasActiveEvent" what="movement templates" compact />
      <MovementTemplatesTab
        v-else
        ref="movementTemplatesTab"
        :templates="movementTemplates"
        :checkpoint-templates="checkpointTemplates"
        @edit="editMovementTemplate"
        @duplicate="duplicateMovementTemplate"
        @delete="deleteMovementTemplate"
        @open-checkpoint-template="openCheckpointTemplate"
      />
    </div>

    <!-- New / edit checkpoint template -->
    <CheckpointTemplateEditor
      v-if="checkpointTemplateEditor"
      :template="checkpointTemplateEditor.template"
      :checkpoints="checkpoints"
      :templates="checkpointTemplates"
      :movement-templates="movementTemplates"
      @close="checkpointTemplateEditor = null"
      @saved="onCheckpointTemplateSaved"
    />

    <!-- New / edit movement template -->
    <MovementTemplateEditor
      v-if="movementEditor"
      :template="movementEditor.template"
      :checkpoint-templates="checkpointTemplates"
      :movement-templates="movementTemplates"
      @close="movementEditor = null"
      @saved="onMovementTemplateSaved"
    />

    <!-- Delete Confirmation Modal -->
    <Modal v-if="deleteTarget" :show="showDeleteConfirmation" @close="cancelDelete">
      <template #title>
        <div style="display: flex; align-items: center; gap: 8px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #DC2626;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <span>{{ deleteError ? 'Cannot delete' : 'Delete' }} {{ deleteTarget.entityType }}</span>
        </div>
      </template>
      <div v-if="deleteError" style="padding: 4px 0;">
        <p style="font-size: 14px; color: #B91C1C; font-weight: 600; margin-bottom: 12px;">
          {{ deleteError }}
        </p>
        <template v-if="deleteTarget.type === 'movement-template'">
          <p style="font-size: 13px; color: var(--ink2); margin-bottom: 8px;">
            Delete or rebuild those plans in Planning first, then delete the template.
          </p>
          <p style="font-size: 13px; color: var(--ink2);">
            Some of them may have <strong>zero movements</strong> and are easy to miss. In Planning,
            search for <strong>{{ deleteTarget.name }}</strong> or use the <strong>No movements</strong>
            filter, and attend to them.
          </p>
        </template>
      </div>
      <div v-else style="padding: 4px 0;">
        <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px;">
          Are you sure you want to delete <strong>{{ deleteTarget.name }}</strong>?
        </p>
        <p v-if="deleteTarget.type === 'movement-template'" style="font-size: 13px; color: var(--ink2);">
          It moves to Setups → Recycle bin and can be restored for 30 days. It can't be deleted while a plan uses it; if only plans in the recycle bin use it, another admin has to approve first.
        </p>
        <p v-else style="font-size: 13px; color: var(--ink2);">
          This action cannot be undone.
        </p>
      </div>
      <template #footer>
        <div v-if="deleteError" style="display: flex; gap: 8px; justify-content: flex-end;">
          <Button variant="secondary" size="sm" @click="cancelDelete">Close</Button>
        </div>
        <div v-else style="display: flex; gap: 8px; justify-content: flex-end;">
          <Button variant="secondary" size="sm" @click="cancelDelete" :disabled="processingDelete">Cancel</Button>
          <Button variant="primary" size="sm" @click="confirmDelete" :disabled="processingDelete" style="background: #DC2626; border-color: #DC2626;">
            {{ processingDelete ? 'Deleting...' : 'Delete' }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Bulk Delete Confirmation Modal -->
    <Modal :show="showBulkDeleteConfirmation" @close="cancelBulkDeleteCheckpoints">
      <template #title>
        <div style="display: flex; align-items: center; gap: 8px;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #DC2626;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
          </svg>
          <span>Delete Checkpoints</span>
        </div>
      </template>
      <div style="padding: 4px 0;">
        <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px;">
          Are you sure you want to delete <strong>{{ selectedCheckpointIds.size }}</strong> checkpoint(s)?
        </p>
        <p style="font-size: 13px; color: var(--ink2);">
          This action cannot be undone. Any checkpoint still in use by a template will be skipped.
        </p>
      </div>
      <template #footer>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <Button variant="secondary" size="sm" @click="cancelBulkDeleteCheckpoints" :disabled="bulkDeletingCheckpoints">Cancel</Button>
          <Button variant="primary" size="sm" @click="confirmDeleteSelectedCheckpoints" :disabled="bulkDeletingCheckpoints" style="background: #DC2626; border-color: #DC2626;">
            {{ bulkDeletingCheckpoints ? 'Deleting...' : `Delete ${selectedCheckpointIds.size}` }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Copy Templates from Event Modal -->
    <Modal :show="showCopyFromEvent" @close="closeCopyFromEvent" maxWidth="600px">
      <template #title>Copy {{ copyType === 'checkpoint-template' ? 'Checkpoint Templates' : 'Movement Templates' }} from Event</template>
      <div style="display: flex; flex-direction: column; gap: 12px;">
        <div>
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; color: var(--ink);">Source Event</label>
          <select v-model="copySourceEventId" style="width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 6px; font-size: 13px;">
            <option :value="null">Select an event...</option>
            <option v-for="event in otherEvents" :key="event.id" :value="event.id">
              {{ event.name }}
            </option>
          </select>
          <div v-if="otherEvents.length === 0" style="font-size: 12px; color: var(--ink3); margin-top: 6px;">
            No other events available to copy from.
          </div>
        </div>

        <div v-if="copySourceEventId">
          <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 8px; color: var(--ink);">
            Templates
            <span v-if="copySelectedIds.size > 0" style="font-weight: 400; color: var(--ink3);">({{ copySelectedIds.size }} selected)</span>
          </label>

          <div v-if="loadingCopySourceTemplates" style="padding: 20px; text-align: center; color: var(--ink3); font-size: 12px;">
            Loading templates...
          </div>

          <div v-else-if="copySourceTemplates.length === 0" style="padding: 20px; text-align: center; color: var(--ink3); background: var(--panel); border: 1px dashed var(--border); border-radius: 6px; font-size: 12px;">
            This event has no {{ copyType === 'checkpoint-template' ? 'checkpoint' : 'movement' }} templates to copy.
          </div>

          <div v-else style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden; max-height: 320px; overflow-y: auto;">
            <div
              v-for="(template, i) in copySourceTemplates"
              :key="template.id"
              style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; cursor: pointer;"
              :style="{ borderBottom: i === copySourceTemplates.length - 1 ? 'none' : '1px solid var(--border)', background: copySelectedIds.has(template.id) ? 'var(--accent-soft)' : 'transparent' }"
              @click="toggleCopySelection(template.id)"
            >
              <input type="checkbox" :checked="copySelectedIds.has(template.id)" @click.stop @change="toggleCopySelection(template.id)" />
              <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600; color: var(--ink);">{{ template.name }}</div>
                <div style="font-size: 11px; color: var(--ink3); font-family: var(--mono);">{{ template.code }}</div>
              </div>
              <div style="font-size: 11px; color: var(--ink2);">
                <template v-if="copyType === 'checkpoint-template'">{{ template.checkpoints_count || 0 }} checkpoint(s)</template>
                <template v-else>{{ template.legs_count || template.total_legs || 0 }} leg(s)</template>
              </div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <Button variant="secondary" size="sm" @click="closeCopyFromEvent" :disabled="copyingTemplates">Cancel</Button>
          <Button variant="primary" size="sm" @click="submitCopyFromEvent" :disabled="copyingTemplates || copySelectedIds.size === 0">
            {{ copyingTemplates ? 'Copying...' : `Copy Selected (${copySelectedIds.size})` }}
          </Button>
        </div>
      </template>
    </Modal>

  </app-layout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '../Components/AppLayout.vue';
import NoActiveEvent from '../Components/NoActiveEvent.vue';
import Button from '../Components/Button.vue';
import Modal from '../Components/Modal.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import CheckpointsTab from '../Components/Library/CheckpointsTab.vue';
import MovementTemplatesTab from '../Components/Library/MovementTemplatesTab.vue';
import MovementTemplateEditor from '../Components/Library/MovementTemplateEditor.vue';
import CheckpointTemplatesTab from '../Components/Library/CheckpointTemplatesTab.vue';
import CheckpointTemplateEditor from '../Components/Library/CheckpointTemplateEditor.vue';
import { useToast } from '../Composables/useToast';

const { error: showErrorToast, success: showSuccessToast } = useToast();
const page = usePage();

// Props from backend
const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  checkpointTemplates: { type: Array, default: () => [] },
  movementTemplates: { type: Array, default: () => [] },
});

// Active tab
const activeTab = ref('checkpoints');

const hasActiveEvent = computed(() => !!page.props.activeEventId);

// Tabs configuration
const tabs = computed(() => [
  { id: 'checkpoints', label: 'Checkpoints', count: props.checkpoints.length },
  { id: 'checkpoint-templates', label: 'Checkpoint Templates', count: props.checkpointTemplates.length },
  { id: 'movement-templates', label: 'Movement Templates', count: props.movementTemplates.length },
]);

// The Checkpoints tab (search, filters, edit panel, bulk actions) is Components/Library/CheckpointsTab.vue.
const checkpointsTab = ref(null);

// Bulk selection for the checkpoints table
const selectedCheckpointIds = ref(new Set());
const bulkDeletingCheckpoints = ref(false);
const showBulkDeleteConfirmation = ref(false);

function deleteSelectedCheckpoints() {
  if (selectedCheckpointIds.value.size === 0) return;
  showBulkDeleteConfirmation.value = true;
}

function cancelBulkDeleteCheckpoints() {
  showBulkDeleteConfirmation.value = false;
}

function confirmDeleteSelectedCheckpoints() {
  const ids = Array.from(selectedCheckpointIds.value);
  if (ids.length === 0) return;

  bulkDeletingCheckpoints.value = true;

  router.delete('/admin/checkpoints/bulk-delete', {
    data: { ids },
    preserveScroll: true,
    onSuccess: () => {
      selectedCheckpointIds.value = new Set();
      showBulkDeleteConfirmation.value = false;
      const flash = page.props.flash;
      if (flash?.error) {
        showErrorToast(flash.error);
      } else if (flash?.success) {
        showSuccessToast(flash.success);
      }
    },
    onError: () => {
      showErrorToast('Failed to delete checkpoints');
    },
    onFinish: () => {
      bulkDeletingCheckpoints.value = false;
    },
  });
}

// Modals
const showDeleteConfirmation = ref(false);
const deleteError = ref(null);

// Processing states
const processingDelete = ref(false);

// Delete confirmation state
const deleteTarget = ref(null); // { type: 'checkpoint'|'checkpoint-template'|'movement-template', id: number, name: string }

// New / edit checkpoint template: { template } (null for new) while the editor is open.
const checkpointTemplateEditor = ref(null);
const movementTemplatesTab = ref(null);

// New / edit movement template: { template } (null for new) while the editor is open.
const movementEditor = ref(null);

// Copy templates from another event
const showCopyFromEvent = ref(false);
const copyType = ref('checkpoint-template'); // 'checkpoint-template' | 'movement-template'
const copySourceEventId = ref(null);
const copySourceTemplates = ref([]);
const copySelectedIds = ref(new Set());
const loadingCopySourceTemplates = ref(false);
const copyingTemplates = ref(false);

const otherEvents = computed(() =>
  (page.props.eventList || []).filter(event => event.id !== page.props.activeEventId)
);

function openCopyFromEvent(type) {
  copyType.value = type;
  copySourceEventId.value = null;
  copySourceTemplates.value = [];
  copySelectedIds.value = new Set();
  showCopyFromEvent.value = true;
}

function closeCopyFromEvent() {
  showCopyFromEvent.value = false;
}

function toggleCopySelection(id) {
  const next = new Set(copySelectedIds.value);
  next.has(id) ? next.delete(id) : next.add(id);
  copySelectedIds.value = next;
}

watch(copySourceEventId, async (eventId) => {
  copySelectedIds.value = new Set();
  copySourceTemplates.value = [];
  if (!eventId) return;

  loadingCopySourceTemplates.value = true;
  const endpoint = copyType.value === 'checkpoint-template'
    ? `/admin/checkpoint-templates/by-event/${eventId}`
    : `/admin/movement-templates/by-event/${eventId}`;

  try {
    const { data } = await axios.get(endpoint);
    copySourceTemplates.value = data.templates;
  } catch (e) {
    showErrorToast('Failed to load templates for that event');
  } finally {
    loadingCopySourceTemplates.value = false;
  }
});

function submitCopyFromEvent() {
  if (copySelectedIds.value.size === 0) return;

  copyingTemplates.value = true;
  const endpoint = copyType.value === 'checkpoint-template'
    ? '/admin/checkpoint-templates/copy-from-event'
    : '/admin/movement-templates/copy-from-event';

  router.post(endpoint, {
    source_event_id: copySourceEventId.value,
    template_ids: Array.from(copySelectedIds.value),
  }, {
    preserveScroll: true,
    onSuccess: () => {
      showCopyFromEvent.value = false;
      const flash = page.props.flash;
      if (flash?.error) {
        showErrorToast(flash.error);
      } else if (flash?.success) {
        showSuccessToast(flash.success);
      }
    },
    onError: (errors) => {
      const firstError = Object.values(errors)[0];
      const message = Array.isArray(firstError) ? firstError[0] : firstError;
      showErrorToast(message || 'Failed to copy templates');
    },
    onFinish: () => {
      copyingTemplates.value = false;
    },
  });
}

// "Open in Checkpoint Templates" from a movement template's leg.
function openCheckpointTemplate(template) {
  activeTab.value = 'checkpoint-templates';
  editCheckpointTemplate(template);
}

function duplicateMovementTemplate(template) {
  router.post(`/admin/movement-templates/${template.id}/duplicate`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      const flash = page.props.flash;
      if (flash?.error) showErrorToast(flash.error);
      else showSuccessToast(flash?.success || 'Template duplicated');
    },
    onError: (errors) => showErrorToast(Object.values(errors)[0] || 'Could not duplicate the template'),
  });
}

// CRUD functions for Checkpoints (create and edit live in CheckpointsTab)
function deleteCheckpoint(id) {
  const checkpoint = props.checkpoints.find(c => c.id === id);
  deleteTarget.value = {
    type: 'checkpoint',
    id: id,
    name: checkpoint?.name || 'this checkpoint',
    entityType: 'Checkpoint'
  };
  showDeleteConfirmation.value = true;
}

// Checkpoint templates
function editCheckpointTemplate(template) {
  checkpointTemplateEditor.value = { template };
}

function onCheckpointTemplateSaved() {
  checkpointTemplateEditor.value = null;
  const flash = page.props.flash;
  if (flash?.error) showErrorToast(flash.error);
  else showSuccessToast(flash?.success || 'Checkpoint template saved');
}

function duplicateCheckpointTemplate(template) {
  router.post(`/admin/checkpoint-templates/${template.id}/duplicate`, {}, {
    preserveScroll: true,
    onSuccess: () => {
      const flash = page.props.flash;
      if (flash?.error) showErrorToast(flash.error);
      else showSuccessToast(flash?.success || 'Template duplicated');
    },
    onError: (errors) => showErrorToast(Object.values(errors)[0] || 'Could not duplicate the template'),
  });
}

// "Used in movement templates" chip: open that template on its tab.
function openMovementTemplate(id) {
  activeTab.value = 'movement-templates';
  nextTick(() => movementTemplatesTab.value?.select(id));
}

function deleteCheckpointTemplate(id) {
  const template = props.checkpointTemplates.find(t => t.id === id);
  deleteTarget.value = {
    type: 'checkpoint-template',
    id: id,
    name: template?.name || 'this checkpoint template',
    entityType: 'Checkpoint Template'
  };
  showDeleteConfirmation.value = true;
}

// CRUD functions for Movement Templates
function editMovementTemplate(template) {
  movementEditor.value = { template };
}

function onMovementTemplateSaved() {
  movementEditor.value = null;
  const flash = page.props.flash;
  if (flash?.error) showErrorToast(flash.error);
  else showSuccessToast(flash?.success || 'Movement template saved');
}

function deleteMovementTemplate(id) {
  const template = props.movementTemplates.find(t => t.id === id);
  deleteTarget.value = {
    type: 'movement-template',
    id: id,
    name: template?.name || 'this movement template',
    entityType: 'Movement Template'
  };
  showDeleteConfirmation.value = true;
}

function confirmDelete() {
  if (!deleteTarget.value) return;

  processingDelete.value = true;
  const { type, id } = deleteTarget.value;
  
  const routes = {
    'checkpoint': `/admin/checkpoints/${id}`,
    'checkpoint-template': `/admin/checkpoint-templates/${id}`,
    'movement-template': `/admin/movement-templates/${id}`
  };

  router.delete(routes[type], {
    onSuccess: () => {
      // Laravel's back()->with('error', ...) guard (e.g. "template is in
      // use") is still an HTTP redirect, so Inertia treats it as success —
      // check the actual flash message to know whether the delete happened.
      const flash = page.props.flash;
      if (flash?.error) {
        deleteError.value = flash.error;
        return;
      }
      showDeleteConfirmation.value = false;
      deleteTarget.value = null;
      showSuccessToast(flash?.success || 'Deleted successfully', 8000);
    },
    onError: () => {
      // Keep modal open on error so user can see the error message
    },
    onFinish: () => {
      processingDelete.value = false;
    },
  });
}

function cancelDelete() {
  showDeleteConfirmation.value = false;
  deleteTarget.value = null;
  deleteError.value = null;
}
</script>

<style scoped>

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border);
}

.tab {
  padding: 10px 14px;
  border: none;
  background: transparent;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: inherit;
  color: var(--ink3);
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: color 0.15s ease, border-bottom-color 0.15s ease;
}

.tab:hover { 
  color: var(--ink); 
}

.tab--active { 
  color: var(--accent); 
  border-bottom-color: var(--accent); 
}

.tab-count {
  background: var(--panel); 
  border: 1px solid var(--border);
  border-radius: 999px; 
  padding: 1px 6px; 
  font-size: 10px; 
  font-weight: 700;
  color: var(--ink3);
}

.page-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px 0;
}

.page-subtitle {
  font-size: 13px;
  color: var(--ink2);
  margin: 0;
}

.page-header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.table-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.table-controls {
  display: flex;
  gap: 8px;
  align-items: center;
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

.search-input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: var(--ink);
  width: 200px;
  padding: 0;
}

.search-input::placeholder {
  color: var(--ink4);
}

.filter-select {
  padding: 6px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 13px;
  color: var(--ink2);
  cursor: pointer;
  transition: border-color 0.13s;
  outline: none;
}

.filter-select:hover {
  border-color: var(--accent);
}

.filter-select:focus {
  border-color: var(--accent);
}

.plan-table-card {
  background: var(--surface);
  border-radius: 8px;
  border: 1px solid var(--border);
  overflow: hidden;
}

/* Slide transition for detail panel */
.slide-left-enter-active,
.slide-left-leave-active {
  transition: all 0.3s ease;
}

.slide-left-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.slide-left-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
