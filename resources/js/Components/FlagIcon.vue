<template>
  <span
    v-if="iconClass"
    :class="iconClass"
    class="flag-icon"
    :title="title"
  ></span>
  <span v-else class="flag-icon-fallback">{{ fallback }}</span>
</template>

<script setup>
import { computed } from 'vue';
import { flagIconClass } from '../Composables/useCountryFlags';

const props = defineProps({
  // 3-letter team/country code, e.g. "QAT"
  code: { type: String, default: null },
  title: { type: String, default: null },
  // Optional emoji/text shown when the code has no known mapping
  fallback: { type: String, default: null },
});

const iconClass = computed(() => flagIconClass(props.code));
</script>

<style scoped>
.flag-icon {
  display: inline-block;
  width: 1.33em;
  height: 1em;
  background-size: cover;
  background-position: center;
  border-radius: 2px;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.12);
  flex-shrink: 0;
}
.flag-icon-fallback {
  display: inline-block;
}
</style>
