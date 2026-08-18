<template>
  <app-layout>

    <!-- ── Page Header ─────────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Movements Dashboard</h1>
        <p class="page-sub">{{ filteredRows.length }} of {{ rows.length }} jobs · planned vs actual</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['rows', 'columns', 'kindCounts']" />
        <Button variant="secondary" size="sm">
          <template #icon><svg-icon name="filter" :size="14" /></template>
          Filter
        </Button>
      </div>
    </div>

    <!-- ── KPI Strip (phase-aware) ──────────────────────────────────── -->
    <div class="kpi-strip">
      <div v-for="(group, gi) in kpiGroups" :key="group.title" class="kpi-group" :class="{ 'kpi-group--divider': gi > 0 }">
        <div class="kpi-group-title">{{ group.title }}</div>
        <div class="kpi-tiles">
          <div v-for="tile in group.tiles" :key="tile.label" class="kpi-tile">
            <svg-icon v-if="tile.icon" :name="tile.icon" :size="22" class="kpi-tile-icon" />
            <div class="kpi-tile-value">{{ tile.value }}</div>
            <div class="kpi-tile-label">{{ tile.label }}</div>
            <div v-if="tile.sub" class="kpi-tile-sub">{{ tile.sub }}</div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Table Controls (outside card, like Teams) ───────────────── -->
    <div class="table-header">
      <div class="table-controls">
        <!-- Team search -->
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search teams..."
            class="search-input"
          />
        </div>
        <!-- Movement kind -->
        <select v-model="selectedKind" class="filter-select" @change="setKind(selectedKind)">
          <option v-for="k in kindOptions" :key="k.value" :value="k.value">
            {{ k.label }} ({{ kindCounts[k.value] ?? 0 }})
          </option>
        </select>
        <!-- Event -->
        <select v-if="events.length" v-model="selectedEvent" class="filter-select" @change="setEvent(selectedEvent)">
          <option value="">All events</option>
          <option v-for="evt in events" :key="evt.id" :value="evt.id">{{ evt.name }}</option>
        </select>
        <!-- Functional Area -->
        <select v-if="functionalAreas.length" v-model="selectedFunctionalArea" class="filter-select" @change="setFunctionalArea(selectedFunctionalArea)">
          <option value="">All functional areas</option>
          <option v-for="fa in functionalAreas" :key="fa.value" :value="fa.value">{{ fa.label }}</option>
        </select>
        <!-- Date -->
        <select v-if="dates.length" v-model="selectedDate" class="filter-select" @change="setDate(selectedDate)">
          <option value="">All dates</option>
          <option v-for="d in dates" :key="d.value" :value="d.value">{{ d.label }}</option>
        </select>
      </div>
      <div class="table-controls">
        <span class="checkpoint-badge">
          <svg width="12" height="12" viewBox="0 0 16 16" fill="none" style="flex-shrink:0">
            <circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.5"/>
            <path d="M5 8l2 2 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          {{ columns.length }} checkpoint{{ columns.length !== 1 ? 's' : '' }} tracked
        </span>
      </div>
    </div>

    <!-- ── Table Card ──────────────────────────────────────────────── -->
    <div class="table-card">

      <!-- Empty state -->
      <div v-if="filteredRows.length === 0" class="empty-state">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" style="color:var(--ink4);margin-bottom:12px">
          <rect x="3" y="3" width="18" height="18" rx="2" stroke="currentColor" stroke-width="1.5"/>
          <path d="M3 9h18M9 9v12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <p class="empty-title">No {{ kindLabel }} jobs found</p>
        <p class="empty-sub">
          {{ searchQuery
            ? 'Try clearing the search filter.'
            : `Create movements with kind "${filters.kind}", assign a checkpoint template, and generate jobs.`
          }}
        </p>
      </div>

      <!-- Table -->
      <div v-else class="table-scroll-wrapper">
        <table class="mv-table">
          <thead>
            <!-- Row 1: column group headers -->
            <tr>
              <th class="mv-th mv-th--pma" rowspan="2">PMA</th>

              <template v-if="filters.kind === 'match'">
                <th class="mv-th" rowspan="2">Match</th>
                <th class="mv-th" rowspan="2">Date</th>
                <th class="mv-th" rowspan="2">KO</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">Hotel</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">Stadium</th>
              </template>
              <template v-else-if="filters.kind === 'arrival'">
                <th class="mv-th" rowspan="2">Flight</th>
                <th class="mv-th" rowspan="2">Date</th>
                <th class="mv-th mv-th--cp-group" colspan="2">PMA Arrival</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">From</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">To</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">Hotel</th>
              </template>
              <template v-else-if="filters.kind === 'departure'">
                <th class="mv-th" rowspan="2">Date</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">From</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">Hotel</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">To</th>
              </template>
              <template v-else>
                <th class="mv-th" rowspan="2">Date</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">From</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">To</th>
              </template>

              <th class="mv-th" rowspan="2">Status</th>

              <!-- Checkpoint group header (one per checkpoint) -->
              <th
                v-for="col in columns"
                :key="col.order"
                class="mv-th mv-th--cp-group"
                :colspan="col.requires_baggage_count ? 4 : 2"
              >{{ col.name }}</th>
            </tr>

            <!-- Row 2: Planned / Actual OR Baggage Count -->
            <tr>
              <!-- PMA Arrival sub-headers for arrival kind only -->
              <template v-if="filters.kind === 'arrival'">
                <th class="mv-th mv-th--sub mv-th--planned">Planned</th>
                <th class="mv-th mv-th--sub mv-th--actual">Actual</th>
              </template>

              <template v-for="col in columns" :key="col.order">
                <template v-if="col.requires_baggage_count">
                  <th class="mv-th mv-th--sub mv-th--baggage">Planned Bags</th>
                  <th class="mv-th mv-th--sub mv-th--baggage">Actual Bags</th>
                  <th class="mv-th mv-th--sub mv-th--baggage">Food Bags</th>
                  <th class="mv-th mv-th--sub mv-th--baggage">Oversized Pieces</th>
                </template>
                <template v-else>
                  <th class="mv-th mv-th--sub mv-th--planned">Planned</th>
                  <th class="mv-th mv-th--sub mv-th--actual">Actual</th>
                </template>
              </template>
            </tr>
          </thead>

          <tbody>
            <template v-for="group in groupedRows" :key="group.date">

              <!-- Date group divider -->
              <tr>
                <td :colspan="totalCols" class="mv-group-label">{{ group.label }}</td>
              </tr>

              <!-- Data rows -->
              <tr
                v-for="row in group.rows"
                :key="row.job_id"
                class="mv-data-row"
              >
                <!-- PMA -->
                <td class="mv-td mv-td--pma">
                  <flag-icon :code="row.country_code" :fallback="row.flag" class="mv-flag" />
                  <div>
                    <div class="team-code">{{ row.team_code }}</div>
                    <div class="team-name">{{ row.team_name }}</div>
                  </div>
                </td>

                <!-- Kind-specific static cells -->
                <template v-if="filters.kind === 'match'">
                  <td class="mv-td mv-td--center">
                    <span class="match-badge">{{ row.match_number || '—' }}</span>
                  </td>
                  <td class="mv-td mv-td--center mono">{{ row.match_date_label || '—' }}</td>
                  <td class="mv-td mv-td--center">
                    <span class="ko-badge">{{ row.kick_off || '—' }}</span>
                  </td>
                  <td class="mv-td mv-td--muted">{{ row.hotel?.name || row.hotel || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.stadium?.name || row.stadium || '—' }}</td>
                </template>
                <template v-else-if="filters.kind === 'arrival'">
                  <td class="mv-td mv-td--center mono">{{ row.flight_number || '—' }}</td>
                  <td class="mv-td mv-td--center mono">{{ row.match_date_label || '—' }}</td>
                  <td class="mv-td mv-td--time mv-td--planned">
                    {{ row.flight_scheduled_at || '—' }}
                  </td>
                  <td class="mv-td mv-td--time">
                    <span class="actual-val">{{ row.flight_actual_at || '—' }}</span>
                  </td>
                  <td class="mv-td mv-td--muted">{{ row.from_location || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.to_location || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.hotel?.name || row.hotel || '—' }}</td>
                </template>
                <template v-else-if="filters.kind === 'departure'">
                  <td class="mv-td mv-td--center mono">{{ row.match_date_label || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.from_location || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.hotel?.name || row.hotel || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.to_location || '—' }}</td>
                </template>
                <template v-else>
                  <td class="mv-td mv-td--center mono">{{ row.match_date_label || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.from_location || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.to_location || '—' }}</td>
                </template>

                <!-- Job status -->
                <td class="mv-td mv-td--center">
                  <status-pill v-if="row.job_status" :tone="statusTone(row.job_status)" dot size="sm">
                    {{ row.job_status }}
                  </status-pill>
                  <span v-else class="no-job">—</span>
                </td>

                <!-- Dynamic checkpoint pairs -->
                <template v-for="col in columns" :key="col.order">
                  <template v-if="col.requires_baggage_count">
                    <td class="mv-td mv-td--center mv-td--baggage">
                      <span class="baggage-count">{{ cpField(row, col.order, 'planned_bags') ?? '—' }}</span>
                    </td>
                    <td class="mv-td mv-td--center mv-td--baggage">
                      <span class="baggage-count">{{ cpField(row, col.order, 'bags_loaded') ?? '—' }}</span>
                    </td>
                    <td class="mv-td mv-td--center mv-td--baggage">
                      <span class="baggage-count">{{ cpField(row, col.order, 'food_bags') ?? '—' }}</span>
                    </td>
                    <td class="mv-td mv-td--center mv-td--baggage">
                      <span class="baggage-count">{{ cpField(row, col.order, 'oversized_pieces') ?? '—' }}</span>
                    </td>
                  </template>
                  <template v-else>
                    <td class="mv-td mv-td--time mv-td--planned">
                      {{ cpField(row, col.order, 'scheduled_at') || '—' }}
                    </td>
                    <td class="mv-td mv-td--time" :class="cpHighlight(row, col.order)">
                      <span class="actual-val">{{ cpField(row, col.order, 'completed_at') || '—' }}</span>
                      <span
                        v-if="cpDelta(row, col.order) !== null"
                        class="delta-badge"
                        :class="cpDelta(row, col.order) > 0 ? 'delta-badge--late' : 'delta-badge--early'"
                      >{{ cpDeltaLabel(row, col.order) }}</span>
                    </td>
                  </template>
                </template>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- Legend footer -->
      <div v-if="filteredRows.length && columns.length" class="table-legend">
        <div class="legend-left">
          <span class="legend-dot legend-dot--late"></span><span class="legend-text">Late (&gt; 5 min)</span>
          <span class="legend-dot legend-dot--early"></span><span class="legend-text">Early (&gt; 5 min)</span>
          <span class="legend-dot legend-dot--ontime"></span><span class="legend-text">On time</span>
        </div>
        <span class="legend-right">{{ filteredRows.length }} rows · {{ columns.length }} checkpoints</span>
      </div>
    </div>

  </app-layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout   from '../Components/AppLayout.vue';
