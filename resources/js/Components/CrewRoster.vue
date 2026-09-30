<template>
  <div class="rst">
    <section class="rst-bar">
      <div class="rst-chips">
        <button v-for="f in chips" :key="f.key" type="button"
          :class="['rst-chip', { 'rst-chip--active': filter === f.key }]" @click="filter = f.key">
          <i class="rst-chip-sw" :style="{ background: f.swatch }" />{{ f.label }}<b>{{ f.count }}</b>
        </button>
      </div>
      <div class="rst-check">
        <span>Status at <strong>{{ clockLabel(checkTime) }}</strong> — click the time ruler to check any time</span>
        <button type="button" class="rst-now-btn" @click="resetNow">Now</button>
      </div>
    </section>

    <section class="rst-card">
      <div class="rst-inner">
        <div class="rst-row rst-head">
          <div class="rst-info rst-info--head">{{ role.singular }} · {{ role.partner }}</div>
          <div class="rst-ruler" @click="onRulerClick">
            <template v-for="h in hours" :key="h.left">
              <div class="rst-tick" :style="{ left: h.left }" />
              <div class="rst-tick-label" :style="{ left: h.left, transform: h.tx }">{{ h.label }}</div>
            </template>
            <div v-if="checkVisible" class="rst-check-flag" :style="{ left: pct(checkTime) }">{{ clockLabel(checkTime) }}</div>
          </div>
        </div>

        <div v-for="r in shown" :key="r.key" class="rst-row">
          <div class="rst-info" :style="{ background: r.infoBg }">
            <div class="rst-line">
              <span class="rst-name">{{ r.name }}</span>
              <span class="rst-st" :style="{ background: r.st.bg, color: r.st.fg }">{{ r.st.label }}</span>
            </div>
            <div class="rst-partner">{{ r.partner }}</div>
            <div class="rst-line rst-duty">
              <span>{{ r.dutyText }}</span>
              <strong>{{ r.durText }}</strong>
            </div>
            <div class="rst-sub" :style="{ color: r.subColor }">{{ r.sub }}</div>
          </div>
          <div class="rst-track" :style="{ height: r.height + 'px' }">
            <div v-if="r.duty" class="rst-duty-band" :style="{ left: pct(r.duty[0]), width: wPct(r.duty[0], r.duty[1]) }" />
            <div v-for="h in hours" :key="h.left" class="rst-gridline" :style="{ left: h.left }" />
            <template v-for="g in r.gaps" :key="g.left">
              <div class="rst-gap" :style="{ left: g.left, width: g.width }" />
              <div v-if="g.showLabel" class="rst-gap-label" :style="{ left: g.left, width: g.width }"><span>{{ g.label }}</span></div>
            </template>
            <button v-for="b in r.bars" :key="b.mv.id" type="button" class="rst-job" :title="b.title"
              :style="{
                left: b.left, width: b.width, top: b.top + 'px', height: BAR_H + 'px',
                background: b.tone.bg, color: b.tone.fg, border: b.tone.border ?? '0',
                outline: b.mv.id === selectedId ? '2px solid var(--ink)' : 'none',
              }"
              @click.stop="emit('select', b.mv)">
              <span class="rst-job-label">{{ b.mv.code }}</span>
              <span v-if="b.showSub" class="rst-job-sub">{{ b.sub }}</span>
            </button>
            <div v-if="checkVisible" class="rst-check-line" :style="{ left: pct(checkTime) }" />
          </div>
        </div>

        <div v-if="!shown.length" class="rst-empty">No {{ role.plural.toLowerCase() }} match this filter.</div>
      </div>
    </section>

    <footer class="rst-legend">
      <span><i class="rst-lg rst-lg--duty" />On duty</span>
      <span><i class="rst-lg rst-lg--off" />Off duty</span>
      <span><i class="rst-lg" :style="{ background: TONES.done.bg }" />Completed</span>
      <span><i class="rst-lg" :style="{ background: TONES.live.bg }" />In progress</span>
      <span><i class="rst-lg" :style="{ background: TONES.scheduled.bg, border: TONES.scheduled.border }" />Scheduled</span>
      <span><i class="rst-lg" :style="{ background: TONES.conflict.bg }" />Overlap conflict</span>
      <span><i class="rst-lg" :style="{ background: TONES.open.bg }" />Needs {{ role.singular.toLowerCase() }}</span>
      <span><i class="rst-lg rst-lg--gap" />Idle gap</span>
    </footer>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ROLES, minutesFrom, clockLabel, duration, todayKey } from '../Composables/useCrewRoster';

