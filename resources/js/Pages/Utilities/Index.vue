<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Utilities</h1>
        <p class="page-sub">One-off data tools. None of these change live data — they produce files you review first.</p>
      </div>
    </div>

    <div class="tabs" role="tablist">
      <button
        v-for="tool in tools"
        :key="tool.type"
        type="button"
        role="tab"
        class="tab"
        :class="{ 'tab--active': tool.type === current }"
        :aria-selected="tool.type === current"
        @click="select(tool.type)"
      >
        <svg-icon :name="tool.type === 'matches' ? 'trophy' : 'team'" :size="15" />
        {{ tool.title }}
      </button>
    </div>

    <!-- Keyed so switching tabs starts each tool from a clean slate. -->
    <sheet-converter
      v-if="activeTool"
      :key="activeTool.type"
      :type="activeTool.type"
      :title="activeTool.title"
      :blurb="activeTool.blurb"
      :import-hint="activeTool.importHint"
      :headers="activeTool.headers"
      :can-use-ai="canUseAi"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '../../Components/AppLayout.vue';
import SvgIcon from '../../Components/SvgIcon.vue';
import SheetConverter from '../../Components/SheetConverter.vue';

const props = defineProps({
  tools: { type: Array, default: () => [] },
  activeTool: { type: String, default: null },
  canUseAi: { type: Boolean, default: false },
});

const current = ref(props.activeTool ?? props.tools[0]?.type ?? null);

const activeTool = computed(() => props.tools.find(t => t.type === current.value) ?? null);

function select(type) {
  current.value = type;
  // Keeps the tab shareable without a server round-trip; the sidebar's active
  // state keys off the /utilities path, which doesn't change.
  window.history.replaceState({}, '', `/utilities?tool=${type}`);
}
</script>

<style scoped>
.page-header { margin-bottom: 16px; }
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.tabs {
  display: flex; gap: 4px; flex-wrap: wrap;
  border-bottom: 1px solid var(--border);
  margin-bottom: 18px;
}

.tab {
  display: inline-flex; align-items: center; gap: 7px;
  padding: 9px 14px; cursor: pointer;
  background: none; border: 0; border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  font-size: 13px; font-weight: 600; color: var(--ink3);
}
.tab:hover { color: var(--ink); }
.tab--active { color: var(--accent); border-bottom-color: var(--accent); }
</style>
