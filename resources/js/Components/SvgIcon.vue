<template>
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
    :stroke-width="strokeWidth" stroke="currentColor"
    :width="size" :height="size"
    v-html="iconPath"
  />
</template>

<script setup>
import { computed } from 'vue';
import { icons } from '../Composables/useIcons.js';

const props = defineProps({
  name:        { type: String, required: true },
  size:        { type: Number, default: 18 },
  strokeWidth: { type: Number, default: 1.7 },
});

const iconPath = computed(() => icons[props.name] ?? '');
</script>
