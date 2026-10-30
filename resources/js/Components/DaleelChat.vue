<template>
  <div class="dc">
    <div ref="threadEl" class="dc-thread">
      <div v-if="state.thread.length === 0" class="dc-empty">
        <p>Try asking:</p>
        <div class="dc-suggestions">
          <button v-for="s in suggestions" :key="s" type="button" class="dc-chip" @click="ask(s)">{{ s }}</button>
        </div>
      </div>

      <div v-for="(entry, i) in state.thread" :key="i" class="dc-entry">
        <div class="dc-question">{{ entry.question }}</div>
        <div v-if="entry.pending" class="dc-answer dc-answer--pending">
          <span class="dc-who"><span class="dc-avatar">د</span>Daleel</span>
          <span class="dc-typing">Looking it up<span>.</span><span>.</span><span>.</span></span>
        </div>
        <div v-else class="dc-answer" :class="{ 'dc-answer--degraded': !entry.ok }">
          <span class="dc-who"><span class="dc-avatar">د</span>Daleel</span>
          <markdown-answer :text="entry.text" />
        </div>
      </div>
    </div>

    <form class="dc-input-row" @submit.prevent="submit">
      <input
        ref="inputEl"
        v-model="question"
        type="text"
        class="dc-input"
        placeholder="Ask Daleel about today's operations…"
        aria-label="Ask Daleel"
        :disabled="state.sending"
        maxlength="500"
      />
      <Button variant="primary" size="md" :processing="state.sending" :disabled="!question.trim()">Ask</Button>
    </form>
  </div>
</template>

<script setup>
import { ref, watch, nextTick, onMounted } from 'vue';
import Button from './Button.vue';
import MarkdownAnswer from './MarkdownAnswer.vue';
import { useDaleel } from '../Composables/useDaleel';

const props = defineProps({
  autofocus: { type: Boolean, default: false },
});

const { state, ask } = useDaleel();
const question = ref('');
const threadEl = ref(null);
const inputEl = ref(null);

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

// New question, or an answer arriving.
watch(() => [state.thread.length, state.sending], scrollToBottom);

onMounted(() => {
  scrollToBottom();
  if (props.autofocus) nextTick(() => inputEl.value?.focus());
});

function submit() {
  const q = question.value;
  question.value = '';
  ask(q);
}

defineExpose({ focus: () => inputEl.value?.focus() });
</script>

<style scoped>
.dc { display: flex; flex-direction: column; height: 100%; min-height: 0; }
.dc-thread { flex: 1; min-height: 0; overflow-y: auto; padding: 16px; display: flex; flex-direction: column; gap: 16px; background: color-mix(in srgb, var(--panel) 55%, var(--surface)); }
.dc-empty { color: var(--ink3); font-size: 13px; }
.dc-empty p { margin: 0; }
.dc-suggestions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.dc-chip {
  padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; cursor: pointer; color: var(--ink2);
}
.dc-chip:hover { background: var(--panel); color: var(--ink); }
.dc-entry { display: flex; flex-direction: column; gap: 6px; }
.dc-question {
  align-self: flex-end; max-width: 80%;
  background: var(--accent); color: #fff;
  padding: 8px 14px; border-radius: 12px 12px 2px 12px; font-size: 13.5px;
}
.dc-answer {
  align-self: stretch; max-width: 100%;
  background: var(--surface); color: var(--ink);
  border: 1px solid var(--border); box-shadow: 0 1px 2px rgba(17, 24, 39, 0.04);
  padding: 10px 14px 12px; border-radius: 12px 12px 12px 2px; font-size: 13.5px;
  display: flex; flex-direction: column; gap: 8px;
  /* A wide table scrolls rather than stretching the thread. */
  overflow-x: auto;
}
.dc-who { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--ink3); letter-spacing: .03em; }
.dc-avatar {
  width: 18px; height: 18px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
  background: var(--accent); color: #fff; font-size: 11px; font-weight: 700;
}
.dc-answer--pending { align-self: flex-start; color: var(--ink3); }
.dc-typing { font-style: italic; }
.dc-typing span { animation: dc-blink 1.2s infinite both; }
.dc-typing span:nth-child(2) { animation-delay: .2s; }
.dc-typing span:nth-child(3) { animation-delay: .4s; }
@keyframes dc-blink { 0%, 80%, 100% { opacity: .2; } 40% { opacity: 1; } }
.dc-answer--degraded { background: var(--warn-soft); color: var(--warn); border-color: transparent; }
.dc-input-row { display: flex; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border); background: var(--surface); }
.dc-input {
  flex: 1; padding: 9px 12px; border-radius: 8px;
  border: 1px solid var(--border); font-size: 13.5px; color: var(--ink); background: none;
}
.dc-input:focus { outline: none; border-color: var(--accent); }
</style>
