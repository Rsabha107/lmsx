<template>
  <Modal :show="!!conflict" max-width="820px" @close="$emit('close')">
    <template #title>Resolve · {{ conflict?.type }}</template>

    <div v-if="conflict" class="rp">
      <div class="rp-summary">
        <status-pill :tone="sevTone[conflict.sev]" :dot="true" size="sm">{{ conflict.sev }}</status-pill>
        <span v-if="!live" class="rp-resolved">Resolved — no longer detected</span>
        <p class="rp-text">{{ conflict.text }}</p>
        <p v-if="conflict.hint" class="rp-hint"><b>Suggested fix:</b> {{ conflict.hint }}</p>
      </div>

      <div v-if="loading" class="rp-muted">Checking who's free…</div>

      <template v-else>
        <!-- One shared axis so the clash is visible at a glance. -->
        <div v-if="axis" class="rp-timeline">
          <div class="rp-axis">
            <span>{{ fmt(axis.start) }}</span>
            <span>{{ fmt(axis.end) }}</span>
          </div>
          <div v-for="o in options" :key="o.movement.id" class="rp-track">
            <span class="rp-track-label">{{ o.movement.code }}</span>
            <div class="rp-bar-area">
              <div v-if="o.movement.span" class="rp-bar rp-bar--span" :style="barStyle(o.movement.span)" :title="`Occupied ${fmt(o.movement.span[0])}–${fmt(o.movement.span[1])}`" />
              <div v-for="(s, i) in o.movement.active" :key="i" class="rp-bar rp-bar--active" :style="barStyle(s)" :title="`Working ${fmt(s[0])}–${fmt(s[1])}`" />
            </div>
          </div>
          <div class="rp-legend"><span class="rp-swatch rp-bar--active" /> working <span class="rp-swatch rp-bar--span" /> waiting</div>
        </div>

        <div v-for="o in options" :key="o.movement.id" class="rp-card">
          <div class="rp-card-head">
            <b class="rp-code">{{ o.movement.code }}</b>
            <span v-if="o.movement.span" class="rp-muted">{{ fmt(o.movement.span[0], true) }} – {{ fmt(o.movement.span[1]) }}</span>
            <span v-if="o.movement.pax" class="rp-muted">· {{ o.movement.pax }} pax</span>
          </div>

          <div class="rp-fields">
            <label v-for="f in fields" :key="f.key" class="rp-field">
              <span>{{ f.label }}</span>
              <select v-model="drafts[o.movement.id][f.key]">
                <option :value="null">Unassigned</option>
                <option v-for="c in o[f.list]" :key="c.id" :value="c.id">
                  {{ c.free ? '✓' : '⚠' }} {{ c.label }}{{ c.reason ? ` — ${c.reason}` : '' }}
                </option>
              </select>
            </label>
          </div>

          <div class="rp-actions">
            <Button v-if="isWindowConflict" variant="ghost" size="sm" :disabled="busy" @click="recompute(o.movement.id)">
              Recalculate window
            </Button>
            <Button variant="primary" size="sm" :disabled="busy || !isDirty(o)" @click="saveCrew(o)">
              Save crew
            </Button>
          </div>
        </div>
      </template>

      <div class="rp-accept">
        <template v-if="live?.accepted">
          <p class="rp-muted">
            Accepted by {{ live.accepted.by }} · {{ live.accepted.at }} — “{{ live.accepted.reason }}”
          </p>
          <Button variant="ghost" size="sm" :disabled="busy" @click="reopen">Reopen</Button>
        </template>
        <template v-else-if="live">
          <label class="rp-field">
            <span>Or accept it as it is</span>
            <textarea v-model="reason" rows="2" placeholder="Why this is OK, e.g. split duty approved by ops lead" />
          </label>
          <Button variant="secondary" size="sm" :disabled="busy || !reason.trim()" @click="accept">Accept with reason</Button>
        </template>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Button from './Button.vue';
import StatusPill from './StatusPill.vue';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  // Snapshot taken when the panel opened, so it stays readable once resolved.
  conflict: { type: Object, default: null },
  conflicts: { type: Array, default: () => [] },
});
defineEmits(['close']);

const { error: showError } = useToast();
const sevTone = { high: 'danger', medium: 'warn', low: 'neutral' };
const fields = [
  { key: 'vehicle_id', label: 'Vehicle', list: 'vehicles' },
  { key: 'driver_id', label: 'Driver', list: 'drivers' },
  { key: 'field_supervisor_id', label: 'Supervisor', list: 'supervisors' },
];

const live = computed(() => props.conflicts.find((c) => c.id === props.conflict?.id) ?? null);
const isWindowConflict = computed(() => props.conflict?.id?.startsWith('OFS'));

const options = ref([]);
const drafts = ref({});
const loading = ref(false);
const busy = ref(false);
const reason = ref('');

