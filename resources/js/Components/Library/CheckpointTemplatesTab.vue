<template>
  <div class="ctl">
    <div class="ctl-toolbar">
      <input v-model="search" class="ctl-search" type="search" placeholder="Search templates or checkpoints…" aria-label="Search checkpoint templates" />
      <div class="ctl-pills">
        <button v-for="f in typePills" :key="f.value" type="button"
          :class="['ctl-pill', { 'ctl-pill--on': type === f.value }]" @click="type = f.value">
          {{ f.label }} <span>{{ f.count }}</span>
        </button>
      </div>
    </div>

    <div class="ctl-body">
      <div class="ctl-table">
        <div class="ctl-row ctl-head">
          <span>Template</span><span>Sequence</span><span class="ctl-right">Time</span><span class="ctl-right">Used in</span><span />
        </div>

        <div v-for="t in rows" :key="t.id" :class="['ctl-row', 'ctl-item', { 'ctl-item--open': selectedId === t.id }]" @click="select(t.id)">
          <div class="ctl-name">
            <div class="ctl-name-top">
              <span class="ctl-name-text">{{ t.name }}</span>
              <span :class="['ctl-type', `ctl-type--${t.movement_type}`]">{{ typeLabel(t.movement_type) }}</span>
            </div>
            <div class="ctl-name-sub">
              <span class="ctl-code">{{ t.code }}</span>
              <span v-if="routeOf(t)">{{ routeOf(t) }}</span>
            </div>
          </div>
          <div class="ctl-seq">
            <div v-if="t.steps.length" class="ctl-dots" aria-hidden="true">
              <span v-for="(s, i) in t.steps" :key="s.id" class="ctl-dot-wrap">
                <span :class="['ctl-dot', { 'ctl-dot--opt': !s.required }]" />
                <span v-if="i < t.steps.length - 1" class="ctl-dot-line" />
              </span>
            </div>
            <span class="ctl-seq-text">
              <template v-if="t.steps.length"><strong>{{ t.steps.length }} checkpoint{{ t.steps.length === 1 ? '' : 's' }}</strong> · {{ firstLast(t) }}</template>
              <template v-else>No checkpoints yet</template>
            </span>
          </div>
          <span class="ctl-num ctl-time">{{ t.time }}</span>
          <span class="ctl-num ctl-used">{{ t.used_in?.length ? `${t.used_in.length} template${t.used_in.length === 1 ? '' : 's'}` : '—' }}</span>
          <div class="ctl-acts" @click.stop>
            <button type="button" class="ctl-act" title="Edit" :aria-label="`Edit ${t.name}`" @click="emit('edit', t)">✎</button>
            <button type="button" class="ctl-act" title="Duplicate" :aria-label="`Duplicate ${t.name}`" @click="emit('duplicate', t)">⧉</button>
            <button type="button" class="ctl-act ctl-act--del" title="Delete" :aria-label="`Delete ${t.name}`" @click="emit('delete', t.id)">×</button>
          </div>
        </div>

        <div v-if="!rows.length" class="ctl-empty">
          {{ templates.length ? 'No templates match your search.' : 'No checkpoint templates yet. Create one with New Checkpoint Template.' }}
        </div>
      </div>

      <aside v-if="selected" class="ctl-panel" aria-label="Checkpoint template">
        <div class="ctl-panel-head">
          <div class="ctl-panel-top">
            <span class="ctl-code ctl-code--plain">{{ selected.code }}</span>
            <span class="ctl-panel-tools">
              <span :class="['ctl-type', `ctl-type--${selected.movement_type}`]">{{ typeLabel(selected.movement_type) }}</span>
              <button type="button" class="ctl-x" aria-label="Close" @click="selectedId = null">×</button>
            </span>
          </div>
          <div class="ctl-panel-name">{{ selected.name }}</div>
          <div v-if="routeOf(selected)" class="ctl-sub">{{ routeOf(selected) }}</div>
          <div class="ctl-stats">
            <div><span>Checkpoints</span><strong>{{ selected.steps.length }}</strong></div>
            <div><span>Required</span><strong>{{ selected.steps.filter((s) => s.required).length }}</strong></div>
            <div><span>Est. time</span><strong>{{ selected.time }}</strong></div>
          </div>
        </div>

        <div class="ctl-sequence">
          <div class="ctl-section-title">Sequence</div>
          <p v-if="!selected.steps.length" class="ctl-muted">No checkpoints yet. Use Edit template to add them.</p>
          <div v-for="(s, i) in selected.steps" :key="s.id" class="ctl-step">
            <div class="ctl-rail">
              <span :class="['ctl-step-dot', { 'ctl-step-dot--opt': !s.required }]">{{ i + 1 }}</span>
              <span :class="['ctl-rail-line', { 'ctl-rail-line--end': i === selected.steps.length - 1 }]" />
            </div>
            <div class="ctl-step-main">
              <span class="ctl-step-name">{{ s.name }}</span>
              <small><span class="ctl-mono">{{ s.code }}</span> · {{ s.required ? 'Required' : 'Optional' }}</small>
            </div>
            <span class="ctl-clock">{{ s.clock }}</span>
          </div>
          <p v-if="selected.description" class="ctl-desc">{{ selected.description }}</p>
        </div>

        <div class="ctl-panel-foot">
          <span class="ctl-section-title">Used in movement templates</span>
          <div class="ctl-chips">
            <button v-for="m in selected.used_in ?? []" :key="m.id" type="button" class="ctl-chip" @click="emit('open-movement-template', m.id)">{{ m.name }}</button>
            <span v-if="!selected.used_in?.length" class="ctl-muted">Not used yet</span>
          </div>
          <button type="button" class="ctl-btn" @click="emit('edit', selected)">Edit template</button>
        </div>
      </aside>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { TYPE_ORDER, typeLabel, stepsOf, fmtMinutes } from './checkpointTemplateFormat';

