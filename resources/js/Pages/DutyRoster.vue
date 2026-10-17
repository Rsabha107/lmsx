<template>
  <app-layout>
    <div v-if="!hasActiveEvent" class="empty-state-full">
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">Select an event from the dropdown above to see its duty roster.</p>
    </div>

    <div v-else class="dr-page">
      <header class="dr-head">
        <div>
          <div class="dr-kicker">Duty roster · {{ eventName }}</div>
          <h1 class="dr-h1">{{ tab === 'summary' ? 'Roster summary' : 'Shift schedule' }}</h1>
          <p class="dr-sub">{{ rangeLabel }} · {{ members.length }} staff<template v-if="todayCoverage"> · {{ todayCoverage }} on duty today</template></p>
        </div>
        <div class="dr-actions">
          <button v-if="canManage" type="button" class="dr-btn" @click="typesOpen = true">
            <svg-icon name="settings" :size="15" /> Shift types
          </button>
          <button v-if="canManage" type="button" class="dr-btn" @click="openAdd">
            <svg-icon name="user" :size="15" /> Add staff
          </button>
          <a class="dr-btn dr-btn--primary" :href="exportUrl">
            <svg-icon name="download" :size="15" /> Excel
          </a>
        </div>
      </header>

      <div class="dr-tabs" role="tablist" aria-label="Roster view">
        <button v-for="t in TABS" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key"
          :class="['dr-tab', { 'dr-tab--on': tab === t.key }]" @click="setTab(t.key)">
          <svg-icon :name="t.icon" :size="15" /> {{ t.label }}
        </button>
      </div>

      <!-- Shift palette: pick one, then click or drag across days to paint it. -->
      <div v-if="tab === 'schedule'" class="dr-palette">
        <button
          v-for="t in shiftTypes" :key="t.id" type="button"
          :class="['dr-chip', { 'dr-chip--on': brush === t.id, 'dr-chip--static': !canManage }]"
          :style="chipStyle(t)" :disabled="!canManage" :aria-pressed="brush === t.id"
          @click="toggleBrush(t.id)"
        >
          <span class="dr-chip-code" :style="{ background: t.color, color: textOn(t.color) }">{{ t.code }}</span>
          <span class="dr-chip-text">
            <span class="dr-chip-name">{{ t.name }}</span>
            <span class="dr-chip-hours">{{ t.hours || (t.is_leave ? 'Off duty' : 'Flexible') }}</span>
          </span>
        </button>
        <button v-if="canManage" type="button" :class="['dr-chip', 'dr-chip--clear', { 'dr-chip--on': brush === CLEAR }]" :aria-pressed="brush === CLEAR" @click="toggleBrush(CLEAR)">
          <span class="dr-chip-code dr-chip-code--clear">✕</span>
          <span class="dr-chip-text"><span class="dr-chip-name">Clear</span><span class="dr-chip-hours">Remove shift</span></span>
        </button>
        <p v-if="canManage" class="dr-hint">
          <template v-if="brush !== null">Painting <b>{{ brushLabel }}</b> — click or drag across days. <a href="#" @click.prevent="brush = null">Stop</a></template>
          <template v-else>Pick a shift to paint, or select days and choose a shift.</template>
        </p>
      </div>

      <div class="dr-toolbar">
        <div class="dr-week">
          <button type="button" class="dr-nav" aria-label="Previous week" @click="go(addDays(from, -7))">‹</button>
          <span class="dr-week-label">{{ rangeLabel }}</span>
          <button type="button" class="dr-nav" aria-label="Next week" @click="go(addDays(from, 7))">›</button>
          <button type="button" class="dr-btn dr-btn--ghost" :disabled="isCurrent" @click="go(todayKey())">This week</button>
          <MonthCalendar :model-value="from" :range="[from, lastDay]" unit="day" @update:model-value="go" />
        </div>
        <div class="dr-seg" role="tablist" aria-label="Weeks shown">
          <button v-for="w in WEEKS" :key="w" type="button" role="tab" :aria-selected="weeks === w"
            :class="['dr-seg-btn', { 'dr-seg-btn--on': weeks === w }]" @click="go(from, w)">{{ w }} wk</button>
        </div>
      </div>

      <div v-if="!members.length" class="dr-empty">
        <svg-icon name="team" :size="28" />
        <p>No one is on this event's roster yet.</p>
        <button v-if="canManage" type="button" class="dr-btn dr-btn--primary" @click="openAdd">Add staff</button>
      </div>

      <div v-else-if="tab === 'schedule'" class="dr-card">
        <div class="dr-scroll" @mouseleave="hover = null">
          <table class="dr-grid" :class="{ 'dr-grid--paint': canManage }">
            <thead>
              <tr>
                <th class="dr-sticky dr-c-num" rowspan="2">#</th>
                <th class="dr-sticky dr-c-name" rowspan="2">Staff</th>
                <th v-for="d in days" :key="d.key" :class="['dr-day', { 'dr-day--today': d.today, 'dr-day--weekend': d.weekend, 'dr-day--weekstart': d.weekStart }]">
                  <div class="dr-day-dow">{{ d.dow }}</div>
                </th>
                <th class="dr-c-sum" rowspan="2">Days</th>
              </tr>
              <tr>
                <th v-for="d in days" :key="d.key" :class="['dr-day dr-day--num', { 'dr-day--today': d.today, 'dr-day--weekend': d.weekend, 'dr-day--weekstart': d.weekStart }]">
                  <span class="dr-day-num">{{ d.num }}</span><span class="dr-day-mon">{{ d.mon }}</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(m, r) in members" :key="m.user_id" :class="{ 'dr-row--hover': hover?.r === r }">
                <td class="dr-sticky dr-c-num">{{ r + 1 }}</td>
                <td class="dr-sticky dr-c-name">
                  <div class="dr-person">
                    <span class="dr-avatar" :style="{ background: avatarColor(m.name) }">{{ initials(m.name) }}</span>
                    <div class="dr-person-text">
                      <div class="dr-person-name" :title="m.email">{{ m.name }}</div>
                      <input
                        v-if="editing === m.user_id" ref="designationInput" v-model="designationDraft" class="dr-designation-input"
                        maxlength="100" placeholder="Designation" @keydown.enter="saveDesignation(m)" @keydown.esc.stop="editing = null" @blur="saveDesignation(m)"
                      />
                      <button v-else-if="canManage" type="button" class="dr-person-role dr-person-role--edit" title="Edit designation" @click="editDesignation(m)">{{ m.designation || 'Add designation' }}</button>
                      <div v-else class="dr-person-role">{{ m.designation || '—' }}</div>
                    </div>
                    <div v-if="canManage" class="dr-row-tools">
                      <button type="button" title="Move up" :disabled="r === 0" @click="moveMember(m, 'up')">↑</button>
                      <button type="button" title="Move down" :disabled="r === members.length - 1" @click="moveMember(m, 'down')">↓</button>
                      <button type="button" title="Remove from roster" class="dr-row-tools-danger" @click="removing = m">✕</button>
                    </div>
                  </div>
                </td>
                <td
                  v-for="(d, c) in days" :key="d.key"
                  :class="['dr-cell', { 'dr-day--today': d.today, 'dr-day--weekend': d.weekend, 'dr-day--weekstart': d.weekStart, 'dr-cell--sel': isSelected(r, c), 'dr-cell--col': hover?.c === c }]"
                  @mousedown.prevent="startSelect(r, c, $event)"
                  @mouseenter="onEnter(r, c)"
                >
                  <span v-if="typeAt(m, d)" class="dr-shift" :style="shiftStyle(typeAt(m, d))" :title="`${typeAt(m, d).name}${typeAt(m, d).hours ? ' · ' + typeAt(m, d).hours : ''}`">
                    {{ weeks > 2 ? typeAt(m, d).code : typeAt(m, d).name }}
                  </span>
                </td>
                <td class="dr-c-sum">
                  <span class="dr-sum-work" title="Working days">{{ rowSummary(m).work }}</span>
                  <span v-if="rowSummary(m).leave" class="dr-sum-leave" title="Leave days">{{ rowSummary(m).leave }}L</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td class="dr-sticky dr-c-num"></td>
                <td class="dr-sticky dr-c-name dr-foot-label">On duty</td>
                <td v-for="d in days" :key="d.key" :class="['dr-foot', { 'dr-day--today': d.today, 'dr-day--weekend': d.weekend, 'dr-day--weekstart': d.weekStart }]">
                  <div v-for="cov in coverage[d.key]" :key="cov.id" class="dr-cov" :style="{ color: cov.color }" :title="`${cov.name}: ${cov.count}`">
                    <i :style="{ background: cov.color }"></i>{{ cov.code }} {{ cov.count }}
                  </div>
                  <span v-if="!coverage[d.key].length" class="dr-cov-none">—</span>
                </td>
                <td class="dr-c-sum"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Summary: coverage per shift, workload per person, period figures. -->
      <div v-else class="dr-summary">
        <section class="dr-sec">
          <h2 class="dr-sec-title"><svg-icon name="schedule" :size="15" /> Shift coverage</h2>
          <div class="dr-cov-cards">
            <div v-for="t in coverageCards" :key="t.id" class="dr-cov-card" :style="{ '--chip': t.color }">
              <div class="dr-cov-card-head">
                <span class="dr-chip-code" :style="{ background: t.color, color: textOn(t.color) }">{{ t.code }}</span>
                <span class="dr-cov-card-name">{{ t.name }}{{ t.is_leave ? '' : ' shift' }}</span>
              </div>
              <div class="dr-cov-card-hours">{{ t.hours || (t.is_leave ? 'Off duty' : 'Flexible hours') }}</div>
              <div class="dr-cov-card-num">{{ t.total }}</div>
              <div class="dr-cov-card-label">{{ t.is_leave ? 'Leave days' : 'Total slots' }} · {{ t.share }}%</div>
              <div class="dr-cov-card-bar"><span :style="{ width: `${t.share}%` }"></span></div>
            </div>
          </div>
        </section>

        <section class="dr-sec">
          <h2 class="dr-sec-title"><svg-icon name="user" :size="15" /> Staff workload</h2>
          <div class="dr-card">
            <div class="dr-scroll dr-scroll--auto">
              <table class="dr-work">
                <thead>
                  <tr>
                    <th class="dr-work-id">Staff ID</th>
                    <th class="dr-work-name">Name</th>
                    <th v-for="t in shiftTypes" :key="t.id" class="dr-work-type" :style="{ background: t.color, color: textOn(t.color) }">{{ t.name }}</th>
                    <th class="dr-work-total">Total shifts</th>
                    <th class="dr-work-load">Load</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(row, i) in workload" :key="row.user_id">
                    <td class="dr-work-id">S{{ i + 1 }}</td>
                    <td class="dr-work-name">
                      <div class="dr-person">
                        <span class="dr-avatar dr-avatar--sm" :style="{ background: avatarColor(row.name) }">{{ initials(row.name) }}</span>
                        <div class="dr-person-text">
                          <div class="dr-person-name">{{ row.name }}</div>
                          <div class="dr-person-role">{{ row.designation || '—' }}</div>
                        </div>
                      </div>
                    </td>
                    <td v-for="t in shiftTypes" :key="t.id" class="dr-work-type" :style="{ background: `color-mix(in srgb, ${t.color} 14%, var(--surface))` }">
                      {{ row.counts[t.id] || 0 }}
                    </td>
                    <td class="dr-work-total"><span>{{ row.total }}</span></td>
                    <td class="dr-work-load">
                      <div class="dr-load"><span :style="{ width: `${maxTotal ? (row.total / maxTotal) * 100 : 0}%` }"></span></div>
                      <span class="dr-load-text">{{ row.open }} open</span>
                    </td>
                  </tr>
                  <tr v-if="!workload.length"><td :colspan="shiftTypes.length + 4" class="dr-muted">No staff on the roster yet.</td></tr>
                </tbody>
                <tfoot v-if="workload.length">
                  <tr>
                    <td class="dr-work-id"></td>
                    <td class="dr-work-name">Total</td>
                    <td v-for="t in shiftTypes" :key="t.id" class="dr-work-type">{{ typeTotals[t.id] || 0 }}</td>
                    <td class="dr-work-total"><span>{{ totalSlots }}</span></td>
                    <td class="dr-work-load"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </section>

        <section class="dr-sec">
          <h2 class="dr-sec-title"><svg-icon name="schedule" :size="15" /> Roster period</h2>
          <div class="dr-stats">
            <div v-for="s in periodStats" :key="s.label" class="dr-stat">
              <div class="dr-stat-label">{{ s.label }}</div>
              <div class="dr-stat-value">{{ s.value }}</div>
              <div v-if="s.note" class="dr-stat-note">{{ s.note }}</div>
            </div>
          </div>
        </section>
      </div>

      <!-- Shift picker for a selection when no shift is being painted. -->
      <div v-if="picker" class="dr-picker" :style="{ top: `${picker.y}px`, left: `${picker.x}px` }" @mousedown.stop>
        <div class="dr-picker-head">{{ selection.length }} day{{ selection.length === 1 ? '' : 's' }} selected</div>
        <button v-for="t in shiftTypes" :key="t.id" type="button" class="dr-picker-item" @click="applyToSelection(t.id)">
          <span class="dr-chip-code" :style="{ background: t.color, color: textOn(t.color) }">{{ t.code }}</span>
          <span>{{ t.name }}</span><span class="dr-picker-hours">{{ t.hours }}</span>
        </button>
        <button type="button" class="dr-picker-item dr-picker-item--clear" @click="applyToSelection(null)">
          <span class="dr-chip-code dr-chip-code--clear">✕</span><span>Clear</span>
        </button>
      </div>
    </div>

    <!-- Add staff -->
    <Modal :show="addOpen" max-width="520px" @close="addOpen = false">
      <template #title>Add staff to the roster</template>
      <input v-model="addSearch" type="search" class="dr-input" placeholder="Search name, email or title…" aria-label="Search users" />
      <div class="dr-pick-list">
        <label v-for="u in filteredCandidates" :key="u.id" class="dr-pick-row">
          <input v-model="addIds" type="checkbox" :value="u.id" />
          <span class="dr-avatar dr-avatar--sm" :style="{ background: avatarColor(u.name) }">{{ initials(u.name) }}</span>
          <span class="dr-pick-text">
            <span class="dr-pick-name">{{ u.name }}</span>
            <span class="dr-pick-meta">{{ [u.job_title, u.email].filter(Boolean).join(' · ') }}</span>
          </span>
        </label>
        <p v-if="!filteredCandidates.length" class="dr-muted">No matching users.</p>
      </div>
      <template #footer>
        <button type="button" class="dr-btn" @click="addOpen = false">Cancel</button>
        <button type="button" class="dr-btn dr-btn--primary" :disabled="!addIds.length || busy" @click="addMembers">
          Add {{ addIds.length || '' }} staff
        </button>
      </template>
    </Modal>

    <!-- Shift types -->
    <Modal :show="typesOpen" max-width="860px" @close="typesOpen = false">
      <template #title>Shift types</template>
      <table class="dr-types">
        <thead>
          <tr><th>Colour</th><th>Name</th><th>Code</th><th>Start</th><th>End</th><th>Leave</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="row in typeRows" :key="row.key">
            <td><input v-model="row.color" type="color" class="dr-color" aria-label="Colour" /></td>
            <td><input v-model="row.name" class="dr-input" maxlength="50" aria-label="Name" /></td>
            <td><input v-model="row.code" class="dr-input dr-input--code" maxlength="4" aria-label="Code" /></td>
            <td><input v-model="row.start_time" type="time" class="dr-input" aria-label="Start time" /></td>
            <td><input v-model="row.end_time" type="time" class="dr-input" aria-label="End time" /></td>
            <td class="dr-center"><input v-model="row.is_leave" type="checkbox" aria-label="Leave" /></td>
            <td class="dr-types-actions">
              <button type="button" class="dr-btn dr-btn--sm dr-btn--primary" :disabled="busy || !row.name || !row.code" @click="saveType(row)">{{ row.id ? 'Save' : 'Add' }}</button>
              <button v-if="row.id" type="button" class="dr-btn dr-btn--sm dr-btn--danger" :disabled="busy" @click="deleteType(row)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="dr-muted dr-types-note">Leave types are not counted as on duty. Leave start and end empty for shifts without set hours.</p>
    </Modal>

    <ConfirmModal
      :show="!!removing"
      title="Remove from roster"
      :message="removing ? `Remove <strong>${escapeHtml(removing.name)}</strong> from this event's roster? Their shifts on it are deleted too.` : ''"
      confirm-label="Remove"
      tone="danger"
      @confirm="removeMember"
      @close="removing = null"
    />
  </app-layout>
