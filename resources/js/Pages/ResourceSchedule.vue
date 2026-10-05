<template>
  <app-layout>
    <div v-if="!hasActiveEvent" class="empty-state-full">
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">Select an event from the dropdown above to view resource schedules.</p>
    </div>

    <div v-else class="rs-page">
      <header class="rs-head">
        <div>
          <div class="rs-kicker">Resource schedule · Week of {{ weekLabel }}</div>
          <h1 class="rs-h1">
            {{ resource ? resource.label : 'Resource schedule' }}
            <span v-if="resource?.detail" class="rs-h1-detail">{{ resource.detail }}</span>
          </h1>
          <p class="rs-sub">
            <template v-if="resource">
              {{ TYPE_LABELS[type] }} · {{ items.length }} movement{{ items.length === 1 ? '' : 's' }} this week ·
              {{ duration(weekMinutes) }} busy
              <template v-if="clashCount"> · <b class="rs-bad">{{ clashCount }} with a clash</b></template>
            </template>
            <template v-else>Pick a vehicle, driver or supervisor to see their week.</template>
          </p>
        </div>
        <div class="rs-actions no-print">
          <button type="button" class="rs-btn" :disabled="!resource" @click="printPdf">PDF</button>
          <a :class="['rs-btn', { 'rs-btn--disabled': !resource }]" :href="resource ? exportUrl : undefined" :aria-disabled="!resource">Excel</a>
        </div>
      </header>

      <!-- Resource picker + week navigation -->
      <div class="rs-toolbar no-print">
        <div class="rs-seg" role="tablist" aria-label="Resource type">
          <button v-for="(label, key) in TYPE_LABELS" :key="key" type="button" role="tab" :aria-selected="pickType === key"
            :class="['rs-seg-btn', { 'rs-seg-btn--active': pickType === key }]" @click="pickType = key">{{ label }}s</button>
        </div>
        <div class="rs-picker">
          <input v-model="search" type="search" class="rs-search" :placeholder="`Search ${TYPE_LABELS[pickType].toLowerCase()}s…`" :aria-label="`Search ${TYPE_LABELS[pickType].toLowerCase()}s`" />
          <select class="rs-select" :value="pickType === type ? resourceId : null" :aria-label="`Choose ${TYPE_LABELS[pickType].toLowerCase()}`" @change="choose(Number($event.target.value))">
            <option :value="null" disabled>Choose {{ TYPE_LABELS[pickType].toLowerCase() }}…</option>
            <option v-for="r in pickList" :key="r.id" :value="r.id">{{ r.label }}{{ r.detail ? ` · ${r.detail}` : '' }}</option>
          </select>
        </div>
        <div class="rs-week">
          <button type="button" class="rs-nav" aria-label="Previous week" @click="shiftWeek(-7)">‹</button>
          <span class="rs-week-label">{{ weekLabel }} – {{ weekEndLabel }}</span>
          <button type="button" class="rs-nav" aria-label="Next week" @click="shiftWeek(7)">›</button>
          <button type="button" class="rs-btn rs-btn--ghost" :disabled="isThisWeek" @click="goWeek(todayKey())">This week</button>
        </div>
      </div>

      <div v-if="!resource" class="rs-empty">No resource selected.</div>

      <template v-else>
        <!-- Gantt: one row per day, 00:00–24:00 -->
        <div class="rs-card">
          <div class="rs-gantt">
            <div class="rs-axis">
              <div class="rs-day-col"></div>
              <div class="rs-track rs-track--axis">
                <span v-for="h in HOURS" :key="h" class="rs-hour" :style="{ left: `${(h / 24) * 100}%` }">{{ String(h).padStart(2, '0') }}</span>
              </div>
            </div>
            <div v-for="day in days" :key="day.key" :class="['rs-row', { 'rs-row--past': day.past, 'rs-row--today': day.today }]">
              <div class="rs-day-col">
                <div class="rs-day-name">{{ day.label }}<span v-if="day.today" class="rs-today">Today</span></div>
                <div class="rs-day-sum">{{ day.bars.length ? `${day.bars.length} · ${duration(day.minutes)}` : 'Free' }}</div>
              </div>
              <div class="rs-track">
                <span v-for="h in HOURS" :key="h" class="rs-grid" :style="{ left: `${(h / 24) * 100}%` }"></span>
                <span v-if="day.today && nowMinutes !== null" class="rs-now" :style="{ left: `${(nowMinutes / 1440) * 100}%` }" title="Now"></span>
                <button
                  v-for="bar in day.bars" :key="bar.item.id" type="button"
                  :class="['rs-bar', `rs-bar--${barTone(bar.item)}`, { 'rs-bar--clash': bar.item.clashes.length, 'rs-bar--cut-start': bar.cutStart, 'rs-bar--cut-end': bar.cutEnd }]"
                  :style="{ left: `${bar.left}%`, width: `${bar.width}%`, top: `${bar.lane * 26 + 6}px` }"
                  :title="barTitle(bar.item)"
                  @click="selectedId = selectedId === bar.item.id ? null : bar.item.id"
                >
                  <span class="rs-bar-text">{{ bar.item.code }}<template v-if="bar.item.team_code"> · {{ bar.item.team_code }}</template></span>
                </button>
                <div class="rs-track-pad" :style="{ height: `${Math.max(1, day.lanes) * 26 + 10}px` }"></div>
              </div>
            </div>
          </div>
          <div class="rs-legend">
            <span><i class="rs-key rs-bar--planned"></i>Planned (no job yet)</span>
            <span><i class="rs-key rs-bar--scheduled"></i>Job scheduled</span>
            <span><i class="rs-key rs-bar--live"></i>In progress</span>
            <span><i class="rs-key rs-bar--done"></i>Completed</span>
            <span><i class="rs-key rs-bar--planned rs-bar--clash"></i>Clash</span>
            <span><i class="rs-key rs-key--now"></i>Now</span>
          </div>
        </div>

        <!-- Same movements as a list; this is what the PDF shows in detail. -->
        <div class="rs-card">
          <table class="rs-table">
            <thead>
              <tr>
                <th>Date</th><th>Time</th><th>Movement</th><th>Job</th><th>Role</th><th>Team</th><th>Route</th><th>Clashes</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!items.length"><td colspan="8" class="rs-empty-row">Nothing booked this week.</td></tr>
              <tr v-for="item in items" :key="item.id" :class="{ 'rs-tr--active': selectedId === item.id, 'rs-tr--past': isPast(item) }">
                <td>{{ dayOf(item.start) }}</td>
                <td class="mono">{{ timeOf(item.start) }}–{{ timeOf(item.end) }}<span v-if="crossesMidnight(item)" class="rs-plus">+1</span></td>
                <td class="mono">{{ item.code }}</td>
                <td>
                  <template v-if="item.job_code">
                    <span class="mono">{{ item.job_code }}</span>
                    <status-pill :tone="pillTone(item)">{{ item.job_status_label }}</status-pill>
                  </template>
                  <status-pill v-else tone="neutral">Planned</status-pill>
                </td>
                <td>{{ item.role }}</td>
                <td>{{ item.team_code }} {{ item.team }}</td>
                <td class="rs-route">{{ item.from }} → {{ item.to }}</td>
                <td class="rs-clash-cell">
                  <div v-for="(c, i) in item.clashes" :key="i">{{ c }}</div>
                  <span v-if="!item.clashes.length" class="rs-muted">—</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import { minutesFrom, duration, mergedMinutes, todayKey } from '../Composables/useCrewRoster';