const props = defineProps({
  movements: { type: Array, default: () => [] },
  date: { type: String, required: true },
  tab: { type: String, default: 'driver' },
  resources: { type: Array, default: () => [] },
  selectedId: { type: Number, default: null },
  // While searching, people with no matching movement are hidden.
  searching: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'today']);

const BAR_H = 34;
const TONES = {
  conflict: { bg: '#c8322b', fg: '#fff' },
  open: { bg: '#e79a1f', fg: '#2b1a00' },
  done: { bg: '#b9c4d3', fg: '#243247' },
  live: { bg: '#1f4e8c', fg: '#fff' },
  scheduled: { bg: '#d6e2f2', fg: '#173a66', border: '1px solid #9fb7d8' },
};
const STATUS = {
  onjob: { bg: '#1f4e8c', fg: '#fff', label: 'On job' },
  idle: { bg: 'var(--warn-soft)', fg: 'var(--warn)', label: 'Idle' },
  off: { bg: 'var(--panel)', fg: 'var(--ink3)', label: 'Off' },
  conflict: { bg: '#c8322b', fg: '#fff', label: 'Conflict' },
  open: { bg: '#e79a1f', fg: '#2b1a00', label: 'Unassigned' },
};

const role = computed(() => ROLES[props.tab]);

const nowMinutes = ref(0);
const checkTime = ref(0);
const filter = ref('all');
let clock = null;
function readClock() {
  const d = new Date();
  return d.getHours() * 60 + d.getMinutes();
}
onMounted(() => {
  nowMinutes.value = checkTime.value = readClock();
  clock = setInterval(() => { nowMinutes.value = readClock(); }, 60000);
});
onUnmounted(() => clearInterval(clock));

function resetNow() {
  nowMinutes.value = checkTime.value = readClock();
  if (props.date !== todayKey()) emit('today');
}

const spans = computed(() => props.movements.flatMap((mv) => {
  const start = minutesFrom(props.date, mv.span_start);
  if (start == null) return [];
  const end = minutesFrom(props.date, mv.span_end);
  return [{ mv, start, end: Math.max(end ?? start + 30, start + 15) }];
}));

// Same default window as the reference roster (04–24), widened to fit the day.
const range = computed(() => {
  const list = spans.value;
  const first = list.length ? Math.min(...list.map((s) => Math.max(s.start, 0))) : 240;
  const last = list.length ? Math.max(...list.map((s) => s.end)) : 1440;
  return [Math.min(240, Math.floor(first / 60) * 60), Math.max(1440, Math.ceil(last / 60) * 60)];
});

function pct(m) {
  const [rs, re] = range.value;
  return `${(Math.max(0, Math.min(1, (m - rs) / (re - rs))) * 100).toFixed(3)}%`;
}
function wPct(a, b) {
  const [rs, re] = range.value;
  return `${(Math.max(0, (Math.min(b, re) - Math.max(a, rs)) / (re - rs)) * 100).toFixed(3)}%`;
}

const hours = computed(() => {
  const [rs, re] = range.value;
  const out = [];
  for (let m = rs; m <= re; m += 60) {
    out.push({
      left: pct(m),
      label: String((m / 60) % 24).padStart(2, '0'),
      tx: m === rs ? 'translateX(3px)' : m === re ? 'translateX(calc(-100% - 3px))' : 'translateX(-50%)',
    });
  }
  return out;
});

const checkVisible = computed(() => checkTime.value >= range.value[0] && checkTime.value <= range.value[1]);

function onRulerClick(e) {
  const [rs, re] = range.value;
  const box = e.currentTarget.getBoundingClientRect();
  checkTime.value = Math.round((rs + ((e.clientX - box.left) / box.width) * (re - rs)) / 5) * 5;
}

