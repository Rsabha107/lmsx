<template>
  <div v-if="flight && !flight.is_bus && flight.flight_number" class="fl">
    <span class="fl-num">{{ flight.flight_number }}</span>
    <span v-if="route" class="fl-route">{{ route }}</span>
    <span :class="['fl-chip', `fl-chip--${chip.tone}`]">{{ chip.text }}</span>
    <span v-if="fresh" :class="['fl-fresh', `fl-fresh--${fresh.tone}`]" :title="fresh.title">{{ fresh.text }}</span>
    <button v-if="canRefresh" type="button" class="fl-refresh" :disabled="refreshing" :aria-label="`Refresh ${flight.flight_number}`"
      :title="`Refresh ${flight.flight_number} from OAG now (a new time re-times its plans and jobs)`" @click.stop="emit('refresh')">
      <svg :class="{ 'fl-spin': refreshing }" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8" /><path d="M21 3v5h-5" /></svg>
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { flightFreshness, fromSummary, wallMinutes } from '@/Composables/flightFreshness';

// One flight (a FlightSummary) on a job: number, route, status and how fresh its data is, with ↻ for those
// who may refresh it (the parent runs useFlightRefresh and shows the result).
const props = defineProps({
  flight: { type: Object, default: null },
  canRefresh: { type: Boolean, default: false },
  refreshing: { type: Boolean, default: false },
});
const emit = defineEmits(['refresh']);
const page = usePage();

const route = computed(() => {
  const f = props.flight;
  return f?.origin_airport || f?.destination_airport ? `${f.origin_airport || '?'} → ${f.destination_airport || '?'}` : '';
});

const chip = computed(() => {
  const f = props.flight ?? {};
  const arrival = f.direction === 'arrival';
  if (f.flight_status === 'cancelled') return { tone: 'bad', text: 'Cancelled' };
  if (f.actual_time) return { tone: 'ok', text: `${arrival ? 'Landed' : 'Departed'} ${f.actual_time}` };
  if (f.flight_status === 'active') return { tone: 'live', text: f.estimated_time ? `In the air · ETA ${f.estimated_time}` : 'In the air' };
  if ((f.delay_minutes ?? 0) >= 15 && f.estimated_time) return { tone: 'warn', text: `Delayed · ${f.estimated_time}` };
  return { tone: 'plan', text: `${arrival ? 'Arr' : 'Dep'} ${f.scheduled_time ?? '--:--'}` };
});

const fresh = computed(() => {
  const status = page.props.flightStatus ?? {};
  const now = wallMinutes(status.now) ?? Math.floor(Date.now() / 60000);
  return flightFreshness(fromSummary(props.flight), now, status.limits ?? {});
});
</script>

<style scoped>
.fl { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 8px; font-size: 11.5px; color: var(--ink2); }
.fl-num { font-family: var(--mono, ui-monospace, monospace); font-weight: 700; color: var(--ink); }
.fl-route { font-family: var(--mono, ui-monospace, monospace); color: var(--ink3); }
.fl-chip { padding: 1px 7px; border-radius: 999px; font-weight: 600; font-size: 11px; }
.fl-chip--plan { background: var(--panel); color: var(--ink2); }
.fl-chip--ok { background: var(--ok-soft, #DCFCE7); color: var(--ok); }
.fl-chip--live { background: #DBEAFE; color: #1E40AF; }
.fl-chip--warn { background: #FEF3C7; color: #92400E; }
.fl-chip--bad { background: var(--danger-soft); color: var(--danger); }
.fl-fresh { font-size: 11px; }
.fl-fresh--ok, .fl-fresh--idle { color: var(--ink3); }
.fl-fresh--warn { color: var(--warn); font-weight: 600; }
.fl-fresh--bad { color: var(--danger); font-weight: 600; }
.fl-refresh { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; padding: 0; border: 1px solid var(--border); border-radius: 5px; background: var(--surface); color: var(--ink2); cursor: pointer; }
.fl-refresh:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.fl-refresh:disabled { opacity: .6; cursor: default; }
.fl-spin { animation: fl-spin 1s linear infinite; }
@keyframes fl-spin { to { transform: rotate(360deg); } }
</style>
