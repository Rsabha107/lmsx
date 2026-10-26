<template>
  <Modal :show="true" max-width="1160px" @close="emit('close')">
    <template #title>
      <span class="mte-kicker">Library · Movement templates</span>
      <span class="mte-title">{{ isNew ? 'New movement template' : 'Edit movement template' }}</span>
    </template>

    <div class="mte">
      <!-- Left: details -->
      <section class="mte-left">
        <div class="mte-field">
          <label class="mte-label" for="mte-name">Template name</label>
          <input id="mte-name" v-model="form.name" autofocus class="mte-input mte-input--lg" placeholder="e.g. Team Match Day to Hotel" />
          <div class="mte-code">
            <span>Code</span>
            <input v-if="form.editingCode" v-model="form.code" class="mte-code-input" aria-label="Code" @input="onCodeInput" />
            <template v-else>
              <span class="mte-code-chip">{{ form.code || '—' }}</span>
              <button type="button" class="mte-link" @click="form.editingCode = true">Edit</button>
            </template>
            <span class="mte-code-hint">{{ form.codeEdited ? 'Custom' : 'Auto-generated' }}</span>
          </div>
          <span v-if="err('code')" class="mte-err">{{ err('code') }}</span>
          <span v-if="err('name')" class="mte-err">{{ err('name') }}</span>
        </div>

        <div class="mte-field">
          <span class="mte-label" id="mte-scen">Scenario</span>
          <div class="mte-scen" role="radiogroup" aria-labelledby="mte-scen">
            <button v-for="s in SCENARIOS" :key="s.value" type="button" role="radio" :aria-checked="form.scenario_type === s.value"
              :class="['mte-scen-btn', { 'mte-scen-btn--on': form.scenario_type === s.value }]" @click="form.scenario_type = s.value">{{ s.label }}</button>
          </div>
        </div>

        <div class="mte-field">
          <label class="mte-label" for="mte-area">Functional area</label>
          <select id="mte-area" v-model="form.functional_area" class="mte-input">
            <option value="">Not set</option>
            <option value="LOG">LOG – Logistics</option>
            <option value="AND">AND – Arrival and Departure</option>
            <option value="MOB">MOB – Mobility</option>
          </select>
        </div>

        <div class="mte-field">
          <label class="mte-label mte-label--row" for="mte-desc">Description <small>Optional</small></label>
          <textarea id="mte-desc" v-model="form.description" rows="3" class="mte-input mte-textarea" placeholder="When is this template used?" />
        </div>

        <div class="mte-summary">
          <span class="mte-summary-title">Summary</span>
          <div class="mte-summary-nums">
            <div><strong>{{ form.legs.length }}</strong><span>Legs</span></div>
            <div><strong>{{ fmt(totalMinutes) }}</strong><span>Est. duration</span></div>
            <div><strong>{{ totalCheckpoints }}</strong><span>Checkpoints</span></div>
          </div>
          <div v-if="stops.length" class="mte-stops">
            <template v-for="(stop, i) in stops" :key="i">
              <span v-if="i" class="mte-arrow">→</span><span class="mte-stop">{{ stop }}</span>
            </template>
          </div>
          <span class="mte-hint">Duration is calculated from leg times.</span>
        </div>
      </section>

      <!-- Right: route builder -->
      <section class="mte-right">
        <div class="mte-route-head">
          <strong>Route</strong>
          <span>Each leg starts where the previous one ends. Pick a checkpoint template to attach its checkpoints.</span>
        </div>

        <div class="mte-legs">
          <div v-for="(leg, i) in form.legs" :key="leg.uid" class="mte-leg">
            <div class="mte-rail">
              <span class="mte-rail-dot">{{ i + 1 }}</span>
              <span class="mte-rail-line" />
            </div>
            <div :class="['mte-card', { 'mte-card--open': form.openUid === leg.uid }]">
              <div class="mte-card-head" role="button" tabindex="0" :aria-expanded="form.openUid === leg.uid"
                @click="toggle(leg)" @keydown.enter.self.prevent="toggle(leg)" @keydown.space.self.prevent="toggle(leg)">
                <div class="mte-card-title">
                  <span class="mte-card-route">
                    {{ leg.from || '—' }} → {{ leg.to || '—' }}
                    <span :class="['mte-phase', `mte-phase--${leg.leg_type}`]">{{ phaseLabel(leg.leg_type) }}</span>
                  </span>
                  <span class="mte-card-sub">{{ legSub(leg) }}</span>
                </div>
                <span v-if="legIssue(leg, i)" :class="['mte-issue', { 'mte-issue--block': !ctOf(leg) }]">{{ legIssue(leg, i) }}</span>
                <div class="mte-card-tools" @click.stop>
                  <button type="button" :disabled="i === 0" title="Move up" :aria-label="`Move leg ${i + 1} up`" @click="move(i, -1)">↑</button>
                  <button type="button" :disabled="i === form.legs.length - 1" title="Move down" :aria-label="`Move leg ${i + 1} down`" @click="move(i, 1)">↓</button>
                  <button type="button" title="Duplicate" :aria-label="`Duplicate leg ${i + 1}`" @click="duplicateLeg(i)">⧉</button>
                  <button type="button" class="mte-del" title="Remove leg" :aria-label="`Remove leg ${i + 1}`" @click="removeLeg(i)">×</button>
                </div>
                <span class="mte-chev" aria-hidden="true">{{ form.openUid === leg.uid ? '▴' : '▾' }}</span>
              </div>

              <div v-if="form.openUid === leg.uid" class="mte-card-body">
                <div class="mte-fromto">
                  <label class="mte-field">
                    <span class="mte-label mte-label--sm">From</span>
                    <input v-model="leg.from" class="mte-input" list="mte-locations" placeholder="Choose location…" :aria-label="`Leg ${i + 1} from`" />
                  </label>
                  <button type="button" class="mte-swap" title="Swap" :aria-label="`Swap leg ${i + 1} from and to`" @click="swap(leg)">⇄</button>
                  <label class="mte-field">
                    <span class="mte-label mte-label--sm">To</span>
                    <input v-model="leg.to" class="mte-input" list="mte-locations" placeholder="Choose location…" :aria-label="`Leg ${i + 1} to`" />
                  </label>
                </div>

                <div class="mte-field">
                  <span class="mte-label mte-label--sm">Transport</span>
                  <div class="mte-seg" role="radiogroup" :aria-label="`Leg ${i + 1} transport`">
                    <button v-for="t in TRANSPORTS" :key="t" type="button" role="radio" :aria-checked="leg.transport_type === t"
                      :class="['mte-seg-btn', { 'mte-seg-btn--on': leg.transport_type === t }]" @click="leg.transport_type = t">{{ cap(t) }}</button>
                  </div>
                </div>

                <div class="mte-two">
                  <label class="mte-field">
                    <span class="mte-label mte-label--sm">Phase</span>
                    <select v-model="leg.leg_type" class="mte-input" :aria-label="`Leg ${i + 1} phase`">
                      <option v-for="p in PHASES" :key="p" :value="p">{{ phaseLabel(p) }}</option>
                    </select>
                  </label>
                  <div class="mte-field">
                    <span class="mte-label mte-label--sm">Est. time (min)</span>
                    <div class="mte-stepper">
                      <button type="button" :aria-label="`Leg ${i + 1} 5 minutes less`" @click="leg.mins = Math.max(0, (Number(leg.mins) || 0) - 5)">−</button>
                      <input :value="leg.mins" inputmode="numeric" :aria-label="`Leg ${i + 1} minutes`" @input="leg.mins = $event.target.value.replace(/\D/g, '')" />
                      <button type="button" :aria-label="`Leg ${i + 1} 5 minutes more`" @click="leg.mins = (Number(leg.mins) || 0) + 5">+</button>
                    </div>
                    <span v-if="Number(leg.mins) > 60" class="mte-hint">= {{ fmt(leg.mins) }}</span>
                  </div>
                </div>

                <div class="mte-field">
                  <span class="mte-label mte-label--sm">Checkpoint template</span>
                  <select :value="leg.checkpoint_template_id ?? ''" :class="['mte-input', { 'mte-input--warn': !leg.checkpoint_template_id }]"
                    :aria-label="`Leg ${i + 1} checkpoint template`" @change="pickTemplate(leg, $event.target.value)">
                    <option value="">Select a checkpoint template…</option>
                    <optgroup v-if="templateGroups.match.length" :label="`${scenarioLabel} templates`">
                      <option v-for="ct in templateGroups.match" :key="ct.id" :value="ct.id">{{ ctOption(ct) }}</option>
                    </optgroup>
                    <optgroup v-if="templateGroups.other.length" :label="templateGroups.match.length ? 'Other movement types' : 'Checkpoint templates'">
                      <option v-for="ct in templateGroups.other" :key="ct.id" :value="ct.id">{{ ctOption(ct) }}</option>
                    </optgroup>
                  </select>

                  <div v-if="ctOf(leg)" class="mte-cps">
                    <div class="mte-cps-head">
                      <span class="mte-mono">{{ ctOf(leg).code }}</span>
                      <span>{{ ctMeta(ctOf(leg)) }}</span>
                    </div>
                    <div v-for="(cp, k) in ctOf(leg).checkpoints ?? []" :key="cp.id" class="mte-cp">
                      <span class="mte-cp-n">{{ k + 1 }}</span>
                      <span class="mte-cp-name">{{ cp.name }}<small class="mte-mono">{{ cp.code }}</small></span>
                      <span :class="['mte-req', { 'mte-req--opt': !isRequired(cp) }]">{{ isRequired(cp) ? 'Required' : 'Optional' }}</span>
                      <span class="mte-cp-min">{{ cp.pivot?.estimated_minutes != null ? `${cp.pivot.estimated_minutes} min` : '—' }}</span>
                    </div>
                    <p v-if="!(ctOf(leg).checkpoints ?? []).length" class="mte-hint mte-cps-empty">This template has no checkpoints yet.</p>
                  </div>
                  <div v-if="ctOf(leg) && ctTotal(ctOf(leg)) && Number(leg.mins) !== ctTotal(ctOf(leg))" class="mte-differ">
                    Leg time differs from the template total ({{ ctTotal(ctOf(leg)) }} min).
                    <button type="button" class="mte-link" @click="leg.mins = ctTotal(ctOf(leg))">Use {{ ctTotal(ctOf(leg)) }} min</button>
                  </div>
                  <button v-if="suggestionFor(leg)" type="button" class="mte-suggest" @click="pickTemplate(leg, suggestionFor(leg).id)">
                    Suggested for this route: {{ suggestionFor(leg).name }} — use it
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="mte-leg mte-leg--add">
            <div class="mte-rail"><span class="mte-rail-dot mte-rail-dot--ghost" /></div>
            <div class="mte-add">
              <button type="button" class="mte-add-btn" @click="addLeg"><span>+</span>{{ lastLeg?.to ? `Add leg from ${lastLeg.to}` : 'Add leg' }}</button>
              <button v-if="canReturn" type="button" class="mte-return-btn" @click="addReturn">↩ Add return leg · {{ lastLeg.to }} → {{ lastLeg.from }}</button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <datalist id="mte-locations">
      <option v-for="loc in locations" :key="loc" :value="loc" />
    </datalist>

    <template #footer>
      <div class="mte-foot">
        <span :class="['mte-status', statusTone]">{{ status }}</span>
        <div class="mte-foot-btns">
          <button type="button" class="mte-btn" @click="emit('close')">Cancel</button>
          <button type="button" class="mte-btn mte-btn--primary" :disabled="blocking.length > 0 || saving" @click="save">
            {{ saving ? 'Saving…' : (isNew ? 'Create template' : 'Save changes') }}
          </button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '../Modal.vue';
