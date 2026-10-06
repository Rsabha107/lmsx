<template>
  <Modal :show="show" @close="close" maxWidth="760px">
    <template #title>
      <span class="override-modal-title-wrap">
        <span class="override-privileged-badge">PRIVILEGED ACTION · {{ job?.id }}</span>
        Job override
        <span v-if="jobStart" class="override-job-start">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
          Starts {{ jobStart }}
        </span>
      </span>
    </template>

    <div class="override-body">
      <div class="override-warning">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span>Field supervisors normally log checkpoints from the mobile app. Overrides bypass that — they're logged to the audit trail with your name, role, and reason.</span>
      </div>

      <!-- 1 · Checkpoint -->
      <section :class="['ovs', { 'ovs--active': state }]">
        <header class="ovs-head">
          <span class="ovs-icon ovs-icon--checkpoint">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
          </span>
          <div class="ovs-heading">
            <h4 class="ovs-title">Checkpoint</h4>
            <p class="ovs-sub">{{ state ? (checkpoint?.label || checkpoint?.name || 'Pick the checkpoint to override') : 'Optional — pick Done or Skipped to override it' }}</p>
          </div>
          <span v-if="state" :class="['ovs-chip', state === 'done' ? 'ovs-chip--ok' : 'ovs-chip--muted']">
            {{ state === 'done' ? 'Mark done' : 'Skip' }}
          </span>
        </header>

        <div class="ovs-body">
          <div class="override-field">
            <div class="override-states" :style="{ gridTemplateColumns: `repeat(${STATES.length}, 1fr)` }">
              <button v-for="s in STATES" :key="s.value"
                type="button"
                role="checkbox"
                :class="['override-state-btn', `override-state-btn--${s.value}`, state === s.value ? 'override-state-btn--active' : '']"
                :aria-checked="state === s.value"
                @click="state = state === s.value ? null : s.value">
                <span class="override-state-check" aria-hidden="true">
                  <svg v-if="state === s.value" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>
                </span>
                <span class="override-state-icon">{{ s.icon }}</span>
                <strong>{{ s.label }}</strong>
                <small>{{ s.desc }}</small>
              </button>
            </div>
            <div class="override-field-hint" v-if="state">Click it again to leave the checkpoint as it is.</div>
          </div>

          <div v-if="state" class="override-field">
            <label class="override-label">CHECKPOINT (REQUIRED)</label>
            <select v-model="checkpoint" class="override-select override-select--checkpoints">
              <option :value="null" disabled>Select a checkpoint…</option>
              <option v-for="cp in job?.checkpoints" :key="cp.id" :value="cp" :class="cp.state === 'done' ? 'checkpoint-option--done' : ''">
                {{ cp.label || cp.name }} — {{ cp.status || cp.state }} {{ cp.state === 'done' && cp.scheduled_at && cp.completed_at ? `(${cp.scheduled_at} → ${cp.completed_at} ✓)` : `(scheduled ${cp.scheduled_at || cp.at})` }}{{ (needsPhotoOf(cp) || needsSignatureOf(cp)) ? ' 📋' : '' }}
              </option>
            </select>
            <div v-if="needsPhoto || needsSignature" class="override-field-hint">
              <strong>Evidence required:</strong>
              {{ [needsPhoto && 'Photo', needsSignature && 'Signature'].filter(Boolean).join(' and ') }}
            </div>
          </div>

          <div v-if="state === 'done'" class="override-two-col">
            <div class="override-field">
              <label class="override-label">ACTUAL TIME</label>
              <input type="time" v-model="time" class="override-input" />
              <div class="override-field-hint">When it actually happened</div>
            </div>
            <div class="override-field">
              <label class="override-label">VARIANCE VS. PLANNED ({{ plannedLabel }})</label>
              <div class="override-variance" :class="varianceMinutes > 0 ? 'is-late' : varianceMinutes < 0 ? 'is-early' : ''">
                {{ varianceText }}
              </div>
              <label class="override-exclude-date">
                <input type="checkbox" v-model="excludeDate" />
                <span>Exclude date from calculation (compare time of day only)</span>
              </label>
            </div>
          </div>

          <div v-if="state" class="override-field">
            <label class="override-label">REASON (REQUIRED)</label>
            <select v-model="reason" class="override-select">
              <option value="" disabled>Select a reason…</option>
              <option value="no_signal">No signal</option>
              <option value="device_offline">Device offline</option>
              <option value="supervisor_error">Supervisor error</option>
              <option value="late_arrival">Late arrival</option>
              <option value="operational_change">Operational change</option>
              <option value="other">Other</option>
            </select>
          </div>

          <!-- Baggage Count (if required) -->
          <div v-if="state === 'done' && needsBaggage" class="override-four-col">
            <div class="override-field">
              <label class="override-label">PLANNED BAGS</label>
              <input type="number" v-model.number="bags.planned_bags" min="0" class="override-input" placeholder="0" />
              <div class="override-field-hint">From the flight manifest</div>
            </div>
            <div class="override-field">
              <label class="override-label">ACTUAL BAGS</label>
              <input type="number" v-model.number="bags.bags_loaded" min="0" class="override-input" placeholder="0" />
              <div class="override-field-hint">Number of bags</div>
            </div>
            <div class="override-field">
              <label class="override-label">FOOD BAGS</label>
              <input type="number" v-model.number="bags.food_bags" min="0" class="override-input" placeholder="0" />
              <div class="override-field-hint">Number of food bags</div>
            </div>
            <div class="override-field">
              <label class="override-label">OVERSIZED PIECES</label>
              <input type="number" v-model.number="bags.oversized_pieces" min="0" class="override-input" placeholder="0" />
              <div class="override-field-hint">Number of oversized items</div>
            </div>
          </div>

          <!-- Photo Upload (if required) -->
          <div v-if="state === 'done' && needsPhoto" class="override-field">
            <label class="override-label">PHOTO (REQUIRED)</label>
            <input type="file" accept="image/*" @change="handlePhotoUpload" class="override-file-input" ref="photoInput" />
            <div v-if="photoPreview" class="photo-preview">
              <img :src="photoPreview" alt="Photo preview" @click="showLightbox = true" style="cursor: pointer;" />
              <button type="button" @click="clearPhoto" class="clear-photo-btn">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                  <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                </svg>
              </button>
            </div>
            <div class="override-field-hint">Upload a photo as evidence</div>
          </div>

          <!-- Signature Canvas (if required) -->
          <div v-if="state === 'done' && needsSignature" class="override-field">
            <label class="override-label">SIGNATURE (REQUIRED)</label>
            <div class="signature-pad-wrapper">
              <canvas
                ref="signatureCanvas"
                @mousedown="startSignature"
                @mousemove="drawSignature"
                @mouseup="endSignature"
                @mouseleave="endSignature"
                @touchstart.prevent="startSignature"
                @touchmove.prevent="drawSignature"
                @touchend.prevent="endSignature"
                class="signature-canvas"
              ></canvas>
              <button type="button" @click="clearSignature" class="clear-signature-btn">Clear Signature</button>
            </div>
            <div class="override-field-hint">Sign with your mouse or finger</div>
          </div>
        </div>
      </section>

      <!-- 2 · Vehicle, driver & supervisor -->
      <section :class="['ovs', { 'ovs--active': crewChanged }]">
        <header class="ovs-head">
          <span class="ovs-icon ovs-icon--crew">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          </span>
          <div class="ovs-heading">
            <h4 class="ovs-title">Vehicle, driver &amp; supervisor</h4>
            <p class="ovs-sub">Now: {{ job?.vehicle || 'Unassigned' }} · {{ job?.driver || 'Unassigned' }} · {{ job?.supervisor || 'Unassigned' }}</p>
          </div>
          <span v-if="crewChanged" class="ovs-chip ovs-chip--accent">Changed</span>
        </header>

        <div class="ovs-body">
          <div class="override-field">
            <label class="override-label">VEHICLE</label>
            <select v-model="vehicleId" class="override-select">
              <option :value="null" disabled>Unassigned — pick a vehicle</option>
              <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleOptionLabel(v) }}</option>
            </select>
            <div v-if="vehicleId !== (job?.vehicle_id ?? null)" class="override-field-hint override-field-hint--change">
              Changes from {{ job?.vehicle || 'Unassigned' }}
            </div>
          </div>
          <div class="override-two-col">
            <div class="override-field">
              <label class="override-label">DRIVER</label>
              <select v-model="driverId" class="override-select">
                <option :value="null" disabled>Unassigned — pick a driver</option>
                <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
              </select>
              <div v-if="driverId !== (job?.driver_id ?? null)" class="override-field-hint override-field-hint--change">
                Changes from {{ job?.driver || 'Unassigned' }}
              </div>
            </div>
            <div class="override-field">
              <label class="override-label">SUPERVISOR</label>
              <select v-model="supervisorId" class="override-select">
                <option :value="null" disabled>Unassigned — pick a supervisor</option>
                <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
              </select>
              <div v-if="supervisorId !== (job?.supervisor_id ?? null)" class="override-field-hint override-field-hint--change">
                Changes from {{ job?.supervisor || 'Unassigned' }}
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- 3 · Flight -->
      <section v-if="isFlightJob(job)" :class="['ovs', { 'ovs--active': flightChanged }]">
        <header class="ovs-head">
          <span class="ovs-icon ovs-icon--flight">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/></svg>
          </span>
          <div class="ovs-heading">
            <h4 class="ovs-title">{{ job.flight.direction === 'arrival' ? 'Arrival flight' : 'Departure flight' }}</h4>
            <p class="ovs-sub">
              {{ job.flight.flight_number || 'Flight TBC' }}<template v-if="flightRoute(job.flight)"> · {{ flightRoute(job.flight) }}</template><template v-if="job.flight.scheduled_time"> · {{ job.flight.scheduled_date }} {{ job.flight.scheduled_time }}</template>
            </p>
          </div>
          <span v-if="flightChanged" class="ovs-chip ovs-chip--accent">Changed</span>
        </header>

        <div class="ovs-body">
          <div class="override-flight-grid">
            <div class="override-field">
              <span class="override-sublabel">Flight no.</span>
              <input v-model="flight.flight_number" type="text" maxlength="20" placeholder="e.g. QR123"
                :class="['override-input', { 'override-input--changed': 'flight_number' in flightChanges }]" />
            </div>
            <div class="override-field">
              <span class="override-sublabel">{{ job.flight.direction === 'arrival' ? 'Scheduled arrival' : 'Scheduled departure' }}</span>
              <input v-model="flight.scheduled" type="datetime-local"
                :class="['override-input', { 'override-input--changed': 'flight_scheduled_at' in flightChanges }]" />
            </div>
            <div class="override-field">
              <span class="override-sublabel">Terminal</span>
              <input v-model="flight.terminal" type="text" maxlength="50"
                :class="['override-input', { 'override-input--changed': 'flight_terminal' in flightChanges }]" />
            </div>
            <div class="override-field">
              <span class="override-sublabel">Gate</span>
              <input v-model="flight.gate" type="text" maxlength="50"
                :class="['override-input', { 'override-input--changed': 'flight_gate' in flightChanges }]" />
            </div>
          </div>
          <div v-if="flightChanged" class="override-field-hint override-field-hint--change">
            <template v-if="flightTimeChanged">
              Outstanding checkpoints and the movement window will be re-timed from the checkpoint settings.
            </template>
            Flight changes are saved to the team's flight and logged to the audit trail.
          </div>
        </div>
      </section>

      <div class="override-field">
        <label class="override-label">NOTES</label>
        <textarea v-model="notes" class="override-textarea" placeholder="Additional notes (optional)" rows="3" />
      </div>

      <div v-if="error" class="override-error">{{ error }}</div>
    </div>

    <template #footer>
      <div class="override-footer-inner">
        <span class="override-signed-as">Signed as <strong>{{ page.props.auth?.user?.name || 'Unknown user' }}</strong></span>
        <div style="display:flex;gap:8px;">
          <Button variant="secondary" size="sm" @click="close" :disabled="processing">Cancel</Button>
          <Button variant="primary" size="sm" :disabled="!canSubmit" :processing="processing" @click="submit">{{ state ? 'Override & log' : flightChanged && !crewChanged ? 'Save flight change' : 'Save changes' }}</Button>
        </div>
      </div>
    </template>
  </Modal>

  <!-- Photo Lightbox -->
  <div v-if="showLightbox" class="photo-lightbox" @click="showLightbox = false">
    <div class="photo-lightbox-content" @click.stop>
      <img :src="photoPreview" alt="Photo full view" />
      <button type="button" @click="showLightbox = false" class="photo-lightbox-close">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
          <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Button from './Button.vue';

