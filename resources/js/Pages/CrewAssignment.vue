<template>
  <app-layout>
    <div v-if="!hasActiveEvent" class="empty-state-full">
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">Select an event from the dropdown above to assign crews.</p>
    </div>

    <div v-else class="crew-page">
      <header class="rh">
        <div class="rh-title">
          <div class="rh-kicker">Crew assignment · Week of {{ weekLabel }}</div>
          <h1 class="rh-h1">{{ view === 'table' ? 'Crew assignment' : `${ROLES[tab].singular} roster` }}</h1>
          <p class="page-sub">
            {{ dayMovements.length }} movement{{ dayMovements.length === 1 ? '' : 's' }} · {{ unassignedCount }} need a crew
            <template v-if="clashCount"> · <b class="sub-bad">{{ clashCount }} with a clash</b></template>
            · {{ dateLabel }}
          </p>
        </div>
        <div class="rh-actions">
          <DatePicker :model-value="selectedDate" @update:model-value="onDateChange" />
          <RefreshButton :only="['movements', 'date', 'days', 'week', 'vehicles', 'drivers', 'supervisors']" />
          <div class="rh-seg">
            <button v-for="v in views" :key="v.value" type="button"
              :class="['rh-seg-btn', { 'rh-seg-btn--active': view === v.value }]" @click="view = v.value">{{ v.label }}</button>
          </div>
        </div>
      </header>

      <!-- The selected date's week, one tab per day -->
      <nav class="wn">
        <button type="button" class="wn-nav" aria-label="Previous week" @click="shiftWeek(-7)">‹</button>
        <button v-for="d in weekTabs" :key="d.date" type="button"
          :class="['wn-day', {
            'wn-day--active': d.date === selectedDate,
            'wn-day--empty': !d.total,
            'wn-day--need': d.total && d.unassigned,
            'wn-day--ok': d.total && !d.unassigned,
          }]" @click="onDateChange(d.date)">
          <span class="wn-label">{{ d.label }}<span v-if="d.clashes" class="wn-conf">{{ d.clashes }}</span></span>
          <span class="wn-sub">
            <template v-if="d.total !== null">
              <span class="wn-count">{{ d.total }} movement{{ d.total === 1 ? '' : 's' }}</span>
              <span :class="['wn-crew', d.unassigned ? 'wn-crew--need' : 'wn-crew--ok']">{{ d.unassigned }} need crew</span>
            </template>
            <span v-else class="wn-count">No movements</span>
          </span>
        </button>
        <button type="button" class="wn-nav" aria-label="Next week" @click="shiftWeek(7)">›</button>
      </nav>

      <div class="crew-toolbar">
        <div v-if="view !== 'table'" class="rh-seg">
          <button v-for="(r, key) in ROLES" :key="key" type="button"
            :class="['rh-seg-btn', { 'rh-seg-btn--active': tab === key }]" @click="tab = key">{{ r.plural }}</button>
        </div>
        <div v-if="view === 'table'" class="filter-tabs">
          <button v-for="f in filters" :key="f.value"
            :class="['filter-tab', activeFilter === f.value ? 'filter-tab--active' : '']"
            @click="activeFilter = f.value">
            {{ f.label }}
          </button>
        </div>
        <input v-if="view !== 'week'" v-model="search" class="crew-search" type="search" placeholder="Search movement, team or route" />
      </div>

      <CrewRoster v-if="view === 'roster'"
        :movements="searched" :date="selectedDate" :tab="tab"
        :resources="resourcesFor(tab)" :selected-id="selectedId" :searching="!!search.trim()"
        @select="(mv) => selectedId = mv.id" @today="onDateChange(todayIso())" />

      <CrewWeek v-else-if="view === 'week'"
        :week="week" :tab="tab" :resources="resourcesFor(tab)" :selected-date="selectedDate"
        @pick="(d) => { view = 'roster'; onDateChange(d); }" />

      <div v-else-if="!filtered.length" class="crew-empty">No movements match.</div>

      <div v-else class="crew-card">
        <table class="crew-table">
          <thead>
            <tr>
              <th>Movement</th><th>Team</th><th>Route</th><th>Pax</th>
              <th>Vehicle</th><th>Driver</th><th>Supervisor</th><th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="mv in filtered" :key="mv.id">
            <tr class="crew-main-row">
              <td data-label="Movement">
                <div class="mono">{{ mv.code }}</div>
                <div class="sub">{{ mv.start ?? '--:--' }}–{{ mv.end ?? '--:--' }} · {{ mv.kind }}</div>
                <status-pill v-if="mv.job_status" :tone="jobTone(mv.job_status)">{{ statusLabel(mv.job_status) }}</status-pill>
                <status-pill v-if="hasClash(mv)" tone="danger" :title="clashTexts(mv).join('\n')">Clash</status-pill>
              </td>
              <td data-label="Team">
                <span class="team-badge-sm">{{ mv.team_code }}</span> {{ mv.team }}
              </td>
              <td data-label="Route" class="sub">
                {{ mv.from }} → {{ mv.to }}
                <div v-if="mv.flight_number">{{ mv.flight_number }}</div>
              </td>
              <td data-label="Pax">{{ mv.pax ?? '—' }}</td>
              <td data-label="Vehicle">
                <select v-model="drafts[mv.id].vehicle_id" :aria-label="`Vehicle for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
                </select>
              </td>
              <td data-label="Driver">
                <select v-model="drafts[mv.id].driver_id" :aria-label="`Driver for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </td>
              <td data-label="Supervisor">
                <select v-model="drafts[mv.id].field_supervisor_id" :aria-label="`Supervisor for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </td>
              <td class="actions">
                <button class="save-btn" :disabled="!isDirty(mv) || saving === mv.id" @click="save(mv)">
                  {{ saving === mv.id ? 'Saving…' : 'Save' }}
                </button>
              </td>
            </tr>
            <!-- Extra vehicles (each beside its own driver) and extra supervisors, one per row. -->
            <tr v-for="i in extraRowIndexes(mv)" :key="`${mv.id}-x${i}`" class="crew-unit-row">
              <td colspan="4" class="unit-label">{{ drafts[mv.id].units[i] ? `Vehicle ${i + 2}` : '' }}</td>
              <td data-label="Extra vehicle">
                <select v-if="drafts[mv.id].units[i]" v-model="drafts[mv.id].units[i].vehicle_id" :aria-label="`Extra vehicle ${i + 2} for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
                </select>
              </td>
              <td data-label="Its driver">
                <div v-if="drafts[mv.id].units[i]" class="unit-pair">
                  <select v-model="drafts[mv.id].units[i].driver_id" :aria-label="`Driver of extra vehicle ${i + 2} for ${mv.code}`">
                    <option :value="null">Unassigned</option>
                    <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                  </select>
                  <button type="button" class="unit-remove" :aria-label="`Remove extra vehicle ${i + 2}`" title="Remove this vehicle" @click="removeUnit(mv, i)">✕</button>
                </div>
              </td>
              <td data-label="Extra supervisor">
                <div v-if="i < drafts[mv.id].supervisors.length" class="unit-pair">
                  <select v-model="drafts[mv.id].supervisors[i]" :aria-label="`Extra supervisor ${i + 2} for ${mv.code}`">
                    <option :value="null">Unassigned</option>
                    <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                  <button type="button" class="unit-remove" :aria-label="`Remove extra supervisor ${i + 2}`" title="Remove this supervisor" @click="removeSupervisor(mv, i)">✕</button>
                </div>
              </td>
              <td></td>
            </tr>
            <tr class="crew-unit-row crew-unit-row--add">
              <td colspan="4"></td>
              <td colspan="2">
                <button type="button" class="unit-add" @click="addUnit(mv)">+ Add vehicle</button>
              </td>
              <td colspan="2">
                <button type="button" class="unit-add" @click="addSupervisor(mv)">+ Add supervisor</button>
              </td>
            </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Assign panel for a roster bar -->
      <aside v-if="selected && view === 'roster'" class="cap" aria-label="Assign crew">
        <div class="cap-head">
          <span class="cap-id">{{ selected.code }}</span>
          <button type="button" class="cap-close" @click="selectedId = null">Close</button>
        </div>
        <div class="cap-who">
          <strong>{{ selected.team_code }} {{ selected.team }}</strong>
          <span>{{ selected.from }} → {{ selected.to }}</span>
        </div>
        <div class="cap-grid">
          <span>Time</span><span class="mono-v">{{ dateLabel }}, {{ selectedSpan.time }}</span>
          <span>Duration</span><span class="mono-v">{{ selectedSpan.dur }}</span>
          <span>Pax</span><span class="mono-v">{{ selected.pax ?? '—' }}</span>
          <span v-if="selected.job_id">Job</span><span v-if="selected.job_id" class="mono-v">{{ selected.job_id }}</span>
        </div>

        <div v-for="(t, i) in clashTexts(selected)" :key="i" class="cap-issue cap-issue--bad">{{ t }}</div>
        <div v-if="!hasClash(selected)" class="cap-issue cap-issue--ok">No conflicts</div>

        <label v-for="(r, key) in ROLES" :key="key" class="cap-field">
          <span>{{ r.singular }}</span>
          <select v-model="drafts[selected.id][r.idField]">
            <option :value="null">Unassigned</option>
            <option v-for="o in resourcesFor(key)" :key="o.id" :value="o.id">
              {{ key === 'vehicle' ? vehicleLabel(o) : o.name }}{{ busyNote(key, o.id, selected) }}
            </option>
          </select>
        </label>
        <p class="cap-hint">"busy" means already booked on an overlapping movement. You can still save; it will be flagged as a clash.</p>

        <div class="cap-units">
          <span class="cap-units-title">Extra vehicles</span>
          <div v-for="(unit, i) in drafts[selected.id].units" :key="i" class="cap-unit">
            <select v-model="unit.vehicle_id" :aria-label="`Extra vehicle ${i + 2}`">
              <option :value="null">Vehicle…</option>
              <option v-for="o in vehicles" :key="o.id" :value="o.id">{{ vehicleLabel(o) }}{{ busyNote('vehicle', o.id, selected) }}</option>
            </select>
            <select v-model="unit.driver_id" :aria-label="`Driver of extra vehicle ${i + 2}`">
              <option :value="null">Driver…</option>
              <option v-for="o in drivers" :key="o.id" :value="o.id">{{ o.name }}{{ busyNote('driver', o.id, selected) }}</option>
            </select>
            <button type="button" class="unit-remove" :aria-label="`Remove extra vehicle ${i + 2}`" @click="removeUnit(selected, i)">✕</button>
          </div>
          <button type="button" class="unit-add" @click="addUnit(selected)">+ Add vehicle</button>
        </div>

        <div class="cap-units">
          <span class="cap-units-title">Extra supervisors</span>
          <div v-for="(_, i) in drafts[selected.id].supervisors" :key="i" class="cap-unit cap-unit--single">
            <select v-model="drafts[selected.id].supervisors[i]" :aria-label="`Extra supervisor ${i + 2}`">
              <option :value="null">Supervisor…</option>
              <option v-for="o in supervisors" :key="o.id" :value="o.id">{{ o.name }}{{ busyNote('supervisor', o.id, selected) }}</option>
            </select>
            <button type="button" class="unit-remove" :aria-label="`Remove extra supervisor ${i + 2}`" @click="removeSupervisor(selected, i)">✕</button>
          </div>
          <button type="button" class="unit-add" @click="addSupervisor(selected)">+ Add supervisor</button>
        </div>

        <div class="cap-actions">
          <button type="button" class="cap-reset" :disabled="!isDirty(selected)" @click="drafts[selected.id] = crewOf(selected)">Reset</button>
          <button type="button" class="save-btn" :disabled="!isDirty(selected) || saving === selected.id" @click="save(selected)">
            {{ saving === selected.id ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </aside>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import DatePicker from '../Components/DatePicker.vue';
import CrewRoster from '../Components/CrewRoster.vue';
import CrewWeek from '../Components/CrewWeek.vue';
import { useToast } from '../Composables/useToast';
import { useStatusLabels } from '../Composables/useStatusLabels';
import { ROLES, minutesFrom, clockLabel, duration } from '../Composables/useCrewRoster';

const props = defineProps({
  movements: { type: Array, default: () => [] },
  date: { type: String, default: null },
  days: { type: Array, default: () => [] },
  week: { type: Object, default: () => ({ start: null, slots: [] }) },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
});

const page = usePage();
const hasActiveEvent = computed(() => !!page.props.activeEventId);
const { success: showSuccessToast, error: showErrorToast, warning: showWarningToast } = useToast();
const { statusLabel } = useStatusLabels();

watch(() => page.props.flash, (flash) => {
  if (flash?.success) showSuccessToast(flash.success);
  if (flash?.warning) showWarningToast(flash.warning, 8000);
  if (flash?.error) showErrorToast(flash.error);
}, { deep: true });

const CREW_FIELDS = ['vehicle_id', 'driver_id', 'field_supervisor_id'];

const drafts = ref({});
const unitsOf = (list) => (list ?? []).map((u) => ({ vehicle_id: u.vehicle_id ?? null, driver_id: u.driver_id ?? null }));
const crewOf = (mv) => ({
  ...Object.fromEntries(CREW_FIELDS.map((f) => [f, mv[f] ?? null])),
  units: unitsOf(mv.units),
  supervisors: (mv.extra_supervisors ?? []).map((s) => s.id),
});
// Blank extra rows are dropped on save, so they don't count as a change.
const filledUnits = (list) => unitsOf(list).filter((u) => u.vehicle_id || u.driver_id);
const filledSupervisors = (list) => [...new Set((list ?? []).filter(Boolean))].sort((a, b) => a - b);
// A draft holds supervisor ids; a movement row holds {id, name} objects.
const supervisorIdsOf = (x) => x?.supervisors ?? (x?.extra_supervisors ?? []).map((s) => s.id);
const sameCrew = (a, b) => CREW_FIELDS.every((f) => (a?.[f] ?? null) === (b?.[f] ?? null))
  && JSON.stringify(filledUnits(a?.units)) === JSON.stringify(filledUnits(b?.units))
  && JSON.stringify(filledSupervisors(supervisorIdsOf(a))) === JSON.stringify(filledSupervisors(supervisorIdsOf(b)));

const extraRowIndexes = (mv) => Array.from(
  { length: Math.max(drafts.value[mv.id].units.length, drafts.value[mv.id].supervisors.length) },
  (_, i) => i,
);

function addSupervisor(mv) {
  drafts.value[mv.id].supervisors.push(null);
}

function removeSupervisor(mv, index) {
  drafts.value[mv.id].supervisors.splice(index, 1);
}

function addUnit(mv) {
  drafts.value[mv.id].units.push({ vehicle_id: null, driver_id: null });
}

function removeUnit(mv, index) {
  drafts.value[mv.id].units.splice(index, 1);
}

// Saving one row reloads the list; keep unsaved edits on the other rows.
watch(() => props.movements, (list, oldList = []) => {
  const before = Object.fromEntries(oldList.map((mv) => [mv.id, mv]));
  const prev = drafts.value;
  drafts.value = Object.fromEntries(list.map((mv) => {
    const edited = prev[mv.id] && before[mv.id] && !sameCrew(prev[mv.id], before[mv.id]);
    return [mv.id, edited ? prev[mv.id] : crewOf(mv)];
  }));
}, { immediate: true });

const isDirty = (mv) => !sameCrew(drafts.value[mv.id], mv);
const isUnassigned = (mv) => CREW_FIELDS.some((f) => !mv[f]);
// Last night's runs are only there to reveal overnight clashes.
const dayMovements = computed(() => props.movements.filter((mv) => !mv.carry_over));
const unassignedCount = computed(() => dayMovements.value.filter(isUnassigned).length);
const clashTexts = (mv) => Object.values(mv.clashes ?? {}).flat();
const hasClash = (mv) => clashTexts(mv).length > 0;
const clashCount = computed(() => dayMovements.value.filter(hasClash).length);

const filters = [
  { value: 'all', label: 'All' },
  { value: 'unassigned', label: 'Needs crew' },
  { value: 'conflict', label: 'Has clash' },
];
const activeFilter = ref('all');
const search = ref('');

const searched = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.movements.filter((mv) => !q || [mv.code, mv.team, mv.team_code, mv.from, mv.to, mv.flight_number]
    .some((v) => (v || '').toLowerCase().includes(q)));
});