</template>

<script setup>
import { ref, reactive, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Modal from '../Components/Modal.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import MonthCalendar from '../Components/MonthCalendar.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import { todayKey } from '../Composables/useCrewRoster';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  from: { type: String, required: true },
  weeks: { type: Number, default: 2 },
  shiftTypes: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  shifts: { type: [Object, Array], default: () => ({}) },
  candidates: { type: Array, default: () => [] },
});

const WEEKS = [1, 2, 4, 6];
const CLEAR = 'clear';

const page = usePage();
const toast = useToast();
const hasActiveEvent = computed(() => !!page.props.activeEventId);
const canManage = computed(() => page.props.auth?.can?.['roster.manage'] === true);
const eventName = computed(() => page.props.eventList?.find((e) => e.id === page.props.activeEventId)?.name ?? '');

watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast.success(flash.success);
  if (flash?.error) toast.error(flash.error);
});

// ── Dates ──────────────────────────────────────────────────────────────────
function addDays(key, n) {
  const d = new Date(`${key}T00:00:00`);
  d.setDate(d.getDate() + n);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}
const fmt = (key, opts) => new Date(`${key}T00:00:00`).toLocaleDateString('en-GB', opts);

const days = computed(() => Array.from({ length: props.weeks * 7 }, (_, i) => {
  const key = addDays(props.from, i);
  const dow = new Date(`${key}T00:00:00`).getDay();
  return {
    key,
    dow: fmt(key, { weekday: 'short' }),
    num: fmt(key, { day: 'numeric' }),
    mon: fmt(key, { month: 'short' }),
    today: key === todayKey(),
    weekend: dow === 5 || dow === 6,
    weekStart: i > 0 && dow === 0,
  };
}));
const lastDay = computed(() => addDays(props.from, props.weeks * 7 - 1));
const rangeLabel = computed(() => `${fmt(props.from, { day: 'numeric', month: 'short' })} – ${fmt(lastDay.value, { day: 'numeric', month: 'short', year: 'numeric' })}`);
const isCurrent = computed(() => todayKey() >= props.from && todayKey() <= lastDay.value);

