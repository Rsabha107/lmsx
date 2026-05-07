<template>
  <div class="action-buttons">
    <button v-if="showDuplicate" class="action-btn action-btn--duplicate" @click="$emit('duplicate')" title="Duplicate">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
        <rect x="2" y="5" width="9" height="9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
        <path d="M5 5V2H14V11H11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
      </svg>
    </button>
    <button class="action-btn action-btn--edit" @click="$emit('edit')" title="Edit">
      <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
        <path d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
    </button>
    <button 
      class="action-btn action-btn--delete" 
      @click="$emit('delete')" 
      :disabled="isDeleting"
      title="Delete"
    >
      <svg v-if="!isDeleting" width="14" height="14" viewBox="0 0 16 16" fill="none">
        <path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>
      <span v-else class="spinner"></span>
    </button>
  </div>
</template>

<script setup>
defineProps({
  isDeleting: {
    type: Boolean,
    default: false,
  },
  showDuplicate: {
    type: Boolean,
    default: false,
  },
});

defineEmits(['edit', 'delete', 'duplicate']);
</script>

<style scoped>
.action-buttons {
  display: flex;
  gap: 6px;
  justify-content: center;
  align-items: center;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.13s;
  background: transparent;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn--edit {
  color: var(--ink3);
}

.action-btn--edit:hover:not(:disabled) {
  background: rgba(59, 130, 246, 0.1);
  color: var(--accent);
}

.action-btn--duplicate {
  color: var(--ink3);
}

.action-btn--duplicate:hover:not(:disabled) {
  background: rgba(168, 85, 247, 0.1);
  color: #A855F7;
}

.action-btn--delete {
  color: var(--ink3);
}

.action-btn--delete:hover:not(:disabled) {
  background: rgba(239, 68, 68, 0.1);
  color: #EF4444;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
</style>
