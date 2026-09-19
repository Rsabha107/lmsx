/**
 * Dialog semantics + focus management for the hand-rolled modals that predate
 * Components/Modal.vue. Apply to the backdrop element; the panel is its first
 * element child. Pass the close handler to wire up Escape:
 *
 *   <div v-if="showX" v-dialog="closeX" class="modal-backdrop">
 *
 * These modals use v-if, so mount/unmount map to open/close.
 */

const FOCUSABLE = [
  'a[href]',
  'button:not([disabled])',
  'input:not([disabled]):not([type="hidden"])',
  'select:not([disabled])',
  'textarea:not([disabled])',
  '[tabindex]:not([tabindex="-1"])',
].join(',');

let sequence = 0;

const panelOf = (el) =>
  el.querySelector('[data-dialog-panel]') ?? el.firstElementChild ?? el;

const focusableIn = (panel) =>
  Array.from(panel.querySelectorAll(FOCUSABLE)).filter(
    (node) => node.offsetParent !== null || node === document.activeElement
  );

export default {
  mounted(el, binding) {
    const panel = panelOf(el);

    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    if (!panel.hasAttribute('tabindex')) {
      panel.setAttribute('tabindex', '-1');
    }

    // These modals title themselves with .modal-title spans as often as headings.
    const heading = panel.querySelector('[data-dialog-title], .modal-title, h1, h2, h3, h4');
    if (panel.hasAttribute('aria-label') || panel.hasAttribute('aria-labelledby')) {
      // The caller named it explicitly.
    } else if (heading?.textContent.trim()) {
      if (!heading.id) {
        heading.id = `dialog-title-${++sequence}`;
      }
      panel.setAttribute('aria-labelledby', heading.id);
    } else {
      panel.setAttribute('aria-label', 'Dialog');
    }

    el._dialogPreviousFocus = document.activeElement;

    el._dialogKeydown = (event) => {
      if (event.key === 'Escape' && typeof binding.value === 'function') {
        event.stopPropagation();
        binding.value();
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusable = focusableIn(panel);

      if (focusable.length === 0) {
        event.preventDefault();
        panel.focus();
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && (document.activeElement === first || document.activeElement === panel)) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    el.addEventListener('keydown', el._dialogKeydown);

    requestAnimationFrame(() => {
      const preferred = panel.querySelector('[autofocus]');
      (preferred ?? panel).focus();
    });
  },

  unmounted(el) {
    el.removeEventListener('keydown', el._dialogKeydown);

    const previous = el._dialogPreviousFocus;
    el._dialogPreviousFocus = null;
    el._dialogKeydown = null;

    if (previous?.isConnected && typeof previous.focus === 'function') {
      previous.focus();
    }
  },
};
