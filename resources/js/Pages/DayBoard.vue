<template>
  <app-layout>
    <div class="db-page">
      <!-- Header -->
      <header class="db-head">
        <div>
          <div class="db-kicker">Supervisor day board</div>
          <h1 class="db-h1">
            {{ dayTitle }}
            <span v-if="isToday" class="db-today-badge">Today</span>
          </h1>
          <p class="db-sub">
            {{ jobs.length }} job{{ jobs.length === 1 ? '' : 's' }}
            <template v-if="counts.attention"> · <span class="db-sub-alert">{{ counts.attention }} need{{ counts.attention === 1 ? 's' : '' }} attention</span></template>
            <template v-if="isToday"> · updated {{ clock }}</template>
          </p>
        </div>
        <div class="db-head-actions">
          <div class="db-views" role="tablist" aria-label="View">
            <button type="button" role="tab" :aria-selected="view === 'board'" :class="['db-view', { 'db-view--on': view === 'board' }]" @click="setView('board')">Day Board</button>
            <button type="button" role="tab" :aria-selected="view === 'schedule'" :class="['db-view', { 'db-view--on': view === 'schedule' }]" @click="setView('schedule')">Schedule</button>
          </div>
          <div v-if="view === 'board'" class="db-search">
            <svg-icon name="search" :size="14" />
            <input v-model="search" type="search" placeholder="Team, job, vehicle, driver…" aria-label="Search jobs" />
          </div>
        </div>
      </header>

      <!-- Week strip: how stacked each day is -->
      <nav class="db-strip" aria-label="Pick a day">
        <button type="button" class="db-strip-nav" aria-label="Previous day" @click="goDay(addDays(date, -1))">‹</button>
        <button
          v-for="d in strip"
          :key="d.key"
          type="button"
          :class="['db-day', { 'db-day--on': d.key === date, 'db-day--today': d.today }]"
          :aria-current="d.key === date ? 'date' : undefined"
          @click="goDay(d.key)"
        >
          <span class="db-day-dow">{{ d.dow }}</span>
          <span class="db-day-num">{{ d.num }}</span>
          <span class="db-day-load"><span :style="{ width: `${d.load}%` }" /></span>
          <span class="db-day-count">{{ d.count || '–' }}</span>
        </button>
        <button type="button" class="db-strip-nav" aria-label="Next day" @click="goDay(addDays(date, 1))">›</button>
        <div ref="calRef" class="db-cal-wrap">
          <button ref="calBtn" type="button" class="db-strip-today db-cal-btn" :aria-expanded="calOpen" title="Pick a date" @click="toggleCal">
            <svg-icon name="schedule" :size="15" /> Calendar
          </button>
          <div v-if="calOpen" class="db-cal" :style="calStyle" role="dialog" aria-label="Pick a date">
            <div class="db-cal-head">
              <button type="button" class="db-cal-nav" aria-label="Previous month" @click="shiftMonth(-1)">‹</button>
              <span class="db-cal-title">{{ calTitle }}</span>
              <button type="button" class="db-cal-nav" aria-label="Next month" @click="shiftMonth(1)">›</button>
            </div>
            <div class="db-cal-grid">
              <span v-for="d in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="d" class="db-cal-dow">{{ d }}</span>
              <button
                v-for="cell in calCells"
                :key="cell.key"
                type="button"
                :class="['db-cal-day', {
                  'db-cal-day--out': !cell.inMonth,
                  'db-cal-day--on': cell.key === date,
                  'db-cal-day--today': cell.today,
                }]"
                :style="cell.count ? { '--heat': cell.heat } : undefined"
                :title="cell.count ? `${cell.count} job${cell.count === 1 ? '' : 's'}` : 'No jobs'"
                @click="pickDate(cell.key)"
              >
                <span>{{ cell.day }}</span>
                <span v-if="cell.count" class="db-cal-count">{{ cell.count }}</span>
              </button>
            </div>
            <div class="db-cal-foot">
              <span class="db-cal-legend"><span class="db-cal-swatch" /> Busier days are darker</span>
              <button type="button" class="db-cal-link" @click="pickDate(todayKey())">Today</button>
            </div>
          </div>
        </div>
        <button v-if="!isToday" type="button" class="db-strip-today" @click="goDay(todayKey())">Today</button>
      </nav>

      <div v-if="!hasActiveEvent" class="db-empty">Select an event to see its day board.</div>

      <template v-else-if="view === 'board'">
        <!-- Summary tiles double as filters -->
        <div class="db-tiles" role="tablist">
          <button
            v-for="t in tiles"
            :key="t.key"
            type="button"
            role="tab"
            :aria-selected="filter === t.key"
            :class="['db-tile', `db-tile--${t.key}`, { 'db-tile--on': filter === t.key }]"
            @click="filter = t.key"
          >
            <span class="db-tile-num">{{ counts[t.key] }}</span>
            <span class="db-tile-label"><span class="db-tile-dot" />{{ t.label }}</span>
          </button>
        </div>

        <div v-if="!groups.length" class="db-empty">
          <template v-if="!jobs.length">No jobs on this day.</template>
          <template v-else-if="filter === 'attention'">Nothing needs attention right now.</template>
          <template v-else>No jobs match.</template>
        </div>

        <!-- Hour lanes -->
        <section v-for="g in groups" :key="g.hour" :class="['db-lane', { 'db-lane--now': g.isNow }]">
          <div class="db-lane-head">
            <span class="db-lane-hour">{{ g.hour }}:00</span>
            <span v-if="g.isNow" class="db-now-tag">Now</span>
            <span class="db-lane-count">{{ g.items.length }} job{{ g.items.length === 1 ? '' : 's' }}</span>
            <span v-if="g.alerts" class="db-lane-alerts">{{ g.alerts }} need attention</span>
            <span class="db-lane-rule" />
          </div>

          <div class="db-grid">
            <article
              v-for="{ job, a } in g.items"
              :key="job.id"
              :class="['db-card', `db-card--${a.level}`, { 'db-card--done': job.status === 'completed' }]"
            >
              <header class="db-card-head">
                <div class="db-time">
                  <span class="db-time-start">{{ hhmm(job.start) }}</span>
                  <span class="db-time-end">{{ hhmm(job.end) }}</span>
                </div>
                <flag-icon :code="job.country_code" :fallback="job.flag" />
                <div class="db-team">
                  <div class="db-team-name">
                    <strong>{{ job.team_code || '—' }}</strong> {{ job.team }}
                  </div>
                  <div class="db-route" :title="`${job.from ?? ''} → ${job.to ?? ''}`">{{ job.from || '?' }} <span>→</span> {{ job.to || '?' }}</div>
                </div>
                <div class="db-card-tags">
                  <span v-if="job.kind" :class="['db-kind', `db-kind--${job.kind}`]">{{ job.kind.replace('_', ' ') }}</span>
                  <status-pill :tone="STATUS_TONES[job.status] ?? 'neutral'" size="sm">{{ job.status_label }}</status-pill>
                </div>
              </header>

              <!-- Previous → current step -->
              <div class="db-steps">
                <div :class="['db-step', 'db-step--prev', job.previous ? `db-step--${prevTone(job)}` : 'db-step--none']">
                  <div class="db-step-label">Previous</div>
                  <template v-if="job.previous">
                    <div class="db-step-name">
                      <span class="db-step-icon">{{ job.previous.state === 'skipped' ? '⤼' : '✓' }}</span>
                      {{ job.previous.name }}
                    </div>
                    <div class="db-step-meta">
                      <template v-if="job.previous.state === 'skipped'">
                        Skipped {{ hhmm(job.previous.completed) }}<template v-if="job.previous.skip_reason"> · {{ job.previous.skip_reason }}</template>
                      </template>
                      <template v-else>
                        Done {{ hhmm(job.previous.completed) }}
                        <span v-if="job.previous.delay > 0" class="db-delta db-delta--late">+{{ duration(job.previous.delay) }} late</span>
                        <span v-else-if="job.previous.delay < 0" class="db-delta db-delta--early">{{ duration(-job.previous.delay) }} early</span>
                        <span v-else-if="job.previous.delay === 0" class="db-delta db-delta--early">on time</span>
                      </template>
                    </div>
                  </template>
                  <div v-else class="db-step-name db-muted">Not started yet</div>
                </div>

                <div class="db-step-arrow" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                </div>

                <div :class="['db-step', 'db-step--current', `db-step--${currentTone(job, a)}`]">
                  <div class="db-step-label">{{ job.current ? 'Current step' : 'Finished' }}</div>
                  <template v-if="job.current">
                    <div class="db-step-name">
                      <span class="db-pulse" />
                      {{ job.current.name }}
                    </div>
                    <div class="db-step-meta">
                      <template v-if="a.overdue !== null"><strong>Overdue {{ duration(a.overdue) }}</strong> · due {{ hhmm(job.current.scheduled) }}</template>
                      <template v-else-if="a.dueIn !== null">Due {{ hhmm(job.current.scheduled) }} · in {{ duration(a.dueIn) }}</template>
                      <template v-else>No time set</template>
                    </div>
                    <div v-if="job.next" class="db-step-then">Then: {{ job.next.name }} {{ hhmm(job.next.scheduled) }}</div>
                  </template>
                  <template v-else>
                    <div class="db-step-name">✓ All {{ job.steps.length }} steps done</div>
                  </template>
                </div>
              </div>

              <!-- Step track -->
              <div v-if="job.steps.length" class="db-track">
                <span
                  v-for="(s, i) in job.steps"
                  :key="i"
                  :title="`${i + 1}. ${s.name} (${s.state})`"
                  :class="['db-track-seg', `db-track-seg--${i === job.current_index ? 'current' : s.state}`]"
                />
                <span class="db-track-label">
                  {{ job.current_index === null ? job.steps.length : job.current_index + 1 }}/{{ job.steps.length }}
                </span>
              </div>

              <div v-if="a.reasons.length" class="db-reasons">
                <span v-for="(r, i) in a.reasons" :key="i" :class="['db-reason', `db-reason--${r.tone}`]">{{ r.text }}</span>
              </div>

              <footer class="db-crew">
                <span :class="{ 'db-missing': !job.vehicle }"><svg-icon name="bus" :size="13" />{{ job.vehicle || 'No vehicle' }}</span>
                <span :class="{ 'db-missing': !job.driver }">
                  <svg-icon name="user" :size="13" />
                  <a v-if="job.driver_phone" :href="`tel:${job.driver_phone}`" @click.stop>{{ job.driver }}</a>
                  <template v-else>{{ job.driver || 'No driver' }}</template>
                </span>
                <span :class="{ 'db-missing': !job.supervisor }"><svg-icon name="shield" :size="13" />{{ job.supervisor || 'No supervisor' }}</span>
                <a :href="job.url" class="db-open" @click.prevent="router.visit(job.url)">{{ job.job_id }} ›</a>
              </footer>
            </article>
          </div>
        </section>
      </template>

      <!-- The same view as the Schedule menu, for this day -->
      <template v-else>
        <div class="filter-tabs">
          <button
            v-for="f in scheduleFilters"
            :key="f.value"
            type="button"
            :class="['filter-tab', { 'filter-tab--active': activeScheduleFilter === f.value }]"
            @click="activeScheduleFilter = f.value"
          >{{ f.label }}</button>
        </div>
        <div v-if="!schedule.length" class="db-empty">No movements on this day.</div>
        <MovementScheduleView v-else :schedule="scheduleRows" />
      </template>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import FlagIcon from '../Components/FlagIcon.vue';
