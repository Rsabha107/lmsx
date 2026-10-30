<template>
  <div class="fja">
    <dl class="fja-rows">
      <template v-if="detail.flight?.number || flightSpan">
        <dt>Flight</dt>
        <dd>
          <span v-if="detail.flight?.number" class="fja-mono fja-strong">{{ detail.flight.number }}</span>
          <template v-if="flightSpan">
            <span class="fja-old">{{ flightSpan.from }}</span><span class="fja-arrow">→</span><strong class="fja-mono">{{ flightSpan.to }}</strong>
            <span v-if="flightSpan.day" class="fja-day">{{ flightSpan.day }}</span>
          </template>
          <span v-if="detail.flight?.shift" class="fja-shift">{{ detail.flight.shift }}</span>
        </dd>
      </template>
      <template v-if="startSpan">
        <dt>Job start</dt>
        <dd>
          <template v-if="detail.kind === 'retimed'">
            <span class="fja-old">{{ startSpan.from }}</span><span class="fja-arrow">→</span><strong class="fja-mono">{{ startSpan.to }}</strong>
          </template>
          <template v-else>
            <strong class="fja-mono">{{ startSpan.from || startSpan.to }}</strong>
            <span class="fja-kept">kept: the job has started</span>
          </template>
          <span v-if="startSpan.day" class="fja-day">{{ startSpan.day }}</span>
        </dd>
      </template>
    </dl>

    <div v-if="detail.clashes?.length" class="fja-clashes">
      <div class="fja-clashes-head">Check at the new time</div>
      <ul>
        <li v-for="(c, i) in detail.clashes" :key="i">
          <div v-if="c.type || split(c.text).note" class="fja-clash-head">
            <span v-if="c.type" class="fja-type">{{ c.type }}</span>
            <span v-if="split(c.text).note" class="fja-note">{{ split(c.text).note }}</span>
          </div>
          <span class="fja-text">{{ split(c.text).main }}</span>
        </li>
      </ul>
    </div>
    <p v-else-if="detail.kind === 'review'" class="fja-hint">Check it, and adjust it from the Jobs Queue (More → Override) if needed.</p>

    <Link v-if="detail.url" :href="detail.url" class="fja-link">Open {{ detail.job_ref }} →</Link>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { splitClash as split, timeSpan } from '@/Composables/flightChangeFormat';

// A "Job re-timed" / "Started job needs review" alert (NotificationFeedService detail): the flight's
// old → new time, the job's start, and the crew clashes at the new time, one per line.
const props = defineProps({ detail: { type: Object, required: true } });

const span = (s) => (s ? timeSpan(s.from, s.to) : null);
const flightSpan = computed(() => span(props.detail.flight));
const startSpan = computed(() => span(props.detail.start));
</script>

<style scoped>
.fja { display: flex; flex-direction: column; gap: 10px; margin-top: 4px; font-size: 13px; color: var(--ink2); }
.fja-rows { display: grid; grid-template-columns: 76px 1fr; gap: 6px 10px; margin: 0; }
.fja-rows dt { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--ink4); padding-top: 2px; }
.fja-rows dd { margin: 0; display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px; }
.fja-mono { font-family: var(--mono, ui-monospace, monospace); }
.fja-strong { font-weight: 700; color: var(--ink); }
.fja-old { font-family: var(--mono, ui-monospace, monospace); color: var(--ink4); text-decoration: line-through; }
.fja-arrow { color: var(--ink4); }
.fja-day { font-size: 12px; color: var(--ink3); }
.fja-shift { padding: 1px 7px; border-radius: 999px; background: #DBEAFE; color: #1E40AF; font-size: 11.5px; font-weight: 600; }
.fja-kept { font-size: 12px; color: var(--warn); font-weight: 600; }
.fja-clashes { border: 1px solid color-mix(in srgb, var(--warn) 35%, var(--border)); border-radius: 8px; background: color-mix(in srgb, #FEF3C7 45%, var(--surface)); padding: 8px 10px; }
.fja-clashes-head { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #92400E; margin-bottom: 6px; }
.fja-clashes ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
.fja-clashes li { display: flex; flex-direction: column; gap: 3px; padding: 7px 0; line-height: 1.45; }
.fja-clashes li + li { border-top: 1px dashed color-mix(in srgb, var(--warn) 30%, var(--border)); }
.fja-clashes li:first-child { padding-top: 0; }
.fja-clashes li:last-child { padding-bottom: 0; }
.fja-clash-head { display: flex; align-items: center; gap: 8px; }
.fja-type { font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 4px; background: #FDE68A; color: #78350F; white-space: nowrap; }
.fja-text { color: var(--ink); }
.fja-note { font-size: 12px; font-weight: 600; color: var(--danger); }
.fja-hint { margin: 0; font-size: 12.5px; color: var(--ink3); }
.fja-link { align-self: flex-start; font-size: 12.5px; font-weight: 600; color: var(--accent); text-decoration: none; }
.fja-link:hover { text-decoration: underline; }
</style>