import SvgIcon     from '../Components/SvgIcon.vue';
import Button      from '../Components/Button.vue';
import StatusPill  from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import FlagIcon     from '../Components/FlagIcon.vue';

const props = defineProps({
  rows:       { type: Array,  default: () => [] },
  columns:    { type: Array,  default: () => [] },
  kindCounts: { type: Object, default: () => ({}) },
  dates:      { type: Array,  default: () => [] },
  events:     { type: Array,  default: () => [] },
  functionalAreas: { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({ kind: 'match', date: null, event: null, functional_area: null }) },
});

// ── Local state ───────────────────────────────────────────────────────────
const searchQuery  = ref('');
const selectedKind = ref(props.filters.kind ?? 'match');
const selectedDate = ref(props.filters.date ?? '');
const selectedEvent = ref(props.filters.event ?? '');
const selectedFunctionalArea = ref(props.filters.functional_area ?? '');

// ── Kind config ───────────────────────────────────────────────────────────
const kindOptions = [
  { value: 'arrival',   label: 'Arrivals'   },
  { value: 'departure', label: 'Departures' },
  { value: 'match',     label: 'Match Day'  },
  { value: 'training',  label: 'Training'   },
  { value: 'transfer',  label: 'Transfer'   },
];

const kindLabel = computed(() =>
  kindOptions.find(k => k.value === props.filters.kind)?.label ?? props.filters.kind
);

