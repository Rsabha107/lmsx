<template>
  <div class="mt">
    <div class="mt-toolbar">
      <input v-model="search" class="mt-search" type="search" placeholder="Search by name, code or location…" aria-label="Search movement templates" />
      <div class="mt-pills">
        <button v-for="f in typePills" :key="f.value" type="button"
          :class="['mt-pill', { 'mt-pill--on': type === f.value }]" @click="type = f.value">
          {{ f.label }} <span>{{ f.count }}</span>
        </button>
      </div>
    </div>

    <div class="mt-body">
      <div class="mt-table">
        <div class="mt-row mt-head">
          <span>Template</span><span>Route</span><span class="mt-right">Checkpoints</span><span class="mt-right">Time</span><span />
        </div>

        <div v-for="t in rows" :key="t.id" :class="['mt-row', 'mt-item', { 'mt-item--open': selectedId === t.id }]" @click="select(t.id)">
          <div class="mt-name">
            <div class="mt-name-top">
              <span class="mt-name-text">{{ t.name }}</span>
              <span :class="['mt-type', `mt-type--${typeKey(t.scenario_type)}`]">{{ scenarioLabel(t.scenario_type) }}</span>
            </div>
            <span class="mt-code">{{ t.code }}</span>
          </div>
          <div class="mt-route">
            <div class="mt-stops">
              <template v-for="(stop, i) in t.info.stops" :key="i">
                <span v-if="i" class="mt-arrow">→</span><span class="mt-stop">{{ stop }}</span>
              </template>
              <span v-if="!t.info.stops.length" class="mt-muted">No legs yet</span>
            </div>
            <span class="mt-sub">{{ t.info.legLabel }}<template v-if="t.info.transport"> · {{ t.info.transport }}</template></span>
          </div>
          <span class="mt-num">{{ t.info.checkpoints }}</span>
          <span class="mt-num mt-time">{{ t.info.time }}</span>
          <div class="mt-acts" @click.stop>
            <button type="button" class="mt-act" title="Edit" :aria-label="`Edit ${t.name}`" @click="emit('edit', t)">✎</button>
            <button type="button" class="mt-act" title="Duplicate" :aria-label="`Duplicate ${t.name}`" @click="emit('duplicate', t)">⧉</button>
            <button type="button" class="mt-act mt-act--del" title="Delete" :aria-label="`Delete ${t.name}`" @click="emit('delete', t.id)">×</button>
          </div>
        </div>

        <div v-if="!rows.length" class="mt-empty">
          {{ templates.length ? 'No movement templates match your search.' : 'No movement templates yet. Create one with New Movement Template.' }}
        </div>
      </div>

      <!-- Detail panel -->
      <aside v-if="selected" class="mt-panel" aria-label="Movement template">
        <div class="mt-panel-head">
          <div class="mt-panel-top">
            <span class="mt-code mt-code--plain">{{ selected.code }}</span>
            <span class="mt-panel-tools">
              <span :class="['mt-type', `mt-type--${typeKey(selected.scenario_type)}`]">{{ scenarioLabel(selected.scenario_type) }}</span>
              <button type="button" class="mt-x" aria-label="Close" @click="selectedId = null">×</button>
            </span>
          </div>
          <div class="mt-panel-name">{{ selected.name }}</div>
          <div v-if="selected.info.stops.length" class="mt-sub">{{ selected.info.stops.join(' → ') }}</div>
          <div class="mt-stats">
            <div><span>Legs</span><strong>{{ selected.legs?.length || 0 }}</strong></div>
            <div><span>Checkpoints</span><strong>{{ selected.info.checkpoints }}</strong></div>
            <div><span>Est. time</span><strong>{{ selected.info.time }}</strong></div>
          </div>
        </div>

        <div class="mt-legs">
          <div class="mt-legs-title">Legs</div>
          <p v-if="!selected.legs?.length" class="mt-muted">No legs yet. Use Edit template to add them.</p>
          <div v-for="(leg, i) in selected.legs" :key="leg.id ?? i" class="mt-leg">
            <div class="mt-rail">
              <span class="mt-rail-dot">{{ i + 1 }}</span>
              <span :class="['mt-rail-line', { 'mt-rail-line--end': i === selected.legs.length - 1 }]" />
            </div>
            <div class="mt-leg-main">
              <span class="mt-leg-route">{{ leg.from_location || 'Start' }} → {{ leg.to_location || 'End' }}</span>
              <div v-if="ctOf(leg)" :class="['mt-ct', { 'mt-ct--open': openLeg === i }]">
                <button type="button" class="mt-ct-head" :aria-expanded="openLeg === i" @click="openLeg = openLeg === i ? null : i">
                  <span class="mt-ct-text">
                    <span class="mt-ct-name">{{ ctOf(leg).name }}</span>
                    <small><span class="mt-mono">{{ ctOf(leg).code }}</span> · {{ stepsOf(leg).length }} checkpoint{{ stepsOf(leg).length === 1 ? '' : 's' }}</small>
                  </span>
                  <span class="mt-chev" aria-hidden="true">{{ openLeg === i ? '▴' : '▾' }}</span>
                </button>
                <template v-if="openLeg === i">
                  <ol class="mt-steps">
                    <li v-for="(step, k) in stepsOf(leg)" :key="step.id" class="mt-step">
                      <div class="mt-rail mt-rail--sm">
                        <span :class="['mt-step-dot', { 'mt-step-dot--opt': !step.required }]">{{ k + 1 }}</span>
                        <span :class="['mt-rail-line', { 'mt-rail-line--end': k === stepsOf(leg).length - 1 }]" />
                      </div>
                      <div class="mt-step-main">
                        <span class="mt-step-name">{{ step.name }}</span>
                        <small><span class="mt-mono">{{ step.code }}</span> · {{ step.required ? 'Required' : 'Optional' }}</small>
                      </div>
                      <span class="mt-clock">{{ step.clock }}</span>
                    </li>
                    <li v-if="!stepsOf(leg).length" class="mt-muted">No checkpoints in this template.</li>
                  </ol>
                  <button type="button" class="mt-ct-link" @click="emit('open-checkpoint-template', ctOf(leg))">Open in Checkpoint Templates →</button>
                </template>
              </div>
              <span v-else class="mt-muted">No checkpoint template</span>
              <span v-if="leg.transport_type" class="mt-sub">{{ transportLabel(leg.transport_type) }}</span>
            </div>
            <span class="mt-leg-time">{{ fmtMinutes(legMinutes(selected, leg)) }}</span>
          </div>
          <p v-if="selected.description" class="mt-desc">{{ selected.description }}</p>
        </div>

        <div class="mt-panel-foot">
          <span class="mt-sub">{{ usedLabel(selected) }}</span>
          <button type="button" class="mt-btn" @click="emit('edit', selected)">Edit template</button>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { legMinutes } from '../../Composables/planFormat';