import MovementScheduleView from '../Components/MovementScheduleView.vue';
import { useScheduleFilter } from '../Composables/useScheduleFilter';
import { minutesFrom, duration, todayKey } from '../Composables/useCrewRoster';

const props = defineProps({
  date: { type: String, required: true },
  view: { type: String, default: 'board' },
  jobs: { type: Array, default: () => [] },
  schedule: { type: Array, default: () => [] },
  dayCounts: { type: Object, default: () => ({}) },
});

const { filters: scheduleFilters, activeFilter: activeScheduleFilter, filtered: scheduleRows } = useScheduleFilter(() => props.schedule);

const page = usePage();
const hasActiveEvent = computed(() => !!page.props.activeEventId);

const STATUS_TONES = { pending: 'info', dispatched: 'primary', 'in-progress': 'live', completed: 'ok' };
// Minutes past due before a late step turns red.
const CRITICAL_AFTER = 15;

// Clock ticks so overdue/due-in stay live; data refreshes every minute.
const now = ref(new Date());
let tick;
let poll;
onMounted(() => {
  tick = setInterval(() => { now.value = new Date(); }, 30000);
  poll = setInterval(() => {
    if (!document.hidden) router.reload({ only: ['jobs', 'schedule', 'dayCounts'], preserveScroll: true });
  }, 60000);
});
onUnmounted(() => { clearInterval(tick); clearInterval(poll); });

