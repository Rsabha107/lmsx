<template>
  <section class="pc" :class="{ 'pc--collapsed': collapsed }" aria-label="Plan checklist">
    <header class="pc-head">
      <div class="pc-title">
        <span class="pc-kicker">Plan checklist</span>
        <span class="pc-count">{{ r.done }} of {{ r.steps.length }} done</span>
        <span class="pc-bar" aria-hidden="true">
          <span v-for="s in r.steps" :key="s.key" :class="['pc-seg', `pc-seg--${s.state}`]" />
        </span>
        <span v-if="collapsed && r.next" class="pc-next-inline">Next: <strong>{{ r.next.label }}</strong> · {{ r.next.detail }}</span>
        <span v-else-if="collapsed" class="pc-next-inline pc-next-inline--ok">Ready: every movement has a job</span>
      </div>
      <div class="pc-head-actions">
        <AskDaleelButton v-if="r.total && r.next" question="Why can't this plan generate all its jobs yet? What is blocking it and what should I do first?"
          :context="{ page: 'planning', plan_id: plan.id }" label="Ask Daleel what's blocking it" />
        <button type="button" class="pc-toggle" :aria-expanded="!collapsed" @click="toggle">
          {{ collapsed ? 'Show steps' : 'Hide' }}
        </button>
      </div>
    </header>

    <ol v-if="!collapsed" class="pc-steps">
      <li v-for="(s, i) in r.steps" :key="s.key"
        :class="['pc-step', `pc-step--${s.state}`, { 'pc-step--next': r.next?.key === s.key }]">
        <div class="pc-step-top">
          <span class="pc-mark" aria-hidden="true">{{ s.state === 'done' ? '✓' : (s.state === 'warn' ? '!' : i + 1) }}</span>
          <span v-if="r.next?.key === s.key" class="pc-next">Next</span>
        </div>
        <span class="pc-label">{{ s.label }}</span>
        <span class="pc-detail">{{ s.detail }}</span>

        <div class="pc-actions">
          <button v-if="s.key === 'setup' && canManage" type="button" class="pc-act" @click="emit('edit')">Edit plan</button>

          <template v-else-if="s.key === 'movements'">
            <button v-if="r.total" type="button" class="pc-act" @click="emit('movements')">Show movements</button>
            <button v-else-if="canManage" type="button" class="pc-act" @click="emit('add-movement')">Add movement</button>
          </template>

          <button v-else-if="s.key === 'conflicts' && r.total" type="button" class="pc-act" @click="emit('conflicts')">
            {{ r.conflicts ? 'Review conflicts' : 'View conflicts' }}
          </button>

          <template v-else-if="s.key === 'crew' && r.total && s.state !== 'done'">
            <Link v-if="canAssignCrew" :href="crewHref" class="pc-act">Open Crew Assignment</Link>
            <button v-else type="button" class="pc-act" @click="emit('movements')">Set in movements</button>
          </template>

          <template v-else-if="s.key === 'jobs' && r.total && s.state !== 'done' && canManage">
            <button v-if="canRefreshFlights && planFlights.length" type="button" class="pc-act" :disabled="flightsRefreshing"
              :title="`Refresh the ${planFlights.length} flight${planFlights.length === 1 ? '' : 's'} of movements without a job from OAG, so jobs are generated from current times`"
              @click="refreshFlights(planFlights)">
              {{ flightsRefreshing ? 'Refreshing…' : `Refresh flights (${planFlights.length})` }}
            </button>
            <button type="button" class="pc-act pc-act--primary"
              :disabled="!r.readyForJobs" :title="r.readyForJobs ? '' : 'A movement needs a vehicle and a supervisor (and a flight for arrivals / departures) first'"
              @click="emit('generate')">
              Generate jobs<template v-if="r.readyForJobs"> ({{ r.readyForJobs }})</template>
            </button>
          </template>
        </div>
      </li>
    </ol>
    <FlightRefreshResult :result="flightResult" @close="flightResult = null" />
  </section>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { planReadiness } from '../../Composables/planReadiness';
import { useFlightRefresh } from '../../Composables/useFlightRefresh';
import AskDaleelButton from '../AskDaleelButton.vue';
import FlightRefreshResult from '../FlightRefreshResult.vue';

// A per-plan guide from set-up to jobs; each step links to the screen that does it. The full editor is unchanged.
const props = defineProps({
  plan: { type: Object, required: true },
  openConflicts: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: true },
});
const emit = defineEmits(['edit', 'movements', 'add-movement', 'conflicts', 'generate']);

