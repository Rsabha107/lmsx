<template>
  <div class="ck">
    <div class="ck-toolbar">
      <input v-model="search" class="ck-search" type="search" placeholder="Search by name or code…" aria-label="Search checkpoints" />
      <div class="ck-pills">
        <button v-for="c in categoryPills" :key="c.value" type="button"
          :class="['ck-pill', { 'ck-pill--on': category === c.value }]" @click="category = c.value">
          {{ c.label }} <span>{{ c.count }}</span>
        </button>
      </div>
      <label class="ck-usage">
        <span>Show</span>
        <select v-model="usage" aria-label="Show checkpoints">
          <option value="all">All checkpoints</option>
          <option value="used">In use</option>
          <option value="unused">Not used in any template</option>
        </select>
      </label>
    </div>

    <div v-if="selected.size" class="ck-selbar" role="toolbar" aria-label="Selected checkpoints">
      <strong>{{ selected.size }} selected</strong>
      <button type="button" @click="openAddToTemplate">Add to template…</button>
      <button type="button" @click="openCategory">Change category</button>
      <button type="button" class="ck-selbar-del" @click="emit('bulk-delete')">Delete</button>
      <button type="button" class="ck-selbar-clear" @click="selected = new Set()">Clear</button>
    </div>

    <div class="ck-body">
      <div class="ck-table">
        <div class="ck-row ck-head">
          <input type="checkbox" :checked="allVisibleSelected" :indeterminate="someVisibleSelected" aria-label="Select all checkpoints" @change="toggleAll" />
          <span>Code</span><span>Checkpoint</span><span>Category · Type</span><span>Used in</span><span />
        </div>

        <div v-for="cp in visible" :key="cp.id"
          :class="['ck-row', 'ck-item', { 'ck-item--open': editing?.id === cp.id }]"
          @click="openEdit(cp)">
          <input type="checkbox" :checked="selected.has(cp.id)" :aria-label="`Select ${cp.name}`" @click.stop @change="toggle(cp.id)" />
          <span class="ck-code">{{ cp.code }}</span>
          <div class="ck-name">
            <span class="ck-name-text">{{ cp.name }}</span>
            <div v-if="cp.description || capsOf(cp).length" class="ck-name-sub">
              <span v-for="c in capsOf(cp)" :key="c" class="ck-cap">{{ c }}</span>
              <span v-if="cp.description" class="ck-desc" :title="cp.description">{{ cp.description }}</span>
            </div>
          </div>
          <div class="ck-cat">
            <span>{{ categoryLabel(cp.category) }}</span>
            <small>{{ typeLabel(cp.type) }} · {{ captureLabel(cp.capture_method) }}</small>
          </div>
          <div class="ck-use">
            <span class="ck-use-bar"><span :style="{ width: usePct(cp) }" /></span>
            <span :class="{ 'ck-unused': !cp.usage_count }">{{ cp.usage_count ? `${cp.usage_count} template${cp.usage_count === 1 ? '' : 's'}` : 'Unused' }}</span>
          </div>
          <div class="ck-acts" @click.stop>
            <button type="button" class="ck-act" title="Edit" :aria-label="`Edit ${cp.name}`" @click="openEdit(cp)">✎</button>
            <button type="button" class="ck-act ck-act--del" title="Delete" :aria-label="`Delete ${cp.name}`" @click="emit('delete', cp.id)">×</button>
          </div>
        </div>

        <div v-if="!visible.length" class="ck-empty">
          {{ checkpoints.length ? 'No checkpoints match.' : 'No checkpoints yet. Create the first one with New Checkpoint.' }}
        </div>
      </div>

      <!-- Edit panel -->
      <aside v-if="editing" class="ck-panel" aria-label="Edit checkpoint">
        <header class="ck-panel-head">
          <span>Edit checkpoint</span>
          <button type="button" class="ck-x" aria-label="Close" @click="editing = null">×</button>
        </header>
        <div class="ck-panel-body">
          <CheckpointFields v-model="editing" :errors="errors" with-code />
          <div class="ck-usedin">
            <span class="ck-label">Used in {{ editing.used_in.length }} checkpoint template{{ editing.used_in.length === 1 ? '' : 's' }}</span>
            <div class="ck-chips">
              <span v-for="t in editing.used_in" :key="t.id" class="ck-chip" :title="t.event ? `Event ${t.event}` : ''">
                {{ t.name }}<small v-if="t.event && t.event_id !== activeEventId"> · {{ t.event }}</small>
              </span>
              <span v-if="!editing.used_in.length" class="ck-muted">Not used in any checkpoint template</span>
            </div>
          </div>
        </div>
        <footer class="ck-panel-foot">
          <button type="button" class="ck-btn" @click="editing = null">Close</button>
          <button type="button" class="ck-btn ck-btn--primary" :disabled="!editDirty || saving" @click="saveEdit">{{ saving ? 'Saving…' : 'Save' }}</button>
        </footer>
      </aside>
    </div>

    <!-- Rename reaches existing jobs -->
    <ConfirmModal
      :show="!!renameConfirm"
      title="Rename this step on existing jobs?"
      :message="renameConfirm?.message ?? ''"
      :note="renameConfirm?.note"
      confirm-label="Rename and save"
      :processing="saving"
      @close="renameConfirm = null"
      @confirm="saveEdit"
    />

    <!-- New checkpoint -->
    <Modal :show="showNew" max-width="520px" @close="showNew = false">
      <template #title>New checkpoint</template>
      <p class="ck-modal-sub">Added to the library. Use it in any checkpoint template.</p>
      <CheckpointFields v-model="draft" :errors="errors" autofocus />
      <template #footer>
        <div class="ck-modal-foot">
          <button type="button" class="ck-btn" @click="showNew = false">Cancel</button>
          <button type="button" class="ck-btn ck-btn--primary" :disabled="!draft.name.trim() || saving" @click="create">{{ saving ? 'Creating…' : 'Create checkpoint' }}</button>
        </div>
      </template>
    </Modal>

    <!-- Change category -->
    <Modal :show="showCategory" max-width="420px" @close="showCategory = false">
      <template #title>Change category</template>
      <label class="ck-field">
        <span class="ck-label">Category for the {{ selected.size }} selected checkpoint{{ selected.size === 1 ? '' : 's' }}</span>
        <select v-model="bulkCategory">
          <option v-for="c in CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option>
        </select>
      </label>
      <template #footer>
        <div class="ck-modal-foot">
          <button type="button" class="ck-btn" @click="showCategory = false">Cancel</button>
          <button type="button" class="ck-btn ck-btn--primary" :disabled="saving" @click="applyCategory">Apply</button>
        </div>
      </template>
    </Modal>

    <!-- Add to template -->
    <Modal :show="showAdd" max-width="480px" @close="showAdd = false">
      <template #title>Add to checkpoint template</template>
      <p v-if="!templates.length" class="ck-modal-sub">
        {{ hasActiveEvent ? 'The active event has no checkpoint templates yet.' : 'Select an event first: checkpoint templates belong to an event.' }}
      </p>
      <template v-else>
        <label class="ck-field">
          <span class="ck-label">Template ({{ activeEventName || 'active event' }})</span>
          <select v-model="addTemplateId" aria-label="Checkpoint template">
            <option :value="null" disabled>Choose a template…</option>
            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} · {{ t.checkpoint_count ?? t.checkpoints?.length ?? 0 }} steps</option>
          </select>
        </label>
        <p v-if="addTemplateId" class="ck-modal-sub">
          <template v-if="addPreview.adding">{{ addPreview.adding }} will be added after the last step, in code order.</template>
          <template v-else>All the selected checkpoints are in this template already.</template>
          <template v-if="addPreview.already"> {{ addPreview.already }} already in it.</template>
        </p>
      </template>
      <template #footer>
        <div class="ck-modal-foot">
          <button type="button" class="ck-btn" @click="showAdd = false">Cancel</button>
          <button type="button" class="ck-btn ck-btn--primary" :disabled="!addTemplateId || !addPreview.adding || saving" @click="applyAdd">
            Add {{ addPreview.adding || '' }} checkpoint{{ addPreview.adding === 1 ? '' : 's' }}
          </button>
        </div>
      </template>
    </Modal>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '../Modal.vue';
