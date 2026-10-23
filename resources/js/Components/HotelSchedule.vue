<template>
  <div class="hs">
    <div v-if="!schedule" class="hs-loading">Loading the hotel schedule…</div>

    <template v-else>
      <div class="hs-toolbar">
        <div class="hs-legend" aria-label="Teams playing per hotel">
          <span v-for="l in LEGEND" :key="l.n" class="hs-legend-item"><i :class="['hs-swatch', `hs-tone-${l.n}`]" />{{ l.label }}</span>
        </div>
        <div class="hs-actions">
          <div class="hs-rule">
            <span>Report <b>KO − {{ hm(offsets.report_before) }}</b> · shift ends <b>KO + {{ hm(offsets.end_after) }}</b></span>
            <button v-if="canManage" type="button" class="hs-link" @click="openOffsets">Change</button>
          </div>
          <button v-if="canManage" type="button" class="hs-btn" :disabled="busy" title="Number empty crew teams 1, 2, 3… per hotel and day by report time, and suggest the kit runs" @click="autoFill">
            <svg-icon name="ai" :size="15" /> Auto-fill crews
          </button>
        </div>
      </div>

      <div v-if="schedule.unhoused.length || schedule.undated" class="hs-notes">
        <span v-if="schedule.unhoused.length">No hotel stay for {{ schedule.unhoused.join(', ') }}: add their stay on Event Teams to place them.</span>
        <span v-if="schedule.undated">{{ schedule.undated }} match{{ schedule.undated === 1 ? ' has' : 'es have' }} no date or kick-off yet and {{ schedule.undated === 1 ? 'is' : 'are' }} not shown.</span>
      </div>

      <div v-if="!hotels.length" class="hs-empty">
        <svg-icon name="building" :size="28" />
        <p>No team hotels yet. Hotels come from the teams' stays on Event Teams.</p>
      </div>

      <div v-else class="hs-frame">
        <div class="hs-scroll">
          <table class="hs-grid">
            <thead>
              <tr class="hs-band">
                <th class="hs-sticky hs-c-hotel" rowspan="2">Hotel</th>
                <th v-for="b in bands" :key="b.key" :colspan="b.span" :class="['hs-band-cell', { 'hs-band-cell--rest': b.rest }]">{{ b.label }}</th>
                <th class="hs-c-sum" rowspan="2">Active<br>days</th>
                <th class="hs-c-sum" rowspan="2">Peak<br>crew</th>
                <th class="hs-c-sum" rowspan="2">Teams</th>
              </tr>
              <tr>
                <th v-for="d in days" :key="d.date" :class="['hs-day', { 'hs-day--rest': d.rest, 'hs-day--today': d.date === today }]">
                  <span class="hs-day-dow">{{ d.dow }}</span>
                  <span class="hs-day-num">{{ d.num }}</span>
                  <span class="hs-day-mon">{{ d.mon }}</span>
                  <span v-if="d.tbd" class="hs-day-tbd" :title="`${d.tbd} match(es) without both teams yet`">{{ d.tbd }} TBD</span>
                </th>
              </tr>
            </thead>

            <tbody v-for="(h, hi) in hotels" :key="h.key" class="hs-hotel">
              <tr class="hs-main">
                <th class="hs-sticky hs-c-hotel" scope="row">
                  <div class="hs-hotel-name"><span class="hs-hotel-num">{{ hi + 1 }}</span>{{ h.name }}</div>
                  <div class="hs-hotel-teams">{{ h.teams.join(' · ') || 'No teams' }}</div>
                  <select v-if="canManage && supervisors.length && activeDates(h).length" class="hs-fill" :aria-label="`Supervisor for every day at ${h.name}`" value="" @change="fillSupervisor(h, $event)">
                    <option value="">Supervisor for all days…</option>
                    <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                  </select>
                </th>
                <td v-for="d in days" :key="d.date" :class="['hs-cell', { 'hs-cell--rest': d.rest }]">
                  <span v-if="d.rest" class="hs-rest">Rest</span>
                  <div v-else-if="dayOf(h, d)?.teams.length" :class="['hs-card', `hs-tone-${tone(dayOf(h, d))}`]">
                    <div class="hs-card-head">
                      <span>{{ dayOf(h, d).teams.length }} team{{ dayOf(h, d).teams.length === 1 ? '' : 's' }}</span>
                      <span>{{ crewCount(dayOf(h, d)) }} crew</span>
                    </div>
                    <select
                      v-model="dayOf(h, d).supervisor_id"
                      :class="['hs-sup', { 'hs-sup--empty': !dayOf(h, d).supervisor_id, 'hs-sup--clash': supervisorClash(h, d) }]"
                      :title="supervisorClash(h, d) ? `Also at ${supervisorClash(h, d)} this day` : null"
                      :disabled="!canManage"
                      :aria-label="`Supervisor at ${h.name} on ${d.date}`"
                      @change="saveDay(h, d, { supervisor_id: dayOf(h, d).supervisor_id })"
                    >
                      <option :value="null">Supervisor…</option>
                      <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <div v-for="t in dayOf(h, d).teams" :key="`${t.match_id}-${t.team_id}`" class="hs-team" :title="teamTitle(t)">
                      <span class="hs-team-code">{{ t.code }}</span>
                      <span class="hs-team-time">{{ t.report ?? '--:--' }}</span>
                      <select
                        v-model="t.crew"
                        :class="['hs-crew', { 'hs-crew--empty': !t.crew }]"
                        :disabled="!canManage"
                        :aria-label="`Crew team for ${t.code} on ${d.date}`"
                        @change="saveCrew(t)"
                      >
                        <option :value="null">Crew –</option>
                        <option v-for="n in crewNumbers" :key="n" :value="n">Crew {{ n }}</option>
                      </select>
                    </div>
                  </div>
                </td>
                <td class="hs-c-sum hs-sum-val" rowspan="3">{{ summary(h).active }}</td>
                <td class="hs-c-sum hs-sum-val hs-sum-peak" rowspan="3">{{ summary(h).peak }}</td>
                <td class="hs-c-sum hs-sum-val" rowspan="3">{{ h.teams.length }}</td>
              </tr>
              <tr v-for="run in RUNS" :key="run.key" class="hs-run">
                <th class="hs-sticky hs-c-hotel hs-run-label" scope="row">{{ run.label }}</th>
                <td v-for="d in days" :key="d.date" :class="['hs-cell', 'hs-cell--run', { 'hs-cell--rest': d.rest }]">
                  <template v-if="!d.rest && dayOf(h, d)?.teams.length">
                    <button
                      v-if="canManage" type="button"
                      :class="['hs-run-btn', { 'hs-run-btn--empty': !hasRun(dayOf(h, d)[run.key]) }]"
                      :aria-label="`${run.label} at ${h.name} on ${d.date}`"
                      @click="openRun(h, d, run.key, $event)"
                    >{{ runLabel(dayOf(h, d)[run.key]) || `+ ${suggestion(dayOf(h, d), run.key)}` }}</button>
                    <span v-else class="hs-run-text">{{ runLabel(dayOf(h, d)[run.key]) || '—' }}</span>
                  </template>
                </td>
              </tr>
            </tbody>

            <tfoot>
              <tr class="hs-total">
                <th class="hs-sticky hs-c-hotel" scope="row">Totals</th>
                <td v-for="d in days" :key="d.date" class="hs-cell">
                  <span v-if="totals[d.date]">{{ totals[d.date].hotels }} hotel{{ totals[d.date].hotels === 1 ? '' : 's' }} · {{ totals[d.date].crew }} crew</span>
                </td>
                <td class="hs-c-sum hs-sum-val">{{ grand.active }}</td>
                <td class="hs-c-sum hs-sum-val hs-sum-peak">{{ grand.peak }}</td>
                <td class="hs-c-sum hs-sum-val">{{ grand.teams }}</td>
              </tr>
              <tr class="hs-vsa">
                <th class="hs-sticky hs-c-hotel" scope="row">Aspire VSA team</th>
                <td v-for="d in days" :key="d.date" :class="['hs-cell', 'hs-cell--run', { 'hs-cell--rest': d.rest }]">
                  <button v-if="canManage && !d.rest" type="button" :class="['hs-run-btn', { 'hs-run-btn--empty': !vsaOf(d).length }]"
                    :aria-label="`Aspire VSA crews on ${d.date}`" @click="openVsa(d, $event)">
                    {{ vsaOf(d).length ? crewText(vsaOf(d)) : '+ crews' }}
                  </button>
                  <span v-else-if="vsaOf(d).length" class="hs-run-text">{{ crewText(vsaOf(d)) }}</span>
                </td>
                <td class="hs-c-sum" colspan="3" />
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </template>

    <!-- Kit run / VSA editor -->
    <div v-if="editor" class="hs-pop" :style="{ top: `${editor.y}px`, left: `${editor.x}px` }" role="dialog" :aria-label="editor.title" @mousedown.stop>
      <div class="hs-pop-title">{{ editor.title }}</div>
      <div class="hs-pop-sub">{{ editor.sub }}</div>
      <div class="hs-pop-label">Crew teams</div>
      <div class="hs-pop-crews">
        <button v-for="n in editorNumbers" :key="n" type="button" :class="['hs-pop-crew', { 'hs-pop-crew--on': editor.crews.includes(n), 'hs-pop-crew--used': editor.used.includes(n) }]"
          :aria-pressed="editor.crews.includes(n)" :aria-label="`Crew ${n}`" @click="toggleCrew(n)">{{ n }}</button>
      </div>
      <template v-if="editor.kind !== 'vsa'">
        <label class="hs-pop-label" for="hs-pop-time">Time</label>
        <input id="hs-pop-time" v-model="editor.time" class="hs-pop-time" inputmode="numeric" maxlength="5" placeholder="HH:MM" @keydown.enter.prevent="saveEditor()" />
        <p v-if="editor.error" class="hs-pop-error">{{ editor.error }}</p>
      </template>
      <div class="hs-pop-actions">
        <button type="button" class="hs-btn hs-btn--sm" @click="clearEditor">Clear</button>
        <span class="hs-pop-space" />
        <button type="button" class="hs-btn hs-btn--sm" @click="editor = null">Cancel</button>
        <button type="button" class="hs-btn hs-btn--sm hs-btn--primary" :disabled="busy" @click="saveEditor()">Save</button>
      </div>
    </div>

    <Modal :show="offsetsOpen" max-width="420px" @close="offsetsOpen = false">
      <div class="hs-modal">
        <h3>Report and shift end times</h3>
        <p class="hs-modal-sub">Each team's report time is its kick-off minus the first value; the crew's shift ends at kick-off plus the second.</p>
        <label class="hs-modal-field">Report before kick-off <input v-model="offsetDraft.report_before" placeholder="4:30" aria-label="Report before kick-off" /></label>
        <label class="hs-modal-field">Shift ends after kick-off <input v-model="offsetDraft.end_after" placeholder="4:30" aria-label="Shift ends after kick-off" /></label>
        <p v-if="offsetDraft.error" class="hs-pop-error">{{ offsetDraft.error }}</p>
        <div class="hs-pop-actions">
          <span class="hs-pop-space" />
          <button type="button" class="hs-btn" @click="offsetsOpen = false">Cancel</button>
          <button type="button" class="hs-btn hs-btn--primary" :disabled="busy" @click="saveOffsets">Save</button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import SvgIcon from './SvgIcon.vue';