import { legMinutes } from '../../Composables/planFormat';

// New / edit movement template: details on the left, the route (legs) on the right.
const props = defineProps({
  // null for a new template.
  template: { type: Object, default: null },
  checkpointTemplates: { type: Array, default: () => [] },
  // The event's movement templates: known locations, codes and route suggestions.
  movementTemplates: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved']);

const SCENARIOS = [
  { value: 'arrival_day', label: 'Arrival', code: 'ARR', ct: 'arrival' },
  { value: 'match_day', label: 'Match', code: 'MD', ct: 'match' },
  { value: 'training_day', label: 'Training', code: 'TRN', ct: 'training' },
  { value: 'departure_day', label: 'Departure', code: 'DEP', ct: 'departure' },
  { value: 'operation_day', label: 'Operations', code: 'OPS', ct: 'daily_ops' },
  { value: 'full_day', label: 'Full day', code: 'FULL', ct: null },
  { value: 'custom', label: 'Custom', code: 'GEN', ct: null },
];
const PHASES = ['transfer', 'arrival', 'departure', 'match', 'training', 'daily_ops'];
const TRANSPORTS = ['bus', 'coach', 'van', 'car', 'walk', 'other'];
const cap = (s) => (s ? s[0].toUpperCase() + s.slice(1).replace(/_/g, ' ') : '');
const phaseLabel = (p) => (p === 'daily_ops' ? 'Daily ops' : cap(p));
const fmt = (m) => {
  const n = Number(m) || 0;
  if (!n) return '0m';
  const h = Math.floor(n / 60);
  return h ? `${h}h${n % 60 ? ` ${n % 60}m` : ''}` : `${n}m`;
};

const isNew = computed(() => !props.template);
let uid = 0;
const newLeg = (from = '', to = '') => ({ uid: ++uid, from, to, checkpoint_template_id: null, leg_type: 'transfer', transport_type: 'bus', mins: 30 });

function initial() {
  const t = props.template;
  if (!t) {
    const leg = newLeg();
    return { name: '', code: '', codeEdited: false, editingCode: false, scenario_type: 'match_day', functional_area: '', description: '', legs: [leg], openUid: leg.uid };
  }
  const legs = (t.legs ?? []).map((l) => ({
    uid: ++uid,
    from: l.from_location ?? '',
    to: l.to_location ?? '',
    checkpoint_template_id: l.checkpoint_template_id ?? null,
    leg_type: l.leg_type || 'transfer',
    transport_type: TRANSPORTS.includes(l.transport_type) ? l.transport_type : 'bus',
    // Spell out each leg's time; together they become the template's duration.
    mins: legMinutes(t, l),
  }));
  return {
    name: t.name ?? '', code: t.code ?? '', codeEdited: true, editingCode: false,
    scenario_type: t.scenario_type || 'custom', functional_area: t.functional_area || '', description: t.description || '',
    legs, openUid: legs[0]?.uid ?? null,
  };
}
const form = reactive(initial());

// Checkpoint templates
const ctById = computed(() => Object.fromEntries(props.checkpointTemplates.map((ct) => [ct.id, ct])));
const ctOf = (leg) => ctById.value[leg.checkpoint_template_id] ?? null;
const isRequired = (cp) => cp.pivot?.is_required !== false && cp.pivot?.is_required !== 0;
function ctTotal(ct) {
  const cps = ct?.checkpoints ?? [];
  if (!cps.length || cps.some((cp) => cp.pivot?.estimated_minutes == null)) return null;
  return cps.reduce((sum, cp) => sum + Number(cp.pivot.estimated_minutes), 0);
}
const ctCount = (ct) => ct?.checkpoint_count ?? ct?.checkpoints?.length ?? 0;
const ctOption = (ct) => [ct.name, `${ctCount(ct)} checkpoints`, ctTotal(ct) ? `${ctTotal(ct)} min` : null].filter(Boolean).join(' · ');
const ctMeta = (ct) => `${phaseLabel(ct.movement_type)} · ${ctCount(ct)} checkpoints · ${(ct.checkpoints ?? []).filter(isRequired).length} required`;
const scenario = computed(() => SCENARIOS.find((s) => s.value === form.scenario_type) ?? SCENARIOS[SCENARIOS.length - 1]);
const scenarioLabel = computed(() => scenario.value.label);
const templateGroups = computed(() => {
  const want = scenario.value.ct;
  const list = [...props.checkpointTemplates].sort((a, b) => (a.name || '').localeCompare(b.name || ''));
  return want
    ? { match: list.filter((ct) => ct.movement_type === want), other: list.filter((ct) => ct.movement_type !== want) }
    : { match: [], other: list };
});

function pickTemplate(leg, value) {
  leg.checkpoint_template_id = value ? Number(value) : null;
  const total = ctTotal(ctOf(leg));
  if (total) leg.mins = total;
}

// A checkpoint template another movement template already uses for the same route.
const routeKey = (from, to) => `${(from || '').trim().toLowerCase()}→${(to || '').trim().toLowerCase()}`;
const usedForRoute = computed(() => {
  const counts = {};
  for (const t of props.movementTemplates) {
    if (t.id === props.template?.id) continue;
    for (const l of t.legs ?? []) {
      if (!l.from_location || !l.to_location || !ctById.value[l.checkpoint_template_id]) continue;
      const key = routeKey(l.from_location, l.to_location);
      counts[key] ??= {};
      counts[key][l.checkpoint_template_id] = (counts[key][l.checkpoint_template_id] ?? 0) + 1;
    }
  }
  return Object.fromEntries(Object.entries(counts).map(([k, ids]) => [k, Number(Object.entries(ids).sort((a, b) => b[1] - a[1])[0][0])]));
});
function suggestionFor(leg) {
  if (!leg.from || !leg.to) return null;
  const key = routeKey(leg.from, leg.to);
  // A checkpoint template whose typical route is this leg's, else one used for this route before.
  const byRoute = props.checkpointTemplates.find((ct) => ct.from_location && ct.to_location && routeKey(ct.from_location, ct.to_location) === key);
  const id = byRoute?.id ?? usedForRoute.value[key];
  return id && id !== leg.checkpoint_template_id ? ctById.value[id] : null;
}

const locations = computed(() => [...new Set([
  ...props.movementTemplates.flatMap((t) => (t.legs ?? []).flatMap((l) => [l.from_location, l.to_location])),
  ...props.checkpointTemplates.flatMap((ct) => [ct.from_location, ct.to_location]),
  ...form.legs.flatMap((l) => [l.from, l.to]),
].map((v) => (v || '').trim()).filter(Boolean))].sort());

// Legs
const lastLeg = computed(() => form.legs[form.legs.length - 1] ?? null);
const stops = computed(() => (form.legs.length ? [form.legs[0].from, ...form.legs.map((l) => l.to)].map((s) => (s || '').trim()).filter(Boolean) : []));
const totalMinutes = computed(() => form.legs.reduce((sum, l) => sum + (Number(l.mins) || 0), 0));
const totalCheckpoints = computed(() => form.legs.reduce((sum, l) => sum + ctCount(ctOf(l)), 0));
const canReturn = computed(() => !!(lastLeg.value?.from && lastLeg.value?.to && stops.value[0] !== lastLeg.value.to.trim()));
const same = (a, b) => (a || '').trim().toLowerCase() === (b || '').trim().toLowerCase();

const legSub = (leg) => [cap(leg.transport_type), fmt(leg.mins), ctOf(leg) ? `${ctCount(ctOf(leg))} checkpoints` : 'No checkpoints'].join(' · ');
function legIssue(leg, i) {
  if (!ctOf(leg)) return 'No checkpoint template';
  if (!leg.from?.trim() || !leg.to?.trim()) return 'Missing location';
  if (same(leg.from, leg.to)) return 'Same start & end';
  const prev = form.legs[i - 1];
  if (prev?.to && !same(prev.to, leg.from)) return `Gap after ${prev.to}`;
  return '';
}

function toggle(leg) {
  form.openUid = form.openUid === leg.uid ? null : leg.uid;
}
function swap(leg) {
  const from = leg.from;
  leg.from = leg.to;
  leg.to = from;
}
function move(i, d) {
  const j = i + d;
  [form.legs[i], form.legs[j]] = [form.legs[j], form.legs[i]];
}
function duplicateLeg(i) {
  const copy = { ...form.legs[i], uid: ++uid };
  form.legs.splice(i + 1, 0, copy);
  form.openUid = copy.uid;
}
function removeLeg(i) {
  form.legs.splice(i, 1);
}
function pushLeg(from, to) {
  const leg = newLeg(from, to);
  const suggestion = suggestionFor(leg);
  if (suggestion) pickTemplate(leg, suggestion.id);
  form.legs.push(leg);
  form.openUid = leg.uid;
}
const addLeg = () => pushLeg(lastLeg.value?.to ?? '', '');
const addReturn = () => pushLeg(lastLeg.value.to, lastLeg.value.from);

// Code: generated from the scenario and the last stop until it is edited by hand.
const takenCodes = computed(() => new Set(props.movementTemplates.filter((t) => t.id !== props.template?.id).map((t) => (t.code || '').toUpperCase())));
function stopCode(stop) {
  const words = (stop || '').trim().toUpperCase().replace(/[^A-Z0-9 ]/g, ' ').split(/\s+/).filter(Boolean);
  if (!words.length) return '';
  return words.length > 1 ? words.slice(0, 3).map((w) => w[0]).join('') : words[0].slice(0, 3);
}
function autoCode() {
  const base = ['MVT', scenario.value.code, stopCode(lastLeg.value?.to)].filter(Boolean).join('-');
  let code = base;
  for (let n = 2; takenCodes.value.has(code); n++) code = `${base}-${n}`;
  return code;
}
watch(() => [form.scenario_type, lastLeg.value?.to, form.codeEdited], () => {
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
  const list = [];
  if (!form.name.trim()) list.push('Name required');
  if (!form.code.trim()) list.push('Code required');
  if (!form.legs.length) list.push('Add at least one leg');
  const missing = form.legs.filter((l) => !ctOf(l)).length;
  if (missing) list.push(`${missing} leg${missing === 1 ? '' : 's'} without a checkpoint template`);
  return list;
});
const warnings = computed(() => [...new Set(form.legs.map((l, i) => legIssue(l, i)).filter((w) => w && w !== 'No checkpoint template'))]);
const serverError = computed(() => Object.values(errors.value).map((e) => (Array.isArray(e) ? e[0] : e))[0] ?? '');
const status = computed(() => {
  if (serverError.value) return serverError.value;
  if (blocking.value.length) return `${blocking.value.length} to fix: ${blocking.value.join(' · ')}`;
  if (warnings.value.length) return `Ready to save · check: ${warnings.value.join(' · ')}`;
  return 'Ready to save';
});
const statusTone = computed(() => (serverError.value ? 'mte-status--bad' : (blocking.value.length || warnings.value.length ? 'mte-status--warn' : '')));
watch(() => [form.name, form.code], () => { errors.value = {}; });

const saving = ref(false);
function save() {
  if (blocking.value.length) return;
  saving.value = true;
  errors.value = {};
  const payload = {
    code: form.code.trim().toUpperCase(),
    name: form.name.trim(),
    description: form.description.trim() || null,
    scenario_type: form.scenario_type,
    functional_area: form.functional_area || null,
    estimated_duration_minutes: totalMinutes.value || null,
    is_active: props.template?.is_active ?? true,
    legs: form.legs.map((l, i) => ({
      order: i + 1,
      checkpoint_template_id: l.checkpoint_template_id,
      leg_type: l.leg_type,
      from_location: l.from.trim() || null,
      to_location: l.to.trim() || null,
      transport_type: l.transport_type,
      estimated_duration_minutes: Number(l.mins) || null,
    })),
  };
  const options = {
    preserveScroll: true,
    onSuccess: () => emit('saved'),
    onError: (errs) => { errors.value = errs; },
    onFinish: () => { saving.value = false; },
  };
  if (isNew.value) router.post('/admin/movement-templates', payload, options);
  else router.put(`/admin/movement-templates/${props.template.id}`, payload, options);
}
</script>

<style scoped>
.mte-kicker { display: block; font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); }
.mte-title { display: block; margin-top: 2px; font-size: 18px; font-weight: 600; color: var(--ink); }

/* Edge to edge inside the dialog body; each column scrolls on its own. */
.mte {
  display: grid; grid-template-columns: minmax(280px, 340px) minmax(0, 1fr); margin: -24px;
  height: min(760px, calc(90vh - 170px));
}
.mte-left {
  display: flex; flex-direction: column; gap: 18px; padding: 22px 24px; overflow-y: auto;
  border-right: 1px solid var(--border); background: var(--panel);
}
.mte-right { display: flex; flex-direction: column; gap: 14px; padding: 22px 24px; min-width: 0; overflow-y: auto; }

.mte-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.mte-label { font-size: 12px; font-weight: 600; color: var(--ink2); }
.mte-label--sm { font-size: 11px; color: var(--ink3); }
.mte-label--row { display: flex; justify-content: space-between; }
.mte-label small { font-weight: 400; color: var(--ink4); }
.mte-input {
  width: 100%; height: 38px; padding: 0 10px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-family: inherit;
}
.mte-input--lg { height: 40px; padding: 0 12px; font-size: 14px; }
.mte-input:focus { outline: none; border-color: var(--ink); box-shadow: 0 0 0 3px var(--accent-ring); }
.mte-input--warn { border-color: var(--warn); }
.mte-textarea { height: auto; padding: 10px 12px; resize: vertical; }
.mte-code { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink3); }
.mte-code-chip { font-family: var(--mono, ui-monospace, monospace); font-weight: 600; color: var(--ink); background: var(--border); padding: 2px 7px; border-radius: 5px; }
.mte-code-input {
  width: 170px; height: 26px; padding: 0 8px; border: 1px solid var(--border-strong); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-family: var(--mono, ui-monospace, monospace); font-size: 12px; text-transform: uppercase;
}
.mte-code-hint { margin-left: auto; font-size: 11px; }
.mte-link { padding: 0; border: 0; background: none; color: var(--accent); font-size: 12px; font-weight: 500; cursor: pointer; }
.mte-err { font-size: 11px; color: var(--danger-strong); }

