<template>
  <Teleport to="body">
    <div v-dialog="() => emit('close')" class="td-backdrop" @mousedown.self="emit('close')">
      <section class="td" role="dialog" :aria-label="team ? `Edit team ${team.team_name}` : 'Add a team'">
        <header class="td-head">
          <div>
            <div class="td-kicker">{{ team ? 'Edit team' : 'New team' }}</div>
            <h2 class="td-title">
              <FlagIcon v-if="form.team.country_id || form.team.flag" :code="form.team.country_id" :fallback="flagFallback" :title="selectedCountry?.country_name" />
              {{ team ? team.team_name : 'Add a team' }}
            </h2>
          </div>
          <button type="button" class="td-x" aria-label="Close" @click="emit('close')">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </header>

        <form class="td-body" @submit.prevent="save">
          <!-- Team -->
          <div class="td-section-label">Team</div>
          <div class="td-grid td-grid--team">
            <label class="td-field">
              <span>Team name</span>
              <input v-model="form.team.team_name" type="text" placeholder="e.g. Kuwait" required />
              <small v-if="err('team.team_name')" class="td-err">{{ err('team.team_name') }}</small>
            </label>
            <label class="td-field">
              <span>Team code</span>
              <input v-model="form.team.code" type="text" class="td-mono" placeholder="KUW-17" maxlength="10" required />
              <small v-if="err('team.code')" class="td-err">{{ err('team.code') }}</small>
            </label>
            <label class="td-field">
              <span>Country</span>
              <select v-model="form.team.country_id" required @change="countryPicked">
                <option :value="null" disabled>Pick a country</option>
                <option v-for="c in countries" :key="c.country_code" :value="c.country_code">{{ c.country_name }}</option>
              </select>
              <small v-if="err('team.country_id')" class="td-err">{{ err('team.country_id') }}</small>
            </label>
          </div>
          <div class="td-grid td-grid--group">
            <label class="td-field">
              <span>Group / pool</span>
              <input v-model="form.team.group_pool" type="text" class="td-mono" placeholder="A1" list="td-pools" />
              <datalist id="td-pools"><option v-for="p in groupOptions" :key="p" :value="p" /></datalist>
            </label>
            <div class="td-field">
              <span>Role</span>
              <div class="td-seg" role="radiogroup" aria-label="Role">
                <button v-for="r in ROLES" :key="r.value" type="button" role="radio" :aria-checked="form.team.role === r.value"
                  :class="['td-seg-btn', { 'td-seg-btn--on': form.team.role === r.value }]" @click="form.team.role = r.value">{{ r.label }}</button>
              </div>
            </div>
          </div>

          <!-- Travelling party: one figure for both flights -->
          <template v-if="form.team.role !== 'host'">
            <div class="td-section-label td-section-label--row">Travelling party <span class="td-count">Applies to both flights</span></div>
            <div class="td-party">
              <label class="td-party-cell"><span>Players</span><input v-model.number="form.party.players" type="number" min="0" max="999" placeholder="0" aria-label="Players" /></label>
              <label class="td-party-cell"><span>Staff</span><input v-model.number="form.party.staff" type="number" min="0" max="999" placeholder="0" aria-label="Staff" /></label>
              <div class="td-party-cell td-party-cell--total" title="Players + staff"><span>Total party</span><strong>{{ partyTotal ?? '—' }}</strong></div>
              <label class="td-party-cell"><span>Planned bags</span><input v-model.number="form.party.bags" type="number" min="0" max="9999" placeholder="0" aria-label="Planned bags" /></label>
            </div>
            <small v-if="err('party.players') || err('party.staff') || err('party.bags')" class="td-err">{{ err('party.players') || err('party.staff') || err('party.bags') }}</small>
          </template>

          <!-- Arrival / departure -->
          <div v-if="form.team.role === 'host'" class="td-host-note">Host team: local, no travel. Arrival and departure aren't needed.</div>
          <div v-else class="td-legs">
            <div v-for="side in ['arrival', 'departure']" :key="side" :class="['td-leg', `td-leg--${side}`, { 'td-leg--removed': form[side].remove }]">
              <div class="td-leg-head">
                <i />{{ legTitle(side) }}
                <template v-if="!form[side].remove">
                  <button type="button" class="td-gate" :aria-label="`${side} gate`" @click="form[side].open = true">Gate {{ form[side].gate || '–' }}</button>
                  <button v-if="form[side].id" type="button" :class="['td-x', 'td-x--small', 'td-leg-remove', { 'td-leg-remove--locked': form[side].jobs > 0 }]" :aria-disabled="form[side].jobs > 0" :aria-label="`Remove ${side} flight`"
                    :title="form[side].jobs > 0 ? `A job was generated from this ${side}: delete the job first, or change the flight instead` : `Remove the ${side} flight (on save)`" @click="form[side].jobs > 0 || (form[side].remove = true)">
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                  </button>
                </template>
              </div>
              <div v-if="form[side].remove" class="td-removed">
                <span>{{ form[side].flight_number || 'This flight' }} will be removed when you save. Its planned movements (without a job) lose their times, and no job can be generated for them until the {{ side }} is added again.</span>
                <button type="button" class="td-btn" @click="form[side].remove = false">Undo</button>
                <small v-if="err(`${side}.remove`)" class="td-err">{{ err(`${side}.remove`) }}</small>
              </div>
              <template v-else>
              <div class="td-grid td-grid--leg">
                <label class="td-field"><span>Date</span><input v-model="form[side].date" type="date" :aria-label="`${side} date`" /></label>
                <label class="td-field"><span>Time</span><input v-model="form[side].time" type="time" :aria-label="`${side} time`" /></label>
                <label class="td-field td-field--wide"><span>Flight</span><input v-model="form[side].flight_number" type="text" class="td-mono" placeholder="TBC" maxlength="20" :aria-label="`${side} flight`" /></label>
                <label v-for="end in ['origin', 'destination']" :key="end" class="td-field">
                  <span>{{ end === 'origin' ? 'From' : 'To' }}</span>
                  <select v-model="form[side][`${end}_airport_id`]" class="td-mono" :aria-label="`${side} ${end === 'origin' ? 'from' : 'to'} airport`">
                    <option :value="null">{{ !form[side].id && isHomeEnd(side, end) ? 'Auto' : '—' }}</option>
                    <option v-for="a in airports" :key="a.id" :value="a.id">{{ a.code }}{{ a.city ? ` · ${a.city}` : '' }}</option>
                  </select>
                </label>
                <label class="td-field">
                  <span>Status</span>
                  <select v-model="form[side].status" :aria-label="`${side} status`" title="Auto hands the status back to the flight source (OAG): a status, ETA or actual time set by hand is cleared on save">
                    <option v-for="s in statusOptions(side)" :key="s.value" :value="s.value">{{ s.label }}</option>
                  </select>
                </label>
                <label class="td-field">
                  <span>{{ side === 'arrival' ? 'Actual / ETA' : 'Actual / ETD' }}</span>
                  <input v-model="form[side].actual_time" type="time" :disabled="!['delayed', 'landed', 'departed'].includes(form[side].status)" />
                </label>
              </div>
              <small v-if="form[side].id && form[side].status === 'auto' && form[side].status0 !== 'auto'" class="td-auto-note">
                On save the {{ form[side].status0 }} status{{ form[side].actual0 ? ` and ${form[side].actual0}` : '' }} {{ form[side].actual0 ? 'are' : 'is' }} cleared; the next flight refresh sets them again.
              </small>
              <small v-if="err(`${side}.time`) || err(`${side}.date`)" class="td-err">{{ err(`${side}.time`) || err(`${side}.date`) }}</small>
              <div class="td-leg-more">
                <button type="button" class="td-toggle" :aria-expanded="form[side].open" @click="form[side].open = !form[side].open">
                  <svg :class="{ 'td-toggle-open': form[side].open }" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="m9 6 6 6-6 6" /></svg>
                  {{ form[side].open ? 'Hide gate & notes' : 'Add gate & notes' }}
                </button>
              </div>
              <div v-if="form[side].open" class="td-grid td-grid--gate">
                <label class="td-field"><span>Gate</span><input v-model="form[side].gate" type="text" class="td-mono" maxlength="50" placeholder="e.g. C12" :aria-label="`${side} gate number`" /></label>
                <label class="td-field"><span>Notes</span><input v-model="form[side].notes" type="text" maxlength="2000" placeholder="e.g. Meet at Al Maha lounge" :aria-label="`${side} notes`" /></label>
              </div>
              </template>
            </div>
          </div>
          <p v-if="form.team.role !== 'host'" class="td-hint">
            Leave flight number blank to mark it TBC.<template v-if="nights !== null"> Stay: {{ nights }} night{{ nights === 1 ? '' : 's' }}.</template>
          </p>

          <!-- Hotel -->
          <label class="td-field">
            <span>Accommodation</span>
            <input v-model="form.hotel_name" type="text" placeholder="Hotel name" list="td-hotels" />
            <datalist id="td-hotels"><option v-for="h in baseCampHotels" :key="h.id" :value="h.name" /></datalist>
          </label>

          <!-- Training sessions -->
          <div class="td-section-label td-section-label--row">
            Training sessions <span v-if="form.trainings.length" class="td-count">{{ form.trainings.length }} session{{ form.trainings.length === 1 ? '' : 's' }}</span>
          </div>
          <div class="td-trainings">
            <p v-if="!form.trainings.length" class="td-empty">No sessions scheduled yet.</p>
            <div v-for="(t, i) in sortedTrainings" :key="t.key" class="td-session">
              <div class="td-session-when"><strong>{{ dayLabel(t.start_at) }}</strong><span>{{ t.start_at.slice(11, 16) }}</span></div>
              <div class="td-session-what"><strong>{{ t.training_ground || 'Training' }}</strong><span v-if="t.notes">{{ t.notes }}</span></div>
              <button type="button" class="td-x td-x--small" :aria-label="`Remove session ${i + 1}`" @click="removeTraining(t)">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12" /></svg>
              </button>
            </div>
            <div class="td-session-new">
              <div class="td-grid td-grid--session">
                <input v-model="newSession.training_ground" type="text" placeholder="Training ground" aria-label="Training ground" />
                <input v-model="newSession.when" type="datetime-local" aria-label="Training date and time" />
              </div>
              <div class="td-grid td-grid--session2">
                <input v-model="newSession.notes" type="text" placeholder="Notes (optional) — e.g. bus 15:15 from hotel" aria-label="Training notes" />
                <button type="button" class="td-btn" :disabled="!newSession.when" @click="addTraining">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14" /></svg>
                  Add session
                </button>
              </div>
            </div>
          </div>

          <!-- Less used team fields -->
          <details class="td-more">
            <summary>More details</summary>
            <div class="td-grid td-grid--more">
              <label class="td-field">
                <span>Classification</span>
                <select v-model="form.team.classification_type_id">
                  <option :value="null">—</option>
                  <option v-for="c in classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
              </label>
              <label class="td-field"><span>Head of delegation</span><input v-model="form.team.head_of_delegation" type="text" /></label>
              <label class="td-field"><span>Flag</span><input v-model="form.team.flag" type="text" maxlength="10" placeholder="Emoji (optional)" /></label>
              <label class="td-field"><span>Bib colour</span><input v-model="form.team.bib_accent_color" type="text" maxlength="20" /></label>
            </div>
            <label class="td-field"><span>Notes</span><textarea v-model="form.team.notes" rows="2" /></label>
          </details>
          <p v-if="otherErrors" class="td-err">{{ otherErrors }}</p>
        </form>

        <footer class="td-foot">
          <button v-if="team && allowDelete" type="button" class="td-btn td-btn--danger" @click="emit('delete', team)">Delete team</button>
          <span class="td-spacer" />
          <button type="button" class="td-btn" @click="emit('close')">Cancel</button>
          <button type="button" class="td-btn td-btn--dark" :disabled="saving" @click="save">{{ saving ? 'Saving…' : team ? 'Save changes' : 'Add team' }}</button>
        </footer>
      </section>
    </div>
  </Teleport>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import FlagIcon from '@/Components/FlagIcon.vue';

