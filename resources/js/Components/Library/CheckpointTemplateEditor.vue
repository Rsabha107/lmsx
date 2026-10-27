<template>
  <Modal :show="true" max-width="1160px" @close="emit('close')">
    <template #title>
      <span class="cte-kicker">Library · Checkpoint templates</span>
      <span class="cte-title">{{ isNew ? 'New checkpoint template' : 'Edit checkpoint template' }}</span>
    </template>

    <div class="cte">
      <!-- Left: details -->
      <section class="cte-left">
        <div class="cte-field">
          <label class="cte-label" for="cte-name">Template name</label>
          <input id="cte-name" v-model="form.name" autofocus class="cte-input cte-input--lg" placeholder="e.g. Team Arrival - Airport to Hotel" />
          <div class="cte-code">
            <span>Code</span>
            <input v-if="form.editingCode" v-model="form.code" class="cte-code-input" aria-label="Code" @input="onCodeInput" />
            <template v-else>
              <span class="cte-code-chip">{{ form.code || '—' }}</span>
              <button type="button" class="cte-link" @click="form.editingCode = true">Edit</button>
            </template>
            <span class="cte-code-hint">{{ form.codeEdited ? 'Custom' : 'Auto-generated' }}</span>
          </div>
          <span v-if="err('code')" class="cte-err">{{ err('code') }}</span>
          <span v-if="err('name')" class="cte-err">{{ err('name') }}</span>
        </div>

        <div class="cte-field">
          <span id="cte-type" class="cte-label">Movement type</span>
          <div class="cte-types" role="radiogroup" aria-labelledby="cte-type">
            <button v-for="t in TYPE_ORDER" :key="t" type="button" role="radio" :aria-checked="form.movement_type === t"
              :class="['cte-type-btn', { 'cte-type-btn--on': form.movement_type === t }]" @click="form.movement_type = t">{{ typeLabel(t) }}</button>
          </div>
        </div>

        <div class="cte-field">
          <span class="cte-label">Typical route</span>
          <div class="cte-route">
            <input v-model="form.from_location" class="cte-input" list="cte-locations" placeholder="From" aria-label="Typical route from" />
            <span class="cte-arrow">→</span>
            <input v-model="form.to_location" class="cte-input" list="cte-locations" placeholder="To" aria-label="Typical route to" />
          </div>
          <span class="cte-hint">Used to suggest this template on matching movement legs.</span>
        </div>

        <div class="cte-field">
          <label class="cte-label cte-label--row" for="cte-desc">Description <small>Optional</small></label>
          <textarea id="cte-desc" v-model="form.description" rows="3" class="cte-input cte-textarea" placeholder="When is this template used?" />
        </div>

        <div class="cte-summary">
          <span class="cte-summary-title">Summary</span>
          <div class="cte-summary-nums">
            <div><strong>{{ form.rows.length }}</strong><span>Checkpoints</span></div>
            <div><strong>{{ form.rows.filter((r) => r.required).length }}</strong><span>Required</span></div>
            <div><strong>{{ allTimed ? fmtMinutes(totalMinutes) : '—' }}</strong><span>Total time</span></div>
          </div>
          <span class="cte-hint">Total time becomes the default leg time in movement templates.</span>
        </div>
      </section>

      <!-- Right: sequence -->
      <section class="cte-right">
        <div class="cte-seq-head">
          <strong>Checkpoint sequence</strong>
          <span>Checkpoints run top to bottom. Times are minutes after the previous checkpoint.</span>
        </div>

        <div class="cte-table">
          <div class="cte-row cte-row--head">
            <span>#</span><span>Checkpoint</span><span class="cte-right-al">Clock</span><span>Est. mins</span><span>Required</span>
          </div>
          <div v-if="!form.rows.length" class="cte-empty">No checkpoints yet — add them from the list below.</div>
          <div v-for="(r, i) in form.rows" :key="r.uid" class="cte-row">
            <span class="cte-n">{{ i + 1 }}</span>
            <div class="cte-cp">
              <div class="cte-cp-text">
                <span class="cte-cp-name">{{ cpOf(r)?.name ?? `#${r.checkpoint_id}` }}</span>
                <small><span class="cte-mono">{{ cpOf(r)?.code }}</span> · {{ categoryLabel(cpOf(r)?.category) }}</small>
              </div>
              <div class="cte-cp-tools">
                <button type="button" :disabled="i === 0" title="Move up" :aria-label="`Move step ${i + 1} up`" @click="move(i, -1)">↑</button>
                <button type="button" :disabled="i === form.rows.length - 1" title="Move down" :aria-label="`Move step ${i + 1} down`" @click="move(i, 1)">↓</button>
                <button type="button" class="cte-del" title="Remove" :aria-label="`Remove step ${i + 1}`" @click="form.rows.splice(i, 1)">×</button>
              </div>
            </div>
            <span class="cte-clock">{{ clocks[i] }}</span>
            <div class="cte-stepper">
              <button type="button" :aria-label="`Step ${i + 1} 5 minutes less`" @click="r.mins = Math.max(0, (Number(r.mins) || 0) - 5)">−</button>
              <input :value="r.mins" inputmode="numeric" :aria-label="`Step ${i + 1} minutes`" @input="r.mins = $event.target.value.replace(/\D/g, '')" />
              <button type="button" :aria-label="`Step ${i + 1} 5 minutes more`" @click="r.mins = (Number(r.mins) || 0) + 5">+</button>
            </div>
            <button type="button" role="switch" :aria-checked="r.required" :aria-label="`Step ${i + 1} required`" class="cte-switch" @click="r.required = !r.required">
              <span :class="['cte-track', { 'cte-track--on': r.required }]"><span class="cte-knob" /></span>{{ r.required ? 'Required' : 'Optional' }}
            </button>
          </div>
        </div>

        <div class="cte-add">
          <div class="cte-add-head">
            <strong>Add checkpoints</strong>
            <span>{{ available }} available</span>
          </div>
          <input v-model="q" class="cte-input" type="search" placeholder="Search by name or code…" aria-label="Search checkpoints to add" />
          <div class="cte-pills">
            <button v-for="c in categoryPills" :key="c.value" type="button" :class="['cte-pill', { 'cte-pill--on': cat === c.value }]" @click="cat = c.value">{{ c.label }}</button>
          </div>
          <div class="cte-results">
            <div v-for="cp in results" :key="cp.id" class="cte-result">
              <div class="cte-cp-text">
                <span :class="['cte-cp-name', { 'cte-dim': added.has(cp.id) }]">{{ cp.name }}</span>
                <small><span class="cte-mono">{{ cp.code }}</span> · {{ categoryLabel(cp.category) }}</small>
              </div>
              <button type="button" class="cte-add-btn" :disabled="added.has(cp.id)" :aria-label="added.has(cp.id) ? `${cp.name} added` : `Add ${cp.name}`" @click="addRow(cp.id)">
                {{ added.has(cp.id) ? 'Added ✓' : '+ Add' }}
              </button>
            </div>
            <div v-if="!results.length" class="cte-noresults">
              No checkpoints match.
              <button v-if="q.trim()" type="button" class="cte-link" :disabled="creating" @click="createCheckpoint">Create “{{ q.trim() }}” as a new checkpoint</button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <datalist id="cte-locations">
      <option v-for="loc in locations" :key="loc" :value="loc" />
    </datalist>

    <template #footer>
      <div class="cte-foot">
        <span :class="['cte-status', statusTone]">{{ status }}</span>
        <div class="cte-foot-btns">
          <button type="button" class="cte-btn" @click="emit('close')">Cancel</button>
          <button type="button" class="cte-btn cte-btn--primary" :disabled="blocking.length > 0 || saving" @click="save()">
            {{ saving ? 'Saving…' : (isNew ? 'Create template' : 'Save changes') }}
          </button>
        </div>
      </div>
    </template>
  </Modal>

  <ConfirmModal :show="!!impactConfirm" :title="impactConfirm?.title" :message="impactConfirm?.message ?? ''" :note="impactConfirm?.note"
    confirm-label="Save changes" :processing="saving" @close="impactConfirm = null" @confirm="save(true)" />
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '../Modal.vue';
import { TYPE_ORDER, typeLabel, isRequired, fmtMinutes } from './checkpointTemplateFormat';
import { CATEGORIES, categoryLabel } from './checkpointOptions';
import { useToast } from '../../Composables/useToast';
import { useTemplateImpact } from '../../Composables/useTemplateImpact';
import ConfirmModal from '../ConfirmModal.vue';