function go(from, weeks = props.weeks) {
  cancelAll();
  router.get('/duty-roster', { from, weeks, tab: tab.value === 'schedule' ? 'schedule' : undefined }, { preserveState: true, preserveScroll: true });
}

const exportUrl = computed(() => `/duty-roster/export?${new URLSearchParams({ from: props.from, weeks: props.weeks })}`);

// ── Shifts ─────────────────────────────────────────────────────────────────
const cells = reactive({});
watch(() => props.shifts, (s) => {
  Object.keys(cells).forEach((k) => delete cells[k]);
  Object.assign(cells, s ?? {});
}, { immediate: true });

const typeById = computed(() => Object.fromEntries(props.shiftTypes.map((t) => [t.id, t])));
const typeAt = (m, d) => typeById.value[cells[`${m.user_id}|${d.key}`]] ?? null;

function textOn(hex) {
  const n = parseInt((hex || '#000000').slice(1), 16);
  const lum = 0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255);
  return lum > 160 ? '#1F2937' : '#FFFFFF';
}
const shiftStyle = (t) => ({
  background: `color-mix(in srgb, ${t.color} 18%, var(--surface))`,
  borderColor: `color-mix(in srgb, ${t.color} 55%, transparent)`,
  color: `color-mix(in srgb, ${t.color} 75%, var(--ink))`,
});
const chipStyle = (t) => ({ '--chip': t.color });