import { todayKey } from '../Composables/useCrewRoster';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  schedule: { type: Object, default: null },
  canManage: { type: Boolean, default: false },
});

const toast = useToast();
const LEGEND = [
  { n: 1, label: '1 team' },
  { n: 2, label: '2 teams' },
  { n: 3, label: '3 teams' },
  { n: 4, label: '4+ teams (peak)' },
];
const RUNS = [
  { key: 'departure', label: 'Kits departure to venue' },
  { key: 'arrival', label: 'Kits arrival from venue' },
];
const today = todayKey();

// Local copy so selects change at once; refreshed from the server once no save is waiting.
const pending = ref(0);
const local = ref(null);
const syncLocal = () => { local.value = props.schedule ? JSON.parse(JSON.stringify(props.schedule)) : null; };
watch(() => props.schedule, () => { if (!pending.value) syncLocal(); }, { immediate: true });

const offsets = computed(() => local.value?.offsets ?? { report_before: 270, end_after: 270 });
const hotels = computed(() => local.value?.hotels ?? []);
const supervisors = computed(() => local.value?.supervisors ?? []);
const vsa = computed(() => local.value?.vsa ?? {});

const fmt = (key, opts) => new Date(`${key}T00:00:00`).toLocaleDateString('en-GB', opts);
const days = computed(() => (local.value?.days ?? []).map((d) => ({
  ...d,
  dow: fmt(d.date, { weekday: 'short' }),
  num: fmt(d.date, { day: 'numeric' }),
  mon: fmt(d.date, { month: 'short' }),
})));