// Static columns per kind: PMA + kind-cols + Status col
const staticCols = computed(() => {
  switch (props.filters.kind) {
    case 'match':     return 7;
    case 'arrival':   return 7;
    case 'departure': return 6;
    default:          return 5;
  }
});

const totalCols = computed(() =>
  staticCols.value + props.columns.reduce((sum, col) => sum + (col.requires_baggage_count ? 4 : 2), 0)
);

// ── Status ────────────────────────────────────────────────────────────────
function statusTone(s) {
  return { completed: 'ok', 'in-progress': 'live', dispatched: 'primary', pending: 'neutral', cancelled: 'neutral' }[s] ?? 'neutral';
}

// ── Checkpoint helpers ────────────────────────────────────────────────────
function getCheckpoint(row, order) {
  return row.checkpoints?.find(c => c.order === order) ?? null;
}
function cpField(row, order, field) {
  return getCheckpoint(row, order)?.[field] ?? null;
}
function cpDelta(row, order) {
  const cp = getCheckpoint(row, order);
  if (!cp?.scheduled_ts || !cp?.completed_ts) return null;
  return Math.round((cp.completed_ts - cp.scheduled_ts) / 60);
}
function cpDeltaLabel(row, order) {
  const d = cpDelta(row, order);
  if (d === null) return '';
  if (d === 0) return 'on time';
  return d > 0 ? `+${d}m` : `${Math.abs(d)}m early`;
}
function cpHighlight(row, order) {
  const cp = getCheckpoint(row, order);
  if (!cp?.completed_ts) return '';
  const d = cpDelta(row, order);
  if (d === null) return '';
  if (d >  5) return 'mv-td--late';
  if (d < -5) return 'mv-td--early';
  return 'mv-td--ontime';
}

