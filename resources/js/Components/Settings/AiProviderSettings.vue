<template>
  <div class="ai">
    <p class="ai-note">
      The provider Daleel (the AI assistant) and the AI imports (sheet column matching, PDF tables) use.
      Keys and endpoints are set on the server; only a provider that is set up can be chosen.
    </p>

    <div class="ai-options" role="radiogroup" aria-label="AI provider">
      <label v-for="o in ai.options" :key="o.value"
        :class="['ai-card', { 'ai-card--on': picked === o.value, 'ai-card--off': !o.configured }]">
        <input v-model="picked" type="radio" name="ai-provider" :value="o.value" :disabled="!o.configured" />
        <div class="ai-card-body">
          <div class="ai-card-title">
            <span class="ai-name">{{ o.label }}</span>
            <span v-if="ai.current === o.value" class="ai-tag ai-tag--current">In use</span>
            <span v-if="!o.configured" class="ai-tag ai-tag--warn">Not set up</span>
          </div>
          <div class="ai-meta">
            <span v-if="o.endpoint">Endpoint <strong>{{ o.endpoint }}</strong></span>
            <span v-if="o.model">Model <strong>{{ o.model }}</strong></span>
          </div>
          <div v-if="!o.configured" class="ai-env">Needs {{ o.env }} in the environment.</div>
          <div class="ai-test">
            <button type="button" class="ai-test-btn" :disabled="!o.configured || testing === o.value" @click.prevent="test(o.value)">
              {{ testing === o.value ? 'Testing…' : 'Test connection' }}
            </button>
            <span v-if="results[o.value]" :class="['ai-result', results[o.value].ok ? 'ai-result--ok' : 'ai-result--bad']">
              <template v-if="results[o.value].ok">✓ Connected · {{ results[o.value].model }} · {{ results[o.value].ms }} ms</template>
              <template v-else>✕ {{ results[o.value].message }}</template>
            </span>
          </div>
        </div>
      </label>
    </div>

    <p class="ai-note ai-note--small">
      The flight schedule lookup (Event Teams, for flights too far ahead for live tracking) searches the web,
      which only Anthropic offers here, so it keeps using Anthropic<template v-if="!ai.webSearch"> and is off until Anthropic is set up</template>.
    </p>

    <div class="ai-foot">
      <span :class="['ai-dirty', { 'ai-dirty--on': dirty }]">{{ dirty ? 'Unsaved change' : 'All changes saved' }}</span>
      <Button variant="primary" size="sm" :disabled="!dirty || saving" :processing="saving" @click="save">Use this provider</Button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import Button from '@/Components/Button.vue';

const props = defineProps({
  ai: { type: Object, required: true },
});

const picked = ref(props.ai.current);
const saving = ref(false);
const testing = ref(null);
const results = ref({});

watch(() => props.ai.current, (v) => { picked.value = v; });
const dirty = computed(() => picked.value !== props.ai.current);

async function test(provider) {
  testing.value = provider;
  try {
    const { data } = await axios.post('/setups/settings/ai-provider/test', { provider });
    results.value = { ...results.value, [provider]: data };
  } catch (e) {
    const message = e.response?.status === 429 ? 'Too many tests, wait a minute.' : (e.response?.data?.message ?? 'Request failed.');
    results.value = { ...results.value, [provider]: { ok: false, message } };
  } finally {
    testing.value = null;
  }
}

function save() {
  saving.value = true;
  router.post('/setups/settings/ai-provider', { provider: picked.value }, {
    preserveScroll: true,
    onFinish: () => { saving.value = false; },
  });
}
</script>

<style scoped>
.ai { display: flex; flex-direction: column; gap: 14px; padding: 20px 28px; }
.ai-note { margin: 0; font-size: 13px; color: var(--ink3); line-height: 1.5; }
.ai-note--small { font-size: 12px; color: var(--ink4); }
.ai-options { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 12px; }
.ai-card {
  display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; cursor: pointer;
  border: 1px solid var(--border); border-radius: 10px; background: var(--surface);
}
.ai-card--on { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
.ai-card--off { cursor: default; background: var(--panel); }
.ai-card input { margin-top: 3px; accent-color: var(--accent); }
.ai-card-body { display: flex; flex-direction: column; gap: 6px; min-width: 0; flex: 1; }
.ai-card-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ai-name { font-size: 14px; font-weight: 700; color: var(--ink); }
.ai-tag { font-size: 10.5px; font-weight: 700; padding: 1px 7px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.04em; }
.ai-tag--current { background: var(--accent-soft); color: var(--accent-fg); }
.ai-tag--warn { background: var(--warn-soft, #fef3c7); color: var(--warn, #b45309); }
.ai-meta { display: flex; flex-direction: column; gap: 2px; font-size: 12px; color: var(--ink3); }
.ai-meta strong { color: var(--ink2); font-weight: 600; word-break: break-all; }
.ai-env { font-size: 12px; color: var(--ink4); }
.ai-test { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 2px; }
.ai-test-btn {
  height: 28px; padding: 0 10px; font-size: 12px; font-weight: 600; cursor: pointer;
  border: 1px solid var(--border); border-radius: 6px; background: var(--surface); color: var(--ink2);
}
.ai-test-btn:hover:not(:disabled) { border-color: var(--border-strong); color: var(--ink); }
.ai-test-btn:disabled { opacity: 0.5; cursor: default; }
.ai-result { font-size: 12px; }
.ai-result--ok { color: var(--ok); font-weight: 600; }
.ai-result--bad { color: var(--danger, #b91c1c); }
.ai-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.ai-dirty { font-size: 12px; color: var(--ink4); }
.ai-dirty--on { color: var(--warn); font-weight: 600; }
</style>
