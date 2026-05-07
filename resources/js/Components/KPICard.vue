<template>
  <div class="kpi-card">
    <div class="kpi-top">
      <span class="kpi-label">{{ kpi.label }}</span>
      <status-pill :tone="kpi.tone">{{ kpi.delta }}</status-pill>
    </div>
    <div class="kpi-value">{{ kpi.value }}</div>
    <sparkline :data="kpi.trend" :color="sparkColor" :width="84" :height="28" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Sparkline from './Sparkline.vue';
import StatusPill from './StatusPill.vue';

const props = defineProps({
  kpi: { type: Object, required: true },
});

const toneColors = {
  ok:      '#16A34A',
  warn:    '#F59E0B',
  danger:  '#EF4444',
  primary: '#6366F1',
  neutral: '#9AA2B2',
};
const sparkColor = computed(() => toneColors[props.kpi.tone] ?? '#6366F1');
</script>

<style scoped>
.kpi-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px 16px 12px;
  display: flex; flex-direction: column; gap: 4px;
  transition: box-shadow 0.15s;
}
.kpi-card:hover { box-shadow: 0 2px 10px rgba(0,0,0,0.06); }

.kpi-top {
  display: flex; align-items: flex-start;
  justify-content: space-between; gap: 8px;
}
.kpi-label {
  font-size: 12px; color: var(--ink3); font-weight: 500;
  line-height: 1.3;
}
.kpi-value {
  font-size: 20px; font-weight: 700; color: var(--ink);
  line-height: 1.2; margin-top: 2px;
}
</style>
