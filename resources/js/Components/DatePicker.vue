<template>
  <div class="date-picker">
    <button class="date-picker-btn" @click="shiftDay(-1)" title="Previous day" type="button">‹</button>
    <FormDateField
      :model-value="modelValue"
      display-format="d/m/Y"
      value-format="Y-m-d"
      :allow-input="false"
      today-button
      input-class="date-picker-input"
      @update:model-value="v => emit('update:modelValue', v)"
    />
    <button class="date-picker-btn" @click="shiftDay(1)" title="Next day" type="button">›</button>
  </div>
</template>

<script setup>
import FormDateField from './FormDateField.vue';

const props = defineProps({
  // ISO 'Y-m-d' string — kept as the wire format so it drops straight into
  // query params / Carbon::parse(), while the input itself displays d/m/Y.
  modelValue: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

function shiftDay(delta) {
  const [y, m, d] = props.modelValue.split('-').map(Number);
  const date = new Date(y, m - 1, d + delta);

  emit('update:modelValue', [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-'));
}
</script>

<style scoped>
.date-picker { display: flex; align-items: center; gap: 4px; }

.date-picker-btn {
  width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
  border-radius: 6px; border: 1px solid var(--border); background: none;
  color: var(--ink3); font-size: 15px; cursor: pointer; line-height: 1;
}
.date-picker-btn:hover { background: var(--panel); color: var(--ink); }

:deep(.date-picker-input) {
  width: 108px; padding: 5px 8px; border-radius: 6px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; color: var(--ink); cursor: pointer; text-align: center;
}
</style>