// ── Filtering & grouping ──────────────────────────────────────────────────
const filteredRows = computed(() => {
  if (!searchQuery.value) return props.rows;
  const q = searchQuery.value.toLowerCase();
  return props.rows.filter(r =>
    r.team_code?.toLowerCase().includes(q) ||
    r.team_name?.toLowerCase().includes(q)
  );
});

const groupedRows = computed(() => {
  const groups = {};
  for (const row of filteredRows.value) {
    const date  = row.match_date ?? 'unknown';
    const label = row.match_date_label ?? date;
    if (!groups[date]) groups[date] = { date, label, rows: [] };
    groups[date].rows.push(row);
  }
  return Object.values(groups).sort((a, b) => a.date.localeCompare(b.date));
});

// ── KPI strip ────────────────────────────────────────────────────────────
// Phase 1: KPI tiles matched to checkpoints by NAME (case-insensitive), since
// checkpoint templates are the source of truth for what stages exist per
// movement kind. This is a best-effort mapping against the checkpoint names
// currently configured in this event's templates — phase 2 will make the
// tile-to-checkpoint mapping and target thresholds configurable per event
// instead of hardcoded here.
function findCheckpointOrder(name) {
  const target = name.trim().toUpperCase();
  const col = props.columns.find(c => c.name?.trim().toUpperCase() === target);
  return col ? col.order : null;
}
function checkpointAt(row, order) {
  if (order == null) return null;
  return row.checkpoints?.find(c => c.order === order) ?? null;
}
function avgDeltaMinutes(rows, name) {
  const order = findCheckpointOrder(name);
  const vals = [];
  for (const r of rows) {
    const cp = checkpointAt(r, order);
    if (cp?.scheduled_ts && cp?.completed_ts) vals.push((cp.completed_ts - cp.scheduled_ts) / 60);
  }
  return vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length) : null;
}
function avgDurationMinutes(rows, nameA, nameB) {
  const orderA = findCheckpointOrder(nameA);
  const orderB = findCheckpointOrder(nameB);
  const vals = [];
  for (const r of rows) {
    const a = checkpointAt(r, orderA);
    const b = checkpointAt(r, orderB);
    if (a?.completed_ts && b?.completed_ts) vals.push((b.completed_ts - a.completed_ts) / 60);
  }
  return vals.length ? Math.round(vals.reduce((a, b) => a + b, 0) / vals.length) : null;
}
function countOnTime(rows, name, onTime) {
  const order = findCheckpointOrder(name);
  let n = 0;
  for (const r of rows) {
    const cp = checkpointAt(r, order);
    if (cp && cp.is_on_time === onTime) n++;
  }
  return n;
}
function sumField(rows, name, field) {
  const order = findCheckpointOrder(name);
  let sum = 0, any = false;
  for (const r of rows) {
    const cp = checkpointAt(r, order);
    if (cp && cp[field] != null) { sum += cp[field]; any = true; }
  }
  return any ? sum : null;
}
function fmtMinutes(v) {
  if (v === null || v === undefined) return '—';
  return `${v} min${Math.abs(v) === 1 ? '' : 's'}`;
}
function fmtCount(v) {
  return v === null || v === undefined ? '—' : v;
}

