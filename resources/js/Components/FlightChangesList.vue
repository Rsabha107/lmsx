<template>
  <div v-if="changes.length" class="fcl">
    <section v-for="(change, i) in changes" :key="`fc${i}`" class="fcl-flight">
      <!-- The flight: team, direction, number and old → new time -->
      <header class="fcl-head">
        <span class="fcl-team">{{ change.code }}</span>
        <span class="fcl-dir">{{ change.direction }}</span>
        <span class="fcl-num">
          <template v-if="change.flight_before && change.flight_before !== change.flight_after">
            <s>{{ change.flight_before }}</s> → <strong>{{ change.flight_after || '—' }}</strong>
          </template>
          <strong v-else>{{ change.flight_after || 'Flight' }}</strong>
        </span>
        <span v-if="flightSpan(change)" class="fcl-times">
          <s>{{ flightSpan(change).from }}</s><span class="fcl-arrow">→</span><strong>{{ flightSpan(change).to }}</strong>
          <span v-if="flightSpan(change).day" class="fcl-day">{{ flightSpan(change).day }}</span>
        </span>
        <span v-if="change.pax_before !== change.pax_after && change.pax_after != null" class="fcl-pax">
          pax {{ change.pax_before ?? '—' }} → <strong>{{ change.pax_after }}</strong>
        </span>
      </header>

      <p v-if="!change.movements.length" class="fcl-empty">No planned movements affected.</p>

      <!-- Each movement it touched -->
      <article v-for="m in change.movements" :key="m.id" :class="['fcl-mv', `fcl-mv--${m.status}`]">
        <div class="fcl-mv-top">
          <div class="fcl-mv-id">
            <Link v-if="m.plan_id" :href="`/plans?plan=${m.plan_id}&movement=${m.id}`" class="fcl-code">{{ m.code }}</Link>
            <strong v-else class="fcl-code">{{ m.code }}</strong>
            <span class="fcl-kind">{{ m.kind }}</span>
            <span v-if="m.plan" class="fcl-plan" :title="m.plan">{{ m.plan }}</span>
          </div>
          <span :class="['fcl-badge', `fcl-badge--${m.status}`]">{{ STATUS[m.status] ?? 'Updated' }}</span>
        </div>

        <dl class="fcl-rows">
          <!-- The job: re-timed start, or kept because it started -->
          <template v-if="m.job_ref">
            <dt>Job</dt>
            <dd>
              <Link :href="`/job/${m.job_ref}`" class="fcl-job">{{ m.job_ref }}</Link>
              <template v-if="m.status === 'job_retimed' && startSpan(m)">
                <span class="fcl-sep">starts</span>
                <s>{{ startSpan(m).from }}</s><span class="fcl-arrow">→</span><strong class="fcl-mono">{{ startSpan(m).to }}</strong>
                <span v-if="startSpan(m).day" class="fcl-day">{{ startSpan(m).day }}</span>
              </template>
              <template v-else-if="m.job_started">
                <span class="fcl-kept">started: kept its times<template v-if="m.start_before"> ({{ m.start_before }})</template></span>
              </template>
            </dd>
          </template>
          <!-- A planned movement (no job): its window -->
          <template v-else-if="m.from !== m.to && (m.from || m.to)">
            <dt>Window</dt>
            <dd>
              <s>{{ windowSpan(m).from }}</s><span class="fcl-arrow">→</span><strong class="fcl-mono">{{ windowSpan(m).to }}</strong>
              <span v-if="windowSpan(m).day" class="fcl-day">{{ windowSpan(m).day }}</span>
            </dd>
          </template>
        </dl>

        <p v-if="facts(m).length" class="fcl-facts">{{ facts(m).join(' · ') }}</p>

        <!-- Crew clashes at the new time -->
        <div v-if="m.clash_list?.length" class="fcl-clashes">
          <div class="fcl-clashes-head">Check at the new time</div>
          <div v-for="(c, k) in m.clash_list" :key="k" class="fcl-clash">
            <div class="fcl-clash-head">
              <span v-if="c.type" class="fcl-type">{{ c.type }}</span>
              <span v-if="splitClash(c.text).note" class="fcl-note">{{ splitClash(c.text).note }}</span>
            </div>
            <span class="fcl-clash-text">{{ splitClash(c.text).main }}</span>
          </div>
        </div>

        <!-- Anything else the server said (passengers, flight number, plan date, crew to check) -->
        <ul v-if="m.notes?.length" class="fcl-notes">
          <li v-for="(note, n) in m.notes" :key="n">{{ note }}</li>
        </ul>
        <p v-if="m.job_started" class="fcl-hint">Adjust it from the Jobs Queue (More → Override) if needed.</p>
      </article>
    </section>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { splitClash, timeSpan } from '@/Composables/flightChangeFormat';

// What a flight change did to the movements planned on it (FlightMovementSync::sync results): per flight,
// each movement (linking to Planning) and job (linking to its page) with old → new times and any crew clash.
defineProps({ changes: { type: Array, default: () => [] } });

