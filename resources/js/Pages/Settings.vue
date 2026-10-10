<template>
  <app-layout>
    <div class="st-page">
      <header class="st-head">
        <div class="st-head-title">
          <div class="st-kicker">Admin</div>
          <h1 class="st-h1">Settings</h1>
        </div>
        <div class="st-head-right">
          <span class="st-note">Changes apply to every event unless overridden</span>
          <RefreshButton :only="['globalOverrides', 'eventOverrides', 'conflictThresholds', 'uiFlags']" />
        </div>
      </header>

      <div class="st-layout">
        <nav class="st-nav" aria-label="Settings sections">
          <div v-for="g in navGroups" :key="g.label" class="st-nav-group">
            <div class="st-nav-label">{{ g.label }}</div>
            <button v-for="it in g.items" :key="it.id" type="button"
              :class="['st-nav-item', { 'st-nav-item--on': section === it.id }]" @click="go(it.id)">
              <span class="st-nav-bar" />
              <span class="st-nav-text">
                <span class="st-nav-name">{{ it.label }}</span>
                <span class="st-nav-sub">{{ it.sub }}</span>
              </span>
              <span v-if="it.count" :class="['st-nav-count', { 'st-nav-count--alert': it.alert }]">{{ it.count }}</span>
            </button>
          </div>
        </nav>

        <main class="st-main">
          <div class="st-main-head">
            <div class="st-main-title">
              <h2 class="st-h2">{{ view.title }}</h2>
              <p class="st-desc">{{ view.desc }}</p>
            </div>
            <Button v-if="view.canAdd" variant="primary" size="md" @click="showAdd = !showAdd">
              {{ showAdd ? 'Close' : section === 'overrides' ? '+ Add override' : '+ Add offset' }}
            </Button>
          </div>

          <!-- Add a global offset -->
          <div v-if="showAdd && section === 'offsets'" class="st-add">
            <label class="st-field st-field--grow">Movement type
              <Select v-model="globalForm.movement_type" :options="movementTypeOptions" optionLabel="label" optionValue="value" placeholder="Choose type..." class="w-full" />
            </label>
            <label class="st-field st-field--wide">Checkpoint (optional)
              <Select v-model="globalForm.checkpoint_id" :options="checkpoints" optionLabel="name" optionValue="id" filter filterPlaceholder="Search checkpoints..." showClear placeholder="— None (movement-type default) —" class="w-full" />
            </label>
            <label class="st-field st-field--narrow">Offset (min)
              <input v-model.number="globalForm.value" type="number" step="15" min="-999" max="999" class="st-input st-input--mono" placeholder="-180" />
            </label>
            <div class="st-field st-field--narrow">
              <span>Reads as</span>
              <span class="st-reads">{{ reads(globalForm.value) }}</span>
            </div>
            <label class="st-field st-field--wide">Description (optional)
              <input v-model="globalForm.description" type="text" class="st-input" />
            </label>
            <div class="st-add-actions">
              <Button variant="ghost" size="sm" @click="closeAdd">Cancel</Button>
              <Button variant="primary" size="sm" :disabled="!globalForm.movement_type || globalForm.value === '' || addingGlobalOverride" :processing="addingGlobalOverride" @click="saveGlobalOverride">Save offset</Button>
            </div>
          </div>

          <!-- Add an event override -->
          <div v-if="showAdd && section === 'overrides'" class="st-add">
            <label class="st-field st-field--wide">Event
              <Select v-model="selectedEventId" :options="events" optionLabel="name" optionValue="id" placeholder="Choose an event..." class="w-full" />
            </label>
            <label class="st-field st-field--grow">Movement type
              <Select v-model="eventForm.movement_type" :options="movementTypeOptions" optionLabel="label" optionValue="value" placeholder="Choose type..." class="w-full" />
            </label>
            <label class="st-field st-field--wide">Checkpoint (optional)
              <Select v-model="eventForm.checkpoint_id" :options="checkpoints" optionLabel="name" optionValue="id" filter filterPlaceholder="Search checkpoints..." showClear placeholder="— None (movement-type override) —" class="w-full" />
            </label>
            <label class="st-field st-field--narrow">Offset (min)
              <input v-model.number="eventForm.value" type="number" step="15" min="-999" max="999" class="st-input st-input--mono" placeholder="-180" />
            </label>
            <div class="st-field st-field--narrow">
              <span>Reads as</span>
              <span class="st-reads">{{ reads(eventForm.value) }}</span>
            </div>
            <div class="st-add-actions">
              <Button variant="ghost" size="sm" @click="closeAdd">Cancel</Button>
              <Button variant="primary" size="sm" :disabled="!selectedEventId || !eventForm.movement_type || eventForm.value === '' || addingEventOverride" :processing="addingEventOverride" @click="saveEventOverride">Save override</Button>
            </div>
          </div>

          <!-- Movement offsets -->
          <div v-if="section === 'offsets'" class="st-body">
            <div class="st-toolbar">
              <div class="st-seg" role="tablist" aria-label="Movement type">
                <button v-for="f in typeFilters" :key="f.value" type="button" role="tab" :aria-selected="filter === f.value"
                  :class="['st-seg-btn', { 'st-seg-btn--on': filter === f.value }]" @click="filter = f.value">{{ f.label }}</button>
              </div>
              <div class="st-order">
                <span class="st-order-title">Resolution order</span>
                <span class="st-chip">1 · Event checkpoint</span><span>›</span>
                <span class="st-chip">2 · Global checkpoint</span><span>›</span>
                <span class="st-chip">3 · Event type</span><span>›</span>
                <span class="st-chip">4 · Global type</span>
              </div>
            </div>

            <section v-for="g in groups" :key="g.type" class="st-group">
              <div class="st-row st-row--head">
                <div class="st-group-name">
                  <span class="st-swatch" :style="{ background: g.color }" />
                  <span class="st-group-label">{{ g.label }}</span>
                  <span class="st-dim">{{ g.rows.length }}</span>
                </div>
                <div class="st-tl st-tl--head">
                  <span v-for="t in ticks" :key="t.m" class="st-tick" :class="{ 'st-tick--zero': t.m === 0 }" :style="{ left: t.left }">{{ t.label }}</span>
                  <span class="st-ref" :style="{ color: g.color }">{{ g.ref }}</span>
                </div>
                <div class="st-colhead">Offset</div>
                <div />
              </div>

              <div v-for="r in g.rows" :key="r.id" class="st-row st-item">
                <div class="st-name">
                  <span class="st-name-main">{{ r.name }}</span>
                  <span class="st-dim">{{ r.desc }}</span>
                </div>
                <div class="st-tl">
                  <span class="st-track" />
                  <span class="st-zero" />
                  <span class="st-bar" :style="{ left: r.barLeft, width: r.barWidth, background: g.soft }" />
                  <span class="st-dot" :style="{ left: r.dot, background: g.color }" />
                </div>
                <div class="st-offset">
                  <input v-if="editingGlobalId === r.id" v-model.number="globalEditForm.value" type="number" step="15" min="-999" max="999"
                    class="st-input st-input--edit" autofocus @keydown.enter="saveGlobalOverrideEdit(r.setting)" @keydown.esc="cancelGlobalEdit" />
                  <template v-else>
                    <span class="st-hm">{{ r.hm }}</span>
                    <span class="st-dim">{{ r.dir }}</span>
                  </template>
                </div>
                <div class="st-actions">
                  <template v-if="editingGlobalId === r.id">
                    <button type="button" class="st-icon st-icon--ok" title="Save" @click="saveGlobalOverrideEdit(r.setting)">✓</button>
                    <button type="button" class="st-icon" title="Cancel" @click="cancelGlobalEdit">✕</button>
                  </template>
                  <template v-else>
                    <button type="button" class="st-icon" title="Edit offset" @click="editGlobalRow(r.setting)">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                    </button>
                    <button type="button" class="st-icon st-icon--danger" title="Delete" @click="confirmDeleteOverride(r.setting, 'global')">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /></svg>
                    </button>
                  </template>
                </div>
              </div>
            </section>

            <div v-if="!groups.length" class="st-empty">No global offsets configured{{ filter === 'all' ? '' : ' for this movement type' }} yet.</div>
          </div>

          <!-- Event overrides -->
          <div v-if="section === 'overrides'" class="st-body">
            <div v-for="grp in overrideGroups" :key="grp.eventId" class="st-ovgroup">
              <div class="st-ovhead">
                <span class="st-ovevent">{{ grp.name }}</span>
                <span class="st-dim">{{ grp.rows.length }} override{{ grp.rows.length === 1 ? '' : 's' }}</span>
              </div>
              <div v-for="o in grp.rows" :key="o.id" class="st-ovrow">
                <span class="st-type" :style="{ color: o.color, background: o.soft }">{{ o.typeLabel }}</span>
                <span class="st-name-main">{{ o.name }}</span>
                <span class="st-change">
                  <template v-if="editingEventOverrideId === o.id">
                    <input v-model.number="eventEditForm.value" type="number" step="15" min="-999" max="999" class="st-input st-input--edit" autofocus
                      @keydown.enter="saveEventOverrideEdit(grp.eventId, o.setting)" @keydown.esc="cancelEventOverrideEdit" />
                  </template>
                  <template v-else>
                    <span class="st-was">{{ o.globalReads }}</span>
                    <span class="st-dim">→</span>
                    <span class="st-hm">{{ o.reads }}</span>
                  </template>
                </span>
                <span class="st-actions">
                  <template v-if="editingEventOverrideId === o.id">
                    <button type="button" class="st-icon st-icon--ok" title="Save" @click="saveEventOverrideEdit(grp.eventId, o.setting)">✓</button>
                    <button type="button" class="st-icon" title="Cancel" @click="cancelEventOverrideEdit">✕</button>
                  </template>
                  <template v-else>
                    <button type="button" class="st-icon" title="Edit offset" @click="editEventOverrideRow(grp.eventId, o.setting)">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z" /></svg>
                    </button>
                    <button type="button" class="st-icon st-icon--danger" title="Remove override" @click="confirmDeleteOverride(o.setting, 'event', grp.eventId)">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18" /><path d="M8 6V4h8v2" /><path d="M19 6l-1 14H6L5 6" /></svg>
                    </button>
                  </template>
                </span>
              </div>
            </div>
            <div v-if="!overrideGroups.length" class="st-empty">Every event uses the global offsets.</div>
          </div>

          <!-- Conflict rules -->
          <div v-if="section === 'rules'" class="st-rules">
            <div v-for="t in props.conflictThresholds" :key="t.key" class="st-rule">
              <div class="st-rule-body">
                <div class="st-rule-title">
                  <span class="st-rule-name">{{ t.label }}</span>
                  <span v-if="thresholdForm[t.key] !== t.default" class="st-custom">Custom</span>
                </div>
                <span class="st-rule-desc">{{ t.description }}</span>
                <span class="st-dim">Default {{ t.default }} {{ t.unit }}</span>
                <span v-if="thresholdErrors[t.key]" class="st-error">{{ thresholdErrors[t.key] }}</span>
              </div>
              <div class="st-stepper-wrap">
                <div class="st-stepper">
                  <button type="button" class="st-step" :aria-label="`Decrease ${t.label}`" @click="stepRule(t, -1)">−</button>
                  <span class="st-step-value">{{ thresholdForm[t.key] }}</span>
                  <button type="button" class="st-step" :aria-label="`Increase ${t.label}`" @click="stepRule(t, 1)">+</button>
                </div>
                <span class="st-unit">{{ t.unit }}</span>
              </div>
            </div>
            <div class="st-rules-foot">
              <span :class="['st-dirty', { 'st-dirty--on': thresholdsDirtyCount }]">{{ thresholdsDirtyCount ? `${thresholdsDirtyCount} unsaved change${thresholdsDirtyCount > 1 ? 's' : ''}` : 'All changes saved' }}</span>
              <div class="st-foot-actions">
                <Button variant="ghost" size="sm" :disabled="savingThresholds" @click="resetThresholds">Restore defaults</Button>
                <Button variant="primary" size="sm" :disabled="savingThresholds || !thresholdsDirtyCount" :processing="savingThresholds" @click="saveThresholds">Save rules</Button>
              </div>
            </div>
          </div>

          <!-- Optional features -->
          <div v-if="section === 'features'" class="st-features">
            <div v-for="f in features" :key="f.key" class="st-feature">
              <div class="st-feature-body">
                <div class="st-feature-title">
                  <span class="st-rule-name">{{ f.name }}</span>
                  <span class="st-path">{{ f.path }}</span>
                </div>
                <span class="st-rule-desc">{{ f.desc }}</span>
                <div v-if="f.warn" class="st-warn">{{ f.warn }}</div>
              </div>
              <button type="button" class="st-toggle" :disabled="!!savingFlag" :aria-pressed="f.on" :aria-label="`${f.name} ${f.on ? 'on' : 'off'}`" @click="f.toggle">
                <span :class="['st-toggle-state', { 'st-toggle-state--on': f.on }]">{{ f.on ? 'On' : 'Off' }}</span>
                <span :class="['st-switch', { 'st-switch--on': f.on, 'st-switch--busy': savingFlag === f.key }]"><span class="st-knob" /></span>
              </button>
            </div>
          </div>

          <!-- Mobile app -->
          <div v-if="section === 'mobile'" class="st-mobile">
            <div class="st-app">
              <div class="st-app-logo">N</div>
              <div class="st-app-text">
                <span class="st-rule-name">NAQLA LMS</span>
                <span class="st-dim">Android APK · for field supervisors</span>
              </div>
              <a href="/downloads/mobile-app" class="st-download">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12" /><path d="m7 10 5 5 5-5" /><path d="M5 21h14" /></svg>
                Download APK
              </a>
            </div>
            <p class="st-desc">Share the installer directly with supervisors. They may need to allow installs from unknown sources on their device.</p>
          </div>
        </main>
      </div>
    </div>

    <!-- Confirm turning the mobile view off -->
    <Modal :show="showFlagConfirm" @close="cancelToggle" max-width="440px">
      <template #title>Turn off Jobs (Mobile)?</template>
      <p class="confirm-text">
        This hides the menu and closes <code>/jobs/mobile</code> for everyone.
      </p>
      <p v-if="mobileOnlyUsers > 0" class="confirm-warn">
        {{ mobileOnlyUsers }} user{{ mobileOnlyUsers !== 1 ? 's' : '' }} can only use that screen. They will be
        locked out of the app until it is turned back on — including anyone mid-shift right now.
      </p>
      <template #footer>
        <Button variant="secondary" size="sm" @click="cancelToggle">Cancel</Button>
        <Button variant="danger" size="sm" :processing="savingFlag === 'ui.jobs_mobile_menu'" @click="confirmToggleOff">
          Turn it off
        </Button>
      </template>
    </Modal>

    <!-- Confirm Offset Change Modal -->
    <Modal :show="showConfirmModal" @close="closeConfirmModal" max-width="480px" :closeable="!confirmProcessing">
      <template #title>{{ confirmModalType === 'delete' ? 'Confirm Deletion' : 'Confirm Offset Change' }}</template>

      <div>
        <p v-if="confirmModalType === 'global'" class="confirm-text">
          Setting the global offset for
          <strong>{{ formatMovementType(confirmGlobalInfo.movementType) }}</strong>
          <template v-if="confirmGlobalInfo.checkpointName"> (<strong>{{ confirmGlobalInfo.checkpointName }}</strong>)</template>
          <template v-if="confirmGlobalInfo.oldValue !== null">from <strong>{{ confirmGlobalInfo.oldValue }}</strong> to</template>
          <template v-else>to</template>
          <strong>{{ confirmGlobalInfo.newValue }}</strong> minutes.
        </p>
        <p v-else-if="confirmModalType === 'event'" class="confirm-text">
          Setting the offset for
          <strong>{{ formatMovementType(confirmEventInfo.movementType) }}</strong>
          on <strong>{{ confirmEventInfo.eventName }}</strong>
          from <strong>{{ confirmEventInfo.oldValue }}</strong> to <strong>{{ confirmEventInfo.newValue }}</strong> minutes.
        </p>
        <p v-else-if="confirmModalType === 'delete'" class="confirm-text">
          Deleting the offset for
          <strong>{{ formatMovementType(confirmDeleteInfo.movementType) }}</strong>
          <template v-if="confirmDeleteInfo.checkpointName"> (<strong>{{ confirmDeleteInfo.checkpointName }}</strong>)</template>
          <template v-if="confirmDeleteInfo.eventName"> on <strong>{{ confirmDeleteInfo.eventName }}</strong></template>
          — currently <strong>{{ confirmDeleteInfo.value }}</strong> minutes.
        </p>

        <div v-if="impactExcluded" class="impact impact--neutral">
          This movement type is not offset-driven — no movement windows will be recalculated.
        </div>
        <div v-else-if="impactNoActiveEvent" class="impact impact--warn">
          No active event is selected, so no movement windows will be recalculated right now.
        </div>
        <div v-else :class="['impact', impactCount > 0 ? 'impact--warn' : 'impact--neutral']">
          This will recalculate the window for <strong>{{ impactCount }}</strong> movement(s)
          that don't yet have a job generated. Movements with a job already generated will not be affected.
        </div>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="closeConfirmModal" :disabled="confirmProcessing">Cancel</Button>
        <Button
          :variant="confirmModalType === 'delete' ? 'danger' : 'primary'"
          size="sm"
          @click="confirmSave"
          :processing="confirmProcessing"
          :disabled="confirmProcessing"
        >
          {{ confirmModalType === 'delete' ? 'Confirm & Delete' : 'Confirm & Save' }}
        </Button>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Components/AppLayout.vue';