const kpiGroups = computed(() => {
  const rows = props.rows;
  const kind = props.filters.kind;

  const teams = new Set(rows.map(r => r.team_code).filter(Boolean)).size;
  const planned = rows.length;
  const maxOrder = props.columns.reduce((max, c) => Math.max(max, c.order), 0);
  const completed = rows.filter(r => checkpointAt(r, maxOrder)?.completed_at).length;

  const overview = {
    title: kind === 'match' ? 'Operations Overview' : `${kindLabel.value} Overview`,
    tiles: [
      { icon: 'team', label: 'Teams', value: teams },
      kind === 'arrival'
        ? { icon: 'plane', label: 'Teams Arrived to Date', value: planned }
        : { icon: 'fleet', label: 'Movements Planned', value: planned },
      { icon: 'fleet', label: 'Movements Completed', value: completed },
    ],
  };

  if (kind === 'arrival') {
    overview.tiles.push({ icon: 'bus', label: 'Arrival by Bus', value: rows.filter(r => r.flight_number === 'BUS').length });
    return [
      overview,
      {
        title: 'GWC Performance',
        tiles: [
          { icon: 'check', label: 'On-time to Staging', value: countOnTime(rows, 'ARRIVAL TIME AT POA STAGING', true) },
          { icon: 'warn', label: 'Early/Late to Staging', value: countOnTime(rows, 'ARRIVAL TIME AT POA STAGING', false) },
          { icon: 'clock', label: 'Avg Planned v Actual (Staging)', value: fmtMinutes(avgDeltaMinutes(rows, 'ARRIVAL TIME AT POA STAGING')), sub: 'Negative = early' },
          { icon: 'clock', label: 'Avg Duration Airport → Hotel', value: fmtMinutes(avgDurationMinutes(rows, 'CONVOY ARRIVAL AT AIRPORT', 'LUGGAGE ARRIVAL TO HOTEL')) },
          { icon: 'clock', label: 'Avg Hotel Arrival → Offload', value: fmtMinutes(avgDurationMinutes(rows, 'LUGGAGE ARRIVAL TO HOTEL', 'OFFLOAD END TIME')) },
        ],
      },
      {
        title: 'External Performance',
        tiles: [
          { icon: 'clock', label: 'Avg Staging → Convoy Depart', value: fmtMinutes(avgDurationMinutes(rows, 'ARRIVAL TIME AT POA STAGING', 'POA STAGING CONVY DEPART')) },
          { icon: 'clock', label: 'Avg Convoy Depart → QAS Handover', value: fmtMinutes(avgDurationMinutes(rows, 'POA STAGING CONVY DEPART', 'QAS HANDOVER TIME')) },
          { icon: 'bag', label: 'Total Bags Received', value: fmtCount(sumField(rows, 'LUGGAGE PIECES', 'bags_loaded')) },
          { icon: 'clock', label: 'Avg Bag Loading Duration', value: fmtMinutes(avgDurationMinutes(rows, 'QAS HANDOVER TIME', 'KIT LOAD END')) },
        ],
      },
    ];
  }

  if (kind === 'departure') {
    return [
      overview,
      {
        title: 'GWC Performance',
        tiles: [
          { icon: 'clock', label: 'Avg Planned v Actual (Hotel Arrival)', value: fmtMinutes(avgDeltaMinutes(rows, 'ARRIVAL TIME AT HOTEL')) },
          { icon: 'clock', label: 'Avg Hotel Arrival → Loading Start', value: fmtMinutes(avgDurationMinutes(rows, 'ARRIVAL TIME AT HOTEL', 'BAG LOAD START')) },
          { icon: 'clock', label: 'Avg Bag Loading Duration', value: fmtMinutes(avgDurationMinutes(rows, 'BAG LOAD START', 'BAG LOAD END')) },
          { icon: 'clock', label: 'Avg Loading End → Departure', value: fmtMinutes(avgDurationMinutes(rows, 'BAG LOAD END', 'DEPARTURE FROM HOTEL')) },
          { icon: 'clock', label: 'Avg Departure → Airport Arrival', value: fmtMinutes(avgDurationMinutes(rows, 'DEPARTURE FROM HOTEL', 'LUGGAGE ARRIVAL AT AIRPORT')) },
        ],
      },
      {
        title: 'Baggage',
        tiles: [
          { icon: 'bag', label: 'Planned Bags', value: fmtCount(sumField(rows, 'LUGGAGE PIECES', 'planned_bags')) },
          { icon: 'bag', label: 'Actual Bags', value: fmtCount(sumField(rows, 'LUGGAGE PIECES', 'bags_loaded')) },
          { icon: 'bag', label: 'Food Bags', value: fmtCount(sumField(rows, 'LUGGAGE PIECES', 'food_bags')) },
          { icon: 'bag', label: 'Oversized Pieces', value: fmtCount(sumField(rows, 'LUGGAGE PIECES', 'oversized_pieces')) },
        ],
      },
    ];
  }

  if (kind === 'match') {
    overview.tiles.push({ icon: 'warn', label: 'Early/Late Arrivals', value: countOnTime(rows, 'GWC ARRIVAL TIME AT HOTEL', false) });
    return [
      overview,
      {
        title: 'GWC Performance',
        tiles: [
          { icon: 'clock', label: 'Avg Planned v Actual (Hotel Arrival)', value: fmtMinutes(avgDeltaMinutes(rows, 'GWC ARRIVAL TIME AT HOTEL')) },
          { icon: 'clock', label: 'Avg Duration Hotel → VSA', value: fmtMinutes(avgDurationMinutes(rows, 'DEPARTURE TIME FROM HOTEL TO FOP', 'GWC ARRIVAL AT VSA')) },
          { icon: 'clock', label: 'Avg Planned v Actual (VSA Arrival)', value: fmtMinutes(avgDeltaMinutes(rows, 'GWC ARRIVAL AT VSA')) },
          { icon: 'clock', label: 'Avg Duration VSA → Hotel', value: fmtMinutes(avgDurationMinutes(rows, 'GWC DEPARTURE FROM VSA/STADIUM TO HOTEL', 'ARRIVAL TO HOTEL')) },
        ],
      },
      {
        title: 'PMA Kit-Manager Performance',
        tiles: [
          { icon: 'clock', label: 'Avg Hotel Arrival → Loading Start', value: fmtMinutes(avgDurationMinutes(rows, 'GWC ARRIVAL TIME AT HOTEL', 'BAG LOAD START')) },
          { icon: 'bag', label: 'Avg Kit Loading Time (Hotel)', value: fmtMinutes(avgDurationMinutes(rows, 'BAG LOAD START', 'BAG LOAD END')) },
          { icon: 'clock', label: 'Avg Loading End → Hotel Departure', value: fmtMinutes(avgDurationMinutes(rows, 'BAG LOAD END', 'DEPARTURE TIME FROM HOTEL TO FOP')) },
          { icon: 'clock', label: 'Avg Final Whistle → Loading Start (Stadium)', value: fmtMinutes(avgDurationMinutes(rows, 'FINAL WHISTLE', 'LOADING AT STADIUM')) },
        ],
      },
      {
        title: 'SSOC',
        tiles: [
          { icon: 'clock', label: 'Avg VSA Departure → Hotel Unload Complete', value: fmtMinutes(avgDurationMinutes(rows, 'ARRIVAL TO HOTEL', 'HOTEL UNLOADING END TIME')) },
        ],
      },
    ];
  }

  // training / transfer / anything else: generic overview only
  return [overview];
});

