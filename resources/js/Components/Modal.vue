<template>
  <Transition name="modal">
    <div v-if="show" class="modal-overlay" @click="handleOverlayClick">
      <div
        ref="container"
        class="modal-container"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="titleId"
        tabindex="-1"
        @click.stop
        @keydown.tab="handleTab"
      >
        <div class="modal-header">
          <h3 :id="titleId" class="modal-title">
            <slot name="title">Modal Title</slot>
          </h3>
          <button v-if="closeable" type="button" class="modal-close" aria-label="Close dialog" @click="close">
            <svg-icon name="x" :size="16" />
          </button>
        </div>
        <div class="modal-body">
          <slot />
        </div>
        <div v-if="$slots.footer" class="modal-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { watch, onMounted, onUnmounted, nextTick, ref, useId } from 'vue';
import SvgIcon from './SvgIcon.vue';

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  closeable: {
    type: Boolean,
    default: true,
  },
  maxWidth: {
    type: String,
    default: '600px',
  },
});

const emit = defineEmits(['close']);

const titleId = useId();
const container = ref(null);
const previouslyFocused = ref(null);

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

const focusableElements = () =>
  Array.from(container.value?.querySelectorAll(FOCUSABLE) ?? [])
    // offsetParent is null for anything hidden, which must not receive focus.
    .filter((el) => el.offsetParent !== null || el === document.activeElement);

const close = () => {
  if (props.closeable) {
    emit('close');
  }
};

const handleOverlayClick = () => {
  if (props.closeable) {
    close();
  }
};

const handleEscape = (e) => {
  if (e.key === 'Escape' && props.show && props.closeable) {
    close();
  }
};

const handleTab = (e) => {
  const focusable = focusableElements();

  if (focusable.length === 0) {
    e.preventDefault();
    container.value?.focus();
    return;
  }

  const first = focusable[0];
  const last = focusable[focusable.length - 1];

  if (e.shiftKey && (document.activeElement === first || document.activeElement === container.value)) {
    e.preventDefault();
    last.focus();
  } else if (!e.shiftKey && document.activeElement === last) {
    e.preventDefault();
    first.focus();
  }
};

watch(
  () => props.show,
  async (value) => {
    if (value) {
      previouslyFocused.value = document.activeElement;
      document.body.style.overflow = 'hidden';

      await nextTick();
      // Prefer an explicit [autofocus] target, else the dialog itself so screen
      // readers announce the title rather than landing mid-content.
      const preferred = container.value?.querySelector('[autofocus]');
      (preferred ?? container.value)?.focus();
    } else {
      document.body.style.overflow = '';

      const target = previouslyFocused.value;
      previouslyFocused.value = null;

      if (target?.isConnected && typeof target.focus === 'function') {
        target.focus();
      }
    }
  }
);

onMounted(() => {
  document.addEventListener('keydown', handleEscape);

  // The watcher doesn't fire for a modal that renders already open.
  if (props.show) {
    previouslyFocused.value = document.activeElement;
    document.body.style.overflow = 'hidden';
    nextTick(() => container.value?.focus());
  }
});

onUnmounted(() => {
  document.removeEventListener('keydown', handleEscape);
  document.body.style.overflow = '';
});
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 20px;
}

.modal-container {
  background: var(--surface);
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  max-width: v-bind(maxWidth);
  width: 100%;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* The dialog takes focus on open; the ring belongs on the controls inside it. */
.modal-container:focus {
  outline: none;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}

.modal-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--ink);
  margin: 0;
}

.modal-close {
  background: none;
  border: none;
  color: var(--ink3);
  cursor: pointer;
  padding: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all 0.15s;
}

.modal-close:hover {
  background: var(--panel);
  color: var(--ink);
}

.modal-body {
  padding: 24px;
  overflow-y: auto;
  flex: 1;
  min-height: 0;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid var(--border);
  display: flex;
  gap: 12px;
  justify-content: flex-end;
}

/* Transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-active .modal-container,
.modal-leave-active .modal-container {
  transition: transform 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

.modal-enter-from .modal-container,
.modal-leave-to .modal-container {
  transform: scale(0.95);
}
</style>
