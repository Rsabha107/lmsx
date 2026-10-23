<template>
  <div :class="['nae', { 'nae--compact': compact }]">
    <div class="nae-icon" aria-hidden="true">📅</div>
    <h2 class="nae-title">No Active Event</h2>

    <template v-if="events.length">
      <p class="nae-text">Select an event to view {{ what }}.</p>
      <div class="nae-events">
        <button
          v-for="ev in events"
          :key="ev.id"
          type="button"
          class="nae-event"
          :disabled="busy !== null"
          @click="pick(ev.id)"
        >
          <img
            v-if="ev.logo_url && !brokenLogos.has(ev.id)"
            :src="ev.logo_url"
            alt=""
            class="nae-logo"
            @error="brokenLogos.add(ev.id)"
          />
          <span v-else class="nae-logo nae-logo--fallback">{{ initials(ev) }}</span>
          <span class="nae-name">{{ ev.name }}</span>
          <span v-if="busy === ev.id" class="nae-spin" aria-label="Opening" />
        </button>
      </div>
      <p class="nae-hint">You can switch events at any time from the selector at the top of the page.</p>
    </template>

    <p v-else class="nae-text">You don't have access to any event yet. Ask an administrator to add you to one.</p>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

defineProps({
  // Completes "Select an event to view …", e.g. "matches".
  what: { type: String, default: 'this page' },
  // Smaller block for use inside a tab rather than as the whole page.
  compact: { type: Boolean, default: false },
});

const page = usePage();
const events = computed(() => page.props.eventList ?? []);
const brokenLogos = reactive(new Set());
const busy = ref(null);

function initials(ev) {
  return (ev.short_name || ev.name || '?').slice(0, 2).toUpperCase();
}

function pick(eventId) {
  busy.value = eventId;
  router.post('/session/active-event', { event_id: eventId }, {
    preserveScroll: true,
    onFinish: () => { busy.value = null; },
  });
}
</script>

<style scoped>
.nae {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  width: 100%;
  padding: 24px 16px;
  text-align: center;
}
.nae--compact { min-height: 40vh; }
.nae-icon { font-size: 64px; line-height: 1; margin-bottom: 16px; }
.nae-title { font-size: 24px; font-weight: 700; color: var(--ink); margin: 0 0 8px; }
.nae-text { font-size: 14px; color: var(--ink3); max-width: 420px; margin: 0; }
.nae-events {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 8px;
  max-width: 680px;
  margin-top: 20px;
}
.nae-event {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px 8px 8px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: border-color .15s, background .15s, box-shadow .15s;
}
.nae-event:hover:not(:disabled) { border-color: var(--accent); background: var(--accent-soft); }
.nae-event:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--accent-ring); border-color: var(--accent); }
.nae-event:disabled { cursor: wait; opacity: .7; }
.nae-logo { width: 24px; height: 24px; border-radius: 50%; object-fit: contain; flex-shrink: 0; }
.nae-logo--fallback {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 10px;
  font-weight: 700;
}
.nae-name { white-space: nowrap; }
.nae-spin {
  width: 12px;
  height: 12px;
  border: 2px solid var(--border-strong);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: nae-spin .7s linear infinite;
}
@keyframes nae-spin { to { transform: rotate(360deg); } }
.nae-hint { font-size: 12px; color: var(--ink4); margin: 16px 0 0; }
</style>
