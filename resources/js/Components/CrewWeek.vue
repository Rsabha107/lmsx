<template>
  <div class="wk">
    <section class="wk-card">
      <div class="wk-inner">
        <div class="wk-row wk-head">
          <div class="wk-info wk-info--head">{{ role.singular }} · {{ role.partner }}</div>
          <div v-for="d in days" :key="d.key" class="wk-day" :style="{ background: d.key === selectedDate ? 'var(--panel)' : 'var(--surface)' }">{{ d.label }}</div>
          <div class="wk-info--head wk-total-head">Week</div>
        </div>

        <div v-if="open.total" class="wk-row">
          <div class="wk-info" style="background: var(--warn-soft)">
            <span class="wk-name" style="color: var(--warn)">Needs {{ role.singular.toLowerCase() }}</span>
            <span class="wk-partner">{{ open.total }} movement{{ open.total === 1 ? '' : 's' }} this week</span>
          </div>
          <button v-for="d in days" :key="d.key" type="button" class="wk-cell" @click="emit('pick', d.key)">
            <template v-if="open.byDay[d.key]">
              <strong class="wk-dur" style="color: var(--warn)">{{ open.byDay[d.key] }}</strong>
              <span class="wk-muted">to assign</span>
            </template>
            <span v-else class="wk-muted">—</span>
          </button>
          <div class="wk-total"><strong>{{ open.total }}</strong><span>to assign</span></div>
        </div>

        <div v-for="r in rows" :key="r.id" class="wk-row">
          <div class="wk-info">
            <span class="wk-name">{{ r.name }}</span>
            <span class="wk-partner">{{ r.partner }}</span>
          </div>
          <button v-for="c in r.cells" :key="c.key" type="button" class="wk-cell"
            :class="{ 'wk-cell--off': !c.on }" :style="c.on && c.conflicts ? { background: 'var(--danger-soft)' } : null"
            @click="emit('pick', c.key)">
            <span v-if="!c.on" class="wk-off">Off</span>
            <template v-else>
              <span class="wk-range">{{ c.range }}</span>
              <span class="wk-line"><strong class="wk-dur">{{ c.dur }}</strong><span class="wk-muted">{{ c.jobs }} job{{ c.jobs === 1 ? '' : 's' }}</span></span>
              <span class="wk-util"><span :style="{ width: c.util + '%' }" /></span>
              <span class="wk-line wk-small">
                <span>{{ c.util }}% busy</span>
                <span v-if="c.conflicts" class="wk-conf">{{ c.conflicts }} clash{{ c.conflicts > 1 ? 'es' : '' }}</span>
              </span>
            </template>
          </button>
          <div class="wk-total"><strong>{{ r.total }}</strong><span>{{ r.jobs }} job{{ r.jobs === 1 ? '' : 's' }}</span></div>
        </div>
      </div>
    </section>
    <p class="wk-note">Click any cell to open that day's timeline. Busy % = time on movements ÷ time from first pickup to last drop-off.</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { ROLES, minutesFrom, clockLabel, duration, mergedMinutes } from '../Composables/useCrewRoster';

const props = defineProps({
  week: { type: Object, default: () => ({ start: null, slots: [] }) },
  tab: { type: String, default: 'driver' },
  resources: { type: Array, default: () => [] },
  selectedDate: { type: String, default: null },
});

const emit = defineEmits(['pick']);

const role = computed(() => ROLES[props.tab]);

const days = computed(() => {
  if (!props.week.start) return [];
  const [y, m, d] = props.week.start.split('-').map(Number);
  return Array.from({ length: 7 }, (_, i) => {
    const date = new Date(y, m - 1, d + i);
    return {
      key: `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`,
      label: date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' }).replace(',', ''),
    };
  });
});

const open = computed(() => {
  const out = { total: 0, byDay: {} };
  for (const s of props.week.slots ?? []) {
    if (s[role.value.idField]) continue;
    out.total++;
    out.byDay[s.date] = (out.byDay[s.date] ?? 0) + 1;
  }
  return out;
});

