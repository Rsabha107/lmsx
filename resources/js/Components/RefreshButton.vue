<template>
  <Button variant="ghost" size="sm" @click="handleRefresh" :disabled="isLoading" title="Refresh data">
    <template #icon>
      <svg 
        class="refresh-icon" 
        :class="{ 'spin': isLoading }" 
        width="16" 
        height="16" 
        viewBox="0 0 24 24" 
        fill="none" 
        stroke="currentColor" 
        stroke-width="2" 
        stroke-linecap="round" 
        stroke-linejoin="round"
      >
        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
      </svg>
    </template>
    <slot></slot>
  </Button>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from './Button.vue';

const props = defineProps({
  only: {
    type: Array,
    default: null,
    description: 'Array of prop names to reload (Inertia only parameter)'
  }
});

const isLoading = ref(false);

function handleRefresh() {
  isLoading.value = true;
  
  const options = {
    onFinish: () => {
      isLoading.value = false;
    }
  };
  
  if (props.only) {
    options.only = props.only;
  }
  
  router.reload(options);
}
</script>

<style scoped>
.refresh-icon {
  display: block;
  color: currentColor;
}

.refresh-icon.spin {
  animation: spin 0.8s linear;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
</style>
