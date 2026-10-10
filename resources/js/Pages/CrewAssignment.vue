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
          <h1 class="rh-h1">{{ view === 'table' ? dayTitle : `${ROLES[tab].singular} roster` }}</h1>
          <p class="page-sub">
            {{ dayMovements.length }} movement{{ dayMovements.length === 1 ? '' : 's' }} · {{ unassignedCount }} need a crew
            <template v-if="clashCount"> · <b class="sub-bad">{{ clashCount }} with a clash</b></template>
            · {{ dateLabel }}
          </p>
        </div>
        <div class="rh-actions">
          <RefreshButton :only="['movements', 'date', 'days', 'week', 'vehicles', 'drivers', 'supervisors', 'providers']" />
          <a class="rh-export" :href="`/crew-assignment/export?date=${selectedDate}`">Export to Excel</a>
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
          :class="['wn-day', { 'wn-day--active': d.date === selectedDate, 'wn-day--empty': !d.total }]"
          @click="onDateChange(d.date)">
          <span class="wn-label">{{ d.label }}<span v-if="d.clashes" class="wn-conf">{{ d.clashes }} clash</span></span>
          <span class="wn-bar"><span :class="['wn-fill', { 'wn-fill--done': d.total && !d.unassigned }]" :style="{ width: `${d.pct}%` }" /></span>
          <span class="wn-sub">{{ d.total ? `${d.total - d.unassigned}/${d.total} crewed` : 'No movements' }}</span>
        </button>
        <button type="button" class="wn-nav" aria-label="Next week" @click="shiftWeek(7)">›</button>
        <MonthCalendar :model-value="selectedDate" :counts="dayTotals" @update:model-value="onDateChange" />
      </nav>

      <div class="crew-toolbar">
        <div v-if="view !== 'table'" class="rh-seg">
          <button v-for="(r, key) in ROLES" :key="key" type="button"
            :class="['rh-seg-btn', { 'rh-seg-btn--active': tab === key }]" @click="tab = key">{{ r.plural }}</button>
        </div>
        <div v-if="view === 'table'" class="filter-tabs">
          <button v-for="f in filters" :key="f.value" type="button"
            :class="['filter-tab', activeFilter === f.value ? 'filter-tab--active' : '', f.value === 'clash' && f.count ? 'filter-tab--alert' : '']"
            @click="activeFilter = f.value">
            {{ f.label }}<span class="filter-count">{{ f.count }}</span>
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
        <div class="cg">
          <div class="cg-row cg-head">
            <span>Movement</span><span>Team &amp; operator</span><span>Pax</span><span>Vehicle &amp; driver</span><span>Supervisor</span><span>Status</span>
          </div>

          <div v-for="mv in filtered" :key="mv.id" :class="['cg-item', `cg-item--${statusOf(mv)}`]">
            <div class="cg-row">
              <div class="cg-col cg-col--tight">
                <div class="cg-idline">
                  <span class="mono cg-id">{{ mv.code }}</span>
                  <span v-if="isDirty(mv)" class="cg-dot" title="Unsaved changes" />
                </div>
                <span class="cg-time">{{ mv.start ?? '--:--' }}–{{ mv.end ?? '--:--' }}</span>
                <span class="sub">{{ mv.kind }} · {{ mv.from }} → {{ mv.to }}<template v-if="mv.flight_number"> · {{ mv.flight_number }}</template></span>
                <status-pill v-if="mv.job_status" :tone="jobTone(mv.job_status)">{{ statusLabel(mv.job_status) }}</status-pill>
              </div>

              <div class="cg-col">
                <div class="cg-team">
                  <span class="team-badge-sm">{{ mv.team_code }}</span>
                  <span class="cg-team-name">{{ mv.team }}</span>
                </div>
                <select v-if="providers.length" class="cg-select" :value="mv.fleet_provider_id ?? ''"
                  :aria-label="`Provider for ${mv.code}`" @change="setProvider(mv, $event.target.value)">
                  <option value="">No provider</option>
                  <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <span v-else-if="mv.provider" class="sub">{{ mv.provider }}</span>
              </div>

              <div class="cg-col cg-col--pax">
                <span :class="['cg-pax', { 'cg-bad': seatsShort(mv) }]">{{ mv.pax || '—' }}</span>
                <span class="cg-cap"><span :class="['cg-cap-fill', { 'cg-cap-fill--bad': seatsShort(mv) }]" :style="{ width: capPct(mv) }" /></span>
                <span :class="['sub', { 'cg-bad': seatsShort(mv) }]">{{ capLabel(mv) }}</span>
              </div>

              <div class="cg-col">
                <div v-for="(row, i) in crewRows(drafts[mv.id])" :key="i" class="cg-crew">
                  <select v-model="crewRow(mv, i).vehicle_id" :class="fieldClass(mv, 'vehicle', row.vehicle_id)" :aria-label="`Vehicle ${i + 1} for ${mv.code}`">
                    <option :value="null">Select vehicle</option>
                    <option v-for="v in optionsFor('vehicle', mv)" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}{{ busyNote('vehicle', v.id, mv) }}</option>
                  </select>
                  <select v-model="crewRow(mv, i).driver_id" :class="fieldClass(mv, 'driver', row.driver_id)" :aria-label="`Driver ${i + 1} for ${mv.code}`">
                    <option :value="null">Select driver</option>
                    <option v-for="d in optionsFor('driver', mv)" :key="d.id" :value="d.id">{{ d.name }}{{ busyNote('driver', d.id, mv) }}</option>
                  </select>
                  <button v-if="drafts[mv.id].units.length" type="button" class="cg-x" :title="`Remove vehicle ${i + 1}`" :aria-label="`Remove vehicle ${i + 1}`" @click="removeCrewRow(mv, i)">×</button>
                  <span v-else />
                </div>
                <button type="button" class="unit-add" @click="addUnit(mv)">+ Add vehicle</button>
              </div>

              <div class="cg-col">
                <select v-model="drafts[mv.id].field_supervisor_id" :class="fieldClass(mv, 'supervisor', drafts[mv.id].field_supervisor_id)" :aria-label="`Supervisor for ${mv.code}`">
                  <option :value="null">Select supervisor</option>
                  <option v-for="s in optionsFor('supervisor', mv)" :key="s.id" :value="s.id">{{ s.name }}{{ busyNote('supervisor', s.id, mv) }}</option>
                </select>
                <div v-for="(sid, i) in drafts[mv.id].supervisors" :key="`s${i}`" class="cg-crew cg-crew--sup">
                  <select v-model="drafts[mv.id].supervisors[i]" :class="fieldClass(mv, 'supervisor', sid)" :aria-label="`Extra supervisor ${i + 2} for ${mv.code}`">
                    <option :value="null">Select supervisor</option>
                    <option v-for="s in optionsFor('supervisor', mv)" :key="s.id" :value="s.id">{{ s.name }}{{ busyNote('supervisor', s.id, mv) }}</option>
                  </select>
                  <button type="button" class="cg-x" :title="`Remove supervisor ${i + 2}`" :aria-label="`Remove supervisor ${i + 2}`" @click="removeSupervisor(mv, i)">×</button>
                </div>
                <button type="button" class="unit-add" @click="addSupervisor(mv)">+ Add supervisor</button>
              </div>

              <div class="cg-col cg-col--status">
                <span :class="['cg-pill', `cg-pill--${statusOf(mv)}`]">{{ STATUS_LABELS[statusOf(mv)] }}</span>
                <span v-for="m in missingFor(mv)" :key="m" class="cg-missing">{{ m }}</span>
              </div>
            </div>

            <div v-if="clashMap[mv.id]?.length" class="cg-clash" role="alert">
              <div v-for="(c, i) in clashMap[mv.id]" :key="i" class="cg-clash-line">
                <span v-if="c.name"><strong>{{ c.name }}</strong> is also on {{ c.otherCode }} ({{ c.otherTime }}) — times overlap.</span>
                <span v-else>{{ c.text }}</span>
                <button v-if="c.fix" type="button" class="cg-fix" @click="swapResource(mv, c.role, c.id, c.fix.id)">Swap to {{ c.role === 'vehicle' ? vehicleName(c.fix) : c.fix.name }}</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="view === 'table' && (dirtyList.length || justSaved)" class="savebar" role="status">
        <span>{{ dirtyList.length ? `${dirtyList.length} movement${dirtyList.length === 1 ? '' : 's'} changed` : 'All changes saved' }}</span>
        <div v-if="dirtyList.length" class="savebar-actions">
          <button type="button" class="savebar-discard" :disabled="savingAll" @click="discardAll">Discard</button>
          <button type="button" class="savebar-save" :disabled="savingAll" @click="saveAll">{{ savingAll ? 'Saving…' : 'Save changes' }}</button>
        </div>
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
            <option v-for="o in optionsFor(key, selected)" :key="o.id" :value="o.id">
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
              <option v-for="o in optionsFor('vehicle', selected)" :key="o.id" :value="o.id">{{ vehicleLabel(o) }}{{ busyNote('vehicle', o.id, selected) }}</option>
            </select>
            <select v-model="unit.driver_id" :aria-label="`Driver of extra vehicle ${i + 2}`">
              <option :value="null">Driver…</option>
              <option v-for="o in optionsFor('driver', selected)" :key="o.id" :value="o.id">{{ o.name }}{{ busyNote('driver', o.id, selected) }}</option>
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
              <option v-for="o in optionsFor('supervisor', selected)" :key="o.id" :value="o.id">{{ o.name }}{{ busyNote('supervisor', o.id, selected) }}</option>
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
import MonthCalendar from '../Components/MonthCalendar.vue';
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
  providers: { type: Array, default: () => [] },
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
// Last night's runs are only there to reveal overnight clashes.
const dayMovements = computed(() => props.movements.filter((mv) => !mv.carry_over));
const hasClash = (mv) => (clashMap.value[mv.id]?.length ?? 0) > 0;
const clashCount = computed(() => dayMovements.value.filter(hasClash).length);