import Button from '@/Components/Button.vue';
import RefreshButton from '@/Components/RefreshButton.vue';
import Modal from '@/Components/Modal.vue';
import Select from 'primevue/select';
import { useToast } from '@/Composables/useToast';

const props = defineProps({
  movementTypes: Array,
  events: Array,
  eventOverrides: { type: [Object, Array], default: () => ({}) },
  globalOverrides: Array,
  activeEvent: Object,
  checkpoints: Array,
  uiFlags: { type: Object, default: () => ({}) },
  mobileOnlyUsers: { type: Number, default: 0 },
  conflictThresholds: { type: Array, default: () => [] },
});

/* ------------------------------- Navigation ------------------------------- */

const section = ref('offsets');
const filter = ref('all');
const showAdd = ref(false);

function go(id) {
  section.value = id;
  showAdd.value = false;
}

const overrideTotal = computed(() => Object.values(props.eventOverrides).reduce((n, list) => n + list.length, 0));

const navGroups = computed(() => [
  { label: 'Scheduling', items: [
    { id: 'offsets', label: 'Movement offsets', sub: 'Global checkpoint timing', count: String(props.globalOverrides.length) },
    { id: 'overrides', label: 'Event overrides', sub: 'Per-event exceptions', count: String(overrideTotal.value) },
    { id: 'rules', label: 'Conflict rules', sub: 'Clash & duty limits', count: thresholdsDirtyCount.value ? `${thresholdsDirtyCount.value} unsaved` : '', alert: thresholdsDirtyCount.value > 0 },
  ] },
  { label: 'Platform', items: [
    { id: 'features', label: 'Optional features', sub: 'Turn app areas on/off', count: `${features.value.filter((f) => f.on).length}/${features.value.length}` },
    { id: 'mobile', label: 'Mobile app', sub: 'Android APK', count: '' },
  ] },
]);