const coverage = computed(() => Object.fromEntries(days.value.map((d) => {
  const counts = {};
  for (const m of props.members) {
    const t = typeAt(m, d);
    if (t && !t.is_leave) counts[t.id] = (counts[t.id] ?? 0) + 1;
  }
  return [d.key, props.shiftTypes.filter((t) => counts[t.id]).map((t) => ({ ...t, count: counts[t.id] }))];
})));

const todayCoverage = computed(() => {
  const list = coverage.value[todayKey()];
  return list ? list.reduce((s, c) => s + c.count, 0) : 0;
});

function rowSummary(m) {
  let work = 0;
  let leave = 0;
  for (const d of days.value) {
    const t = typeAt(m, d);
    if (t) t.is_leave ? leave++ : work++;
  }
  return { work, leave };
}

// ── Summary tab ────────────────────────────────────────────────────────────
const TABS = [
  { key: 'summary', label: 'Summary', icon: 'chart' },
  { key: 'schedule', label: 'Schedule', icon: 'schedule' },
];
const tab = ref(new URLSearchParams(window.location.search).get('tab') === 'schedule' ? 'schedule' : 'summary');
function setTab(key) {
  tab.value = key;
  cancelAll();
  brush.value = null;
  const url = new URL(window.location.href);
  if (key === 'schedule') url.searchParams.set('tab', key);
  else url.searchParams.delete('tab');
  window.history.replaceState(window.history.state, '', url);
}

const workload = computed(() => props.members.map((m) => {
  const counts = {};
  let total = 0;
  let leave = 0;
  for (const d of days.value) {
    const t = typeAt(m, d);
    if (!t) continue;
    counts[t.id] = (counts[t.id] ?? 0) + 1;
    t.is_leave ? leave++ : total++;
  }
  return { ...m, counts, total, leave, open: days.value.length - total - leave };
}));

const typeTotals = computed(() => {
  const totals = {};
  for (const row of workload.value) {
    for (const [id, n] of Object.entries(row.counts)) totals[id] = (totals[id] ?? 0) + n;
  }
  return totals;
});
const totalSlots = computed(() => workload.value.reduce((s, r) => s + r.total, 0));
const totalLeave = computed(() => workload.value.reduce((s, r) => s + r.leave, 0));
const maxTotal = computed(() => Math.max(0, ...workload.value.map((r) => r.total)));

const coverageCards = computed(() => props.shiftTypes.map((t) => {
  const total = typeTotals.value[t.id] ?? 0;
  const base = t.is_leave ? totalSlots.value + totalLeave.value : totalSlots.value;
  return { ...t, total, share: base ? Math.round((total / base) * 100) : 0 };
}));

const periodStats = computed(() => {
  const staffDays = props.members.length * days.value.length;
  const open = staffDays - totalSlots.value - totalLeave.value;
  return [
    { label: 'Period start', value: fmt(props.from, { day: 'numeric', month: 'short', year: 'numeric' }), note: fmt(props.from, { weekday: 'long' }) },
    { label: 'Period end', value: fmt(lastDay.value, { day: 'numeric', month: 'short', year: 'numeric' }), note: fmt(lastDay.value, { weekday: 'long' }) },
    { label: 'Total days', value: days.value.length, note: `${props.weeks} week${props.weeks === 1 ? '' : 's'}` },
    { label: 'Staff', value: props.members.length },
    { label: 'Total slots', value: totalSlots.value, note: 'Working shifts' },
    { label: 'Leave days', value: totalLeave.value },
    { label: 'Open days', value: open, note: staffDays ? `${Math.round((open / staffDays) * 100)}% of staff-days unassigned` : null },
    { label: 'Avg. on duty / day', value: days.value.length ? (totalSlots.value / days.value.length).toFixed(1) : 0 },
  ];
});