const STATUS_LABELS = { clash: 'Clash', needs: 'Needs crew', ready: 'Ready' };
const ROLE_KEYS = ['vehicle', 'driver', 'supervisor'];

const crewRows = (d) => [{ vehicle_id: d.vehicle_id, driver_id: d.driver_id }, ...d.units];
// Row 0 is the lead vehicle and driver on the movement; the rest are its extra units.
const crewRow = (mv, i) => (i === 0 ? drafts.value[mv.id] : drafts.value[mv.id].units[i - 1]);

function idsOf(d, role) {
  const list = role === 'supervisor'
    ? [d.field_supervisor_id, ...d.supervisors]
    : crewRows(d).map((r) => r[`${role}_id`]);
  return list.filter(Boolean);
}

const overlaps = (a, b) => !!a && !!b && a[0] < b[1] && b[0] < a[1];
const spans = computed(() => Object.fromEntries(props.movements.map((mv) => [mv.id, spanOf(mv)])));

// Checked against everyone's current picks, saved or not, so a clash shows the moment it is made.
function busyWith(role, resourceId, mv) {
  const mine = spans.value[mv.id];
  return props.movements.find((o) => o.id !== mv.id && drafts.value[o.id]
    && overlaps(mine, spans.value[o.id]) && idsOf(drafts.value[o.id], role).includes(resourceId)) ?? null;
}