const filtered = computed(() => searched.value
  .filter((mv) => !mv.carry_over)
  .filter((mv) => activeFilter.value === 'all'
    || (activeFilter.value === 'unassigned' && isUnassigned(mv))
    || (activeFilter.value === 'conflict' && hasClash(mv))));

const views = [
  { value: 'table', label: 'Table' },
  { value: 'roster', label: 'Day timeline' },
  { value: 'week', label: 'Week matrix' },
];
const view = ref(localStorage.getItem('crew.view') || 'table');
watch(view, (v) => localStorage.setItem('crew.view', v));
const tab = ref(ROLES[localStorage.getItem('crew.tab')] ? localStorage.getItem('crew.tab') : 'driver');
watch(tab, (v) => localStorage.setItem('crew.tab', v));

const resourcesFor = (key) => ({ driver: props.drivers, vehicle: props.vehicles, supervisor: props.supervisors }[key] ?? []);

const selectedId = ref(null);
const selected = computed(() => props.movements.find((mv) => mv.id === selectedId.value) ?? null);

function spanOf(mv) {
  const start = minutesFrom(selectedDate.value, mv.span_start);
  if (start == null) return null;
  return [start, Math.max(minutesFrom(selectedDate.value, mv.span_end) ?? start + 30, start + 15)];
}

