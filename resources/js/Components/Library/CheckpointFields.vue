<template>
  <div class="cf">
    <div class="cf-field">
      <span class="cf-label" aria-hidden="true">Name</span>
      <input ref="nameInput" v-model="cp.name" type="text" aria-label="Name" placeholder="e.g. KIT LOADED AT HOTEL" :class="{ 'cf-bad': errors.name }" />
      <span v-if="errors.name" class="cf-err">{{ msg(errors.name) }}</span>
      <span v-if="withCode" class="cf-code">
        Code
        <input v-model="cp.code" type="text" aria-label="Code" :class="{ 'cf-bad': errors.code }" @input="cp.code = cp.code.toUpperCase()" />
      </span>
      <span v-else class="cf-hint">Code assigned automatically</span>
      <span v-if="errors.code" class="cf-err">{{ msg(errors.code) }}</span>
    </div>

    <label class="cf-field">
      <span class="cf-label">Description <small>optional</small></span>
      <textarea v-model="cp.description" rows="3" placeholder="What the crew must do at this checkpoint" />
      <span class="cf-hint">Shown to supervisors on the mobile job screen.</span>
    </label>

    <div class="cf-two">
      <label class="cf-field">
        <span class="cf-label">Category</span>
        <select v-model="cp.category">
          <option v-for="c in CATEGORIES" :key="c" :value="c">{{ categoryLabel(c) }}</option>
        </select>
      </label>
      <label class="cf-field">
        <span class="cf-label">Type</span>
        <select v-model="cp.type">
          <option v-for="t in typeOptions" :key="t" :value="t">{{ typeLabel(t) }}</option>
        </select>
      </label>
    </div>

    <div class="cf-field">
      <span id="cf-capture" class="cf-label">Capture</span>
      <div class="cf-seg" role="radiogroup" aria-labelledby="cf-capture">
        <button v-for="m in CAPTURE_METHODS" :key="m" type="button" role="radio" :aria-checked="cp.capture_method === m"
          :class="['cf-seg-btn', { 'cf-seg-btn--on': cp.capture_method === m }]" @click="cp.capture_method = m">{{ captureLabel(m) }}</button>
      </div>
    </div>

    <div class="cf-field">
      <span class="cf-label">Staff must also record</span>
      <div class="cf-toggles">
        <button v-for="r in RECORDS" :key="r.field" type="button" :aria-pressed="!!cp[r.field]"
          :class="['cf-toggle', { 'cf-toggle--on': cp[r.field] }]" @click="cp[r.field] = !cp[r.field]">
          {{ cp[r.field] ? '✓' : '+' }} {{ r.label }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { CATEGORIES, TYPES, CAPTURE_METHODS, categoryLabel, typeLabel, captureLabel } from './checkpointOptions';

// The checkpoint form shared by the edit panel and the New checkpoint dialog.
const cp = defineModel({ type: Object, required: true });
const props = defineProps({
  errors: { type: Object, default: () => ({}) },
  withCode: { type: Boolean, default: false },
  autofocus: { type: Boolean, default: false },
});

const RECORDS = [
  { field: 'requires_photo', label: 'Photo' },
  { field: 'requires_signature', label: 'Signature' },
  { field: 'requires_baggage_count', label: 'Bag count' },
];
// Older checkpoints may carry a type the form no longer offers; keep it selectable.
const typeOptions = computed(() => (TYPES.includes(cp.value.type) || !cp.value.type ? TYPES : [...TYPES, cp.value.type]));
const msg = (e) => (Array.isArray(e) ? e[0] : e);

const nameInput = ref(null);
onMounted(() => { if (props.autofocus) nameInput.value?.focus(); });
</script>

<style scoped>
.cf { display: flex; flex-direction: column; gap: 14px; }
.cf-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.cf-label { font-size: 11px; font-weight: 600; color: var(--ink3); }
.cf-label small { font-weight: 400; color: var(--ink4); }
.cf-field input, .cf-field textarea, .cf-field select {
  width: 100%; padding: 0 10px; border: 1px solid var(--border-strong); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px; font-family: inherit;
}
.cf-field input, .cf-field select { height: 38px; }
.cf-field select { padding: 0 8px; }
.cf-field textarea { padding: 9px 10px; resize: vertical; }
.cf-field input:focus, .cf-field textarea:focus, .cf-field select:focus { outline: none; border-color: var(--ink); }
.cf-field .cf-bad { border-color: var(--danger); }
.cf-err { font-size: 11px; color: var(--danger-strong); }
.cf-hint { font-size: 11px; color: var(--ink4); }
.cf-code { display: flex; align-items: center; gap: 6px; font-size: 11px; color: var(--ink4); }
.cf-field .cf-code input {
  width: 110px; height: 26px; padding: 0 8px; font-family: var(--mono, ui-monospace, monospace);
  font-size: 11px; font-weight: 600; text-transform: uppercase;
}
.cf-two { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.cf-seg { display: flex; gap: 3px; padding: 3px; border-radius: 8px; background: var(--panel); }
.cf-seg-btn {
  flex: 1; min-width: 0; height: 30px; padding: 0 4px; border: 0; border-radius: 6px; background: transparent;
  color: var(--ink3); font-size: 12px; font-weight: 500; cursor: pointer; white-space: nowrap;
}
.cf-seg-btn:hover { color: var(--ink); }
.cf-seg-btn--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }
.cf-toggles { display: flex; gap: 6px; flex-wrap: wrap; }
.cf-toggle {
  height: 30px; padding: 0 10px; border-radius: 999px; border: 1px solid var(--border-strong);
  background: var(--surface); color: var(--ink2); font-size: 12px; cursor: pointer;
}
.cf-toggle:hover { background: var(--panel); }
.cf-toggle--on, .cf-toggle--on:hover { background: var(--ink); border-color: var(--ink); color: var(--surface); }
</style>