// Add / Edit team as in the Teams Logistics design: team, travelling party, arrival, departure (with gate and notes),
// hotel and trainings in one save (TeamProfileController). Used on Event Teams and from Planning's Edit movement.
const props = defineProps({
  team: { type: Object, default: null },
  eventId: { type: Number, required: true },
  airports: { type: Array, default: () => [] },
  countries: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
  baseCampHotels: { type: Array, default: () => [] },
  groupOptions: { type: Array, default: () => [] },
  // Off where the page has no team delete flow (Planning).
  allowDelete: { type: Boolean, default: true },
});
const emit = defineEmits(['close', 'saved', 'delete']);

const ROLES = [{ value: 'team', label: 'Team' }, { value: 'host', label: 'Host' }, { value: 'guest', label: 'Guest' }];
const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// Wall-clock values: read the digits, never convert time zones.
const digits = (v) => String(v ?? '').match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/);

function legForm(side) {
  const f = (props.team?.flights ?? []).find((x) => x.direction === side);
  if (!f) return { id: null, date: '', time: '', flight_number: '', origin_airport_id: null, destination_airport_id: null, status: 'auto', actual_time: '', gate: '', notes: '', open: false, remove: false, jobs: 0 };
  const s = digits(f.scheduled_at);
  const a = digits(f.actual_at);
  const e = digits(f.estimated_at);
  const status = f.flight_status === 'cancelled' ? 'cancelled'
    : a ? (side === 'arrival' ? 'landed' : 'departed')
    : e && s && `${e[1]} ${e[2]}` > `${s[1]} ${s[2]}` ? 'delayed'
    : f.flight_status === 'scheduled' ? 'scheduled' : 'auto';
  return {
    id: f.id,
    date: s?.[1] ?? '',
    time: s?.[2] ?? '',
    flight_number: f.flight_number ?? '',
    origin_airport_id: f.origin_airport_id ?? null,
    destination_airport_id: f.destination_airport_id ?? null,
    status,
    actual_time: (a ?? e)?.[2] ?? '',
    gate: f.gate ?? '',
    notes: f.notes ?? '',
    open: !!f.notes,
    remove: false,
    // Jobs generated from this flight: it can't be removed while there are any.
    jobs: f.issued_jobs_count ?? 0,
    // What the dialog opened with: a status that isn't changed isn't sent (the flight keeps OAG's live status).
    status0: status,
    actual0: (a ?? e)?.[2] ?? '',
  };
}