// ── Painting and selection ─────────────────────────────────────────────────
const brush = ref(null);
const brushLabel = computed(() => (brush.value === CLEAR ? 'Clear' : typeById.value[brush.value]?.name));
function toggleBrush(id) {
  brush.value = brush.value === id ? null : id;
  picker.value = null;
  anchor.value = null;
}

const anchor = ref(null);
const focus = ref(null);
const dragging = ref(false);
const hover = ref(null);
const picker = ref(null);

const bounds = computed(() => {
  if (!anchor.value || !focus.value) return null;
  return {
    r1: Math.min(anchor.value.r, focus.value.r), r2: Math.max(anchor.value.r, focus.value.r),
    c1: Math.min(anchor.value.c, focus.value.c), c2: Math.max(anchor.value.c, focus.value.c),
  };
});
const isSelected = (r, c) => {
  const b = bounds.value;
  return !!b && r >= b.r1 && r <= b.r2 && c >= b.c1 && c <= b.c2;
};
const selection = computed(() => {
  const b = bounds.value;
  if (!b) return [];
  const out = [];
  for (let r = b.r1; r <= b.r2; r++) {
    for (let c = b.c1; c <= b.c2; c++) out.push({ user_id: props.members[r].user_id, date: days.value[c].key });
  }
  return out;
});

function startSelect(r, c, e) {
  if (!canManage.value || e.button !== 0) return;
  picker.value = null;
  if (e.shiftKey && anchor.value) {
    focus.value = { r, c };
  } else {
    anchor.value = { r, c };
    focus.value = { r, c };
  }
  dragging.value = true;
}

function onEnter(r, c) {
  hover.value = { r, c };
  if (dragging.value) focus.value = { r, c };
}

function onMouseUp(e) {
  if (!dragging.value) return;
  dragging.value = false;
  if (!selection.value.length) return;
  if (brush.value !== null) {
    apply(brush.value === CLEAR ? null : brush.value, selection.value);
    anchor.value = null;
    focus.value = null;
    return;
  }
  const x = Math.min(e.clientX + 8, window.innerWidth - 240);
  const y = Math.min(e.clientY + 8, window.innerHeight - (props.shiftTypes.length + 2) * 40 - 20);
  picker.value = { x, y: Math.max(8, y) };
}

function onDocMouseDown(e) {
  if (picker.value && !e.target.closest('.dr-picker') && !e.target.closest('.dr-cell')) cancelAll();
}

function cancelAll() {
  picker.value = null;
  anchor.value = null;
  focus.value = null;
  dragging.value = false;
}

function onKey(e) {
  if (e.key !== 'Escape') return;
  if (picker.value || anchor.value) cancelAll();
  else brush.value = null;
}

onMounted(() => {
  window.addEventListener('mouseup', onMouseUp);
  document.addEventListener('mousedown', onDocMouseDown);
  document.addEventListener('keydown', onKey);
});
onUnmounted(() => {
  window.removeEventListener('mouseup', onMouseUp);
  document.removeEventListener('mousedown', onDocMouseDown);
  document.removeEventListener('keydown', onKey);
});

function applyToSelection(typeId) {
  apply(typeId, selection.value);
  cancelAll();
}

function apply(typeId, list) {
  const changed = list.filter((cell) => (cells[`${cell.user_id}|${cell.date}`] ?? null) !== typeId);
  if (!changed.length) return;

  const before = Object.fromEntries(changed.map((cell) => [`${cell.user_id}|${cell.date}`, cells[`${cell.user_id}|${cell.date}`]]));
  for (const cell of changed) {
    const key = `${cell.user_id}|${cell.date}`;
    if (typeId === null) delete cells[key];
    else cells[key] = typeId;
  }

  router.put('/duty-roster/shifts', { shift_type_id: typeId, cells: changed }, {
    preserveScroll: true,
    preserveState: true,
    only: ['shifts'],
    onError: (errors) => {
      for (const [key, value] of Object.entries(before)) {
        if (value === undefined) delete cells[key];
        else cells[key] = value;
      }
      toast.error(Object.values(errors)[0] || 'The roster could not be saved.');
    },
  });
}

// ── Staff ──────────────────────────────────────────────────────────────────
const busy = ref(false);
const visit = (method, url, data = {}, extra = {}) => {
  busy.value = true;
  const options = { preserveScroll: true, preserveState: true, onFinish: () => { busy.value = false; }, ...extra };
  if (method === 'delete') router.delete(url, options);
  else router[method](url, data, options);
};

const addOpen = ref(false);
const addSearch = ref('');
const addIds = ref([]);
function openAdd() {
  addSearch.value = '';
  addIds.value = [];
  addOpen.value = true;
}
const filteredCandidates = computed(() => {
  const q = addSearch.value.trim().toLowerCase();
  return q ? props.candidates.filter((u) => `${u.name} ${u.email} ${u.job_title ?? ''}`.toLowerCase().includes(q)) : props.candidates;
});
function addMembers() {
  visit('post', '/duty-roster/members', { user_ids: addIds.value }, { onSuccess: () => { addOpen.value = false; } });
}

const editing = ref(null);
const designationDraft = ref('');
const designationInput = ref(null);
function editDesignation(m) {
  editing.value = m.user_id;
  designationDraft.value = m.designation ?? '';
  nextTick(() => (Array.isArray(designationInput.value) ? designationInput.value[0] : designationInput.value)?.focus());
}
function saveDesignation(m) {
  if (editing.value !== m.user_id) return;
  editing.value = null;
  const value = designationDraft.value.trim() || null;
  if (value === (m.designation ?? null)) return;
  visit('patch', `/duty-roster/members/${m.user_id}`, { designation: value });
}
function moveMember(m, move) {
  visit('patch', `/duty-roster/members/${m.user_id}`, { move });
}