.mte-scen { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; }
.mte-scen-btn {
  height: 36px; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface);
  color: var(--ink2); font-size: 13px; font-weight: 500; cursor: pointer;
}
.mte-scen-btn:hover { color: var(--ink); }
.mte-scen-btn--on, .mte-scen-btn--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }

.mte-summary {
  margin-top: auto; display: flex; flex-direction: column; gap: 12px; padding: 14px;
  border: 1px solid var(--border); border-radius: 10px; background: var(--surface);
}
.mte-summary-title { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); }
.mte-summary-nums { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.mte-summary-nums div { display: flex; flex-direction: column; gap: 2px; }
.mte-summary-nums strong { font-size: 20px; font-weight: 600; color: var(--ink); }
.mte-summary-nums span { font-size: 11px; color: var(--ink3); }
.mte-stops { display: flex; flex-wrap: wrap; align-items: center; gap: 4px; font-size: 12px; color: var(--ink2); }
.mte-stop { padding: 2px 8px; border-radius: 999px; background: var(--panel); }
.mte-arrow { color: var(--ink4); }
.mte-hint { font-size: 11px; color: var(--ink4); }

.mte-route-head { display: flex; flex-direction: column; gap: 2px; }
.mte-route-head strong { font-size: 15px; font-weight: 600; color: var(--ink); }
.mte-route-head span { font-size: 12px; color: var(--ink3); }

.mte-legs { display: flex; flex-direction: column; }
.mte-leg { display: grid; grid-template-columns: 32px minmax(0, 1fr); gap: 12px; }
.mte-rail { display: flex; flex-direction: column; align-items: center; }
.mte-rail-dot {
  display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; margin-top: 14px; border-radius: 50%;
  background: var(--accent); color: #fff; font-size: 12px; font-weight: 600;
}
.mte-rail-dot--ghost { margin-top: 6px; background: transparent; border: 2px dashed var(--border-strong); }
.mte-rail-line { flex: 1; width: 2px; margin-top: 4px; background: var(--border); }
.mte-card { margin-bottom: 12px; border: 1px solid var(--border); border-radius: 12px; background: var(--surface); overflow: hidden; }
.mte-card--open { border-color: var(--border-strong); }
.mte-card-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; cursor: pointer; }
.mte-card-head:focus-visible { outline: 2px solid var(--accent); outline-offset: -2px; }
.mte-card-title { display: flex; flex: 1; flex-direction: column; gap: 3px; min-width: 0; }
.mte-card-route { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 14px; font-weight: 600; color: var(--ink); }
.mte-card-sub { font-size: 12px; color: var(--ink3); }
.mte-phase {
  --c: #4338ca;
  font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
  padding: 2px 7px; border-radius: 999px; color: var(--c); background: color-mix(in srgb, var(--c) 13%, var(--surface));
}
.mte-phase--match { --c: #7e22ce; }
.mte-phase--training { --c: #047857; }
.mte-phase--arrival { --c: #1d4ed8; }
.mte-phase--departure { --c: #c2410c; }
.mte-phase--daily_ops { --c: var(--ink3); }
.mte-issue { flex: none; padding: 3px 8px; border-radius: 999px; background: var(--warn-soft); color: var(--warn); font-size: 11px; font-weight: 500; }
.mte-issue--block { background: var(--danger-soft); color: var(--danger-strong); }
.mte-card-tools { display: flex; gap: 2px; }
.mte-card-tools button { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--ink3); cursor: pointer; }
.mte-card-tools button:hover:not(:disabled) { background: var(--panel); color: var(--ink); }
.mte-card-tools button:disabled { opacity: 0.35; cursor: default; }
.mte-card-tools .mte-del { color: var(--danger-strong); font-size: 15px; }
.mte-card-tools .mte-del:hover { background: var(--danger-soft); }
.mte-chev { width: 14px; text-align: center; font-size: 12px; color: var(--ink4); }

.mte-card-body { display: flex; flex-direction: column; gap: 14px; padding: 14px; border-top: 1px solid var(--border); background: color-mix(in srgb, var(--panel) 40%, var(--surface)); }
.mte-fromto { display: grid; grid-template-columns: minmax(0, 1fr) 32px minmax(0, 1fr); gap: 8px; align-items: end; }
.mte-swap { height: 38px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink3); cursor: pointer; }
.mte-swap:hover { color: var(--ink); background: var(--panel); }
.mte-two { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.mte-seg { display: flex; gap: 3px; height: 38px; padding: 3px; border-radius: 8px; background: var(--panel); }
.mte-seg-btn { flex: 1; min-width: 0; border: 0; border-radius: 6px; background: transparent; color: var(--ink3); font-size: 12px; font-weight: 500; cursor: pointer; }
.mte-seg-btn:hover { color: var(--ink); }
.mte-seg-btn--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }
.mte-stepper { display: flex; align-items: center; height: 38px; border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface); overflow: hidden; }
.mte-stepper button { width: 32px; height: 100%; border: 0; background: transparent; color: var(--ink3); font-size: 15px; cursor: pointer; }
.mte-stepper button:hover { background: var(--panel); color: var(--ink); }
.mte-stepper input { flex: 1; min-width: 0; height: 100%; border: 0; background: transparent; color: var(--ink); text-align: center; font-size: 13px; }
.mte-stepper input:focus { outline: none; }

.mte-cps { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); overflow: hidden; }
.mte-cps-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 8px 12px; background: var(--panel); border-bottom: 1px solid var(--border); font-size: 11px; color: var(--ink3); }
.mte-cps-head .mte-mono { font-weight: 600; color: var(--ink2); }
.mte-cp { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto 52px; gap: 10px; align-items: center; padding: 7px 12px; border-top: 1px solid var(--border); }
.mte-cp:first-of-type { border-top: 0; }
.mte-cp-n { display: flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 50%; background: var(--accent-soft); color: var(--accent-fg); font-size: 10px; font-weight: 600; }
.mte-cp-name { display: flex; flex-direction: column; min-width: 0; font-size: 12px; font-weight: 500; color: var(--ink); }
.mte-cp-name small { font-size: 10px; font-weight: 400; color: var(--ink4); }
.mte-req { font-size: 10px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; background: var(--accent-soft); color: var(--accent-fg); }
.mte-req--opt { background: var(--panel); color: var(--ink3); }
.mte-cp-min { font-size: 12px; text-align: right; color: var(--ink3); }
.mte-cps-empty { margin: 0; padding: 8px 12px; }
.mte-mono { font-family: var(--mono, ui-monospace, monospace); }
.mte-differ { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--ink3); }
.mte-suggest {
  align-self: flex-start; padding: 6px 10px; border: 1px dashed color-mix(in srgb, var(--accent) 45%, transparent); border-radius: 8px;
  background: var(--accent-soft); color: var(--accent-fg); font-size: 12px; cursor: pointer;
}