// The party is entered once; the arrival's figures win when the two flights differ.
function partyForm() {
  const flights = props.team?.flights ?? [];
  const f = flights.find((x) => x.direction === 'arrival') ?? flights.find((x) => x.direction === 'departure');
  return { players: f?.party_size_players ?? null, staff: f?.party_size_staff ?? null, bags: f?.planned_bags ?? null, total: f?.party_size_total ?? null };
}

let seq = 0;
const startOf = (v) => { const d = digits(v); return d ? `${d[1]} ${d[2]}` : ''; };
const form = reactive({
  team: {
    code: props.team?.code ?? '',
    team_name: props.team?.team_name ?? '',
    country_id: props.team?.country_id ?? null,
    flag: props.team?.flag ?? '',
    group_pool: props.team?.group_pool ?? '',
    role: props.team?.role ?? 'team',
    classification_type_id: props.team?.classification_type_id ?? null,
    head_of_delegation: props.team?.head_of_delegation ?? '',
    bib_accent_color: props.team?.bib_accent_color ?? '',
    notes: props.team?.notes ?? '',
  },
  arrival: legForm('arrival'),
  departure: legForm('departure'),
  party: partyForm(),
  hotel_name: props.team?.stay?.hotel_name ?? '',
  trainings: (props.team?.trainings ?? [])
    .map((t) => ({ key: ++seq, id: t.id, training_ground: t.training_ground ?? '', start_at: startOf(t.training_start_at), notes: t.notes ?? '' }))
    .filter((t) => t.start_at),
});

