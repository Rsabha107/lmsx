<template>
  <app-layout>
    <NoActiveEvent v-if="!hasActiveEvent" what="crew assignments" />

    <div v-else class="crew-page">
      <header class="rh">
        <div class="rh-title">
          <div class="rh-kicker">Crew assignment<template v-if="view !== 'table'"> · Week of {{ weekLabel }}</template></div>
          <h1 class="rh-h1">{{ view === 'table' ? dayTitle : `${ROLES[tab].singular} roster` }}</h1>
          <p class="rh-sum">
            <span>{{ counts.all }} movement{{ counts.all === 1 ? '' : 's' }}</span>
            <span v-if="counts.clash" class="rh-sum-item rh-sum-item--bad"><i />{{ counts.clash }} with a clash</span>
            <span v-if="counts.needs" class="rh-sum-item rh-sum-item--warn"><i />{{ counts.needs }} need crew</span>
            <span v-if="counts.ready || counts.done" class="rh-sum-item rh-sum-item--ok"><i />{{ counts.ready }} ready<template v-if="counts.done"> · {{ counts.done }} completed</template></span>
            <span v-if="multiEvent" class="rh-sum-events">{{ shownEvents.map((e) => e.short).join(' + ') }}</span>
          </p>
        </div>
        <div class="rh-actions">
          <details v-if="events.length > 1" ref="evPick" class="ev-pick" @keydown.esc="closeEvPick">
            <summary class="rh-btn" aria-label="Events shown">
              Shared crew: <strong>{{ shownEvents.length }} event{{ shownEvents.length === 1 ? '' : 's' }}</strong> <span class="rh-caret">▾</span>
            </summary>
            <div class="ev-pick-panel">
              <p class="ev-pick-hint">Include other events using this provider so buses, drivers and supervisors aren't double-booked.</p>
              <label v-for="e in events" :key="e.id" class="ev-pick-row">
                <input type="checkbox" :checked="eventIds.includes(e.id)" :disabled="e.id === activeEventId"
                  :aria-label="`Show ${e.name}`" @change="toggleEvent(e.id, $event.target.checked)" />
                <span class="ev-pick-name">{{ e.name }}</span>
                <small v-if="e.id === activeEventId">this event</small>
              </label>
            </div>
          </details>
          <RefreshButton :only="['movements', 'date', 'days', 'week', 'vehicles', 'drivers', 'supervisors', 'providers', 'eventIds', 'events']" />
          <a class="rh-btn" :href="exportHref" title="Export to Excel">Export</a>
          <div class="rh-seg">
            <button v-for="v in views" :key="v.value" type="button"
              :class="['rh-seg-btn', { 'rh-seg-btn--active': view === v.value }]" @click="view = v.value">{{ v.label }}</button>
          </div>
        </div>
      </header>

      <!-- The selected date's week, one tab per day -->
      <nav class="wn">
        <button type="button" class="wn-nav" aria-label="Previous week" @click="shiftWeek(-7)">‹</button>
        <div class="wn-days">
          <button v-for="d in weekTabs" :key="d.date" type="button"
            :class="['wn-day', { 'wn-day--active': d.date === selectedDate, 'wn-day--empty': !d.total }]"
            :aria-label="d.long" @click="onDateChange(d.date)">
            <span class="wn-top">
              <span class="wn-wd">{{ d.wd }}<template v-if="d.today"> · Today</template></span>
              <span v-if="d.clashes" class="wn-conf">{{ d.clashes }} clash</span>
            </span>
            <span class="wn-num">{{ d.num }}</span>
            <span class="wn-bar"><span :class="['wn-fill', { 'wn-fill--done': d.total && !d.unassigned }]" :style="{ width: `${d.pct}%` }" /></span>
            <span class="wn-sub">{{ d.total ? `${d.total - d.unassigned}/${d.total} crewed` : 'No movements' }}</span>
          </button>
        </div>
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
            :class="['filter-tab', { 'filter-tab--active': activeFilter === f.value }]"
            @click="activeFilter = f.value">
            {{ f.label }}<span class="filter-count">{{ f.count }}</span>
          </button>
        </div>
        <AskDaleelButton v-if="view === 'table' && counts.all" class="crew-ask" label="Brief me on this day"
          :question="`Brief me on crew gaps for ${selectedDate}: which movements are missing a vehicle, driver or supervisor, any double-bookings, and are we short of vehicles or drivers?`"
          :context="{ page: 'crew-assignment', date: selectedDate }" />
        <input v-if="view !== 'week'" v-model="search" class="crew-search" type="search" placeholder="Search movement, team or route" aria-label="Search movements" />
      </div>

      <CrewRoster v-if="view === 'roster'"
        :movements="searched" :date="selectedDate" :tab="tab"
        :resources="resourcesFor(tab)" :selected-id="selectedId" :searching="!!search.trim()"
        @select="(mv) => selectedId = mv.id" @today="onDateChange(todayIso())" />

      <CrewWeek v-else-if="view === 'week'"
        :week="week" :tab="tab" :resources="resourcesFor(tab)" :selected-date="selectedDate"
        @pick="(d) => { view = 'roster'; onDateChange(d); }" />

      <div v-else class="ct">
        <div class="ct-row ct-head">
          <span>Window</span><span>Movement</span><span>Crew</span><span class="ct-right">Status</span>
        </div>

        <div v-for="mv in filtered" :key="mv.id" :class="['ct-item', `ct-item--${statusOf(mv)}`]">
          <div class="ct-row">
            <div class="ct-win">
              <span class="ct-start">{{ mv.start ?? '--:--' }}</span>
              <span class="ct-end">→ {{ mv.end ?? '--:--' }}</span>
            </div>

            <div class="ct-mv">
              <div class="ct-team">
                <span class="ct-team-name">{{ mv.team || 'No team' }}</span>
                <span v-if="mv.team_code" class="ct-tag">{{ mv.team_code }}</span>
                <span v-if="mv.event" class="ct-tag ct-tag--event" :title="`Event: ${mv.event}`">{{ mv.event }}</span>
                <span v-if="isDirty(mv)" class="ct-dot" title="Unsaved changes" />
              </div>
              <span class="ct-route">{{ mv.from }} → {{ mv.to }}</span>
              <span class="ct-meta"><span class="ct-code">{{ mv.code }}</span> · {{ mv.kind }}<template v-if="mv.flight_number"> · {{ mv.flight_number }}</template></span>
              <span class="ct-meta">
                <select v-if="providers.length && !isLocked(mv)" class="ct-provider" :value="mv.fleet_provider_id ?? ''"
                  :aria-label="`Provider for ${mv.code}`" @change="setProvider(mv, $event.target.value)">
                  <option value="">No provider</option>
                  <option v-for="p in providers" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <template v-else>{{ mv.provider || 'No provider' }}</template>
                · <span :class="{ 'ct-short': seatsShort(mv) }">{{ seatsLabel(mv) }}</span>
              </span>
            </div>

            <div class="ct-crew">
              <div v-for="(row, i) in crewRows(drafts[mv.id])" :key="`v${i}`" class="ct-line">
                <span class="ct-label">{{ crewRows(drafts[mv.id]).length > 1 ? `Vehicle ${i + 1}` : 'Vehicle' }}</span>
                <CrewChip :model-value="row.vehicle_id" :options="chipOptions('vehicle', mv)" :display="chipText('vehicle', row.vehicle_id)"
                  placeholder="+ Assign vehicle" :bad="isBad(mv, 'vehicle', row.vehicle_id)" :locked="isLocked(mv)"
                  :aria-label="`Vehicle ${i + 1} for ${mv.code}`" @update:model-value="(id) => { crewRow(mv, i).vehicle_id = id; }" />
                <CrewChip :model-value="row.driver_id" :options="chipOptions('driver', mv)" :display="chipText('driver', row.driver_id)"
                  placeholder="+ Assign driver" :bad="isBad(mv, 'driver', row.driver_id)" :locked="isLocked(mv)"
                  :aria-label="`Driver ${i + 1} for ${mv.code}`" @update:model-value="(id) => { crewRow(mv, i).driver_id = id; }" />
                <button v-if="!isLocked(mv) && crewRows(drafts[mv.id]).length > 1" type="button" class="ct-x"
                  :title="`Remove vehicle ${i + 1}`" :aria-label="`Remove vehicle ${i + 1}`" @click="removeCrewRow(mv, i)">×</button>
                <span v-else />
              </div>
              <button v-if="!isLocked(mv)" type="button" class="ct-add" @click="addUnit(mv)">+ Add vehicle</button>

              <div v-for="(sid, i) in supervisorRows(drafts[mv.id])" :key="`s${i}`" class="ct-line ct-line--single">
                <span class="ct-label">{{ supervisorRows(drafts[mv.id]).length > 1 ? `Supv. ${i + 1}` : 'Supervisor' }}</span>
                <CrewChip :model-value="sid" :options="chipOptions('supervisor', mv)" :display="chipText('supervisor', sid)"
                  placeholder="+ Assign supervisor" :bad="isBad(mv, 'supervisor', sid)" :locked="isLocked(mv)"
                  :aria-label="`Supervisor ${i + 1} for ${mv.code}`" @update:model-value="(id) => setSupervisor(mv, i, id)" />
                <button v-if="!isLocked(mv) && supervisorRows(drafts[mv.id]).length > 1" type="button" class="ct-x"
                  :title="`Remove supervisor ${i + 1}`" :aria-label="`Remove supervisor ${i + 1}`" @click="removeSupervisorRow(mv, i)">×</button>
                <span v-else />
              </div>
              <button v-if="!isLocked(mv)" type="button" class="ct-add" @click="addSupervisor(mv)">+ Add supervisor</button>
            </div>

            <div class="ct-status">
              <span :class="['ct-pill', `ct-pill--${statusOf(mv)}`]">{{ STATUS_LABELS[statusOf(mv)] }}</span>
              <template v-if="isLocked(mv)">
                <span v-if="mv.job_done_at" class="ct-note">Done at {{ mv.job_done_at }}</span>
                <span class="ct-note ct-note--muted">🔒︎ Read-only</span>
              </template>
              <template v-else>
                <span v-if="missingFor(mv).length" class="ct-note ct-note--warn">Missing {{ missingFor(mv).join(', ') }}</span>
                <span v-if="seatsShort(mv)" class="ct-note ct-note--bad">{{ mv.pax - seatsOf(mv) }} seats short</span>
                <span v-if="mv.job_status" class="ct-note ct-note--muted" :title="mv.job_id">Job {{ statusLabel(mv.job_status).toLowerCase() }}</span>
                <AskDaleelButton v-if="['clash', 'needs'].includes(statusOf(mv)) && mv.event_id === activeEventId" quiet label="Who's free?"
                  :question="`For ${mv.code}: which vehicles, drivers and supervisors are free, and why is each of the others unavailable?`"
                  :context="{ page: 'crew-assignment', movement_id: mv.id, date: selectedDate }" />
              </template>
            </div>
          </div>

          <div v-if="!isLocked(mv) && clashMap[mv.id]?.length" class="ct-clashes" role="alert">
            <div v-for="(c, i) in clashMap[mv.id]" :key="i" class="ct-clash">
              <span class="ct-clash-text">
                <span class="ct-role">{{ c.role }}</span>
                <span v-if="c.name"><strong>{{ c.name }}</strong> is also on <strong>{{ c.otherCode }}</strong><template v-if="c.otherTeam"> · {{ c.otherTeam }}</template> ({{ c.otherTime }}) — overlaps by {{ c.overlap }}.</span>
                <span v-else>{{ c.text }}</span>
              </span>
              <button v-if="c.fix" type="button" class="ct-fix" @click="swapResource(mv, c.role, c.id, c.fix.id)">Swap to {{ c.role === 'vehicle' ? vehicleName(c.fix) : c.fix.name }}</button>
            </div>
          </div>
        </div>

        <div v-if="!filtered.length" class="ct-empty">{{ dayMovements.length ? 'Nothing matches this filter.' : 'No movements on this day.' }}</div>
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
          <span class="cap-id">{{ selected.code }}<span v-if="selected.event" class="ct-tag ct-tag--event">{{ selected.event }}</span></span>
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
        <div v-if="isLocked(selected)" class="cap-issue cap-issue--locked">🔒︎ Job completed<template v-if="selected.job_done_at"> at {{ selected.job_done_at }}</template>: the crew is read-only.</div>

        <fieldset class="cap-fs" :disabled="isLocked(selected)">
        <label v-for="(r, key) in ROLES" :key="key" class="cap-field">
          <span>{{ r.singular }}</span>
          <select v-model="drafts[selected.id][r.idField]">
            <option :value="null">Unassigned</option>
            <option v-for="o in optionsFor(key, selected)" :key="o.id" :value="o.id">
              {{ key === 'vehicle' ? vehicleLabel(o) : personName(o) }}{{ busyNote(key, o.id, selected) }}
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
              <option v-for="o in optionsFor('supervisor', selected)" :key="o.id" :value="o.id">{{ personName(o) }}{{ busyNote('supervisor', o.id, selected) }}</option>
            </select>
            <button type="button" class="unit-remove" :aria-label="`Remove extra supervisor ${i + 2}`" @click="removeSupervisor(selected, i)">✕</button>
          </div>
          <button type="button" class="unit-add" @click="addSupervisor(selected)">+ Add supervisor</button>
        </div>
        </fieldset>

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
import { ref, computed, watch, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import NoActiveEvent from '../Components/NoActiveEvent.vue';
import CrewChip from '../Components/CrewChip.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import AskDaleelButton from '../Components/AskDaleelButton.vue';
import MonthCalendar from '../Components/MonthCalendar.vue';
import CrewRoster from '../Components/CrewRoster.vue';
import CrewWeek from '../Components/CrewWeek.vue';
import { useToast } from '../Composables/useToast';
import { useStatusLabels } from '../Composables/useStatusLabels';
import { ROLES, minutesFrom, clockLabel, duration } from '../Composables/useCrewRoster';
import { crewOptions } from '../Composables/crewPeople';

const props = defineProps({
  movements: { type: Array, default: () => [] },
  date: { type: String, default: null },
  days: { type: Array, default: () => [] },
  week: { type: Object, default: () => ({ start: null, slots: [] }) },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  providers: { type: Array, default: () => [] },
  // The events shown (active first) and the ones that can be added.
  eventIds: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
});

const page = usePage();
const hasActiveEvent = computed(() => !!page.props.activeEventId);
const activeEventId = computed(() => Number(page.props.activeEventId) || null);
const multiEvent = computed(() => props.eventIds.length > 1);
const shownEvents = computed(() => props.eventIds.map((id) => props.events.find((e) => e.id === id)).filter(Boolean));
// '' rather than [] so clearing the extra events is sent (an empty list would be dropped from the query).
const eventsQuery = (ids) => (ids.length ? ids : '');
const otherEventIds = computed(() => props.eventIds.filter((id) => id !== activeEventId.value));

// The events panel floats over the table, so a click elsewhere or Esc closes it.
const evPick = ref(null);
const closeEvPick = () => { if (evPick.value) evPick.value.open = false; };
const onDocClick = (e) => { if (evPick.value?.open && !evPick.value.contains(e.target)) closeEvPick(); };
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));