// New / edit checkpoint template: details on the left, the checkpoint sequence on the right.
const props = defineProps({
  // null for a new template.
  template: { type: Object, default: null },
  // The global checkpoint library.
  checkpoints: { type: Array, default: () => [] },
  // The event's checkpoint and movement templates: taken codes and known locations.
  templates: { type: Array, default: () => [] },
  movementTemplates: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved']);

const page = usePage();
const { error: toastError } = useToast();
const isNew = computed(() => !props.template);
let uid = 0;

function initial() {
  const t = props.template;
  return {
    name: t?.name ?? '',
    code: t?.code ?? '',
    codeEdited: !!t,
    editingCode: false,
    movement_type: t?.movement_type || 'arrival',
    from_location: t?.from_location ?? '',
    to_location: t?.to_location ?? '',
    description: t?.description ?? '',
    rows: (t?.checkpoints ?? []).map((cp) => ({
      uid: ++uid, checkpoint_id: cp.id, required: isRequired(cp), mins: cp.pivot?.estimated_minutes ?? '',
    })),
  };
}
const form = reactive(initial());

const cpById = computed(() => Object.fromEntries(props.checkpoints.map((cp) => [cp.id, cp])));
const cpOf = (r) => cpById.value[r.checkpoint_id] ?? null;

// Running clock: blank from the first step without minutes.
const clocks = computed(() => {
  let total = 0;
  let known = true;
  return form.rows.map((r) => {
    if (r.mins === '' || r.mins == null) known = false;
    else total += Number(r.mins);
    return known ? `T+${total}` : '—';
  });
});
const allTimed = computed(() => form.rows.length > 0 && form.rows.every((r) => r.mins !== '' && r.mins != null));
const totalMinutes = computed(() => form.rows.reduce((sum, r) => sum + (Number(r.mins) || 0), 0));

function move(i, d) {
  const j = i + d;
  [form.rows[i], form.rows[j]] = [form.rows[j], form.rows[i]];
}
function addRow(id) {
  if (!added.value.has(id)) form.rows.push({ uid: ++uid, checkpoint_id: id, required: true, mins: 5 });
}

// Add checkpoints
const q = ref('');
const cat = ref('all');
const added = computed(() => new Set(form.rows.map((r) => r.checkpoint_id)));
const available = computed(() => props.checkpoints.filter((cp) => !added.value.has(cp.id)).length);
const categoryPills = computed(() => [
  { value: 'all', label: 'All' },
  ...CATEGORIES.filter((c) => props.checkpoints.some((cp) => cp.category === c)).map((c) => ({ value: c, label: categoryLabel(c) })),
]);
const results = computed(() => {
  const term = q.value.trim().toLowerCase();
  return props.checkpoints.filter((cp) => (cat.value === 'all' || cp.category === cat.value)
    && (!term || (cp.name || '').toLowerCase().includes(term) || (cp.code || '').toLowerCase().includes(term)));
});

// "Create … as a new checkpoint": adds it to the library, then to this sequence.
const creating = ref(false);
function createCheckpoint() {
  creating.value = true;
  router.post('/admin/checkpoints', {
    name: q.value.trim(), description: '', category: cat.value === 'all' ? 'Logistics' : cat.value,
    type: 'dispatch', capture_method: 'manual', requires_photo: false, requires_signature: false, requires_baggage_count: false, is_active: true,
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      const made = [...props.checkpoints].sort((a, b) => b.id - a.id)[0];
      if (made && !added.value.has(made.id)) addRow(made.id);
      q.value = '';
    },
    onError: (errs) => toastError(Object.values(errs)[0] ?? 'Could not create the checkpoint.'),
    onFinish: () => { creating.value = false; },
  });
}

const locations = computed(() => [...new Set([
  ...props.movementTemplates.flatMap((t) => (t.legs ?? []).flatMap((l) => [l.from_location, l.to_location])),
  ...props.templates.flatMap((t) => [t.from_location, t.to_location]),
].map((v) => (v || '').trim()).filter(Boolean))].sort());

// Code: generated from the type and the route until it is edited by hand.
const TYPE_CODES = { arrival: 'ARR', departure: 'DEP', match: 'MD', training: 'TRN', transfer: 'TRF', daily_ops: 'OPS' };
const takenCodes = computed(() => new Set(props.templates.filter((t) => t.id !== props.template?.id).map((t) => (t.code || '').toUpperCase())));
function stopCode(stop) {
  const words = (stop || '').trim().toUpperCase().replace(/[^A-Z0-9 ]/g, ' ').split(/\s+/).filter(Boolean);
  if (!words.length) return '';
  return words.length > 1 ? words.slice(0, 3).map((w) => w[0]).join('') : words[0].slice(0, 3);
}
function autoCode() {
  const base = ['TMPL', TYPE_CODES[form.movement_type] ?? 'GEN', stopCode(form.from_location), stopCode(form.to_location)].filter(Boolean).join('-');
  let code = base;
  for (let n = 2; takenCodes.value.has(code); n++) code = `${base}-${n}`;
  return code;
}
watch(() => [form.movement_type, form.from_location, form.to_location, form.codeEdited], () => {
  if (!form.codeEdited) form.code = autoCode();
}, { immediate: true });
function onCodeInput() {
  form.code = form.code.toUpperCase();
  form.codeEdited = true;
}

// Status and save
const errors = ref({});
const err = (key) => { const e = errors.value[key]; return Array.isArray(e) ? e[0] : e; };
const blocking = computed(() => {
  if (!form.name.trim()) return ['Add a template name'];
  if (!form.code.trim()) return ['Add a code'];
  if (!form.rows.length) return ['Add at least one checkpoint'];
  return [];
});
const serverError = computed(() => Object.values(errors.value).map((e) => (Array.isArray(e) ? e[0] : e))[0] ?? '');
const status = computed(() => serverError.value || blocking.value[0] || (isNew.value ? 'Ready to create' : 'Ready to save'));
const statusTone = computed(() => (serverError.value ? 'cte-status--bad' : (blocking.value.length ? 'cte-status--warn' : '')));
watch(() => [form.name, form.code], () => { errors.value = {}; });

// Steps as saved, to tell a steps edit (asks first when the template is in use) from a details edit.
const stepsKey = () => JSON.stringify(form.rows.map((r) => [r.checkpoint_id, !!r.required, Number(r.mins) || null]));
const savedSteps = stepsKey();
const { confirm: impactConfirm, check: checkImpact } = useTemplateImpact('checkpoint');

const saving = ref(false);
async function save(confirmed = false) {
  if (blocking.value.length) return;
  if (!isNew.value && !confirmed && stepsKey() !== savedSteps) {
    saving.value = true;
    const go = await checkImpact(props.template.id);
    saving.value = false;
    if (!go) return;
  }
  impactConfirm.value = null;
  saving.value = true;
  errors.value = {};
  const payload = {
    code: form.code.trim().toUpperCase(),
    name: form.name.trim(),
    movement_type: form.movement_type,
    description: form.description.trim() || null,
    from_location: form.from_location.trim() || null,
    to_location: form.to_location.trim() || null,
    // The steps' minutes add up to the template's time; keep the old figure when some are blank.
    estimated_duration_minutes: allTimed.value && totalMinutes.value ? totalMinutes.value : (props.template?.estimated_duration_minutes ?? null),
    is_active: props.template?.is_active ?? true,
    checkpoints: form.rows.map((r, i) => ({
      checkpoint_id: r.checkpoint_id,
      order: i + 1,
      is_required: r.required,
      estimated_minutes: Number(r.mins) || null,
    })),
  };
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      if (page.props.flash?.error) errors.value = { error: page.props.flash.error };
      else emit('saved');
    },
    onError: (errs) => { errors.value = errs; },
    onFinish: () => { saving.value = false; },
  };
  if (isNew.value) router.post('/admin/checkpoint-templates', payload, options);
  else router.put(`/admin/checkpoint-templates/${props.template.id}`, payload, options);
}
</script>

