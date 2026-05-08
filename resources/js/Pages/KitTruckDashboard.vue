<template>
  <app-layout>

    <!-- ── Page Header ─────────────────────────────────────────────── -->
    <div class="page-header">
      <div>
        <h1 class="page-title">Movements Dashboard</h1>
        <p class="page-sub">{{ filteredRows.length }} of {{ rows.length }} movements · planned vs actual</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['rows', 'columns', 'kindCounts']" />
        <Button variant="secondary" size="sm">
          <template #icon><svg-icon name="filter" :size="14" /></template>
          Filter
        </Button>
      </div>
    </div>

    <!-- ── Summary Stats ───────────────────────────────────────────── -->
    <div class="stats-grid">
      <mini-stat label="Total Movements"  :value="rows.length" />
      <mini-stat label="Jobs Active"      :value="withJobCount"  tone="primary" />
      <mini-stat label="On Time"          :value="onTimeCount"   tone="ok" />
      <mini-stat label="Late Checkpoints" :value="lateCount"     tone="warn" />
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
        <p class="empty-title">No {{ kindLabel }} movements found</p>
        <p class="empty-sub">
          {{ searchQuery
            ? 'Try clearing the search filter.'
            : `Create movements with kind "${filters.kind}" and assign a checkpoint template.`
          }}
        </p>
      </div>

      <!-- Table -->
      <div v-else style="overflow-x: auto;">
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
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">From</th>
                <th class="mv-th mv-th--left mv-th--wide" rowspan="2">Hotel</th>
              </template>
              <template v-else-if="filters.kind === 'departure'">
                <th class="mv-th" rowspan="2">Date</th>
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
                colspan="2"
              >{{ col.name }}</th>
            </tr>

            <!-- Row 2: Planned / Actual -->
            <tr>
              <template v-for="col in columns" :key="col.order">
                <th class="mv-th mv-th--sub mv-th--planned">Planned</th>
                <th class="mv-th mv-th--sub mv-th--actual">Actual</th>
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
                :key="row.movement_id"
                class="mv-data-row"
              >
                <!-- PMA -->
                <td class="mv-td mv-td--pma">
                  <span v-if="row.flag" class="mv-flag">{{ row.flag }}</span>
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
                  <td class="mv-td mv-td--muted">{{ row.hotel || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.stadium || '—' }}</td>
                </template>
                <template v-else-if="filters.kind === 'arrival'">
                  <td class="mv-td mv-td--center mono">{{ row.flight_number || '—' }}</td>
                  <td class="mv-td mv-td--center mono">{{ row.match_date_label || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.from_location || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.hotel || '—' }}</td>
                </template>
                <template v-else-if="filters.kind === 'departure'">
                  <td class="mv-td mv-td--center mono">{{ row.match_date_label || '—' }}</td>
                  <td class="mv-td mv-td--muted">{{ row.hotel || '—' }}</td>
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
import MiniStat    from '../Components/MiniStat.vue';
import SvgIcon     from '../Components/SvgIcon.vue';
import Button      from '../Components/Button.vue';
import StatusPill  from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  rows:       { type: Array,  default: () => [] },
  columns:    { type: Array,  default: () => [] },
  kindCounts: { type: Object, default: () => ({}) },
  dates:      { type: Array,  default: () => [] },
  filters:    { type: Object, default: () => ({ kind: 'match', date: null }) },
});

// ── Local state ───────────────────────────────────────────────────────────
const searchQuery  = ref('');
const selectedKind = ref(props.filters.kind ?? 'match');
const selectedDate = ref(props.filters.date ?? '');

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
    case 'match':    return 7;
    case 'arrival':  return 6;
    default:         return 5;
  }
});

const totalCols = computed(() => staticCols.value + props.columns.length * 2);

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

// ── Stats ─────────────────────────────────────────────────────────────────
const withJobCount = computed(() => props.rows.filter(r => r.job_status).length);
const onTimeCount  = computed(() => {
  let n = 0;
  for (const r of props.rows) for (const cp of r.checkpoints ?? []) if (cp.is_on_time === true) n++;
  return n;
});
const lateCount = computed(() => {
  let n = 0;
  for (const r of props.rows) for (const cp of r.checkpoints ?? []) if (cp.is_on_time === false) n++;
  return n;
});

// ── Navigation ────────────────────────────────────────────────────────────
function setKind(kind) {
  searchQuery.value  = '';
  selectedDate.value = '';
  router.get('/kit-truck', { kind }, { preserveScroll: true });
}
function setDate(date) {
  router.get('/kit-truck', { kind: props.filters.kind, ...(date ? { date } : {}) }, { preserveScroll: true });
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

/* ── Stats grid — identical to Teams.vue ─────────────────────────── */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 16px;
}
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 640px)  { .stats-grid { grid-template-columns: 1fr; } }

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
  position: sticky; top: 0; z-index: 2;
}
.mv-th:last-child { border-right: none; }
.mv-th--left { text-align: left; }
.mv-th--wide { min-width: 150px; }
.mv-th--pma {
  text-align: left; min-width: 190px;
  position: sticky; left: 0; z-index: 3;
  border-right: 1px solid var(--border);
}

/* Checkpoint group header */
.mv-th--cp-group {
  color: var(--accent);
  border-left: 2px solid var(--border);
  min-width: 144px;
}

/* Sub-header row (Planned / Actual) */
.mv-th--sub     { background: var(--bg); font-size: 10px; font-weight: 500; }
.mv-th--planned { border-left: 2px solid var(--border); color: var(--ink4); }
.mv-th--actual  { color: var(--ink2); }

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
  position: sticky; left: 0; z-index: 1;
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