// Stage band: one block per run of the same stage (or of rest days).
const bands = computed(() => {
  const out = [];
  for (const d of days.value) {
    const label = d.rest ? 'Rest' : (d.stage_name ?? '');
    const last = out[out.length - 1];
    if (last && last.label === label) last.span++;
    else out.push({ key: d.date, label, span: 1, rest: d.rest });
  }
  return out;
});

const hm = (m) => `${Math.floor(m / 60)}h${m % 60 ? String(m % 60).padStart(2, '0') : ''}`;
const dayOf = (h, d) => h.days?.[d.date] ?? null;
const activeDates = (h) => Object.keys(h.days ?? {}).filter((k) => h.days[k].teams.length);
const tone = (day) => Math.min(day.teams.length, 4);

// Crew teams needed: each distinct crew team assigned, plus one per team still without one.
function crewCount(day) {
  const assigned = day.teams.map((t) => t.crew).filter(Boolean);
  return new Set(assigned).size + (day.teams.length - assigned.length);
}

const maxCrew = computed(() => {
  let max = 0;
  for (const h of hotels.value) for (const day of Object.values(h.days ?? {})) for (const t of day.teams) max = Math.max(max, t.crew ?? 0);
  return max;
});
const crewNumbers = computed(() => Array.from({ length: Math.max(16, maxCrew.value + 2) }, (_, i) => i + 1));