<style scoped>
.cte-kicker { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); }
.cte-title { display: block; margin-top: 2px; font-size: 18px; font-weight: 600; color: var(--ink); }

/* Edge to edge inside the dialog body; each column scrolls on its own. */
.cte {
  display: grid; grid-template-columns: minmax(280px, 340px) minmax(0, 1fr); margin: -24px;
  height: min(760px, calc(90vh - 170px));
}
.cte-left {
  display: flex; flex-direction: column; gap: 18px; padding: 22px 24px; overflow-y: auto;
  border-right: 1px solid var(--border); background: var(--panel);
}
.cte-right { display: flex; flex-direction: column; gap: 14px; padding: 22px 24px; min-width: 0; overflow-y: auto; }

.cte-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.cte-label { font-size: 12px; font-weight: 600; color: var(--ink2); }
.cte-label--row { display: flex; justify-content: space-between; }
.cte-label small { font-weight: 400; color: var(--ink4); }
.cte-input {
  width: 100%; min-width: 0; height: 38px; padding: 0 10px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-family: inherit;
}
.cte-input--lg { height: 40px; padding: 0 12px; font-size: 14px; }
.cte-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px var(--accent-ring); }
.cte-textarea { height: auto; padding: 10px 12px; resize: vertical; }
.cte-code { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink3); }
.cte-code-chip { font-family: var(--mono, ui-monospace, monospace); font-weight: 600; color: var(--ink); background: var(--border); padding: 2px 7px; border-radius: 5px; }
.cte-code-input {
  width: 190px; height: 26px; padding: 0 8px; border: 1px solid var(--border-strong); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-family: var(--mono, ui-monospace, monospace); font-size: 12px; text-transform: uppercase;
}
.cte-code-hint { margin-left: auto; font-size: 11px; }
.cte-link { padding: 0; border: 0; background: none; color: var(--accent); font-size: 12px; font-weight: 500; cursor: pointer; }
.cte-err { font-size: 11px; color: var(--danger-strong); }
.cte-hint { font-size: 11px; color: var(--ink4); }