// Flags options already booked on an overlapping movement of this day.
function busyNote(key, resourceId, mv) {
  const field = ROLES[key].idField;
  const mine = spanOf(mv);
  if (!mine) return '';
  const other = props.movements.find((o) => {
    if (o.id === mv.id) return false;
    const extra = key === 'supervisor'
      ? (o.extra_supervisors ?? []).map((s) => s.id)
      : (o.units ?? []).map((u) => u[field]);
    if (o[field] !== resourceId && !extra.includes(resourceId)) return false;
    const span = spanOf(o);
    return span && span[0] < mine[1] && mine[0] < span[1];
  });
  if (!other) return '';
  const [s, e] = spanOf(other);
  return ` — busy ${clockLabel(s)}–${clockLabel(e)} (${other.code})`;
}

const saving = ref(null);

function save(mv) {
  saving.value = mv.id;
  router.patch(`/movements/${mv.id}/crew`, {
    ...drafts.value[mv.id],
    units: filledUnits(drafts.value[mv.id].units),
    supervisors: filledSupervisors(drafts.value[mv.id].supervisors),
  }, {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => showErrorToast(Object.values(errors)[0] ?? 'Could not save the crew.'),
    onFinish: () => { saving.value = null; },
  });
}

function vehicleLabel(v) {
  const name = v.code || v.plate_number || v.vehicle_type || `#${v.id}`;
  return v.capacity ? `${name} (${v.capacity} seats)` : name;
}

