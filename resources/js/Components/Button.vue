<template>
  <button :class="['btn', `btn--${variant}`, `btn--${size}`, { 'btn--processing': processing }]" @click="$emit('click')" :disabled="disabled || processing">
    <span v-if="processing" class="btn-spinner"></span>
    <slot v-if="!processing" name="icon" />
    <slot />
  </button>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'primary', // primary (dark), secondary (light), ghost
    validator: (value) => ['primary', 'secondary', 'ghost'].includes(value)
  },
  size: {
    type: String,
    default: 'sm', // sm, md
    validator: (value) => ['sm', 'md'].includes(value)
  },
  disabled: {
    type: Boolean,
    default: false
  },
  processing: {
    type: Boolean,
    default: false
  }
})

defineEmits(['click'])
</script>

<style scoped>
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  font-family: inherit;
  transition: all 0.2s;
  border: 1px solid transparent;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn--processing {
  cursor: wait;
}

.btn-spinner {
  display: inline-block;
  width: 12px;
  height: 12px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: btn-spin 0.6s linear infinite;
}

.btn--secondary .btn-spinner {
  border-color: rgba(15, 23, 36, 0.3);
  border-top-color: #0F1724;
}

.btn--ghost .btn-spinner {
  border-color: rgba(15, 23, 36, 0.3);
  border-top-color: var(--ink3);
}

@keyframes btn-spin {
  to { transform: rotate(360deg); }
}

/* Sizes */
.btn--sm {
  padding: 5px 10px;
  font-size: 12px;
}

.btn--md {
  padding: 8px 16px;
  font-size: 13px;
}

/* Variants */
.btn--primary {
  background: #0F1724;
  color: #fff;
  border-color: #0F1724;
}

.btn--primary:hover:not(:disabled) {
  opacity: 0.9;
}

.btn--secondary {
  background: #fff;
  color: #0F1724;
  border-color: #D7DAE2;
}

.btn--secondary:hover:not(:disabled) {
  background: var(--panel);
  border-color: var(--accent);
}

.btn--ghost {
  background: none;
  color: var(--ink3);
  border-color: var(--border);
}

.btn--ghost:hover:not(:disabled) {
  background: var(--panel);
  color: var(--ink);
}
</style>
