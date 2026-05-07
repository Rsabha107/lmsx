<template>
  <div style="padding: 10px; background: var(--panel); border-radius: 8px; border: 1px solid var(--border);">
    <div style="font-size: 11px; color: var(--ink3); margin-bottom: 2px;">{{ label }}</div>
    <div :style="{
      fontSize: '16px', fontWeight: '700',
      color: toneColor || 'var(--ink)',
      fontFamily: 'var(--mono)',
      letterSpacing: '-0.3px',
    }">{{ value }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { LMS_TOKENS } from '@/Composables/useTokens.js'

const props = defineProps({
  label: String,
  value: [String, Number],
  tone:  { type: String, default: null },
})

const toneColor = computed(() => {
  if (!props.tone) return null
  const map = { 
    warn: LMS_TOKENS.warn, 
    danger: LMS_TOKENS.danger, 
    ok: LMS_TOKENS.ok, 
    primary: LMS_TOKENS.primary,
    live: LMS_TOKENS.live
  }
  return map[props.tone] || null
})
</script>