function summary(h) {
  const list = activeDates(h).map((k) => h.days[k]);
  return { active: list.length, peak: Math.max(0, ...list.map(crewCount)) };
}
const totals = computed(() => {
  const out = {};
  for (const h of hotels.value) {
    for (const k of activeDates(h)) {
      out[k] ??= { hotels: 0, crew: 0 };
      out[k].hotels++;
      out[k].crew += crewCount(h.days[k]);
    }
  }
  return out;
});
const grand = computed(() => hotels.value.reduce((g, h) => {
  const s = summary(h);
  return { active: g.active + s.active, peak: Math.max(g.peak, s.peak), teams: g.teams + h.teams.length };
}, { active: 0, peak: 0, teams: 0 }));

// The same supervisor at two hotels on one day.
const supervisorAt = computed(() => {
  const map = {};
  for (const h of hotels.value) {
    for (const [k, day] of Object.entries(h.days ?? {})) {
      if (day.supervisor_id && day.teams.length) (map[`${day.supervisor_id}|${k}`] ??= []).push(h.name);
    }
  }
  return map;
});
function supervisorClash(h, d) {
  const id = dayOf(h, d)?.supervisor_id;
  const list = id ? supervisorAt.value[`${id}|${d.date}`] ?? [] : [];
  return list.length > 1 ? list.filter((n) => n !== h.name).join(', ') : null;
}

const teamTitle = (t) => [
  `${t.name} vs ${t.opponent}`,
  t.match_number,
  t.kick_off ? `KO ${t.kick_off} · report ${t.report} · shift ends ${t.end}` : 'No kick-off time yet',
  t.venue,
].filter(Boolean).join('\n');

const hasRun = (run) => !!(run?.crews?.length || run?.time);
// "Crew 1" / "Crew 1, 2": crew teams are numbered per hotel and day, like the Job list.
const crewText = (list) => (list?.length ? `Crew ${list.join(', ')}` : '');
const runLabel = (run) => (hasRun(run) ? [crewText(run.crews), run.time ? `@ ${run.time}` : ''].filter(Boolean).join(' ') : '');
// Departure goes with the first team to report, arrival with the last (as on the planners' sheet).
function suggestion(day, kind) {
  const t = kind === 'departure' ? day.teams[0] : day.teams[day.teams.length - 1];
  return [t?.crew ? `Crew ${t.crew}` : null, t?.report ? `@ ${t.report}` : null].filter(Boolean).join(' ') || 'add';
}
const vsaOf = (d) => vsa.value[d.date] ?? [];