const sortedIds = (d, role) => idsOf(d, role).sort((a, b) => a - b).join(',');
const roleChanged = (mv, role) => !!drafts.value[mv.id] && sortedIds(drafts.value[mv.id], role) !== sortedIds(crewOf(mv), role);
const anyChanged = computed(() => Object.fromEntries(ROLE_KEYS.map((role) => [role, props.movements.some((mv) => roleChanged(mv, role))])));

const vehicleById = computed(() => Object.fromEntries(props.vehicles.map((v) => [v.id, v])));

function resourceName(role, id) {
  if (role === 'vehicle') return vehicleById.value[id] ? vehicleName(vehicleById.value[id]) : `#${id}`;
  return resourcesFor(role).find((o) => o.id === id)?.name ?? `#${id}`;
}

// A free resource of the same role that could take the clashing one's place.
function fixFor(mv, role) {
  const used = idsOf(drafts.value[mv.id], role);
  return optionsFor(role, mv).find((o) => !used.includes(o.id) && !busyWith(role, o.id, mv)
    && (role !== 'vehicle' || (o.capacity ?? 0) >= (mv.pax ?? 0))) ?? null;
}

const liveClashes = computed(() => Object.fromEntries(props.movements.map((mv) => {
  const d = drafts.value[mv.id];
  const found = [];
  for (const role of d ? ROLE_KEYS : []) {
    for (const id of new Set(idsOf(d, role))) {
      const other = busyWith(role, id, mv);
      if (!other) continue;
      const [s, e] = spans.value[other.id];
      found.push({ role, id, name: resourceName(role, id), otherCode: other.code, otherTime: `${clockLabel(s)}–${clockLabel(e)}`, fix: fixFor(mv, role) });
    }
  }
  return [mv.id, found];
})));