const VIEWS = {
  offsets: { title: 'Movement offsets', desc: "When each checkpoint happens relative to its reference time. Offsets on a movement's first checkpoint drive that movement's whole window.", canAdd: true },
  overrides: { title: 'Event overrides', desc: 'Replace a global offset for one event only. Overrides win over every global setting.', canAdd: true },
  rules: { title: 'Conflict rules', desc: 'Limits the Conflicts tab and crew clash checks judge against. Applies to every event.' },
  features: { title: 'Optional features', desc: 'Turn parts of the app on or off for everyone.' },
  mobile: { title: 'Mobile app', desc: 'Android app for field supervisors.' },
};
const view = computed(() => VIEWS[section.value]);

/* ------------------------------ Offsets display ---------------------------- */

const TYPE_META = {
  arrival: { color: '#2F5BD3', soft: '#DCE5FB', ref: 'Flight lands' },
  departure: { color: '#C2410C', soft: '#FBE1D3', ref: 'Flight departs' },
  match: { color: '#15803D', soft: '#D5EFDD', ref: 'Kick-off' },
  transfer: { color: '#7C3AED', soft: '#E9E0FB', ref: 'Transfer time' },
  training: { color: '#0E7490', soft: '#D3EEF3', ref: 'Training start' },
  daily_ops: { color: '#6B655D', soft: '#E9E6E1', ref: 'Reference time' },
};
const meta = (type) => TYPE_META[type] ?? TYPE_META.daily_ops;

