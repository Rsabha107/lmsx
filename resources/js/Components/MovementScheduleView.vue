<template>
  <div>
    <!-- Gantt-style timeline header -->
    <div class="schedule-card">
      <div class="gantt-header">
        <div class="gantt-info-col">Movement</div>
        <div class="gantt-times">
          <span v-for="h in timeSlots" :key="h" class="gantt-hour">{{ h }}</span>
        </div>
      </div>

      <div class="gantt-rows">
        <div v-for="mv in schedule" :key="mv.id" class="gantt-row">
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
          <tr v-for="mv in schedule" :key="mv.id">
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
  </div>
</template>

<script setup>
import { computed } from 'vue';
import StatusPill from './StatusPill.vue';
import { useScheduleStatus } from '../Composables/useScheduleFilter';

const props = defineProps({
  schedule: { type: Array, default: () => [] },
});

const { statusTone, statusLabel } = useScheduleStatus();

function toMin(t) {
  if (!t) return null;
  const [h, m] = String(t).split(':').map(Number);
  return Number.isNaN(h) ? null : h * 60 + (m || 0);
}

// Arrivals past midnight run on into the next day.
function span(mv) {
  const dep = toMin(mv.dep);
  if (dep === null) return null;
  let arr = toMin(mv.arr) ?? dep + 30;
  if (arr < dep) arr += 24 * 60;
  return [dep, arr];
}

const range = computed(() => {
  const spans = props.schedule.map(span).filter(Boolean);
  if (!spans.length) return { start: 0, end: 24 * 60 };
  const start = Math.floor(Math.min(...spans.map(s => s[0])) / 60) * 60;
  const end = Math.max(start + 60, Math.ceil(Math.max(...spans.map(s => s[1])) / 60) * 60);
  return { start, end };
});

const timeSlots = computed(() => {
  const slots = [];
  for (let m = range.value.start; m <= range.value.end; m += 60) {
    slots.push(`${String((m / 60) % 24).padStart(2, '0')}:00`);
  }
  return slots;
});

function barStyle(mv) {
  const s = span(mv);
  if (!s) return { display: 'none' };
  const total = range.value.end - range.value.start;
  const left = ((s[0] - range.value.start) / total) * 100;
  const width = ((s[1] - s[0]) / total) * 100;
  return { left: `${left.toFixed(2)}%`, width: `${Math.max(1, width).toFixed(2)}%` };
}
</script>

<style scoped>
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
  min-width: 36px; height: 36px; padding: 0 6px; border-radius: 8px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700; white-space: nowrap;
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
</style>