// The event's movement templates: list with route and totals, and a read-only detail panel.
// New / edit / delete stay with the page's dialogs.
const props = defineProps({
  templates: { type: Array, default: () => [] },
  checkpointTemplates: { type: Array, default: () => [] },
});
const emit = defineEmits(['edit', 'duplicate', 'delete', 'open-checkpoint-template']);

const SCENARIOS = {
  arrival_day: ['Arrival', 'arrival'],
  departure_day: ['Departure', 'departure'],
  match_day: ['Match', 'match'],
  training_day: ['Training', 'training'],
  operation_day: ['Operations', 'other'],
  full_day: ['Full day', 'other'],
  custom: ['Custom', 'other'],
};
const scenarioLabel = (s) => SCENARIOS[s]?.[0] ?? (s || '—').replace(/_/g, ' ');
const typeKey = (s) => SCENARIOS[s]?.[1] ?? 'other';
const transportLabel = (t) => (t ? t[0].toUpperCase() + t.slice(1).replace(/_/g, ' ') : '');

function fmtMinutes(m) {
  if (!m) return '—';
  const h = Math.floor(m / 60);
  const r = m % 60;
  return h ? `${h}h${r ? ` ${r}m` : ''}` : `${r}m`;
}

const ctById = computed(() => Object.fromEntries(props.checkpointTemplates.map((t) => [t.id, t])));
const ctOf = (leg) => ctById.value[leg.checkpoint_template_id] ?? null;
const stepCount = (leg) => ctOf(leg)?.checkpoint_count ?? ctOf(leg)?.checkpoints?.length ?? 0;
// A step's clock is its running total of estimated minutes within the leg ("T+25").
function stepsOf(leg) {
  let clock = 0;
  let known = true;
  return (ctOf(leg)?.checkpoints ?? []).map((cp) => {
    const mins = cp.pivot?.estimated_minutes;
    if (mins == null) known = false;
    else clock += Number(mins);
    return { id: cp.id, name: cp.name, code: cp.code, required: cp.pivot?.is_required !== false && cp.pivot?.is_required !== 0, clock: known && mins != null ? `T+${clock}` : '' };
  });
}

function infoOf(t) {
  const legs = t.legs ?? [];
  const stops = legs.length ? [legs[0].from_location || 'Start', ...legs.map((l) => l.to_location || 'End')] : [];
  const minutes = legs.length ? legs.reduce((sum, leg) => sum + legMinutes(t, leg), 0) : Number(t.estimated_duration_minutes) || 0;
  return {
    stops,
    legLabel: `${legs.length} leg${legs.length === 1 ? '' : 's'}`,
    transport: [...new Set(legs.map((l) => transportLabel(l.transport_type)).filter(Boolean))].join(', '),
    checkpoints: legs.reduce((sum, leg) => sum + stepCount(leg), 0),
    time: fmtMinutes(minutes),
  };
}

