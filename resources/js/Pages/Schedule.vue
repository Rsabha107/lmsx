<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Movement Schedule</h1>
        <p class="page-sub">{{ schedule.length }} movements today · {{ $page.props.today }}</p>
      </div>
      <div class="page-header-actions">
        <RefreshButton :only="['schedule']" />
        <div class="filter-tabs">
          <button v-for="f in filters" :key="f.value"
            :class="['filter-tab', activeFilter === f.value ? 'filter-tab--active' : '']"
            @click="activeFilter = f.value">
            {{ f.label }}
          </button>
        </div>
      </div>
    </div>

    <!-- Gantt-style timeline header -->
    <div class="schedule-card">
      <div class="gantt-header">
        <div class="gantt-info-col">Movement</div>
        <div class="gantt-times">
          <span v-for="h in timeSlots" :key="h" class="gantt-hour">{{ h }}</span>
        </div>
      </div>

      <div class="gantt-rows">
        <div v-for="mv in filtered" :key="mv.id" class="gantt-row">
          <div class="gantt-info">
            <span class="team-badge">{{ mv.code }}</span>
            <div class="gantt-meta">
              <div class="gantt-team">{{ mv.team }}</div>
              <div class="gantt-route">{{ mv.from }} → {{ mv.to }}</div>
            </div>
            <status-pill :tone="statusTone(mv.status)" style="margin-left:auto;flex-shrink:0">
              {{ mv.delay ? `+${mv.delay}m` : statusLabel(mv.status) }}
            </status-pill>
          </div>
          <div class="gantt-bar-col">
            <div class="gantt-track">
              <div
                :class="['gantt-bar', `gantt-bar--${mv.status}`]"
                :style="barStyle(mv)"
                :title="`${mv.dep} – ${mv.arr}`"
              >
                <span class="gantt-bar-label">{{ mv.dep }} – {{ mv.arr }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Table view for mobile -->
    <div class="schedule-table-card">
      <table class="sched-table">
        <thead>
          <tr>
            <th>Job</th><th>Team</th><th>Route</th>
            <th>Dep</th><th>Arr</th><th>Pax</th><th>Vehicle</th><th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="mv in filtered" :key="mv.id">
            <td class="mono">{{ mv.id }}</td>
            <td>
              <div class="flex-cell">
                <span class="team-badge-sm">{{ mv.code }}</span>
                {{ mv.team }}
              </div>
            </td>
            <td class="route-cell">{{ mv.from }} → {{ mv.to }}</td>
            <td class="mono">{{ mv.dep }}</td>
            <td class="mono">{{ mv.arr }}</td>
            <td>{{ mv.pax }}</td>
            <td>{{ mv.vehicle }}</td>
            <td>
              <status-pill :tone="statusTone(mv.status)">
                {{ mv.delay ? `Delayed +${mv.delay}m` : statusLabel(mv.status) }}
              </status-pill>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  schedule: { type: Array, default: () => [] },
});

const filters = [
  { value: 'all',         label: 'All' },
  { value: 'in-progress', label: 'In Progress' },
  { value: 'scheduled',   label: 'Scheduled' },
  { value: 'delayed',     label: 'Delayed' },
];
const activeFilter = ref('all');

const filtered = computed(() =>
  activeFilter.value === 'all'
    ? props.schedule
    : props.schedule.filter(m => m.status === activeFilter.value)
);

const timeSlots = ['13:00','14:00','15:00','16:00','17:00','18:00','22:00','23:00'];

// Timeline span 13:00 – 23:30
const START_MIN = 13 * 60;
const SPAN_MIN  = 10.5 * 60;

function toMin(t) {
  const [h, m] = t.split(':').map(Number);
  return h * 60 + m;
}

