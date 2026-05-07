<template>
  <svg :width="width" :height="height" viewBox="0 0 100 32" preserveAspectRatio="none">
    <defs>
      <linearGradient :id="gradId" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" :stop-color="color" stop-opacity="0.25"/>
        <stop offset="100%" :stop-color="color" stop-opacity="0"/>
      </linearGradient>
    </defs>
    <path :d="areaPath" :fill="`url(#${gradId})`" />
    <path :d="linePath" :stroke="color" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data:   { type: Array, default: () => [] },
  color:  { type: String, default: '#6366F1' },
  width:  { type: Number, default: 80 },
  height: { type: Number, default: 32 },
});

const gradId = `sg-${Math.random().toString(36).slice(2)}`;

function buildPaths(data) {
  if (!data || data.length < 2) return { linePath: '', areaPath: '' };
  const min = Math.min(...data);
  const max = Math.max(...data);
  const range = max - min || 1;
  const pts = data.map((v, i) => [
    (i / (data.length - 1)) * 100,
    32 - ((v - min) / range) * 28 - 2,
  ]);
  const line = pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
  const area = `${line} L100,32 L0,32 Z`;
  return { linePath: line, areaPath: area };
}

const linePath = computed(() => buildPaths(props.data).linePath);
const areaPath = computed(() => buildPaths(props.data).areaPath);
</script>