// Once a role is edited anywhere the server's saved-state clashes are stale, so the live check takes over for it.
const clashMap = computed(() => Object.fromEntries(props.movements.map((mv) => [mv.id, ROLE_KEYS.flatMap((role) => {
  const live = (liveClashes.value[mv.id] ?? []).filter((c) => c.role === role);
  if (anyChanged.value[role]) return live;
  const texts = mv.clashes?.[role] ?? [];
  if (!texts.length) return [];
  return live.length ? live : texts.map((text) => ({ role, text }));
})])));

const clashText = (c) => c.text ?? `${c.name} is also on ${c.otherCode} (${c.otherTime}) — times overlap.`;
const clashTexts = (mv) => (clashMap.value[mv.id] ?? []).map(clashText);

const seatsOf = (mv) => crewRows(drafts.value[mv.id]).reduce((n, r) => n + (vehicleById.value[r.vehicle_id]?.capacity ?? 0), 0);
const seatsShort = (mv) => mv.pax > 0 && seatsOf(mv) > 0 && seatsOf(mv) < mv.pax;
const capPct = (mv) => `${seatsOf(mv) ? Math.min(100, Math.round((mv.pax ?? 0) / seatsOf(mv) * 100)) : 0}%`;
const capLabel = (mv) => (seatsOf(mv) ? `of ${seatsOf(mv)} seats` : (mv.pax ? 'no seats yet' : 'no pax yet'));

function missingOf(mv) {
  const d = drafts.value[mv.id];
  const rows = crewRows(d);
  const missing = [];
  if (rows.some((r) => !r.vehicle_id)) missing.push('Needs vehicle');
  if (rows.some((r) => !r.driver_id)) missing.push('Needs driver');
  if (seatsShort(mv)) missing.push(`${mv.pax - seatsOf(mv)} seats short`);
  if (!d.field_supervisor_id) missing.push('Needs supervisor');
  return missing;
}

const rowInfo = computed(() => Object.fromEntries(dayMovements.value.map((mv) => {
  const missing = missingOf(mv);
  return [mv.id, { missing, status: hasClash(mv) ? 'clash' : (missing.length ? 'needs' : 'ready') }];
})));
const statusOf = (mv) => rowInfo.value[mv.id]?.status ?? 'ready';
const missingFor = (mv) => rowInfo.value[mv.id]?.missing ?? [];

const counts = computed(() => {
  const c = { all: dayMovements.value.length, clash: 0, needs: 0, ready: 0 };
  for (const mv of dayMovements.value) c[statusOf(mv)] += 1;
  return c;
});
const unassignedCount = computed(() => counts.value.needs);

const fieldClass = (mv, role, id) => ['cg-select', {
  'cg-select--bad': !!id && (clashMap.value[mv.id] ?? []).some((c) => c.role === role && c.id === id),
  'cg-select--empty': !id,
}];

function removeCrewRow(mv, i) {
  const d = drafts.value[mv.id];
  if (i === 0) {
    const next = d.units.shift();
    d.vehicle_id = next?.vehicle_id ?? null;
    d.driver_id = next?.driver_id ?? null;
  } else {
    d.units.splice(i - 1, 1);
  }
}

function swapResource(mv, role, oldId, newId) {
  const d = drafts.value[mv.id];
  if (role === 'supervisor') {
    if (d.field_supervisor_id === oldId) d.field_supervisor_id = newId;
    else d.supervisors = d.supervisors.map((s) => (s === oldId ? newId : s));
    return;
  }
  const key = `${role}_id`;
  crewRows(d).forEach((r, i) => {
    if (r[key] === oldId) crewRow(mv, i)[key] = newId;
  });
}

const filters = computed(() => [
  { value: 'all', label: 'All' },
  { value: 'clash', label: 'Clashes' },
  { value: 'needs', label: 'Needs crew' },
  { value: 'ready', label: 'Ready' },
].map((f) => ({ ...f, count: counts.value[f.value] })));
const activeFilter = ref('all');
const search = ref('');

const searched = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.movements.filter((mv) => !q || [mv.code, mv.team, mv.team_code, mv.from, mv.to, mv.flight_number]
    .some((v) => (v || '').toLowerCase().includes(q)));
});