const RANGE = 300;
const pct = (m) => ((Math.max(-RANGE, Math.min(RANGE, m)) + RANGE) / (2 * RANGE)) * 100;

function hm(m) {
  const a = Math.abs(m);
  return `${m < 0 ? '−' : m > 0 ? '+' : ''}${Math.floor(a / 60)}:${String(a % 60).padStart(2, '0')}`;
}

function reads(m) {
  if (m === '' || m === null || m === undefined || Number.isNaN(Number(m))) return '—';
  const n = Number(m);
  if (n === 0) return 'At reference';
  return `${Math.floor(Math.abs(n) / 60)}:${String(Math.abs(n) % 60).padStart(2, '0')} ${n < 0 ? 'before' : 'after'}`;
}

const ticks = [-240, -120, 0, 120, 240].map((m) => ({ m, left: `${pct(m)}%`, label: m === 0 ? '0' : `${m > 0 ? '+' : '−'}${Math.abs(m) / 60}h` }));

const typeFilters = computed(() => [{ value: 'all', label: 'All' }, ...props.movementTypes.map((t) => ({ value: t, label: formatMovementType(t) }))]);

const typeOf = (setting) => setting.key.replace('movement_offset.', '');

const groups = computed(() => {
  const types = filter.value === 'all' ? props.movementTypes : [filter.value];
  return types.map((type) => {
    const rows = props.globalOverrides
      .filter((s) => typeOf(s) === type)
      .sort((a, b) => Number(a.value) - Number(b.value))
      .map((s) => {
        const v = Number(s.value);
        const p = pct(v);
        return {
          id: s.id,
          setting: s,
          name: s.checkpoint_name || 'Movement-type default',
          desc: s.description || (s.checkpoint_id ? '' : 'Used when no checkpoint offset applies'),
          hm: hm(v),
          dir: v === 0 ? 'at reference' : v < 0 ? 'before' : 'after',
          dot: `${p}%`,
          barLeft: `${Math.min(p, 50)}%`,
          barWidth: `${Math.abs(p - 50)}%`,
        };
      });
    return { type, label: formatMovementType(type), rows, ...meta(type) };
  }).filter((g) => g.rows.length);
});

const overrideGroups = computed(() => Object.entries(props.eventOverrides).map(([eventId, list]) => ({
  eventId,
  name: getEventName(eventId),
  rows: list.map((s) => {
    const type = typeOf(s);
    const global = props.globalOverrides.find((g) => g.key === s.key && (g.checkpoint_id ?? null) === (s.checkpoint_id ?? null));
    return {
      id: s.id,
      setting: s,
      typeLabel: formatMovementType(type),
      name: s.checkpoint_name || 'Movement-type default',
      reads: reads(Number(s.value)),
      globalReads: global ? reads(Number(global.value)) : '—',
      ...meta(type),
    };
  }),
})).filter((g) => g.rows.length));

/* ------------------------------ Conflict rules ----------------------------- */

const thresholdForm = ref({});
const thresholdErrors = ref({});
const savingThresholds = ref(false);

watch(() => props.conflictThresholds, (list) => {
  thresholdForm.value = Object.fromEntries(list.map((t) => [t.key, t.value]));
}, { immediate: true });

const thresholdsDirtyCount = computed(() => props.conflictThresholds.filter((t) => Number(thresholdForm.value[t.key]) !== t.value).length);

function stepRule(t, dir) {
  const step = t.unit === 'hours' ? 1 : 5;
  const next = Number(thresholdForm.value[t.key]) + dir * step;
  thresholdForm.value[t.key] = Math.max(t.min, Math.min(t.max, next));
}

function resetThresholds() {
  thresholdForm.value = Object.fromEntries(props.conflictThresholds.map((t) => [t.key, t.default]));
}

function saveThresholds() {
  savingThresholds.value = true;
  thresholdErrors.value = {};
  router.post('/setups/settings/thresholds', thresholdForm.value, {
    preserveScroll: true,
    onError: (errors) => { thresholdErrors.value = errors; },
    onFinish: () => { savingThresholds.value = false; },
  });
}

/* ----------------------------- Optional features --------------------------- */

const savingFlag = ref(null); // key of the flag being saved
const showFlagConfirm = ref(false);
const jobsMobileMenu = computed(() => props.uiFlags?.jobsMobileMenu !== false);
const utilitiesEnabled = computed(() => props.uiFlags?.utilities !== false);

