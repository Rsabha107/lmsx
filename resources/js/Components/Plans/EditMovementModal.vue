<template>
  <div v-dialog="() => emit('close')" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal em" role="dialog" aria-labelledby="em-title">
      <div class="em-head">
        <div class="em-head__row">
          <div class="em-head__text">
            <div class="em-tags">
              <span class="em-code">{{ mv.code || 'Movement' }}</span>
              <span v-if="kind" :class="['em-pill', `em-pill--${kindTone}`]">{{ kind.replace('_', ' ').toUpperCase() }}</span>
              <span v-if="isBus" class="em-pill em-pill--bus">BUS</span>
              <span v-else-if="flightNo" class="em-pill em-pill--flight">{{ flightNo }}</span>
            </div>
            <div id="em-title" class="em-title">Edit movement</div>
          </div>
          <button type="button" class="em-close" aria-label="Close" @click="emit('close')">×</button>
        </div>

        <!-- Read-only route, reference time and team -->
        <div class="em-route">
          <div class="em-route__end">
            <span class="em-route__label">FROM</span>
            <span class="em-route__place">{{ from || '—' }}</span>
            <span v-if="fromSub" class="em-route__sub">{{ fromSub }}</span>
          </div>
          <div class="em-route__mid">
            <span class="em-route__time">{{ reference ? `${dayLabel(reference.at, false)} · ${clock(reference.at)}` : '—' }}</span>
            <span class="em-route__line"><i class="em-dot" /><i class="em-route__bar" /><i class="em-dot em-dot--hollow" /></span>
            <span class="em-route__sub">{{ reference ? reference.label : 'no reference time' }}</span>
          </div>
          <div class="em-route__end em-route__end--right">
            <span class="em-route__label">TO</span>
            <span class="em-route__place">{{ to || '—' }}</span>
            <span v-if="toSub" class="em-route__sub">{{ toSub }}</span>
          </div>
          <div class="em-route__foot">
            <span class="em-team">
              <flag-icon v-if="countryCode" :code="countryCode" />
              <strong>{{ teamName || 'No team' }}</strong>
              <span v-if="teamCode" class="em-muted">{{ teamCode }}</span>
            </span>
            <span v-if="passengers > 0" class="em-pax"><i class="em-sdot" />{{ passengers }} passenger{{ passengers === 1 ? '' : 's' }}</span>
            <span v-else class="em-pax em-pax--warn"><i class="em-sdot em-sdot--warn" />No passengers listed</span>
            <span class="em-readonly" title="Flight and team details come from the plan and can't be edited here">Read-only</span>
          </div>
        </div>
      </div>

      <div class="em-body">
        <!-- Pickup window -->
        <section class="em-section">
          <div class="em-section__head">
            <span class="em-section__title">Pickup window</span>
            <span class="em-section__meta">{{ windowSummary }}</span>
          </div>
          <div class="em-ends">
            <div v-for="e in ENDS" :key="e.key" class="em-end">
              <span class="em-end__label">{{ e.label }}</span>
              <div class="em-end__row">
                <button type="button" class="em-step" :aria-label="`${e.name} 5 minutes earlier`" @click="shift(e.key, -5)">−</button>
                <input
                  type="text"
                  inputmode="numeric"
                  maxlength="5"
                  placeholder="--:--"
                  class="em-end__time"
                  :aria-label="e.name"
                  :value="form[e.key].slice(11, 16)"
                  @change="setTime(e.key, $event)"
                  @keydown.up.prevent="shift(e.key, 5)"
                  @keydown.down.prevent="shift(e.key, -5)"
                />
                <button type="button" class="em-step" :aria-label="`${e.name} 5 minutes later`" @click="shift(e.key, 5)">+</button>
              </div>
              <span class="em-end__date">
                <button type="button" class="em-end__day" :aria-label="`${e.name} date`" title="Change the date" @click="openDate(e.key)">
                  {{ form[e.key] ? dayLabel(toMinutes(form[e.key])) : 'Pick a date' }}
                </button>
                · 5 min steps
                <input
                  :ref="(el) => (dateInputs[e.key] = el)"
                  type="date"
                  class="em-hidden-date"
                  tabindex="-1"
                  aria-hidden="true"
                  :value="form[e.key].slice(0, 10)"
                  @change="setDate(e.key, $event.target.value)"
                />
              </span>
            </div>
          </div>
          <div v-if="timeline" class="em-timeline" aria-hidden="true">
            <div class="em-timeline__track" />
            <div class="em-timeline__window" :style="{ left: timeline.left, width: timeline.width }" />
            <template v-if="timeline.ref">
              <div class="em-timeline__ref" :style="{ left: timeline.ref }" />
              <span class="em-timeline__ref-label" :style="{ left: timeline.ref }">{{ timeline.refLabel }}</span>
            </template>
            <span class="em-timeline__edge">{{ timeline.lo }}</span>
            <span class="em-timeline__edge em-timeline__edge--right">{{ timeline.hi }}</span>
          </div>
          <div v-if="windowBad" class="em-error">The window has to end after it starts.</div>
        </section>

        <!-- Vehicles & drivers: row 1 is the lead, the rest are extra units -->
        <section class="em-section">
          <div class="em-section__head">
            <span class="em-section__title">Vehicles &amp; drivers</span>
            <span class="em-section__meta">{{ assignedCount }} of {{ form.crew.length }} assigned</span>
          </div>
          <div v-for="(row, i) in form.crew" :key="i" class="em-crew">
            <span :class="['em-crew__n', { 'em-crew__n--on': row.vehicle_id && row.driver_id }]">{{ i + 1 }}</span>
            <select v-model="row.vehicle_id" :class="['em-input', { 'em-input--empty': !row.vehicle_id }]" :aria-label="`Vehicle ${i + 1}`">
              <option :value="null">Vehicle…</option>
              <option v-for="v in vehicleOptions" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}{{ crewNote('vehicles', v.id) }}</option>
            </select>
            <select v-model="row.driver_id" :class="['em-input', { 'em-input--empty': !row.driver_id }]" :aria-label="`Driver ${i + 1}`">
              <option :value="null">Driver…</option>
              <option v-for="d in driverOptions" :key="d.id" :value="d.id">{{ d.name }}{{ crewNote('drivers', d.id) }}</option>
            </select>
            <button
              type="button"
              class="em-remove"
              :style="{ visibility: form.crew.length > 1 ? 'visible' : 'hidden' }"
              :aria-label="`Remove vehicle ${i + 1}`"
              title="Remove"
              @click="form.crew.splice(i, 1)"
            >×</button>
          </div>
          <button type="button" class="em-add" @click="form.crew.push({ vehicle_id: null, driver_id: null })">+ Add vehicle</button>
        </section>

        <!-- Field supervisors: the first is the lead -->
        <section class="em-section">
          <span class="em-section__title">Field supervisors</span>
          <div class="em-sups">
            <span v-for="id in form.supervisors" :key="id" class="em-sup" :title="crewNote('supervisors', id).replace(/^ — /, '') || null">
              {{ supervisorName(id) }}
              <button type="button" :aria-label="`Remove ${supervisorName(id)}`" @click="removeSupervisor(id)">×</button>
            </span>
            <select class="em-sups__add" aria-label="Add field supervisor" value="" @change="addSupervisor($event)">
              <option value="">{{ form.supervisors.length ? '+ Add another' : 'Add supervisor…' }}</option>
              <option v-for="s in supervisorChoices" :key="s.id" :value="s.id">{{ personLabel(s) }}{{ crewNote('supervisors', s.id) }}</option>
            </select>
          </div>
        </section>

        <!-- Linked match -->
        <section v-if="matchOptions.length > 1" class="em-section">
          <div class="em-section__head">
            <span class="em-section__title">Linked match</span>
            <span class="em-section__meta em-muted">Optional</span>
          </div>
          <div class="em-matches" role="radiogroup" aria-label="Linked match">
            <button
              v-for="m in matchOptions"
              :key="m.id ?? 'none'"
              type="button"
              role="radio"
              :aria-checked="form.matchId === m.id"
              :class="['em-match', { 'em-match--on': form.matchId === m.id }]"
              @click="form.matchId = m.id"
            >
              <span class="em-match__label">{{ m.label }}</span>
              <span class="em-match__sub">{{ m.sub }}</span>
            </button>
          </div>
        </section>

        <section class="em-section">
          <label for="em-notes" class="em-section__title">Notes</label>
          <textarea
            id="em-notes"
            v-model="form.notes"
            class="em-input em-notes"
            rows="3"
            placeholder="Anything the crew should know — gate, contact, special luggage…"
          ></textarea>
        </section>
      </div>

      <div class="em-foot">
        <span :class="['em-status', `em-status--${status.tone}`]"><i class="em-sdot" />{{ status.text }}</span>
        <div class="em-foot__actions">
          <button type="button" class="em-btn" :disabled="processing" @click="emit('close')">Cancel</button>
          <button type="button" class="em-btn em-btn--primary" :disabled="!canSave" @click="submit">
            {{ processing ? 'Updating…' : 'Update movement' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import FlagIcon from '../FlagIcon.vue';
import { crewOptions, personLabel } from '../../Composables/crewPeople';
import { useCrewBusy } from '../../Composables/useCrewBusy';

// Mounted per movement (v-if + key), so the form starts from the movement each time.
// Takes both payloads: By Plan rows (Eloquent movement) and By Team rows (from/to/dep/arr/pax).
const props = defineProps({
  movement: { type: Object, required: true },
  // Date of the open plan, for By Team rows that only carry times.
  planDate: { type: String, default: null },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  matches: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const ENDS = [
  { key: 'start', label: 'WINDOW START', name: 'Window start' },
  { key: 'end', label: 'WINDOW END', name: 'Window end' },
];

// Wall-clock maths on the digits ("YYYY-MM-DD HH:MM"), never through the browser's time zone.
function toMinutes(value) {
  const m = String(value ?? '').match(/(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})/);
  return m ? Date.UTC(+m[1], +m[2] - 1, +m[3], +m[4], +m[5]) / 60000 : null;
}
function fromMinutes(n) {
  const d = new Date(n * 60000);
  const p = (x) => String(x).padStart(2, '0');
  return `${d.getUTCFullYear()}-${p(d.getUTCMonth() + 1)}-${p(d.getUTCDate())}T${p(d.getUTCHours())}:${p(d.getUTCMinutes())}`;
}
const clock = (n) => fromMinutes(n).slice(11, 16);
const dayLabel = (n, weekday = true) =>
  new Date(n * 60000)
    .toLocaleDateString('en-GB', { ...(weekday ? { weekday: 'short' } : {}), day: '2-digit', month: 'short', timeZone: 'UTC' })
    .replace(',', '');
function span(minutes) {
  const m = Math.abs(Math.round(minutes));
  if (m < 60) return `${m} min`;
  if (m >= 1440) {
    const d = Math.floor(m / 1440);
    const h = Math.round((m % 1440) / 60);
    return h ? `${d}d ${h}h` : `${d}d`;
  }
  const h = Math.floor(m / 60);
  return m % 60 ? `${h}h ${String(m % 60).padStart(2, '0')}m` : `${h}h`;
}

// By Plan rows carry window_start/window_end; By Team rows only dep/arr times.
function windowValue(full, time) {
  const n = toMinutes(full);
  if (n !== null) return fromMinutes(n);
  return time && props.planDate ? `${props.planDate.slice(0, 10)}T${time}` : '';
}

const mv = props.movement;
const kind = mv.kind || '';
const kindTone = ['arrival', 'departure', 'match'].includes(kind) ? kind : 'other';
const from = mv.from_location || mv.from || '';
const to = mv.to_location || mv.to || '';
const flight = mv.flight || null;
const flightNo = String(flight?.flight_number || mv.flight_number || '');
const isBus = flightNo.toUpperCase() === 'BUS';
const teamName = mv.team?.team_name || mv.team?.team || (typeof mv.team === 'string' ? mv.team : '');
const teamCode = mv.team?.code || mv.team_code || '';
const countryCode = mv.team?.country_id || teamCode.split('-')[0] || null;
const passengers = Number(mv.passengers ?? mv.pax ?? 0);
const airport = (a) => (a && typeof a === 'object' ? a.code || a.iata : a) || '';

const fromSub = kind === 'arrival' && flightNo && !isBus
  ? [flightNo, airport(flight?.origin_airport) && `from ${airport(flight.origin_airport)}`].filter(Boolean).join(' · ')
  : '';
const toSub = kind === 'departure' && flightNo && !isBus
  ? [flightNo, airport(flight?.destination_airport) && `to ${airport(flight.destination_airport)}`].filter(Boolean).join(' · ')
  : (mv.match?.venue?.name || '');

const supervisorIds = [mv.field_supervisor_id, ...(mv.extra_supervisors || []).map((s) => s.id)].filter(Boolean);
const form = reactive({
  start: windowValue(mv.window_start, mv.dep),
  end: windowValue(mv.window_end, mv.arr),
  crew: [
    { vehicle_id: mv.vehicle_id || null, driver_id: mv.driver_id || null },
    ...(mv.units || []).map((u) => ({ vehicle_id: u.vehicle_id ?? null, driver_id: u.driver_id ?? null })),
  ],
  supervisors: [...new Set(supervisorIds)],
  matchId: mv.match_id || null,
  notes: mv.notes || '',
});
const initial = JSON.parse(JSON.stringify(form));
const processing = ref(false);
const serverError = ref('');
const dateInputs = {};

// Same lists Crew Assignment offers (crewOptions): the movement's provider's, plus whoever is already on it.
const vehicleOptions = computed(() => crewOptions(props.vehicles, 'vehicle', mv));
const driverOptions = computed(() => crewOptions(props.drivers, 'driver', mv));
const supervisorOptions = computed(() => crewOptions(props.supervisors, 'supervisor', mv));
const vehicleLabel = (v) => [v.code, v.vehicle_type, v.capacity ? `${v.capacity} seats` : null].filter(Boolean).join(' · ');

// Why a vehicle, driver or supervisor is busy for this movement (same checks as the Conflicts tab).
const { note: crewNote } = useCrewBusy(() => mv.id ?? null);

// Matches the movement's team plays in.
const relevantMatches = computed(() => {
  const teamId = mv.team_id ?? mv.team?.id;
  if (!teamId) return [];
  return props.matches.filter((m) => m.team1_id === teamId || m.team2_id === teamId);
});
const matchSide = (t) => t?.code || t?.team_name || t?.team || '?';
const matchOptions = computed(() => {
  const list = [...relevantMatches.value];
  if (mv.match?.id && !list.some((m) => m.id === mv.match.id)) list.push(mv.match);
  return [
    { id: null, label: 'None', sub: 'Regular logistics' },
    ...list.map((m) => {
      const ko = toMinutes(m.kick_off);
      return {
        id: m.id,
        label: `${matchSide(m.team1)} vs ${matchSide(m.team2)}`,
        sub: [m.match_number, ko !== null ? `${dayLabel(ko, false)} · ${clock(ko)}` : null].filter(Boolean).join(' · '),
      };
    }),
  ];
});
const linkedMatch = computed(() =>
  form.matchId ? props.matches.find((m) => m.id === form.matchId) || (mv.match?.id === form.matchId ? mv.match : null) : null,
);

// The time the window is planned around: kick-off of the linked match, else the flight or bus time.
const reference = computed(() => {
  const ko = toMinutes(linkedMatch.value?.kick_off);
  if (ko !== null) return { at: ko, label: 'kick-off', edge: 'end' };
  const at = toMinutes(flight?.scheduled_at);
  if (at === null || !['arrival', 'departure'].includes(kind)) return null;
  const arriving = kind === 'arrival';
  const label = isBus ? (arriving ? 'bus arrival' : 'bus departure') : arriving ? 'landing' : 'take-off';
  return { at, label, edge: arriving ? 'start' : 'end' };
});

const startMin = computed(() => toMinutes(form.start));
const endMin = computed(() => toMinutes(form.end));
const duration = computed(() => (startMin.value !== null && endMin.value !== null ? endMin.value - startMin.value : null));
const windowBad = computed(() => duration.value !== null && duration.value <= 0);

const windowSummary = computed(() => {
  if (windowBad.value) return '—';
  if (duration.value === null) return 'No window set';
  const ref = reference.value;
  if (!ref) return `${span(duration.value)} window`;
  const gap = (ref.edge === 'start' ? startMin.value : endMin.value) - ref.at;
  const when = gap === 0 ? 'at' : `${span(gap)} ${gap > 0 ? 'after' : 'before'}`;
  return `${span(duration.value)} window · ${ref.edge === 'start' ? 'starts' : 'ends'} ${when} ${ref.label}`;
});

const timeline = computed(() => {
  if (duration.value === null || windowBad.value) return null;
  const ref = reference.value;
  const points = [startMin.value, endMin.value, ref?.at].filter((v) => v != null);
  const lo = Math.floor((Math.min(...points) - 30) / 60) * 60;
  const hi = Math.ceil((Math.max(...points) + 30) / 60) * 60;
  if (hi - lo > 36 * 60) return null;
  const pct = (v) => `${((v - lo) / (hi - lo)) * 100}%`;
  return {
    left: pct(startMin.value),
    width: `${(duration.value / (hi - lo)) * 100}%`,
    ref: ref ? pct(ref.at) : null,
    refLabel: ref ? clock(ref.at) : null,
    lo: clock(lo),
    hi: clock(hi),
  };
});

function defaultDate(key) {
  const other = form[key === 'start' ? 'end' : 'start'];
  return other.slice(0, 10)
    || props.planDate?.slice(0, 10)
    || (reference.value ? fromMinutes(reference.value.at).slice(0, 10) : fromMinutes(Date.now() / 60000).slice(0, 10));
}
// Typed 24-hour time ("0725", "7:25", "07:25"); anything else puts the old value back.
function setTime(key, event) {
  const m = event.target.value.trim().match(/^(\d{1,2}):?(\d{2})$/);
  if (!m || +m[1] > 23 || +m[2] > 59) {
    event.target.value = form[key].slice(11, 16);
    return;
  }
  const value = `${m[1].padStart(2, '0')}:${m[2]}`;
  form[key] = `${(form[key] || defaultDate(key)).slice(0, 10)}T${value}`;
  event.target.value = value;
}
function setDate(key, value) {
  if (!value) return;
  form[key] = `${value}T${form[key].slice(11, 16) || '09:00'}`;
}
function shift(key, delta) {
  const base = toMinutes(form[key])
    ?? (key === 'end' ? startMin.value : endMin.value)
    ?? reference.value?.at
    ?? toMinutes(`${defaultDate(key)} 09:00`);
  form[key] = fromMinutes(base + delta);
}
function openDate(key) {
  const input = dateInputs[key];
  if (!input) return;
  try {
    input.showPicker();
  } catch {
    input.focus();
  }
}

const assignedCount = computed(() => form.crew.filter((r) => r.vehicle_id && r.driver_id).length);

function supervisorName(id) {
  const lead = mv.field_supervisor;
  return props.supervisors.find((s) => s.id === id)?.name
    || (mv.extra_supervisors || []).find((s) => s.id === id)?.name
    || (id === mv.field_supervisor_id ? (typeof lead === 'string' ? lead : lead?.name) : null)
    || `Supervisor #${id}`;
}
const supervisorChoices = computed(() => supervisorOptions.value.filter((s) => !form.supervisors.includes(s.id)));
function addSupervisor(event) {
  const id = Number(event.target.value);
  if (id && !form.supervisors.includes(id)) form.supervisors.push(id);
  event.target.value = '';
}
function removeSupervisor(id) {
  form.supervisors = form.supervisors.filter((s) => s !== id);
}

const changes = computed(() =>
  Object.keys(initial).filter((k) => JSON.stringify(form[k]) !== JSON.stringify(initial[k])).length,
);
const missing = computed(() => [
  ...(assignedCount.value ? [] : ['vehicle & driver']),
  ...(form.supervisors.length ? [] : ['supervisor']),
]);
const status = computed(() => {
  if (serverError.value) return { tone: 'bad', text: serverError.value };
  if (windowBad.value) return { tone: 'bad', text: 'Fix the pickup window' };
  if (missing.value.length) return { tone: 'warn', text: `Still needed: ${missing.value.join(', ')}` };
  if (!changes.value) return { tone: 'ok', text: 'No changes' };
  return { tone: 'ok', text: `${changes.value} change${changes.value > 1 ? 's' : ''} ready to save` };
});
const canSave = computed(() => changes.value > 0 && !windowBad.value && !processing.value);

function submit() {
  if (!canSave.value) return;
  processing.value = true;
  serverError.value = '';
  const [lead = {}, ...units] = form.crew;
  const [leadSupervisor = null, ...extraSupervisors] = form.supervisors;

  // Only operational fields; flight and team data are read-only here.
  router.put(
    `/movements/${mv.id}`,
    {
      window_start: form.start || null,
      window_end: form.end || null,
      vehicle_id: lead.vehicle_id || null,
      driver_id: lead.driver_id || null,
      field_supervisor_id: leadSupervisor,
      units: units.filter((u) => u.vehicle_id || u.driver_id),
      supervisors: extraSupervisors,
      notes: form.notes || null,
      match_id: form.matchId || null,
    },
    {
      onSuccess: () => emit('close'),
      onError: (errors) => {
        serverError.value = Object.values(errors)[0] || 'The movement could not be saved.';
      },
      onFinish: () => {
        processing.value = false;
      },
    },
  );
}
</script>

<style scoped src="./planDialog.css"></style>

<style scoped>
/* Edit movement dialog: layout after the "Edit Movement Modal" design, in the app's theme colours. */
.em {
  max-width: 580px;
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(17, 24, 39, 0.3);
}
.em-head {
  flex: none;
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding: 18px 22px 16px;
  border-bottom: 1px solid var(--border);
}
.em-head__row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.em-head__text { display: flex; flex-direction: column; gap: 3px; }
.em-tags { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.em-code { font-family: ui-monospace, Menlo, monospace; font-size: 11px; color: var(--ink3); }
.em-title { font-size: 18px; font-weight: 600; color: var(--ink); }
.em-close {
  width: 32px; height: 32px; border: none; border-radius: 8px;
  background: transparent; color: var(--ink3); font-size: 20px; cursor: pointer;
}
.em-close:hover { background: var(--panel); color: var(--ink); }

.em-pill { font-size: 10px; font-weight: 600; letter-spacing: 0.05em; padding: 2px 7px; border-radius: 999px; }
.em-pill--arrival { background: rgba(16, 185, 129, 0.14); color: #047857; }
.em-pill--departure { background: rgba(59, 130, 246, 0.14); color: #1d4ed8; }
.em-pill--match { background: rgba(139, 92, 246, 0.14); color: #6d28d9; }
.em-pill--other { background: var(--panel); color: var(--ink2); }
.em-pill--bus { background: var(--warn-soft); color: var(--warn); }
.em-pill--flight { background: var(--panel); color: var(--ink2); font-family: ui-monospace, Menlo, monospace; }
:global([data-theme="dark"]) .em-pill--arrival { color: #34d399; }
:global([data-theme="dark"]) .em-pill--departure { color: #93c5fd; }
:global([data-theme="dark"]) .em-pill--match { color: #c4b5fd; }

.em-route {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr);
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--panel);
}
.em-route__end { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.em-route__end--right { text-align: right; }
.em-route__label { font-size: 10px; font-weight: 600; letter-spacing: 0.06em; color: var(--ink3); }
.em-route__place { font-size: 15px; font-weight: 600; color: var(--ink); overflow-wrap: anywhere; }
.em-route__sub { font-size: 11px; color: var(--ink3); }
.em-route__mid { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.em-route__time { font-family: ui-monospace, Menlo, monospace; font-size: 11px; font-weight: 600; color: var(--ink); white-space: nowrap; }
.em-route__line { display: flex; align-items: center; width: 110px; }
.em-route__bar { flex: 1; height: 1.5px; background: var(--accent-soft); }
.em-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
.em-dot--hollow { background: var(--surface); border: 1.5px solid var(--accent); }
.em-route__foot {
  grid-column: 1 / -1;
  display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap;
  padding-top: 10px; border-top: 1px solid var(--border); font-size: 12px; color: var(--ink);
}
.em-team { display: flex; align-items: center; gap: 8px; }
.em-team strong { font-weight: 600; }
.em-muted { color: var(--ink3); }
.em-pax { display: flex; align-items: center; gap: 6px; color: var(--ink2); }
.em-pax--warn { color: var(--warn); }
.em-readonly { font-size: 11px; color: var(--ink4); cursor: help; }
.em-sdot { width: 6px; height: 6px; border-radius: 50%; background: var(--ok); flex: none; }
.em-sdot--warn { background: var(--warn); }

.em-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: 18px 22px 22px;
  display: flex;
  flex-direction: column;
  gap: 22px;
}
.em-section { display: flex; flex-direction: column; gap: 10px; }
.em-section__head { display: flex; align-items: baseline; justify-content: space-between; gap: 10px; }
.em-section__title { font-size: 13px; font-weight: 600; color: var(--ink); }
.em-section__meta { font-size: 12px; color: var(--ink3); text-align: right; }

.em-ends { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 8px; }
.em-end {
  position: relative;
  display: flex; flex-direction: column; gap: 6px;
  padding: 10px 12px; border: 1px solid var(--border); border-radius: 10px;
}
.em-end__label { font-size: 10px; font-weight: 600; letter-spacing: 0.06em; color: var(--ink3); }
.em-end__row { display: flex; align-items: center; gap: 6px; }
.em-end__time {
  flex: 1; min-width: 0; height: 32px; border: none; outline: none; background: transparent;
  color: var(--ink); font-size: 20px; font-weight: 600; text-align: center; font-family: inherit;
}
.em-end__time:focus-visible { box-shadow: 0 0 0 2px var(--accent-ring, rgba(138, 21, 56, 0.2)); border-radius: 6px; }
.em-step {
  width: 28px; height: 28px; flex: none; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink2); font-size: 14px; cursor: pointer;
}
.em-step:hover { background: var(--panel); }
.em-end__date { font-size: 11px; color: var(--ink4); text-align: center; }
.em-end__day {
  border: none; background: none; padding: 0; font: inherit; color: var(--ink3);
  text-decoration: underline dotted; text-underline-offset: 2px; cursor: pointer;
}
.em-end__day:hover { color: var(--accent); }
.em-hidden-date { position: absolute; left: 50%; bottom: 0; width: 1px; height: 1px; opacity: 0; pointer-events: none; }

.em-timeline { position: relative; height: 28px; }
.em-timeline__track { position: absolute; left: 0; right: 0; top: 12px; height: 4px; border-radius: 2px; background: var(--panel); }
.em-timeline__window { position: absolute; top: 12px; height: 4px; border-radius: 2px; background: var(--accent); }
.em-timeline__ref { position: absolute; top: 6px; width: 2px; height: 16px; background: var(--ink); }
.em-timeline__ref-label { position: absolute; top: -4px; font-size: 10px; font-weight: 600; color: var(--ink); transform: translateX(-50%); }
.em-timeline__edge { position: absolute; top: 18px; left: 0; font-size: 10px; color: var(--ink4); }
.em-timeline__edge--right { left: auto; right: 0; }
.em-error { font-size: 12px; color: var(--danger); }

.em-input {
  height: 40px; width: 100%; min-width: 0; padding: 0 10px;
  border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-family: inherit; text-overflow: ellipsis;
}
.em-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring, rgba(138, 21, 56, 0.15)); }
.em-input--empty { color: var(--ink3); }
.em-crew { display: grid; grid-template-columns: 24px minmax(0, 1fr) minmax(0, 1fr) 28px; gap: 8px; align-items: center; }
.em-crew__n {
  width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center;
  background: var(--panel); color: var(--ink3); font-size: 11px; font-weight: 600;
}
.em-crew__n--on { background: var(--accent); color: #fff; }
.em-remove {
  width: 28px; height: 28px; border: none; border-radius: 6px;
  background: transparent; color: var(--ink4); font-size: 16px; cursor: pointer;
}
.em-remove:hover { background: var(--danger-soft); color: var(--danger); }
.em-add {
  align-self: flex-start; margin-left: 32px; height: 32px; padding: 0 12px;
  border: 1px dashed var(--border); border-radius: 8px; background: var(--surface);
  color: var(--accent); font-size: 12px; font-weight: 500; font-family: inherit; cursor: pointer;
}
.em-add:hover { border-color: var(--accent); }

.em-sups {
  display: flex; flex-wrap: wrap; align-items: center; gap: 6px;
  min-height: 40px; padding: 6px; border: 1px solid var(--border); border-radius: 10px;
}
.em-sups:focus-within { border-color: var(--accent); }
.em-sup {
  display: flex; align-items: center; gap: 6px; height: 28px; padding: 0 6px 0 10px;
  border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-size: 12px; font-weight: 500;
}
.em-sup button { border: none; background: none; padding: 0 2px; color: var(--accent); font-size: 14px; cursor: pointer; }
.em-sups__add {
  flex: 1; min-width: 140px; height: 28px; border: none; outline: none;
  background: transparent; color: var(--ink3); font-size: 13px; font-family: inherit;
}

.em-matches { display: flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 10px; background: var(--panel); }
.em-match {
  flex: 1 1 140px; display: flex; flex-direction: column; gap: 1px; padding: 8px 10px;
  border: none; border-radius: 7px; background: transparent; text-align: left; cursor: pointer; font-family: inherit;
}
.em-match__label { font-size: 12px; font-weight: 600; color: var(--ink); }
.em-match__sub { font-size: 11px; color: var(--ink3); }
.em-match--on { background: var(--surface); box-shadow: 0 1px 3px rgba(17, 24, 39, 0.12); }
.em-match--on .em-match__label { color: var(--accent); }
.em-match:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }

.em-notes { height: auto; padding: 10px 12px; resize: vertical; line-height: 1.4; }

.em-foot {
  flex: none; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 14px 22px; border-top: 1px solid var(--border); background: var(--surface);
}
.em-status { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ink2); }
.em-status--warn { color: var(--warn); }
.em-status--warn .em-sdot { background: var(--warn); }
.em-status--bad { color: var(--danger); }
.em-status--bad .em-sdot { background: var(--danger); }
.em-foot__actions { display: flex; gap: 8px; margin-left: auto; }
.em-btn {
  height: 38px; padding: 0 16px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-weight: 500; font-family: inherit; cursor: pointer;
}
.em-btn:hover:not(:disabled) { background: var(--panel); }
.em-btn--primary { border: none; padding: 0 18px; background: var(--accent); color: #fff; font-weight: 600; }
.em-btn--primary:hover:not(:disabled) { background: var(--accent); filter: brightness(1.08); }
.em-btn:disabled { cursor: not-allowed; }
.em-btn--primary:disabled { background: var(--panel); color: var(--ink3); }

@media (max-width: 560px) {
  .em-ends { grid-template-columns: 1fr; }
  .em-crew { grid-template-columns: 24px minmax(0, 1fr) 28px; }
  .em-crew select:last-of-type { grid-column: 2; }
}
</style>