const filtered = computed(() => searched.value
  .filter((mv) => !mv.carry_over)
  .filter((mv) => activeFilter.value === 'all' || statusOf(mv) === activeFilter.value));

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

// A movement belongs to one provider, so only that provider's people and fleet are offered (plus whoever is already on it).
function optionsFor(key, mv) {
  const list = resourcesFor(key);
  if (!mv?.fleet_provider_id) return list;
  const field = key === 'supervisor' ? 'fleet_provider_id' : 'provider_id';
  const idField = ROLES[key].idField;
  const current = key === 'supervisor'
    ? [mv[idField], ...(mv.extra_supervisors ?? []).map((s) => s.id)]
    : [mv[idField], ...(mv.units ?? []).map((u) => u[idField])];
  return list.filter((o) => o[field] === mv.fleet_provider_id || current.includes(o.id));
}

function setProvider(mv, value) {
  router.patch(`/movements/${mv.id}/provider`, { fleet_provider_id: value ? Number(value) : null }, {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => showErrorToast(Object.values(errors)[0] ?? 'Could not change the provider.'),
  });
}

const selectedId = ref(null);
const selected = computed(() => props.movements.find((mv) => mv.id === selectedId.value) ?? null);

function spanOf(mv) {
  const start = minutesFrom(selectedDate.value, mv.span_start);
  if (start == null) return null;
  return [start, Math.max(minutesFrom(selectedDate.value, mv.span_end) ?? start + 30, start + 15)];
}

// Flags options already booked on an overlapping movement of this day.
function busyNote(key, resourceId, mv) {
  const other = busyWith(key, resourceId, mv);
  if (!other) return '';
  const [s, e] = spans.value[other.id];
  return ` — busy ${clockLabel(s)}–${clockLabel(e)} (${other.code})`;
}

const saving = ref(null);
const savingAll = ref(false);
const justSaved = ref(false);
let savedTimer;

const crewPayload = (mv) => ({
  ...drafts.value[mv.id],
  units: filledUnits(drafts.value[mv.id].units),
  supervisors: filledSupervisors(drafts.value[mv.id].supervisors),
});

const patchCrew = (mv) => new Promise((resolve) => {
  let ok = true;
  router.patch(`/movements/${mv.id}/crew`, crewPayload(mv), {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => { ok = false; showErrorToast(Object.values(errors)[0] ?? `Could not save the crew for ${mv.code}.`); },
    onFinish: () => resolve(ok),
  });
});

function save(mv) {
  saving.value = mv.id;
  patchCrew(mv).finally(() => { saving.value = null; });
}

const dirtyList = computed(() => props.movements.filter((mv) => drafts.value[mv.id] && isDirty(mv)));

// One request at a time: a second visit would cancel the first.
async function saveAll() {
  savingAll.value = true;
  let allSaved = true;
  for (const mv of [...dirtyList.value]) allSaved = (await patchCrew(mv)) && allSaved;
  savingAll.value = false;
  if (!allSaved) return;
  justSaved.value = true;
  clearTimeout(savedTimer);
  savedTimer = setTimeout(() => { justSaved.value = false; }, 2200);
}

function discardAll() {
  drafts.value = Object.fromEntries(props.movements.map((mv) => [mv.id, crewOf(mv)]));
}

function vehicleName(v) {
  return v.code || v.plate_number || v.vehicle_type || `#${v.id}`;
}

function vehicleLabel(v) {
  const name = vehicleName(v);
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

const dayTitle = computed(() => new Date(`${selectedDate.value}T00:00:00`)
  .toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }));

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
const dayTotals = computed(() => Object.fromEntries(props.days.map((d) => [d.date, d.total])));

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
  // The loaded day is counted live from the drafts; the others come from the saved state.
  const live = key === props.date;
  const total = live ? counts.value.all : (info ? info.total : null);
  const ready = live ? counts.value.ready : (info ? info.total - info.unassigned : 0);
  return {
    date: key,
    label: key === todayIso() ? `${label} · Today` : label,
    total,
    unassigned: total ? total - ready : 0,
    clashes: live ? counts.value.clash : (info?.clashes ?? 0),
    pct: total ? Math.round((ready / total) * 100) : 0,
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
.rh-export {
  display: inline-flex; align-items: center; padding: 7px 14px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  font-size: 13px; font-weight: 600; text-decoration: none;
}
.rh-export:hover { background: var(--panel); }
.prov-select { font-size: 12px; padding: 2px 4px; max-width: 160px; }
.rh-seg { display: inline-flex; gap: 4px; background: var(--panel); border: 1px solid var(--border); padding: 4px; border-radius: 8px; }
.rh-seg-btn {
  border: 0; cursor: pointer; font-size: 14px; font-weight: 500; padding: 7px 14px;
  border-radius: 6px; background: transparent; color: var(--ink2);
}
.rh-seg-btn:hover { color: var(--ink); }
.rh-seg-btn--active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }

/* Week tabs */
.wn { display: grid; grid-template-columns: 30px repeat(7, minmax(0, 1fr)) 30px auto; gap: 6px; }
.wn-nav {
  border: 1px solid var(--border); background: var(--surface); border-radius: 8px;
  font-size: 18px; color: var(--ink2); cursor: pointer;
}
.wn-nav:hover { background: var(--panel); }
.wn-day {
  cursor: pointer; text-align: left; border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  border-radius: 10px; padding: 10px 12px; display: flex; flex-direction: column; gap: 8px; min-width: 0;
}
.wn-day:hover { background: var(--panel); }
.wn-day--empty { background: var(--panel); border-style: dashed; color: var(--ink3); }
.wn-day--empty:hover { background: var(--bg); }
.wn-day--active, .wn-day--active:hover { background: var(--ink); border-color: var(--ink); border-style: solid; color: var(--surface); }
.wn-label { display: flex; justify-content: space-between; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; white-space: nowrap; }
.wn-bar { height: 4px; border-radius: 2px; background: var(--border); overflow: hidden; }
.wn-day--active .wn-bar { background: rgba(255, 255, 255, 0.2); }
.wn-fill { display: block; height: 100%; background: var(--warn); }
.wn-fill--done { background: var(--ok); }
.wn-sub { font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; opacity: 0.75; }
.wn-conf {
  background: var(--danger, #c62828); color: #fff;
  font-size: 11px; font-weight: 600; padding: 1px 7px; border-radius: 99px;
}

.filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-tab {
  display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 12px 0 14px; border-radius: 99px;
  border: 1px solid var(--border); background: var(--surface); font-size: 14px; cursor: pointer; color: var(--ink2); font-weight: 500;
}
.filter-tab:hover { background: var(--panel); color: var(--ink); }
.filter-tab--active, .filter-tab--active:hover { background: var(--ink); color: var(--surface); border-color: var(--ink); }
.filter-count { min-width: 20px; padding: 1px 6px; border-radius: 99px; background: var(--panel); color: var(--ink3); font-size: 12px; font-weight: 600; text-align: center; }
.filter-tab--active .filter-count { background: rgba(255, 255, 255, 0.18); color: inherit; }
.filter-tab--alert:not(.filter-tab--active) .filter-count { background: var(--danger-soft); color: var(--danger-strong); }

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
.cap-unit select {
  min-width: 0; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12px;
}
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
  border-radius: 12px; overflow-x: auto;
}

