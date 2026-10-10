<template>
  <div ref="wrap" class="mc-wrap">
    <button ref="btn" type="button" class="mc-btn" :aria-expanded="open" title="Pick a date" @click="toggle">
      <svg-icon name="schedule" :size="15" /> Calendar
    </button>
    <div v-if="open" class="mc" :style="pos" role="dialog" aria-label="Pick a date">
      <div class="mc-head">
        <button type="button" class="mc-nav" aria-label="Previous month" @click="shiftMonth(-1)">‹</button>
        <span class="mc-title">{{ title }}</span>
        <button type="button" class="mc-nav" aria-label="Next month" @click="shiftMonth(1)">›</button>
      </div>
      <div class="mc-grid">
        <span v-for="d in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="d" class="mc-dow">{{ d }}</span>
        <button
          v-for="cell in cells"
          :key="cell.key"
          type="button"
          :class="['mc-day', {
            'mc-day--out': !cell.inMonth,
            'mc-day--on': cell.key === modelValue,
            'mc-day--range': cell.inRange,
            'mc-day--today': cell.today,
          }]"
          :style="cell.count ? { '--heat': cell.heat } : undefined"
          :title="cell.count ? `${cell.count} ${unit}${cell.count === 1 ? '' : 's'}` : `No ${unit}s`"
          @click="pick(cell.key)"
        >
          <span>{{ cell.day }}</span>
          <span v-if="cell.count" class="mc-count">{{ cell.count }}</span>
        </button>
      </div>
      <div class="mc-foot">
        <span class="mc-legend"><span class="mc-swatch" /> Busier days are darker</span>
        <button type="button" class="mc-link" @click="pick(todayKey())">Today</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import SvgIcon from './SvgIcon.vue';
import { todayKey } from '../Composables/useCrewRoster';

const props = defineProps({
  modelValue: { type: String, required: true },
  // 'YYYY-MM-DD' => how many items fall on that day, for the shading
  counts: { type: [Object, Array], default: () => ({}) },
  unit: { type: String, default: 'movement' },
  // Optional first and last day to outline, e.g. the week being viewed
  range: { type: Array, default: null },
});
const emit = defineEmits(['update:modelValue']);

const pad = (n) => String(n).padStart(2, '0');
const keyOf = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

function addDays(key, n) {
  const d = new Date(`${key}T00:00:00`);
  d.setDate(d.getDate() + n);
  return keyOf(d);
}

const open = ref(false);
const wrap = ref(null);
const btn = ref(null);
const pos = ref({});
const month = ref(props.modelValue.slice(0, 7)); // 'YYYY-MM'

// Fixed, so a scrolling or clipping parent can't cut it off.
function toggle() {
  open.value = !open.value;
  if (!open.value) return;
  month.value = props.modelValue.slice(0, 7);
  const rect = btn.value.getBoundingClientRect();
  const left = Math.max(8, Math.min(rect.right - 300, window.innerWidth - 308));
  pos.value = { top: `${rect.bottom + 6}px`, left: `${left}px` };
}

function shiftMonth(n) {
  const [y, m] = month.value.split('-').map(Number);
  month.value = keyOf(new Date(y, m - 1 + n, 1)).slice(0, 7);
}

function pick(key) {
  open.value = false;
  emit('update:modelValue', key);
}

const title = computed(() => new Date(`${month.value}-01T00:00:00`).toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }));

const cells = computed(() => {
  const first = `${month.value}-01`;
  const start = addDays(first, -((new Date(`${first}T00:00:00`).getDay() + 6) % 7)); // Monday-first
  const keys = Array.from({ length: 42 }, (_, i) => addDays(start, i));
  const max = Math.max(1, ...keys.map((k) => props.counts[k] ?? 0));
  return keys.map((key) => {
    const count = props.counts[key] ?? 0;
    return {
      key,
      count,
      heat: `${Math.round(12 + (count / max) * 48)}%`,
      day: Number(key.slice(8)),
      inMonth: key.startsWith(month.value),
      inRange: !!props.range && key >= props.range[0] && key <= props.range[1],
      today: key === todayKey(),
    };
  });
});

function onDocDown(e) {
  if (open.value && !wrap.value?.contains(e.target)) open.value = false;
}
function onKey(e) {
  if (e.key === 'Escape') open.value = false;
}
function onScroll(e) {
  if (open.value && !wrap.value?.contains(e.target)) open.value = false;
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
</script>

<style scoped>
.mc-wrap { flex-shrink: 0; display: flex; }
.mc-btn {
  display: inline-flex; align-items: center; gap: 6px; border: 1px solid var(--border); border-radius: 10px;
  background: var(--surface); color: var(--ink2); cursor: pointer; font-size: 13px; font-weight: 600; padding: 0 12px;
}
.mc-btn:hover { background: var(--panel); }
.mc-btn[aria-expanded="true"] { border-color: var(--accent); color: var(--accent); }
.mc {
  position: fixed; z-index: 1100; width: 300px; padding: 12px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
}
.mc-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.mc-title { font-size: 14px; font-weight: 600; color: var(--ink); }
.mc-nav { width: 28px; height: 28px; border: 1px solid var(--border); border-radius: 7px; background: var(--surface); color: var(--ink2); cursor: pointer; font-size: 16px; }
.mc-nav:hover { background: var(--panel); }
.mc-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 3px; }
.mc-dow { font-size: 10px; font-weight: 600; color: var(--ink3); text-align: center; padding: 2px 0 4px; }
.mc-day {
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1px; height: 38px;
  border: 1px solid transparent; border-radius: 7px; cursor: pointer; font-size: 12px; color: var(--ink);
  background: color-mix(in srgb, var(--accent) var(--heat, 0%), transparent);
}
.mc-day:hover { border-color: var(--accent); }
.mc-day--out { opacity: 0.4; }
.mc-day--range { border-radius: 0; box-shadow: inset 0 1px 0 var(--accent), inset 0 -1px 0 var(--accent); }
.mc-day--today { font-weight: 700; box-shadow: inset 0 0 0 1px var(--accent); }
.mc-day--on { border-color: var(--accent); box-shadow: 0 0 0 2px color-mix(in srgb, var(--accent) 35%, transparent); font-weight: 700; }
.mc-count { font-size: 9px; font-weight: 700; line-height: 1; color: var(--ink2); }
.mc-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; }
.mc-legend { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--ink3); }
.mc-swatch { width: 24px; height: 8px; border-radius: 3px; background: linear-gradient(90deg, color-mix(in srgb, var(--accent) 12%, transparent), color-mix(in srgb, var(--accent) 60%, transparent)); }
.mc-link { border: 0; background: none; color: var(--accent); font-size: 12px; font-weight: 600; cursor: pointer; }
</style>