const usedLabel = (t) => {
  if (!t.plans_count) return 'Not used in any plan yet';
  const plans = `${t.plans_count} plan${t.plans_count === 1 ? '' : 's'}`;
  return t.movements_count ? `Used in ${plans} · ${t.movements_count} movement${t.movements_count === 1 ? '' : 's'}` : `Used in ${plans}`;
};

const search = ref('');
const type = ref('all');
const typePills = computed(() => {
  const keys = [...new Set(props.templates.map((t) => t.scenario_type))];
  const order = Object.keys(SCENARIOS);
  keys.sort((a, b) => (order.indexOf(a) + 1 || 99) - (order.indexOf(b) + 1 || 99));
  return [
    { value: 'all', label: 'All', count: props.templates.length },
    ...keys.map((k) => ({ value: k, label: scenarioLabel(k), count: props.templates.filter((t) => t.scenario_type === k).length })),
  ];
});

const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.templates
    .filter((t) => (type.value === 'all' || t.scenario_type === type.value)
      && (!q || [t.name, t.code, ...(t.legs ?? []).flatMap((l) => [l.from_location, l.to_location])]
        .some((v) => (v || '').toLowerCase().includes(q))))
    .map((t) => ({ ...t, info: infoOf(t) }));
});

const selectedId = ref(null);
const openLeg = ref(null);
const selected = computed(() => {
  const t = props.templates.find((x) => x.id === selectedId.value);
  return t ? { ...t, info: infoOf(t) } : null;
});
function select(id) {
  if (selectedId.value !== id) openLeg.value = null;
  selectedId.value = id;
}
// A filter that hides the open template closes its panel.
watch(rows, (list) => {
  if (selectedId.value && !list.some((t) => t.id === selectedId.value)) selectedId.value = null;
});
defineExpose({ select });
</script>

<style scoped>
.mt { display: flex; flex-direction: column; gap: 14px; }
.mt-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mt-search {
  flex: 0 1 320px; min-width: 220px; height: 36px; padding: 0 12px; border: 1px solid var(--border-strong);
  border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 13px;
}
.mt-search:focus { outline: none; border-color: var(--ink); }
.mt-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.mt-pill {
  height: 30px; padding: 0 11px; border-radius: 999px; border: 1px solid var(--border-strong);
  background: var(--surface); color: var(--ink2); font-size: 12px; font-weight: 500; cursor: pointer;
}
.mt-pill span { opacity: 0.6; margin-left: 2px; }
.mt-pill:hover { background: var(--panel); color: var(--ink); }
.mt-pill--on, .mt-pill--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }

.mt-body { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
.mt-table { flex: 1 1 600px; min-width: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface); }
.mt-row {
  display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1.6fr) 90px 70px 84px;
  gap: 14px; align-items: center; padding: 14px 16px;
}
.mt-head {
  padding: 10px 16px; background: var(--panel); border-bottom: 1px solid var(--border);
  font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3);
}
.mt-right { text-align: right; }
.mt-item { border-bottom: 1px solid var(--border); cursor: pointer; }
.mt-item:last-of-type { border-bottom: none; }
.mt-item:hover { background: var(--panel); }
.mt-item--open, .mt-item--open:hover { background: var(--accent-soft); box-shadow: inset 3px 0 0 var(--accent); }
.mt-name { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.mt-name-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.mt-name-text { font-size: 14px; font-weight: 600; color: var(--ink); }
.mt-code {
  align-self: flex-start; font-family: var(--mono, ui-monospace, monospace); font-size: 11px; color: var(--ink2);
  background: var(--panel); padding: 1px 6px; border-radius: 4px;
}
.mt-item--open .mt-code { background: var(--surface); }
.mt-code--plain { background: none; padding: 0; color: var(--ink3); }
.mt-type {
  --c: var(--ink3);
  font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; white-space: nowrap;
  padding: 2px 7px; border-radius: 999px; color: var(--c); background: color-mix(in srgb, var(--c) 13%, var(--surface));
}
.mt-type--arrival { --c: #047857; }
.mt-type--departure { --c: #be185d; }
.mt-type--match { --c: #7e22ce; }
.mt-type--training { --c: #1d4ed8; }
.mt-route { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.mt-stops { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; color: var(--ink); }
.mt-stop { font-weight: 500; }
.mt-arrow { color: var(--ink4); }
.mt-sub { font-size: 11px; color: var(--ink3); }
.mt-muted { margin: 0; font-size: 12px; color: var(--ink4); }
.mt-num { font-size: 13px; text-align: right; color: var(--ink2); }
.mt-time { font-weight: 500; color: var(--ink); }
.mt-acts { display: flex; gap: 2px; justify-content: flex-end; }
.mt-act { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--ink2); font-size: 13px; cursor: pointer; }
.mt-act:hover { background: var(--panel); }
.mt-act--del { color: var(--danger-strong); font-size: 15px; }
.mt-act--del:hover { background: var(--danger-soft); }
.mt-empty { padding: 32px; text-align: center; font-size: 13px; color: var(--ink3); }

.mt-panel {
  flex: 1 1 340px; max-width: 440px; position: sticky; top: 12px;
  display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface);
}
.mt-panel-head { display: flex; flex-direction: column; gap: 6px; padding: 16px; border-bottom: 1px solid var(--border); }
.mt-panel-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.mt-panel-tools { display: flex; align-items: center; gap: 6px; }
.mt-x { border: 0; background: none; font-size: 18px; line-height: 1; color: var(--ink3); cursor: pointer; }
.mt-panel-name { font-size: 16px; font-weight: 600; color: var(--ink); }
.mt-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; }
.mt-stats div { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 8px; background: var(--panel); }
.mt-stats span { font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--ink3); }
.mt-stats strong { font-size: 16px; font-weight: 600; color: var(--ink); }

.mt-legs { display: flex; flex-direction: column; padding: 14px 16px; }
.mt-legs-title { margin-bottom: 10px; font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); }
.mt-leg { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; gap: 10px; }
.mt-rail { display: flex; flex-direction: column; align-items: center; }
.mt-rail-dot {
  display: flex; align-items: center; justify-content: center; flex: none; width: 22px; height: 22px; border-radius: 50%;
  background: var(--accent); color: #fff; font-size: 10px; font-weight: 600;
}
.mt-rail-line { flex: 1; width: 1.5px; min-height: 10px; background: color-mix(in srgb, var(--accent) 25%, var(--surface)); }
.mt-rail-line--end { background: transparent; }
.mt-leg-main { display: flex; flex-direction: column; gap: 6px; min-width: 0; padding-bottom: 14px; }
.mt-leg-route { font-size: 13px; font-weight: 600; color: var(--ink); }
.mt-leg-time { padding-top: 3px; font-size: 12px; font-weight: 500; color: var(--ink2); }
.mt-ct { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
.mt-ct--open { border-color: var(--border-strong); }
.mt-ct-head {
  display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px;
  border: 0; background: var(--surface); color: var(--ink); text-align: left; cursor: pointer;
}
.mt-ct-head:hover, .mt-ct--open .mt-ct-head { background: var(--panel); }
.mt-ct-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.mt-ct-name { font-size: 12px; font-weight: 500; }
.mt-ct-text small, .mt-step-main small { font-size: 11px; color: var(--ink3); }
.mt-mono { font-family: var(--mono, ui-monospace, monospace); }
.mt-chev { flex: none; font-size: 11px; color: var(--ink3); }
.mt-steps { display: flex; flex-direction: column; margin: 0; padding: 10px 10px 4px; list-style: none; border-top: 1px solid var(--border); }
.mt-step { display: grid; grid-template-columns: 18px minmax(0, 1fr) auto; gap: 8px; }
.mt-step-dot {
  display: flex; align-items: center; justify-content: center; flex: none; width: 18px; height: 18px; border-radius: 50%;
  border: 1.5px solid var(--accent); background: var(--accent); color: #fff; font-size: 9px; font-weight: 600;
}
.mt-step-dot--opt { background: var(--surface); color: var(--accent); }
.mt-rail--sm .mt-rail-line { min-height: 6px; }
.mt-step-main { display: flex; flex-direction: column; gap: 1px; min-width: 0; padding-bottom: 8px; }
.mt-step-main small { font-size: 10px; color: var(--ink4); }
.mt-step-name { font-size: 11px; font-weight: 600; color: var(--ink); }
.mt-clock { padding-top: 2px; font-family: var(--mono, ui-monospace, monospace); font-size: 10px; color: var(--ink3); }
.mt-ct-link {
  height: 32px; border: 0; border-top: 1px solid var(--border); background: var(--panel);
  color: var(--accent); font-size: 12px; font-weight: 500; cursor: pointer;
}
.mt-ct-link:hover { background: var(--accent-soft); }
.mt-desc { margin: 4px 0 0; font-size: 12px; color: var(--ink2); }
.mt-panel-foot { display: flex; flex-direction: column; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border); background: var(--panel); }
.mt-btn {
  height: 34px; border: 0; border-radius: 8px; background: var(--ink); color: var(--surface);
  font-size: 12px; font-weight: 600; cursor: pointer;
}
.mt-btn:hover { filter: brightness(1.2); }

@media (max-width: 900px) {
  .mt-panel { max-width: none; position: static; }
}
</style>
