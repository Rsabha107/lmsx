<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Notifications</h1>
        <p class="page-sub">{{ notifications.length }} recent alerts</p>
      </div>
      <div class="page-header-actions">
        <RefreshButton :only="['notifications']" />
      </div>
    </div>

    <div class="notif-cards">
      <div v-for="n in notifications" :key="n.id" :class="['notif-card', `notif-card--${n.tone}`]">
        <div class="notif-stripe" />
        <div class="notif-content">
          <div class="notif-header">
            <span class="notif-title">{{ n.title }}</span>
            <span class="notif-time">{{ n.t }}</span>
          </div>
          <div class="notif-body">{{ n.body }}</div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import AppLayout from '../Components/AppLayout.vue';
import RefreshButton from '../Components/RefreshButton.vue';

defineProps({
  notifications: { type: Array, default: () => [] },
});
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.notif-cards { display: flex; flex-direction: column; gap: 10px; max-width: 720px; }
.notif-card {
  display: flex; background: var(--surface);
  border: 1px solid var(--border); border-radius: 10px; overflow: hidden;
}
.notif-stripe { width: 4px; flex-shrink: 0; }
.notif-card--warn    .notif-stripe { background: var(--warn); }
.notif-card--danger  .notif-stripe { background: var(--danger); }
.notif-card--ok      .notif-stripe { background: var(--ok); }
.notif-card--primary .notif-stripe { background: var(--accent); }
.notif-card--neutral .notif-stripe { background: var(--ink4); }

.notif-content { padding: 14px 16px; flex: 1; }
.notif-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 4px; }
.notif-title { font-size: 14px; font-weight: 700; color: var(--ink); }
.notif-time { font-size: 12px; color: var(--ink4); flex-shrink: 0; }
.notif-body { font-size: 13px; color: var(--ink3); line-height: 1.5; }
</style>
