<template>
  <Modal :show="show" @close="handleClose" max-width="500px">
    <template #title>{{ title }}</template>

    <div style="padding: 0;">
      <p style="margin: 0; color: var(--ink2); font-size: 14px; white-space: pre-line;" v-html="message"></p>
      <p v-if="resolvedNote" style="margin: 12px 0 0; color: var(--ink3); font-size: 13px;">
        {{ resolvedNote }}
      </p>
    </div>

    <template #footer>
      <Button v-if="!hideCancel" variant="secondary" size="sm" @click="handleClose" :disabled="processing">
        {{ cancelLabel }}
      </Button>
      <Button
        variant="primary"
        size="sm"
        @click="handleConfirm"
        :processing="processing"
        :disabled="processing"
        :style="tone === 'danger' ? 'background: var(--danger); border-color: var(--danger);' : undefined"
      >
        {{ resolvedConfirmLabel }}
      </Button>
    </template>
  </Modal>
</template>

<script setup>
import { computed } from 'vue';
import Modal from './Modal.vue';
import Button from './Button.vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: 'Are you sure?' },
  // Rendered as HTML so callers can bold names/counts.
  message: { type: String, default: '' },
  // Secondary line under the message; defaults to the irreversibility warning for danger.
  note: { type: String, default: null },
  confirmLabel: { type: String, default: null },
  cancelLabel: { type: String, default: 'Cancel' },
  // For acknowledge-only dialogs that have nothing to cancel out of.
  hideCancel: { type: Boolean, default: false },
  tone: { type: String, default: 'primary' }, // 'primary' | 'danger'
  processing: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'confirm']);

const resolvedConfirmLabel = computed(
  () => props.confirmLabel ?? (props.tone === 'danger' ? 'Delete' : 'Confirm')
);

const resolvedNote = computed(
  () => props.note ?? (props.tone === 'danger' ? 'This action cannot be undone.' : '')
);

function handleClose() {
  if (!props.processing) {
    emit('close');
  }
}

function handleConfirm() {
  emit('confirm');
}
</script>