// The event's checkpoint templates: list with their sequence and a read-only detail panel.
// New / edit open CheckpointTemplateEditor; delete stays with the page's dialog.
const props = defineProps({
  templates: { type: Array, default: () => [] },
});
const emit = defineEmits(['edit', 'duplicate', 'delete', 'open-movement-template']);

const routeOf = (t) => (t.from_location || t.to_location ? `${t.from_location || '…'} → ${t.to_location || '…'}` : '');
const firstLast = (t) => {
  const s = t.steps;
  return s.length > 1 ? `${s[0].name.toLowerCase()} → ${s[s.length - 1].name.toLowerCase()}` : (s[0]?.name.toLowerCase() ?? '');
};
const decorate = (t) => {
  const steps = stepsOf(t);
  const known = steps.length && steps.every((s) => s.minutes != null);
  return { ...t, steps, time: known ? fmtMinutes(steps.reduce((sum, s) => sum + s.minutes, 0)) : '—' };
};

const search = ref('');
const type = ref('all');
const typePills = computed(() => {
  const keys = [...new Set(props.templates.map((t) => t.movement_type))]
    .sort((a, b) => (TYPE_ORDER.indexOf(a) + 1 || 99) - (TYPE_ORDER.indexOf(b) + 1 || 99));
  return [
    { value: 'all', label: 'All', count: props.templates.length },
    ...keys.map((k) => ({ value: k, label: typeLabel(k), count: props.templates.filter((t) => t.movement_type === k).length })),
  ];
});

const rows = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.templates
    .filter((t) => (type.value === 'all' || t.movement_type === type.value)
      && (!q || [t.name, t.code, t.from_location, t.to_location, ...(t.checkpoints ?? []).flatMap((c) => [c.name, c.code])]
        .some((v) => (v || '').toLowerCase().includes(q))))
    .map(decorate);
});

