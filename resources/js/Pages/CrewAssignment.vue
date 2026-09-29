<template>
  <app-layout>
    <div v-if="!hasActiveEvent" class="empty-state-full">
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">Select an event from the dropdown above to assign crews.</p>
    </div>

    <div v-else>
      <div class="page-header">
        <div>
          <h1 class="page-title">Crew Assignment</h1>
          <p class="page-sub">{{ movements.length }} movement{{ movements.length === 1 ? '' : 's' }} · {{ unassignedCount }} need a crew · {{ dateLabel }}</p>
        </div>
        <div class="page-header-actions">
          <DatePicker :model-value="selectedDate" @update:model-value="onDateChange" />
          <RefreshButton :only="['movements', 'date', 'vehicles', 'drivers', 'supervisors']" />
          <div class="filter-tabs">
            <button v-for="f in filters" :key="f.value"
              :class="['filter-tab', activeFilter === f.value ? 'filter-tab--active' : '']"
              @click="activeFilter = f.value">
              {{ f.label }}
            </button>
          </div>
        </div>
      </div>

      <input v-model="search" class="crew-search" type="search" placeholder="Search movement, team or route" />

      <div v-if="!filtered.length" class="crew-empty">No movements match.</div>

      <div v-else class="crew-card">
        <table class="crew-table">
          <thead>
            <tr>
              <th>Movement</th><th>Team</th><th>Route</th><th>Pax</th>
              <th>Vehicle</th><th>Driver</th><th>Supervisor</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="mv in filtered" :key="mv.id">
              <td data-label="Movement">
                <div class="mono">{{ mv.code }}</div>
                <div class="sub">{{ mv.start ?? '--:--' }}–{{ mv.end ?? '--:--' }} · {{ mv.kind }}</div>
                <status-pill v-if="mv.job_status" :tone="jobTone(mv.job_status)">{{ statusLabel(mv.job_status) }}</status-pill>
              </td>
              <td data-label="Team">
                <span class="team-badge-sm">{{ mv.team_code }}</span> {{ mv.team }}
              </td>
              <td data-label="Route" class="sub">
                {{ mv.from }} → {{ mv.to }}
                <div v-if="mv.flight_number">{{ mv.flight_number }}</div>
              </td>
              <td data-label="Pax">{{ mv.pax ?? '—' }}</td>
              <td data-label="Vehicle">
                <select v-model="drafts[mv.id].vehicle_id" :aria-label="`Vehicle for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="v in vehicles" :key="v.id" :value="v.id">{{ vehicleLabel(v) }}</option>
                </select>
              </td>
              <td data-label="Driver">
                <select v-model="drafts[mv.id].driver_id" :aria-label="`Driver for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="d in drivers" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
              </td>
              <td data-label="Supervisor">
                <select v-model="drafts[mv.id].field_supervisor_id" :aria-label="`Supervisor for ${mv.code}`">
                  <option :value="null">Unassigned</option>
                  <option v-for="s in supervisors" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
              </td>
              <td class="actions">
                <button class="save-btn" :disabled="!isDirty(mv) || saving === mv.id" @click="save(mv)">
                  {{ saving === mv.id ? 'Saving…' : 'Save' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import DatePicker from '../Components/DatePicker.vue';
import { useToast } from '../Composables/useToast';
import { useStatusLabels } from '../Composables/useStatusLabels';

const props = defineProps({
  movements: { type: Array, default: () => [] },
  date: { type: String, default: null },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
});

const page = usePage();
const hasActiveEvent = computed(() => !!page.props.activeEventId);
const { success: showSuccessToast, error: showErrorToast } = useToast();
const { statusLabel } = useStatusLabels();

watch(() => page.props.flash, (flash) => {
  if (flash?.success) showSuccessToast(flash.success);
  if (flash?.error) showErrorToast(flash.error);
}, { deep: true });

const CREW_FIELDS = ['vehicle_id', 'driver_id', 'field_supervisor_id'];

const drafts = ref({});
const crewOf = (mv) => Object.fromEntries(CREW_FIELDS.map((f) => [f, mv[f] ?? null]));
const sameCrew = (a, b) => CREW_FIELDS.every((f) => (a?.[f] ?? null) === (b?.[f] ?? null));

// Saving one row reloads the list; keep unsaved edits on the other rows.
watch(() => props.movements, (list, oldList = []) => {
  const before = Object.fromEntries(oldList.map((mv) => [mv.id, mv]));
  const prev = drafts.value;
  drafts.value = Object.fromEntries(list.map((mv) => {
    const edited = prev[mv.id] && before[mv.id] && !sameCrew(prev[mv.id], before[mv.id]);
    return [mv.id, edited ? prev[mv.id] : crewOf(mv)];
  }));
}, { immediate: true });

const isDirty = (mv) => !sameCrew(drafts.value[mv.id], mv);
const isUnassigned = (mv) => CREW_FIELDS.some((f) => !mv[f]);
const unassignedCount = computed(() => props.movements.filter(isUnassigned).length);

const filters = [
  { value: 'all', label: 'All' },
  { value: 'unassigned', label: 'Needs crew' },
];
const activeFilter = ref('all');
const search = ref('');

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  return props.movements
    .filter((mv) => activeFilter.value === 'all' || isUnassigned(mv))
    .filter((mv) => !q || [mv.code, mv.team, mv.team_code, mv.from, mv.to, mv.flight_number]
      .some((v) => (v || '').toLowerCase().includes(q)));
});