import ConfirmModal from '../ConfirmModal.vue';
import CheckpointFields from './CheckpointFields.vue';
import { CATEGORIES, categoryLabel, typeLabel, captureLabel, blankCheckpoint } from './checkpointOptions';
import { useToast } from '../../Composables/useToast';

// The global checkpoint library: list, edit panel, new checkpoint and bulk actions.
const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  // The active event's checkpoint templates, for "Add to template".
  templates: { type: Array, default: () => [] },
  hasActiveEvent: { type: Boolean, default: false },
});
// Ticked checkpoint ids; the page owns them for its bulk-delete confirmation.
const selected = defineModel('selected', { type: Set, required: true });
const emit = defineEmits(['delete', 'bulk-delete']);

const page = usePage();
const { success: toastSuccess, error: toastError } = useToast();
const activeEventId = computed(() => Number(page.props.activeEventId) || null);
const activeEventName = computed(() => page.props.eventList?.find((e) => e.id === activeEventId.value)?.name ?? '');

const search = ref('');
const category = ref('all');
const usage = ref('all');

const categoryPills = computed(() => [
  { value: 'all', label: 'All', count: props.checkpoints.length },
  ...CATEGORIES.map((c) => ({ value: c, label: categoryLabel(c), count: props.checkpoints.filter((cp) => cp.category === c).length }))
    .filter((c) => c.count || category.value === c.value),
]);