const removing = ref(null);
// Keeps a name like "<b>x</b>" literal in ConfirmModal's message.
const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);
function removeMember() {
  const m = removing.value;
  removing.value = null;
  if (m) visit('delete', `/duty-roster/members/${m.user_id}`);
}

const initials = (name) => (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0]).join('').toUpperCase();
const AVATAR_HUES = [340, 210, 160, 30, 270, 190, 0, 120];
function avatarColor(name) {
  let h = 0;
  for (const ch of name || '') h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return `hsl(${AVATAR_HUES[h % AVATAR_HUES.length]} 45% 42%)`;
}

// ── Shift types ────────────────────────────────────────────────────────────
const typesOpen = ref(false);
const typeRows = ref([]);
const blankType = () => ({ key: `new-${Date.now()}`, id: null, name: '', code: '', color: '#0EA5E9', start_time: '', end_time: '', is_leave: false });
watch([typesOpen, () => props.shiftTypes], ([open]) => {
  if (!open) return;
  typeRows.value = [...props.shiftTypes.map((t) => ({ ...t, key: t.id, start_time: t.start_time ?? '', end_time: t.end_time ?? '' })), blankType()];
}, { immediate: true });

function saveType(row) {
  const data = {
    name: row.name.trim(), code: row.code.trim(), color: row.color, is_leave: !!row.is_leave,
    start_time: row.start_time || null, end_time: row.end_time || null,
  };
  visit(row.id ? 'put' : 'post', row.id ? `/duty-roster/shift-types/${row.id}` : '/duty-roster/shift-types', data, {
    onError: (errors) => toast.error(Object.values(errors)[0]),
  });
}
function deleteType(row) {
  visit('delete', `/duty-roster/shift-types/${row.id}`);
}
</script>

<style scoped>
.empty-state-full { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 60vh; text-align: center; }
.empty-state-title { font-size: 24px; font-weight: 700; color: var(--ink); margin-bottom: 8px; }
.empty-state-text { font-size: 14px; color: var(--ink3); max-width: 400px; }

