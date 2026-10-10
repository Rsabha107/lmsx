<template>
  <select :value="modelValue ?? ''" @change="$emit('update:modelValue', $event.target.value)">
    <option value="">Select role</option>
    <option v-for="r in options" :key="r" :value="r">{{ r }}</option>
  </select>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  roles: { type: Array, default: () => [] },
});

defineEmits(['update:modelValue']);

// A role saved before the list existed stays selectable.
const options = computed(() => (props.modelValue && !props.roles.includes(props.modelValue)
  ? [...props.roles, props.modelValue]
  : props.roles));
</script>