// The home end is where the team lands (arrival) or takes off (departure); new flights may leave it on Auto.
const isHomeEnd = (side, end) => (side === 'arrival') === (end === 'destination');
const airportCode = (id) => props.airports.find((a) => a.id === id)?.code ?? null;
function legTitle(side) {
  const home = airportCode(form[side][side === 'arrival' ? 'destination_airport_id' : 'origin_airport_id']);
  if (side === 'arrival') return home ? `Arrival · into ${home}` : 'Arrival';
  return home ? `Departure · from ${home}` : 'Departure';
}

// Blank number inputs give '' with v-model.number.
const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));
const partyKey = (p) => [num(p.players), num(p.staff), num(p.bags)].join('|');
const partyBefore = partyKey(form.party);
const partyTotal = computed(() => {
  const players = num(form.party.players);
  const staff = num(form.party.staff);
  return players !== null || staff !== null ? (players ?? 0) + (staff ?? 0) : form.party.total;
});
const statusOptions = (side) => [
  { value: 'auto', label: 'Auto' },
  { value: 'scheduled', label: 'Scheduled' },
  { value: 'delayed', label: 'Delayed' },
  side === 'arrival' ? { value: 'landed', label: 'Landed' } : { value: 'departed', label: 'Departed' },
  { value: 'cancelled', label: 'Cancelled' },
];

