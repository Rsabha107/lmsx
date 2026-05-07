<template>
  <div class="columns-dropdown" ref="dropdownRef">
    <Button variant="secondary" size="sm" @click="isOpen = !isOpen">
      <template #icon>
        <svg-icon name="columns" :size="14" />
      </template>
      Columns
    </Button>
    <div v-if="isOpen" class="dropdown-menu">
      <div class="dropdown-header">Show/Hide Columns</div>
      <label v-for="col in columns" :key="col.key" class="dropdown-item">
        <input 
          type="checkbox" 
          :checked="visibleColumns[col.key]"
          @change="toggleColumn(col.key)"
          :disabled="col.required"
        />
        <span>{{ col.label }}</span>
      </label>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import Button from './Button.vue';
import SvgIcon from './SvgIcon.vue';

const props = defineProps({
  columns: {
    type: Array,
    required: true,
  },
  visibleColumns: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['toggle']);

const isOpen = ref(false);
const dropdownRef = ref(null);

function toggleColumn(key) {
  emit('toggle', key);
}

function handleClickOutside(event) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target)) {
    isOpen.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>

<style scoped>
.columns-dropdown {
  position: relative;
}

.dropdown-menu {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 220px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  z-index: 50;
  overflow: hidden;
}

.dropdown-header {
  padding: 10px 14px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--ink3);
  border-bottom: 1px solid var(--border);
  background: var(--panel);
}

.dropdown-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 14px;
  font-size: 13px;
  color: var(--ink2);
  cursor: pointer;
  transition: background 0.13s;
}

.dropdown-item:hover {
  background: var(--panel);
}

.dropdown-item input[type="checkbox"] {
  cursor: pointer;
  accent-color: var(--accent);
}

.dropdown-item input[type="checkbox"]:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.dropdown-item span {
  user-select: none;
}
</style>