// ── Saving ─────────────────────────────────────────────────────────────────
// Saves run one after another: a new Inertia visit would cancel one still in flight.
const busy = computed(() => pending.value > 0);
let queue = Promise.resolve();
function send(method, url, data, done) {
  pending.value++;
  queue = queue.then(() => new Promise((resolve) => {
    router[method](url, data, {
      preserveScroll: true,
      preserveState: true,
      only: ['hotelSchedule', 'flash'],
      onSuccess: () => done?.(),
      onError: (errors) => {
        toast.error(Object.values(errors)[0] ?? 'Could not save.');
      },
      onFinish: () => {
        pending.value--;
        if (!pending.value) syncLocal();
        resolve();
      },
    });
  }));
}

function saveDay(h, d, data, done) {
  send('put', '/duty-roster/hotels/day', { hotel: h.key, date: d.date, ...data }, done);
}
function saveCrew(t) {
  send('put', '/duty-roster/hotels/crew', { match_id: t.match_id, team_id: t.team_id, crew_team: t.crew });
}
function fillSupervisor(h, event) {
  const id = Number(event.target.value);
  event.target.value = '';
  if (!id) return;
  const dates = activeDates(h).filter((k) => !h.days[k].supervisor_id);
  if (!dates.length) {
    toast.info('Every day at this hotel already has a supervisor.');
    return;
  }
  dates.forEach((k) => {
    h.days[k].supervisor_id = id;
    saveDay(h, { date: k }, { supervisor_id: id });
  });
}
function autoFill() {
  send('post', '/duty-roster/hotels/auto-fill', {});
}

// ── Kit run / VSA editor ───────────────────────────────────────────────────
const editor = ref(null);
const editorNumbers = computed(() => crewNumbers.value);
let anchorEl = null;
function placeAt(el) {
  const r = el.getBoundingClientRect();
  const width = 280;
  return { x: Math.max(8, Math.min(r.left, window.innerWidth - width - 12)), y: Math.min(r.bottom + 4, window.innerHeight - 320) };
}
function place(event) {
  anchorEl = event.currentTarget;
  return placeAt(anchorEl);
}
function openRun(h, d, kind, event) {
  const day = dayOf(h, d);
  const run = day[kind];
  const first = kind === 'departure' ? day.teams[0] : day.teams[day.teams.length - 1];
  editor.value = {
    kind, hotel: h, date: d.date,
    title: RUNS.find((r) => r.key === kind).label,
    sub: `${h.name} · ${fmt(d.date, { weekday: 'short', day: 'numeric', month: 'short' })}`,
    crews: hasRun(run) ? [...run.crews] : (first?.crew ? [first.crew] : []),
    time: hasRun(run) ? (run.time ?? '') : (first?.report ?? ''),
    used: day.teams.map((t) => t.crew).filter(Boolean),
    error: null,
    ...place(event),
  };
}
function openVsa(d, event) {
  editor.value = {
    kind: 'vsa', date: d.date,
    title: 'Aspire VSA team',
    sub: fmt(d.date, { weekday: 'short', day: 'numeric', month: 'short' }),
    crews: [...vsaOf(d)], time: '', used: [], error: null,
    ...place(event),
  };
}
function toggleCrew(n) {
  const list = editor.value.crews;
  editor.value.crews = list.includes(n) ? list.filter((c) => c !== n) : [...list, n].sort((a, b) => a - b);
}
function normaliseTime(value) {
  const m = String(value ?? '').trim().match(/^(\d{1,2}):?(\d{2})$/);
  if (!m || +m[1] > 23 || +m[2] > 59) return null;
  return `${m[1].padStart(2, '0')}:${m[2]}`;
}
function saveEditor(clear = false) {
  const e = editor.value;
  if (e.kind === 'vsa') {
    const crews = clear ? [] : e.crews;
    local.value.vsa = { ...local.value.vsa, [e.date]: crews };
    send('put', '/duty-roster/hotels/vsa', { date: e.date, crews });
    editor.value = null;
    return;
  }
  const time = clear || !e.time.trim() ? null : normaliseTime(e.time);
  if (!clear && e.time.trim() && !time) {
    e.error = 'Type a 24-hour time, e.g. 12:30.';
    return;
  }
  const run = clear ? { crews: [], time: null } : { crews: e.crews, time };
  e.hotel.days[e.date][e.kind] = run;
  saveDay(e.hotel, { date: e.date }, { [e.kind]: run });
  editor.value = null;
}
const clearEditor = () => saveEditor(true);