const jobTones = { 'in-progress': 'live', completed: 'ok', dispatched: 'primary', cancelled: 'danger' };
const jobTone = (s) => jobTones[s] ?? 'neutral';

function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const selectedDate = ref(props.date || todayIso());
watch(() => props.date, (v) => { if (v) selectedDate.value = v; });

const dateLabel = computed(() => new Date(`${selectedDate.value}T00:00:00`)
  .toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }));

function onDateChange(dateStr) {
  selectedDate.value = dateStr;
  selectedId.value = null;
  router.get('/crew-assignment', { date: dateStr }, {
    preserveState: true,
    preserveScroll: true,
    only: ['movements', 'date', 'week'],
  });
}

const stripDays = computed(() => Object.fromEntries(props.days.map((d) => [d.date, d])));

function isoOf(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

// Monday-based week around the selected date, like the reference roster.
const weekDates = computed(() => {
  const d = new Date(`${selectedDate.value}T00:00:00`);
  const monday = new Date(d.getFullYear(), d.getMonth(), d.getDate() - ((d.getDay() + 6) % 7));
  return Array.from({ length: 7 }, (_, i) => new Date(monday.getFullYear(), monday.getMonth(), monday.getDate() + i));
});

const weekLabel = computed(() => weekDates.value[0].toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }));