function barStyle(mv) {
  const left = ((toMin(mv.dep) - START_MIN) / SPAN_MIN) * 100;
  const width = ((toMin(mv.arr) - toMin(mv.dep)) / SPAN_MIN) * 100;
  return { left: `${Math.max(0, left).toFixed(2)}%`, width: `${Math.max(1, width).toFixed(2)}%` };
}

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'scheduled':   { tone: 'primary', label: 'Scheduled' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'done':        { tone: 'ok',      label: 'Done' },
};
function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
function statusLabel(s) { return statusMap[s]?.label ?? s; }
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.page-header-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }

.filter-tabs { display: flex; gap: 4px; }
.filter-tab {
  padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; cursor: pointer; color: var(--ink3);
  font-weight: 500;
}
.filter-tab:hover { background: var(--panel); color: var(--ink); }
.filter-tab--active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Gantt */
.schedule-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden; margin-bottom: 16px;
  display: none;
}
@media (min-width: 768px) { .schedule-card { display: block; } }

.gantt-header {
  display: flex; border-bottom: 1px solid var(--border);
  background: var(--panel);
}
.gantt-info-col {
  width: 320px; flex-shrink: 0; padding: 8px 16px;
  font-size: 11px; font-weight: 600; text-transform: uppercase;
  letter-spacing: 0.05em; color: var(--ink3);
}
.gantt-times {
  flex: 1; display: flex; justify-content: space-between;
  padding: 8px 8px; overflow: hidden;
}
.gantt-hour { font-size: 11px; color: var(--ink4); }

.gantt-rows {}
.gantt-row {
  display: flex; border-bottom: 1px solid var(--border);
  min-height: 52px;
}
.gantt-row:last-child { border-bottom: none; }

.gantt-info {
  width: 320px; flex-shrink: 0;
  display: flex; align-items: center; gap: 10px;
  padding: 10px 16px;
}
.team-badge {
  width: 36px; height: 36px; border-radius: 8px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.gantt-meta { flex: 1; min-width: 0; }
.gantt-team { font-size: 13px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gantt-route { font-size: 11.5px; color: var(--ink3); }

.gantt-bar-col { flex: 1; display: flex; align-items: center; padding: 0 8px; }
.gantt-track { flex: 1; height: 28px; position: relative; }
.gantt-bar {
  position: absolute; top: 0; height: 100%;
  border-radius: 5px; display: flex; align-items: center;
  padding: 0 8px; overflow: hidden; cursor: default;
  transition: opacity 0.15s;
}
.gantt-bar:hover { opacity: 0.85; }
.gantt-bar--in-progress { background: var(--live-soft); border: 1px solid var(--live); }
.gantt-bar--scheduled   { background: var(--accent-soft); border: 1px solid var(--accent); }
.gantt-bar--delayed     { background: var(--warn-soft); border: 1px solid var(--warn); }
.gantt-bar--done        { background: var(--ok-soft); border: 1px solid var(--ok); }
.gantt-bar-label { font-size: 11px; font-weight: 600; color: var(--ink2); white-space: nowrap; }

/* Table view */
.schedule-table-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}
@media (min-width: 768px) { .schedule-table-card { display: none; } }

.sched-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.sched-table th {
  padding: 8px 12px; text-align: left;
  font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--ink3); border-bottom: 1px solid var(--border); background: var(--panel);
  white-space: nowrap;
}
.sched-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.sched-table tr:last-child td { border-bottom: none; }

.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); }
.flex-cell { display: flex; align-items: center; gap: 6px; }
.team-badge-sm {
  width: 28px; height: 28px; border-radius: 6px; flex-shrink: 0;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 9px; font-weight: 700;
  display: inline-flex; align-items: center; justify-content: center;
}
.route-cell { color: var(--ink3); font-size: 12px; white-space: nowrap; }

/* Mobile card layout */
@media (max-width: 767px) {
  .schedule-table-card { display: block; }
  .sched-table { display: none; }
  .schedule-table-card::before { content: none; }
}

.mobile-card-list { display: flex; flex-direction: column; }
</style>
