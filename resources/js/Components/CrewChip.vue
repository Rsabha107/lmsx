<template>
  <div :class="['chip', `chip--${state}`, { 'chip--locked': locked }]" :title="locked ? undefined : text">
    <span class="chip-text">{{ text }}</span>
    <template v-if="!locked">
      <span class="chip-caret" aria-hidden="true">▾</span>
      <select class="chip-select" :value="modelValue ?? ''" :aria-label="ariaLabel" @change="onChange">
        <option value="">{{ placeholder }}</option>
        <option v-for="o in options" :key="o.id" :value="o.id">{{ o.label }}</option>
      </select>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: Number, default: null },
  // [{ id, label }]: the label is what the dropdown lists (with busy notes).
  options: { type: Array, default: () => [] },
  // What the chip shows for the current pick.
  display: { type: String, default: '' },
  placeholder: { type: String, default: 'Select' },
  ariaLabel: { type: String, default: undefined },
  bad: { type: Boolean, default: false },
  locked: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const state = computed(() => (props.bad ? 'bad' : (props.modelValue ? 'set' : 'empty')));
const text = computed(() => (props.modelValue ? props.display : (props.locked ? '—' : props.placeholder)));

function onChange(e) {
  emit('update:modelValue', e.target.value === '' ? null : Number(e.target.value));
}
</script>

<style scoped>
.chip {
  position: relative; display: flex; align-items: center; min-width: 0; height: 32px;
  border-radius: 8px; border: 1px solid transparent; background: var(--panel); color: var(--ink);
}
.chip:focus-within { box-shadow: 0 0 0 3px var(--accent-ring); border-color: var(--accent); }
.chip--empty { border: 1px dashed var(--warn); background: var(--surface); color: var(--warn); }
.chip--bad { border-color: color-mix(in srgb, var(--danger) 45%, transparent); background: var(--danger-soft); color: var(--danger-strong); }
.chip--locked { background: transparent; border: 1px solid var(--border); color: var(--ink2); }
.chip--locked.chip--empty { color: var(--ink4); }
.chip-text {
  flex: 1; min-width: 0; padding: 0 24px 0 10px; font-size: 13px; font-weight: 500;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.chip--locked .chip-text { padding-right: 10px; }
.chip-caret { position: absolute; right: 9px; font-size: 9px; color: var(--ink4); pointer-events: none; }
.chip-select { position: absolute; inset: 0; width: 100%; opacity: 0; cursor: pointer; }
</style>
