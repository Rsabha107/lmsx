<template>
  <div class="ops-report" :class="{ 'ops-report--open': open }">
    <!-- Always-visible summary strip -->
    <div class="ops-strip">
      <button type="button" class="ops-toggle" :aria-expanded="open" @click="open = !open">
        <svg-icon name="chart" :size="15" />
        <span class="ops-title">{{ title }}</span>
        <svg-icon name="chevronDown" :size="14" class="ops-chevron" :class="{ 'ops-chevron--open': open }" />
      </button>

      <div class="ops-headline">
        <div v-for="stat in headline" :key="stat.label" class="hl">
          <span class="hl-value" :class="stat.empty ? 'hl-value--empty' : `tone-${stat.tone}`">{{ stat.value }}</span>
          <span class="hl-label">{{ stat.label }}</span>
        </div>
      </div>

      <!-- Mini chart doubles as the day picker when collapsed -->
      <div v-if="!open && byDate.length > 1" class="spark" :title="`${byDate.length} days`">
        <button
          v-for="day in byDate"
          :key="day.date"
          type="button"
          class="spark-bar"
          :class="{ 'spark-bar--on': day.date === selectedDate }"
          :style="{ height: `${barHeight(day.total)}%` }"
          :title="`${day.label}: ${day.total} job${day.total !== 1 ? 's' : ''}`"
          @click="toggleDay(day.date)"
        />
      </div>

      <span v-if="selectedDate" class="ops-chip">
        {{ selectedLabel }}
        <button type="button" class="ops-chip-x" aria-label="Clear day filter" @click="toggleDay(selectedDate)">
          <svg-icon name="x" :size="10" />
        </button>
      </span>
    </div>

    <!-- Expanded report -->
    <div v-if="open" class="ops-body">
      <!-- One continuous KPI rail, banded like the printed PMA report -->
      <div class="rail">
        <section v-for="band in bands" :key="band.name" class="rail-band" :class="`band-${band.tone}`">
          <h4 class="rail-title">{{ band.name }}</h4>
          <div class="rail-tiles">
            <div v-for="tile in band.tiles" :key="tile.label" class="tile">
              <div class="tile-value" :class="tile.empty ? 'tile-value--empty' : `tone-${tile.tone}`">{{ tile.value }}</div>
              <div class="tile-label">{{ tile.label }}</div>
              <div class="tile-note">{{ tile.note || '\u00A0' }}</div>
            </div>
          </div>
        </section>
      </div>

      <div class="chart-card">
        <div class="chart-head">
          <span class="chart-title">Jobs per day</span>
          <span class="chart-hint">click a bar to filter the list</span>
          <span class="legend">
            <span class="legend-item"><i class="swatch swatch--total" /> Scheduled</span>
            <span class="legend-item"><i class="swatch swatch--done" /> Completed</span>
          </span>
        </div>
        <div v-if="byDate.length" class="chart">
          <button
            v-for="day in byDate"
            :key="day.date"
            type="button"
            class="col"
            :class="{ 'col--on': day.date === selectedDate, 'col--dim': selectedDate && day.date !== selectedDate }"
            @click="toggleDay(day.date)"
          >
            <span class="col-value">{{ day.total }}</span>
            <span class="col-track">
              <span class="col-fill" :style="{ height: `${barHeight(day.total)}%` }" />
              <span v-if="day.done" class="col-done" :style="{ height: `${barHeight(day.done)}%` }" />
            </span>
            <span class="col-label">{{ day.label }}</span>
          </button>
        </div>
        <p v-else class="chart-empty">No dated jobs in this selection.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import SvgIcon from './SvgIcon.vue';

const props = defineProps({
  // Already filtered by the page's own controls, minus the day selection.
  jobs: { type: Array, default: () => [] },
  kind: { type: String, default: null },
  selectedDate: { type: String, default: null },
});

const emit = defineEmits(['update:selectedDate']);

const open = ref(false);

const DONE = ['completed', 'done'];
const LIVE = ['in-progress', 'live', 'dispatched'];

const title = computed(() => {
  const named = { arrival: 'Arrivals', departure: 'Departures', transfer: 'Transfers', match: 'Match movements', training: 'Training movements', daily_ops: 'Daily ops' };
  return `${named[props.kind] ?? 'Operations'} overview`;
});

