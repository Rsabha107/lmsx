<template>
  <Modal :show="show" @close="handleClose" max-width="500px">
    <template #title>Delete {{ title }}</template>
    
    <div style="padding: 0;">
      <p style="margin: 0 0 12px; color: var(--ink2); font-size: 14px;" v-html="message"></p>
      <p style="margin: 0; color: var(--ink3); font-size: 13px;">
        This action cannot be undone.
      </p>
    </div>

    <template #footer>
      <Button variant="secondary" size="sm" @click="handleClose" :disabled="processing">Cancel</Button>
      <Button 
        variant="primary" 
        size="sm" 
        @click="handleConfirm" 
        :processing="processing" 
        :disabled="processing" 
        style="background: #EF4444;"
      >
        Delete
      </Button>
    </template>
  </Modal>
</template>

<script setup>
import Modal from './Modal.vue';
import Button from './Button.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false
  },
  title: {
    type: String,
    default: 'Item'
  },
  message: {
    type: String,
    default: 'Are you sure you want to delete this item?'
  },
  processing: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['close', 'confirm']);

function handleClose() {
  if (!props.processing) {
    emit('close');
  }
}

function handleConfirm() {
  emit('confirm');
}
</script>
