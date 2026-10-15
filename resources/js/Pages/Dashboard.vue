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
          <inertia-link href="/day-board?view=schedule" class="card-link">View all</inertia-link>
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
          <div v-if="!notifications.length" class="notif-meta">No alerts right now.</div>
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

    <!-- System clocks (admins) -->
    <section v-if="clocks" class="dash-card" style="margin-top:20px">
      <div class="card-header">
        <span class="card-title">System Clocks</span>
        <span class="card-hint">Server clocks read at page load, then ticking</span>
      </div>
      <div class="audit-table-wrap">
        <table class="audit-table">
          <thead>
            <tr>
              <th>Clock</th><th>Time</th><th>Time zone</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in clockRows" :key="row.key">
              <td class="audit-who">{{ row.label }}</td>
              <td class="mono clock-time">{{ row.time }}</td>
              <td class="audit-target">{{ row.zone }}</td>
              <td>
                <status-pill :tone="row.pill.tone">{{ row.pill.label }}</status-pill>
                <div class="audit-meta clock-note">{{ row.note }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </app-layout>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useStatusLabels } from '../Composables/useStatusLabels';
import { VENUE_TIMEZONE } from '../Composables/useVenueTime';
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
  clocks:        { type: Object, default: null },
});

// Server readings are a snapshot; they tick on by the browser's elapsed time.
const loadedAt = ref(Date.now());
const nowMs = ref(loadedAt.value);
let clockTimer = null;
onMounted(() => {
  if (props.clocks) clockTimer = setInterval(() => { nowMs.value = Date.now(); }, 1000);
});
onBeforeUnmount(() => clearInterval(clockTimer));
watch(() => props.clocks, () => {
  loadedAt.value = Date.now();
  nowMs.value = loadedAt.value;
});

// 'YYYY-MM-DD HH:mm:ss' read as UTC, so the browser's own zone never applies.
function wallMs(value) {
  const m = value?.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
  return m ? Date.UTC(+m[1], m[2] - 1, +m[3], +m[4], +m[5], +m[6]) : null;
}
const wallText = (ms) => new Date(ms).toISOString().slice(0, 19).replace('T', ' ');
const pad2 = (n) => String(n).padStart(2, '0');
const offsetText = (min) => `UTC${min < 0 ? '−' : '+'}${pad2(Math.floor(Math.abs(min) / 60))}:${pad2(Math.abs(min) % 60)}`;
function spanText(sec) {
  const s = Math.abs(sec);
  if (s < 120) return `${s} s`;
  return s < 7200 ? `${Math.round(s / 60)} min` : `${+(s / 3600).toFixed(1)} h`;
}
const aheadBehind = (sec) => `${spanText(sec)} ${sec > 0 ? 'ahead' : 'behind'}`;

const clockRows = computed(() => {
  const c = props.clocks;
  if (!c) return [];

  const elapsed = nowMs.value - loadedAt.value;
  const tick = (value) => {
    const ms = wallMs(value);
    return ms === null ? '—' : wallText(ms + elapsed);
  };
  const zoneText = (tz, offset) => (/^[+-]\d/.test(tz)
    ? `UTC${tz}`
    : `${tz === 'SYSTEM' ? `SYSTEM (${c.db.system_tz})` : tz}${offset === null ? '' : ` · ${offsetText(offset)}`}`);

  const utcMs = wallMs(c.db.utc_now);
  const sessionOffset = Math.round((wallMs(c.db.session_now) - utcMs) / 60000);
  const serverOffset = c.db.server_now ? Math.round((wallMs(c.db.server_now) - utcMs) / 60000) : null;
  const clientShift = serverOffset === null ? null : (serverOffset - sessionOffset) * 60;
  const dbDrift = Math.round((utcMs - wallMs(c.app.utc)) / 1000);
  const browserDrift = Math.round((loadedAt.value - wallMs(c.app.utc)) / 1000);
  const browserOffset = -new Date(nowMs.value).getTimezoneOffset();
  const isVenue = c.app.zone === VENUE_TIMEZONE;
  const pinned = c.db.session_tz === '+00:00';

  return [
    {
      key: 'app',
      label: 'Application (PHP)',
      time: tick(c.app.now),
      zone: zoneText(c.app.zone, c.app.offset),
      pill: isVenue ? { tone: 'ok', label: 'Qatar time' } : { tone: 'danger', label: 'Not Qatar time' },
      note: isVenue ? 'What the app stores and shows.' : `Set APP_TIMEZONE=${VENUE_TIMEZONE}.`,
    },
    {
      key: 'db',
      label: 'MySQL · app connection',
      time: tick(c.db.session_now),
      zone: zoneText(c.db.session_tz, sessionOffset),
      pill: pinned ? { tone: 'ok', label: 'Pinned' } : { tone: 'danger', label: 'Not pinned' },
      note: pinned
        ? "Keeps the app's digits as written. MySQL's NOW() reads UTC here, so the app uses its own clock."
        : 'Expected +00:00 (DB_TIMEZONE); TIMESTAMP values can shift.',
    },
    {
      key: 'server',
      label: 'MySQL · server default',
      time: tick(c.db.server_now),
      zone: zoneText(c.db.global_tz, serverOffset),
      pill: clientShift === null
        ? { tone: 'neutral', label: 'Unknown' }
        : clientShift === 0
          ? { tone: 'ok', label: 'Same as app' }
          : { tone: 'warn', label: `${clientShift > 0 ? '+' : '−'}${spanText(clientShift)} in SQL clients` },
      note: clientShift === null
        ? "MySQL can't convert to this zone (time zone tables not loaded)."
        : clientShift === 0
          ? 'Used by SQL clients; they show the same digits as the app.'
          : `Used by HeidiSQL and other SQL clients: TIMESTAMP columns (job and checkpoint times, created/updated) show ${spanText(clientShift)} ${clientShift > 0 ? 'later' : 'earlier'} than in the app. Run SET time_zone = '+00:00'; first.`,
    },
    {
      key: 'utc',
      label: 'UTC',
      time: tick(c.app.utc),
      zone: 'UTC',
      pill: Math.abs(dbDrift) <= 5 ? { tone: 'ok', label: 'PHP = MySQL' } : { tone: 'warn', label: `MySQL ${aheadBehind(dbDrift)}` },
      note: 'Reference only; Qatar is UTC+03:00.',
    },
    {
      key: 'browser',
      label: 'This browser',
      time: wallText(nowMs.value + browserOffset * 60000),
      zone: zoneText(Intl.DateTimeFormat().resolvedOptions().timeZone, browserOffset),
      pill: Math.abs(browserDrift) <= 30 ? { tone: 'ok', label: 'In sync' } : { tone: 'warn', label: aheadBehind(browserDrift) },
      note: "This device's clock; live screens such as the Day Board use it.",
    },
  ];
});

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'scheduled':   { tone: 'primary', label: 'Scheduled' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'done':        { tone: 'ok',      label: 'Done' },
};

function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
const { statusLabel: sharedStatusLabel } = useStatusLabels();
function statusLabel(s) { return sharedStatusLabel(s, statusMap[s]?.label); }

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
  min-width: 36px; height: 36px; padding: 0 6px; border-radius: 8px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700; letter-spacing: 0.04em; white-space: nowrap;
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

/* clocks */
.card-hint { font-size: 11.5px; color: var(--ink4); }
.clock-time { color: var(--ink); font-weight: 600; }
.clock-note { margin-top: 4px; }
</style>