async function load() {
  const ids = (props.conflict?.movement_ids ?? []).slice(0, 6);
  if (!ids.length) {
    options.value = [];
    return;
  }
  loading.value = true;
  try {
    const results = await Promise.all(ids.map(async (id) => {
      const res = await fetch(`/movements/${id}/crew-options`, { headers: { Accept: 'application/json' } });
      if (!res.ok) throw new Error(`Could not load options for movement ${id}`);
      return res.json();
    }));
    options.value = results.filter((r) => r.movement);
    drafts.value = Object.fromEntries(options.value.map((o) => [
      o.movement.id,
      Object.fromEntries(fields.map((f) => [f.key, o.movement[f.key] ?? null])),
    ]));
  } catch (e) {
    showError(e.message);
  } finally {
    loading.value = false;
  }
}

watch(() => props.conflict?.id, (id) => {
  reason.value = '';
  if (id) load();
}, { immediate: true });

const isDirty = (o) => fields.some((f) => (drafts.value[o.movement.id]?.[f.key] ?? null) !== (o.movement[f.key] ?? null));

// Every action reloads the page's conflicts, then re-checks who's free.
function submit(method, url, data) {
  busy.value = true;
  router.visit(url, {
    method,
    data,
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => load(),
    onError: (errors) => showError(Object.values(errors)[0] ?? 'That change could not be saved.'),
    onFinish: () => { busy.value = false; },
  });
}

const saveCrew = (o) => submit('patch', `/movements/${o.movement.id}/crew`, drafts.value[o.movement.id]);
const recompute = (id) => submit('post', `/movements/${id}/recompute-window`, {});
const accept = () => submit('post', '/conflicts/accept', { conflict_id: props.conflict.id, reason: reason.value.trim() });
const reopen = () => submit('delete', '/conflicts/accept', { conflict_id: props.conflict.id });

const toDate = (s) => new Date(s.replace(' ', 'T'));
const fmt = (s, withDay = false) => {
  const d = toDate(s);
  const time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
  return withDay ? `${d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })} ${time}` : time;
};

const axis = computed(() => {
  const spans = options.value.map((o) => o.movement.span).filter(Boolean);
  if (!spans.length) return null;
  const start = spans.map((s) => s[0]).sort()[0];
  const end = spans.map((s) => s[1]).sort().at(-1);
  return { start, end, from: toDate(start).getTime(), to: toDate(end).getTime() };
});

function barStyle([s, e]) {
  const total = Math.max(axis.value.to - axis.value.from, 1);
  const left = ((toDate(s).getTime() - axis.value.from) / total) * 100;
  const width = Math.max(((toDate(e).getTime() - toDate(s).getTime()) / total) * 100, 0.8);
  return { left: `${left}%`, width: `${width}%` };
}
</script>

<style scoped>
.rp { display: flex; flex-direction: column; gap: 14px; }
.rp-summary { display: flex; flex-direction: column; gap: 6px; }
.rp-text { margin: 0; font-size: 13px; color: var(--ink2); line-height: 1.5; }
.rp-hint { margin: 0; font-size: 12px; color: var(--ink3); }
.rp-resolved { font-size: 12px; font-weight: 600; color: var(--ok); }
.rp-muted { font-size: 12px; color: var(--ink3); }

.rp-timeline { border: 1px solid var(--border); border-radius: 8px; padding: 10px 12px; background: var(--panel); }
.rp-axis { display: flex; justify-content: space-between; margin-left: 70px; font-size: 11px; color: var(--ink3); font-family: var(--mono); }
.rp-track { display: flex; align-items: center; gap: 8px; margin-top: 6px; }
.rp-track-label { width: 62px; flex-shrink: 0; font-size: 11px; font-weight: 700; font-family: var(--mono); color: var(--ink); }
.rp-bar-area { position: relative; flex: 1; height: 16px; }
.rp-bar { position: absolute; top: 0; height: 100%; border-radius: 3px; }
.rp-bar--span { background: var(--accent-soft); border: 1px dashed var(--accent); box-sizing: border-box; }
.rp-bar--active { background: var(--accent); }
.rp-legend { display: flex; align-items: center; gap: 6px; margin-top: 8px; font-size: 11px; color: var(--ink3); }
.rp-swatch { display: inline-block; width: 14px; height: 8px; border-radius: 2px; }

.rp-card { border: 1px solid var(--border); border-radius: 8px; padding: 12px; display: flex; flex-direction: column; gap: 10px; }
.rp-card-head { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.rp-code { font-family: var(--mono); font-size: 13px; color: var(--ink); }
.rp-fields { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.rp-field { display: flex; flex-direction: column; gap: 4px; font-size: 11px; font-weight: 600; color: var(--ink3); }
.rp-field select, .rp-field textarea {
  width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12.5px; font-weight: 400;
}
.rp-actions { display: flex; justify-content: flex-end; gap: 8px; }
.rp-accept { border-top: 1px solid var(--border); padding-top: 12px; display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.rp-accept .rp-field { width: 100%; }

@media (max-width: 640px) {
  .rp-fields { grid-template-columns: 1fr; }
}
</style>