/**
 * The Jobs Queue override: complete or skip a checkpoint (with time, evidence
 * and baggage), change the crew, or correct the flight. `job` is a Jobs Queue row.
 */
const props = defineProps({
  show: { type: Boolean, default: false },
  job: { type: Object, default: null },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  vehicles: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'saved']);
const page = usePage();

const STATES = [
  { value: 'done', label: 'Done', icon: '✓', desc: 'Confirm completion manually' },
  { value: 'skipped', label: 'Skipped', icon: '↷', desc: 'No longer applies to this job' },
];

const processing = ref(false);
const error = ref('');
const checkpoint = ref(null);
const state = ref(null); // null = leave the checkpoint as it is (crew/flight change only)
const time = ref('');
const excludeDate = ref(false);
const reason = ref('');
const notes = ref('');
const driverId = ref(null);
const supervisorId = ref(null);
const vehicleId = ref(null);
const flight = ref({ flight_number: '', scheduled: '', terminal: '', gate: '' });
const bags = ref({ planned_bags: 0, bags_loaded: 0, food_bags: 0, oversized_pieces: 0 });
const photo = ref(null);
const photoPreview = ref(null);
const signature = ref(null);
const photoInput = ref(null);
const signatureCanvas = ref(null);
const showLightbox = ref(false);
let drawing = false;
let ctx = null;