const STATUS = { updated: 'Re-timed', job_retimed: 'Job re-timed', needs_review: 'Needs review', unchanged: 'Unchanged' };

const flightSpan = (c) => (c.time_before !== c.time_after ? timeSpan(c.time_before, c.time_after) : null);
const startSpan = (m) => timeSpan(m.start_before, m.start_after);
const windowSpan = (m) => timeSpan(m.from, m.to);
const facts = (m) => [
  m.checkpoints_moved ? `${m.checkpoints_moved} checkpoint${m.checkpoints_moved === 1 ? '' : 's'} moved` : null,
  m.notified ? 'supervisors notified' : null,
].filter(Boolean);
</script>

<style scoped>
.fcl { display: flex; flex-direction: column; gap: 12px; }
.fcl-flight { border: 1px solid var(--border); border-radius: 10px; overflow: hidden; }
.fcl-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 6px 10px; padding: 9px 12px; background: var(--panel); border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--ink2); }
.fcl-team { font-weight: 700; color: var(--ink); }
.fcl-dir { text-transform: capitalize; color: var(--ink3); }
.fcl-num { font-family: var(--mono, ui-monospace, monospace); }
.fcl-num strong { color: var(--ink); }
.fcl-times, .fcl-pax { display: inline-flex; align-items: baseline; gap: 5px; font-family: var(--mono, ui-monospace, monospace); }
.fcl-head s, .fcl-mv s { color: var(--ink4); font-family: var(--mono, ui-monospace, monospace); }
.fcl-arrow { color: var(--ink4); }
.fcl-day { font-family: inherit; font-size: 11.5px; color: var(--ink3); }
.fcl-empty { margin: 0; padding: 10px 12px; font-size: 12.5px; color: var(--ink3); }

.fcl-mv { padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; border-left: 3px solid transparent; }
.fcl-mv + .fcl-mv { border-top: 1px solid var(--border); }
.fcl-mv--job_retimed { border-left-color: #3B82F6; }
.fcl-mv--needs_review { border-left-color: var(--warn); background: color-mix(in srgb, #FEF3C7 35%, var(--surface)); }
.fcl-mv--updated { border-left-color: var(--ok); }
.fcl-mv-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.fcl-mv-id { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 8px; min-width: 0; font-size: 13px; }
.fcl-code { font-weight: 700; color: var(--accent); text-decoration: none; font-family: var(--mono, ui-monospace, monospace); }
a.fcl-code:hover, .fcl-job:hover { text-decoration: underline; }
.fcl-kind { text-transform: capitalize; color: var(--ink3); font-size: 12px; }
.fcl-plan { color: var(--ink3); font-size: 12px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.fcl-badge { flex-shrink: 0; padding: 2px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; white-space: nowrap; }
.fcl-badge--job_retimed { background: #DBEAFE; color: #1E40AF; }
.fcl-badge--needs_review { background: #FEF3C7; color: #92400E; }
.fcl-badge--updated { background: var(--ok-soft, #DCFCE7); color: var(--ok); }
.fcl-badge--unchanged { background: var(--panel); color: var(--ink3); }

.fcl-rows { display: grid; grid-template-columns: 58px 1fr; gap: 4px 10px; margin: 0; font-size: 13px; }
.fcl-rows:empty { display: none; }
.fcl-rows dt { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--ink4); padding-top: 2px; }
.fcl-rows dd { margin: 0; display: flex; flex-wrap: wrap; align-items: baseline; gap: 5px; color: var(--ink2); }
.fcl-job { font-weight: 700; color: var(--accent); text-decoration: none; font-family: var(--mono, ui-monospace, monospace); }
.fcl-sep { color: var(--ink3); font-size: 12px; }
.fcl-mono { font-family: var(--mono, ui-monospace, monospace); color: var(--ink); }
.fcl-kept { color: #92400E; font-size: 12px; font-weight: 600; }
.fcl-facts { margin: 0; font-size: 11.5px; color: var(--ink3); }

.fcl-clashes { border: 1px solid #FCD34D; border-radius: 8px; background: #FFFBEB; padding: 8px 10px; }
.fcl-clashes-head { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #92400E; margin-bottom: 4px; }
.fcl-clash { display: flex; flex-direction: column; gap: 3px; padding: 6px 0; font-size: 12.5px; line-height: 1.45; }
.fcl-clash + .fcl-clash { border-top: 1px dashed #FCD34D; }
.fcl-clash:last-child { padding-bottom: 0; }
.fcl-clash-head { display: flex; align-items: center; gap: 8px; }
.fcl-type { font-size: 11px; font-weight: 700; padding: 1px 7px; border-radius: 4px; background: #FDE68A; color: #78350F; white-space: nowrap; }
.fcl-note { font-size: 12px; font-weight: 700; color: var(--danger); }
.fcl-clash-text { color: var(--ink); }
.fcl-notes { margin: 0; padding-left: 16px; font-size: 12px; color: var(--ink3); }
.fcl-hint { margin: 0; font-size: 12px; color: var(--ink3); }
</style>