const weekTabs = computed(() => weekDates.value.map((date) => {
  const key = isoOf(date);
  const info = stripDays.value[key];
  const label = date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }).replace(',', '');
  return {
    date: key,
    label: key === todayIso() ? `${label} · Today` : label,
    total: info ? info.total : null,
    unassigned: info?.unassigned ?? 0,
    clashes: info?.clashes ?? 0,
  };
}));

function shiftWeek(days) {
  const d = new Date(`${selectedDate.value}T00:00:00`);
  onDateChange(isoOf(new Date(d.getFullYear(), d.getMonth(), d.getDate() + days)));
}

const selectedSpan = computed(() => {
  const span = selected.value && spanOf(selected.value);
  if (!span) return { time: '--:--', dur: '—' };
  return { time: `${clockLabel(span[0])}–${clockLabel(span[1])}`, dur: duration(span[1] - span[0]) };
});
</script>

<style scoped>
.empty-state-full {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 60vh; text-align: center;
}
.empty-state-title { font-size: 24px; font-weight: 700; color: var(--ink); margin-bottom: 8px; }
.empty-state-text { font-size: 14px; color: var(--ink3); max-width: 400px; }

.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.crew-page { display: flex; flex-direction: column; gap: 16px; }
.rh { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
.rh-title { display: flex; flex-direction: column; gap: 4px; }
.rh-kicker {
  font-family: var(--font-mono, monospace); font-size: 12px; letter-spacing: 0.08em;
  text-transform: uppercase; color: var(--ink3);
}
.rh-h1 { margin: 0; font-size: 28px; font-weight: 600; letter-spacing: -0.01em; color: var(--ink); }
.rh-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rh-seg { display: inline-flex; gap: 4px; background: var(--panel); border: 1px solid var(--border); padding: 4px; border-radius: 8px; }
.rh-seg-btn {
  border: 0; cursor: pointer; font-size: 14px; font-weight: 500; padding: 7px 14px;
  border-radius: 6px; background: transparent; color: var(--ink2);
}
.rh-seg-btn:hover { color: var(--ink); }
.rh-seg-btn--active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }

