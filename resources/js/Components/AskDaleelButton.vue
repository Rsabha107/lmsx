<template>
  <button v-if="canAsk" type="button" :class="['ask-daleel', { 'ask-daleel--quiet': quiet }]" :title="question" @click.stop="go">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
      <path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8L12 3z" />
      <path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15z" />
    </svg>
    <span>{{ label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useDaleel } from '../Composables/useDaleel';

// Opens the Daleel window with a ready question about what's on screen.
const props = defineProps({
  question: { type: String, required: true },
  context: { type: Object, default: null },
  label: { type: String, default: 'Ask Daleel' },
  quiet: { type: Boolean, default: false },
});

const page = usePage();
const canAsk = computed(() => page.props.auth?.can?.['ai.use'] === true);
const { askAbout } = useDaleel();

function go() {
  askAbout(props.question, props.context);
}
</script>

<style scoped>
.ask-daleel {
  display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 11px; flex-shrink: 0;
  font-size: 12px; font-weight: 600; white-space: nowrap; cursor: pointer;
  color: var(--accent-fg, var(--accent)); background: var(--accent-soft, var(--panel));
  border: 1px solid color-mix(in srgb, var(--accent) 25%, transparent); border-radius: 999px;
}
.ask-daleel:hover { border-color: var(--accent); }
.ask-daleel--quiet { height: 24px; padding: 0 8px; font-size: 11px; background: none; border-color: var(--border); color: var(--ink3); }
.ask-daleel--quiet:hover { color: var(--accent-fg, var(--accent)); border-color: var(--accent); }
</style>