function onDocMouseDown() { editor.value = null; }
function onKey(e) { if (e.key === 'Escape') editor.value = null; }
// The editor is fixed to the viewport, so it follows its cell when the grid or page scrolls.
function onScroll() {
  if (editor.value && anchorEl?.isConnected) Object.assign(editor.value, placeAt(anchorEl));
}
onMounted(() => {
  document.addEventListener('mousedown', onDocMouseDown);
  document.addEventListener('keydown', onKey);
  document.addEventListener('scroll', onScroll, true);
});
onUnmounted(() => {
  document.removeEventListener('mousedown', onDocMouseDown);
  document.removeEventListener('keydown', onKey);
  document.removeEventListener('scroll', onScroll, true);
});

// ── Offsets ────────────────────────────────────────────────────────────────
const offsetsOpen = ref(false);
const offsetDraft = ref({ report_before: '', end_after: '', error: null });
const asHm = (m) => `${Math.floor(m / 60)}:${String(m % 60).padStart(2, '0')}`;
function openOffsets() {
  offsetDraft.value = { report_before: asHm(offsets.value.report_before), end_after: asHm(offsets.value.end_after), error: null };
  offsetsOpen.value = true;
}
function minutesOf(value) {
  const m = String(value ?? '').trim().match(/^(\d{1,2})(?::(\d{2}))?$/);
  if (!m || (m[2] && +m[2] > 59)) return null;
  return +m[1] * 60 + (+m[2] || 0);
}
function saveOffsets() {
  const report = minutesOf(offsetDraft.value.report_before);
  const end = minutesOf(offsetDraft.value.end_after);
  if (report === null || end === null || report > 720 || end > 720) {
    offsetDraft.value.error = 'Use hours and minutes up to 12:00, e.g. 4:30.';
    return;
  }
  send('put', '/duty-roster/hotels/offsets', { report_before: report, end_after: end }, () => { offsetsOpen.value = false; });
}
</script>