const needsPhotoOf = (cp) => !!(cp?.requires_photo || cp?.requiresPhoto);
const needsSignatureOf = (cp) => !!(cp?.requires_signature || cp?.requiresSignature);
const needsPhoto = computed(() => needsPhotoOf(checkpoint.value));
const needsSignature = computed(() => needsSignatureOf(checkpoint.value));
const needsBaggage = computed(() => !!(checkpoint.value?.checkpoint?.requires_baggage_count || checkpoint.value?.requires_baggage_count));

function isFlightJob(job) {
  return !!job?.flight && (job.kind === 'arrival' || job.kind === 'departure');
}

function flightRoute(f) {
  if (!f?.origin_airport && !f?.destination_airport) return '';
  return `${f.origin_airport || '?'} → ${f.destination_airport || '?'}`;
}

function flightDraftOf(f) {
  return {
    flight_number: f?.flight_number ?? '',
    scheduled: f?.scheduled_local ?? '',
    terminal: f?.terminal ?? '',
    gate: f?.gate ?? '',
  };
}

function vehicleOptionLabel(v) {
  const name = v.code || v.plate_number || v.vehicle_type || `#${v.id}`;
  return [name, v.code && v.plate_number ? v.plate_number : null, v.capacity ? `${v.capacity} seats` : null].filter(Boolean).join(' · ');
}