const selectedId = ref(null);
const selected = computed(() => {
  const t = props.templates.find((x) => x.id === selectedId.value);
  return t ? decorate(t) : null;
});
const select = (id) => { selectedId.value = id; };
watch(rows, (list) => {
  if (selectedId.value && !list.some((t) => t.id === selectedId.value)) selectedId.value = null;
});
defineExpose({ select });
</script>

<style scoped>
.ctl { display: flex; flex-direction: column; gap: 14px; }
.ctl-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ctl-search {
  flex: 0 1 320px; min-width: 220px; height: 36px; padding: 0 12px; border: 1px solid var(--border-strong);
  border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 13px;
}
.ctl-search:focus { outline: none; border-color: var(--ink); }
.ctl-pills { display: flex; gap: 6px; flex-wrap: wrap; }
.ctl-pill {
  height: 30px; padding: 0 11px; border-radius: 999px; border: 1px solid var(--border-strong);
  background: var(--surface); color: var(--ink2); font-size: 12px; font-weight: 500; cursor: pointer;
}
.ctl-pill span { opacity: 0.6; margin-left: 2px; }
.ctl-pill:hover { background: var(--panel); color: var(--ink); }
.ctl-pill--on, .ctl-pill--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }

.ctl-body { display: flex; gap: 16px; align-items: flex-start; flex-wrap: wrap; }
.ctl-table { flex: 1 1 600px; min-width: 0; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface); }
.ctl-row {
  display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1.4fr) 70px 90px 84px;
  gap: 14px; align-items: center; padding: 14px 16px;
}
.ctl-head {
  padding: 10px 16px; background: var(--panel); border-bottom: 1px solid var(--border);
  font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3);
}
.ctl-right { text-align: right; }
.ctl-item { border-bottom: 1px solid var(--border); cursor: pointer; }
.ctl-item:last-of-type { border-bottom: none; }
.ctl-item:hover { background: var(--panel); }
.ctl-item--open, .ctl-item--open:hover { background: var(--accent-soft); box-shadow: inset 3px 0 0 var(--accent); }
.ctl-name { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.ctl-name-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ctl-name-text { font-size: 14px; font-weight: 600; color: var(--ink); }
.ctl-name-sub { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 12px; color: var(--ink3); }
.ctl-code { font-family: var(--mono, ui-monospace, monospace); font-size: 11px; color: var(--ink2); background: var(--panel); padding: 1px 6px; border-radius: 4px; }
.ctl-item--open .ctl-code { background: var(--surface); }
.ctl-code--plain { background: none; padding: 0; color: var(--ink3); }
.ctl-type {
  --c: var(--ink3);
  font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; white-space: nowrap;
  padding: 2px 7px; border-radius: 999px; color: var(--c); background: color-mix(in srgb, var(--c) 13%, var(--surface));
}
.ctl-type--arrival { --c: #047857; }
.ctl-type--departure { --c: #be185d; }
.ctl-type--match { --c: #7e22ce; }
.ctl-type--training { --c: #1d4ed8; }
.ctl-type--transfer { --c: #4338ca; }

.ctl-seq { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.ctl-dots { display: flex; align-items: center; }
.ctl-dot-wrap { display: flex; flex: 1; align-items: center; min-width: 0; }
.ctl-dot-wrap:last-child { flex: none; }
.ctl-dot { flex: none; width: 8px; height: 8px; border-radius: 50%; border: 1.5px solid var(--accent); background: var(--accent); }
.ctl-dot--opt { background: var(--surface); }
.ctl-dot-line { flex: 1; height: 1.5px; background: color-mix(in srgb, var(--accent) 25%, var(--surface)); }
.ctl-seq-text { font-size: 11px; color: var(--ink2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ctl-seq-text strong { font-weight: 600; color: var(--ink); }
.ctl-num { font-size: 12px; text-align: right; color: var(--ink3); }
.ctl-time { font-size: 13px; font-weight: 500; color: var(--ink); }
.ctl-acts { display: flex; gap: 2px; justify-content: flex-end; }
.ctl-act { width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--ink2); font-size: 13px; cursor: pointer; }
.ctl-act:hover { background: var(--panel); }
.ctl-act--del { color: var(--danger-strong); font-size: 15px; }
.ctl-act--del:hover { background: var(--danger-soft); }
.ctl-empty { padding: 32px; text-align: center; font-size: 13px; color: var(--ink3); }

.ctl-panel {
  flex: 1 1 340px; max-width: 440px; position: sticky; top: 12px;
  display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 12px; overflow: hidden; background: var(--surface);
}
.ctl-panel-head { display: flex; flex-direction: column; gap: 6px; padding: 16px; border-bottom: 1px solid var(--border); }
.ctl-panel-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.ctl-panel-tools { display: flex; align-items: center; gap: 6px; }
.ctl-x { border: 0; background: none; font-size: 18px; line-height: 1; color: var(--ink3); cursor: pointer; }
.ctl-panel-name { font-size: 16px; font-weight: 600; color: var(--ink); }
.ctl-sub { font-size: 12px; color: var(--ink3); }
.ctl-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 8px; }
.ctl-stats div { display: flex; flex-direction: column; gap: 2px; padding: 8px 10px; border-radius: 8px; background: var(--panel); }
.ctl-stats span { font-size: 10px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--ink3); }
.ctl-stats strong { font-size: 16px; font-weight: 600; color: var(--ink); }

.ctl-sequence { display: flex; flex-direction: column; padding: 14px 16px; }
.ctl-section-title { font-size: 10px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); }
.ctl-sequence .ctl-section-title { margin-bottom: 10px; }
.ctl-step { display: grid; grid-template-columns: 22px minmax(0, 1fr) auto; gap: 10px; }
.ctl-rail { display: flex; flex-direction: column; align-items: center; }
.ctl-step-dot {
  display: flex; align-items: center; justify-content: center; flex: none; width: 22px; height: 22px; border-radius: 50%;
  border: 1.5px solid var(--accent); background: var(--accent); color: #fff; font-size: 10px; font-weight: 600;
}
.ctl-step-dot--opt { background: var(--surface); color: var(--accent); }
.ctl-rail-line { flex: 1; width: 1.5px; min-height: 10px; background: color-mix(in srgb, var(--accent) 25%, var(--surface)); }
.ctl-rail-line--end { background: transparent; }
.ctl-step-main { display: flex; flex-direction: column; gap: 1px; min-width: 0; padding-bottom: 12px; }
.ctl-step-name { font-size: 12px; font-weight: 600; color: var(--ink); }
.ctl-step-main small { font-size: 11px; color: var(--ink4); }
.ctl-mono { font-family: var(--mono, ui-monospace, monospace); }
.ctl-clock { padding-top: 3px; font-family: var(--mono, ui-monospace, monospace); font-size: 11px; color: var(--ink3); }
.ctl-desc { margin: 4px 0 0; font-size: 12px; color: var(--ink2); }
.ctl-muted { margin: 0; font-size: 12px; color: var(--ink4); }

.ctl-panel-foot { display: flex; flex-direction: column; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border); background: var(--panel); }
.ctl-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.ctl-chip { padding: 3px 9px; border: 1px solid var(--border); border-radius: 999px; background: var(--surface); color: var(--ink); font-size: 12px; cursor: pointer; }
.ctl-chip:hover { border-color: var(--accent); color: var(--accent); }
.ctl-btn {
  margin-top: 4px; height: 34px; border: 0; border-radius: 8px; background: var(--ink); color: var(--surface);
  font-size: 12px; font-weight: 600; cursor: pointer;
}
.ctl-btn:hover { filter: brightness(1.2); }

@media (max-width: 900px) {
  .ctl-panel { max-width: none; position: static; }
}
</style>