<style scoped>
.hs { display: flex; flex-direction: column; gap: 12px; }
.hs-loading { padding: 40px; text-align: center; color: var(--ink3); font-size: 13px; }
.hs-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.hs-btn:hover { background: var(--panel); }
.hs-btn:disabled { opacity: 0.45; pointer-events: none; }
.hs-btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.hs-btn--primary:hover { background: color-mix(in srgb, var(--accent) 88%, #000); }
.hs-btn--sm { padding: 5px 10px; font-size: 12px; }
.hs-frame { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.hs-empty {
  display: flex; flex-direction: column; align-items: center; gap: 10px; padding: 56px 24px;
  color: var(--ink3); font-size: 13px; background: var(--surface); border: 1px dashed var(--border); border-radius: 12px;
}
.hs-empty p { margin: 0; }

.hs-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; }
.hs-legend { display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--ink2); }
.hs-legend-item { display: inline-flex; align-items: center; gap: 6px; }
.hs-swatch { width: 14px; height: 14px; border-radius: 4px; border: 1px solid var(--tone-border); background: var(--tone-bg); }
.hs-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.hs-rule { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 8px; background: var(--panel); font-size: 12.5px; color: var(--ink2); }
.hs-rule b { color: var(--ink); font-family: var(--font-mono, monospace); font-weight: 600; }
.hs-link { border: 0; background: none; padding: 0; color: var(--accent); font: inherit; font-weight: 600; cursor: pointer; }
.hs-notes { display: flex; flex-direction: column; gap: 4px; padding: 8px 12px; border-radius: 8px; background: var(--warn-soft); color: var(--warn); font-size: 12.5px; }

/* Team-count tones (1 / 2 / 3 / 4+), after the planners' legend, in theme-friendly tints. */
.hs-tone-1 { --tone: #3b82f6; }
.hs-tone-2 { --tone: #16a34a; }
.hs-tone-3 { --tone: #7c3aed; }
.hs-tone-4 { --tone: #db2777; }
[class*='hs-tone-'] { --tone-bg: color-mix(in srgb, var(--tone) 14%, var(--surface)); --tone-border: color-mix(in srgb, var(--tone) 45%, transparent); }

.hs-scroll { overflow: auto; max-height: calc(100vh - 290px); }
.hs-grid { border-collapse: separate; border-spacing: 0; font-size: 12px; min-width: 100%; }
.hs-grid th, .hs-grid td { border-bottom: 1px solid var(--border); }
.hs-grid thead th { position: sticky; z-index: 3; background: var(--panel); color: var(--ink3); font-weight: 600; }
.hs-grid thead tr:first-child th { top: 0; }
.hs-grid thead tr:nth-child(2) th { top: 26px; }
.hs-sticky { position: sticky; left: 0; z-index: 2; background: var(--surface); }
.hs-grid thead .hs-sticky { z-index: 4; }
.hs-c-hotel { min-width: 230px; max-width: 230px; padding: 8px 12px; text-align: left; border-right: 1px solid var(--border); box-shadow: 4px 0 6px -4px rgba(0, 0, 0, .12); vertical-align: top; }
.hs-c-sum { min-width: 58px; padding: 6px; text-align: center; border-left: 1px solid var(--border); font-size: 10.5px; text-transform: uppercase; letter-spacing: .04em; }

.hs-band-cell { height: 26px; padding: 0 8px; font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--accent) !important; border-left: 1px solid var(--border); white-space: nowrap; }
.hs-band-cell--rest { color: var(--ink4) !important; }
.hs-day { min-width: 150px; padding: 4px 6px 6px; border-left: 1px solid var(--border); text-align: center; white-space: nowrap; }
.hs-day-dow { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; margin-right: 4px; }
.hs-day-num { font-size: 15px; font-weight: 700; color: var(--ink); margin-right: 3px; }
.hs-day-mon { font-size: 10.5px; }
.hs-day-tbd { display: block; font-size: 10px; color: var(--warn); }
.hs-day--rest { min-width: 44px; background: color-mix(in srgb, var(--ink) 5%, var(--panel)) !important; }
.hs-day--rest .hs-day-num { color: var(--ink3); }
.hs-day--today { box-shadow: inset 0 -3px 0 var(--accent); }

.hs-hotel-name { display: flex; gap: 8px; font-weight: 700; font-size: 12.5px; color: var(--ink); line-height: 1.25; }
.hs-hotel-num { flex: none; min-width: 20px; height: 20px; border-radius: 6px; background: var(--accent); color: #fff; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; }
.hs-hotel-teams { margin: 4px 0 0 28px; font-family: var(--font-mono, monospace); font-size: 10.5px; color: var(--ink3); white-space: normal; }
.hs-fill { margin: 6px 0 0 28px; width: calc(100% - 28px); height: 26px; border: 1px dashed var(--border); border-radius: 6px; background: var(--surface); color: var(--ink3); font: inherit; font-size: 11.5px; }

.hs-cell { padding: 4px; border-left: 1px solid var(--border); vertical-align: top; }
.hs-cell--rest { background: color-mix(in srgb, var(--ink) 4%, var(--surface)); text-align: center; }
.hs-rest { display: inline-block; margin-top: 8px; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: var(--ink4); writing-mode: vertical-rl; }
.hs-card { display: flex; flex-direction: column; gap: 3px; padding: 6px; border-radius: 8px; background: var(--tone-bg); border: 1px solid var(--tone-border); }
.hs-card-head { display: flex; justify-content: space-between; gap: 6px; font-size: 10.5px; font-weight: 700; color: color-mix(in srgb, var(--tone) 70%, var(--ink)); text-transform: uppercase; letter-spacing: .03em; }
.hs-sup, .hs-crew {
  border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--ink); font: inherit; font-size: 11.5px;
}
.hs-sup { width: 100%; height: 26px; padding: 0 4px; }
.hs-sup--empty { color: var(--ink3); border-style: dashed; }
.hs-sup--clash { border-color: var(--danger); box-shadow: 0 0 0 2px color-mix(in srgb, var(--danger) 20%, transparent); }
.hs-team { display: grid; grid-template-columns: 1fr auto 72px; align-items: center; gap: 4px; }
.hs-team-code { font-weight: 700; color: var(--ink); }
.hs-team-time { font-family: var(--font-mono, monospace); color: var(--ink2); }
.hs-crew { height: 22px; padding: 0 2px; }
.hs-crew--empty { color: var(--ink3); border-style: dashed; }
.hs-sup:disabled, .hs-crew:disabled { opacity: 1; appearance: none; border-color: transparent; background: transparent; }

.hs-run-label { font-size: 11px; font-weight: 500; color: var(--ink3); padding: 4px 12px 4px 40px; }
.hs-cell--run { padding: 2px 4px; }
.hs-run-btn { width: 100%; min-height: 24px; padding: 2px 6px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--ink); font: inherit; font-size: 11.5px; font-family: var(--font-mono, monospace); cursor: pointer; text-align: left; }
.hs-run-btn:hover { border-color: var(--accent); }
.hs-run-btn--empty { border-style: dashed; color: var(--ink4); }
.hs-run-text { font-family: var(--font-mono, monospace); font-size: 11.5px; color: var(--ink2); }

.hs-sum-val { font-size: 15px; font-weight: 700; color: var(--ink); text-transform: none; vertical-align: middle; background: color-mix(in srgb, var(--warn) 8%, var(--surface)); }
.hs-sum-peak { background: color-mix(in srgb, var(--warn) 18%, var(--surface)); }
.hs-hotel:not(:last-of-type) tr:last-child > * { border-bottom: 2px solid var(--border); }

.hs-grid tfoot .hs-cell, .hs-grid tfoot th { background: var(--panel); }
.hs-total .hs-cell { font-size: 11.5px; font-weight: 600; color: var(--ink); text-align: center; }
.hs-total th, .hs-vsa th { font-weight: 700; color: var(--ink); }
.hs-vsa th { color: #15803d; }

.hs-pop { position: fixed; z-index: 60; width: 280px; padding: 12px; border-radius: 10px; background: var(--surface); border: 1px solid var(--border); box-shadow: 0 12px 32px rgba(17, 24, 39, .18); display: flex; flex-direction: column; gap: 6px; }
.hs-pop-title { font-size: 13px; font-weight: 700; color: var(--ink); }
.hs-pop-sub { font-size: 11.5px; color: var(--ink3); margin-top: -4px; }
.hs-pop-label { font-size: 11px; font-weight: 600; color: var(--ink2); margin-top: 4px; }
.hs-pop-crews { display: grid; grid-template-columns: repeat(6, 1fr); gap: 4px; }
.hs-pop-crew { height: 26px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--ink2); font: inherit; font-size: 11px; cursor: pointer; }
.hs-pop-crew--used { border-color: color-mix(in srgb, var(--accent) 40%, var(--border)); }
.hs-pop-crew--on { background: var(--accent); border-color: var(--accent); color: #fff; }
.hs-pop-time { height: 32px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink); font-family: var(--font-mono, monospace); font-size: 14px; }
.hs-pop-error { margin: 0; font-size: 11.5px; color: var(--danger); }
.hs-pop-actions { display: flex; align-items: center; gap: 6px; margin-top: 6px; }
.hs-pop-space { flex: 1; }

.hs-modal { padding: 20px; display: flex; flex-direction: column; gap: 10px; }
.hs-modal h3 { margin: 0; font-size: 16px; color: var(--ink); }
.hs-modal-sub { margin: 0; font-size: 12.5px; color: var(--ink3); }
.hs-modal-field { display: flex; align-items: center; justify-content: space-between; gap: 12px; font-size: 13px; color: var(--ink2); }
.hs-modal-field input { width: 90px; height: 34px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink); font-family: var(--font-mono, monospace); }
</style>