function syncBaggage() {
  const cp = checkpoint.value;
  bags.value = {
    planned_bags: cp?.planned_bags || 0,
    bags_loaded: cp?.bags_loaded || 0,
    food_bags: cp?.food_bags || 0,
    oversized_pieces: cp?.oversized_pieces || 0,
  };
}

watch(checkpoint, syncBaggage);

// A hidden reason must not be sent with a crew-only change.
watch(state, (s) => {
  if (!s) reason.value = '';
});

// The canvas mounts and unmounts with the state and checkpoint picked.
watch(signatureCanvas, (el) => {
  ctx = null;
  signature.value = null;
  if (el) initSignatureCanvas();
});

watch(() => props.show, (open) => {
  if (!open) return;
  const job = props.job;
  const active = job?.checkpoints?.find(c => c.status === 'active' || c.state === 'active');
  checkpoint.value = active ?? job?.checkpoints?.[0] ?? null;
  state.value = null;
  const now = new Date();
  time.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  excludeDate.value = false;
  reason.value = '';
  notes.value = '';
  error.value = '';
  driverId.value = job?.driver_id ?? null;
  supervisorId.value = job?.supervisor_id ?? null;
  vehicleId.value = job?.vehicle_id ?? null;
  flight.value = flightDraftOf(job?.flight);
  syncBaggage();
  photo.value = null;
  photoPreview.value = null;
  signature.value = null;
  processing.value = false;
  showLightbox.value = false;
});