/* Week tabs */
.wn { display: grid; grid-template-columns: 30px repeat(7, minmax(0, 1fr)) 30px; gap: 6px; }
.wn-nav {
  border: 1px solid var(--border); background: var(--surface); border-radius: 8px;
  font-size: 18px; color: var(--ink2); cursor: pointer;
}
.wn-nav:hover { background: var(--panel); }
.wn-day {
  cursor: pointer; text-align: left; border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  border-radius: 8px; padding: 8px 12px; display: flex; flex-direction: column; gap: 2px; min-width: 0;
}
.wn-day:hover { background: var(--panel); }
.wn-day--empty { background: var(--panel); border-style: dashed; color: var(--ink3); }
.wn-day--empty:hover { background: var(--bg); }
.wn-day--need { border-color: var(--warn); box-shadow: inset 0 0 0 1px var(--warn); }
.wn-day--ok { border-color: var(--ok); box-shadow: inset 0 0 0 1px var(--ok); }
.wn-day--active, .wn-day--active:hover { background: var(--ink); border-color: var(--ink); border-style: solid; color: var(--surface); }
.wn-label { display: flex; justify-content: space-between; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; white-space: nowrap; }
.wn-sub { display: flex; align-items: center; gap: 6px; font-size: 12px; white-space: nowrap; overflow: hidden; }
.wn-count { opacity: 0.75; overflow: hidden; text-overflow: ellipsis; }
.wn-crew { flex-shrink: 0; font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 10px; }
.wn-crew--need { background: var(--warn-soft); color: var(--warn); }
.wn-crew--ok { background: var(--ok-soft); color: var(--ok); }
.wn-conf {
  background: #c8322b; color: #fff; font-family: var(--font-mono, monospace);
  font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 10px;
}