function toneOf(s, clash, open) {
  if (clash) return TONES.conflict;
  if (open) return TONES.open;
  if (s.mv.job_status === 'completed') return TONES.done;
  if (s.mv.job_status === 'in-progress') return TONES.live;
  const today = todayKey();
  if (props.date < today || (props.date === today && s.end <= nowMinutes.value)) return TONES.done;
  if (props.date === today && s.start <= nowMinutes.value) return TONES.live;
  return TONES.scheduled;
}

function statusAt(row, t) {
  if (row.open) return { key: 'open', sub: `${row.bars.length} movement${row.bars.length === 1 ? '' : 's'} to assign` };
  if (!row.duty) return { key: 'off', sub: 'No movements this day' };
  if (t < row.duty[0]) return { key: 'off', sub: `First pickup ${clockLabel(row.duty[0])}` };
  if (t >= row.duty[1]) return { key: 'off', sub: `Finished ${clockLabel(row.duty[1])}` };
  const act = row.items.filter((s) => s.start <= t && t < s.end);
  if (act.length > 1) return { key: 'conflict', sub: `${act.map((s) => s.mv.code).join(' + ')} at once` };
  if (act.length === 1) return { key: 'onjob', sub: `${act[0].mv.code} until ${clockLabel(act[0].end)}` };
  const next = row.items.find((s) => s.start > t);
  return { key: 'idle', sub: next ? `Next ${next.mv.code} in ${duration(next.start - t)}` : 'No more movements' };
}

function build(row) {
  const r = role.value;
  const items = [...row.items].sort((a, b) => a.start - b.start);
  const laneEnds = [];
  const lanesOf = items.map((s) => {
    let l = laneEnds.findIndex((e) => e <= Math.max(s.start, 0));
    if (l < 0) { l = laneEnds.length; laneEnds.push(0); }
    laneEnds[l] = s.end;
    return l;
  });
  const lanes = Math.max(1, laneEnds.length);
  const height = Math.max(88, 16 + lanes * (BAR_H + 4));
  const lanePad = (height - lanes * (BAR_H + 4) + 4) / 2;

  const merged = [];
  for (const s of items) {
    const last = merged[merged.length - 1];
    if (last && s.start <= last[1]) last[1] = Math.max(last[1], s.end);
    else merged.push([s.start, s.end]);
  }
  const duty = row.open || !items.length ? null : [merged[0][0], merged[merged.length - 1][1]];
  const gaps = [];
  for (let i = 1; !row.open && i < merged.length; i++) {
    const [a, b] = [merged[i - 1][1], merged[i][0]];
    if (b - a >= 20) gaps.push({ left: pct(a), width: wPct(a, b), label: `idle ${duration(b - a).replace(' ', '')}`, showLabel: b - a >= 60 });
  }

  const bars = items.map((s, i) => {
    const clash = !row.open && (s.mv.clashes?.[r.clashKey] ?? []).length > 0;
    const others = ['driver', 'vehicle', 'supervisor'].filter((k) => k !== props.tab)
      .map((k) => `${ROLES[k].singular}: ${s.mv[ROLES[k].nameField] ?? '—'}`);
    return {
      mv: s.mv,
      clash,
      tone: toneOf(s, clash, row.open),
      left: pct(s.start),
      width: wPct(s.start, s.end),
      top: lanePad + lanesOf[i] * (BAR_H + 4),
      showSub: s.end - s.start >= 60,
      sub: `${clockLabel(s.start)}–${clockLabel(s.end)} · ${s.mv.team_code ?? s.mv.team ?? ''}`,
      title: [
        `${s.mv.code} · ${s.mv.team ?? ''} · ${clockLabel(s.start)}–${clockLabel(s.end)}${s.start < 0 ? ' (from the day before)' : ''}`,
        `${s.mv.from ?? ''} → ${s.mv.to ?? ''}`,
        ...others,
        ...(clash ? s.mv.clashes[r.clashKey] : []),
      ].join('\n'),
    };
  });

  const clashCount = bars.filter((b) => b.clash).length;
  const built = { ...row, items, bars, duty, gaps, height, hasConf: clashCount > 0 };
  const st = statusAt(built, checkTime.value);
  let sub = st.sub;
  if (clashCount && st.key !== 'conflict') sub += ` · ${clashCount} clash${clashCount > 1 ? 'es' : ''} today`;

  return {
    ...built,
    key: row.key,
    stKey: st.key,
    st: STATUS[st.key],
    sub,
    subColor: clashCount || st.key === 'conflict' ? 'var(--danger)' : row.open ? 'var(--warn)' : 'var(--ink3)',
    infoBg: clashCount ? 'var(--danger-soft)' : row.open ? 'var(--warn-soft)' : 'var(--surface)',
    dutyText: duty ? `${clockLabel(duty[0])}–${clockLabel(duty[1])}` : row.open ? 'Needs assigning' : 'Free all day',
    durText: duty ? duration(duty[1] - duty[0]) : '—',
  };
}

