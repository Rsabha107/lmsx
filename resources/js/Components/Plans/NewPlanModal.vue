<template>
  <div v-dialog="() => emit('close')" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal np" role="dialog" aria-labelledby="np-title">
      <div class="np-head">
        <div class="np-head__text">
          <div id="np-title" class="np-title">New plan</div>
          <div class="np-sub">{{ activeEvent?.name || 'No active event' }}</div>
        </div>
        <button type="button" class="np-close" aria-label="Close" @click="emit('close')">×</button>
      </div>

      <div class="np-body">
        <div v-if="!activeEvent" class="np-alert np-alert--warn">
          No active event selected. Pick an event from the selector at the top of the page to create plans.
        </div>

        <!-- 1 · Mode -->
        <section class="np-section">
          <div class="np-label">1 · CREATE PLANS FROM</div>
          <div class="np-modes" role="tablist" aria-label="Create plans from">
            <button
              v-for="m in modeOptions"
              :key="m.value"
              type="button"
              role="tab"
              :aria-selected="newPlanMode === m.value"
              :class="['np-mode', { 'np-mode--on': newPlanMode === m.value }]"
              @click="newPlanMode = m.value"
            >
              <span class="np-mode__label">{{ m.label }}</span>
              <span class="np-mode__sub">{{ m.sub }}</span>
            </button>
          </div>
        </section>

        <!-- 2 · Template -->
        <section class="np-section">
          <div class="np-label">2 · MOVEMENT TEMPLATE</div>
          <select
            v-model="newPlanTemplate"
            aria-label="Movement template"
            :class="['np-input', { 'np-input--error': newPlanErrors.template }]"
          >
            <option v-if="newPlanMode === 'single'" value="">Blank: no movements</option>
            <option v-else value="">Select a template…</option>
            <option v-for="t in modeTemplates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.code }})</option>
          </select>
          <span v-if="newPlanErrors.template" class="np-error">{{ newPlanErrors.template }}</span>
          <span v-if="newPlanMode === 'matches' && newPlanTemplate" class="np-hint">
            Plans start {{ describeKickoffOffset(matchStartOffsetMinutes) }} · from {{ matchStartOffsetSource }}
          </span>
        </section>

        <!-- 3 · Single plan -->
        <template v-if="newPlanMode === 'single'">
          <section class="np-section">
            <div class="np-label">3 · TEAM AND DATE</div>
            <div class="np-grid">
              <select v-model="newPlanTeamId" aria-label="Team" class="np-input" @change="handleTeamChange">
                <option :value="null">All teams</option>
                <option v-for="team in props.teams" :key="team.id" :value="team.id">
                  {{ (team.team_name || team.team || 'Unnamed Team') + (team.code ? ` (${team.code})` : '') }}
                </option>
              </select>
              <input
                v-model="newPlanDate"
                type="date"
                aria-label="Date"
                :class="['np-input', { 'np-input--error': newPlanErrors.date }]"
                @input="newPlanErrors.date = ''"
              />
              <input v-model="newPlanStartTime" type="time" aria-label="Start time" class="np-input np-input--time" />
            </div>
            <span v-if="newPlanErrors.date" class="np-error">{{ newPlanErrors.date }}</span>
            <span v-if="newPlanErrors.team_id" class="np-error">{{ newPlanErrors.team_id }}</span>

            <div v-if="needsFlightSelection" class="np-field">
              <label for="np-flight">Flight <span class="np-req">*</span> · this team has several</label>
              <select id="np-flight" v-model="newPlanFlightId" :class="['np-input', { 'np-input--error': newPlanErrors.flight_id }]">
                <option :value="null">Select a flight…</option>
                <option v-for="flight in selectedTeamFlights" :key="flight.id" :value="flight.id">
                  {{ flightLabel(flight) }}
                </option>
              </select>
              <span v-if="newPlanErrors.flight_id" class="np-error">{{ newPlanErrors.flight_id }}</span>
            </div>

            <div v-if="duplicateCheckLoading" class="np-alert np-alert--muted">Checking for duplicates…</div>
            <div v-else-if="duplicateCheck?.exists && duplicateCheck.strict" class="np-alert np-alert--block">
              <span class="np-alert__icon">!</span>
              <span>
                <strong>Duplicate movement.</strong> {{ duplicateCheck.message }}
                <a v-if="duplicateCheck.existing" :href="`/plans#movement-${duplicateCheck.existing.id}`">
                  {{ duplicateCheck.existing.code }}<template v-if="duplicateCheck.existing.plan_name"> ({{ duplicateCheck.existing.plan_name }})</template>
                </a>
                Pick another date or team.
              </span>
            </div>
            <div v-else-if="duplicateCheck?.exists" class="np-alert np-alert--warn">
              <span class="np-alert__icon">⚠</span>
              <span>
                <strong>Similar movement found.</strong> {{ duplicateCheck.message }}
                <a v-if="duplicateCheck.existing" :href="`/plans#movement-${duplicateCheck.existing.id}`" target="_blank">
                  {{ duplicateCheck.existing.code }}<template v-if="duplicateCheck.existing.plan_name"> ({{ duplicateCheck.existing.plan_name }})</template>
                </a>
                You can still create it; you will be asked to confirm.
              </span>
            </div>
            <div v-else-if="singleChecked" class="np-alert np-alert--ok">
              <span class="np-alert__tick">✓</span>
              <span>No conflicts: {{ selectedNewPlanTeam?.code || 'the team' }} is free on this date.</span>
            </div>
          </section>

          <section class="np-section">
            <div class="np-label">4 · DETAILS</div>
            <div class="np-field">
              <label for="np-name">Plan name <span class="np-opt">optional</span></label>
              <input
                id="np-name"
                v-model="newPlanName"
                type="text"
                placeholder="e.g. Match Day 4"
                :class="['np-input', { 'np-input--error': newPlanErrors.name }]"
                @input="newPlanErrors.name = ''"
              />
              <span v-if="newPlanErrors.name" class="np-error">{{ newPlanErrors.name }}</span>
            </div>
            <div v-if="selectedNewPlanTemplate?.legs?.length > 1" class="np-field">
              <label for="np-position">Movements to create</label>
              <select id="np-position" v-model="newPlanMovementPosition" class="np-input">
                <option :value="null">All {{ selectedNewPlanTemplate.legs.length }}, in the template's order</option>
                <option v-for="(leg, index) in selectedNewPlanTemplate.legs" :key="index" :value="index">
                  Only {{ formatMovementCode(nextMovementNumber + index) }}: {{ legRoute(leg, index) }}
                </option>
              </select>
            </div>

            <div v-if="selectedNewPlanTemplate?.legs?.length" class="np-list">
              <div class="np-list__head">
                Will create {{ newPlanMovementPosition !== null ? 1 : selectedNewPlanTemplate.legs.length }}
                movement{{ newPlanMovementPosition !== null || selectedNewPlanTemplate.legs.length === 1 ? '' : 's' }}
              </div>
              <div
                v-for="(leg, i) in selectedNewPlanTemplate.legs"
                v-show="newPlanMovementPosition === null || newPlanMovementPosition === i"
                :key="i"
                class="np-leg"
              >
                <span class="np-leg__no">M{{ i + 1 }}</span>
                <span :class="['np-kind', `np-kind--${leg.leg_type || 'transfer'}`]">{{ (leg.leg_type || 'transfer').replace('_', ' ') }}</span>
                <span class="np-leg__route">
                  {{ legRoute(leg, i) }}
                  <span v-if="leg.checkpoint_template" class="np-leg__steps">{{ leg.checkpoint_template.name }}</span>
                </span>
                <span class="np-time">{{ previewLegTime(i, 'start') }} → {{ previewLegTime(i, 'end') }}</span>
              </div>
              <div class="np-list__foot">Vehicles and drivers are auto-assigned from the available pool; clashes show in the Conflicts tab.</div>
            </div>
          </section>
        </template>

        <!-- 3 · Bulk: choose teams -->
        <section v-else class="np-section">
          <div class="np-label-row">
            <div class="np-label">3 · CHOOSE TEAMS</div>
            <input
              v-model="teamSearch"
              type="search"
              placeholder="Filter teams…"
              aria-label="Filter teams"
              class="np-search"
              @keydown.esc="clearSearchOnEscape"
            />
          </div>

          <div v-if="!newPlanTemplate" class="np-empty">Select a movement template to see the teams it would plan.</div>
          <template v-else>
            <div v-if="pickLoading" class="np-alert np-alert--muted">Checking for duplicates…</div>

            <div class="np-summary">
              <div class="np-bar" aria-hidden="true">
                <div class="np-bar__on" :style="{ width: pickTotals.pct(pickTotals.picked) }" />
                <div class="np-bar__off" :style="{ width: pickTotals.pct(pickTotals.unticked) }" />
                <div class="np-bar__dup" />
              </div>
              <div class="np-legend" aria-live="polite">
                <span><i class="np-dot np-dot--on" /><strong>{{ pickTotals.picked }}</strong> will be created</span>
                <span><i class="np-dot np-dot--off" />{{ pickTotals.unticked }} unticked</span>
                <span><i class="np-dot np-dot--dup" />{{ pickTotals.blocked }} already planned</span>
              </div>
            </div>

            <div class="np-days">
              <div v-for="day in pickDays" :key="day.date" class="np-day">
                <div class="np-day__date">
                  <span class="np-day__wd">{{ day.wd }}</span>
                  <span :class="['np-day__num', { 'np-day__num--off': !day.state.picked }]">{{ day.dayNum }}</span>
                  <span class="np-day__wd">{{ day.mon }}</span>
                </div>
                <div class="np-day__body">
                  <label v-if="day.ready.length" class="np-day__all">
                    <input
                      type="checkbox"
                      :checked="day.state.all"
                      :indeterminate="day.state.some"
                      :aria-label="`Include every team on ${day.label}`"
                      @change="toggleGroup(day.rows)"
                    />
                    <span>{{ day.state.picked }} of {{ day.ready.length }} team{{ day.ready.length === 1 ? '' : 's' }} · {{ day.plans }} plan{{ day.plans === 1 ? '' : 's' }}</span>
                  </label>

                  <label
                    v-for="item in day.ready"
                    :key="item.row.id"
                    :class="['np-row', { 'np-row--off': !item.row.picked }]"
                    :title="item.row.warn ? `${item.row.message || 'Similar movement found'}. Included unless you untick it.` : undefined"
                  >
                    <input type="checkbox" :checked="item.row.picked" :aria-label="item.team" @change="togglePicked(item.row)" />
                    <span class="np-row__team">{{ item.team }}</span>
                    <span class="np-row__detail">
                      <span :class="['np-kind', `np-kind--${item.kind.toLowerCase()}`]">{{ item.kind }}</span>
                      <span v-if="item.row.warn" class="np-kind np-kind--warn">⚠ similar</span>
                      <select
                        v-if="item.flights?.length > 1"
                        class="np-row__flight"
                        :value="item.flightId"
                        :aria-label="`Flight for ${item.team}`"
                        @click.stop
                        @change="selectTeamFlight(item.teamId, Number($event.target.value))"
                      >
                        <option v-for="f in item.flights" :key="f.id" :value="f.id">{{ flightLabel(f) }}</option>
                      </select>
                      <span v-else class="np-row__text">{{ item.detail }}</span>
                    </span>
                    <span class="np-time">{{ item.time }}</span>
                  </label>

                  <div v-if="day.dups.length" class="np-dups">
                    <button type="button" class="np-dups__toggle" :aria-expanded="dupOpen.has(day.date)" @click="toggleDupOpen(day.date)">
                      <span :class="['np-caret', { 'np-caret--open': dupOpen.has(day.date) }]">›</span>
                      {{ day.dups.length }} already planned · skipped
                    </button>
                    <div v-if="dupOpen.has(day.date)" class="np-dups__list">
                      <div v-for="item in day.dups" :key="item.row.id" class="np-dups__row">
                        <span class="np-row__team">{{ item.team }}</span>
                        <span class="np-row__text">{{ item.kind.toLowerCase() }} · {{ item.detail }}</span>
                        <a v-if="item.existing" :href="`/plans#movement-${item.existing.id}`" class="np-ref" :title="item.existing.plan_name || ''">{{ item.existing.code }} ↗</a>
                      </div>
                    </div>
                    <div v-else class="np-dups__chips">
                      <span v-for="item in day.dups" :key="item.row.id" class="np-chip">{{ item.team }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="!pickDays.length" class="np-empty">
                <template v-if="teamSearch.trim()">No teams match “{{ teamSearch.trim() }}”.</template>
                <template v-else-if="newPlanMode === 'matches'">No matches found. Create matches for the teams first.</template>
                <template v-else>No teams with {{ bulkPreview.isDepartureTemplate ? 'departure' : 'arrival' }} dates. Set their flight or bus dates in Event Teams first.</template>
              </div>

              <div v-if="newPlanMode === 'bulk' && bulkPreview.teamsWithoutDates > 0" class="np-missing">
                {{ bulkPreview.teamsWithoutDates }} team{{ bulkPreview.teamsWithoutDates === 1 ? ' has' : 's have' }} no
                {{ bulkPreview.isDepartureTemplate ? 'departure' : 'arrival' }} date yet:
                {{ bulkPreview.teamsWithoutDatesList.map((t) => t.code).join(', ') }}
              </div>
            </div>
          </template>
        </section>
      </div>

      <div class="np-foot">
        <span class="np-foot__note">{{ footNote }}</span>
        <div class="np-foot__actions">
          <button type="button" class="np-btn" :disabled="newPlanProcessing" @click="emit('close')">Cancel</button>
          <button type="button" class="np-btn np-btn--primary" :disabled="cta.disabled" @click="cta.action">{{ cta.label }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { formatDate, formatDateTime, formatMovementCode, legMinutes } from '../../Composables/planFormat';
import { legCheck, findDuplicates } from '../../Composables/planDuplicateChecks';

// Mounted only while open (v-if), so every opening starts from a clean form.
const props = defineProps({
  activeEvent: { type: Object, default: null },
  teams: { type: Array, default: () => [] },
  movementTemplates: { type: Array, default: () => [] },
  matches: { type: Array, default: () => [] },
  nextMovementNumber: { type: Number, default: 1 },
});

const emit = defineEmits(['close']);

const newPlanMode = ref("single"); // 'single' or 'bulk' or 'matches'
const newPlanDate = ref("");
const newPlanStartTime = ref("09:00");
const newPlanName = ref("");
const newPlanTeamId = ref(null);
const newPlanFlightId = ref(null);
const newPlanAccommodationId = ref(null);
const newPlanTemplate = ref("");
const newPlanMovementPosition = ref(null); // Selected movement position from template
const newPlanProcessing = ref(false);
const newPlanErrors = ref({});
const teamFlightSelections = ref({}); // Track selected flight per team

// Duplicate checking state
const duplicateCheck = ref(null); // { exists, strict, message, existing }
const duplicateCheckLoading = ref(false);
const duplicateConfirmed = ref(false); // User confirmed they want to create despite warning

// Bulk duplicate checking state
const bulkDuplicateChecks = ref(new Map()); // Map<team_id, { exists, strict, message, existing }>
const bulkDuplicateCheckLoading = ref(false);

// Match duplicate checking state
const matchDuplicateChecks = ref(new Map()); // Map<matchCheckKey, { exists, strict, message, existing }>
// A team plays several matches, so one match's movements must not block its others.
const matchCheckKey = (team) => `${team.match_id}:${team.team_id}`;
const matchDuplicateCheckLoading = ref(false);

// Bulk team picking. Every team in a bulk preview starts included; the planner can leave any out.
// Keyed per preview row (a team plays several matches) and reset with the template or mode.
const excludedTeams = ref(new Set());
const teamSearch = ref("");
watch([() => newPlanTemplate.value, () => newPlanMode.value], () => {
  excludedTeams.value = new Set();
  teamSearch.value = "";
});

// A strict duplicate can never be included; a soft one is included unless the planner says otherwise.
function pickRow(id, check) {
  const blocked = !!(check?.exists && check?.strict);
  return {
    id,
    blocked,
    warn: !!(check?.exists && !check?.strict),
    picked: !blocked && !excludedTeams.value.has(id),
    message: check?.message ?? "",
    existing: check?.existing ?? null,
  };
}
const bulkRow = (team) => pickRow(`t${team.id}`, bulkDuplicateChecks.value.get(team.id));
const matchRow = (team) => pickRow(`m${matchCheckKey(team)}`, matchDuplicateChecks.value.get(matchCheckKey(team)));

function setPicked(rows, on) {
  const next = new Set(excludedTeams.value);
  rows.filter((row) => !row.blocked).forEach((row) => (on ? next.delete(row.id) : next.add(row.id)));
  excludedTeams.value = next;
}
const togglePicked = (row) => setPicked([row], !row.picked);

function groupState(rows) {
  const selectable = rows.filter((row) => !row.blocked);
  const picked = selectable.filter((row) => row.picked).length;
  return {
    all: selectable.length > 0 && picked === selectable.length,
    some: picked > 0 && picked < selectable.length,
    empty: selectable.length === 0,
    picked,
    selectable: selectable.length,
    blocked: rows.length - selectable.length,
  };
}
const toggleGroup = (rows) => setPicked(rows, !groupState(rows).all);

function matchesTeamSearch(code, name) {
  const query = teamSearch.value.trim().toLowerCase();
  return !query || `${code ?? ""} ${name ?? ""}`.toLowerCase().includes(query);
}

// The preview groups as shown: filtered by the search box, each with its pick rows.
function shownGroups(plans, codeOf, rowOf) {
  return plans
    .map((plan) => {
      const teams = plan.teams.filter((team) => matchesTeamSearch(codeOf(team), team.team_name));
      return { ...plan, teams, rows: teams.map(rowOf) };
    })
    .filter((plan) => plan.teams.length > 0);
}

// Bulk mode only previews the arrival / departure groups, match mode the match groups.
const isMatchPicking = computed(() => newPlanMode.value === "matches");
const pickPlans = computed(() => (isMatchPicking.value ? matchesPreview.value.plans : bulkPreview.value.plans));
const pickRowOf = (team) => (isMatchPicking.value ? matchRow(team) : bulkRow(team));
const pickCodeOf = (team) => (isMatchPicking.value ? team.team_code : team.code);

const shownPickGroups = computed(() => shownGroups(pickPlans.value, pickCodeOf, pickRowOf));
const shownPickRows = computed(() => shownPickGroups.value.flatMap((plan) => plan.rows));

// Totals count the whole preview, whatever the filter hides.
const pickSummary = computed(() => {
  const rows = pickPlans.value.flatMap((plan) => plan.teams.map(pickRowOf));
  const selectable = rows.filter((row) => !row.blocked);
  return {
    picked: selectable.filter((row) => row.picked).length,
    selectable: selectable.length,
    blocked: rows.length - selectable.length,
  };
});

// The bar above the list: will be created / unticked / already planned.
const pickTotals = computed(() => {
  const { picked, selectable, blocked } = pickSummary.value;
  const total = picked + (selectable - picked) + blocked || 1;
  return { picked, unticked: selectable - picked, blocked, pct: (n) => `${(n / total) * 100}%` };
});

const pickLoading = computed(() => (isMatchPicking.value ? matchDuplicateCheckLoading.value : bulkDuplicateCheckLoading.value));

const WEEKDAYS = ["SUN", "MON", "TUE", "WED", "THU", "FRI", "SAT"];
const MONTHS = ["JAN", "FEB", "MAR", "APR", "MAY", "JUN", "JUL", "AUG", "SEP", "OCT", "NOV", "DEC"];

function flightLabel(flight) {
  if (flight.flight_number === "BUS") return `Bus${flight.scheduled_time ? ` · ${flight.scheduled_time}` : ""}`;
  const where = flight.origin_airport?.code ?? flight.origin_airport?.iata ?? flight.origin_airport;
  return [flight.flight_number || "Flight", where ? `from ${where}` : null, flight.scheduled_time].filter(Boolean).join(" · ");
}

/** One preview row as the list shows it: team, kind tag, detail and time. */
function describePick(plan, team, row) {
  if (isMatchPicking.value) {
    return {
      row, planKey: plan.match_id, team: team.team_code, kind: "MATCH",
      detail: `${team.match_number} · vs ${team.opponent} · ${team.venue} · KO ${team.kick_off_time_only}`, time: team.plan_time,
      existing: row.existing,
    };
  }
  const flight = team.selected_flight;
  const departing = bulkPreview.value.isDepartureTemplate;
  const bus = flight?.flight_number === "BUS";
  const airport = departing ? flight?.destination_airport : flight?.origin_airport;
  const time = (team.arrival_date_time ?? "").match(/[T ](\d{2}):(\d{2})/);
  return {
    row, planKey: plan.date, team: team.code, teamId: team.id,
    kind: bus ? "BUS" : "FLIGHT",
    detail: bus ? "By road" : flight ? [flight.flight_number, airport ? `${departing ? "to" : "from"} ${airport}` : null].filter(Boolean).join(" ") : "No flight on record",
    time: time ? `${time[1]}:${time[2]}` : "—",
    flights: team.flights, flightId: flight?.id,
    existing: row.existing,
  };
}

// The preview by day: a date header, the teams that can be created, and the ones already planned.
const pickDays = computed(() => {
  const days = new Map();
  for (const plan of shownPickGroups.value) {
    const day = days.get(plan.date) ?? days.set(plan.date, { date: plan.date, items: [] }).get(plan.date);
    plan.teams.forEach((team, i) => day.items.push(describePick(plan, team, plan.rows[i])));
  }
  return [...days.values()].sort((a, b) => a.date.localeCompare(b.date)).map((day) => {
    const [y, m, dd] = day.date.split("-").map(Number);
    const ready = day.items.filter((item) => !item.row.blocked);
    const rows = ready.map((item) => item.row);
    return {
      date: day.date,
      label: formatDate(day.date),
      wd: WEEKDAYS[new Date(y, m - 1, dd).getDay()],
      dayNum: dd,
      mon: MONTHS[m - 1],
      ready,
      rows,
      dups: day.items.filter((item) => item.row.blocked),
      state: groupState(rows),
      plans: new Set(ready.filter((item) => item.row.picked).map((item) => item.planKey)).size,
    };
  });
});

const dupOpen = ref(new Set());
function toggleDupOpen(date) {
  const next = new Set(dupOpen.value);
  next.has(date) ? next.delete(date) : next.add(date);
  dupOpen.value = next;
}

// Esc clears the filter first; only an empty filter lets Esc reach the dialog.
function clearSearchOnEscape(event) {
  if (!teamSearch.value) return;
  event.stopPropagation();
  teamSearch.value = "";
}

// Handle team selection change. Date/start-time defaulting is handled by
// the unified watcher below (it needs the selected template too, to know
// whether to default from the team's arrival, departure, or match).
function handleTeamChange() {
  newPlanErrors.value.team_id = "";

  // Reset flight and accommodation selection when team changes
  newPlanFlightId.value = null;
  newPlanAccommodationId.value = null;

  if (newPlanTeamId.value && props.teams) {
    const selectedTeam = props.teams.find((t) => t.id === newPlanTeamId.value);

    if (selectedTeam) {
      // Auto-select flight if only one exists
      if (selectedTeam.flights && selectedTeam.flights.length === 1) {
        newPlanFlightId.value = selectedTeam.flights[0].id;
      }

      // Auto-select accommodation if team has one
      if (selectedTeam.stay) {
        newPlanAccommodationId.value = selectedTeam.stay.id;
      }
    }
  }
}

const selectedNewPlanTemplate = computed(() => {
  if (!newPlanTemplate.value) return null;
  return props.movementTemplates.find((t) => t.id === newPlanTemplate.value);
});

// "By arrivals" groups teams by arrival/departure date, so only templates
// meant for those movements make sense to offer there.
const bulkArrivalMovementTemplates = computed(() =>
  props.movementTemplates.filter((t) => ["arrival_day", "departure_day"].includes(t.scenario_type))
);

// "Bulk by Matches" schedules a movement ahead of each match, so only
// match-day templates make sense to offer there.
const matchDayMovementTemplates = computed(() =>
  props.movementTemplates.filter((t) => t.scenario_type === "match_day")
);

// Resolved server-side from Settings (checkpoint override, then event, then global).
const matchStartOffsetMinutes = computed(
  () => selectedNewPlanTemplate.value?.match_start_offset?.minutes ?? 0
);
const matchStartOffsetSource = computed(
  () => selectedNewPlanTemplate.value?.match_start_offset?.source ?? "no offset configured yet"
);

function describeKickoffOffset(minutes) {
  if (!minutes) return "at kick-off";
  const abs = Math.abs(minutes);
  const hours = Math.floor(abs / 60);
  const mins = abs % 60;
  const span = [
    hours ? `${hours} hour${hours === 1 ? "" : "s"}` : "",
    mins ? `${mins} min` : "",
  ].filter(Boolean).join(" ");
  return `${span} ${minutes < 0 ? "before" : "after"} kick-off`;
}

// How long before kick-off match plans start, short: "2h before kick-off".
function shortKickoffOffset(minutes) {
  if (!minutes) return "at kick-off";
  const abs = Math.abs(minutes);
  const span = [Math.floor(abs / 60) ? `${Math.floor(abs / 60)}h` : "", abs % 60 ? `${abs % 60}m` : ""].filter(Boolean).join(" ");
  return `${span} ${minutes < 0 ? "before" : "after"} kick-off`;
}

const modeOptions = computed(() => {
  const matchTemplate = newPlanMode.value === "matches" && selectedNewPlanTemplate.value
    ? selectedNewPlanTemplate.value
    : matchDayMovementTemplates.value[0];
  const offset = matchTemplate?.match_start_offset?.minutes;
  return [
    { value: "single", label: "Single plan", sub: "One team, one date" },
    { value: "bulk", label: "By arrivals", sub: "Flights & buses, per date" },
    { value: "matches", label: "By matches", sub: offset == null ? "Per match, before kick-off" : `Starts ${shortKickoffOffset(offset)}` },
  ];
});

// The templates each mode offers.
const modeTemplates = computed(() => ({
  single: props.movementTemplates,
  bulk: bulkArrivalMovementTemplates.value,
  matches: matchDayMovementTemplates.value,
}[newPlanMode.value] ?? []));

// A template the new mode doesn't offer would sit selected but hidden.
watch(() => newPlanMode.value, () => {
  if (newPlanTemplate.value && !modeTemplates.value.some((t) => t.id === newPlanTemplate.value)) {
    newPlanTemplate.value = "";
  }
  newPlanErrors.value = {};
});

function legRoute(leg, index) {
  if (leg.from_location && leg.to_location) return `${leg.from_location} → ${leg.to_location}`;
  return leg.checkpoint_template?.name || `Movement ${index + 1}`;
}

const selectedNewPlanTeam = computed(() => {
  if (!newPlanTeamId.value) return null;
  return props.teams.find((t) => t.id === newPlanTeamId.value);
});

const selectedTeamFlights = computed(() => {
  return selectedNewPlanTeam.value?.flights || [];
});

const needsFlightSelection = computed(() => {
  return selectedTeamFlights.value.length > 1;
});

// Find next match for selected team
const selectedTeamNextMatch = computed(() => {
  if (!newPlanTeamId.value || !props.matches || props.matches.length === 0) {
    return null;
  }
  
  const selectedTeam = selectedNewPlanTeam.value;
  if (!selectedTeam) return null;
  
  const teamId = selectedTeam.id;
  if (!teamId) return null;

  // Find all matches for this team
  const teamMatches = props.matches.filter(match =>
    match.team1_id === teamId || match.team2_id === teamId
  );
  
  if (teamMatches.length === 0) return null;
  
  // Sort by kick_off date and return the first one (or next upcoming)
  const sortedMatches = teamMatches.sort((a, b) => 
    new Date(a.kick_off) - new Date(b.kick_off)
  );
  
  return sortedMatches[0];
});

// Bulk plan preview - group teams by arrival date (or departure date when a departure template is selected)
const bulkPreview = computed(() => {
  const teamsWithDates = [];
  const teamsWithoutDates = [];
  const dateGroups = {};

  const template = selectedNewPlanTemplate.value;
  const templateText = `${template?.scenario_type || ''} ${template?.name || ''} ${template?.code || ''}`.toLowerCase();
  const isMatchTemplate = templateText.includes('match');
  const isDepartureTemplate = !isMatchTemplate && templateText.includes('departure');

  // Group teams by arrival date, unless the selected template is a departure
  // movement, in which case teams must be grouped by their departure date
  props.teams.forEach((team) => {
    const flightPool = isDepartureTemplate ? (team.departure_flights || []) : (team.flights || []);

    // Get selected flight or default to first flight
    const defaultFlightId = isDepartureTemplate ? flightPool[0]?.id : team.selected_flight_id;
    const selectedFlightId = teamFlightSelections.value[team.id] || defaultFlightId;
    const selectedFlight = flightPool.find(f => f.id === selectedFlightId) || flightPool[0];

    // Ensure team has valid display properties with fallbacks
    const normalizedTeam = {
      ...team,
      code: team.code || team.team || 'TEAM',
      team_name: team.team_name || team.team || 'Unnamed Team',
      flights: flightPool,
      arrival_date_time: selectedFlight?.scheduled_at || (isDepartureTemplate ? team.departure_date_time : team.arrival_date_time),
      arrival_date: selectedFlight?.scheduled_date || (isDepartureTemplate ? team.departure_date : team.arrival_date),
      selected_flight: selectedFlight,
      has_multiple_flights: flightPool.length > 1,
    };
    
    if (normalizedTeam.arrival_date_time || normalizedTeam.arrival_date) {
      const arrivalDate = normalizedTeam.arrival_date_time || normalizedTeam.arrival_date;
      const dateOnly = arrivalDate.split(/[ T]/)[0]; // Extract YYYY-MM-DD

      if (!dateGroups[dateOnly]) {
        dateGroups[dateOnly] = [];
      }
      dateGroups[dateOnly].push(normalizedTeam);
      teamsWithDates.push(normalizedTeam);
    } else {
      teamsWithoutDates.push(normalizedTeam);
    }
  });

  // Convert to array of plans sorted by date
  const plans = Object.entries(dateGroups)
    .map(([date, teams]) => ({
      date,
      teams: teams.sort((a, b) => (a.code || "").localeCompare(b.code || "")),
    }))
    .sort((a, b) => a.date.localeCompare(b.date));

  return {
    plans,
    plansCount: plans.length,
    teamsWithDates: teamsWithDates.length,
    teamsWithoutDates: teamsWithoutDates.length,
    teamsWithoutDatesList: teamsWithoutDates.sort((a, b) =>
      (a.code || "").localeCompare(b.code || "")
    ),
    isDepartureTemplate,
  };
});

// Computed property for bulk plans excluding conflicts
const bulkAllowedPreview = computed(() => {
  if (bulkPreview.value.plans.length === 0) {
    return {
      plansCount: 0,
      teamsCount: 0,
      hasStrictDuplicates: false,
    };
  }

  let allowedPlansCount = 0;
  let allowedTeamsCount = 0;
  let hasStrictDuplicates = false;

  // For each plan (date group), count the teams that are included
  bulkPreview.value.plans.forEach((plan) => {
    const allowedTeamsInPlan = plan.teams.filter((team) => {
      const row = bulkRow(team);
      if (row.blocked) hasStrictDuplicates = true;
      return row.picked;
    });

    if (allowedTeamsInPlan.length > 0) {
      allowedPlansCount++;
      allowedTeamsCount += allowedTeamsInPlan.length;
    }
  });

  return {
    plansCount: allowedPlansCount,
    teamsCount: allowedTeamsCount,
    hasStrictDuplicates,
  };
});

// Computed property for matches preview
const matchesPreview = computed(() => {
  const teamsByMatchTime = {}; // Group teams by match (not just date)
  
  if (!props.matches || props.matches.length === 0) {
    return {
      plans: [],
      plansCount: 0,
      teamsCount: 0,
      matchesCount: 0,
    };
  }

  // For each match, create a separate plan entry
  props.matches.forEach((match) => {
    if (!match.kick_off) return;

    const kickOffDate = new Date(match.kick_off);
    
    // Use the match date (not the plan start date which could roll back to previous day)
    const matchDate = kickOffDate.toISOString().split('T')[0];
    
    // Calculate plan start time from the template's configured match offset
    const planStartDate = new Date(kickOffDate.getTime() + matchStartOffsetMinutes.value * 60 * 1000);
    const planTime = planStartDate.toTimeString().slice(0, 5);
    
    // Create a unique key for this match to group its teams together
    const matchKey = `${matchDate}_${match.id}`;

    // Initialize match group if needed
    if (!teamsByMatchTime[matchKey]) {
      teamsByMatchTime[matchKey] = {
        date: matchDate, // Use match date, not plan start date
        match_id: match.id,
        match_number: match.match_number,
        venue: match.venue?.name || 'TBD',
        kick_off: match.kick_off,
        kick_off_time: formatDateTime(match.kick_off),
        plan_time: planTime,
        teams: [],
      };
    }

    // Add team1
    if (match.team1_id && match.team1) {
      teamsByMatchTime[matchKey].teams.push({
        match_id: match.id,
        team_id: match.team1.id,
        team_code: match.team1.code,
        team_name: match.team1.team_name || match.team1.team,
        match_number: match.match_number,
        opponent: match.team2?.team_name || match.team2?.team || 'TBD',
        venue: match.venue?.name || 'TBD',
        kick_off_time: formatDateTime(match.kick_off),
        kick_off_time_only: kickOffDate.toTimeString().slice(0, 5),
        plan_start_time: formatDateTime(planStartDate.toISOString()),
        plan_time: planTime,
      });
    }

    // Add team2
    if (match.team2_id && match.team2) {
      teamsByMatchTime[matchKey].teams.push({
        match_id: match.id,
        team_id: match.team2.id,
        team_code: match.team2.code,
        team_name: match.team2.team_name || match.team2.team,
        match_number: match.match_number,
        opponent: match.team1?.team_name || match.team1?.team || 'TBD',
        venue: match.venue?.name || 'TBD',
        kick_off_time: formatDateTime(match.kick_off),
        kick_off_time_only: kickOffDate.toTimeString().slice(0, 5),
        plan_start_time: formatDateTime(planStartDate.toISOString()),
        plan_time: planTime,
      });
    }
  });

  // Convert to array and sort by date and time
  const plans = Object.values(teamsByMatchTime).sort((a, b) => {
    const dateCompare = a.date.localeCompare(b.date);
    if (dateCompare !== 0) return dateCompare;
    return a.plan_time.localeCompare(b.plan_time);
  });

  const allTeams = plans.flatMap(p => p.teams);
  const uniqueTeams = new Set(allTeams.map(t => t.team_id));

  // Debug: Log what matches are in the preview
  console.log('Matches Preview:', {
    totalPlans: plans.length,
    dates: [...new Set(plans.map(p => p.date))].sort(),
    planDetails: plans.map(p => ({
      date: p.date,
      match: p.match_number,
      teams: p.teams.map(t => t.team_code)
    }))
  });

  return {
    plans,
    plansCount: plans.length,
    teamsCount: uniqueTeams.size,
    matchesCount: props.matches.length,
  };
});

// Computed property for matches excluding conflicts
const matchAllowedPreview = computed(() => {
  if (matchesPreview.value.plans.length === 0) {
    return {
      plansCount: 0,
      teamsCount: 0,
      hasStrictDuplicates: false,
    };
  }

  let allowedPlansCount = 0;
  let allowedTeamsCount = 0;
  let hasStrictDuplicates = false;

  // For each plan (match group), count the teams that are included
  matchesPreview.value.plans.forEach((plan) => {
    const allowedTeamsInPlan = plan.teams.filter((team) => {
      const row = matchRow(team);
      if (row.blocked) hasStrictDuplicates = true;
      return row.picked;
    });

    if (allowedTeamsInPlan.length > 0) {
      allowedPlansCount++;
      allowedTeamsCount += allowedTeamsInPlan.length;
    }
  });

  return {
    plansCount: allowedPlansCount,
    teamsCount: allowedTeamsCount,
    hasStrictDuplicates,
  };
});

function previewLegTime(index, type) {
  const legs = selectedNewPlanTemplate.value?.legs;
  if (!legs) return "—";
  if (!newPlanDate.value) return "—";

  // Parse date and start time
  const [hours, minutes] = (newPlanStartTime.value || "09:00")
    .split(":")
    .map(Number);
  const base = new Date(newPlanDate.value);
  if (isNaN(base.getTime())) return "—";

  // Set the start time
  base.setHours(hours, minutes, 0, 0);

  // Calculate cumulative offset from previous legs
  let offsetMin = 0;
  for (let i = 0; i < index; i++)
    offsetMin += legMinutes(selectedNewPlanTemplate.value, legs[i]);

  const start = new Date(base.getTime() + offsetMin * 60000);
  const end = new Date(
    start.getTime() + legMinutes(selectedNewPlanTemplate.value, legs[index]) * 60000
  );
  const fmt = (d) => d.toTimeString().slice(0, 5);
  return type === "start" ? fmt(start) : fmt(end);
}

function selectTeamFlight(teamId, flightId) {
  teamFlightSelections.value = { ...teamFlightSelections.value, [teamId]: flightId };
}

// Check for duplicate movements
async function checkForDuplicate() {
  // Reset duplicate state
  duplicateCheck.value = null;
  duplicateConfirmed.value = false;

  // Skip if required fields missing
  if (!newPlanTeamId.value || !newPlanDate.value) {
    return;
  }

  // Only check if we're creating from a template (movements will be generated)
  if (!newPlanTemplate.value) {
    return;
  }

  // Determine what to check based on template legs
  const template = selectedNewPlanTemplate.value;
  if (!template || !template.legs || template.legs.length === 0) {
    return;
  }

  duplicateCheckLoading.value = true;

  try {
    // A chosen position creates only that leg, so only that leg is checked.
    const legs = newPlanMovementPosition.value !== null
      ? [template.legs[newPlanMovementPosition.value]].filter(Boolean)
      : template.legs;
    const base = {
      team_id: newPlanTeamId.value,
      window_start: newPlanDate.value + ' ' + (newPlanStartTime.value || '09:00') + ':00',
      flight_id: newPlanFlightId.value,
    };
    const items = legs.map((leg) => ({ owner: 'plan', check: legCheck(leg, base) })).filter((item) => item.check);

    duplicateCheck.value = (await findDuplicates(items)).get('plan') ?? { exists: false };
  } catch (error) {
    console.error('Duplicate check failed:', error);
  } finally {
    duplicateCheckLoading.value = false;
  }
}

// Check for duplicates in bulk mode (all teams in bulk preview)
async function checkBulkDuplicates() {
  // Reset state
  bulkDuplicateChecks.value = new Map();
  
  // Skip if no template selected or not in bulk mode
  if (!newPlanTemplate.value || newPlanMode.value !== 'bulk') {
    return;
  }
  
  const template = selectedNewPlanTemplate.value;
  if (!template || !template.legs || template.legs.length === 0) {
    return;
  }
  
  // Skip if no teams in preview
  if (bulkPreview.value.plans.length === 0) {
    return;
  }
  
  bulkDuplicateCheckLoading.value = true;

  try {
    const items = [];
    for (const plan of bulkPreview.value.plans) {
      for (const team of plan.teams) {
        const time = team.arrival_date_time?.match(/[T ](\d{2}):(\d{2})/);
        const base = {
          team_id: team.id,
          window_start: `${plan.date} ${time ? `${time[1]}:${time[2]}` : '09:00'}:00`,
          flight_id: team.selected_flight?.id || team.flights?.[0]?.id,
        };
        for (const leg of template.legs) {
          const check = legCheck(leg, base);
          if (check) items.push({ owner: team.id, check });
        }
      }
    }

    bulkDuplicateChecks.value = await findDuplicates(items);
  } catch (error) {
    console.error('Bulk duplicate check failed:', error);
  } finally {
    bulkDuplicateCheckLoading.value = false;
  }
}

// Check for duplicates in matches mode (all teams in matches preview)
async function checkMatchDuplicates() {
  // Reset state
  matchDuplicateChecks.value = new Map();
  
  // Skip if no template selected or not in matches mode
  if (!newPlanTemplate.value || newPlanMode.value !== 'matches') {
    return;
  }
  
  const template = selectedNewPlanTemplate.value;
  if (!template || !template.legs || template.legs.length === 0) {
    return;
  }
  
  // Skip if no teams in preview
  if (matchesPreview.value.plans.length === 0) {
    return;
  }
  
  matchDuplicateCheckLoading.value = true;

  try {
    const items = [];
    for (const plan of matchesPreview.value.plans) {
      for (const team of plan.teams) {
        // No flight here: arrival/departure legs are not checked in this mode.
        const base = { team_id: team.team_id, window_start: `${plan.date} ${team.plan_time}:00`, match_id: team.match_id };
        for (const leg of template.legs) {
          const check = legCheck(leg, base);
          if (check) items.push({ owner: matchCheckKey(team), check });
        }
      }
    }

    matchDuplicateChecks.value = await findDuplicates(items);
  } catch (error) {
    console.error('Match duplicate check failed:', error);
  } finally {
    matchDuplicateCheckLoading.value = false;
  }
}

// Watch for changes to check duplicates
watch(
  [() => newPlanTeamId.value, () => newPlanFlightId.value, () => newPlanTemplate.value, () => newPlanDate.value, () => newPlanStartTime.value, () => newPlanMovementPosition.value],
  () => {
    if (newPlanMode.value === 'single') {
      checkForDuplicate();
    }
  },
  { deep: true }
);

// Watch for changes to trigger bulk duplicate checks
watch(
  [() => newPlanTemplate.value, () => bulkPreview.value.plans, () => teamFlightSelections.value],
  () => {
    if (newPlanMode.value === 'bulk') {
      checkBulkDuplicates();
    }
  },
  { deep: true }
);

// Watch for changes to trigger match duplicate checks
watch(
  [() => newPlanTemplate.value, () => matchesPreview.value.plans],
  () => {
    if (newPlanMode.value === 'matches') {
      checkMatchDuplicates();
    }
  },
  { deep: true }
);

// Reset movement position when template changes
watch(() => newPlanTemplate.value, () => {
  newPlanMovementPosition.value = null;
});

// Auto-set date/time from the selected team, based on what kind of
// template is selected: match templates default from the team's next
// match kick-off, departure templates from the team's departure flight,
// and everything else (including blank) from the team's arrival flight.
watch([() => newPlanTemplate.value, () => newPlanTeamId.value], () => {
  if (!newPlanTeamId.value || !props.teams) {
    return;
  }
  const selectedTeam = props.teams.find((t) => t.id === newPlanTeamId.value);
  if (!selectedTeam) {
    return;
  }

  const template = selectedNewPlanTemplate.value;
  const templateText = `${template?.scenario_type || ''} ${template?.name || ''} ${template?.code || ''}`.toLowerCase();
  const isMatchTemplate = templateText.includes('match');
  const isDepartureTemplate = !isMatchTemplate && templateText.includes('departure');

  newPlanErrors.value.date = "";

  if (isMatchTemplate) {
    if (!selectedTeamNextMatch.value) return;

    // Set date and time from the match kick-off and the template's configured offset
    const kickOffDate = new Date(selectedTeamNextMatch.value.kick_off);
    const planStartDate = new Date(kickOffDate.getTime() + matchStartOffsetMinutes.value * 60 * 1000);

    const year = planStartDate.getFullYear();
    const month = String(planStartDate.getMonth() + 1).padStart(2, '0');
    const day = String(planStartDate.getDate()).padStart(2, '0');
    newPlanDate.value = `${year}-${month}-${day}`;

    const hours = String(planStartDate.getHours()).padStart(2, '0');
    const minutes = String(planStartDate.getMinutes()).padStart(2, '0');
    newPlanStartTime.value = `${hours}:${minutes}`;
    return;
  }

  if (isDepartureTemplate) {
    if (!selectedTeam.departure_date_time) {
      newPlanDate.value = "";
      newPlanStartTime.value = "";
      newPlanErrors.value.date = `No departure flight set or created for ${selectedTeam.team_name}.`;
      return;
    }
    const dt = selectedTeam.departure_date_time;
    const dateMatch = dt.match(/(\d{4}-\d{2}-\d{2})/);
    if (dateMatch) newPlanDate.value = dateMatch[1];
    const timeMatch = dt.match(/[T ](\d{2}):(\d{2})/);
    if (timeMatch) newPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
    return;
  }

  // Default: arrival-based
  if (selectedTeam.arrival_date_time) {
    const dt = selectedTeam.arrival_date_time;
    const dateMatch = dt.match(/(\d{4}-\d{2}-\d{2})/);
    if (dateMatch) newPlanDate.value = dateMatch[1];
    const timeMatch = dt.match(/[T ](\d{2}):(\d{2})/);
    if (timeMatch) newPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
  }
});

// Single plan: a template, team and date were checked and nothing clashed.
const singleChecked = computed(() =>
  Boolean(newPlanTemplate.value && newPlanTeamId.value && newPlanDate.value)
  && !duplicateCheckLoading.value && duplicateCheck.value !== null && !duplicateCheck.value.exists
);

const footNote = computed(() => {
  const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
  if (newPlanMode.value === "single") {
    if (duplicateCheck.value?.exists && duplicateCheck.value.strict) return "Resolve the conflict to continue";
    if (!newPlanTemplate.value) return "An empty plan: add movements to it afterwards";
    const legs = newPlanMovementPosition.value !== null ? 1 : selectedNewPlanTemplate.value?.legs?.length ?? 0;
    return `1 plan${selectedNewPlanTeam.value ? ` for ${selectedNewPlanTeam.value.code}` : ""} · ${plural(legs, "movement")}`;
  }
  if (!newPlanTemplate.value) return "Select a template";
  const allowed = newPlanMode.value === "matches" ? matchAllowedPreview.value : bulkAllowedPreview.value;
  const { blocked, selectable } = pickSummary.value;
  if (!allowed.teamsCount) return selectable ? "Tick at least one team" : "Nothing to create";
  return `${plural(allowed.plansCount, "plan")} for ${plural(allowed.teamsCount, "team")}${blocked ? ` · ${blocked} skipped` : ""}`;
});

const cta = computed(() => {
  if (newPlanMode.value === "single") {
    return {
      label: newPlanProcessing.value ? "Creating…" : duplicateCheckLoading.value ? "Checking…" : "Create plan",
      disabled: newPlanProcessing.value || duplicateCheckLoading.value || Boolean(duplicateCheck.value?.exists && duplicateCheck.value?.strict),
      action: createPlan,
    };
  }
  const matches = newPlanMode.value === "matches";
  const allowed = matches ? matchAllowedPreview.value : bulkAllowedPreview.value;
  const plansCount = allowed.plansCount;
  const label = newPlanProcessing.value ? "Creating…"
    : pickLoading.value ? "Checking…"
    : plansCount ? `Create ${plansCount} plan${plansCount === 1 ? "" : "s"}`
    : pickSummary.value.selectable ? "Select at least one team"
    : pickSummary.value.blocked ? "Nothing to create" : "Create plans";
  return {
    label,
    disabled: newPlanProcessing.value || pickLoading.value || !plansCount,
    action: matches ? createMatchPlans : createBulkPlans,
  };
});

function createPlan() {
  // Reset errors
  newPlanErrors.value = {};

  // Validate required fields
  if (!newPlanDate.value) {
    newPlanErrors.value.date = "Date is required";
    return;
  }

  // Validate flight selection if team has multiple flights
  if (needsFlightSelection.value && !newPlanFlightId.value) {
    newPlanErrors.value.flight_id = "Please select a flight";
    return;
  }

  // Check for strict duplicates - block submission
  if (duplicateCheck.value?.exists && duplicateCheck.value?.strict) {
    newPlanErrors.value.duplicate = duplicateCheck.value.message;
    return;
  }

  // Check for soft warning - require confirmation
  if (duplicateCheck.value?.exists && !duplicateCheck.value?.strict && !duplicateConfirmed.value) {
    if (!confirm(duplicateCheck.value.message + '\n\nCreate anyway?')) {
      return;
    }
    duplicateConfirmed.value = true;
  }

  newPlanProcessing.value = true;

  router.post(
    "/plans",
    {
      date: newPlanDate.value,
      start_time: newPlanStartTime.value || "09:00",
      name: newPlanName.value || null,
      team_id: newPlanTeamId.value || null,
      flight_id: newPlanFlightId.value || null,
      accommodation_id: newPlanAccommodationId.value || null,
      movement_template_id: newPlanTemplate.value || null,
      movement_position: newPlanMovementPosition.value,
    },
    {
      onSuccess: () => {
        emit('close');
        newPlanDate.value = "";
        newPlanStartTime.value = "09:00";
        newPlanName.value = "";
        newPlanTeamId.value = null;
        newPlanFlightId.value = null;
        newPlanAccommodationId.value = null;
        newPlanTemplate.value = "";
        newPlanMovementPosition.value = null;
        newPlanErrors.value = {};
        duplicateCheck.value = null;
        duplicateCheckLoading.value = false;
        duplicateConfirmed.value = false;
      },
      onError: (errors) => {
        newPlanErrors.value = errors;
      },
      onFinish: () => {
        newPlanProcessing.value = false;
      },
    }
  );
}

function createBulkPlans() {
  // Reset errors
  newPlanErrors.value = {};

  // Validate movement template selection
  if (!newPlanTemplate.value) {
    newPlanErrors.value.template =
      "Movement template is required for bulk creation";
    return;
  }

  // Check if there are plans to create
  if (bulkPreview.value.plans.length === 0) {
    newPlanErrors.value.template = "No teams with arrival dates found";
    return;
  }

  // Check if there are any included teams (strict duplicates are never included)
  if (bulkAllowedPreview.value.plansCount === 0) {
    newPlanErrors.value.template = pickSummary.value.selectable > 0
      ? "Select at least one team"
      : "No teams available to create (all have duplicate conflicts)";
    return;
  }

  newPlanProcessing.value = true;

  // Prepare bulk data: one plan per unique arrival date with the included teams
  const bulkData = bulkPreview.value.plans
    .map((plan) => {
      const allowedTeams = plan.teams
        .filter((team) => bulkRow(team).picked)
        .map((team) => {
          let startTime = '09:00'; // fallback
          if (team.arrival_date_time) {
            const timeMatch = team.arrival_date_time.match(/[T ](\d{2}):(\d{2})/);
            if (timeMatch) {
              startTime = `${timeMatch[1]}:${timeMatch[2]}`;
            }
          }
          
          return {
            team_id: team.id,
            start_time: startTime,
            flight_id: team.selected_flight?.id || null,
          };
        });
      
      return {
        date: plan.date,
        teams: allowedTeams,
        movement_template_id: newPlanTemplate.value,
      };
    })
    .filter(plan => plan.teams.length > 0); // Only include plans that have at least one allowed team

  router.post(
    "/plans/bulk",
    {
      plans: bulkData,
    },
    {
      onSuccess: () => {
        emit('close');
        newPlanMode.value = "single";
        newPlanTemplate.value = "";
        newPlanMovementPosition.value = null;
        newPlanErrors.value = {};
      },
      onError: (errors) => {
        newPlanErrors.value = errors;
      },
      onFinish: () => {
        newPlanProcessing.value = false;
      },
    }
  );
}

function createMatchPlans() {
  // Reset errors
  newPlanErrors.value = {};

  // Validate movement template selection
  if (!newPlanTemplate.value) {
    newPlanErrors.value.template =
      "Movement template is required for match plan creation";
    return;
  }

  // Check if there are plans to create
  if (matchesPreview.value.plans.length === 0) {
    newPlanErrors.value.template = "No matches found";
    return;
  }

  // Check if there are any included teams (strict duplicates are never included)
  if (matchAllowedPreview.value.plansCount === 0) {
    newPlanErrors.value.template = pickSummary.value.selectable > 0
      ? "Select at least one team"
      : "No teams available to create (all have duplicate conflicts)";
    return;
  }

  newPlanProcessing.value = true;

  // Prepare match plans data, one per match, with the included teams
  const matchPlansData = matchesPreview.value.plans
    .map((plan) => {
      const allowedTeams = plan.teams
        .filter((team) => matchRow(team).picked)
        .map((team) => ({
          team_id: team.team_id,
          start_time: team.plan_time,
          match_id: team.match_id,
        }));

      // Debug logging for empty plans
      if (allowedTeams.length === 0) {
        console.log(`Plan for ${plan.date} (${plan.match_number}) has no allowed teams - will be skipped`);
      }

      return {
        date: plan.date,
        teams: allowedTeams,
        movement_template_id: newPlanTemplate.value,
      };
    })
    .filter(plan => plan.teams.length > 0); // Only include plans that have at least one allowed team

  // Debug: Log what's being sent to backend
  console.log('Sending match plans data:', matchPlansData);
  console.log('Total plans to create:', matchPlansData.length);
  console.log('Plan dates:', matchPlansData.map(p => p.date));

  router.post(
    "/plans/bulk-matches",
    {
      plans: matchPlansData,
    },
    {
      onSuccess: () => {
        emit('close');
        newPlanMode.value = "single";
        newPlanTemplate.value = "";
        newPlanMovementPosition.value = null;
        newPlanErrors.value = {};
      },
      onError: (errors) => {
        newPlanErrors.value = errors;
      },
      onFinish: () => {
        newPlanProcessing.value = false;
      },
    }
  );
}
</script>

<style scoped src="./planDialog.css"></style>

<style scoped>
/* New plan dialog: layout after the "New Plan Modal" design, in the app's theme colours. */
.np {
  max-width: 640px;
  max-height: calc(100vh - 48px);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  border-radius: 16px;
  box-shadow: 0 24px 64px rgba(17, 24, 39, 0.3);
}
.np-head {
  flex: none;
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 18px 22px 16px;
  border-bottom: 1px solid var(--border);
}
.np-head__text { display: flex; flex-direction: column; gap: 3px; }
.np-title { font-size: 18px; font-weight: 600; color: var(--ink); }
.np-sub { font-size: 12px; color: var(--ink3); }
.np-close {
  width: 32px; height: 32px; border: none; border-radius: 8px;
  background: transparent; color: var(--ink3); font-size: 20px; cursor: pointer;
}
.np-close:hover { background: var(--panel); color: var(--ink); }

.np-body {
  flex: 1;
  min-height: 0;
  overflow: auto;
  padding: 18px 22px;
  display: flex;
  flex-direction: column;
  gap: 18px;
}
.np-section { display: flex; flex-direction: column; gap: 8px; }
.np-label { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; color: var(--ink3); }
.np-label-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }

.np-modes {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 4px;
  padding: 4px;
  border-radius: 12px;
  background: var(--panel);
}
.np-mode {
  display: flex; flex-direction: column; gap: 2px;
  padding: 10px; border: none; border-radius: 9px;
  background: transparent; text-align: left; cursor: pointer; font-family: inherit;
}
.np-mode__label { font-size: 13px; font-weight: 600; color: var(--ink); }
.np-mode__sub { font-size: 11px; color: var(--ink3); }
.np-mode--on { background: var(--surface); box-shadow: 0 1px 3px rgba(17, 24, 39, 0.12); }
.np-mode--on .np-mode__label { color: var(--accent); }
.np-mode:focus-visible { outline: 2px solid var(--accent); outline-offset: 1px; }

.np-input {
  height: 42px;
  width: 100%;
  min-width: 0;
  padding: 0 12px;
  border: 1px solid var(--border);
  border-radius: 10px;
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-family: inherit;
}
.np-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring, rgba(138, 21, 56, 0.15)); }
.np-input--error { border-color: var(--danger); }
.np-grid { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr) 112px; gap: 8px; }
.np-field { display: flex; flex-direction: column; gap: 5px; }
.np-field label { font-size: 12px; font-weight: 600; color: var(--ink2); }
.np-req { color: var(--danger); }
.np-opt { font-weight: 400; color: var(--ink3); }
.np-error { font-size: 12px; color: var(--danger); }
.np-hint { font-size: 12px; color: var(--ink3); }

