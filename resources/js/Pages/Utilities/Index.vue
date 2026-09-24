<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Utilities</h1>
        <p class="page-sub">One-off data tools. None of these change live data — they produce files you review first.</p>
      </div>
    </div>

    <div class="utility-grid">
      <a v-for="utility in converters" :key="utility.type" class="utility-card" :href="`/utilities/converters/${utility.type}`">
        <div class="utility-icon"><svg-icon :name="utility.type === 'matches' ? 'trophy' : 'team'" :size="22" /></div>
        <div class="utility-body">
          <h2 class="utility-title">{{ utility.title }}</h2>
          <p class="utility-desc">{{ utility.blurb }} Review the mapping and every converted row before downloading.</p>
        </div>
        <svg-icon name="chevron" :size="16" class="utility-chevron" />
      </a>
    </div>
  </app-layout>
</template>

<script setup>
import AppLayout from '../../Components/AppLayout.vue';
import SvgIcon from '../../Components/SvgIcon.vue';

defineProps({
  converters: { type: Array, default: () => [] },
});
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.utility-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
  gap: 14px;
}

.utility-card {
  display: flex; align-items: flex-start; gap: 14px;
  padding: 18px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  text-decoration: none;
  transition: border-color .15s, transform .15s;
}
.utility-card:hover { border-color: var(--accent); transform: translateY(-1px); }

.utility-icon {
  flex: 0 0 auto;
  width: 42px; height: 42px;
  display: grid; place-items: center;
  border-radius: 9px;
  background: var(--panel);
  color: var(--accent);
}

.utility-body { flex: 1 1 auto; min-width: 0; }
.utility-title { font-size: 15px; font-weight: 650; color: var(--ink); margin: 0 0 4px; }
.utility-desc { font-size: 12.5px; line-height: 1.5; color: var(--ink3); margin: 0; }

.utility-chevron { flex: 0 0 auto; color: var(--ink3); margin-top: 12px; }
</style>