function close() {
  emit('close');
}

// Read as text so the event's wall-clock time shows, whatever the browser's timezone.
const jobStart = computed(() => {
  const m = props.job?.span_start?.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
  if (!m) return null;
  const day = new Date(Date.UTC(+m[1], m[2] - 1, +m[3]))
    .toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' });
  return `${day}, ${m[4]}`;
});

const crewChanged = computed(() =>
  (vehicleId.value !== null && vehicleId.value !== (props.job?.vehicle_id ?? null))
  || (driverId.value !== null && driverId.value !== (props.job?.driver_id ?? null))
  || (supervisorId.value !== null && supervisorId.value !== (props.job?.supervisor_id ?? null))
);

// Only edited fields are sent, keyed as the override endpoint expects.
const flightChanges = computed(() => {
  if (!isFlightJob(props.job)) return {};
  const before = flightDraftOf(props.job.flight);
  const keys = { flight_number: 'flight_number', scheduled: 'flight_scheduled_at', terminal: 'flight_terminal', gate: 'flight_gate' };
  return Object.fromEntries(Object.entries(keys)
    .filter(([field]) => (flight.value[field] ?? '').trim() !== (before[field] ?? '').trim())
    .map(([field, key]) => [key, (flight.value[field] ?? '').trim()]));
});
const flightChanged = computed(() => Object.keys(flightChanges.value).length > 0);
const flightTimeChanged = computed(() => 'flight_scheduled_at' in flightChanges.value && !!flight.value.scheduled);

// On save the server re-times outstanding checkpoints to new flight time + their configured offset.
const checkpointShift = computed(() => {
  const cp = checkpoint.value;
  if (!flightTimeChanged.value || !cp || ['done', 'skipped'].includes(cp.state)) return 0;
  if (cp.flight_offset == null || !cp.scheduled_local) return 0;
  const planned = new Date(flight.value.scheduled).getTime() + cp.flight_offset * 60000;
  return Math.round((planned - new Date(cp.scheduled_local).getTime()) / 60000);
});