const rows = computed(() => {
  const r = role.value;
  const partnerOf = (mv) => (r.partnerField === 'team_code' ? mv.team_code : mv[r.partnerField]);
  const byId = new Map(props.resources.map((res) => [res.id, {
    key: res.id, name: r.label(res), items: [], partners: new Set(), fallback: r.describe(res),
  }]));
  const open = { key: 'open', name: `Needs ${r.singular.toLowerCase()}`, open: true, items: [], partners: new Set(), fallback: '' };

  for (const s of spans.value) {
    const id = s.mv[r.idField];
    if (!id) { open.items.push(s); continue; }
    // Planning may have used someone outside this list; still show their work.
    if (!byId.has(id)) byId.set(id, { key: id, name: s.mv[r.nameField] ?? `#${id}`, items: [], partners: new Set(), fallback: '' });
    const row = byId.get(id);
    row.items.push(s);
    if (partnerOf(s.mv)) row.partners.add(partnerOf(s.mv));
  }

  const people = [...byId.values()]
    .filter((row) => !props.searching || row.items.length)
    .map((row) => build({ ...row, partner: [...row.partners].join(' · ') || row.fallback || '—' }))
    .sort((a, b) => (a.duty ? a.duty[0] : Infinity) - (b.duty ? b.duty[0] : Infinity) || a.name.localeCompare(b.name));

  return {
    people,
    open: open.items.length ? build({ ...open, partner: `${open.items.length} movement${open.items.length === 1 ? '' : 's'} without a ${r.singular.toLowerCase()}` }) : null,
  };
});

const matches = (row, key) => (key === 'all' ? true : key === 'conflict' ? row.hasConf || row.stKey === 'conflict' : row.stKey === key);

const chips = computed(() => {
  const r = role.value;
  const list = [
    ['all', `All ${r.plural.toLowerCase()}`, 'var(--ink)'],
    ['onjob', 'On job', '#1f4e8c'],
    ['idle', 'Idle', '#e0a526'],
    ['off', 'Off', '#b8b5ac'],
    ['conflict', 'Has conflict', '#c8322b'],
  ].map(([key, label, swatch]) => ({ key, label, swatch, count: rows.value.people.filter((row) => matches(row, key)).length }));
  list.push({ key: 'open', label: `Needs ${r.singular.toLowerCase()}`, swatch: '#e79a1f', count: rows.value.open?.bars.length ?? 0 });
  return list;
});

const shown = computed(() => {
  const { people, open } = rows.value;
  if (filter.value === 'open') return open ? [open] : [];
  const list = people.filter((row) => matches(row, filter.value));
  return filter.value === 'all' && open ? [open, ...list] : list;
});
</script>

<style scoped>
.rst { display: flex; flex-direction: column; gap: 14px; font-variant-numeric: tabular-nums; }

.rst-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
.rst-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.rst-chip {
  display: flex; align-items: center; gap: 8px; cursor: pointer;
  border: 1px solid var(--border-strong); background: var(--surface); color: var(--ink);
  border-radius: 999px; padding: 6px 12px; font-size: 13px; font-weight: 500;
}
.rst-chip b { font-family: var(--font-mono, monospace); font-weight: 600; }
.rst-chip--active { background: var(--ink); color: var(--surface); border-color: var(--ink); }
.rst-chip-sw { width: 10px; height: 10px; border-radius: 3px; }
.rst-check { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--ink3); }
.rst-check strong { font-family: var(--font-mono, monospace); color: var(--ink); }
.rst-now-btn {
  cursor: pointer; border: 1px solid var(--border-strong); background: var(--surface);
  border-radius: 6px; padding: 5px 10px; font-size: 12px; font-weight: 500; color: var(--ink);
}