const saving = ref(null);

function save(mv) {
  saving.value = mv.id;
  router.patch(`/movements/${mv.id}/crew`, drafts.value[mv.id], {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => showErrorToast(Object.values(errors)[0] ?? 'Could not save the crew.'),
    onFinish: () => { saving.value = null; },
  });
}

function vehicleLabel(v) {
  const name = v.code || v.plate_number || v.vehicle_type || `#${v.id}`;
  return v.capacity ? `${name} (${v.capacity} seats)` : name;
}

const jobTones = { 'in-progress': 'live', completed: 'ok', dispatched: 'primary', cancelled: 'danger' };
const jobTone = (s) => jobTones[s] ?? 'neutral';

function todayIso() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

const selectedDate = ref(props.date || todayIso());
watch(() => props.date, (v) => { if (v) selectedDate.value = v; });

const dateLabel = computed(() => new Date(`${selectedDate.value}T00:00:00`)
  .toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }));

function onDateChange(dateStr) {
  selectedDate.value = dateStr;
  router.get('/crew-assignment', { date: dateStr }, {
    preserveState: true,
    preserveScroll: true,
    only: ['movements', 'date'],
  });
}
</script>

<style scoped>
.empty-state-full {
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  min-height: 60vh; text-align: center;
}
.empty-state-title { font-size: 24px; font-weight: 700; color: var(--ink); margin-bottom: 8px; }
.empty-state-text { font-size: 14px; color: var(--ink3); max-width: 400px; }

.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.page-header-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.filter-tabs { display: flex; gap: 4px; }
.filter-tab {
  padding: 5px 12px; border-radius: 20px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; cursor: pointer; color: var(--ink3); font-weight: 500;
}
.filter-tab:hover { background: var(--panel); color: var(--ink); }
.filter-tab--active { background: var(--accent); color: #fff; border-color: var(--accent); }

.crew-search {
  width: 100%; max-width: 360px; margin-bottom: 12px;
  padding: 7px 10px; border: 1px solid var(--border); border-radius: 8px;
  background: var(--surface); color: var(--ink); font-size: 13px;
}
.crew-empty { padding: 40px; text-align: center; color: var(--ink3); font-size: 13px; }

.crew-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow-x: auto;
}
.crew-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.crew-table th {
  padding: 8px 12px; text-align: left; font-size: 11px; font-weight: 600;
  text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink3);
  border-bottom: 1px solid var(--border); background: var(--panel); white-space: nowrap;
}
.crew-table td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.crew-table tr:last-child td { border-bottom: none; }
.crew-table select {
  width: 100%; min-width: 150px; padding: 6px 8px;
  border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink); font-size: 12.5px;
}

.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink); font-weight: 600; }
.sub { color: var(--ink3); font-size: 12px; }
.team-badge-sm {
  display: inline-flex; align-items: center; justify-content: center;
  min-width: 28px; height: 22px; padding: 0 4px; border-radius: 6px;
  background: var(--accent-soft); color: var(--accent-fg); font-size: 9px; font-weight: 700;
}

.actions { text-align: right; white-space: nowrap; }
.save-btn {
  padding: 6px 14px; border-radius: 6px; border: none; cursor: pointer;
  background: var(--accent); color: #fff; font-size: 12.5px; font-weight: 600;
}
.save-btn:disabled { opacity: 0.4; cursor: default; }

/* Cards on phones: one movement per block, labels from data-label. */
@media (max-width: 767px) {
  .crew-card { overflow: visible; background: none; border: none; }
  .crew-table thead { display: none; }
  .crew-table, .crew-table tbody, .crew-table tr, .crew-table td { display: block; width: 100%; }
  .crew-table tr {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: 10px; padding: 8px 0; margin-bottom: 10px;
  }
  .crew-table td { border: none; padding: 6px 12px; }
  .crew-table td[data-label]::before {
    content: attr(data-label); display: block;
    font-size: 10.5px; font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.05em; color: var(--ink4); margin-bottom: 3px;
  }
  .crew-table select { min-height: 40px; font-size: 15px; }
  .save-btn { width: 100%; min-height: 40px; }
}
</style>