const plannedLabel = computed(() => {
  const label = checkpoint.value?.scheduled_at || checkpoint.value?.at;
  const m = label?.match(/^(\d{1,2}):(\d{2})/);
  if (!m || !checkpointShift.value) return label || '—';
  const t = (((Number(m[1]) * 60 + Number(m[2]) + checkpointShift.value) % 1440) + 1440) % 1440;
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}, was ${label}`;
});

const varianceMinutes = computed(() => {
  if (!checkpoint.value?.scheduled_ts || !time.value) return null;
  const shift = checkpointShift.value;
  const [ah, am] = time.value.split(':').map(Number);
  if (isNaN(ah) || isNaN(am)) return null;

  if (excludeDate.value) {
    // Time of day only, from the "HH:mm" label: re-deriving hours from the
    // timestamp would use the browser's timezone, not the event's.
    const schedMatch = (checkpoint.value?.scheduled_at || checkpoint.value?.at)?.match(/^(\d{1,2}):(\d{2})/);
    if (!schedMatch) return null;
    let diff = ah * 60 + am - (Number(schedMatch[1]) * 60 + Number(schedMatch[2]) + shift);
    if (diff > 720) diff -= 1440;
    if (diff < -720) diff += 1440;
    return diff;
  }

  const scheduledHour = new Date((checkpoint.value.scheduled_ts + shift * 60) * 1000).getHours();
  const actualDate = new Date();
  actualDate.setHours(ah, am, 0, 0);
  // Scheduled late at night, done early morning: the next day.
  if (scheduledHour >= 18 && ah < 6) actualDate.setDate(actualDate.getDate() + 1);
  return Math.round((Math.floor(actualDate.getTime() / 1000) - (checkpoint.value.scheduled_ts + shift * 60)) / 60);
});

const varianceText = computed(() => {
  const v = varianceMinutes.value;
  if (v === null) return '—';
  if (v === 0) return 'On time';
  return v > 0 ? `${Math.abs(v)} min late` : `${Math.abs(v)} min early`;
});

const canSubmit = computed(() => {
  if (processing.value) return false;
  // Without a new state there is only a crew or flight change to save.
  if (!state.value) return crewChanged.value || flightChanged.value;
  if (!checkpoint.value || !reason.value) return false;
  if (state.value === 'done') {
    if (needsPhoto.value && !photo.value) return false;
    if (needsSignature.value && !signature.value) return false;
  }
  return true;
});

function handlePhotoUpload(event) {
  const file = event.target.files[0];
  if (!file) return;
  if (!file.type.startsWith('image/')) {
    error.value = 'Please select a valid image file.';
    return;
  }
  if (file.size > 10 * 1024 * 1024) {
    error.value = 'Photo must be less than 10MB.';
    return;
  }
  error.value = '';
  photo.value = file;
  const reader = new FileReader();
  reader.onload = (e) => { photoPreview.value = e.target.result; };
  reader.readAsDataURL(file);
}

function clearPhoto() {
  photo.value = null;
  photoPreview.value = null;
  if (photoInput.value) photoInput.value.value = '';
}

function initSignatureCanvas() {
  const canvas = signatureCanvas.value;
  if (!canvas) return;
  canvas.width = canvas.offsetWidth;
  canvas.height = 150;
  ctx = canvas.getContext('2d');
  ctx.strokeStyle = '#111827';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
}

function pointOf(e) {
  const rect = signatureCanvas.value.getBoundingClientRect();
  const p = e.touches?.[0] ?? e;
  return [p.clientX - rect.left, p.clientY - rect.top];
}

function startSignature(e) {
  if (!ctx) initSignatureCanvas();
  if (!ctx) return;
  drawing = true;
  ctx.beginPath();
  ctx.moveTo(...pointOf(e));
}

function drawSignature(e) {
  if (!drawing || !ctx) return;
  ctx.lineTo(...pointOf(e));
  ctx.stroke();
}

function endSignature() {
  if (!drawing) return;
  drawing = false;
  signature.value = signatureCanvas.value.toDataURL();
}

function clearSignature() {
  if (!ctx || !signatureCanvas.value) return;
  ctx.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height);
  signature.value = null;
}

async function submit() {
  if (!canSubmit.value || !checkpoint.value) return;

  processing.value = true;
  error.value = '';
  const job = props.job;
  const body = new FormData();
  if (state.value) body.append('state', state.value);
  if (reason.value) body.append('reason', reason.value);
  if (notes.value) body.append('notes', notes.value);
  if (driverId.value !== null && driverId.value !== (job?.driver_id ?? null)) body.append('driver_id', driverId.value);
  if (supervisorId.value !== null && supervisorId.value !== (job?.supervisor_id ?? null)) body.append('supervisor_id', supervisorId.value);
  if (vehicleId.value !== null && vehicleId.value !== (job?.vehicle_id ?? null)) body.append('vehicle_id', vehicleId.value);
  for (const [key, value] of Object.entries(flightChanges.value)) body.append(key, value);

  if (state.value === 'done') {
    body.append('actual_time', time.value);
    body.append('exclude_date', excludeDate.value ? '1' : '0');
    // A PMA arrival also stamps the team flight's actual arrival.
    if ((checkpoint.value.name || checkpoint.value.label || '').toLowerCase().includes('pma arrival')) {
      body.append('update_flight_actual', 'true');
    }
    if (needsBaggage.value) {
      for (const [key, value] of Object.entries(bags.value)) body.append(key, value);
    }
    if (photo.value instanceof File) {
      const safeName = photo.value.name.replace(/[^a-zA-Z0-9.-]/g, '_');
      body.append('photo', new File([photo.value], safeName, { type: photo.value.type }));
    }
    if (needsSignature.value && signature.value) body.append('signature_data', signature.value);
  }

  try {
    const response = await fetch(`/jobs/checkpoint/${checkpoint.value.id || checkpoint.value.dbId}/override`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      body,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      error.value = data.message || `Failed to save the override (${response.status}).`;
      return;
    }
    emit('saved');
  } catch {
    error.value = 'Could not reach the server. Please try again.';
  } finally {
    processing.value = false;
  }
}
</script>

<style scoped>
.override-modal-title-wrap { display: flex; flex-direction: column; gap: 2px; }
.override-privileged-badge {
  font-size: 10px; font-weight: 700; letter-spacing: 0.8px;
  text-transform: uppercase; color: #b45309;
}
.override-job-start {
  display: inline-flex; align-items: center; gap: 5px; width: fit-content; margin-top: 2px;
  font-size: 11.5px; font-weight: 600; color: var(--ink2);
  padding: 2px 8px; border-radius: 6px; background: var(--panel); border: 1px solid var(--border);
}
.override-job-start svg { color: var(--ink3); }

.override-body { display: flex; flex-direction: column; gap: 14px; }

.ovs {
  border: 1px solid var(--border); border-radius: 12px; background: var(--surface);
  overflow: hidden; transition: border-color 0.15s, box-shadow 0.15s;
}
.ovs--active { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
.ovs-head {
  display: flex; align-items: center; gap: 12px; padding: 12px 16px;
  background: linear-gradient(180deg, var(--panel), transparent);
  border-bottom: 1px solid var(--border);
}
.ovs-icon {
  width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
}
.ovs-icon--checkpoint { background: var(--ok-soft); color: var(--ok); }
.ovs-icon--crew { background: var(--accent-soft); color: var(--accent-fg); }
.ovs-icon--flight { background: var(--live-soft); color: var(--live); }
.ovs-heading { flex: 1; min-width: 0; }
.ovs-title { margin: 0; font-size: 13.5px; font-weight: 700; color: var(--ink); }
.ovs-sub {
  margin: 1px 0 0; font-size: 11.5px; color: var(--ink3);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.ovs-chip {
  flex-shrink: 0; font-size: 10.5px; font-weight: 700; letter-spacing: 0.3px;
  padding: 3px 9px; border-radius: 999px; text-transform: uppercase;
}
.ovs-chip--ok { background: var(--ok-soft); color: var(--ok); }
.ovs-chip--muted { background: var(--panel); color: var(--ink3); border: 1px solid var(--border); }
.ovs-chip--accent { background: var(--accent-soft); color: var(--accent-fg); }
.ovs-body { display: flex; flex-direction: column; gap: 14px; padding: 14px 16px 16px; }

.override-warning {
  display: flex; gap: 10px; align-items: flex-start;
  background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px;
  padding: 12px 14px; font-size: 12px; color: #92400e; line-height: 1.5;
}
.override-warning svg { flex-shrink: 0; margin-top: 1px; color: #d97706; }

.override-field { display: flex; flex-direction: column; gap: 6px; }
.override-label {
  font-size: 10px; font-weight: 700; letter-spacing: 0.8px;
  text-transform: uppercase; color: var(--ink3);
}
.override-select, .override-input {
  width: 100%; padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  font-size: 13px; color: var(--ink); font-family: inherit;
  outline: none; box-sizing: border-box;
}
.override-select:focus, .override-input:focus {
  border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring);
}
.override-select--checkpoints { font-family: var(--font-sans, sans-serif); }
.override-select--checkpoints option { padding: 8px; line-height: 1.5; }
.checkpoint-option--done { color: #059669; font-weight: 500; }

.override-field-hint { font-size: 11px; color: var(--ink4); }
.override-field-hint--change { color: #b45309; font-weight: 600; }
.override-error { font-size: 12.5px; color: var(--danger); font-weight: 600; }

.override-states { display: grid; gap: 8px; }
.override-state-btn {
  position: relative;
  display: flex; flex-direction: column; gap: 3px;
  padding: 10px 12px; border-radius: 8px;
  border: 1.5px solid var(--border); background: var(--surface);
  cursor: pointer; text-align: left; font-family: inherit;
  transition: border-color 0.15s, background 0.15s;
}
.override-state-btn:hover { border-color: var(--ink4); }
.override-state-check {
  position: absolute; top: 8px; right: 8px;
  width: 16px; height: 16px; border-radius: 4px;
  display: grid; place-items: center;
  border: 1.5px solid var(--border); background: var(--surface); color: #fff;
  transition: background 0.15s, border-color 0.15s;
}
.override-state-btn--active .override-state-check { border-color: currentColor; }
.override-state-btn--done.override-state-btn--active .override-state-check { background: #166534; border-color: #166534; }
.override-state-btn--skipped.override-state-btn--active .override-state-check { background: var(--ink3); border-color: var(--ink3); }
.override-state-btn strong { font-size: 13px; font-weight: 700; color: var(--ink); }
.override-state-btn small { font-size: 10px; color: var(--ink3); line-height: 1.3; }
.override-state-icon { font-size: 14px; margin-bottom: 2px; }
.override-state-btn--done.override-state-btn--active { border-color: var(--ok); background: #f0fdf4; }
.override-state-btn--done.override-state-btn--active .override-state-icon,
.override-state-btn--done.override-state-btn--active strong { color: #166534; }
.override-state-btn--skipped.override-state-btn--active { border-color: var(--ink3); background: var(--panel); }

.override-two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.override-four-col { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; }

.override-flight-grid { display: grid; grid-template-columns: 1fr 1.4fr 0.8fr 0.8fr; gap: 12px; }
.override-flight-grid .override-field { gap: 4px; }
.override-sublabel { font-size: 10.5px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.04em; }
.override-input--changed { border-color: var(--accent); }

.override-variance {
  padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--panel);
  font-size: 13px; font-family: var(--font-mono, monospace);
  font-weight: 600; color: var(--ink3);
  min-height: 38px; display: flex; align-items: center;
}
.override-variance.is-late { color: #c2410c; }
.override-variance.is-early { color: #166534; }

.override-exclude-date {
  display: flex; align-items: flex-start; gap: 6px;
  font-size: 11px; color: var(--ink3); cursor: pointer;
}
.override-exclude-date input { margin-top: 2px; accent-color: var(--accent); flex-shrink: 0; }

.override-textarea {
  width: 100%; padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  font-size: 13px; color: var(--ink); font-family: inherit;
  outline: none; resize: vertical; box-sizing: border-box;
}
.override-textarea:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }

.override-footer-inner { display: flex; align-items: center; justify-content: space-between; width: 100%; }
.override-signed-as { font-size: 12px; color: var(--ink3); }
.override-signed-as strong { color: var(--ink); }

.override-file-input {
  width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 7px;
  background: var(--surface); font-size: 13px; color: var(--ink); font-family: inherit; cursor: pointer;
}
.photo-preview {
  position: relative; margin-top: 8px; border: 1px solid var(--border);
  border-radius: 7px; overflow: hidden; max-width: 300px;
}
.photo-preview img { width: 100%; height: auto; display: block; }
.clear-photo-btn {
  position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; border-radius: 6px;
  background: rgba(0, 0, 0, 0.6); border: none; color: white; cursor: pointer;
  display: flex; align-items: center; justify-content: center; transition: background 0.15s;
}
.clear-photo-btn:hover { background: rgba(0, 0, 0, 0.8); }

.signature-pad-wrapper { display: flex; flex-direction: column; gap: 8px; }
.signature-canvas {
  width: 100%; height: 150px; border: 1px solid var(--border); border-radius: 7px;
  background: #FAFAFA; cursor: crosshair; touch-action: none;
}
.clear-signature-btn {
  align-self: flex-end; padding: 6px 12px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink2); font-size: 12px; font-weight: 500;
  cursor: pointer; transition: all 0.15s; font-family: inherit;
}
.clear-signature-btn:hover { background: var(--panel); border-color: var(--ink3); }

.photo-lightbox {
  position: fixed; inset: 0; background: rgba(0, 0, 0, 0.95); z-index: 99999;
  display: flex; align-items: center; justify-content: center; padding: 20px; cursor: zoom-out;
}
.photo-lightbox-content { position: relative; max-width: 90vw; max-height: 90vh; cursor: default; }
.photo-lightbox-content img {
  max-width: 100%; max-height: 90vh; width: auto; height: auto; display: block;
  border-radius: 8px; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}
.photo-lightbox-close {
  position: absolute; top: -50px; right: 0; width: 40px; height: 40px; border-radius: 8px;
  background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
}
.photo-lightbox-close:hover { background: rgba(255, 255, 255, 0.2); }

@media (max-width: 640px) {
  .override-flight-grid { grid-template-columns: 1fr 1fr; }
  .override-two-col, .override-four-col { grid-template-columns: 1fr 1fr; }
  .photo-lightbox-close { top: 10px; right: 10px; }
}
</style>