function toggleEvent(id, on) {
  const ids = on ? [...otherEventIds.value, id] : otherEventIds.value.filter((x) => x !== id);
  selectedId.value = null;
  router.get('/crew-assignment', { date: selectedDate.value, events: eventsQuery(ids) }, {
    preserveState: true,
    preserveScroll: true,
  });
}

const exportHref = computed(() => {
  const q = new URLSearchParams({ date: selectedDate.value });
  if (otherEventIds.value.length) otherEventIds.value.forEach((id) => q.append('events[]', id));
  else q.set('events', '');
  return `/crew-assignment/export?${q}`;
});
const { success: showSuccessToast, error: showErrorToast, warning: showWarningToast } = useToast();
const { statusLabel } = useStatusLabels();

// Not deep: a partial reload keeps the old flash object, and a deep watcher would toast it again.
watch(() => page.props.flash, (flash) => {
  if (flash?.success) showSuccessToast(flash.success);
  if (flash?.warning) showWarningToast(flash.warning, 8000);
  if (flash?.error) showErrorToast(flash.error);
});

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
// A completed job's crew is the record of who ran it; the server refuses changes too.
const isLocked = (mv) => mv?.job_status === 'completed';

const STATUS_LABELS = { clash: 'Clash', needs: 'Needs crew', ready: 'Ready', done: '✓ Completed' };
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