// Day selection narrows the tiles too, so the numbers always describe the list.
const scoped = computed(() => props.selectedDate
  ? props.jobs.filter(j => j.date === props.selectedDate)
  : props.jobs);

const totals = computed(() => {
  const acc = {
    jobs: 0, done: 0, live: 0, delayed: 0, pax: 0, teams: new Set(),
    onTime: 0, offSchedule: 0, varianceAbsSum: 0, varianceCount: 0,
    bags: 0, plannedBags: 0, oversized: 0, checkpointSeconds: 0, checkpointsDone: 0,
    spanSum: 0, spanCount: 0,
  };

  for (const job of scoped.value) {
    const m = job.metrics ?? {};
    acc.jobs++;
    if (DONE.includes(job.status)) acc.done++;
    if (LIVE.includes(job.status)) acc.live++;
    if (job.delay > 0) acc.delayed++;
    acc.pax += Number(job.pax) || 0;
    if (job.code) acc.teams.add(job.code);

    acc.onTime += m.onTime ?? 0;
    acc.offSchedule += m.offSchedule ?? 0;
    acc.varianceAbsSum += m.varianceAbsSum ?? 0;
    acc.varianceCount += m.varianceCount ?? 0;
    acc.bags += m.bags ?? 0;
    acc.plannedBags += m.plannedBags ?? 0;
    acc.oversized += m.oversized ?? 0;
    acc.checkpointSeconds += m.checkpointSeconds ?? 0;
    acc.checkpointsDone += m.checkpointsDone ?? 0;
    if (m.spanMinutes != null) { acc.spanSum += m.spanMinutes; acc.spanCount++; }
  }

  return acc;
});

// A metric with nothing recorded yet reads "—", not "0" — the source report
// shows both as zero, which hides whether ops are perfect or simply untracked.
function mins(sum, count) {
  return count > 0 ? { value: `${Math.round(sum / count)} min`, empty: false } : { value: '—', empty: true };
}

function count(n) {
  return { value: String(n), empty: n === 0 };
}

const headline = computed(() => {
  const t = totals.value;
  return [
    { label: 'Jobs', value: String(t.jobs), empty: t.jobs === 0, tone: 'accent' },
    { label: 'Completed', value: String(t.done), empty: t.done === 0, tone: 'ok' },
    { label: 'Delayed', value: String(t.delayed), empty: t.delayed === 0, tone: 'danger' },
    { label: 'Teams', value: String(t.teams.size), empty: t.teams.size === 0, tone: 'info' },
  ];
});

const bands = computed(() => {
  const t = totals.value;
  const avgCheckpoint = t.checkpointsDone > 0
    ? { value: `${Math.round(t.checkpointSeconds / t.checkpointsDone / 60)} min`, empty: false }
    : { value: '—', empty: true };

  return [
    {
      name: 'Overview',
      tone: 'accent',
      tiles: [
        { label: 'Jobs in view', tone: 'accent', ...count(t.jobs) },
        { label: 'Teams involved', tone: 'info', ...count(t.teams.size) },
        { label: 'Completed to date', tone: 'ok', ...count(t.done) },
        { label: 'In progress', tone: 'info', ...count(t.live) },
      ],
    },
    {
      name: 'Schedule performance',
      tone: 'ok',
      tiles: [
        { label: 'On-time checkpoints', tone: 'ok', ...count(t.onTime) },
        { label: 'Early / late checkpoints', tone: 'warn', ...count(t.offSchedule) },
        { label: 'Avg. planned v actual', tone: 'warn', ...mins(t.varianceAbsSum, t.varianceCount), note: 'completed checkpoints' },
        { label: 'Delayed jobs', tone: 'danger', ...count(t.delayed) },
      ],
    },
    {
      name: 'Load & duration',
      tone: 'info',
      tiles: [
        { label: 'Bags loaded', tone: 'info', ...count(t.bags), note: t.plannedBags ? `${t.plannedBags} planned` : null },
        { label: 'Oversized pieces', tone: 'warn', ...count(t.oversized) },
        { label: 'Avg. checkpoint duration', tone: 'info', ...avgCheckpoint },
        { label: 'Passengers moved', tone: 'accent', ...count(t.pax) },
      ],
    },
  ];
});