const page = usePage();
const canAssignCrew = computed(() => page.props.auth?.can?.['movements.assign-crew'] === true);
const r = computed(() => planReadiness(props.plan, props.openConflicts));
const crewHref = computed(() => `/crew-assignment?date=${String(props.plan.date ?? '').slice(0, 10)}`);

// Flights of the plan's arrivals / departures that have no job yet (not landed or cancelled): refreshed
// before generating, so the jobs start from current times. Needs events.manage (the refresh route).
const canRefreshFlights = computed(() => page.props.auth?.can?.['events.manage'] === true);
const planFlights = computed(() => {
  const seen = new Map();
  for (const mv of props.plan.movements ?? []) {
    const f = mv.flight;
    if (mv.job_id || !['arrival', 'departure'].includes(mv.kind) || !f?.id || !f.flight_number || f.flight_number === 'BUS') continue;
    if (f.actual_at || f.flight_status === 'cancelled' || f.flight_status === 'landed') continue;
    seen.set(f.id, { id: f.id, event_id: f.event_id ?? props.plan.event_id });
  }
  return [...seen.values()];
});
const { refresh: refreshFlights, refreshing: flightsRefreshing, result: flightResult } = useFlightRefresh();

const STORE_KEY = 'plans.checklist';
const collapsed = ref(localStorage.getItem(STORE_KEY) === 'collapsed');
function toggle() {
  collapsed.value = !collapsed.value;
  localStorage.setItem(STORE_KEY, collapsed.value ? 'collapsed' : 'open');
}
</script>

<style scoped>
.pc {
  display: flex; flex-direction: column; gap: 12px; margin-bottom: 12px; padding: 12px 14px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
}
.pc--collapsed { gap: 0; padding: 8px 14px; }
.pc-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.pc-head-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.pc-title { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; min-width: 0; }
.pc-kicker { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.pc-count { font-size: 12px; font-weight: 600; color: var(--ink); }
.pc-bar { display: inline-flex; gap: 3px; }
.pc-seg { width: 22px; height: 5px; border-radius: 3px; background: var(--border); }
.pc-seg--done { background: var(--ok); }
.pc-seg--partial { background: color-mix(in srgb, var(--ok) 45%, var(--border)); }
.pc-seg--warn { background: var(--warn); }
.pc-next-inline { font-size: 12px; color: var(--ink2); }
.pc-next-inline strong { font-weight: 600; color: var(--ink); }
.pc-next-inline--ok { color: var(--ok); }
.pc-toggle {
  flex: none; padding: 4px 10px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink2); font-size: 12px; font-weight: 500; cursor: pointer;
}
.pc-toggle:hover { background: var(--panel); color: var(--ink); }

.pc-steps { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 8px; margin: 0; padding: 0; list-style: none; }
.pc-step {
  display: flex; flex-direction: column; gap: 4px; min-width: 0; padding: 10px 12px;
  border: 1px solid var(--border); border-radius: 10px; background: var(--panel);
}
.pc-step--next { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 3px var(--accent-ring); }
.pc-step-top { display: flex; align-items: center; justify-content: space-between; }
.pc-mark {
  display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%;
  font-size: 11px; font-weight: 700; background: var(--border); color: var(--ink2);
}
.pc-step--done .pc-mark { background: var(--ok); color: #fff; }
.pc-step--partial .pc-mark { background: var(--ok-soft); color: var(--ok); }
.pc-step--warn .pc-mark { background: var(--warn); color: #fff; }
.pc-step--next .pc-mark { outline: 2px solid var(--accent); outline-offset: 1px; }
.pc-next { font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--accent); }
.pc-label { font-size: 13px; font-weight: 600; color: var(--ink); }
.pc-detail { font-size: 12px; color: var(--ink3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pc-step--warn .pc-detail { color: var(--warn); }
.pc-actions { margin-top: auto; padding-top: 4px; min-height: 26px; display: flex; }
.pc-act {
  align-self: flex-start; padding: 4px 10px; border: 1px solid var(--border-strong); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none;
}
.pc-act:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.pc-act--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.pc-act--primary:hover:not(:disabled) { color: #fff; filter: brightness(1.08); }
.pc-act:disabled { opacity: 0.5; cursor: default; }

@media (max-width: 1100px) {
  .pc-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>