const chipText = (role, id) => (id && role === 'vehicle' && vehicleById.value[id] ? vehicleLabel(vehicleById.value[id]) : (id ? resourceName(role, id) : ''));

// Free picks first, then the ones already booked on an overlapping movement.
function chipOptions(role, mv) {
  return optionsFor(role, mv)
    .map((o) => {
      const note = busyNote(role, o.id, mv);
      return { id: o.id, busy: !!note, label: `${note ? '⚠ ' : ''}${role === 'vehicle' ? vehicleLabel(o) : personName(o)}${note}` };
    })
    .sort((a, b) => a.busy - b.busy);
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
      const [ms, me] = spans.value[mv.id];
      found.push({
        role, id, name: resourceName(role, id), otherCode: other.code, otherTeam: other.team_code,
        otherTime: `${clockLabel(s)}–${clockLabel(e)}`, overlap: duration(Math.min(me, e) - Math.max(ms, s)), fix: fixFor(mv, role),
      });
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
function seatsLabel(mv) {
  const seats = seatsOf(mv);
  if (seats) return mv.pax ? `${mv.pax} pax · ${seats} seats` : `${seats} seats · no pax yet`;
  return mv.pax ? `${mv.pax} pax · no vehicle yet` : 'no vehicle yet';
}

function missingOf(mv) {
  const d = drafts.value[mv.id];
  const rows = crewRows(d);
  const missing = [];
  if (rows.some((r) => !r.vehicle_id)) missing.push('vehicle');
  if (rows.some((r) => !r.driver_id)) missing.push('driver');
  if (!d.field_supervisor_id) missing.push('supervisor');
  return missing;
}

const rowInfo = computed(() => Object.fromEntries(dayMovements.value.map((mv) => {
  const missing = missingOf(mv);
  let status = missing.length || seatsShort(mv) ? 'needs' : 'ready';
  if (hasClash(mv)) status = 'clash';
  if (isLocked(mv)) status = 'done';
  return [mv.id, { missing, status }];
})));
const statusOf = (mv) => rowInfo.value[mv.id]?.status ?? 'ready';
const missingFor = (mv) => rowInfo.value[mv.id]?.missing ?? [];

const counts = computed(() => {
  const c = { all: dayMovements.value.length, clash: 0, needs: 0, ready: 0, done: 0 };
  for (const mv of dayMovements.value) c[statusOf(mv)] += 1;
  return c;
});
const isBad = (mv, role, id) => !!id && !isLocked(mv) && (clashMap.value[mv.id] ?? []).some((c) => c.role === role && c.id === id);

// Row 0 is the lead supervisor; the rest are the extras.
const supervisorRows = (d) => [d.field_supervisor_id, ...d.supervisors];

function setSupervisor(mv, i, id) {
  const d = drafts.value[mv.id];
  if (i === 0) d.field_supervisor_id = id;
  else d.supervisors[i - 1] = id;
}

function removeSupervisorRow(mv, i) {
  const d = drafts.value[mv.id];
  if (i === 0) d.field_supervisor_id = d.supervisors.shift() ?? null;
  else d.supervisors.splice(i - 1, 1);
}

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
  { value: 'done', label: 'Completed' },
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
// With several events shown, vehicles and drivers also have to be in the movement's own event pool.
function optionsFor(key, mv) {
  const list = resourcesFor(key);
  const onIt = key === 'supervisor' ? [] : idsOf(crewOf(mv), key);
  const pool = key === 'supervisor' || !multiEvent.value
    ? list
    : list.filter((o) => !o.event_ids || o.event_ids.includes(mv.event_id) || onIt.includes(o.id));
  return crewOptions(pool, key, mv);
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

// One request for every changed movement, so the planner gets a single message.
function saveAll() {
  savingAll.value = true;
  router.patch('/movements/crew', {
    movements: dirtyList.value.map((mv) => ({ id: mv.id, ...crewPayload(mv) })),
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => nextTick(() => {
      if (dirtyList.value.length) return;
      justSaved.value = true;
      clearTimeout(savedTimer);
      savedTimer = setTimeout(() => { justSaved.value = false; }, 2200);
    }),
    onError: (errors) => showErrorToast(Object.values(errors)[0] ?? 'Could not save the crew.'),
    onFinish: () => { savingAll.value = false; },
  });
}

function discardAll() {
  drafts.value = Object.fromEntries(props.movements.map((mv) => [mv.id, crewOf(mv)]));
}

function vehicleName(v) {
  return v.code || v.plate_number || v.vehicle_type || `#${v.id}`;
}

function vehicleLabel(v) {
  const name = vehicleName(v);
  return v.capacity ? `${name} · ${v.capacity} seats` : name;
}

const personName = (p) => (p.job_title ? `${p.name} · ${p.job_title}` : p.name);


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
  router.get('/crew-assignment', { date: dateStr, events: eventsQuery(otherEventIds.value) }, {
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
  // The loaded day is counted live from the drafts; the others come from the saved state.
  const live = key === props.date;
  const total = live ? counts.value.all : (info ? info.total : null);
  const ready = live ? counts.value.ready + counts.value.done : (info ? info.total - info.unassigned : 0);
  return {
    date: key,
    wd: date.toLocaleDateString('en-GB', { weekday: 'short' }),
    num: date.getDate(),
    today: key === todayIso(),
    long: date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }),
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


.crew-page { display: flex; flex-direction: column; gap: 18px; }
.rh { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px; }
.rh-title { display: flex; flex-direction: column; gap: 6px; }
.rh-kicker { font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.rh-h1 { margin: 0; font-size: 26px; font-weight: 600; letter-spacing: -0.01em; color: var(--ink); }
.rh-sum { display: flex; flex-wrap: wrap; gap: 14px; margin: 0; font-size: 13px; color: var(--ink2); }
.rh-sum-item { display: inline-flex; align-items: center; gap: 6px; }
.rh-sum-item i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.rh-sum-item--bad { color: var(--danger-strong); font-weight: 600; }
.rh-sum-item--bad i { background: var(--danger); }
.rh-sum-item--warn { color: var(--warn); }
.rh-sum-item--ok { color: var(--ok); }
.rh-sum-events { color: var(--ink3); }
.rh-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rh-btn {
  display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px; border-radius: 8px;
  border: 1px solid var(--border-strong); background: var(--surface); color: var(--ink);
  font-size: 12px; font-weight: 500; text-decoration: none; cursor: pointer;
}
.rh-btn strong { font-weight: 600; }
.rh-btn:hover { background: var(--panel); }
.rh-caret { font-size: 9px; color: var(--ink3); }
.ev-pick { position: relative; }
.ev-pick > summary { list-style: none; user-select: none; }
.ev-pick > summary::-webkit-details-marker { display: none; }
.ev-pick-panel {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; width: 300px;
  display: flex; flex-direction: column; gap: 2px; padding: 12px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
  box-shadow: 0 12px 32px rgba(17, 24, 39, 0.14);
}
.ev-pick-hint { margin: 0; padding: 0 4px 8px; font-size: 12px; line-height: 1.45; color: var(--ink3); }
.ev-pick-row { display: flex; align-items: center; gap: 10px; padding: 7px 6px; border-radius: 6px; font-size: 13px; color: var(--ink); cursor: pointer; }
.ev-pick-row:hover { background: var(--panel); }
.ev-pick-row input { margin: 0; accent-color: var(--accent); }
.ev-pick-name { flex: 1; }
.ev-pick-row small { font-size: 10px; color: var(--ink3); }
.rh-seg { display: inline-flex; gap: 2px; background: var(--panel); padding: 3px; border-radius: 9px; }
.rh-seg-btn {
  height: 28px; padding: 0 12px; border: 0; border-radius: 6px; cursor: pointer;
  font-size: 12px; font-weight: 500; background: transparent; color: var(--ink3);
}
.rh-seg-btn:hover { color: var(--ink); }
.rh-seg-btn--active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); }

/* Week strip */
.wn { display: flex; align-items: stretch; gap: 6px; }
.wn-days { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; }
.wn-nav {
  width: 32px; flex: none; border: 1px solid var(--border); background: var(--surface); border-radius: 10px;
  font-size: 16px; color: var(--ink3); cursor: pointer;
}
.wn-nav:hover { background: var(--panel); color: var(--ink); }
.wn-day {
  cursor: pointer; text-align: left; border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  border-radius: 10px; padding: 9px 10px; display: flex; flex-direction: column; gap: 6px; min-width: 0;
}
.wn-day:hover { border-color: var(--border-strong); }
.wn-day--empty { background: var(--panel); color: var(--ink4); }
.wn-day--active, .wn-day--active:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }
.wn-top { display: flex; align-items: center; justify-content: space-between; gap: 4px; }
.wn-wd { font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--ink3); white-space: nowrap; }
.wn-day--active .wn-wd, .wn-day--active .wn-sub { color: color-mix(in srgb, var(--surface) 75%, transparent); }
.wn-num { font-size: 18px; font-weight: 600; line-height: 1; }
.wn-bar { height: 3px; border-radius: 2px; background: var(--border); overflow: hidden; }
.wn-day--active .wn-bar { background: rgba(255, 255, 255, 0.2); }
.wn-fill { display: block; height: 100%; background: var(--warn); }
.wn-fill--done { background: var(--ok); }
.wn-sub { font-size: 11px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wn-conf { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 999px; background: var(--danger); color: #fff; white-space: nowrap; }

.crew-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
.crew-ask { margin-left: auto; }
.filter-tabs { display: flex; gap: 6px; flex-wrap: wrap; }
.filter-tab {
  display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border-radius: 999px;
  border: 1px solid var(--border-strong); background: var(--surface); color: var(--ink2);
  font-size: 12px; font-weight: 500; cursor: pointer;
}
.filter-tab:hover { color: var(--ink); background: var(--panel); }
.filter-tab--active, .filter-tab--active:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }
.filter-count { opacity: 0.65; }
.crew-search {
  width: 260px; max-width: 100%; height: 34px; margin-left: auto; padding: 0 12px;
  border: 1px solid var(--border-strong); border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 13px;
}
.crew-search:focus { outline: none; border-color: var(--ink); }

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
.cap-fs { display: contents; border: 0; margin: 0; padding: 0; min-width: 0; }
.cap-fs:disabled select, .cap-fs:disabled button { opacity: 0.6; cursor: default; }
.cap-issue--locked { background: var(--panel); color: var(--ink2); }

/* Table: window · movement · crew · status */
.ct { display: flex; flex-direction: column; background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow-x: auto; }
.ct-row {
  display: grid; gap: 18px; padding: 14px 18px; align-items: start; min-width: 900px;
  grid-template-columns: 88px minmax(0, 1fr) minmax(0, 2.4fr) 120px;
}
.ct-head {
  padding: 10px 18px; align-items: center; background: var(--panel); border-bottom: 1px solid var(--border);
  font-size: 10px; font-weight: 600; letter-spacing: 0.07em; text-transform: uppercase; color: var(--ink3);
}
.ct-right { text-align: right; }
.ct-item { display: flex; flex-direction: column; border-bottom: 1px solid var(--border); }
.ct-item:last-of-type { border-bottom: none; }
.ct-item--clash { background: color-mix(in srgb, var(--danger-soft) 40%, var(--surface)); box-shadow: inset 3px 0 0 var(--danger); }
.ct-item--done { background: var(--panel); }

.ct-win { display: flex; flex-direction: column; gap: 2px; font-variant-numeric: tabular-nums; }
.ct-start { font-size: 15px; font-weight: 600; color: var(--ink); }
.ct-item--done .ct-start { color: var(--ink3); }
.ct-end { font-size: 12px; color: var(--ink4); }

.ct-mv { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.ct-team { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ct-team-name { font-size: 14px; font-weight: 600; color: var(--ink); }
.ct-tag { font-size: 10px; font-weight: 600; padding: 1px 6px; border-radius: 4px; background: var(--accent-soft); color: var(--accent-fg); white-space: nowrap; }
.ct-tag--event { border-radius: 999px; }
.ct-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); }
.ct-route { font-size: 12px; color: var(--ink2); }
.ct-meta { font-size: 11px; color: var(--ink4); }
.ct-code { font-family: var(--font-mono, ui-monospace, monospace); }
.ct-provider {
  appearance: none; field-sizing: content; max-width: 170px; padding: 0; border: 0;
  border-bottom: 1px dashed var(--border-strong); background: transparent; color: var(--ink3);
  font-size: 11px; cursor: pointer; text-overflow: ellipsis;
}
.ct-provider:hover, .ct-provider:focus { color: var(--ink); border-bottom-color: var(--ink3); outline: none; }
.ct-short { color: var(--danger-strong); font-weight: 600; }

.ct-crew { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.ct-line { display: grid; grid-template-columns: 72px minmax(0, 1fr) minmax(0, 1fr) 24px; gap: 8px; align-items: center; }
.ct-line--single { grid-template-columns: 72px minmax(0, 1fr) 24px; }
.ct-label { font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink4); white-space: nowrap; }
.ct-x {
  width: 24px; height: 24px; border: 0; border-radius: 6px; background: transparent;
  color: var(--ink4); font-size: 15px; cursor: pointer;
}
.ct-x:hover { background: var(--danger-soft); color: var(--danger-strong); }
.ct-add {
  align-self: flex-start; margin-left: 80px; padding: 0 0 6px; border: 0; background: none;
  font-size: 12px; font-weight: 600; color: var(--accent); cursor: pointer;
}
.ct-add:last-child { padding-bottom: 0; }
.ct-add:hover { text-decoration: underline; }

.ct-status { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; text-align: right; }
.ct-pill { font-size: 11px; font-weight: 600; padding: 3px 9px; border-radius: 999px; white-space: nowrap; }
.ct-pill--clash { background: var(--danger-soft); color: var(--danger-strong); }
.ct-pill--needs { background: var(--warn-soft); color: var(--warn); }
.ct-pill--ready { background: var(--ok-soft); color: var(--ok); }
.ct-pill--done { background: var(--border); color: var(--ink2); }
.ct-note { font-size: 11px; color: var(--ink3); }
.ct-note--warn { color: var(--warn); }
.ct-note--bad { color: var(--danger-strong); }
.ct-note--muted { font-size: 10px; color: var(--ink4); }

.ct-clashes { display: flex; flex-direction: column; gap: 6px; margin: -2px 18px 14px 124px; min-width: 0; }
.ct-clash {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 10px 12px; border-radius: 8px; background: var(--surface);
  border: 1px solid color-mix(in srgb, var(--danger) 35%, transparent);
}
.ct-clash-text { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--danger-strong); }
.ct-clash-text strong { font-weight: 600; }
.ct-role {
  font-size: 10px; font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;
  padding: 2px 6px; border-radius: 4px; background: var(--danger-soft); color: var(--danger-strong);
}
.ct-fix {
  height: 28px; padding: 0 10px; border: 1px solid var(--danger-strong); border-radius: 6px;
  background: var(--surface); color: var(--danger-strong); font-size: 12px; font-weight: 600; cursor: pointer;
}
.ct-fix:hover { background: var(--danger-soft); }
.ct-empty { padding: 36px; text-align: center; font-size: 13px; color: var(--ink3); }

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

.save-btn {
  padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer;
  background: var(--accent); color: #fff; font-size: 12.5px; font-weight: 600;
}
.save-btn:disabled { opacity: 0.4; cursor: default; }

/* Phones: one column per movement. */
@media (max-width: 767px) {
  .wn { overflow-x: auto; }
  .wn-days { grid-template-columns: repeat(7, 96px); }
  .ct { overflow: visible; }
  .ct-head { display: none; }
  .ct-row { grid-template-columns: 1fr; gap: 12px; min-width: 0; padding: 14px 16px; }
  .ct-status { align-items: flex-start; text-align: left; }
  .ct-clashes { margin: 0 16px 14px; }
  .savebar { width: calc(100% - 16px); justify-content: space-between; }
}
</style>