const visible = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.checkpoints.filter((cp) => (category.value === 'all' || cp.category === category.value)
    && (usage.value === 'all' || (usage.value === 'used' ? cp.usage_count > 0 : !cp.usage_count))
    && (!q || [cp.code, cp.name, cp.description].some((v) => (v || '').toLowerCase().includes(q))));
});

const maxUse = computed(() => Math.max(4, ...props.checkpoints.map((cp) => cp.usage_count || 0)));
const usePct = (cp) => `${Math.min(100, ((cp.usage_count || 0) / maxUse.value) * 100)}%`;
const capsOf = (cp) => [cp.requires_photo && 'Photo', cp.requires_signature && 'Signature', cp.requires_baggage_count && 'Bag count'].filter(Boolean);

const allVisibleSelected = computed(() => visible.value.length > 0 && visible.value.every((cp) => selected.value.has(cp.id)));
const someVisibleSelected = computed(() => !allVisibleSelected.value && visible.value.some((cp) => selected.value.has(cp.id)));
function toggleAll() {
  selected.value = allVisibleSelected.value ? new Set() : new Set(visible.value.map((cp) => cp.id));
}
function toggle(id) {
  const next = new Set(selected.value);
  next.has(id) ? next.delete(id) : next.add(id);
  selected.value = next;
}

const saving = ref(false);
const errors = ref({});
const firstError = (errs) => { const e = Object.values(errs)[0]; return Array.isArray(e) ? e[0] : e; };
const flashToast = (fallback) => {
  const flash = page.props.flash;
  if (flash?.error) toastError(flash.error);
  else toastSuccess(flash?.success || fallback);
};

// Edit panel: a copy of the row, saved on Save.
const editing = ref(null);
const EDIT_FIELDS = ['code', 'name', 'description', 'category', 'type', 'capture_method', 'requires_photo', 'requires_signature', 'requires_baggage_count'];
const original = computed(() => props.checkpoints.find((cp) => cp.id === editing.value?.id) ?? null);
const editDirty = computed(() => !!original.value && EDIT_FIELDS.some((f) => (editing.value[f] ?? '') !== (original.value[f] ?? '')));

function openEdit(cp) {
  errors.value = {};
  editing.value = { ...cp, used_in: cp.used_in ?? [] };
}
// After a reload, follow the saved row (or close if it was deleted).
watch(() => props.checkpoints, () => {
  if (!editing.value) return;
  const fresh = props.checkpoints.find((cp) => cp.id === editing.value.id);
  if (!fresh) editing.value = null;
  else if (!editDirty.value) editing.value = { ...fresh, used_in: fresh.used_in ?? [] };
});

// A rename reaches every job's copy of the step, so it asks first (when any job has it).
const renameConfirm = ref(null);
const esc = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

function saveEdit() {
  const from = (original.value?.name ?? '').trim();
  const to = (editing.value.name ?? '').trim();
  const jobs = original.value?.jobs_count ?? 0;
  if (from !== to && jobs > 0 && !renameConfirm.value) {
    renameConfirm.value = {
      message: `<strong>${esc(from)}</strong> becomes <strong>${esc(to)}</strong>.<br><br>`
        + `The step is renamed on <strong>${jobs} job${jobs === 1 ? '' : 's'}</strong>, past and future, in every event that uses it: `
        + 'the Jobs Queue, Job Detail, the mobile app, Movement Tracking and exports. Times, notes and photos are not changed.',
      note: 'Recorded in the Audit Trail.',
    };
    return;
  }
  renameConfirm.value = null;
  saving.value = true;
  errors.value = {};
  const data = Object.fromEntries(EDIT_FIELDS.map((f) => [f, editing.value[f]]));
  router.put(`/admin/checkpoints/${editing.value.id}`, { ...data, is_active: editing.value.is_active ?? true }, {
    preserveScroll: true,
    onSuccess: () => flashToast('Checkpoint updated'),
    onError: (errs) => { errors.value = errs; toastError(firstError(errs)); },
    onFinish: () => { saving.value = false; },
  });
}

