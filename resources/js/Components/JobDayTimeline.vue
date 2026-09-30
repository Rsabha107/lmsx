<template>
  <div v-if="days.length" class="jdt">
    <!-- Date strip -->
    <div class="jdt-strip">
      <button
        type="button"
        :class="['jdt-all', { 'jdt-all--active': !selectedDate }]"
        @click="pick(null)"
      >
        <span class="jdt-all-label">All days</span>
        <span class="jdt-all-count">{{ jobs.length }}</span>
      </button>
      <button type="button" class="jdt-nav" :disabled="dayIndex === 0" aria-label="Previous day" @click="step(-1)">‹</button>
      <div ref="stripEl" class="jdt-days">
        <button
          v-for="d in days"
          :key="d.key"
          type="button"
          :class="['jdt-day', { 'jdt-day--active': d.key === selectedDate, 'jdt-day--today': d.key === todayKey }]"
          :title="`${d.total} job${d.total === 1 ? '' : 's'} · ${d.done} done${d.attention ? ` · ${d.attention} need attention` : ''}`"
          @click="pick(d.key)"
        >
          <span class="jdt-day-dow">{{ d.dow }}</span>
          <span class="jdt-day-num">{{ d.day }}</span>
          <span class="jdt-day-mon">{{ d.month }}</span>
          <span class="jdt-day-count">
            {{ d.total }}<span v-if="d.attention" class="jdt-day-warn">●</span>
          </span>
          <span class="jdt-day-progress"><span :style="{ width: `${(d.done / d.total) * 100}%` }" /></span>
        </button>
      </div>
      <button type="button" class="jdt-nav" :disabled="dayIndex >= days.length - 1" aria-label="Next day" @click="step(1)">›</button>
    </div>

    <!-- Gantt for the chosen day -->
    <div v-if="selectedDate && timeline.rows.length" class="jdt-tl">
      <div class="jdt-tl-head">
        <button type="button" class="jdt-tl-toggle" :aria-expanded="expanded" @click="expanded = !expanded">
          <svg-icon name="chevronDown" :size="14" :class="['jdt-chevron', { 'jdt-chevron--open': expanded }]" />
          Timeline · {{ longLabel }}
        </button>
        <span class="jdt-tl-meta">
          {{ timeline.count }} job{{ timeline.count === 1 ? '' : 's' }} · {{ timeline.rows.length }} team{{ timeline.rows.length === 1 ? '' : 's' }}
          <template v-if="timeline.untimed"> · {{ timeline.untimed }} without a time</template>
        </span>
        <span v-if="expanded" class="jdt-legend">
          <span v-for="s in legend" :key="s.key" class="jdt-legend-item">
            <i :class="['jdt-swatch', `jdt-st--${s.key}`]" />{{ s.label }}
          </span>
        </span>
      </div>

      <div v-show="expanded" class="jdt-tl-body">
        <div class="jdt-grid">
          <div class="jdt-label jdt-sticky"></div>
          <div class="jdt-axis jdt-sticky">
            <span v-for="h in timeline.hours" :key="h.left" class="jdt-hour" :style="{ left: h.left + '%' }">{{ h.label }}</span>
          </div>
          <template v-for="row in timeline.rows" :key="row.key">
            <div class="jdt-label">
              <flag-icon :code="row.countryCode" :fallback="row.flag" />
              <span>{{ row.team }}</span>
            </div>
            <div class="jdt-track" :style="{ height: row.lanes * LANE + 6 + 'px' }">
              <span v-for="h in timeline.hours" :key="h.left" class="jdt-gridline" :style="{ left: h.left + '%' }" />
              <span v-if="timeline.now != null" class="jdt-now" :style="{ left: timeline.now + '%' }" />
              <button
                v-for="bar in row.bars"
                :key="bar.job.id"
                type="button"
                :class="['jdt-bar', `jdt-st--${statusKey(bar.job.status)}`, { 'jdt-bar--selected': bar.job.id === selectedJobId }]"
                :style="{ left: bar.left + '%', width: bar.width + '%', top: bar.lane * LANE + 3 + 'px' }"
                :title="`${bar.job.id} · ${bar.startLabel}–${bar.endLabel}\n${bar.job.from} → ${bar.job.to}\n${legendLabel(bar.job.status)} · ${bar.progress}%`"
                @click="emit('select', bar.job)"
              >
                <span class="jdt-bar-text">{{ bar.job.id }}</span>
                <span class="jdt-bar-progress" :style="{ width: bar.progress + '%' }" />
              </button>
            </div>
          </template>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import SvgIcon from './SvgIcon.vue';