const features = computed(() => [
  {
    key: 'ui.jobs_mobile_menu', name: 'Jobs (Mobile)', path: '/jobs/mobile', on: jobsMobileMenu.value,
    desc: 'The touch-friendly jobs view for staff working on their phones. Turning it off hides the menu and closes the page — anyone who opens it gets a 403.',
    warn: props.mobileOnlyUsers > 0
      ? `${props.mobileOnlyUsers} user${props.mobileOnlyUsers !== 1 ? 's have' : ' has'} no other screen — turning this off locks them out of the app entirely.`
      : '',
    toggle: () => requestToggle(!jobsMobileMenu.value),
  },
  {
    key: 'ui.utilities', name: 'Utilities', path: '/utilities', on: utilitiesEnabled.value,
    desc: 'The Team and Match sheet converters, including AI reading of PDFs. Turning it off hides the menu and the converter links on Event Teams and Matches, and closes the page. Importing teams and matches is not affected.',
    warn: '',
    toggle: () => setFlag('ui.utilities', !utilitiesEnabled.value),
  },
]);

function requestToggle(enabled) {
  enabled ? setFlag('ui.jobs_mobile_menu', true) : (showFlagConfirm.value = true);
}

function cancelToggle() {
  showFlagConfirm.value = false;
}

function confirmToggleOff() {
  setFlag('ui.jobs_mobile_menu', false);
}

function setFlag(key, enabled) {
  savingFlag.value = key;
  router.post('/setups/settings/ui-flag', { key, enabled }, {
    preserveScroll: true,
    onFinish: () => { savingFlag.value = null; showFlagConfirm.value = false; },
  });
}

/* ------------------------------ Flash toasts ------------------------------- */

const page = usePage();
const { success: showSuccessToast, error: showErrorToast } = useToast();

// Not deep: a partial reload keeps the old flash object, and a deep watcher would toast it again.
watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) showSuccessToast(flash.success);
    if (flash?.error) showErrorToast(flash.error);
  }
);

/* --------------------------- Offset forms and saves ------------------------ */

// Global override add-form state
const globalForm = ref({ movement_type: '', checkpoint_id: '', value: '', description: '' });

// Global override inline-edit state (existing rows)
const editingGlobalId = ref(null);
const globalEditForm = ref({ movement_type: '', checkpoint_id: '', value: '', description: '' });

// Populated by whichever global-override save flow (add or edit) opens the
// confirmation modal.
const confirmGlobalInfo = ref({ movementType: '', checkpointName: null, oldValue: null, newValue: null });

// Event override state
const selectedEventId = ref('');
const eventForm = ref({ movement_type: '', value: '', description: '', checkpoint_id: '' });

// Event override inline-edit state (existing rows)
const editingEventOverrideId = ref(null);
const eventEditForm = ref({ movement_type: '', checkpoint_id: '', value: '', description: '' });

// Populated by whichever event-override save flow (add or edit) opens the
// confirmation modal, so the modal doesn't need to know which one triggered it.
const confirmEventInfo = ref({ eventName: '', movementType: '', oldValue: null, newValue: null });

// Confirmation modal state (global/event offset changes, and deletions)
const showConfirmModal = ref(false);
const confirmModalType = ref(null); // 'global' | 'event' | 'delete'
const confirmProcessing = ref(false);
const impactCount = ref(0);
const impactNoActiveEvent = ref(false);
const impactExcluded = ref(false);
const pendingSaveFn = ref(null);

// Populated by confirmDeleteOverride() so the modal can describe what's being deleted.
const confirmDeleteInfo = ref({ movementType: '', checkpointName: null, eventName: null, value: null });

// "Save" button processing state — covers the preview-impact fetch
// that runs before the confirmation modal opens.
const addingGlobalOverride = ref(false);
const addingEventOverride = ref(false);

// {label, value} pairs for the PrimeVue Select movement-type dropdowns
const movementTypeOptions = computed(() =>
  props.movementTypes.map((type) => ({ label: formatMovementType(type), value: type }))
);

function closeAdd() {
  showAdd.value = false;
}

const derivedOldEventValue = computed(() => {
  if (!selectedEventId.value || !eventForm.value.movement_type) return null;
  const key = `movement_offset.${eventForm.value.movement_type}`;
  const existingEventOverride = (props.eventOverrides[selectedEventId.value] || []).find((s) => s.key === key && !s.checkpoint_id);
  if (existingEventOverride) return existingEventOverride.value;
  const globalDefault = props.globalOverrides.find((s) => s.key === key && !s.checkpoint_id);
  return globalDefault ? globalDefault.value : null;
});

// Add a new global override (plain movement-type default, or checkpoint-
// specific if a checkpoint is picked).
async function saveGlobalOverride() {
  const checkpointId = globalForm.value.checkpoint_id || null;

  addingGlobalOverride.value = true;
  try {
    const { data } = await axios.post('/setups/settings/preview-impact', {
      movement_type: globalForm.value.movement_type,
      scope: 'global',
    });
    impactCount.value = data.count;
    impactNoActiveEvent.value = !!data.no_active_event;
    impactExcluded.value = !!data.excluded;
    confirmGlobalInfo.value = {
      movementType: globalForm.value.movement_type,
      checkpointName: checkpointId ? getCheckpointName(checkpointId) : null,
      oldValue: null,
      newValue: globalForm.value.value,
    };
    confirmModalType.value = 'global';
    pendingSaveFn.value = doSaveGlobalOverride;
    showConfirmModal.value = true;
  } finally {
    addingGlobalOverride.value = false;
  }
}

