<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Dashboard</h1>
        <p class="page-sub">Operations overview · {{ $page.props.today }}</p>
      </div>
      <div class="page-header-actions">
        <RefreshButton />
        <button class="btn btn--primary btn--sm">
          <svg-icon name="download" :size="15" /> Export
        </button>
      </div>
    </div>

    <!-- KPI grid -->
    <div class="kpi-grid">
      <k-p-i-card v-for="kpi in kpis" :key="kpi.id" :kpi="kpi" />
    </div>

    <div class="dash-bottom">
      <!-- Today's schedule -->
      <section class="dash-card">
        <div class="card-header">
          <span class="card-title">Today's Movements</span>
          <inertia-link href="/schedule" class="card-link">View all</inertia-link>
        </div>
        <div class="movements-list">
          <movement-row v-for="mv in schedule" :key="mv.id" :mv="mv" />
        </div>
      </section>

      <!-- Notifications -->
      <section class="dash-card">
        <div class="card-header">
          <span class="card-title">Recent Alerts</span>
          <inertia-link href="/notifications" class="card-link">View all</inertia-link>
        </div>
        <div class="notif-list">
          <div v-for="n in notifications" :key="n.id" class="notif-row">
            <div :class="['notif-dot', `notif-dot--${n.tone}`]" />
            <div class="notif-body">
              <div class="notif-title">{{ n.title }}</div>
              <div class="notif-meta">{{ n.body }}</div>
            </div>
            <span class="notif-time">{{ n.t }}</span>
          </div>
        </div>
      </section>
    </div>

    <!-- Audit -->
    <section class="dash-card" style="margin-top:20px">
      <div class="card-header">
        <span class="card-title">Audit Log</span>
        <inertia-link href="/audit" class="card-link">Full log</inertia-link>
      </div>
      <div class="audit-table-wrap">
        <table class="audit-table">
          <thead>
            <tr>
              <th>Time</th><th>Who</th><th>Action</th><th>Target</th><th>Note</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, i) in audit" :key="i">
              <td class="mono">{{ row.t }}</td>
              <td>
                <div class="audit-who">{{ row.who }}</div>
                <div class="audit-role">{{ row.role }}</div>
              </td>
              <td><span class="audit-action">{{ row.action }}</span></td>
              <td class="audit-target">{{ row.target }}</td>
              <td class="audit-meta">{{ row.meta }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </app-layout>
</template>

<script setup>
import AppLayout from '../Components/AppLayout.vue';
import KPICard from '../Components/KPICard.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import StatusPill from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import { Link as InertiaLink } from '@inertiajs/vue3';

const props = defineProps({
  kpis:          { type: Array, default: () => [] },
  schedule:      { type: Array, default: () => [] },
  notifications: { type: Array, default: () => [] },
  audit:         { type: Array, default: () => [] },
});

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'scheduled':   { tone: 'primary', label: 'Scheduled' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'done':        { tone: 'ok',      label: 'Done' },
};

function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
function statusLabel(s) { return statusMap[s]?.label ?? s; }

// MovementRow as inline component via template is fine here
const MovementRow = {
  props: ['mv'],
  components: { StatusPill, SvgIcon },
  template: `
    <div class="mv-row">
      <div class="mv-left">
        <span class="team-badge">{{ mv.code }}</span>
        <div>
          <div class="mv-team">{{ mv.team }}</div>
          <div class="mv-route">{{ mv.from }} → {{ mv.to }}</div>
        </div>
      </div>
      <div class="mv-right">
        <span class="mv-time">{{ mv.dep }} – {{ mv.arr }}</span>
        <status-pill :tone="statusTone(mv.status)">{{ statusLabel(mv.status) }}</status-pill>
      </div>
    </div>
  `,
  methods: { statusTone, statusLabel },
};
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.page-header-actions { display: flex; gap: 8px; flex-shrink: 0; }

.btn {
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer;
  border: 1px solid transparent; transition: all 0.13s;
}
.btn--sm { padding: 6px 12px; }
.btn--ghost { background: none; border-color: var(--border); color: var(--ink3); }
.btn--ghost:hover { background: var(--panel); color: var(--ink); }
.btn--primary { background: var(--accent); color: #fff; }
.btn--primary:hover { opacity: 0.9; }

.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 12px; margin-bottom: 20px;
}

.dash-bottom {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;
}
@media (max-width: 767px) { .dash-bottom { grid-template-columns: 1fr; } }

.dash-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 12px 16px; border-bottom: 1px solid var(--border);
}
.card-title { font-size: 13.5px; font-weight: 600; color: var(--ink); }
.card-link { font-size: 12px; color: var(--accent); text-decoration: none; }
.card-link:hover { text-decoration: underline; }

/* movements */
.movements-list { display: flex; flex-direction: column; }
.mv-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 16px; border-bottom: 1px solid var(--border); gap: 12px;
}
.mv-row:last-child { border-bottom: none; }
.mv-left { display: flex; align-items: center; gap: 10px; min-width: 0; }
.team-badge {
  width: 36px; height: 36px; border-radius: 8px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.mv-team { font-size: 13px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mv-route { font-size: 11.5px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mv-right { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.mv-time { font-size: 11.5px; color: var(--ink3); white-space: nowrap; }

/* notifications */
.notif-list { display: flex; flex-direction: column; }
.notif-row {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 10px 16px; border-bottom: 1px solid var(--border);
}
.notif-row:last-child { border-bottom: none; }
.notif-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}
.notif-dot--warn    { background: var(--warn); }
.notif-dot--danger  { background: var(--danger); }
.notif-dot--ok      { background: var(--ok); }
.notif-dot--primary { background: var(--accent); }
.notif-dot--neutral { background: var(--ink4); }
.notif-body { flex: 1; min-width: 0; }
.notif-title { font-size: 13px; font-weight: 600; color: var(--ink); }
.notif-meta  { font-size: 12px; color: var(--ink3); margin-top: 2px; }
.notif-time  { font-size: 11px; color: var(--ink4); flex-shrink: 0; margin-top: 2px; }

/* audit */
.audit-table-wrap { overflow-x: auto; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.audit-table th {
  text-align: left; padding: 8px 12px;
  color: var(--ink3); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;
  border-bottom: 1px solid var(--border); white-space: nowrap;
}
.audit-table td { padding: 9px 12px; border-bottom: 1px solid var(--border); vertical-align: top; }
.audit-table tr:last-child td { border-bottom: none; }
.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); white-space: nowrap; }
.audit-who { font-weight: 600; color: var(--ink); }
.audit-role { font-size: 11px; color: var(--ink4); }
.audit-action { font-weight: 600; color: var(--accent-fg); }
.audit-target { color: var(--ink2); }
.audit-meta { color: var(--ink3); font-size: 12px; }
</style>