import FlagIcon from './FlagIcon.vue';

const props = defineProps({
  // Already filtered by the page's own controls, minus the day selection.
  jobs: { type: Array, default: () => [] },
  selectedDate: { type: String, default: null },
  selectedJobId: { type: String, default: null },
});

const emit = defineEmits(['update:selectedDate', 'select']);

const LANE = 24;
const DONE = ['completed', 'done'];
const STORAGE_KEY = 'jobs.timeline.expanded';

const expanded = ref(localStorage.getItem(STORAGE_KEY) !== '0');
watch(expanded, (v) => localStorage.setItem(STORAGE_KEY, v ? '1' : '0'));

const stripEl = ref(null);

function localKey(d) {
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
const todayKey = localKey(new Date());

// Refreshes the "now" marker without reloading jobs.
const nowMinutes = ref(0);
let clock = null;
function tick() {
  const d = new Date();
  nowMinutes.value = d.getHours() * 60 + d.getMinutes();
}
onMounted(() => { tick(); clock = setInterval(tick, 60000); });
onUnmounted(() => clearInterval(clock));

function statusKey(s) {
  if (DONE.includes(s)) return 'done';
  if (s === 'in-progress' || s === 'live') return 'live';
  if (s === 'dispatched') return 'dispatched';
  if (s === 'delayed' || s === 'issue') return 'delayed';
  if (s === 'cancelled') return 'cancelled';
  return 'scheduled';
}

const legend = [
  { key: 'scheduled', label: 'Scheduled' },
  { key: 'dispatched', label: 'Dispatched' },
  { key: 'live', label: 'In progress' },
  { key: 'delayed', label: 'Delayed' },
  { key: 'done', label: 'Done' },
];
function legendLabel(s) {
  return legend.find((l) => l.key === statusKey(s))?.label ?? 'Cancelled';
}

function needsAttention(job) {
  return statusKey(job.status) === 'delayed' || (job.issues ?? []).some((i) => !i.resolved_at);
}

function progressOf(job) {
  const cps = job.checkpoints ?? [];
  if (cps.length) return Math.round((cps.filter((c) => c.state === 'done').length / cps.length) * 100);
  return DONE.includes(job.status) ? 100 : 0;
}

function parts(key) {
  const [y, m, d] = key.split('-').map(Number);
  const date = new Date(y, m - 1, d);
  return {
    dow: date.toLocaleDateString('en-GB', { weekday: 'short' }),
    day: d,
    month: date.toLocaleDateString('en-GB', { month: 'short' }),
    long: date.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long' }),
  };
}

const days = computed(() => {
  const map = new Map();
  for (const job of props.jobs) {
    if (!job.date) continue;
    if (!map.has(job.date)) map.set(job.date, { key: job.date, total: 0, done: 0, attention: 0 });
    const day = map.get(job.date);
    day.total++;
    if (DONE.includes(job.status)) day.done++;
    if (needsAttention(job)) day.attention++;
  }
  return [...map.values()]
    .sort((a, b) => a.key.localeCompare(b.key))
    .map((d) => ({ ...d, ...parts(d.key) }));
});

const dayIndex = computed(() => days.value.findIndex((d) => d.key === props.selectedDate));
const longLabel = computed(() => (props.selectedDate ? parts(props.selectedDate).long : ''));

function pick(key) {
  if (key) expanded.value = true;
  emit('update:selectedDate', key);
}

function step(delta) {
  const next = dayIndex.value === -1 ? days.value[0] : days.value[dayIndex.value + delta];
  if (next) pick(next.key);
}

// A page filter can remove the chosen day; fall back to all days rather than an empty list.
watch(days, (list) => {
  if (props.selectedDate && !list.some((d) => d.key === props.selectedDate)) pick(null);
});

watch(() => props.selectedDate, () => {
  nextTick(() => {
    const strip = stripEl.value;
    const active = strip?.querySelector('.jdt-day--active');
    if (strip && active) strip.scrollLeft = active.offsetLeft - strip.clientWidth / 2 + active.clientWidth / 2;
  });
}, { immediate: true });

// Minutes from midnight of the job's own day, read as text so no timezone shift applies.
function minutesFrom(dayKey, stamp) {
  const m = stamp?.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/);
  if (!m) return null;
  const dayOffset = Math.round((new Date(`${m[1]}T00:00:00`) - new Date(`${dayKey}T00:00:00`)) / 86400000);
  return dayOffset * 1440 + Number(m[2]) * 60 + Number(m[3]);
}

