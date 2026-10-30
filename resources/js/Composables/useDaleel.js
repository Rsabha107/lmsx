import { reactive } from 'vue';

// One Daleel conversation for the whole session: the floating window and the /ai page share it,
// and it survives moving between pages (Inertia keeps this module loaded).
const state = reactive({
  open: false,
  minimized: false,
  // An answer arrived while the window was minimised.
  unread: false,
  sending: false,
  thread: [],
});

// context: what the user is looking at ({ page, date, plan_id, job_id, movement_id }); checked on the server.
async function ask(text, context = null) {
  const q = (text ?? '').trim();
  if (!q || state.sending) return;

  state.sending = true;
  // The last few finished exchanges, so a follow-up like "the first one" has context.
  const history = state.thread
    .filter((e) => !e.pending && e.ok && e.text)
    .slice(-5)
    .flatMap((e) => [
      { role: 'user', content: e.question },
      { role: 'assistant', content: e.text.slice(0, 3500) },
    ]);
  state.thread.push({ question: q, pending: true, ok: true, text: '' });
  const entry = state.thread[state.thread.length - 1];

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  try {
    const response = await fetch('/ai/query', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
      },
      body: JSON.stringify(context ? { question: q, history, context } : { question: q, history }),
    });

    const data = await response.json();
    entry.pending = false;
    entry.ok = data.ok !== false;
    entry.text = data.ok ? data.answer : (data.message || 'Something went wrong.');
  } catch (e) {
    entry.pending = false;
    entry.ok = false;
    entry.text = 'Could not reach Daleel. Please try again.';
  } finally {
    state.sending = false;
    if (state.minimized) state.unread = true;
  }
}

function clear() {
  if (!state.sending) state.thread.splice(0);
}

export function useDaleel() {
  return {
    state,
    ask,
    clear,
    open: () => { state.open = true; state.minimized = false; state.unread = false; },
    close: () => { state.open = false; state.minimized = false; state.unread = false; },
    minimize: () => { state.minimized = true; },
    // An "Ask Daleel" button on a page: open the window and ask straight away.
    askAbout: (question, context = null) => {
      state.open = true;
      state.minimized = false;
      state.unread = false;
      ask(question, context);
    },
    restore: () => { state.minimized = false; state.unread = false; },
  };
}
