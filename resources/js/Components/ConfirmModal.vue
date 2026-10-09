<template>
  <Modal :show="show" @close="handleClose" max-width="500px">
    <template #title>{{ title }}</template>

    <div style="padding: 0;">
      <p style="margin: 0; color: var(--ink2); font-size: 14px; white-space: pre-line;" v-html="message"></p>
      <p v-if="resolvedNote" style="margin: 12px 0 0; color: var(--ink3); font-size: 13px;">
        {{ resolvedNote }}
      </p>
      <div v-if="confirmText" style="margin-top: 14px;">
        <label for="confirm-text-input" style="display: block; font-size: 13px; color: var(--ink2); margin-bottom: 6px;">
          Type <strong>{{ confirmText }}</strong> to confirm
        </label>
        <input
          id="confirm-text-input"
          v-model="typed"
          type="text"
          class="confirm-input"
          autocomplete="off"
          :disabled="processing"
          @keyup.enter="typedMatches && handleConfirm()"
        />
      </div>
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
        :disabled="processing || !typedMatches"
        :style="tone === 'danger' ? 'background: var(--danger); border-color: var(--danger);' : undefined"
      >
        {{ resolvedConfirmLabel }}
      </Button>
    </template>
  </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
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
  // When set, the user must type this exact text before Confirm is enabled.
  confirmText: { type: String, default: null },
});

const emit = defineEmits(['close', 'confirm']);

const typed = ref('');
const typedMatches = computed(() => !props.confirmText || typed.value.trim() === props.confirmText);

watch(() => props.show, () => { typed.value = ''; });

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
  if (!typedMatches.value) return;
  emit('confirm');
}
</script>

<style scoped>
.confirm-input {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 12px;
  border: 1px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  color: var(--ink);
  font-size: 14px;
  font-family: inherit;
}
.confirm-input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
}
</style>