const nights = computed(() => {
  if (!form.arrival.date || !form.departure.date) return null;
  const d = (s) => Date.UTC(+s.slice(0, 4), +s.slice(5, 7) - 1, +s.slice(8, 10));
  return Math.max(0, Math.round((d(form.departure.date) - d(form.arrival.date)) / 86400000));
});

// Picking a country fills an empty (or country-named) team name.
const selectedCountry = computed(() => props.countries.find((c) => c.country_code === form.team.country_id) ?? null);
const flagFallback = computed(() => form.team.flag || selectedCountry.value?.flag || null);
let lastCountryName = props.countries.find((c) => c.country_code === form.team.country_id)?.country_name ?? '';
function countryPicked() {
  const c = props.countries.find((x) => x.country_code === form.team.country_id);
  if (c && (!form.team.team_name || form.team.team_name === lastCountryName)) form.team.team_name = c.country_name;
  lastCountryName = c?.country_name ?? '';
}

const sortedTrainings = computed(() => [...form.trainings].sort((a, b) => a.start_at.localeCompare(b.start_at)));
const dayLabel = (s) => {
  const d = new Date(Date.UTC(+s.slice(0, 4), +s.slice(5, 7) - 1, +s.slice(8, 10)));
  return `${WEEKDAYS[d.getUTCDay()]} ${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]}`;
};
const newSession = reactive({ training_ground: '', when: '', notes: '' });
function addTraining() {
  if (!newSession.when) return;
  form.trainings.push({ key: ++seq, id: null, training_ground: newSession.training_ground.trim(), start_at: newSession.when.replace('T', ' ').slice(0, 16), notes: newSession.notes.trim() });
  Object.assign(newSession, { training_ground: '', when: '', notes: '' });
}
function removeTraining(t) {
  form.trainings = form.trainings.filter((x) => x.key !== t.key);
}

const errors = ref({});
const saving = ref(false);
const err = (key) => errors.value[key];
const shownInline = ['team.team_name', 'team.code', 'team.country_id', 'arrival.time', 'arrival.date', 'departure.time', 'departure.date', 'arrival.remove', 'departure.remove'];
const otherErrors = computed(() => Object.entries(errors.value).filter(([k]) => !shownInline.includes(k)).map(([, v]) => v).join(' '));

function save() {
  // A session typed in but not added yet is kept rather than lost.
  if (newSession.when) addTraining();
  const leg = (side) => {
    const { open, jobs, status0, actual0, ...rest } = form[side];
    if (form.team.role === 'host' && !rest.id) return null;
    // Untouched status and time: send none, so saving the team never overrides the flight's live status.
    const untouched = rest.id && rest.status === status0 && rest.actual_time === actual0;
    return untouched ? { ...rest, status: null } : rest;
  };
  // Sent only when changed, so two flights with different imported figures aren't overwritten by an untouched form.
  const partyChanged = form.team.role !== 'host' && partyKey(form.party) !== partyBefore;
  const payload = {
    team: { ...form.team, flag: form.team.flag || null, group_pool: form.team.group_pool || null },
    arrival: leg('arrival'),
    departure: leg('departure'),
    party: partyChanged ? { players: num(form.party.players), staff: num(form.party.staff), bags: num(form.party.bags) } : null,
    hotel_name: form.hotel_name || null,
    trainings: form.trainings.map(({ key, ...t }) => t),
  };
  saving.value = true;
  errors.value = {};
  const url = props.team
    ? `/events/${props.eventId}/teams/${encodeURIComponent(props.team.code)}/profile`
    : `/events/${props.eventId}/teams/profile`;
  router[props.team ? 'put' : 'post'](url, payload, {
    preserveScroll: true,
    onSuccess: (page) => { if (!page.props.flash?.error) emit('saved', page); },
    onError: (e) => { errors.value = e; },
    onFinish: () => { saving.value = false; },
  });
}
</script>

