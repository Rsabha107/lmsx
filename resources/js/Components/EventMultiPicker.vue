<template>
  <details v-if="events.length > 1" ref="root" class="ev-pick" @keydown.esc="close">
    <summary class="ev-pick-btn" aria-label="Events shown">
      {{ label }}: <strong>{{ eventIds.length }} event{{ eventIds.length === 1 ? '' : 's' }}</strong> <span class="ev-pick-caret">▾</span>
    </summary>
    <div class="ev-pick-panel">
      <p v-if="hint" class="ev-pick-hint">{{ hint }}</p>
      <label v-for="e in events" :key="e.id" class="ev-pick-row">
        <input type="checkbox" :checked="eventIds.includes(e.id)" :disabled="e.id === activeEventId"
          :aria-label="`Show ${e.name}`" @change="toggle(e.id, $event.target.checked)" />
        <span class="ev-pick-name">{{ e.name }}</span>
        <small v-if="e.id === activeEventId">this event</small>
      </label>
    </div>
  </details>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Several events on one page (Jobs Queue, Day Board): the active event is always on; the page
// reloads with ?events[]= for the others (see App\Support\EventSelection).
const props = defineProps({
  events: { type: Array, default: () => [] },
  eventIds: { type: Array, default: () => [] },
  label: { type: String, default: 'Events' },
  hint: { type: String, default: '' },
});
// The other events (not the active one) to show; [] = the active event only.
const emit = defineEmits(['change']);

const activeEventId = computed(() => Number(usePage().props.activeEventId) || null);
const others = computed(() => props.eventIds.filter((id) => id !== activeEventId.value));

function toggle(id, on) {
  emit('change', on ? [...others.value, id] : others.value.filter((x) => x !== id));
}

// The panel floats over the page, so a click elsewhere or Esc closes it.
const root = ref(null);
const close = () => { if (root.value) root.value.open = false; };
const onDocClick = (e) => { if (root.value?.open && !root.value.contains(e.target)) close(); };
onMounted(() => document.addEventListener('click', onDocClick));
onBeforeUnmount(() => document.removeEventListener('click', onDocClick));
</script>

<style scoped>
.ev-pick { position: relative; }
.ev-pick > summary { list-style: none; user-select: none; }
.ev-pick > summary::-webkit-details-marker { display: none; }
.ev-pick-btn {
  display: inline-flex; align-items: center; gap: 4px; height: 32px; padding: 0 12px; cursor: pointer;
  border: 1px solid var(--border); border-radius: 8px; background: var(--surface);
  font-size: 12px; font-weight: 500; color: var(--ink2); white-space: nowrap;
}
.ev-pick-btn:hover { border-color: var(--border-strong); color: var(--ink); }
.ev-pick-caret { color: var(--ink3); font-size: 10px; }
.ev-pick-panel {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 30; width: 300px;
  display: flex; flex-direction: column; gap: 2px; padding: 12px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
  box-shadow: 0 12px 32px rgba(17, 24, 39, 0.14);
}
.ev-pick-hint { margin: 0; padding: 0 4px 8px; font-size: 12px; line-height: 1.45; color: var(--ink3); }
.ev-pick-row { display: flex; align-items: center; gap: 10px; padding: 7px 6px; border-radius: 6px; font-size: 13px; color: var(--ink); cursor: pointer; }
.ev-pick-row:hover { background: var(--panel); }
.ev-pick-row input { margin: 0; accent-color: var(--accent); }
.ev-pick-name { flex: 1; }
.ev-pick-row small { font-size: 10px; color: var(--ink3); }
</style>