.np-alert {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px; border-radius: 10px; border: 1px solid;
  font-size: 13px; line-height: 1.45;
}
.np-alert a { color: inherit; font-weight: 600; }
.np-alert__icon { font-weight: 700; }
.np-alert__tick {
  width: 18px; height: 18px; flex: none; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  background: var(--ok); color: #fff; font-size: 11px;
}
.np-alert--ok { background: var(--ok-soft); border-color: color-mix(in srgb, var(--ok) 35%, transparent); color: var(--ok); align-items: center; }
.np-alert--warn { background: var(--warn-soft); border-color: color-mix(in srgb, var(--warn) 35%, transparent); color: var(--warn); }
.np-alert--block { background: #fff7ed; border-color: #fed7aa; color: #9a3412; }
.np-alert--muted { background: var(--panel); border-color: var(--border); color: var(--ink3); }
:root[data-theme="dark"] .np-alert--block { background: rgba(234, 88, 12, 0.14); border-color: rgba(234, 88, 12, 0.4); color: #fdba74; }

.np-list { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.np-list__head { padding: 8px 14px; background: var(--panel); font-size: 11px; font-weight: 600; letter-spacing: 0.04em; color: var(--ink3); }
.np-list__foot { padding: 8px 14px; border-top: 1px solid var(--border); font-size: 11.5px; color: var(--ink3); }
.np-leg {
  display: grid; grid-template-columns: 32px auto minmax(0, 1fr) auto;
  gap: 10px; align-items: center; padding: 9px 14px;
  border-top: 1px solid var(--border);
}
.np-leg:first-of-type { border-top: none; }
.np-leg__no { font-family: var(--mono); font-size: 11px; font-weight: 700; color: var(--ink); }
.np-leg__route { display: flex; flex-direction: column; min-width: 0; font-size: 13px; color: var(--ink); }
.np-leg__steps { font-size: 11px; color: var(--ink3); }

.np-kind {
  flex: none; padding: 2px 6px; border-radius: 4px;
  font-size: 10px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase;
  background: var(--panel); color: var(--ink2);
}
.np-kind--flight, .np-kind--arrival { background: rgba(59, 130, 246, 0.12); color: #2563eb; }
.np-kind--bus, .np-kind--departure { background: rgba(245, 158, 11, 0.16); color: #b45309; }
.np-kind--match { background: rgba(168, 85, 247, 0.14); color: #7e22ce; }
.np-kind--training { background: rgba(14, 116, 144, 0.14); color: #0e7490; }
.np-kind--warn { background: var(--warn-soft); color: var(--warn); text-transform: none; }
:root[data-theme="dark"] .np-kind--flight, :root[data-theme="dark"] .np-kind--arrival { color: #93c5fd; }
:root[data-theme="dark"] .np-kind--bus, :root[data-theme="dark"] .np-kind--departure { color: #fcd34d; }
:root[data-theme="dark"] .np-kind--match { color: #d8b4fe; }

.np-time { font-family: var(--mono); font-size: 12px; color: var(--ink); white-space: nowrap; }

.np-search {
  height: 32px; width: 180px; padding: 0 10px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 12px; font-family: inherit;
}
.np-search:focus { outline: none; border-color: var(--ink); }

.np-summary { display: flex; flex-direction: column; gap: 8px; padding: 14px; border-radius: 12px; background: var(--panel); }
.np-bar { display: flex; gap: 2px; height: 8px; border-radius: 4px; overflow: hidden; background: var(--border); }
.np-bar__on { background: var(--accent); }
.np-bar__off { background: color-mix(in srgb, var(--accent) 35%, var(--surface)); }
.np-bar__dup { flex: 1; background: repeating-linear-gradient(135deg, var(--border) 0 3px, var(--panel) 3px 6px); }
.np-legend { display: flex; flex-wrap: wrap; gap: 16px; font-size: 12px; color: var(--ink2); }
.np-legend span { display: flex; align-items: center; gap: 6px; }
.np-legend strong { color: var(--ink); }
.np-dot { width: 8px; height: 8px; border-radius: 2px; display: inline-block; }
.np-dot--on { background: var(--accent); }
.np-dot--off { background: color-mix(in srgb, var(--accent) 35%, var(--surface)); }
.np-dot--dup { background: var(--border); }

.np-days { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.np-day { display: grid; grid-template-columns: 64px minmax(0, 1fr); border-bottom: 1px solid var(--border); }
.np-day:last-of-type { border-bottom: none; }
.np-day__date {
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  padding: 14px 0; background: var(--panel); border-right: 1px solid var(--border);
}
.np-day__wd { font-size: 10px; font-weight: 600; letter-spacing: 0.06em; color: var(--ink3); }
.np-day__num { font-size: 22px; font-weight: 600; line-height: 1.1; color: var(--accent); }
.np-day__num--off { color: var(--ink3); }
.np-day__body { display: flex; flex-direction: column; min-width: 0; }
.np-day__all { display: flex; align-items: center; gap: 10px; padding: 10px 14px 6px; font-size: 11px; color: var(--ink3); cursor: pointer; }
.np-day__all input, .np-row input { margin: 0; accent-color: var(--accent); cursor: pointer; }

.np-row {
  display: grid; grid-template-columns: 16px 64px minmax(0, 1fr) auto;
  gap: 10px; align-items: center; padding: 8px 14px; cursor: pointer;
}
.np-row:hover { background: var(--accent-soft); }
.np-row__team { font-size: 13px; font-weight: 600; color: var(--ink); }
.np-row--off .np-row__team { color: var(--ink3); }
.np-row__detail { display: flex; align-items: center; gap: 6px; min-width: 0; font-size: 12px; color: var(--ink2); }
.np-row__text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.np-row__flight {
  min-width: 0; height: 26px; padding: 0 6px;
  border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12px; font-family: inherit;
}

.np-dups { display: flex; flex-direction: column; gap: 6px; padding: 8px 14px 12px; }
.np-dups__toggle {
  align-self: flex-start; display: flex; align-items: center; gap: 6px;
  padding: 0; border: none; background: none; font-size: 11px; color: var(--ink3); cursor: pointer; font-family: inherit;
}
.np-caret { display: inline-block; transition: transform 0.15s; }
.np-caret--open { transform: rotate(90deg); }
.np-dups__list { display: flex; flex-direction: column; gap: 2px; padding-left: 14px; }
.np-dups__row {
  display: grid; grid-template-columns: 64px minmax(0, 1fr) auto;
  gap: 10px; align-items: center; padding: 3px 0; font-size: 12px; color: var(--ink3);
}
.np-dups__row .np-row__team { color: var(--ink3); font-size: 12px; }
.np-ref { font-family: var(--mono); font-size: 11px; color: var(--accent); }
.np-dups__chips { display: flex; flex-wrap: wrap; gap: 4px; padding-left: 14px; }
.np-chip { padding: 2px 7px; border-radius: 999px; background: var(--panel); color: var(--ink3); font-size: 11px; }

.np-empty { padding: 24px; text-align: center; font-size: 13px; color: var(--ink3); }
.np-missing { padding: 10px 14px; border-top: 1px solid var(--border); background: var(--warn-soft); color: var(--warn); font-size: 12px; }

.np-foot {
  flex: none; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  padding: 14px 22px; border-top: 1px solid var(--border); background: var(--surface);
}
.np-foot__note { font-size: 12px; color: var(--ink2); }
.np-foot__actions { display: flex; gap: 8px; margin-left: auto; }
.np-btn {
  height: 38px; padding: 0 16px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-weight: 500; font-family: inherit; cursor: pointer;
}
.np-btn:hover:not(:disabled) { background: var(--panel); }
.np-btn--primary { border: none; padding: 0 18px; background: var(--accent); color: #fff; font-weight: 600; }
.np-btn--primary:hover:not(:disabled) { background: var(--accent); filter: brightness(1.08); }
.np-btn:disabled { cursor: not-allowed; }
.np-btn--primary:disabled { background: var(--panel); color: var(--ink3); }

@media (max-width: 560px) {
  .np-grid { grid-template-columns: 1fr 1fr; }
  .np-modes { grid-template-columns: 1fr; }
}
</style>
