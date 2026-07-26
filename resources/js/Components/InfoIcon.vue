<template>
  <button
    @click.stop="$emit('click')"
    class="info-icon"
    :title="title"
    type="button"
  >
    <svg 
      :width="size" 
      :height="size" 
      viewBox="0 0 24 24" 
      fill="none" 
      stroke="currentColor" 
      stroke-width="2" 
      stroke-linecap="round" 
      stroke-linejoin="round"
    >
      <circle cx="12" cy="12" r="10"></circle>
      <line x1="12" y1="16" x2="12" y2="12"></line>
      <line x1="12" y1="8" x2="12.01" y2="8"></line>
    </svg>
  </button>
</template>

<script setup>
defineProps({
  size: {
    type: [Number, String],
    default: 14
  },
  title: {
    type: String,
    default: ''
  }
});

defineEmits(['click']);
</script>

<style scoped>
.info-icon {
  background: none;
  border: none;
  padding: 0;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  color: var(--ink3);
  transition: color 0.15s;
  line-height: 1;
}

.info-icon:hover {
  color: var(--accent);
}

.info-icon:focus {
  outline: none;
}

.info-icon:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
  border-radius: 4px;
}
</style>