.dr-page { display: flex; flex-direction: column; gap: 14px; }
.dr-head { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-end; gap: 12px; }
.dr-kicker { font-family: var(--font-mono, monospace); font-size: 12px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.dr-h1 { margin: 4px 0; font-size: 26px; font-weight: 600; color: var(--ink); }
.dr-sub { margin: 0; font-size: 13px; color: var(--ink3); }
.dr-actions { display: flex; flex-wrap: wrap; gap: 8px; }

.dr-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap;
}
.dr-btn:hover { background: var(--panel); }
.dr-btn:disabled { opacity: 0.45; pointer-events: none; }
.dr-btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.dr-btn--primary:hover { background: color-mix(in srgb, var(--accent) 88%, #000); }
.dr-btn--danger { color: var(--danger, #b91c1c); }
.dr-btn--ghost { font-weight: 500; }
.dr-btn--sm { padding: 5px 10px; font-size: 12px; }

/* Palette */
.dr-palette { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.dr-chip {
  --chip: var(--accent);
  display: inline-flex; align-items: center; gap: 9px; padding: 6px 12px 6px 6px; border-radius: 10px;
  border: 1px solid var(--border); background: var(--surface); cursor: pointer; font: inherit; text-align: left;
  transition: border-color .15s, box-shadow .15s, transform .15s;
}
.dr-chip:hover:not(:disabled) { border-color: var(--chip); transform: translateY(-1px); }
.dr-chip--on { border-color: var(--chip); box-shadow: 0 0 0 3px color-mix(in srgb, var(--chip) 25%, transparent); }
.dr-chip--static { cursor: default; }
.dr-chip--clear { --chip: var(--ink3); }
.dr-chip-code {
  display: inline-flex; align-items: center; justify-content: center; min-width: 26px; height: 26px; padding: 0 5px;
  border-radius: 7px; font-size: 11px; font-weight: 800; letter-spacing: .02em;
}
.dr-chip-code--clear { background: var(--panel); color: var(--ink3); border: 1px dashed var(--border); }
.dr-chip-text { display: flex; flex-direction: column; line-height: 1.15; }
.dr-chip-name { font-size: 12.5px; font-weight: 600; color: var(--ink); }
.dr-chip-hours { font-family: var(--font-mono, monospace); font-size: 10.5px; color: var(--ink3); }
.dr-hint { margin: 0 0 0 auto; font-size: 12px; color: var(--ink3); }
.dr-hint a { color: var(--accent); margin-left: 4px; }

/* Toolbar */
.dr-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.dr-week { display: flex; align-items: center; gap: 6px; }
.dr-week-label { font-size: 13px; font-weight: 600; color: var(--ink); min-width: 170px; text-align: center; }
.dr-nav { width: 32px; height: 32px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink2); font-size: 18px; cursor: pointer; }
.dr-nav:hover { background: var(--panel); }
.dr-week :deep(.mc-btn) { padding: 7px 14px; border-radius: 8px; font-size: 13px; font-weight: 500; color: var(--ink); }
.dr-seg { display: inline-flex; gap: 4px; background: var(--panel); border: 1px solid var(--border); padding: 4px; border-radius: 9px; }
.dr-seg-btn { border: 0; cursor: pointer; font: inherit; font-size: 12.5px; font-weight: 500; padding: 5px 12px; border-radius: 6px; background: transparent; color: var(--ink2); }
.dr-seg-btn--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, .12); }

.dr-empty {
  display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 56px 24px;
  color: var(--ink3); font-size: 13px; background: var(--surface); border: 1px dashed var(--border); border-radius: 12px;
}
.dr-empty p { margin: 0; }

/* Grid */
.dr-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.dr-scroll { overflow: auto; max-height: calc(100vh - 300px); }
.dr-grid { border-collapse: separate; border-spacing: 0; width: 100%; font-size: 12px; user-select: none; }
.dr-grid th, .dr-grid td { border-bottom: 1px solid var(--border); }
.dr-grid thead th {
  position: sticky; top: 0; z-index: 3; background: var(--panel);
  font-size: 11px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: .05em;
}
.dr-grid thead tr:nth-child(2) th { top: 27px; }
.dr-grid tfoot td { position: sticky; bottom: 0; z-index: 2; background: var(--panel); border-top: 1px solid var(--border); border-bottom: 0; }

.dr-sticky { position: sticky; z-index: 2; background: var(--surface); }
.dr-grid thead .dr-sticky { z-index: 4; background: var(--panel); }
.dr-grid tfoot .dr-sticky { z-index: 3; background: var(--panel); }
.dr-c-num { left: 0; width: 36px; min-width: 36px; text-align: center; color: var(--ink3); font-family: var(--font-mono, monospace); }
.dr-c-name { left: 36px; min-width: 250px; max-width: 250px; text-align: left; padding: 6px 12px !important; border-right: 1px solid var(--border); box-shadow: 4px 0 6px -4px rgba(0, 0, 0, .12); }
.dr-c-sum { min-width: 64px; text-align: center; border-left: 1px solid var(--border); white-space: nowrap; }

.dr-day { min-width: 74px; padding: 6px 4px 0; text-align: center; border-left: 1px solid transparent; }
.dr-day--num { padding: 0 4px 6px; }
.dr-day-dow { font-size: 10.5px; }
.dr-day-num { font-size: 15px; font-weight: 700; color: var(--ink); text-transform: none; letter-spacing: 0; margin-right: 3px; }
.dr-day-mon { font-size: 10.5px; text-transform: none; letter-spacing: 0; }
.dr-day--weekend { background: color-mix(in srgb, var(--panel) 55%, var(--surface)); }
.dr-grid thead .dr-day--weekend { background: color-mix(in srgb, var(--ink) 6%, var(--panel)); }
.dr-day--weekstart { border-left: 2px solid var(--border) !important; }
.dr-grid thead .dr-day--today { background: var(--accent); }
.dr-grid thead .dr-day--today, .dr-grid thead .dr-day--today .dr-day-num { color: #fff; }
.dr-cell.dr-day--today, .dr-foot.dr-day--today { background: color-mix(in srgb, var(--accent) 6%, var(--surface)); }

.dr-person { display: flex; align-items: center; gap: 10px; min-width: 0; }
.dr-avatar {
  flex-shrink: 0; width: 32px; height: 32px; border-radius: 50%; color: #fff;
  display: inline-flex; align-items: center; justify-content: center; font-size: 11.5px; font-weight: 700;
}
.dr-avatar--sm { width: 28px; height: 28px; font-size: 10.5px; }
.dr-person-text { flex: 1; min-width: 0; }
.dr-person-name { font-size: 13px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dr-person-role { display: block; max-width: 100%; font-size: 11.5px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dr-person-role--edit { padding: 0; border: 0; background: none; font: inherit; font-size: 11.5px; cursor: text; text-align: left; }
.dr-person-role--edit:hover { color: var(--accent); text-decoration: underline dotted; }
.dr-designation-input { width: 100%; padding: 2px 6px; font: inherit; font-size: 11.5px; border: 1px solid var(--accent); border-radius: 5px; background: var(--surface); color: var(--ink); }
.dr-row-tools { display: flex; gap: 2px; opacity: 0; transition: opacity .15s; }
tr:hover .dr-row-tools, .dr-row-tools:focus-within { opacity: 1; }
.dr-row-tools button {
  width: 22px; height: 22px; border: 1px solid var(--border); border-radius: 5px; background: var(--surface);
  color: var(--ink3); font-size: 11px; cursor: pointer; line-height: 1;
}
.dr-row-tools button:hover:not(:disabled) { color: var(--ink); background: var(--panel); }
.dr-row-tools button:disabled { opacity: .35; cursor: default; }
.dr-row-tools-danger:hover { color: var(--danger, #b91c1c) !important; }

.dr-cell { height: 46px; padding: 4px; text-align: center; border-left: 1px solid color-mix(in srgb, var(--border) 60%, transparent); }
.dr-grid--paint .dr-cell { cursor: cell; }
.dr-row--hover td.dr-cell, .dr-cell--col { background-image: linear-gradient(color-mix(in srgb, var(--accent) 4%, transparent), color-mix(in srgb, var(--accent) 4%, transparent)); }
.dr-cell--sel { box-shadow: inset 0 0 0 2px var(--accent); background: color-mix(in srgb, var(--accent) 10%, var(--surface)) !important; }
.dr-shift {
  display: flex; align-items: center; justify-content: center; height: 100%; min-height: 30px; padding: 0 4px;
  border: 1px solid; border-radius: 7px; font-size: 11.5px; font-weight: 700; white-space: nowrap; overflow: hidden;
  animation: dr-pop .18s ease-out;
}
@keyframes dr-pop { from { transform: scale(.85); opacity: .4; } to { transform: scale(1); opacity: 1; } }

.dr-sum-work { font-size: 13px; font-weight: 700; color: var(--ink); }
.dr-sum-leave { margin-left: 5px; font-size: 11px; font-weight: 600; color: var(--warn, #b45309); }

.dr-foot { padding: 6px 4px; vertical-align: top; text-align: left; border-left: 1px solid color-mix(in srgb, var(--border) 60%, transparent); }
.dr-foot-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--ink3); }
.dr-cov { display: flex; align-items: center; gap: 4px; font-family: var(--font-mono, monospace); font-size: 10.5px; font-weight: 700; line-height: 1.5; }
.dr-cov i { width: 6px; height: 6px; border-radius: 50%; }
.dr-cov-none { color: var(--ink4, var(--ink3)); padding-left: 4px; }

/* Picker */
.dr-picker {
  position: fixed; z-index: 1100; width: 230px; padding: 6px; border-radius: 12px;
  background: var(--surface); border: 1px solid var(--border); box-shadow: 0 12px 32px rgba(0, 0, 0, .18);
}
.dr-picker-head { padding: 6px 8px 8px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--ink3); }
.dr-picker-item {
  display: flex; align-items: center; gap: 10px; width: 100%; padding: 6px 8px; border: 0; border-radius: 8px;
  background: none; color: var(--ink); font: inherit; font-size: 13px; cursor: pointer; text-align: left;
}
.dr-picker-item:hover { background: var(--panel); }
.dr-picker-item--clear { border-top: 1px solid var(--border); border-radius: 0 0 8px 8px; margin-top: 4px; padding-top: 8px; }
.dr-picker-hours { margin-left: auto; font-family: var(--font-mono, monospace); font-size: 10.5px; color: var(--ink3); }

/* Modals */
.dr-input { width: 100%; padding: 7px 10px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--ink); font: inherit; font-size: 13px; box-sizing: border-box; }
.dr-input--code { width: 64px; text-transform: uppercase; }
.dr-pick-list { margin-top: 10px; max-height: 360px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; }
.dr-pick-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; }
.dr-pick-row:last-child { border-bottom: 0; }
.dr-pick-row:hover { background: var(--panel); }
.dr-pick-text { display: flex; flex-direction: column; min-width: 0; }
.dr-pick-name { font-size: 13px; font-weight: 600; color: var(--ink); }
.dr-pick-meta { font-size: 11.5px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dr-muted { color: var(--ink3); font-size: 12.5px; padding: 12px; margin: 0; }

.dr-types { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.dr-types th { text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--ink3); padding: 0 6px 6px; }
.dr-types td { padding: 4px 6px; vertical-align: middle; }
.dr-color { width: 38px; height: 32px; padding: 2px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); cursor: pointer; }
.dr-center { text-align: center; }
.dr-types-actions { display: flex; gap: 6px; white-space: nowrap; }
.dr-types-note { padding: 10px 6px 0; }

/* Tabs */
.dr-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--border); }
.dr-tab {
  display: inline-flex; align-items: center; gap: 7px; padding: 9px 16px; margin-bottom: -1px;
  border: 0; border-bottom: 2px solid transparent; background: none; color: var(--ink3);
  font: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer;
}
.dr-tab:hover { color: var(--ink); }
.dr-tab--on { color: var(--accent); border-bottom-color: var(--accent); }

/* Summary */
.dr-summary { display: flex; flex-direction: column; gap: 20px; }
.dr-sec { display: flex; flex-direction: column; gap: 10px; }
.dr-sec-title {
  display: flex; align-items: center; gap: 8px; margin: 0; padding: 8px 14px; border-radius: 9px;
  background: var(--accent); color: #fff; font-size: 12.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
}
.dr-cov-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
.dr-cov-card {
  --chip: var(--accent);
  padding: 14px 16px; border-radius: 12px; text-align: center;
  background: color-mix(in srgb, var(--chip) 12%, var(--surface));
  border: 1px solid color-mix(in srgb, var(--chip) 35%, transparent);
}
.dr-cov-card-head { display: flex; align-items: center; justify-content: center; gap: 8px; }
.dr-cov-card-name { font-size: 14px; font-weight: 700; color: color-mix(in srgb, var(--chip) 80%, var(--ink)); }
.dr-cov-card-hours { margin-top: 6px; font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink2); }
.dr-cov-card-num { margin-top: 4px; font-size: 34px; font-weight: 800; line-height: 1.1; color: color-mix(in srgb, var(--chip) 85%, var(--ink)); }
.dr-cov-card-label { font-size: 11.5px; color: var(--ink3); }
.dr-cov-card-bar { margin-top: 10px; height: 5px; border-radius: 3px; background: color-mix(in srgb, var(--chip) 15%, transparent); overflow: hidden; }
.dr-cov-card-bar span { display: block; height: 100%; background: var(--chip); border-radius: 3px; transition: width .3s; }

.dr-scroll--auto { max-height: none; }
.dr-work { width: 100%; border-collapse: collapse; font-size: 13px; }
.dr-work th {
  padding: 9px 12px; background: var(--panel); color: var(--ink2); font-size: 11.5px; font-weight: 700;
  text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid var(--border); white-space: nowrap;
}
.dr-work td { padding: 7px 12px; border-bottom: 1px solid var(--border); color: var(--ink); }
.dr-work tbody tr:hover td { filter: brightness(0.97); }
.dr-work tfoot td { font-weight: 700; background: var(--panel); border-bottom: 0; }
.dr-work-id { width: 70px; text-align: center; font-family: var(--font-mono, monospace); font-weight: 700; color: var(--accent) !important; }
.dr-work-name { text-align: left; min-width: 200px; }
.dr-work-type { text-align: center; font-weight: 700; font-size: 14px; min-width: 84px; }
.dr-work-total { text-align: center; min-width: 96px; }
.dr-work-total span {
  display: inline-block; min-width: 40px; padding: 3px 10px; border-radius: 7px;
  background: var(--ink); color: var(--surface); font-weight: 800;
}
.dr-work thead .dr-work-total { background: var(--ink); color: var(--surface); }
.dr-work-load { min-width: 140px; }
.dr-load { height: 6px; border-radius: 3px; background: var(--panel); overflow: hidden; }
.dr-load span { display: block; height: 100%; background: var(--accent); border-radius: 3px; transition: width .3s; }
.dr-load-text { font-size: 11px; color: var(--ink3); }

.dr-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 12px; }
.dr-stat { padding: 12px 14px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); box-shadow: inset 3px 0 0 var(--accent); }
.dr-stat-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--ink3); }
.dr-stat-value { margin-top: 4px; font-size: 22px; font-weight: 700; color: var(--ink); }
.dr-stat-note { font-size: 11.5px; color: var(--ink3); }
</style>