.cte-types { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.cte-type-btn {
  height: 36px; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface);
  color: var(--ink2); font-size: 13px; font-weight: 500; cursor: pointer;
}
.cte-type-btn:hover { color: var(--ink); }
.cte-type-btn--on, .cte-type-btn--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }
.cte-route { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 6px; align-items: center; }
.cte-arrow { color: var(--ink4); }

.cte-summary {
  margin-top: auto; display: flex; flex-direction: column; gap: 12px; padding: 14px;
  border: 1px solid var(--border); border-radius: 10px; background: var(--surface);
}
.cte-summary-title { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); }
.cte-summary-nums { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.cte-summary-nums div { display: flex; flex-direction: column; gap: 2px; }
.cte-summary-nums strong { font-size: 20px; font-weight: 600; color: var(--ink); }
.cte-summary-nums span { font-size: 11px; color: var(--ink3); }

.cte-seq-head { display: flex; flex-direction: column; gap: 2px; }
.cte-seq-head strong { font-size: 15px; font-weight: 600; color: var(--ink); }
.cte-seq-head span { font-size: 12px; color: var(--ink3); }

.cte-table { flex: none; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.cte-row {
  display: grid; grid-template-columns: 28px minmax(0, 1fr) 64px 108px 112px; gap: 12px; align-items: center;
  padding: 10px 14px; border-top: 1px solid var(--border); background: var(--surface);
}
.cte-row--head {
  padding: 8px 14px; border-top: 0; background: var(--panel);
  font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3);
}
.cte-right-al { text-align: right; }
.cte-empty { padding: 28px 14px; text-align: center; font-size: 13px; color: var(--ink3); border-top: 1px solid var(--border); }
.cte-n { display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: var(--accent); color: #fff; font-size: 11px; font-weight: 600; }
.cte-cp { display: flex; align-items: center; gap: 8px; min-width: 0; }
.cte-cp-text { display: flex; flex: 1; flex-direction: column; min-width: 0; }
.cte-cp-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.cte-cp-text small { font-size: 11px; color: var(--ink4); }
.cte-dim { color: var(--ink4); }
.cte-mono { font-family: var(--mono, ui-monospace, monospace); }
.cte-cp-tools { display: flex; gap: 2px; }
.cte-cp-tools button { width: 26px; height: 26px; border: 0; border-radius: 6px; background: transparent; color: var(--ink3); cursor: pointer; }
.cte-cp-tools button:hover:not(:disabled) { background: var(--panel); color: var(--ink); }
.cte-cp-tools button:disabled { opacity: 0.35; cursor: default; }
.cte-cp-tools .cte-del { color: var(--danger-strong); font-size: 15px; }
.cte-cp-tools .cte-del:hover { background: var(--danger-soft); }
.cte-clock { font-family: var(--mono, ui-monospace, monospace); font-size: 12px; text-align: right; color: var(--ink3); }
.cte-stepper { display: flex; align-items: center; height: 32px; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface); overflow: hidden; }
.cte-stepper button { width: 26px; height: 100%; border: 0; background: transparent; color: var(--ink3); cursor: pointer; }
.cte-stepper button:hover { background: var(--panel); color: var(--ink); }
.cte-stepper input { flex: 1; min-width: 0; height: 100%; border: 0; background: transparent; color: var(--ink); text-align: center; font-size: 13px; }
.cte-stepper input:focus { outline: none; }
.cte-switch { display: flex; align-items: center; gap: 8px; padding: 0; border: 0; background: none; color: var(--ink2); font-size: 12px; cursor: pointer; }
.cte-track { position: relative; display: block; flex: none; width: 32px; height: 18px; border-radius: 999px; background: var(--border-strong); transition: background 0.15s; }
.cte-track--on { background: var(--accent); }
.cte-knob { position: absolute; top: 2px; left: 2px; width: 14px; height: 14px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.2); transition: left 0.15s; }
.cte-track--on .cte-knob { left: 16px; }
.cte-switch:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }

.cte-add {
  flex: none; display: flex; flex-direction: column; gap: 10px; padding: 14px;
  border: 1px dashed var(--border-strong); border-radius: 12px; background: color-mix(in srgb, var(--panel) 40%, var(--surface));
}
.cte-add-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.cte-add-head strong { font-size: 13px; font-weight: 600; color: var(--ink); }
.cte-add-head span { font-size: 11px; color: var(--ink4); }
.cte-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.cte-pill {
  height: 28px; padding: 0 10px; border-radius: 999px; border: 1px solid var(--border-strong);
  background: var(--surface); color: var(--ink2); font-size: 12px; font-weight: 500; cursor: pointer;
}
.cte-pill:hover { color: var(--ink); background: var(--panel); }
.cte-pill--on, .cte-pill--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }
.cte-results {
  display: flex; flex-direction: column; min-height: 120px; max-height: 228px; overflow: auto;
  border: 1px solid var(--border); border-radius: 8px; background: var(--surface);
}
.cte-result { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--border); }
.cte-result:last-of-type { border-bottom: 0; }
.cte-result .cte-cp-name { font-size: 12px; }
.cte-add-btn {
  flex: none; height: 28px; padding: 0 10px; border: 1px solid var(--border-strong); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12px; font-weight: 500; cursor: pointer;
}
.cte-add-btn:hover:not(:disabled) { background: var(--panel); }
.cte-add-btn:disabled { border-color: var(--border); color: var(--ink4); cursor: default; }
.cte-noresults { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 16px; font-size: 12px; color: var(--ink3); }

.cte-foot { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.cte-status { font-size: 12px; color: var(--ink3); }
.cte-status--warn { color: var(--warn); }
.cte-status--bad { color: var(--danger-strong); }
.cte-foot-btns { display: flex; gap: 8px; }
.cte-btn {
  height: 38px; padding: 0 16px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-weight: 500; cursor: pointer;
}
.cte-btn:hover:not(:disabled) { background: var(--panel); }
.cte-btn--primary { padding: 0 18px; border-color: var(--ink); background: var(--ink); color: var(--surface); font-weight: 600; }
.cte-btn--primary:hover:not(:disabled) { background: var(--ink); filter: brightness(1.2); }
.cte-btn:disabled { opacity: 0.45; cursor: not-allowed; }

@media (max-width: 860px) {
  .cte { grid-template-columns: 1fr; height: auto; }
  .cte-left, .cte-right { overflow: visible; }
  .cte-left { border-right: 0; border-bottom: 1px solid var(--border); }
}
</style>