// ── Navigation ────────────────────────────────────────────────────────────
function setKind(kind) {
  searchQuery.value  = '';
  selectedDate.value = '';
  selectedEvent.value = '';
  selectedFunctionalArea.value = '';
  router.get('/kit-truck', { kind }, { preserveScroll: true });
}
function setDate(date) {
  const params = { kind: props.filters.kind };
  if (date) params.date = date;
  if (props.filters.event) params.event = props.filters.event;
  if (props.filters.functional_area) params.functional_area = props.filters.functional_area;
  router.get('/kit-truck', params, { preserveScroll: true });
}
function setEvent(event) {
  const params = { kind: props.filters.kind };
  if (event) params.event = event;
  if (props.filters.date) params.date = props.filters.date;
  if (props.filters.functional_area) params.functional_area = props.filters.functional_area;
  router.get('/kit-truck', params, { preserveScroll: true });
}
function setFunctionalArea(functionalArea) {
  const params = { kind: props.filters.kind };
  if (functionalArea) params.functional_area = functionalArea;
  if (props.filters.date) params.date = props.filters.date;
  if (props.filters.event) params.event = props.filters.event;
  router.get('/kit-truck', params, { preserveScroll: true });
}
</script>

<style scoped>
/* ── Page header — identical to Teams.vue ─────────────────────────── */
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 16px; margin-bottom: 16px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub   { font-size: 13px; color: var(--ink3); margin: 0; }
.header-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }

/* ── KPI strip ────────────────────────────────────────────────────── */
.kpi-strip {
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
  gap: 0;
  margin-bottom: 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}