const pad = (n) => String(n).padStart(2, '0');
const nowStamp = computed(() => {
  const d = now.value;
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
});
const clock = computed(() => nowStamp.value.slice(11));
const isToday = computed(() => props.date === todayKey());

const hhmm = (stamp) => stamp?.slice(11, 16) ?? '--:--';
const minutesBetween = (a, b) => minutesFrom(props.date, b) - minutesFrom(props.date, a);

function addDays(key, n) {
  const d = new Date(`${key}T00:00:00`);
  d.setDate(d.getDate() + n);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

// Kept in the URL so a day change or reload stays on the same view.
function visit(date, view) {
  router.get('/day-board', { date, view: view === 'schedule' ? 'schedule' : undefined }, { preserveState: true, preserveScroll: true });
}

function goDay(key) {
  visit(key, props.view);
}

function setView(next) {
  if (next !== props.view) visit(props.date, next);
}

const fmt = (key, opts) => new Date(`${key}T00:00:00`).toLocaleDateString('en-GB', opts);
const dayTitle = computed(() => fmt(props.date, { weekday: 'long', day: 'numeric', month: 'long' }));

// Month calendar for jumping to any date; shade shows how many jobs a day has.
const calOpen = ref(false);
const calRef = ref(null);
const calBtn = ref(null);
const calStyle = ref({});
const calMonth = ref(props.date.slice(0, 7)); // 'YYYY-MM'

// Fixed, because the day strip scrolls sideways and would clip it.
function toggleCal() {
  calOpen.value = !calOpen.value;
  if (!calOpen.value) return;
  calMonth.value = props.date.slice(0, 7);
  const rect = calBtn.value.getBoundingClientRect();
  const left = Math.max(8, Math.min(rect.right - 300, window.innerWidth - 308));
  calStyle.value = { top: `${rect.bottom + 6}px`, left: `${left}px` };
}

function shiftMonth(n) {
  const [y, m] = calMonth.value.split('-').map(Number);
  const d = new Date(y, m - 1 + n, 1);
  calMonth.value = `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
}

function pickDate(key) {
  calOpen.value = false;
  goDay(key);
}

const calTitle = computed(() => fmt(`${calMonth.value}-01`, { month: 'long', year: 'numeric' }));

const calCells = computed(() => {
  const first = `${calMonth.value}-01`;
  const start = addDays(first, -((new Date(`${first}T00:00:00`).getDay() + 6) % 7)); // Monday-first
  const keys = Array.from({ length: 42 }, (_, i) => addDays(start, i));
  const max = Math.max(1, ...keys.map((k) => props.dayCounts[k] ?? 0));
  return keys.map((key) => {
    const count = props.dayCounts[key] ?? 0;
    return {
      key,
      count,
      heat: `${Math.round(12 + (count / max) * 48)}%`,
      day: Number(key.slice(8)),
      inMonth: key.startsWith(calMonth.value),
      today: key === todayKey(),
    };
  });
});

function onDocDown(e) {
  if (calOpen.value && !calRef.value?.contains(e.target)) calOpen.value = false;
}
function onKey(e) {
  if (e.key === 'Escape') calOpen.value = false;
}
function onScroll(e) {
  if (calOpen.value && !calRef.value?.contains(e.target)) calOpen.value = false;
}
onMounted(() => {
  document.addEventListener('mousedown', onDocDown);
  document.addEventListener('keydown', onKey);
  window.addEventListener('scroll', onScroll, true);
  window.addEventListener('resize', onScroll);
});
onUnmounted(() => {
  document.removeEventListener('mousedown', onDocDown);
  document.removeEventListener('keydown', onKey);
  window.removeEventListener('scroll', onScroll, true);
  window.removeEventListener('resize', onScroll);
});

const strip = computed(() => {
  const days = Array.from({ length: 7 }, (_, i) => addDays(props.date, i - 3));
  const max = Math.max(1, ...days.map((k) => props.dayCounts[k] ?? 0));
  return days.map((key) => {
    const count = props.dayCounts[key] ?? 0;
    return {
      key,
      count,
      load: (count / max) * 100,
      today: key === todayKey(),
      dow: fmt(key, { weekday: 'short' }),
      num: fmt(key, { day: 'numeric', month: 'short' }),
    };
  });
});

/** Why a job needs a look, worked out against the live clock. */
function analyse(job) {
  const reasons = [];
  const done = job.status === 'completed';
  const n = nowStamp.value;
  let overdue = null;
  let dueIn = null;

  if (!done) {
    if (job.current?.scheduled) {
      if (n > job.current.scheduled) overdue = minutesBetween(job.current.scheduled, n);
      else dueIn = minutesBetween(n, job.current.scheduled);
    }
    if (overdue) {
      reasons.push({ tone: overdue >= CRITICAL_AFTER ? 'danger' : 'warn', text: `Step overdue ${duration(overdue)}` });
    }
    if (['pending', 'dispatched'].includes(job.status) && job.start && n > job.start) {
      const late = minutesBetween(job.start, n);
      reasons.push({ tone: late >= CRITICAL_AFTER ? 'danger' : 'warn', text: `Not started · ${duration(late)} past start` });
    }
    for (const issue of job.issues) {
      reasons.push({ tone: issue.severity === 'danger' ? 'danger' : 'warn', text: issue.label });
    }
    if (job.previous?.state === 'skipped') {
      reasons.push({ tone: 'warn', text: 'Previous step skipped' });
    } else if (job.previous?.on_time === false && job.previous.delay > 0) {
      reasons.push({ tone: 'warn', text: `Previous step ${duration(job.previous.delay)} late` });
    }
    const missing = [!job.vehicle && 'vehicle', !job.driver && 'driver', !job.supervisor && 'supervisor'].filter(Boolean);
    if (missing.length) reasons.push({ tone: 'warn', text: `No ${missing.join(', ')}` });
  }

  const level = reasons.some((r) => r.tone === 'danger') ? 'danger' : reasons.length ? 'warn' : done ? 'done' : 'ok';
  return { reasons, level, overdue, dueIn };
}

const prevTone = (job) => (job.previous.state === 'skipped' || job.previous.on_time === false ? 'warn' : 'ok');
const currentTone = (job, a) => (!job.current ? 'ok' : a.overdue !== null ? (a.overdue >= CRITICAL_AFTER ? 'danger' : 'warn') : 'live');

const analysed = computed(() => props.jobs.map((job) => ({ job, a: analyse(job) })));

const search = ref('');
const searched = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return analysed.value;
  return analysed.value.filter(({ job }) =>
    [job.job_id, job.team, job.team_code, job.vehicle, job.driver, job.supervisor, job.from, job.to]
      .some((v) => v && String(v).toLowerCase().includes(q)));
});

const MATCHES = {
  attention: ({ job, a }) => job.status !== 'completed' && a.reasons.length > 0,
  live: ({ job }) => job.status === 'in-progress',
  upcoming: ({ job }) => ['pending', 'dispatched'].includes(job.status),
  done: ({ job }) => job.status === 'completed',
  all: () => true,
};

const tiles = [
  { key: 'attention', label: 'Needs attention' },
  { key: 'live', label: 'In progress' },
  { key: 'upcoming', label: 'Upcoming' },
  { key: 'done', label: 'Done' },
  { key: 'all', label: 'All jobs' },
];

const counts = computed(() => Object.fromEntries(
  Object.entries(MATCHES).map(([key, fn]) => [key, searched.value.filter(fn).length])));

// Open on what needs attention, unless nothing does.
const filter = ref(props.jobs.some((job) => MATCHES.attention({ job, a: analyse(job) })) ? 'attention' : 'all');

const groups = computed(() => {
  const byHour = new Map();
  for (const entry of searched.value.filter(MATCHES[filter.value])) {
    const hour = entry.job.start?.slice(11, 13) ?? '--';
    if (!byHour.has(hour)) byHour.set(hour, []);
    byHour.get(hour).push(entry);
  }
  const nowHour = isToday.value ? clock.value.slice(0, 2) : null;
  return [...byHour.entries()]
    .sort(([a], [b]) => a.localeCompare(b))
    .map(([hour, items]) => ({
      hour,
      items,
      isNow: hour === nowHour,
      alerts: items.filter(MATCHES.attention).length,
    }));
});
</script>

<style scoped>
.db-page { display: flex; flex-direction: column; gap: 16px; }

/* Header */
.db-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 12px; }
.db-kicker { font-family: var(--font-mono, monospace); font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.db-h1 { margin: 4px 0; font-size: 26px; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 10px; }
.db-today-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 3px 8px; border-radius: 999px; background: var(--accent); color: #fff; }
.db-sub { margin: 0; font-size: 13px; color: var(--ink3); }
.db-sub-alert { color: var(--danger); font-weight: 600; }
.db-search { display: flex; align-items: center; gap: 6px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink3); min-width: 260px; }
.db-search input { border: 0; outline: none; background: none; padding: 8px 0; font-size: 13px; color: var(--ink); flex: 1; }
.db-head-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
.db-views { display: inline-flex; padding: 3px; gap: 2px; border: 1px solid var(--border); border-radius: 8px; background: var(--panel); }
.db-view { border: 0; background: none; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; color: var(--ink3); cursor: pointer; }
.db-view:hover { color: var(--ink); }
.db-view--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08); }

.filter-tabs { display: flex; gap: 4px; }
.filter-tab {
  padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; cursor: pointer; color: var(--ink3); font-weight: 500;
}
.filter-tab:hover { background: var(--panel); color: var(--ink); }
.filter-tab--active { background: var(--accent); color: #fff; border-color: var(--accent); }

/* Day strip */
.db-strip { display: flex; align-items: stretch; gap: 6px; overflow-x: auto; padding-bottom: 2px; }
.db-strip-nav, .db-strip-today {
  flex-shrink: 0; border: 1px solid var(--border); border-radius: 10px; background: var(--surface);
  color: var(--ink2); cursor: pointer; font-size: 18px; padding: 0 12px;
}
.db-strip-today { font-size: 13px; font-weight: 600; }
.db-strip-nav:hover, .db-strip-today:hover { background: var(--panel); }
.db-day {
  flex: 1; min-width: 78px; display: grid; grid-template-columns: 1fr auto; grid-template-areas: 'dow count' 'num num' 'load load';
  gap: 2px 6px; text-align: left; padding: 8px 10px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface); cursor: pointer; color: var(--ink);
}
.db-day:hover { border-color: var(--accent); }
.db-day--on { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 25%, transparent); background: color-mix(in srgb, var(--accent) 6%, var(--surface)); }
.db-day-dow { grid-area: dow; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink3); }
.db-day--today .db-day-dow { color: var(--accent); font-weight: 700; }
.db-day-num { grid-area: num; font-size: 14px; font-weight: 600; }
.db-day-count { grid-area: count; font-size: 12px; font-weight: 700; color: var(--ink2); }
.db-day-load { grid-area: load; height: 4px; border-radius: 2px; background: var(--panel); overflow: hidden; margin-top: 4px; }
.db-day-load span { display: block; height: 100%; background: var(--accent); border-radius: 2px; }

/* Calendar */
.db-cal-wrap { flex-shrink: 0; display: flex; }
.db-cal-btn { display: inline-flex; align-items: center; gap: 6px; }
.db-cal-btn[aria-expanded="true"] { border-color: var(--accent); color: var(--accent); }
.db-cal {
  position: fixed; z-index: 1100; width: 300px; padding: 12px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
}
.db-cal-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.db-cal-title { font-size: 14px; font-weight: 600; color: var(--ink); }
.db-cal-nav { width: 28px; height: 28px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--ink2); cursor: pointer; font-size: 16px; }
.db-cal-nav:hover { background: var(--panel); }
.db-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.db-cal-dow { font-size: 10px; font-weight: 600; color: var(--ink3); text-align: center; padding: 2px 0 4px; }
.db-cal-day {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; height: 38px;
  border: 1px solid transparent; border-radius: 7px; cursor: pointer; font-size: 12px; color: var(--ink);
  background: color-mix(in srgb, var(--accent) var(--heat, 0%), transparent);
}
.db-cal-day:hover { border-color: var(--accent); }
.db-cal-day--out { opacity: 0.4; }
.db-cal-day--today { font-weight: 700; box-shadow: inset 0 0 0 1px var(--accent); }
.db-cal-day--on { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 35%, transparent); font-weight: 700; }
.db-cal-count { font-size: 9px; font-weight: 700; line-height: 1; color: var(--ink2); }
.db-cal-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
.db-cal-legend { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--ink3); }
.db-cal-swatch { width: 24px; height: 8px; border-radius: 3px; background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 12%, transparent), color-mix(in srgb, var(--accent) 60%, transparent)); }
.db-cal-link { border: 0; background: none; color: var(--accent); font-size: 12px; font-weight: 600; cursor: pointer; }

/* Tiles */
.db-tiles { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 10px; }
.db-tile {
  --tile: var(--ink3);
  display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 12px 14px;
  border: 1px solid var(--border); border-radius: 12px; background: var(--surface); cursor: pointer; text-align: left;
  border-top: 3px solid var(--tile); transition: transform 0.12s ease, box-shadow 0.12s ease;
}
.db-tile:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06); }
.db-tile--on { background: color-mix(in srgb, var(--tile) 8%, var(--surface)); box-shadow: 0 0 0 1px var(--tile); }
.db-tile--attention { --tile: var(--danger); }
.db-tile--live { --tile: var(--accent); }
.db-tile--upcoming { --tile: var(--warn); }
.db-tile--done { --tile: var(--ok); }
.db-tile-num { font-size: 26px; font-weight: 700; color: var(--ink); line-height: 1.1; }
.db-tile-label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ink2); }
.db-tile-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--tile); }

.db-empty { padding: 48px; text-align: center; color: var(--ink3); font-size: 14px; background: var(--surface); border: 1px dashed var(--border); border-radius: 12px; }

/* Hour lanes */
.db-lane { display: flex; flex-direction: column; gap: 10px; }
.db-lane-head { display: flex; align-items: center; gap: 10px; position: sticky; top: 0; z-index: 2; padding: 6px 0; background: var(--bg); }
.db-lane-hour { font-family: var(--font-mono, monospace); font-size: 18px; font-weight: 700; color: var(--ink); }
.db-lane--now .db-lane-hour { color: var(--accent); }
.db-now-tag { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #fff; background: var(--accent); padding: 2px 7px; border-radius: 999px; }
.db-lane-count { font-size: 12px; color: var(--ink3); font-weight: 600; }
.db-lane-alerts { font-size: 12px; color: var(--danger); font-weight: 600; }
.db-lane-rule { flex: 1; height: 1px; background: var(--border); }
.db-lane--now .db-lane-rule { background: var(--accent); height: 2px; }

.db-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(380px, 1fr)); gap: 12px; }

/* Card */
.db-card {
  --level: var(--border);
  display: flex; flex-direction: column; gap: 12px; padding: 14px 14px 12px 16px;
  background: var(--surface); border: 1px solid var(--border); border-left: 4px solid var(--level); border-radius: 12px;
}
.db-card--danger { --level: var(--danger); box-shadow: 0 0 0 1px color-mix(in srgb, var(--danger) 25%, transparent); }
.db-card--warn { --level: var(--warn); }
.db-card--ok { --level: var(--accent); }
.db-card--done { --level: var(--ok); opacity: 0.75; }

.db-card-head { display: flex; align-items: center; gap: 10px; min-width: 0; }
.db-time { display: flex; flex-direction: column; align-items: center; padding-right: 10px; border-right: 1px solid var(--border); }
.db-time-start { font-family: var(--font-mono, monospace); font-size: 16px; font-weight: 700; color: var(--ink); }
.db-time-end { font-family: var(--font-mono, monospace); font-size: 11px; color: var(--ink3); }
.db-team { flex: 1; min-width: 0; }
.db-team-name { font-size: 14px; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.db-route { font-size: 12px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.db-route span { color: var(--ink2); }
.db-card-tags { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; flex-shrink: 0; }
.db-kind { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 2px 7px; border-radius: 4px; background: var(--panel); color: var(--ink2); }
.db-kind--arrival { background: #DBEAFE; color: #1E40AF; }
.db-kind--departure { background: #FCE7F3; color: #9D174D; }
.db-kind--match { background: #DCFCE7; color: #166534; }
.db-kind--training { background: #FEF3C7; color: #92400E; }
.db-kind--transfer { background: #EDE9FE; color: #5B21B6; }

/* Previous → current */
.db-steps { display: grid; grid-template-columns: 1fr auto 1.2fr; align-items: stretch; gap: 8px; }
.db-step { --tone: var(--ink3); padding: 10px 12px; border-radius: 10px; background: var(--panel); border: 1px solid transparent; min-width: 0; }
.db-step--ok { --tone: var(--ok); }
.db-step--warn { --tone: var(--warn); }
.db-step--danger { --tone: var(--danger); }
.db-step--live { --tone: var(--accent); }
.db-step--current { background: color-mix(in srgb, var(--tone) 9%, var(--surface)); border-color: color-mix(in srgb, var(--tone) 40%, transparent); }
.db-step--prev { opacity: 0.9; }
.db-step-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em; color: var(--ink3); margin-bottom: 4px; }
.db-step--current .db-step-label { color: var(--tone); }
.db-step-name { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: var(--ink); line-height: 1.3; }
.db-step-icon { color: var(--tone); font-weight: 700; }
.db-step-meta { margin-top: 3px; font-size: 12px; color: var(--ink2); }
.db-step--danger .db-step-meta strong, .db-step--warn .db-step-meta strong { color: var(--tone); }
.db-step-then { margin-top: 6px; padding-top: 6px; border-top: 1px dashed var(--border); font-size: 11px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.db-step-arrow { display: flex; align-items: center; color: var(--ink3); }
.db-muted { color: var(--ink3); font-weight: 500; }
.db-delta { margin-left: 4px; font-size: 11px; font-weight: 600; padding: 1px 6px; border-radius: 999px; }
.db-delta--late { background: color-mix(in srgb, var(--warn) 18%, transparent); color: var(--warn); }
.db-delta--early { background: color-mix(in srgb, var(--ok) 15%, transparent); color: var(--ok); }

.db-pulse { width: 8px; height: 8px; border-radius: 50%; background: var(--tone); flex-shrink: 0; box-shadow: 0 0 0 0 var(--tone); animation: db-pulse 1.8s infinite; }
@keyframes db-pulse {
  0% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--tone) 60%, transparent); }
  70% { box-shadow: 0 0 0 7px transparent; }
  100% { box-shadow: 0 0 0 0 transparent; }
}
@media (prefers-reduced-motion: reduce) { .db-pulse { animation: none; } }

/* Step track */
.db-track { display: flex; align-items: center; gap: 3px; }
.db-track-seg { flex: 1; height: 6px; border-radius: 3px; background: var(--border); }
.db-track-seg--done { background: var(--ok); }
.db-track-seg--skipped { background: repeating-linear-gradient(45deg, var(--warn), var(--warn) 3px, transparent 3px, transparent 6px); }
.db-track-seg--current { background: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 30%, transparent); }
.db-track-label { margin-left: 6px; font-family: var(--font-mono, monospace); font-size: 11px; color: var(--ink3); }

.db-reasons { display: flex; flex-wrap: wrap; gap: 6px; }
.db-reason { font-size: 11px; font-weight: 600; padding: 3px 8px; border-radius: 999px; }
.db-reason--warn { background: color-mix(in srgb, var(--warn) 15%, transparent); color: var(--warn); }
.db-reason--danger { background: color-mix(in srgb, var(--danger) 14%, transparent); color: var(--danger); }

.db-crew { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; padding-top: 10px; border-top: 1px solid var(--border); font-size: 12px; color: var(--ink2); }
.db-crew > span { display: inline-flex; align-items: center; gap: 4px; }
.db-crew a { color: inherit; }
.db-missing { color: var(--warn) !important; font-weight: 600; }
.db-open { margin-left: auto; font-family: var(--font-mono, monospace); font-size: 11px; font-weight: 600; color: var(--accent) !important; text-decoration: none; }
.db-open:hover { text-decoration: underline; }

@media (max-width: 900px) {
  .db-tiles { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
  .db-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .db-search { min-width: 0; width: 100%; }
  .db-grid { grid-template-columns: 1fr; }
  .db-steps { grid-template-columns: 1fr; }
  .db-step-arrow { justify-content: center; transform: rotate(90deg); }
}
</style>