const byDate = computed(() => {
  const days = new Map();

  for (const job of props.jobs) {
    if (!job.date) continue;
    if (!days.has(job.date)) days.set(job.date, { date: job.date, total: 0, done: 0 });
    const day = days.get(job.date);
    day.total++;
    if (DONE.includes(job.status)) day.done++;
  }

  return [...days.values()]
    .sort((a, b) => a.date.localeCompare(b.date))
    .map(day => ({ ...day, label: shortDate(day.date) }));
});

const maxTotal = computed(() => Math.max(1, ...byDate.value.map(d => d.total)));

const selectedLabel = computed(() =>
  props.selectedDate ? shortDate(props.selectedDate) : '');

function barHeight(value) {
  return value === 0 ? 2 : Math.max(6, (value / maxTotal.value) * 100);
}

function shortDate(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return `${date.toLocaleDateString('en-GB', { weekday: 'short' })}, ${String(d).padStart(2, '0')}-${date.toLocaleDateString('en-GB', { month: 'short' })}`;
}

function toggleDay(date) {
  emit('update:selectedDate', props.selectedDate === date ? null : date);
}
</script>

<style scoped>
/* Digits line up column-to-column, which is what makes a KPI rail read as
   symmetric even when the values have different widths. */
.ops-report {
  /* One palette for values, band rules and the chart, so a colour always
     means the same thing wherever it appears. */
  --tone-accent: var(--accent);
  --tone-ok: var(--ok, #16a34a);
  --tone-warn: var(--warn, #b45309);
  --tone-danger: var(--danger, #b91c1c);
  --tone-info: var(--blue, #2563eb);

  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 12px;
  margin: 14px 0 12px;
  overflow: hidden;
  font-variant-numeric: tabular-nums;
  transition: box-shadow .2s;
}

.tone-accent { color: var(--tone-accent); }
.tone-ok { color: var(--tone-ok); }
.tone-warn { color: var(--tone-warn); }
.tone-danger { color: var(--tone-danger); }
.tone-info { color: var(--tone-info); }
.ops-report--open { box-shadow: 0 4px 16px rgba(0, 0, 0, .07); }

.ops-strip {
  display: flex; align-items: center; gap: 18px;
  padding: 9px 14px; flex-wrap: wrap;
  background: linear-gradient(180deg, var(--panel), transparent);
}

.ops-toggle {
  display: inline-flex; align-items: center; gap: 7px;
  background: none; border: 0; padding: 0; cursor: pointer;
  color: var(--ink); font-size: 12.5px; font-weight: 700;
  letter-spacing: .01em;
}
.ops-toggle:hover { color: var(--accent); }
.ops-title { white-space: nowrap; }
.ops-chevron { transition: transform .2s; color: var(--ink3); }
.ops-chevron--open { transform: rotate(180deg); }

.ops-headline { display: flex; flex: 1 1 auto; flex-wrap: wrap; }
.hl {
  display: flex; align-items: baseline; gap: 6px;
  padding: 0 16px;
  border-left: 1px solid var(--border);
}
.hl:first-child { border-left: 0; padding-left: 0; }
.hl-value { font-size: 16px; font-weight: 700; color: var(--ink); line-height: 1; }
.hl-value--empty { color: var(--ink4); }.hl-label {
  font-size: 10px; font-weight: 700; letter-spacing: .06em;
  text-transform: uppercase; color: var(--ink3);
}

.spark { display: flex; align-items: flex-end; gap: 2px; height: 26px; }
.spark-bar {
  width: 6px; min-height: 2px; cursor: pointer; padding: 0;
  background: var(--border); border: 0; border-radius: 2px 2px 0 0;
  transition: background .15s, transform .15s;
}
.spark-bar:hover { background: var(--accent); transform: scaleY(1.12); }
.spark-bar--on { background: var(--accent); }

.ops-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 3px 6px 3px 10px; border-radius: 999px;
  background: var(--accent-soft, var(--panel)); color: var(--accent);
  font-size: 11px; font-weight: 700;
}
.ops-chip-x {
  display: grid; place-items: center; cursor: pointer;
  width: 15px; height: 15px; border: 0; border-radius: 50%;
  background: var(--accent); color: #fff;
}

.ops-body { border-top: 1px solid var(--border); }

/* ── KPI rail ─────────────────────────────────────────────────────────── */
.rail {
  display: flex; align-items: stretch;
  padding: 14px 6px 10px;
  overflow-x: auto;
}

.rail-band {
  display: flex; flex-direction: column; gap: 8px;
  flex: 1 1 0; min-width: 0;
  padding: 0 14px;
  border-left: 1px solid var(--border);
}
.rail-band:first-child { border-left: 0; }

.rail-title {
  margin: 0; padding-bottom: 7px;
  text-align: center; white-space: nowrap;
  font-size: 10.5px; font-weight: 800; letter-spacing: .1em;
  text-transform: uppercase;
  border-bottom: 2px solid currentColor;
}
.band-accent .rail-title { color: var(--tone-accent); }
.band-ok .rail-title { color: var(--tone-ok); }
.band-info .rail-title { color: var(--tone-info); }

.rail-tiles { display: flex; align-items: flex-start; flex: 1 1 auto; }

.tile {
  flex: 1 1 0; min-width: 74px;
  padding: 4px 6px 2px; text-align: center;
  border-radius: 8px;
  transition: background .15s;
}
.tile:hover { background: var(--panel); }

.tile-value {
  font-size: 22px; font-weight: 700; line-height: 1.05;
  color: var(--ink); letter-spacing: -.02em;
}
.tile-value--empty { color: var(--ink4); font-weight: 600; }
.tile-label {
  font-size: 9px; font-weight: 700; letter-spacing: .05em;
  text-transform: uppercase; color: var(--ink3);
  margin-top: 5px; line-height: 1.35;
}
.tile-note { font-size: 8.5px; font-style: italic; color: var(--ink4); margin-top: 2px; min-height: 11px; }

/* ── Chart ────────────────────────────────────────────────────────────── */
.chart-card { border-top: 1px solid var(--border); padding: 10px 14px 12px; }

.chart-head { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.chart-title {
  font-size: 10.5px; font-weight: 800; letter-spacing: .1em;
  text-transform: uppercase; color: var(--tone-accent);
}
.chart-hint { font-size: 10.5px; color: var(--ink4); }
.legend { display: flex; gap: 14px; margin-left: auto; }
.legend-item { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; color: var(--ink3); }
.swatch { width: 9px; height: 9px; border-radius: 2px; display: inline-block; }
.swatch--total { background: var(--border); }
.swatch--done { background: var(--tone-ok); }

.chart {
  display: flex; align-items: flex-end; gap: 8px;
  padding: 12px 2px 0; height: 146px; overflow-x: auto;
}
.col {
  display: flex; flex-direction: column; align-items: center; gap: 5px;
  flex: 1 1 0; min-width: 46px; height: 100%;
  background: none; border: 0; cursor: pointer; padding: 0;
  transition: opacity .15s;
}
.col--dim { opacity: .4; }
.col-value { font-size: 11px; font-weight: 700; color: var(--ink); }
.col-track {
  position: relative; width: 100%; max-width: 38px; flex: 1 1 auto;
  border-radius: 4px 4px 0 0;
  background: repeating-linear-gradient(
    to top, transparent 0 24%, var(--border) 24% calc(24% + 1px)
  );
}
.col-fill {
  position: absolute; bottom: 0; width: 100%;
  background: var(--border); border-radius: 4px 4px 0 0;
  transition: height .25s ease;
}
.col--on .col-fill, .col:hover .col-fill { background: var(--tone-accent); opacity: .28; }
.col-done {
  position: absolute; bottom: 0; width: 100%;
  background: var(--tone-ok); border-radius: 4px 4px 0 0;
  transition: height .25s ease;
}
.col-label { font-size: 9.5px; color: var(--ink3); white-space: nowrap; }

.chart-empty { margin: 0; padding: 22px; text-align: center; font-size: 12px; color: var(--ink3); }

@media (max-width: 900px) {
  .rail { flex-direction: column; gap: 14px; }
  .rail-band { border-left: 0; }
}
</style>