const props = defineProps({
  type: { type: String, default: 'vehicle' },
  resourceId: { type: Number, default: null },
  resource: { type: Object, default: null },
  weekStart: { type: String, required: true },
  items: { type: Array, default: () => [] },
  resources: { type: Object, default: () => ({ vehicle: [], driver: [], supervisor: [] }) },
});

const page = usePage();
const hasActiveEvent = computed(() => !!page.props.activeEventId);

const TYPE_LABELS = { vehicle: 'Vehicle', driver: 'Driver', supervisor: 'Supervisor' };
const HOURS = [0, 2, 4, 6, 8, 10, 12, 14, 16, 18, 20, 22];

const pickType = ref(props.type);
watch(() => props.type, (t) => { pickType.value = t; });
const search = ref('');
const selectedId = ref(null);

const pickList = computed(() => {
  const q = search.value.trim().toLowerCase();
  const list = props.resources[pickType.value] ?? [];
  return q ? list.filter((r) => `${r.label} ${r.detail ?? ''}`.toLowerCase().includes(q)) : list;
});

function visit(params) {
  router.get('/resource-schedule', params, { preserveState: true, preserveScroll: true });
}

function choose(id) {
  selectedId.value = null;
  visit({ type: pickType.value, id, week: props.weekStart });
}

