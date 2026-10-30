<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Ask Daleel</h1>
        <p class="page-sub">Daleel (دليل) — your guide to today's movements. Read-only, scoped to what you can already see.</p>
      </div>
      <button type="button" class="new-chat" :disabled="!state.thread.length || state.sending" @click="clear">New chat</button>
    </div>

    <div class="copilot-card">
      <DaleelChat autofocus />
    </div>
  </app-layout>
</template>

<script setup>
import AppLayout from '../Components/AppLayout.vue';
import DaleelChat from '../Components/DaleelChat.vue';
import { useDaleel } from '../Composables/useDaleel';

// Same conversation as the floating Daleel window.
const { state, clear } = useDaleel();
</script>

<style scoped>
.page-header { margin-bottom: 20px; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.new-chat {
  height: 32px; padding: 0 12px; font-size: 12.5px; font-weight: 600; cursor: pointer;
  border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink2);
}
.new-chat:hover:not(:disabled) { color: var(--ink); border-color: var(--border-strong); }
.new-chat:disabled { opacity: 0.5; cursor: default; }

.copilot-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
  height: calc(100vh - 220px);
  max-height: 720px;
}
</style>