.filter-tabs { display: flex; gap: 4px; }
.filter-tab {
  padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; cursor: pointer; color: var(--ink3); font-weight: 500;
}
.filter-tab:hover { background: var(--panel); color: var(--ink); }
.filter-tab--active { background: var(--accent); color: #fff; border-color: var(--accent); }

.crew-search {
  width: 100%; max-width: 300px; margin-left: auto;
  padding: 7px 10px; border: 1px solid var(--border); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px;
}
.sub-bad { color: var(--danger, #b91c1c); }

.crew-toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }

/* Movement panel */
.cap {
  position: fixed; right: 24px; bottom: 24px; z-index: 40; width: 340px; max-width: calc(100vw - 48px);
  max-height: calc(100vh - 48px); overflow-y: auto;
  display: flex; flex-direction: column; gap: 10px; padding: 16px;
  background: var(--surface); border: 1px solid var(--border-strong); border-radius: 10px;
  box-shadow: 0 12px 32px rgba(23, 25, 30, 0.16);
}
.cap-head { display: flex; justify-content: space-between; align-items: center; }
.cap-id { font-family: var(--font-mono, monospace); font-size: 15px; font-weight: 600; color: var(--ink); }
.cap-close {
  cursor: pointer; border: 0; background: var(--panel); border-radius: 6px;
  padding: 4px 10px; font-size: 12px; font-weight: 500; color: var(--ink);
}
.cap-who { display: flex; flex-direction: column; gap: 2px; }
.cap-who strong { font-size: 15px; color: var(--ink); }
.cap-who span { font-size: 13px; color: var(--ink3); }
.cap-grid { display: grid; grid-template-columns: auto 1fr; gap: 4px 12px; font-size: 13px; color: var(--ink); }
.cap-grid > span:nth-child(odd) { color: var(--ink3); }
.mono-v { font-family: var(--font-mono, monospace); }
.cap-issue { border-radius: 6px; padding: 8px 10px; font-size: 13px; font-weight: 500; }
.cap-issue--bad { background: var(--danger-soft); color: var(--danger-strong); }
.cap-issue--ok { background: var(--ok-soft); color: var(--ok); }
.cap-field { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.05em; }
.cap-field select {
  padding: 7px 8px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12.5px; text-transform: none; letter-spacing: 0; font-weight: 400;
}
.cap-hint { margin: 0; font-size: 11px; color: var(--ink3); }
.cap-units { display: flex; flex-direction: column; gap: 6px; padding-top: 6px; border-top: 1px solid var(--border); }
.cap-units-title { font-size: 11px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.05em; }
.cap-unit { display: grid; grid-template-columns: 1fr 1fr auto; gap: 6px; align-items: center; }
.cap-unit--single { grid-template-columns: 1fr auto; }
.unit-pair { display: flex; align-items: center; gap: 4px; }
.crew-table .unit-pair select { flex: 1; }
.cap-unit select {
  min-width: 0; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12px;
}
/* A movement's lead row, its extra-vehicle rows and the add row read as one block. */
.crew-table tr.crew-main-row td,
.crew-table tr.crew-unit-row td { border-bottom: none; }
.crew-table tr.crew-unit-row td { padding-top: 0; padding-bottom: 6px; }
.crew-table tr.crew-unit-row--add td { padding-bottom: 10px; border-bottom: 1px solid var(--border); }
.crew-table tr.crew-unit-row--add:last-child td { border-bottom: none; }
.unit-label { text-align: right; font-size: 11px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.05em; }
.unit-add {
  padding: 0; border: none; background: none;
  color: var(--accent); font-size: 12px; font-weight: 600; cursor: pointer; text-align: left;
}
.unit-remove {
  flex-shrink: 0; width: 24px; height: 24px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink3); font-size: 11px; cursor: pointer;
}
.unit-remove:hover { color: var(--danger, #b91c1c); border-color: var(--danger, #b91c1c); }
.cap-actions { display: flex; justify-content: flex-end; gap: 8px; }
.cap-reset {
  padding: 6px 12px; border-radius: 6px; border: 1px solid var(--border); background: var(--surface);
  color: var(--ink2); font-size: 12.5px; cursor: pointer;
}
.cap-reset:disabled { opacity: 0.4; cursor: default; }
.crew-empty { padding: 40px; text-align: center; color: var(--ink3); font-size: 13px; }

.crew-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow-x: auto;
}
.crew-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.crew-table th {
  padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink3);
  border-bottom: 1px solid var(--border); background: var(--panel); white-space: nowrap;
}
.crew-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.crew-table tr:last-child td { border-bottom: none; }
.crew-table select {
  width: 100%; min-width: 150px; padding: 6px 8px;
  border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12.5px;
}

.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink); font-weight: 600; }
.sub { color: var(--ink3); font-size: 12px; }
.team-badge-sm {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 28px; height: 22px; padding: 0 4px; border-radius: 6px;
  background: var(--accent-soft); color: var(--accent-fg); font-size: 9px; font-weight: 700;
}