function goWeek(date) {
  selectedId.value = null;
  visit({ type: props.type, id: props.resourceId ?? undefined, week: date });
}

function addDays(key, n) {
  const d = new Date(`${key}T00:00:00`);
  d.setDate(d.getDate() + n);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function shiftWeek(n) {
  goWeek(addDays(props.weekStart, n));
}

const fmt = (key, opts) => new Date(`${key}T00:00:00`).toLocaleDateString('en-GB', opts);
const weekLabel = computed(() => fmt(props.weekStart, { day: 'numeric', month: 'short', year: 'numeric' }));
const weekEndLabel = computed(() => fmt(addDays(props.weekStart, 6), { day: 'numeric', month: 'short', year: 'numeric' }));
const isThisWeek = computed(() => {
  const today = todayKey();
  return today >= props.weekStart && today <= addDays(props.weekStart, 6);
});

// Ticks once a minute so the now line and past shading stay current.
const now = ref(new Date());
const clock = setInterval(() => { now.value = new Date(); }, 60000);
onUnmounted(() => clearInterval(clock));
const nowStamp = computed(() => {
  const d = now.value;
  return `${todayKey()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
});
const nowMinutes = computed(() => minutesFrom(todayKey(), nowStamp.value));

const days = computed(() => Array.from({ length: 7 }, (_, i) => {
  const key = addDays(props.weekStart, i);
  const bars = [];
  const spans = [];
  for (const item of props.items) {
    const s = minutesFrom(key, item.start);
    if (s === null) continue;
    const e = Math.max(minutesFrom(key, item.end) ?? s + 30, s + 10);
    if (e <= 0 || s >= 1440) continue;
    const from = Math.max(s, 0);
    const to = Math.min(e, 1440);
    spans.push([from, to]);
    bars.push({ item, from, to, cutStart: s < 0, cutEnd: e > 1440, left: (from / 1440) * 100, width: Math.max(((to - from) / 1440) * 100, 0.6) });
  }

  // Overlapping bars (a clash) stack into lanes instead of hiding each other.
  const laneEnds = [];
  for (const bar of bars.sort((a, b) => a.from - b.from)) {
    let lane = laneEnds.findIndex((end) => end <= bar.from);
    if (lane === -1) lane = laneEnds.length;
    laneEnds[lane] = bar.to;
    bar.lane = lane;
  }

  return {
    key,
    label: fmt(key, { weekday: 'short', day: 'numeric', month: 'short' }),
    today: key === todayKey(),
    past: key < todayKey(),
    bars,
    lanes: laneEnds.length,
    minutes: mergedMinutes(spans),
  };
}));

const weekMinutes = computed(() => days.value.reduce((sum, d) => sum + d.minutes, 0));
const clashCount = computed(() => props.items.filter((i) => i.clashes.length).length);

function barTone(item) {
  if (!item.job_code) return 'planned';
  if (item.job_status === 'completed') return 'done';
  if (item.job_status === 'in-progress') return 'live';
  if (item.job_status === 'delayed') return 'warn';
  if (item.job_status === 'cancelled') return 'cancelled';
  return 'scheduled';
}

const PILL_TONES = { done: 'ok', live: 'live', warn: 'warn', cancelled: 'danger', scheduled: 'primary' };
const pillTone = (item) => PILL_TONES[barTone(item)] ?? 'neutral';

const timeOf = (stamp) => stamp?.slice(11, 16) ?? '--:--';
const dayOf = (stamp) => (stamp ? fmt(stamp.slice(0, 10), { weekday: 'short', day: 'numeric', month: 'short' }) : '—');
const crossesMidnight = (item) => item.start && item.end && item.start.slice(0, 10) !== item.end.slice(0, 10);
const isPast = (item) => item.end && item.end < nowStamp.value;

function barTitle(item) {
  return [
    `${item.code}${item.job_code ? ` · ${item.job_code} (${item.job_status_label})` : ' · Planned'}`,
    `${timeOf(item.start)}–${timeOf(item.end)}${crossesMidnight(item) ? ' (+1)' : ''} · ${item.role}`,
    [item.team_code, item.team].filter(Boolean).join(' '),
    `${item.from ?? ''} → ${item.to ?? ''}`,
    ...item.clashes.map((c) => `⚠ ${c}`),
  ].filter(Boolean).join('\n');
}

const exportUrl = computed(() => `/resource-schedule/export?${new URLSearchParams({ type: props.type, id: props.resourceId, week: props.weekStart })}`);

// "Save as PDF" from the browser's print dialog, using the print styles below.
function printPdf() {
  window.print();
}
</script>

<style scoped>
.empty-state-full { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center; }
.empty-state-title { font-size: 24px; font-weight: 700; color: var(--ink); margin-bottom: 8px; }
.empty-state-text { font-size: 14px; color: var(--ink3); max-width: 400px; }

.rs-page { display: flex; flex-direction: column; gap: 14px; }
.rs-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 12px; }
.rs-kicker { font-family: var(--font-mono, monospace); font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.rs-h1 { margin: 4px 0; font-size: 26px; font-weight: 600; color: var(--ink); display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.rs-h1-detail { font-size: 14px; font-weight: 500; color: var(--ink3); }
.rs-sub { margin: 0; font-size: 13px; color: var(--ink3); }
.rs-bad { color: var(--danger, #b91c1c); }
.rs-actions { display: flex; gap: 8px; }

.rs-btn {
  display: inline-flex; align-items: center; padding: 7px 14px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
}
.rs-btn:hover { background: var(--panel); }
.rs-btn:disabled, .rs-btn--disabled { opacity: 0.45; pointer-events: none; }
.rs-btn--ghost { font-weight: 500; }

.rs-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.rs-seg { display: inline-flex; gap: 4px; background: var(--panel); border: 1px solid var(--border); padding: 4px; border-radius: 8px; }
.rs-seg-btn { border: 0; cursor: pointer; font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 6px; background: transparent; color: var(--ink2); }
.rs-seg-btn--active { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }
.rs-picker { display: flex; gap: 6px; flex: 1; min-width: 280px; max-width: 520px; }
.rs-search, .rs-select {
  padding: 7px 10px; border: 1px solid var(--border); border-radius: 7px;
  background: var(--surface); color: var(--ink); font-size: 13px;
}
.rs-search { width: 160px; }
.rs-select { flex: 1; min-width: 0; }
.rs-week { display: flex; align-items: center; gap: 6px; margin-left: auto; }
.rs-week-label { font-size: 13px; font-weight: 600; color: var(--ink); min-width: 190px; text-align: center; }
.rs-nav { width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--ink2); font-size: 18px; cursor: pointer; }
.rs-nav:hover { background: var(--panel); }

.rs-empty { padding: 48px; text-align: center; color: var(--ink3); font-size: 13px; background: var(--surface); border: 1px dashed var(--border); border-radius: 10px; }

.rs-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow-x: auto; }
.rs-gantt { min-width: 900px; }
.rs-axis, .rs-row { display: grid; grid-template-columns: 150px 1fr; }
.rs-axis { border-bottom: 1px solid var(--border); background: var(--panel); }
.rs-row { border-bottom: 1px solid var(--border); }
.rs-row:last-child { border-bottom: none; }
.rs-row--past { background: color-mix(in srgb, var(--panel) 60%, transparent); }
.rs-row--past .rs-bar { opacity: 0.7; }
.rs-row--today .rs-day-col { box-shadow: inset 3px 0 0 var(--accent); }
.rs-day-col { padding: 8px 12px; border-right: 1px solid var(--border); }
.rs-day-name { font-size: 13px; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 6px; }
.rs-today { font-size: 10px; font-weight: 700; padding: 1px 6px; border-radius: 8px; background: var(--accent); color: #fff; }
.rs-day-sum { font-size: 11px; color: var(--ink3); margin-top: 2px; }
.rs-track { position: relative; min-height: 36px; }
.rs-track--axis { height: 26px; }
.rs-hour { position: absolute; top: 6px; transform: translateX(-50%); font-family: var(--font-mono, monospace); font-size: 10px; color: var(--ink3); }
.rs-hour:first-child { transform: none; padding-left: 3px; }
.rs-grid { position: absolute; top: 0; bottom: 0; width: 1px; background: var(--border); opacity: 0.6; }
.rs-now { position: absolute; top: 0; bottom: 0; width: 2px; background: var(--danger, #dc2626); z-index: 3; }
.rs-track-pad { pointer-events: none; }

.rs-bar {
  position: absolute; height: 22px; padding: 0 6px; border-radius: 5px;
  display: flex; align-items: center; overflow: hidden; cursor: pointer; z-index: 2;
  font: inherit; font-size: 11px; font-weight: 600; text-align: left; border: 1px solid transparent;
}
.rs-bar-text { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rs-bar--planned { background: var(--surface); border: 1.5px dashed var(--accent); color: var(--accent); }
.rs-bar--scheduled { background: var(--accent); color: #fff; }
.rs-bar--live { background: var(--live, #0ea5e9); color: #fff; }
.rs-bar--warn { background: var(--warn, #f59e0b); color: #fff; }
.rs-bar--done { background: var(--ink4, #9ca3af); color: #fff; }
.rs-bar--cancelled { background: var(--panel); color: var(--ink3); text-decoration: line-through; }
.rs-bar--clash { box-shadow: 0 0 0 2px var(--danger, #dc2626); }
.rs-bar--cut-start { border-top-left-radius: 0; border-bottom-left-radius: 0; }
.rs-bar--cut-end { border-top-right-radius: 0; border-bottom-right-radius: 0; }

.rs-legend { display: flex; flex-wrap: wrap; gap: 14px; padding: 10px 14px; border-top: 1px solid var(--border); font-size: 11.5px; color: var(--ink3); }
.rs-legend span { display: inline-flex; align-items: center; gap: 6px; }
.rs-key { display: inline-block; width: 18px; height: 10px; border-radius: 3px; position: static; }
.rs-key--now { width: 2px; height: 12px; background: var(--danger, #dc2626); }

.rs-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.rs-table th {
  padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--ink3); background: var(--panel); border-bottom: 1px solid var(--border); white-space: nowrap;
}
.rs-table td { padding: 8px 12px; border-bottom: 1px solid var(--border); vertical-align: top; color: var(--ink); }
.rs-table tr:last-child td { border-bottom: none; }
.rs-tr--active td { background: var(--accent-soft); }
.rs-tr--past td { color: var(--ink3); }
.rs-empty-row { text-align: center; color: var(--ink3); padding: 24px; }
.rs-route { color: var(--ink3); }
.rs-clash-cell { color: var(--danger, #b91c1c); font-size: 11.5px; max-width: 320px; }
.rs-muted { color: var(--ink4, var(--ink3)); }
.rs-plus { margin-left: 3px; font-size: 10px; color: var(--ink3); }
.mono { font-family: var(--font-mono, monospace); }
</style>

<style>
/* Print = the PDF export: drop the app chrome and controls, keep colours. */
@media print {
  body:has(.rs-page) #app-sidebar,
  body:has(.rs-page) .topbar,
  body:has(.rs-page) .mobile-bottom-nav,
  body:has(.rs-page) .no-print { display: none !important; }
  body:has(.rs-page) .main-wrap { margin-left: 0 !important; }
  body:has(.rs-page) .rs-card { overflow: visible !important; break-inside: avoid; }
  body:has(.rs-page) * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  @page { size: A4 landscape; margin: 10mm; }
}
</style>