.rst-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: auto; max-height: 72vh; }
.rst-inner { min-width: 1180px; }
.rst-row { display: grid; grid-template-columns: 260px minmax(0, 1fr); border-bottom: 1px solid var(--border); }
.rst-head { position: sticky; top: 0; z-index: 4; background: var(--surface); }

.rst-info {
  position: sticky; left: 0; z-index: 3; padding: 10px 16px; border-right: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 4px; justify-content: center; background: var(--surface);
}
.rst-info--head {
  justify-content: flex-end; font-family: var(--font-mono, monospace); font-size: 12px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3);
}
.rst-line { display: flex; justify-content: space-between; align-items: center; gap: 8px; }
.rst-name { font-size: 14px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rst-st { flex: none; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 4px; }
.rst-partner { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rst-duty { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink2); }
.rst-sub { font-size: 12px; }

.rst-ruler { position: relative; height: 44px; cursor: crosshair; }
.rst-tick { position: absolute; bottom: 0; height: 12px; border-left: 1px solid var(--border-strong); }
.rst-tick-label { position: absolute; bottom: 14px; font-family: var(--font-mono, monospace); font-size: 11px; font-weight: 500; color: var(--ink3); }
.rst-check-flag {
  position: absolute; top: 4px; transform: translateX(-50%); pointer-events: none;
  background: var(--ink); color: var(--surface); font-family: var(--font-mono, monospace);
  font-size: 11px; font-weight: 600; padding: 2px 6px; border-radius: 4px;
}

.rst-track { position: relative; background: repeating-linear-gradient(135deg, var(--panel) 0 6px, var(--border) 6px 7px); }
.rst-duty-band { position: absolute; top: 0; bottom: 0; background: var(--surface); border-left: 2px solid #2f7d4f; border-right: 2px solid #2f7d4f; }
.rst-gridline { position: absolute; top: 0; bottom: 0; border-left: 1px solid color-mix(in srgb, var(--ink) 6%, transparent); pointer-events: none; }
.rst-gap { position: absolute; top: 50%; height: 0; border-top: 2px dashed #d99a1e; pointer-events: none; }
.rst-gap-label { position: absolute; bottom: 2px; display: flex; justify-content: center; pointer-events: none; z-index: 1; }
.rst-gap-label span {
  flex: none; font-family: var(--font-mono, monospace); font-size: 10px; font-weight: 500; white-space: nowrap;
  color: #8a5a00; background: var(--surface); padding: 0 3px; border-radius: 2px;
}
.rst-job {
  position: absolute; z-index: 1; border-radius: 5px; padding: 0 6px; cursor: pointer; outline-offset: 1px;
  display: flex; flex-direction: column; justify-content: center; align-items: flex-start; overflow: hidden; text-align: left;
}
.rst-job:hover { filter: brightness(0.95); }
.rst-job-label, .rst-job-sub { max-width: 100%; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2; }
.rst-job-label { font-family: var(--font-mono, monospace); font-size: 11px; font-weight: 600; }
.rst-job-sub { font-size: 11px; opacity: 0.85; }
.rst-check-line { position: absolute; top: 0; bottom: 0; border-left: 2px solid var(--ink); z-index: 2; pointer-events: none; }
.rst-empty { padding: 32px; text-align: center; color: var(--ink3); font-size: 14px; }

.rst-legend { display: flex; flex-wrap: wrap; gap: 18px; font-size: 12px; color: var(--ink2); align-items: center; }
.rst-legend span { display: flex; align-items: center; gap: 6px; }
.rst-lg { width: 22px; height: 12px; border-radius: 3px; }
.rst-lg--duty { border-radius: 0; background: var(--surface); border-left: 2px solid #2f7d4f; border-right: 2px solid #2f7d4f; outline: 1px solid var(--border); }
.rst-lg--off { border-radius: 0; background: repeating-linear-gradient(135deg, var(--panel) 0 3px, var(--border-strong) 3px 4px); }
.rst-lg--gap { height: 0; border-radius: 0; border-top: 2px dashed #d99a1e; }
</style>