.kpi-group {
  flex: 1 1 auto;
  padding: 10px 12px;
  min-width: 0;
}
.kpi-group--divider {
  border-left: 1px solid var(--border);
}
.kpi-group-title {
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--accent);
  margin-bottom: 8px;
  white-space: nowrap;
}
.kpi-tiles {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}
.kpi-tile {
  flex: 1 1 64px;
  min-width: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.kpi-tile-icon {
  color: var(--accent);
  margin-bottom: 5px;
  flex-shrink: 0;
}
.kpi-tile-value {
  font-size: 16px;
  font-weight: 700;
  font-family: var(--mono);
  color: var(--ink);
  letter-spacing: -0.3px;
  line-height: 1.15;
}
.kpi-tile-label {
  font-size: 9px;
  color: var(--ink3);
  margin-top: 2px;
  line-height: 1.25;
  width: 100%;
  white-space: normal;
  overflow-wrap: break-word;
}
.kpi-tile-sub {
  font-size: 8px;
  color: var(--ink4);
  font-style: italic;
  margin-top: 1px;
}
@media (max-width: 900px) {
  .kpi-group--divider { border-left: none; border-top: 1px solid var(--border); }
}

/* ── Table header — identical to Teams.vue ───────────────────────── */
.table-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 0 0 8px 0; margin-bottom: 8px;
}
.table-controls { display: flex; gap: 8px; align-items: center; }

.search-box {
  position: relative; display: flex; align-items: center; gap: 8px;
  padding: 6px 12px; background: var(--surface);
  border: 1px solid var(--border); border-radius: 7px;
  transition: border-color 0.13s;
}
.search-box:focus-within { border-color: var(--accent); }
.search-box svg { color: var(--ink3); flex-shrink: 0; }
.search-input {
  border: none; background: none; outline: none;
  font-size: 13px; color: var(--ink); width: 200px; padding: 0;
}
.search-input::placeholder { color: var(--ink4); }

.filter-select {
  padding: 6px 12px; background: var(--surface);
  border: 1px solid var(--border); border-radius: 7px;
  font-size: 13px; color: var(--ink2); cursor: pointer;
  outline: none; font-family: inherit; transition: border-color 0.13s;
}
.filter-select:hover, .filter-select:focus { border-color: var(--accent); }

.checkpoint-badge {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 12px; color: var(--ink3); font-weight: 500;
  background: var(--panel); border: 1px solid var(--border);
  border-radius: 6px; padding: 5px 10px;
}

/* ── Table card — identical to Teams.vue ─────────────────────────── */
.table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 20px;
}

/* Empty state */
.empty-state {
  display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 60px 24px; text-align: center;
}
.empty-title { font-size: 14px; font-weight: 600; color: var(--ink2); margin: 0 0 6px; }
.empty-sub   { font-size: 12px; color: var(--ink4); margin: 0; max-width: 380px; line-height: 1.5; }

/* Table scroll wrapper */
.table-scroll-wrapper {
  overflow-x: auto;
  overflow-y: auto;
  max-height: calc(100vh - 400px);
  min-height: 400px;
}

/* ── Table — based on matches-table from Teams/Matches pages ─────── */
.mv-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  min-width: 800px;
}

/* Header cells */
.mv-th {
  padding: 9px 12px;
  text-align: center;
  font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--ink3);
  background: var(--panel);
  border-bottom: 1px solid var(--border);
  border-right: 1px solid var(--border);
  white-space: nowrap;
  position: sticky; top: 0; z-index: 3;
  box-shadow: 0 1px 0 var(--border);
}
.mv-th:last-child { border-right: none; }
.mv-th--left { text-align: left; }
.mv-th--wide { min-width: 150px; }
.mv-th--pma {
  text-align: left; min-width: 190px;
  position: sticky; left: 0; top: 0; z-index: 5;
  border-right: 1px solid var(--border);
  box-shadow: 1px 1px 0 var(--border);
}

/* Checkpoint group header */
.mv-th--cp-group {
  color: var(--accent);
  border-left: 2px solid var(--border);
  min-width: 144px;
}

/* Sub-header row (Planned / Actual / Baggage) */
.mv-th--sub     { background: var(--bg); font-size: 10px; font-weight: 500; position: sticky; top: 33px; z-index: 3; box-shadow: 0 1px 0 var(--border); }
.mv-th--planned { border-left: 2px solid var(--border); color: var(--ink4); }
.mv-th--actual  { color: var(--ink2); }
.mv-th--baggage { border-left: 2px solid var(--border); color: var(--accent); }

