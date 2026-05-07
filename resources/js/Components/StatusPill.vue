<template>
  <span :class="['pill', `pill--${tone}`]"><slot /></span>
</template>

<script setup>
defineProps({
  tone: { type: String, default: 'neutral' }, // ok | warn | danger | primary | neutral | live
});
</script>

<style scoped>
.pill {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 2px 8px; border-radius: 20px;
  font-size: 11.5px; font-weight: 600; white-space: nowrap;
}
.pill--ok      { background: var(--ok-soft);      color: var(--ok); }
.pill--warn    { background: var(--warn-soft);     color: var(--warn); }
.pill--danger  { background: var(--danger-soft);   color: var(--danger); }
.pill--primary { background: var(--accent-soft);   color: var(--accent-fg); }
.pill--live    { background: var(--live-soft);     color: var(--live); }
.pill--neutral { background: var(--panel);         color: var(--ink3); border: 1px solid var(--border); }
</style>