const rows = computed(() => {
  const r = role.value;
  const partnerOf = (s) => (r.partnerField === 'team_code' ? null : s[r.partnerField]);
  const byId = new Map(props.resources.map((res) => [res.id, { id: res.id, name: r.label(res), fallback: r.describe(res), slots: [], partners: new Set() }]));

  for (const s of props.week.slots ?? []) {
    const id = s[r.idField];
    if (!id) continue;
    if (!byId.has(id)) byId.set(id, { id, name: s[r.nameField] ?? `#${id}`, fallback: '', slots: [], partners: new Set() });
    const row = byId.get(id);
    row.slots.push(s);
    if (partnerOf(s)) row.partners.add(partnerOf(s));
  }

  return [...byId.values()].map((row) => {
    let total = 0;
    const cells = days.value.map((d) => {
      const list = row.slots.filter((s) => s.date === d.key);
      const spans = list.flatMap((s) => {
        const a = minutesFrom(d.key, s.start);
        if (a == null) return [];
        return [[a, Math.max(minutesFrom(d.key, s.end) ?? a + 30, a + 15)]];
      });
      if (!spans.length) return { key: d.key, on: false };
      const first = Math.min(...spans.map((x) => x[0]));
      const last = Math.max(...spans.map((x) => x[1]));
      total += last - first;
      return {
        key: d.key,
        on: true,
        range: `${clockLabel(first)}–${clockLabel(last)}`,
        dur: duration(last - first),
        jobs: list.length,
        util: Math.round((mergedMinutes(spans) / Math.max(last - first, 1)) * 100),
        conflicts: list.filter((s) => (s.clashes ?? []).includes(r.clashKey)).length,
      };
    });
    return {
      id: row.id,
      name: row.name,
      partner: [...row.partners].join(' · ') || row.fallback || '—',
      cells,
      minutes: total,
      total: total ? duration(total) : '—',
      jobs: row.slots.length,
    };
  }).sort((a, b) => b.minutes - a.minutes || a.name.localeCompare(b.name));
});
</script>

<style scoped>
.wk { display: flex; flex-direction: column; gap: 10px; font-variant-numeric: tabular-nums; }
.wk-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: auto; max-height: 72vh; }
.wk-inner { min-width: 1100px; }
.wk-row { display: grid; grid-template-columns: 240px repeat(7, minmax(120px, 1fr)) 110px; border-bottom: 1px solid var(--border); }
.wk-head { position: sticky; top: 0; z-index: 2; background: var(--surface); }

.wk-info { padding: 12px 16px; border-right: 1px solid var(--border); display: flex; flex-direction: column; gap: 2px; justify-content: center; min-width: 0; }
.wk-info--head {
  font-family: var(--font-mono, monospace); font-size: 12px; font-weight: 600;
  letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3);
}
.wk-total-head { padding: 12px; }
.wk-day { padding: 12px; font-size: 13px; font-weight: 600; color: var(--ink); border-right: 1px solid var(--border); }
.wk-name { font-size: 14px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.wk-partner { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.wk-cell {
  cursor: pointer; border: 0; border-right: 1px solid var(--border); text-align: left; padding: 10px 12px;
  display: flex; flex-direction: column; gap: 5px; color: var(--ink); background: var(--surface); font: inherit;
}
.wk-cell:hover { filter: brightness(0.97); }
.wk-cell--off { background: repeating-linear-gradient(135deg, var(--panel) 0 6px, var(--border) 6px 7px); }
.wk-off { font-size: 12px; font-weight: 600; color: var(--ink3); }
.wk-range { font-family: var(--font-mono, monospace); font-size: 12px; font-weight: 500; color: var(--ink2); }
.wk-line { display: flex; justify-content: space-between; align-items: baseline; gap: 6px; }
.wk-dur { font-family: var(--font-mono, monospace); font-size: 15px; font-weight: 600; }
.wk-muted { font-size: 12px; color: var(--ink3); }
.wk-small { font-size: 11px; color: var(--ink3); }
.wk-util { display: block; height: 6px; border-radius: 3px; background: var(--border); overflow: hidden; }
.wk-util span { display: block; height: 100%; background: #1f4e8c; }
.wk-conf { background: #c8322b; color: #fff; font-weight: 600; padding: 0 6px; border-radius: 3px; }

.wk-total { padding: 12px; display: flex; flex-direction: column; justify-content: center; gap: 2px; }
.wk-total strong { font-family: var(--font-mono, monospace); font-size: 15px; font-weight: 600; color: var(--ink); }
.wk-total span { font-size: 12px; color: var(--ink3); }
.wk-note { margin: 0; font-size: 13px; color: var(--ink3); }
</style>