// New checkpoint
const showNew = ref(false);
const draft = ref(blankCheckpoint());
function openNew() {
  draft.value = blankCheckpoint();
  errors.value = {};
  showNew.value = true;
}
function create() {
  saving.value = true;
  errors.value = {};
  router.post('/admin/checkpoints', { ...draft.value, name: draft.value.name.trim() }, {
    preserveScroll: true,
    onSuccess: () => {
      showNew.value = false;
      flashToast('Checkpoint created');
      const made = [...props.checkpoints].sort((a, b) => b.id - a.id)[0];
      if (made) openEdit(made);
    },
    onError: (errs) => { errors.value = errs; toastError(firstError(errs)); },
    onFinish: () => { saving.value = false; },
  });
}
defineExpose({ openNew });

// Bulk: change category
const showCategory = ref(false);
const bulkCategory = ref('Logistics');
function openCategory() {
  const cats = new Set(props.checkpoints.filter((cp) => selected.value.has(cp.id)).map((cp) => cp.category));
  bulkCategory.value = cats.size === 1 ? [...cats][0] : 'Logistics';
  showCategory.value = true;
}
function applyCategory() {
  saving.value = true;
  router.patch('/admin/checkpoints/bulk-category', { ids: [...selected.value], category: bulkCategory.value }, {
    preserveScroll: true,
    onSuccess: () => { showCategory.value = false; selected.value = new Set(); flashToast('Category changed'); },
    onError: (errs) => toastError(firstError(errs)),
    onFinish: () => { saving.value = false; },
  });
}

// Bulk: add to a checkpoint template of the active event
const showAdd = ref(false);
const addTemplateId = ref(null);
const addPreview = computed(() => {
  const t = props.templates.find((x) => x.id === addTemplateId.value);
  const inIt = new Set((t?.checkpoints ?? []).map((cp) => cp.id));
  const ids = [...selected.value];
  const already = ids.filter((id) => inIt.has(id)).length;
  return { adding: ids.length - already, already };
});
function openAddToTemplate() {
  addTemplateId.value = props.templates.length === 1 ? props.templates[0].id : null;
  showAdd.value = true;
}
function applyAdd() {
  saving.value = true;
  router.post('/admin/checkpoints/add-to-template', { template_id: addTemplateId.value, ids: [...selected.value] }, {
    preserveScroll: true,
    onSuccess: () => { showAdd.value = false; selected.value = new Set(); flashToast('Added to the template'); },
    onError: (errs) => toastError(firstError(errs)),
    onFinish: () => { saving.value = false; },
  });
}
</script>

<style scoped>
.ck { display: flex; flex-direction: column; gap: 14px; }
.ck-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ck-search {
  flex: 0 1 300px; min-width: 220px; height: 36px; padding: 0 12px; border: 1px solid var(--border-strong);
  border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 13px;
}
.ck-search:focus { outline: none; border-color: var(--ink); }
.ck-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.ck-pill {
  height: 30px; padding: 0 11px; border-radius: 999px; border: 1px solid var(--border-strong);
  background: var(--surface); color: var(--ink2); font-size: 12px; font-weight: 500; cursor: pointer;
}
.ck-pill span { opacity: 0.6; margin-left: 2px; }
.ck-pill:hover { background: var(--panel); color: var(--ink); }
.ck-pill--on, .ck-pill--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }
.ck-usage { margin-left: auto; display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink3); }
.ck-usage select {
  height: 32px; padding: 0 8px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 12px;
}