.mte-add { display: flex; flex-wrap: wrap; gap: 8px; }
.mte-add-btn, .mte-return-btn {
  display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 14px; border-radius: 10px;
  font-size: 13px; cursor: pointer;
}
.mte-add-btn { border: 1px dashed var(--border-strong); background: var(--surface); color: var(--ink); font-weight: 500; }
.mte-add-btn span { font-size: 16px; }
.mte-add-btn:hover { border-color: var(--ink); background: var(--panel); }
.mte-return-btn { border: 1px solid var(--border); background: var(--panel); color: var(--ink2); }
.mte-return-btn:hover { color: var(--ink); }

.mte-foot { display: flex; flex: 1; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.mte-status { font-size: 12px; color: var(--ink3); }
.mte-status--warn { color: var(--warn); }
.mte-status--bad { color: var(--danger-strong); }
.mte-foot-btns { display: flex; gap: 8px; }
.mte-btn {
  height: 38px; padding: 0 16px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-weight: 500; cursor: pointer;
}
.mte-btn:hover:not(:disabled) { background: var(--panel); }
.mte-btn--primary { padding: 0 18px; border-color: var(--ink); background: var(--ink); color: var(--surface); font-weight: 600; }
.mte-btn--primary:hover:not(:disabled) { background: var(--ink); filter: brightness(1.2); }
.mte-btn:disabled { opacity: 0.45; cursor: not-allowed; }

@media (max-width: 860px) {
  .mte { grid-template-columns: 1fr; height: auto; }
  .mte-left, .mte-right { overflow: visible; }
  .mte-left { border-right: 0; border-bottom: 1px solid var(--border); }
}
</style>
