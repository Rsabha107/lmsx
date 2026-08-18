<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">AI Operations Copilot</h1>
        <p class="page-sub">Ask about today's movements — read-only, scoped to what you can already see.</p>
      </div>
    </div>

    <div class="copilot-card">
      <div class="copilot-thread" ref="threadEl">
        <div v-if="thread.length === 0" class="copilot-empty">
          <p>Try asking:</p>
          <div class="copilot-suggestions">
            <button v-for="s in suggestions" :key="s" class="suggestion-chip" @click="ask(s)">{{ s }}</button>
          </div>
        </div>

        <div v-for="(entry, i) in thread" :key="i" class="copilot-entry">
          <div class="copilot-question">{{ entry.question }}</div>
          <div v-if="entry.pending" class="copilot-answer copilot-answer--pending">Thinking…</div>
          <div v-else class="copilot-answer" :class="{ 'copilot-answer--degraded': !entry.ok }">
            {{ entry.text }}
          </div>
        </div>
      </div>

      <form class="copilot-input-row" @submit.prevent="submit">
        <input
          v-model="question"
          type="text"
          class="copilot-input"
          placeholder="Ask about today's operations…"
          :disabled="sending"
          maxlength="500"
        />
        <Button variant="primary" size="md" :processing="sending" :disabled="!question.trim()">
          Ask
        </Button>
      </form>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, nextTick } from 'vue';
import AppLayout from '../Components/AppLayout.vue';
import Button from '../Components/Button.vue';

const question = ref('');
const sending = ref(false);
const thread = ref([]);
const threadEl = ref(null);

const suggestions = [
  'What movements are delayed right now?',
  'What is in progress right now?',
  'What is happening in the next 2 hours?',
  'Give me a status summary',
];

function scrollToBottom() {
  nextTick(() => {
    if (threadEl.value) threadEl.value.scrollTop = threadEl.value.scrollHeight;
  });
}

async function ask(text) {
  const q = text.trim();
  if (!q || sending.value) return;

  sending.value = true;
  const entry = { question: q, pending: true, ok: true, text: '' };
  thread.value.push(entry);
  scrollToBottom();

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  try {
    const response = await fetch('/ai/query', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
      },
      body: JSON.stringify({ question: q }),
    });

    const data = await response.json();
    entry.pending = false;
    entry.ok = data.ok !== false;
    entry.text = data.ok ? data.answer : (data.message || 'Something went wrong.');
  } catch (e) {
    entry.pending = false;
    entry.ok = false;
    entry.text = 'Could not reach the AI Copilot. Please try again.';
  } finally {
    sending.value = false;
    scrollToBottom();
  }
}

function submit() {
  const q = question.value;
  question.value = '';
  ask(q);
}
</script>

<style scoped>
.page-header { margin-bottom: 20px; }
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.copilot-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
  display: flex; flex-direction: column;
  height: calc(100vh - 220px);
  max-height: 720px;
}

.copilot-thread {
  flex: 1; overflow-y: auto; padding: 16px;
  display: flex; flex-direction: column; gap: 16px;
}

.copilot-empty { color: var(--ink3); font-size: 13px; }
.copilot-suggestions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.suggestion-chip {
  padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; cursor: pointer; color: var(--ink2);
}
.suggestion-chip:hover { background: var(--panel); color: var(--ink); }

.copilot-entry { display: flex; flex-direction: column; gap: 6px; }
.copilot-question {
  align-self: flex-end; max-width: 80%;
  background: var(--accent); color: #fff;
  padding: 8px 14px; border-radius: 12px 12px 2px 12px;
  font-size: 13.5px;
}
.copilot-answer {
  align-self: flex-start; max-width: 80%;
  background: var(--panel); color: var(--ink);
  padding: 10px 14px; border-radius: 12px 12px 12px 2px;
  font-size: 13.5px; white-space: pre-wrap;
}
.copilot-answer--pending { color: var(--ink3); font-style: italic; }
.copilot-answer--degraded { background: var(--warn-soft); color: var(--warn); }

.copilot-input-row {
  display: flex; gap: 8px; padding: 12px 16px;
  border-top: 1px solid var(--border); background: var(--surface);
}
.copilot-input {
  flex: 1; padding: 9px 12px; border-radius: 8px;
  border: 1px solid var(--border); font-size: 13.5px; color: var(--ink);
  background: none;
}
.copilot-input:focus { outline: none; border-color: var(--accent); }
</style>
