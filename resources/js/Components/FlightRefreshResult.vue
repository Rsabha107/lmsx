<template>
  <Modal :show="!!result" max-width="720px" @close="emit('close')">
    <template #title>Flights refreshed</template>
    <div v-if="result" class="fr">
      <p class="fr-sum">
        <strong>{{ result.refreshed }}</strong> refreshed<template v-if="result.failed"> · <strong class="fr-bad">{{ result.failed }}</strong> not found</template>
      </p>
      <div v-if="chips.length" class="fr-chips">
        <span v-for="c in chips" :key="c.label" :class="['fr-chip', `fr-chip--${c.tone}`]"><strong>{{ c.n }}</strong> {{ c.label }}</span>
      </div>
      <p v-else class="fr-none">No flight time changed, so no plan or job moved.</p>

      <template v-if="changes.length">
        <div class="fr-head">What changed</div>
        <FlightChangesList :changes="changes" />
      </template>

      <div class="fr-head">Flights</div>
      <ul class="fr-list">
        <li v-for="r in rows.listed" :key="r.id" :class="{ 'fr-row-bad': !r.ok }">
          <span class="fr-flight">{{ r.team }} · {{ r.flight_number }}</span>
          <span class="fr-dir">{{ r.direction }}</span>
          <span>{{ r.ok ? `${r.change ? 'New time' : 'Updated'} from ${r.source === 'oag' ? 'OAG' : 'AviationStack'}` : r.error }}</span>
        </li>
        <li v-if="rows.unchanged.length" class="fr-unchanged">
          <span>{{ rows.unchanged.length }} unchanged:</span>
          <span class="fr-flight">{{ rows.unchanged.map((r) => r.flight_number).join(', ') }}</span>
        </li>
      </ul>
    </div>
    <template #footer>
      <Button variant="primary" size="sm" @click="emit('close')">Close</Button>
    </template>
  </Modal>
</template>

<script setup>
import { computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import Button from '@/Components/Button.vue';
import FlightChangesList from '@/Components/FlightChangesList.vue';

// The result of a flight refresh (useFlightRefresh): the figures, what the new times did to plans and jobs,
// then each flight's outcome. Used wherever flights can be refreshed.
const props = defineProps({ result: { type: Object, default: null } });
const emit = defineEmits(['close']);

const plural = (n, one, many) => (n === 1 ? one : many);
const chips = computed(() => {
  const s = props.result?.summary ?? {};
  return [
    { n: s.flights_changed, label: plural(s.flights_changed, 'flight changed', 'flights changed'), tone: 'info' },
    { n: s.movements_updated, label: plural(s.movements_updated, 'planned movement re-timed', 'planned movements re-timed'), tone: 'info' },
    { n: s.jobs_retimed, label: plural(s.jobs_retimed, 'job re-timed', 'jobs re-timed'), tone: 'info' },
    { n: s.jobs_review, label: plural(s.jobs_review, 'started job needs review', 'started jobs need review'), tone: 'warn' },
    { n: s.clashes, label: plural(s.clashes, 'crew clash to check', 'crew clashes to check'), tone: 'bad' },
  ].filter((c) => c.n > 0);
});
const changes = computed(() => (props.result?.results ?? []).filter((r) => r.change).map((r) => r.change));
// Failed and changed flights one by one; the rest folded into one line.
const rows = computed(() => {
  const all = props.result?.results ?? [];
  return { listed: all.filter((r) => !r.ok || r.change), unchanged: all.filter((r) => r.ok && !r.change) };
});
</script>

<style scoped>
.fr { display: flex; flex-direction: column; gap: 12px; }
.fr-sum { margin: 0; font-size: 13.5px; color: var(--ink2); }
.fr-bad { color: var(--danger); }
.fr-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.fr-chip { padding: 4px 10px; border-radius: 999px; font-size: 12px; }
.fr-chip--info { background: #DBEAFE; color: #1E40AF; }
.fr-chip--warn { background: #FEF3C7; color: #92400E; }
.fr-chip--bad { background: var(--danger-soft); color: var(--danger); }
.fr-none { margin: 0; font-size: 12.5px; color: var(--ink3); }
.fr-head { font-family: var(--mono, ui-monospace, monospace); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--ink3); margin-top: 4px; }
.fr-list { list-style: none; margin: 0; padding: 0; max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; }
.fr-list li { display: grid; grid-template-columns: 150px 80px 1fr; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--ink2); }
.fr-list li:last-child { border-bottom: none; }
.fr-list li.fr-unchanged { grid-template-columns: auto 1fr; color: var(--ink3); }
.fr-flight { font-family: var(--mono, ui-monospace, monospace); color: var(--ink); }
.fr-dir { color: var(--ink3); text-transform: capitalize; }
.fr-row-bad { background: color-mix(in srgb, var(--danger-soft) 60%, transparent); }
</style>