/* One card per movement: a header row of labels, then a grid row per movement. */
.cg { min-width: 1180px; }
.cg-row {
  display: grid; align-items: start; gap: 20px;
  grid-template-columns: minmax(170px, 1fr) minmax(190px, 1.1fr) 110px minmax(380px, 2.4fr) minmax(190px, 1.1fr) 150px;
}
.cg-head {
  padding: 12px 20px; background: var(--panel); border-bottom: 1px solid var(--border); align-items: center;
  font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3);
}
.cg-item { border-bottom: 1px solid var(--border); }
.cg-item:last-child { border-bottom: none; }
.cg-item--clash { background: color-mix(in srgb, var(--danger-soft) 35%, var(--surface)); }
.cg-item > .cg-row { padding: 16px 20px; }
.cg-col { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.cg-col--tight { gap: 3px; align-items: flex-start; }
.cg-col--pax { gap: 6px; padding-top: 2px; }
.cg-col--status { gap: 6px; align-items: flex-start; }
.cg-idline { display: flex; align-items: center; gap: 8px; }
.cg-id { font-size: 14px; }
.cg-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
.cg-time { font-size: 14px; color: var(--ink); }
.cg-team { display: flex; align-items: center; gap: 8px; }
.cg-team-name { font-size: 14px; font-weight: 500; color: var(--ink); }
.cg-pax { font-size: 18px; font-weight: 600; color: var(--ink); }
.cg-cap { height: 4px; border-radius: 2px; background: var(--border); overflow: hidden; }
.cg-cap-fill { display: block; height: 100%; background: var(--ok); }
.cg-cap-fill--bad { background: var(--danger, #c62828); }
.cg-bad { color: var(--danger, #c62828); }

.cg-crew { display: grid; grid-template-columns: minmax(0, 1.2fr) minmax(0, 1fr) 28px; gap: 8px; align-items: center; }
.cg-crew--sup { grid-template-columns: minmax(0, 1fr) 28px; }
.cg-select {
  width: 100%; min-width: 0; height: 36px; padding: 0 8px; border: 1px solid var(--border); border-radius: 7px;
  background: var(--surface); color: var(--ink); font-size: 14px; cursor: pointer;
}
.cg-select--empty { border-color: var(--warn); background: var(--warn-soft); color: var(--warn); }
.cg-select--bad { border-color: var(--danger, #c62828); background: var(--danger-soft); color: var(--ink); }
.cg-x {
  width: 28px; height: 28px; border: 0; background: transparent; border-radius: 6px;
  color: var(--ink3); font-size: 16px; cursor: pointer;
}
.cg-x:hover { background: var(--panel); color: var(--ink); }

.cg-pill { font-size: 12px; font-weight: 600; padding: 3px 9px; border-radius: 99px; }
.cg-pill--clash { background: var(--danger, #c62828); color: #fff; }
.cg-pill--needs { background: var(--warn-soft); color: var(--warn); }
.cg-pill--ready { background: var(--ok-soft); color: var(--ok); }
.cg-missing { font-size: 12px; color: var(--warn); }

.cg-clash {
  margin: 0 20px 14px; padding: 10px 14px; border-radius: 8px; background: var(--danger-soft);
  display: flex; flex-direction: column; gap: 6px;
}
.cg-clash-line { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; font-size: 13px; color: var(--danger-strong); }
.cg-fix {
  height: 28px; padding: 0 12px; border: 1px solid var(--danger, #c62828); background: var(--surface); border-radius: 6px;
  color: var(--danger-strong); font-size: 13px; font-weight: 600; cursor: pointer;
}
.cg-fix:hover { background: var(--danger-soft); }

.savebar {
  position: sticky; bottom: 20px; align-self: center; z-index: 30;
  display: flex; align-items: center; gap: 16px; padding: 10px 10px 10px 20px;
  background: var(--ink); color: var(--surface); border-radius: 12px; box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18); font-size: 14px;
}
.savebar-actions { display: flex; gap: 8px; }
.savebar-discard, .savebar-save { height: 36px; padding: 0 16px; border-radius: 8px; font-size: 14px; cursor: pointer; }
.savebar-discard { border: 1px solid rgba(255, 255, 255, 0.3); background: transparent; color: inherit; }
.savebar-save { border: 0; background: var(--accent); color: #fff; font-weight: 600; }
.savebar-discard:disabled, .savebar-save:disabled { opacity: 0.5; cursor: default; }

.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink); font-weight: 600; }
.sub { color: var(--ink3); font-size: 12px; }
.team-badge-sm {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 28px; height: 22px; padding: 0 4px; border-radius: 6px;
  background: var(--accent-soft); color: var(--accent-fg); font-size: 9px; font-weight: 700;
}

.save-btn {
  padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer;
  background: var(--accent); color: #fff; font-size: 12.5px; font-weight: 600;
}
.save-btn:disabled { opacity: 0.4; cursor: default; }

/* Phones: one column per movement. */
@media (max-width: 767px) {
  .wn { grid-template-columns: 30px repeat(7, 120px) 30px auto; overflow-x: auto; }
  .crew-card { overflow: visible; }
  .cg { min-width: 0; }
  .cg-head { display: none; }
  .cg-row { grid-template-columns: 1fr; gap: 14px; }
  .cg-item > .cg-row { padding: 14px 16px; }
  .cg-clash { margin: 0 16px 14px; }
  .cg-select { height: 40px; font-size: 15px; }
  .savebar { width: calc(100% - 16px); justify-content: space-between; }
}
</style>