function doSaveGlobalOverride() {
  confirmProcessing.value = true;
  router.post('/setups/settings/global', {
    movement_type: globalForm.value.movement_type,
    value: globalForm.value.value,
    description: globalForm.value.description,
    checkpoint_id: globalForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      globalForm.value = { movement_type: '', checkpoint_id: '', value: '', description: '' };
      showAdd.value = false;
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Edit an existing global override's offset (inline, row-level). Saved by row
// ID so the row is updated in place.
function editGlobalRow(setting) {
  editingGlobalId.value = setting.id;
  globalEditForm.value = {
    movement_type: getMovementTypeFromKey(setting.key),
    checkpoint_id: setting.checkpoint_id || '',
    value: setting.value,
    description: setting.description || '',
  };
}

function cancelGlobalEdit() {
  editingGlobalId.value = null;
  globalEditForm.value = { movement_type: '', checkpoint_id: '', value: '', description: '' };
}

async function saveGlobalOverrideEdit(setting) {
  const checkpointId = globalEditForm.value.checkpoint_id || null;

  const { data } = await axios.post('/setups/settings/preview-impact', {
    movement_type: globalEditForm.value.movement_type,
    scope: 'global',
  });
  impactCount.value = data.count;
  impactNoActiveEvent.value = !!data.no_active_event;
  impactExcluded.value = !!data.excluded;
  confirmGlobalInfo.value = {
    movementType: globalEditForm.value.movement_type,
    checkpointName: checkpointId ? getCheckpointName(checkpointId) : null,
    oldValue: setting.value,
    newValue: globalEditForm.value.value,
  };
  confirmModalType.value = 'global';
  pendingSaveFn.value = () => doSaveGlobalOverrideEdit(setting);
  showConfirmModal.value = true;
}

function doSaveGlobalOverrideEdit(setting) {
  confirmProcessing.value = true;
  router.post('/setups/settings/global', {
    id: setting.id,
    movement_type: globalEditForm.value.movement_type,
    value: globalEditForm.value.value,
    description: globalEditForm.value.description,
    checkpoint_id: globalEditForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      cancelGlobalEdit();
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Save event override (new). A checkpoint-specific override on a
// movement's first checkpoint can drive that movement's window_start, so
// it goes through the same impact preview/confirmation as a plain override.
async function saveEventOverride() {
  addingEventOverride.value = true;
  try {
    const { data } = await axios.post('/setups/settings/preview-impact', {
      movement_type: eventForm.value.movement_type,
      scope: 'event',
      event_id: selectedEventId.value,
    });
    impactCount.value = data.count;
    impactNoActiveEvent.value = !!data.no_active_event;
    impactExcluded.value = !!data.excluded;
    confirmEventInfo.value = {
      eventName: getEventName(selectedEventId.value),
      movementType: eventForm.value.movement_type,
      oldValue: derivedOldEventValue.value,
      newValue: eventForm.value.value,
    };
    confirmModalType.value = 'event';
    pendingSaveFn.value = doSaveEventOverride;
    showConfirmModal.value = true;
  } finally {
    addingEventOverride.value = false;
  }
}

function doSaveEventOverride() {
  confirmProcessing.value = true;
  router.post('/setups/settings/event', {
    event_id: selectedEventId.value,
    movement_type: eventForm.value.movement_type,
    value: eventForm.value.value,
    description: eventForm.value.description,
    checkpoint_id: eventForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      eventForm.value = { movement_type: '', value: '', description: '', checkpoint_id: '' };
      showAdd.value = false;
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

// Edit an existing event override's offset (inline, row-level), saved by row ID.
function editEventOverrideRow(eventId, setting) {
  editingEventOverrideId.value = setting.id;
  eventEditForm.value = {
    movement_type: getMovementTypeFromKey(setting.key),
    checkpoint_id: setting.checkpoint_id || '',
    value: setting.value,
    description: setting.description || '',
  };
}

function cancelEventOverrideEdit() {
  editingEventOverrideId.value = null;
  eventEditForm.value = { movement_type: '', checkpoint_id: '', value: '', description: '' };
}

async function saveEventOverrideEdit(eventId, setting) {
  const { data } = await axios.post('/setups/settings/preview-impact', {
    movement_type: eventEditForm.value.movement_type,
    scope: 'event',
    event_id: eventId,
  });
  impactCount.value = data.count;
  impactNoActiveEvent.value = !!data.no_active_event;
  impactExcluded.value = !!data.excluded;
  confirmEventInfo.value = {
    eventName: getEventName(eventId),
    movementType: eventEditForm.value.movement_type,
    oldValue: setting.value,
    newValue: eventEditForm.value.value,
  };
  confirmModalType.value = 'event';
  pendingSaveFn.value = () => doSaveEventOverrideEdit(eventId, setting);
  showConfirmModal.value = true;
}

function doSaveEventOverrideEdit(eventId, setting) {
  confirmProcessing.value = true;
  router.post('/setups/settings/event', {
    id: setting.id,
    event_id: eventId,
    movement_type: eventEditForm.value.movement_type,
    value: eventEditForm.value.value,
    description: eventEditForm.value.description,
    checkpoint_id: eventEditForm.value.checkpoint_id || null,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      cancelEventOverrideEdit();
      closeConfirmModal();
    },
    onFinish: () => { confirmProcessing.value = false; },
  });
}

function confirmSave() {
  if (pendingSaveFn.value) pendingSaveFn.value();
}

function closeConfirmModal() {
  showConfirmModal.value = false;
  confirmModalType.value = null;
  pendingSaveFn.value = null;
  confirmProcessing.value = false;
}

// Delete override — shows the same impact preview/confirmation as a save,
// since deleting also triggers a movement-window recompute.
async function confirmDeleteOverride(setting, scope, eventId = null) {
  const movementType = getMovementTypeFromKey(setting.key);

  const { data } = await axios.post('/setups/settings/preview-impact', {
    movement_type: movementType,
    scope,
    event_id: eventId,
  });
  impactCount.value = data.count;
  impactNoActiveEvent.value = !!data.no_active_event;
  impactExcluded.value = !!data.excluded;
  confirmDeleteInfo.value = {
    movementType,
    checkpointName: setting.checkpoint_name || null,
    eventName: eventId ? getEventName(eventId) : null,
    value: setting.value,
  };
  confirmModalType.value = 'delete';
  pendingSaveFn.value = () => doDeleteOverride(setting.id);
  showConfirmModal.value = true;
}

function doDeleteOverride(id) {
  confirmProcessing.value = true;
  router.delete(`/setups/settings/${id}`, {
    preserveScroll: true,
    onSuccess: () => closeConfirmModal(),
    onFinish: () => { confirmProcessing.value = false; },
  });
}

/* --------------------------------- Helpers --------------------------------- */

function formatMovementType(type) {
  return type.split('_').map((word) => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
}

function getMovementTypeFromKey(key) {
  return key.replace('movement_offset.', '');
}

function getEventName(eventId) {
  const event = props.events.find((e) => e.id == eventId);
  return event ? event.name : `Event #${eventId}`;
}

function getCheckpointName(checkpointId) {
  const checkpoint = props.checkpoints.find((c) => c.id == checkpointId);
  return checkpoint ? checkpoint.name : `Checkpoint #${checkpointId}`;
}
</script>

<style scoped>
.st-page { max-width: 1440px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }

.st-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.st-head-title { display: flex; flex-direction: column; gap: 4px; }
.st-kicker { font-size: 12px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--accent); }
.st-h1 { margin: 0; font-size: 26px; font-weight: 600; letter-spacing: -0.01em; color: var(--ink); }
.st-head-right { display: flex; align-items: center; gap: 12px; }
.st-note { font-size: 13px; color: var(--ink3); }

.st-layout { display: grid; grid-template-columns: minmax(220px, 280px) minmax(0, 1fr); gap: 20px; align-items: start; }

/* Section nav */
.st-nav {
  background: var(--surface); border: 1px solid var(--border); border-radius: 14px; padding: 10px;
  display: flex; flex-direction: column; gap: 2px; position: sticky; top: 20px;
}
.st-nav-group { display: flex; flex-direction: column; gap: 2px; padding-bottom: 6px; }
.st-nav-label { font-size: 11px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); padding: 12px 12px 6px; }
.st-nav-item {
  display: grid; grid-template-columns: 4px minmax(0, 1fr) auto; gap: 12px; align-items: center;
  padding: 10px 12px 10px 0; border: 0; border-radius: 10px; background: transparent; cursor: pointer; text-align: left; color: var(--ink);
}
.st-nav-item:hover { background: var(--panel); }
.st-nav-item--on, .st-nav-item--on:hover { background: var(--accent-soft); }
.st-nav-bar { width: 4px; height: 28px; border-radius: 0 3px 3px 0; background: transparent; }
.st-nav-item--on .st-nav-bar { background: var(--accent); }
.st-nav-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.st-nav-name { font-size: 14px; font-weight: 500; }
.st-nav-item--on .st-nav-name { font-weight: 600; }
.st-nav-sub { font-size: 12px; color: var(--ink3); line-height: 1.35; }
.st-nav-count { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); background: var(--panel); padding: 2px 8px; border-radius: 999px; }
.st-nav-count--alert { color: var(--accent); background: var(--accent-soft); }

/* Content card */
.st-main {
  background: var(--surface); border: 1px solid var(--border); border-radius: 14px; min-width: 0;
  display: flex; flex-direction: column; overflow: hidden; container-type: inline-size;
}
.st-main-head { padding: 24px 28px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; }
.st-main-title { display: flex; flex-direction: column; gap: 6px; max-width: 640px; }
.st-h2 { margin: 0; font-size: 20px; font-weight: 600; color: var(--ink); }
.st-desc { margin: 0; font-size: 14px; color: var(--ink3); line-height: 1.5; text-wrap: pretty; }
.st-body { padding: 20px 28px; display: flex; flex-direction: column; gap: 20px; }
.st-dim { font-size: 12px; color: var(--ink3); line-height: 1.4; }
.st-empty { padding: 32px; text-align: center; border: 1px dashed var(--border); border-radius: 12px; font-size: 14px; color: var(--ink3); }
.st-error { font-size: 12.5px; color: var(--danger, #b91c1c); }

/* Add panel */
.st-add { padding: 20px 28px; background: var(--panel); border-bottom: 1px solid var(--border); display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-end; }
.st-field { display: flex; flex-direction: column; gap: 6px; font-size: 12px; font-weight: 600; color: var(--ink3); min-width: 0; }
.st-field--grow { flex: 1 1 160px; }
.st-field--wide { flex: 2 1 240px; }
.st-field--narrow { flex: 0 1 130px; }
.st-input {
  height: 40px; border: 1px solid var(--border); border-radius: 8px; padding: 0 12px; font-size: 14px;
  background: var(--surface); color: var(--ink); box-sizing: border-box; width: 100%;
}
.st-input--mono { font-family: var(--font-mono, monospace); }
.st-input--edit { width: 84px; height: 32px; padding: 0 8px; text-align: right; font-family: var(--font-mono, monospace); font-size: 13px; border-color: var(--accent); outline: none; }
.st-reads { height: 40px; display: flex; align-items: center; font-size: 14px; font-weight: 500; color: var(--ink); }
.st-add-actions { display: flex; gap: 8px; }

/* Offsets */
.st-toolbar { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; justify-content: space-between; }
.st-seg { display: flex; gap: 4px; background: var(--panel); padding: 4px; border-radius: 10px; flex-wrap: wrap; }
.st-seg-btn { border: 0; cursor: pointer; padding: 7px 14px; border-radius: 7px; font-size: 13px; font-weight: 500; background: transparent; color: var(--ink3); }
.st-seg-btn--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); }
.st-order { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; color: var(--ink3); }
.st-order-title { font-weight: 600; color: var(--ink); margin-right: 2px; }
.st-chip { padding: 3px 8px; border-radius: 6px; background: var(--panel); }

.st-group { border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.st-row {
  display: grid; align-items: center; gap: 16px; padding: 16px 18px;
  grid-template-columns: minmax(180px, 1.1fr) minmax(0, 2fr) 120px 84px;
}
.st-row--head { padding: 14px 18px 10px; background: var(--panel); border-bottom: 1px solid var(--border); align-items: end; }
.st-item { border-top: 1px solid var(--border); }
.st-row--head + .st-item { border-top: 0; }
.st-item:hover { background: color-mix(in srgb, var(--panel) 50%, transparent); }
.st-group-name { display: flex; align-items: center; gap: 10px; }
.st-swatch { width: 10px; height: 10px; border-radius: 3px; }
.st-group-label { font-size: 15px; font-weight: 600; color: var(--ink); }
.st-colhead { font-size: 11px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--ink3); text-align: right; }
.st-name { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.st-name-main { font-size: 14px; font-weight: 500; line-height: 1.35; color: var(--ink); }

.st-tl { position: relative; height: 22px; }
.st-tl--head { height: 30px; }
.st-tick { position: absolute; bottom: 0; transform: translateX(-50%); font-family: var(--font-mono, monospace); font-size: 11px; color: var(--ink3); white-space: nowrap; }
.st-tick--zero { color: var(--ink); font-weight: 600; }
.st-ref { position: absolute; top: 0; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 600; white-space: nowrap; }
.st-track { position: absolute; left: 0; right: 0; top: 10px; height: 2px; background: var(--border); border-radius: 2px; }
.st-zero { position: absolute; left: 50%; top: 2px; bottom: 2px; width: 1px; background: var(--ink3); opacity: 0.5; }
.st-bar { position: absolute; top: 9px; height: 4px; border-radius: 2px; }
.st-dot { position: absolute; top: 4px; width: 14px; height: 14px; border-radius: 50%; transform: translateX(-50%); box-shadow: 0 0 0 3px var(--surface); }

.st-offset { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
.st-hm { font-family: var(--font-mono, monospace); font-size: 15px; font-weight: 600; color: var(--ink); }
.st-actions { display: flex; gap: 2px; justify-content: flex-end; }
.st-icon { border: 0; background: transparent; width: 32px; height: 32px; border-radius: 8px; cursor: pointer; color: var(--ink3); display: grid; place-items: center; font-size: 15px; }
.st-icon:hover { background: var(--panel); color: var(--ink); }
.st-icon--ok { color: var(--ok, #15803d); }
.st-icon--danger:hover { background: var(--danger-soft, #fdecea); color: var(--danger, #b42318); }

/* Event overrides */
.st-ovgroup { display: flex; flex-direction: column; gap: 10px; }
.st-ovhead { display: flex; align-items: baseline; gap: 10px; }
.st-ovevent { font-size: 16px; font-weight: 600; color: var(--ink); }
.st-ovrow { display: grid; grid-template-columns: auto minmax(0, 1fr) auto auto; gap: 16px; align-items: center; padding: 14px 18px; border: 1px solid var(--border); border-radius: 12px; }
.st-type { font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; }
.st-change { display: flex; align-items: center; gap: 10px; font-family: var(--font-mono, monospace); font-size: 14px; justify-content: flex-end; }
.st-was { color: var(--ink3); text-decoration: line-through; }

/* Conflict rules */
.st-rules { display: flex; flex-direction: column; }
.st-rule, .st-feature { display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 24px; align-items: center; padding: 20px 28px; border-bottom: 1px solid var(--border); }
.st-feature { align-items: start; padding: 22px 28px; }
.st-rule-body, .st-feature-body { display: flex; flex-direction: column; gap: 4px; max-width: 640px; }
.st-rule-title, .st-feature-title { display: flex; align-items: center; gap: 10px; }
.st-rule-name { font-size: 15px; font-weight: 600; color: var(--ink); }
.st-rule-desc { font-size: 13px; color: var(--ink3); line-height: 1.55; text-wrap: pretty; }
.st-custom { font-size: 11px; font-weight: 600; color: var(--accent); background: var(--accent-soft); padding: 2px 8px; border-radius: 999px; }
.st-stepper-wrap { display: flex; align-items: center; gap: 10px; }
.st-stepper { display: flex; align-items: center; border: 1px solid var(--border); border-radius: 9px; overflow: hidden; }
.st-step { border: 0; background: var(--panel); width: 36px; height: 40px; font-size: 18px; cursor: pointer; color: var(--ink3); }
.st-step:hover { background: var(--border); }
.st-step-value { min-width: 52px; text-align: center; font-family: var(--font-mono, monospace); font-size: 16px; font-weight: 600; color: var(--ink); }
.st-unit { font-size: 13px; color: var(--ink3); width: 54px; }
.st-rules-foot { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 28px; background: var(--panel); flex-wrap: wrap; }
.st-dirty { font-size: 13px; font-weight: 500; color: var(--ink3); }
.st-dirty--on { color: var(--accent); }
.st-foot-actions { display: flex; gap: 8px; }

/* Features */
.st-features { display: flex; flex-direction: column; }
.st-path { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); background: var(--panel); padding: 2px 7px; border-radius: 5px; }
.st-warn { margin-top: 6px; padding: 10px 12px; background: var(--warn-soft, #fff6e5); border: 1px solid var(--warn, #f5dfb3); border-radius: 8px; font-size: 13px; color: var(--warn, #7a4a00); line-height: 1.45; }
.st-toggle { display: flex; align-items: center; gap: 10px; border: 0; background: transparent; cursor: pointer; padding: 0; }
.st-toggle:disabled { cursor: progress; }
.st-toggle-state { font-size: 13px; font-weight: 500; color: var(--ink3); width: 24px; text-align: right; }
.st-toggle-state--on { color: var(--accent); }
.st-switch { width: 44px; height: 24px; border-radius: 999px; background: var(--border); position: relative; transition: background 0.2s; }
.st-switch--on { background: var(--accent); }
.st-switch--busy { opacity: 0.6; }
.st-knob { position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25); transition: left 0.2s; }
.st-switch--on .st-knob { left: 23px; }

/* Mobile app */
.st-mobile { padding: 28px; display: flex; flex-direction: column; gap: 20px; }
.st-app { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 20px; align-items: center; padding: 20px; border: 1px solid var(--border); border-radius: 12px; }
.st-app-logo { width: 52px; height: 52px; border-radius: 12px; background: var(--accent); color: #fff; display: grid; place-items: center; font-weight: 700; font-size: 18px; }
.st-app-text { display: flex; flex-direction: column; gap: 4px; }
.st-download { display: inline-flex; align-items: center; gap: 8px; padding: 11px 18px; border-radius: 9px; background: var(--ink); color: var(--surface); font-size: 14px; font-weight: 500; text-decoration: none; }
.st-download:hover { opacity: 0.88; }

/* Modals */
.confirm-text { font-size: 14px; color: var(--ink); line-height: 1.5; margin: 0; }
.confirm-text code { font-size: 12px; background: var(--panel); padding: 1px 4px; border-radius: 3px; }
.confirm-warn { font-size: 12.5px; line-height: 1.55; color: var(--warn, #b45309); margin: 10px 0 0; }
.impact { margin-top: 12px; padding: 10px 12px; border-radius: 4px; font-size: 13px; }
.impact--neutral { background: var(--panel); border-left: 3px solid var(--ink3); color: var(--ink2); }
.impact--warn { background: var(--warn-soft, #fef3c7); border-left: 3px solid var(--warn, #f59e0b); color: var(--warn, #92400e); }

/* Narrow content: drop the timeline and stack the nav */
@container (max-width: 820px) {
  .st-row { grid-template-columns: minmax(0, 1fr) 110px 76px; }
  .st-tl { display: none; }
  .st-ovrow { grid-template-columns: auto minmax(0, 1fr) auto; }
  .st-ovrow .st-actions { grid-column: 3; }
}

@media (max-width: 900px) {
  .st-layout { grid-template-columns: 1fr; }
  .st-nav { position: static; flex-direction: row; flex-wrap: wrap; }
  .st-nav-group { flex-direction: row; flex-wrap: wrap; align-items: center; padding-bottom: 0; }
  .st-nav-label { display: none; }
  .st-nav-item { padding: 8px 12px; }
  .st-nav-bar, .st-nav-sub { display: none; }
  .st-rule, .st-feature, .st-app { grid-template-columns: 1fr; }
}
</style>