.actions { text-align: right; white-space: nowrap; }
.save-btn {
  padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer;
  background: var(--accent); color: #fff; font-size: 12.5px; font-weight: 600;
}
.save-btn:disabled { opacity: 0.4; cursor: default; }

/* Cards on phones: one movement per block, labels from data-label. */
@media (max-width: 767px) {
  .wn { grid-template-columns: 30px repeat(7, 120px) 30px; overflow-x: auto; }
  .crew-card { overflow: visible; background: none; border: none; }
  .crew-table thead { display: none; }
  .crew-table, .crew-table tbody, .crew-table tr, .crew-table td { display: block; width: 100%; }
  .crew-table tr {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 8px 0; margin-bottom: 10px;
  }
  .crew-table td { border: none; padding: 6px 12px; }
  .crew-table td[data-label]::before {
    content: attr(data-label); display: block;
    font-size: 10.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--ink4); margin-bottom: 3px;
  }
  .crew-table select { min-height: 40px; font-size: 15px; }
  .save-btn { width: 100%; min-height: 40px; }
  /* Extra-vehicle and add rows continue the movement's card instead of starting new ones. */
  .crew-table tr.crew-main-row { margin-bottom: 0; border-bottom: none; border-radius: 10px 10px 0 0; }
  .crew-table tr.crew-unit-row { margin: 0; padding: 0; border-top: none; border-bottom: none; border-radius: 0; }
  .crew-table tr.crew-unit-row--add { margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--border); border-radius: 0 0 10px 10px; }
  .crew-table tr.crew-unit-row td:empty { display: none; }
  .unit-label { text-align: left; }
}
</style>