<style scoped>
.td-backdrop { position: fixed; inset: 0; z-index: 800; display: flex; align-items: center; justify-content: center; padding: 32px 16px; background: rgba(17, 24, 39, .45); }
/* Header and footer stay put; only the body scrolls. */
.td {
  --td-mono: var(--mono, var(--font-mono, ui-monospace, SFMono-Regular, Menlo, monospace));
  width: 100%; max-width: 760px; max-height: 100%; display: flex; flex-direction: column; overflow: hidden;
  background: var(--surface); border-radius: 16px; box-shadow: 0 24px 64px rgba(17, 24, 39, .3);
}
.td-head { flex-shrink: 0; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 22px 24px 16px; border-bottom: 1px solid var(--border); }
.td-kicker { font-family: var(--td-mono); font-size: 11.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); margin-bottom: 4px; }
.td-title { display: flex; align-items: center; gap: 10px; margin: 0; font-size: 21px; font-weight: 700; color: var(--ink); }
.td-title :deep(.flag-icon), .td-title :deep(.flag-icon-fallback) { font-size: 22px; }
.td-x { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border: none; border-radius: 8px; background: none; color: var(--ink3); cursor: pointer; }
.td-x:hover { background: var(--panel); color: var(--ink); }
.td-x--small { width: 26px; height: 26px; }
.td-body { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 14px; padding: 18px 24px 22px; }
/* Sections keep their height so the body scrolls instead of squeezing them. */
.td-body > * { flex-shrink: 0; }
.td-section-label { font-family: var(--td-mono); font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: var(--ink3); margin-top: 4px; }
.td-section-label--row { display: flex; align-items: center; justify-content: space-between; }
.td-count { letter-spacing: 0; text-transform: none; }
.td-grid { display: grid; gap: 12px; }
.td-grid--team { grid-template-columns: 1.6fr 1fr 1.2fr; }
.td-grid--group { grid-template-columns: 1fr 2fr; }
.td-grid--leg { grid-template-columns: 1.3fr 1fr; }
.td-grid--session { grid-template-columns: 1fr 1fr; }
.td-grid--session2 { grid-template-columns: 1fr auto; margin-top: 8px; }
.td-grid--more { grid-template-columns: 1fr 1fr; margin-bottom: 12px; }
.td-field { display: flex; flex-direction: column; gap: 5px; min-width: 0; font-size: 12.5px; color: var(--ink2); }
.td-field input, .td-field select, .td-field textarea, .td-session-new input {
  height: 36px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface);
  font-size: 13px; color: var(--ink); width: 100%; box-sizing: border-box;
}
.td-field textarea { height: auto; padding: 8px 10px; resize: vertical; }
.td-field input:disabled { background: var(--panel); color: var(--ink4); }
.td-mono { font-family: var(--td-mono); }
.td-err { color: var(--danger); font-size: 12px; }
.td-seg { display: flex; padding: 3px; gap: 2px; background: var(--panel); border: 1px solid var(--border); border-radius: 9px; height: 36px; box-sizing: border-box; }
.td-seg-btn { flex: 1; border: none; border-radius: 7px; background: none; cursor: pointer; font-size: 13px; color: var(--ink2); }
.td-seg-btn--on { background: var(--surface); color: var(--ink); font-weight: 600; box-shadow: 0 1px 2px rgba(17, 24, 39, .08); }
.td-host-note { padding: 12px 14px; border-radius: 10px; background: var(--panel); font-size: 13px; color: var(--ink2); }
.td-legs { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
.td-leg { display: flex; flex-direction: column; gap: 12px; padding: 14px; border: 1px solid var(--border); border-radius: 12px; background: color-mix(in srgb, var(--panel) 45%, var(--surface)); }
.td-leg-head { display: flex; align-items: center; gap: 7px; font-family: var(--td-mono); font-size: 11.5px; letter-spacing: .06em; text-transform: uppercase; }
.td-leg-head i { width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
.td-leg--arrival .td-leg-head { color: var(--ok); }
.td-leg--departure .td-leg-head { color: var(--warn); }
.td-gate { margin-left: auto; padding: 3px 8px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); cursor: pointer; font-family: var(--td-mono); font-size: 11.5px; letter-spacing: 0; text-transform: none; color: var(--ink2); }
.td-gate:hover { border-color: var(--ink3); }
.td-leg-more { display: flex; align-items: center; gap: 10px; }
.td-auto-note { font-size: 12px; color: var(--ink3); }
.td-field--wide { grid-column: 1 / -1; }
.td-leg-remove { margin: -6px -6px -6px 0; color: var(--ink3); }
.td-leg-remove:hover:not(.td-leg-remove--locked) { color: var(--danger); background: var(--danger-soft, var(--panel)); }
.td-leg-remove--locked { opacity: .35; cursor: not-allowed; }
.td-leg--removed { border-style: dashed; background: var(--surface); }
.td-removed { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; font-size: 12.5px; color: var(--ink2); }
.td-toggle { display: inline-flex; align-items: center; gap: 6px; border: none; background: none; padding: 0; cursor: pointer; font-size: 12.5px; color: var(--ink2); }
.td-toggle:hover { color: var(--ink); }
.td-toggle svg { transition: transform .15s; }
.td-toggle-open { transform: rotate(90deg); }
.td-grid--gate { grid-template-columns: 1fr 2fr; padding-top: 12px; border-top: 1px dashed var(--border); }
.td-party { display: grid; grid-template-columns: repeat(4, 1fr); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.td-party-cell { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; min-width: 0; font-size: 12.5px; color: var(--ink2); background: var(--surface); transition: background .12s; }
.td-party-cell + .td-party-cell { border-left: 1px solid var(--border); }
.td-party-cell input { border: none; background: none; padding: 0; height: 26px; font-family: inherit; font-size: 18px; font-weight: 500; color: var(--ink); width: 100%; outline: none; }
.td-party-cell input::placeholder { color: var(--ink4); font-weight: 400; }
/* The cell's tint is the focus cue; :not(#id) outranks app.css's !important text-field ring. */
.td-party-cell input:focus:not(#td-none) { border: none !important; box-shadow: none !important; }
.td-party-cell:focus-within:not(.td-party-cell--total) { background: color-mix(in srgb, var(--accent) 5%, var(--surface)); }
.td-party-cell--total { background: var(--panel); }
.td-party-cell--total strong { height: 26px; line-height: 26px; font-size: 18px; font-weight: 500; color: var(--ink); }
.td-hint { margin: -4px 0 0; font-size: 12px; color: var(--ink3); }
.td-trainings { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.td-empty { margin: 0; padding: 12px 14px; font-size: 13px; color: var(--ink3); border-bottom: 1px solid var(--border); }
.td-session { display: flex; align-items: center; gap: 14px; padding: 10px 14px; border-bottom: 1px solid var(--border); }
.td-session-when { display: flex; flex-direction: column; width: 80px; font-size: 13px; }
.td-session-when span, .td-session-what span { font-family: var(--td-mono); font-size: 11.5px; color: var(--ink3); }
.td-session-what { flex: 1; display: flex; flex-direction: column; font-size: 13px; }
.td-session-new { padding: 12px 14px; background: color-mix(in srgb, var(--panel) 45%, var(--surface)); }
.td-btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }
.td-btn:disabled { opacity: .5; cursor: default; }
.td-btn--dark { background: var(--ink); border-color: var(--ink); color: var(--surface); }
.td-btn--danger { color: var(--danger); }
.td-btn--danger:hover { border-color: var(--danger); }
.td-more summary { cursor: pointer; font-size: 12.5px; font-weight: 600; color: var(--ink2); margin-bottom: 10px; }
.td-foot { flex-shrink: 0; background: var(--surface); display: flex; align-items: center; gap: 8px; padding: 14px 24px; border-top: 1px solid var(--border); }
.td-spacer { flex: 1; }
@media (max-width: 720px) {
  .td-legs, .td-grid--team, .td-grid--group, .td-grid--more { grid-template-columns: 1fr; }
  .td-party { grid-template-columns: 1fr 1fr; }
  .td-party-cell:nth-child(3) { border-left: none; }
  .td-party-cell:nth-child(n + 3) { border-top: 1px solid var(--border); }
}
</style>