.ck-selbar {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 8px 12px;
  border-radius: 10px; background: var(--ink); color: var(--surface); font-size: 13px;
}
.ck-selbar strong { font-weight: 600; }
.ck-selbar button {
  height: 28px; padding: 0 10px; border: 1px solid rgba(255, 255, 255, 0.25); border-radius: 6px;
  background: transparent; color: inherit; font-size: 12px; cursor: pointer;
}
.ck-selbar button:hover { background: rgba(255, 255, 255, 0.1); }
.ck-selbar .ck-selbar-del { color: #fca5a5; }
.ck-selbar .ck-selbar-clear { margin-left: auto; border: 0; color: color-mix(in srgb, var(--surface) 70%, transparent); }

.ck-body { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
.ck-table { flex: 1 1 640px; min-width: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface); }
.ck-row {
  display: grid; grid-template-columns: 20px 64px minmax(0, 1fr) 150px 120px 64px;
  gap: 14px; align-items: center; padding: 11px 16px;
}
.ck-row input[type="checkbox"] { margin: 0; accent-color: var(--accent); }
.ck-head {
  padding: 10px 16px; background: var(--panel); border-bottom: 1px solid var(--border);
  font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3);
}
.ck-item { border-bottom: 1px solid var(--border); cursor: pointer; }
.ck-item:last-of-type { border-bottom: none; }
.ck-item:hover { background: var(--panel); }
.ck-item--open, .ck-item--open:hover { background: var(--accent-soft); box-shadow: inset 3px 0 0 var(--accent); }
.ck-code { font-family: var(--mono, ui-monospace, monospace); font-size: 11px; font-weight: 600; color: var(--ink2); }
.ck-name { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.ck-name-text { font-size: 13px; font-weight: 600; color: var(--ink); }
.ck-name-sub { display: flex; align-items: center; gap: 4px; min-width: 0; }
.ck-cap { flex: none; font-size: 10px; font-weight: 500; padding: 1px 6px; border-radius: 4px; background: var(--panel); color: var(--ink2); }
.ck-item--open .ck-cap { background: var(--surface); }
.ck-desc { min-width: 0; font-size: 11px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ck-cat { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: var(--ink); }
.ck-cat small { font-size: 11px; color: var(--ink3); }
.ck-use { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink2); }
.ck-use-bar { flex: 1; max-width: 60px; height: 4px; border-radius: 2px; background: var(--border); overflow: hidden; }
.ck-use-bar span { display: block; height: 100%; background: var(--accent); }
.ck-unused { color: var(--warn); }
.ck-acts { display: flex; gap: 2px; justify-content: flex-end; }
.ck-act { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--ink2); cursor: pointer; }
.ck-act:hover { background: var(--panel); }
.ck-act--del { color: var(--danger-strong); font-size: 15px; }
.ck-act--del:hover { background: var(--danger-soft); }
.ck-empty { padding: 32px; text-align: center; font-size: 13px; color: var(--ink3); }

.ck-panel {
  flex: 1 1 320px; max-width: 400px; position: sticky; top: 12px;
  display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface);
}
.ck-panel-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px; border-bottom: 1px solid var(--border); font-size: 14px; font-weight: 600; color: var(--ink); }
.ck-x { border: 0; background: none; font-size: 18px; line-height: 1; color: var(--ink3); cursor: pointer; }
.ck-panel-body { display: flex; flex-direction: column; gap: 14px; padding: 16px; }
.ck-usedin { display: flex; flex-direction: column; gap: 6px; padding-top: 12px; border-top: 1px solid var(--border); }
.ck-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.ck-chip { font-size: 12px; padding: 3px 9px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--ink); }
.ck-chip small { color: var(--ink3); }
.ck-muted { font-size: 12px; color: var(--ink4); }
.ck-panel-foot { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border); background: var(--panel); }

.ck-label { font-size: 11px; font-weight: 600; color: var(--ink3); }
.ck-field { display: flex; flex-direction: column; gap: 5px; }
.ck-field select { height: 38px; padding: 0 8px; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 13px; }
.ck-modal-sub { margin: 0 0 14px; font-size: 12px; color: var(--ink3); }
.ck-field + .ck-modal-sub { margin: 10px 0 0; }
.ck-modal-foot { display: flex; justify-content: flex-end; gap: 8px; }
.ck-btn {
  height: 34px; padding: 0 14px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 12px; font-weight: 500; cursor: pointer;
}
.ck-btn:hover:not(:disabled) { background: var(--panel); }
.ck-btn--primary { border-color: var(--ink); background: var(--ink); color: var(--surface); font-weight: 600; }
.ck-btn--primary:hover:not(:disabled) { background: var(--ink); filter: brightness(1.2); }
.ck-btn:disabled { opacity: 0.45; cursor: not-allowed; }

@media (max-width: 900px) {
  .ck-panel { max-width: none; position: static; }
}
</style>