function clockLabel(minutes) {
  const t = ((minutes % 1440) + 1440) % 1440;
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

const timeline = computed(() => {
  const empty = { hours: [], rows: [], count: 0, untimed: 0, now: null };
  if (!props.selectedDate) return empty;

  const dayJobs = props.jobs.filter((j) => j.date === props.selectedDate);
  const spans = [];
  for (const job of dayJobs) {
    const start = minutesFrom(job.date, job.span_start);
    if (start == null) continue;
    const end = minutesFrom(job.date, job.span_end);
    spans.push({ job, start, end: Math.max(end ?? start + 30, start + 15) });
  }
  if (!spans.length) return { ...empty, untimed: dayJobs.length };

  const from = Math.floor(Math.min(...spans.map((s) => s.start)) / 60) * 60;
  const to = Math.max(from + 240, Math.ceil(Math.max(...spans.map((s) => s.end)) / 60) * 60);
  const pct = (minutes) => ((minutes - from) / (to - from)) * 100;

  const rows = new Map();
  for (const s of [...spans].sort((a, b) => a.start - b.start)) {
    const key = s.job.code || s.job.team;
    if (!rows.has(key)) {
      rows.set(key, { key, team: s.job.team, countryCode: s.job.country_code, flag: s.job.flag, first: s.start, bars: [], laneEnds: [] });
    }
    const row = rows.get(key);
    // Overlapping jobs of the same team stack into separate lanes.
    let lane = row.laneEnds.findIndex((laneEnd) => laneEnd <= s.start);
    if (lane === -1) lane = row.laneEnds.length;
    row.laneEnds[lane] = s.end;
    row.bars.push({
      job: s.job,
      lane,
      left: pct(s.start),
      width: pct(s.end) - pct(s.start),
      startLabel: clockLabel(s.start),
      endLabel: clockLabel(s.end),
      progress: progressOf(s.job),
    });
  }

  // Wide ranges get 2- or 3-hourly ticks so labels never collide.
  const stepHours = to - from > 16 * 60 ? 3 : to - from > 10 * 60 ? 2 : 1;
  const hours = [];
  for (let t = from; t <= to; t += stepHours * 60) hours.push({ label: clockLabel(t), left: pct(t) });

  const now = props.selectedDate === todayKey && nowMinutes.value >= from && nowMinutes.value <= to
    ? pct(nowMinutes.value)
    : null;

  return {
    hours,
    now,
    count: spans.length,
    untimed: dayJobs.length - spans.length,
    rows: [...rows.values()]
      .map((row) => ({ ...row, lanes: row.laneEnds.length }))
      .sort((a, b) => a.first - b.first || a.team.localeCompare(b.team)),
  };
});
</script>

<style scoped>
.jdt { display: flex; flex-direction: column; gap: 10px; margin-top: 14px; font-variant-numeric: tabular-nums; }

/* Date strip */
.jdt-strip {
  display: flex; align-items: stretch; gap: 6px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 8px;
}
.jdt-all {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px;
  min-width: 64px; padding: 6px 10px; border-radius: 8px; flex-shrink: 0; cursor: pointer;
  border: 1px solid var(--border); background: var(--surface); color: var(--ink2);
}
.jdt-all:hover { background: var(--panel); }
.jdt-all--active, .jdt-all--active:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.jdt-all-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.jdt-all-count { font-size: 17px; font-weight: 700; line-height: 1.1; }

.jdt-nav {
  border: 1px solid var(--border); background: var(--surface); border-radius: 6px;
  width: 28px; font-size: 18px; color: var(--ink2); cursor: pointer; flex-shrink: 0;
}
.jdt-nav:hover:not(:disabled) { background: var(--panel); }
.jdt-nav:disabled { opacity: 0.35; cursor: default; }

.jdt-days { display: flex; gap: 6px; overflow-x: auto; flex: 1; scroll-behavior: smooth; scrollbar-width: thin; }
.jdt-day {
  position: relative; overflow: hidden;
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  min-width: 58px; padding: 6px 8px 8px; border-radius: 8px; flex-shrink: 0;
  border: 1px solid var(--border); background: var(--surface); cursor: pointer; color: var(--ink2);
}
.jdt-day:hover { background: var(--panel); }
.jdt-day--today { border-color: var(--accent); }
.jdt-day--active, .jdt-day--active:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.jdt-day-dow, .jdt-day-mon { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
.jdt-day-num { font-size: 17px; font-weight: 700; line-height: 1.1; }
.jdt-day-count { font-size: 10.5px; font-family: var(--mono); font-weight: 600; }
.jdt-day-warn { color: var(--warn); margin-left: 3px; }
.jdt-day--active .jdt-day-warn { color: #FDE68A; }
/* Share of the day's jobs completed. */
.jdt-day-progress { position: absolute; left: 0; right: 0; bottom: 0; height: 3px; background: transparent; }
.jdt-day-progress > span { display: block; height: 100%; background: var(--ok, #16a34a); }
.jdt-day--active .jdt-day-progress > span { background: rgba(255, 255, 255, 0.85); }

/* Timeline card */
.jdt-tl { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.jdt-tl-head {
  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
  padding: 9px 14px; background: linear-gradient(180deg, var(--panel), transparent);
}
.jdt-tl-toggle {
  display: inline-flex; align-items: center; gap: 7px; padding: 0; border: 0; background: none; cursor: pointer;
  font-size: 12.5px; font-weight: 700; color: var(--ink);
}
.jdt-tl-toggle:hover { color: var(--accent); }
.jdt-chevron { color: var(--ink3); transition: transform 0.2s; transform: rotate(-90deg); }
.jdt-chevron--open { transform: rotate(0deg); }
.jdt-tl-meta { font-size: 11px; color: var(--ink3); }
.jdt-legend { display: flex; gap: 12px; margin-left: auto; flex-wrap: wrap; }
.jdt-legend-item { display: inline-flex; align-items: center; gap: 5px; font-size: 10.5px; color: var(--ink3); }
.jdt-swatch { width: 9px; height: 9px; border-radius: 2px; background: var(--c); }

.jdt-tl-body { max-height: 320px; overflow-y: auto; padding: 0 14px 12px; border-top: 1px solid var(--border); }
.jdt-grid { display: grid; grid-template-columns: 170px 1fr; row-gap: 4px; }
.jdt-sticky { position: sticky; top: 0; z-index: 3; background: var(--surface); padding-top: 8px; }
.jdt-label {
  display: flex; align-items: center; gap: 6px; min-width: 0; padding-right: 10px;
  font-size: 12px; font-weight: 600; color: var(--ink);
}
.jdt-label span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.jdt-axis { height: 26px; border-bottom: 1px solid var(--border); }
.jdt-axis .jdt-hour { top: 10px; }
.jdt-hour { position: absolute; transform: translateX(-50%); font-size: 10px; font-family: var(--mono); color: var(--ink3); }
.jdt-hour:first-child { transform: none; }
.jdt-hour:last-child { transform: translateX(-100%); }

.jdt-track { position: relative; background: var(--panel); border-radius: 6px; }
.jdt-gridline { position: absolute; top: 0; bottom: 0; width: 1px; background: var(--border); opacity: 0.7; }
.jdt-now { position: absolute; top: -2px; bottom: -2px; width: 2px; background: var(--danger, #b91c1c); z-index: 2; border-radius: 1px; }

.jdt-st--scheduled { --c: #64748B; }
.jdt-st--dispatched { --c: #6366F1; }
.jdt-st--live { --c: #0077C8; }
.jdt-st--delayed { --c: var(--warn, #b45309); }
.jdt-st--done { --c: var(--ok, #16a34a); }
.jdt-st--cancelled { --c: #94A3B8; }

.jdt-bar {
  position: absolute; height: 20px; min-width: 8px; padding: 0 6px; overflow: hidden;
  display: flex; align-items: center;
  border: 0; border-left: 3px solid var(--c); border-radius: 4px; cursor: pointer;
  background: color-mix(in srgb, var(--c) 16%, var(--surface));
  color: var(--ink); font-size: 10px; font-family: var(--mono); font-weight: 700; text-align: left;
  transition: box-shadow 0.15s;
}
.jdt-bar:hover { box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15); z-index: 1; }
.jdt-bar--selected { outline: 2px solid var(--ink); outline-offset: 1px; z-index: 2; }
.jdt-st--cancelled.jdt-bar .jdt-bar-text { text-decoration: line-through; color: var(--ink3); }
.jdt-bar-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.jdt-bar-progress { position: absolute; left: 0; bottom: 0; height: 2px; background: var(--c); }

@media (max-width: 900px) {
  .jdt-grid { grid-template-columns: 110px 1fr; }
  .jdt-legend { display: none; }
}
</style>