/* Group date divider */
.mv-group-label {
  background: var(--bg);
  padding: 5px 14px;
  font-size: 10px; font-weight: 700;
  text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--ink4);
  border-bottom: 1px solid var(--border);
}

/* Data rows — same hover as matches-table */
.mv-data-row { transition: background-color 0.13s; }
.mv-data-row:hover td { background: var(--panel) !important; }
.mv-data-row:hover .mv-td--pma { background: var(--panel) !important; }
.mv-data-row:last-child .mv-td { border-bottom: none; }

/* Data cells */
.mv-td {
  padding: 11px 12px;
  vertical-align: middle;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  border-right: 1px solid var(--border);
  color: var(--ink);
  white-space: nowrap;
}
.mv-td:last-child { border-right: none; }

.mv-td--pma {
  display: flex; align-items: center; gap: 8px;
  position: sticky; left: 0; z-index: 2;
  background: var(--surface);
  border-right: 1px solid var(--border);
}
.mv-td--center { text-align: center; }
.mv-td--muted  { font-size: 12px; color: var(--ink2); max-width: 180px; overflow: hidden; text-overflow: ellipsis; }
.mv-td--time   { text-align: center; font-variant-numeric: tabular-nums; padding: 11px 8px; }
.mv-td--planned {
  border-left: 2px solid var(--border);
  color: var(--ink4) !important;
  font-size: 12px;
}

/* Highlight states */
.mv-td--late   { background: #FEF2F2 !important; }
.mv-td--early  { background: #EFF6FF !important; }
.mv-td--ontime { background: #F0FDF4 !important; }

/* Team cell content */
.mv-flag { font-size: 20px; flex-shrink: 0; line-height: 1; }
.team-code {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 36px; height: 20px; padding: 0 6px;
  border-radius: 4px; background: var(--panel);
  font-size: 10px; font-weight: 700; color: var(--ink);
  margin-bottom: 2px; width: fit-content;
}
.team-name { font-size: 12px; font-weight: 600; color: var(--ink); }

/* Badges */
.match-badge {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 52px; height: 24px; padding: 0 8px;
  border-radius: 5px; background: var(--accent-soft);
  color: var(--accent-fg); font-size: 11px; font-weight: 700;
}
.ko-badge {
  display: inline-flex; align-items: center; justify-content: center;
  height: 22px; padding: 0 8px; border-radius: 4px;
  background: var(--panel); border: 1px solid var(--border);
  font-size: 12px; font-weight: 700; color: var(--ink);
  font-variant-numeric: tabular-nums;
}
.no-job { font-size: 11px; color: var(--ink4); }

/* Actual time + delta */
.actual-val { display: block; font-weight: 600; font-size: 13px; }
.delta-badge {
  display: block; font-size: 9px; font-weight: 700;
  margin-top: 1px; letter-spacing: 0.2px;
}
.delta-badge--late  { color: #DC2626; }
.delta-badge--early { color: #2563EB; }

/* Baggage count */
.mv-td--baggage { 
  background: var(--accent-soft) !important;
  border-left: 2px solid var(--border);
}
.baggage-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 22px;
  padding: 0 8px;
  border-radius: 4px;
  background: var(--surface);
  font-size: 13px;
  font-weight: 700;
  color: var(--accent-fg);
  font-variant-numeric: tabular-nums;
}

.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); }

/* ── Legend footer ────────────────────────────────────────────────── */
.table-legend {
  display: flex; align-items: center; justify-content: space-between;
  flex-wrap: wrap; gap: 10px;
  padding: 8px 14px;
  border-top: 1px solid var(--border);
  background: var(--bg);
}
.legend-left  { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.legend-right { font-size: 11px; color: var(--ink4); }

.legend-dot {
  display: inline-block; width: 8px; height: 8px;
  border-radius: 2px; margin-right: 4px;
}
.legend-dot--late   { background: #FCA5A5; }
.legend-dot--early  { background: #93C5FD; }
.legend-dot--ontime { background: #86EFAC; }
.legend-text { font-size: 11px; color: var(--ink3); font-weight: 500; }

/* ── Dark mode ────────────────────────────────────────────────────── */
:root[data-theme="dark"] .mv-td--late   { background: #2d0b0b !important; }
:root[data-theme="dark"] .mv-td--early  { background: #0c1d3d !important; }
:root[data-theme="dark"] .mv-td--ontime { background: #052e16 !important; }
:root[data-theme="dark"] .delta-badge--late  { color: #fca5a5; }
:root[data-theme="dark"] .delta-badge--early { color: #93c5fd; }
</style>
