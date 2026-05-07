<template>
  <app-layout>
    <div class="page-header">
      <inertia-link href="/jobs" class="back-link">
        <svg-icon name="chevron" style="transform:rotate(180deg)" :size="16" /> Jobs
      </inertia-link>
    </div>

    <div style="display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 16px; gap: 12px; flex-wrap: wrap;">
      <div>
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
          <div class="team-badge-lg">{{ job.code }}</div>
          <h1 style="font-size: 24px; font-weight: 700; color: var(--ink); letter-spacing: -0.4px; margin: 0;">{{ job.team }}</h1>
          <status-pill :tone="statusTone(job.status)" :dot="true" size="sm">
            {{ job.delay ? `+${job.delay}m delayed` : statusLabel(job.status) }}
          </status-pill>
        </div>
        <div style="font-size: 13px; color: var(--ink3);">{{ job.from }} → {{ job.to }} · {{ job.vehicle }} · {{ job.pax }} passengers</div>
      </div>
      <div style="display: flex; gap: 8px;">
        <Button variant="secondary" size="sm">Contact liaison</Button>
        <Button variant="secondary" size="sm">
          <template #icon><svg-icon name="bell" :size="14" /></template>
          Notify team
        </Button>
        <Button variant="primary" size="sm" @click="openOverride">Override checkpoint</Button>
      </div>
    </div>

    <div class="detail-grid">
      <!-- Checkpoint Timeline -->
      <div class="section-card">
        <div class="section-header">
          <div>
            <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">{{ completedCount }} of {{ checkpoints.length }} complete</div>
            <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">Checkpoint timeline</div>
          </div>
          <Button variant="ghost" size="sm" @click="openOverride">Mark checkpoint</Button>
        </div>
        <div style="padding: 16px 20px;">
          <div style="display: flex; flex-direction: column;">
            <div v-for="(cp, i) in checkpoints" :key="cp.id" style="display: flex; align-items: stretch; gap: 12px;">
              <!-- Icon column -->
              <div style="display: flex; flex-direction: column; align-items: center; width: 28px; flex-shrink: 0;">
                <!-- Done: solid green with checkmark -->
                <div v-if="cp.status === 'done'" style="width: 28px; height: 28px; border-radius: 999px; background: var(--ok); color: #fff; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <svg-icon name="check" :size="12" />
                </div>
                <!-- Active: ring with center dot -->
                <div v-else-if="cp.status === 'active'" style="width: 28px; height: 28px; border-radius: 999px; background: var(--surface); border: 2.5px solid var(--accent); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                  <div style="width: 8px; height: 8px; border-radius: 999px; background: var(--accent);"></div>
                </div>
                <!-- Pending: grey ring -->
                <div v-else style="width: 28px; height: 28px; border-radius: 999px; background: transparent; border: 2px solid var(--border); flex-shrink: 0;"></div>
                <!-- Connector line -->
                <div v-if="i < checkpoints.length - 1" :style="{
                  width: '2px', flex: 1, minHeight: '16px',
                  background: cp.status === 'done' ? 'var(--ok)' : 'var(--border)',
                }" />
              </div>

              <!-- Content -->
              <div style="flex: 1; padding: 4px 0 14px;">
                <div style="font-size: 13px; font-weight: 600; color: var(--ink); line-height: 20px;">{{ cp.label }}</div>
                <div v-if="cp.status === 'active'" style="font-size: 12px; color: var(--accent); margin-top: 2px;">Awaiting supervisor confirmation</div>
              </div>

              <!-- Time display -->
              <div style="padding: 4px 0; text-align: right; font-family: var(--mono); font-size: 12.5px; white-space: nowrap; flex-shrink: 0; line-height: 20px;">
                <template v-if="cp.status === 'done' && cp.actual">
                  <span style="text-decoration: line-through; color: var(--ink4); margin-right: 5px;">{{ cp.time }}</span>
                  <span style="color: #f59e0b; font-weight: 600;">{{ cp.actual }}</span>
                </template>
                <template v-else>
                  <span style="color: var(--ink3);">{{ cp.time }}</span>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right column -->
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <!-- Planned vs Actual -->
        <div class="section-card">
          <div class="section-header">
            <div>
              <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">Timing</div>
              <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">Planned vs. actual</div>
            </div>
          </div>
          <div style="padding: 16px 20px;">
            <div class="timing-grid">
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Departure</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); font-family: var(--mono); letter-spacing: -0.4px;">{{ job.dep }}</div>
                <div style="font-size: 11px; color: var(--ok); margin-top: 4px; font-weight: 600;">On time</div>
              </div>
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Arrival</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); font-family: var(--mono); letter-spacing: -0.4px;">{{ job.arr }}</div>
                <div style="font-size: 11px; color: var(--accent); margin-top: 4px; font-weight: 600;">ETA {{ job.arr }}</div>
              </div>
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Passengers</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); font-family: var(--mono); letter-spacing: -0.4px;">{{ job.pax }}</div>
                <div style="font-size: 11px; color: var(--ok); margin-top: 4px; font-weight: 600;">All boarded</div>
              </div>
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Vehicle</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); letter-spacing: -0.4px;">{{ job.vehicle }}</div>
                <div style="font-size: 11px; color: var(--ink3); margin-top: 4px; font-weight: 600;">Assigned</div>
              </div>
            </div>
          </div>
        </div>

        <!-- Crew -->
        <div class="section-card">
          <div class="section-header">
            <div>
              <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">Team</div>
              <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">Crew on this job</div>
            </div>
          </div>
          <div style="padding: 16px 20px;">
            <div v-if="crewMembers.length === 0" style="text-align: center; padding: 20px; color: var(--ink3); font-size: 13px;">
              No crew assigned
            </div>
            <div v-for="crew in crewMembers" :key="crew.name" style="display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border);">
              <div style="width: 32px; height: 32px; border-radius: 999px; background: var(--accent-soft); color: var(--accent-fg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0;">
                {{ crew.initials }}
              </div>
              <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600; color: var(--ink);">{{ crew.name }}</div>
                <div style="font-size: 11px; color: var(--ink3);">{{ crew.role }}</div>
              </div>
              <status-pill :tone="crew.onShift ? 'ok' : 'neutral'" :dot="true" size="sm">
                {{ crew.onShift ? 'On shift' : 'Off' }}
              </status-pill>
            </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="section-card">
          <div class="section-header">
            <div style="font-size: 14px; font-weight: 700; color: var(--ink);">Quick Actions</div>
          </div>
          <div style="padding: 16px 20px; display: flex; flex-direction: column; gap: 6px;">
            <button class="action-btn">
              <svg-icon name="phone" :size="16" /> Contact driver
            </button>
            <button class="action-btn">
              <svg-icon name="bell" :size="16" /> Notify team liaison
            </button>
            <button class="action-btn action-btn--warn">
              <svg-icon name="warn" :size="16" /> Flag delay
            </button>
            <button class="action-btn">
              <svg-icon name="refresh" :size="16" /> Reassign vehicle
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Override Modal -->
    <teleport to="body">
      <transition name="fade-modal">
        <div v-if="showOverride" class="modal-backdrop" @click.self="showOverride = false">
          <div class="modal">
            <div class="modal-header">
              <div>
                <div class="modal-eyebrow">PRIVILEGED ACTION · {{ job.id }}</div>
                <div class="modal-title">Override checkpoint</div>
              </div>
              <button class="modal-close-btn" @click="showOverride = false">
                <svg-icon name="x" :size="16" />
              </button>
            </div>

            <div class="modal-warning">
              <svg-icon name="warn" :size="16" style="flex-shrink:0;" />
              <span>Field supervisors normally log checkpoints from the mobile app. Overrides bypass that — they're logged to the audit trail with your name, role, and reason.</span>
            </div>

            <div class="modal-body">
              <!-- Checkpoint -->
              <div class="form-field">
                <label class="form-label-caps">CHECKPOINT</label>
                <select v-model="overrideCheckpoint" class="form-select">
                  <option value="" disabled>Choose a checkpoint...</option>
                  <option v-for="cp in checkpoints" :key="cp.id" :value="cp.id">
                    {{ cp.label }}{{ cp.status === 'active' ? ` — active (scheduled ${cp.time})` : ` (scheduled ${cp.time})` }}
                  </option>
                </select>
              </div>

              <!-- New State -->
              <div class="form-field">
                <label class="form-label-caps">NEW STATE</label>
                <div class="state-cards">
                  <button class="state-card" :class="{ 'state-card--done': overrideState === 'done' }" @click="overrideState = 'done'">
                    <div class="state-icon state-icon--done"><svg-icon name="check" :size="13" /></div>
                    <div class="state-card-name">Done</div>
                    <div class="state-card-desc">Confirm completion manually</div>
                  </button>
                  <button class="state-card" :class="{ 'state-card--missed': overrideState === 'missed' }" @click="overrideState = 'missed'">
                    <div class="state-icon state-icon--missed"><svg-icon name="x" :size="13" /></div>
                    <div class="state-card-name">Missed</div>
                    <div class="state-card-desc">Mark as failed or not reached</div>
                  </button>
                  <button class="state-card" :class="{ 'state-card--skipped': overrideState === 'skipped' }" @click="overrideState = 'skipped'">
                    <div class="state-icon state-icon--skipped"><svg-icon name="refresh" :size="13" /></div>
                    <div class="state-card-name">Skipped</div>
                    <div class="state-card-desc">No longer applies to this job</div>
                  </button>
                </div>
              </div>

              <!-- Actual Time + Variance -->
              <div class="time-variance-row">
                <div class="form-field" style="flex:1;">
                  <label class="form-label-caps">ACTUAL TIME</label>
                  <input v-model="overrideTime" type="time" class="form-input" />
                  <div class="form-hint">When it actually happened</div>
                </div>
                <div class="form-field" style="flex:1;">
                  <label class="form-label-caps">VARIANCE VS. PLANNED{{ selectedCheckpoint ? ` (${selectedCheckpoint.time})` : '' }}</label>
                  <div class="variance-box">{{ varianceText }}</div>
                </div>
              </div>

              <!-- Reason -->
              <div class="form-field">
                <label class="form-label-caps">REASON (REQUIRED)</label>
                <select v-model="overrideReason" class="form-select">
                  <option value="" disabled>Select a reason...</option>
                  <option value="no_signal">No signal</option>
                  <option value="device_issue">Device issue</option>
                  <option value="manual_entry">Manual entry required</option>
                  <option value="late_report">Late report</option>
                  <option value="other">Other</option>
                </select>
              </div>

              <!-- Notes -->
              <textarea v-model="overrideNote" rows="2" class="form-textarea" placeholder="Additional notes (optional)" />

              <!-- Notify -->
              <label class="notify-row">
                <input type="checkbox" v-model="overrideNotify" class="notify-check" />
                <div>
                  <div class="notify-title">Notify liaison and supervisor</div>
                  <div class="notify-desc">Push the override to field devices so everyone stays in sync</div>
                </div>
              </label>
            </div>

            <div class="modal-footer">
              <div class="modal-signed">Signed as <strong>Logistics Manager</strong></div>
              <div style="display:flex; gap:8px;">
                <Button variant="secondary" size="sm" @click="showOverride = false">Cancel</Button>
                <Button variant="primary" size="sm" @click="saveOverride" :disabled="!overrideCheckpoint || !overrideReason">Override &amp; log</Button>
              </div>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link as InertiaLink } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';

const props = defineProps({
  job: { type: Object, default: () => ({}) },
  checkpoints: { type: Array, default: () => [] },
  crewMembers: { type: Array, default: () => [] },
});

const showOverride = ref(false);
const overrideCheckpoint = ref('');
const overrideState = ref('done');
const overrideTime = ref('');
const overrideReason = ref('');
const overrideNote = ref('');
const overrideNotify = ref(true);

const selectedCheckpoint = computed(() =>
  props.checkpoints.find(cp => cp.id === overrideCheckpoint.value) ?? null
);

const varianceText = computed(() => {
  if (!overrideTime.value || !selectedCheckpoint.value?.time) return '—';
  const [ah, am] = overrideTime.value.split(':').map(Number);
  const [ph, pm] = selectedCheckpoint.value.time.split(':').map(Number);
  const diff = (ah * 60 + am) - (ph * 60 + pm);
  if (diff === 0) return 'On time';
  const abs = Math.abs(diff);
  return diff < 0 ? `-${abs} min early` : `+${abs} min late`;
});

const completedCount = computed(() => 
  props.checkpoints.filter(cp => cp.status === 'done').length
);

const statusMap = {
  'in-progress': { tone: 'live', label: 'In Progress' },
  'scheduled': { tone: 'primary', label: 'Scheduled' },
  'delayed': { tone: 'warn', label: 'Delayed' },
  'done': { tone: 'ok', label: 'Done' },
};

function statusTone(s) {
  return statusMap[s]?.tone ?? 'neutral';
}

function statusLabel(s) {
  return statusMap[s]?.label ?? s;
}

function openOverride() {
  overrideCheckpoint.value = '';
  overrideState.value = 'done';
  overrideTime.value = '';
  overrideReason.value = '';
  overrideNote.value = '';
  overrideNotify.value = true;
  showOverride.value = true;
}

function saveOverride() {
  console.log('Saving override:', {
    checkpoint: overrideCheckpoint.value,
    state: overrideState.value,
    time: overrideTime.value,
    reason: overrideReason.value,
    note: overrideNote.value,
    notify: overrideNotify.value,
  });
  showOverride.value = false;
}
</script>

<style scoped>
.page-header {
  margin-bottom: 16px;
}

.back-link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: var(--ink3);
  text-decoration: none;
  font-size: 13.5px;
  font-weight: 500;
}

.back-link:hover {
  color: var(--ink);
}

.team-badge-lg {
  width: 42px;
  height: 42px;
  border-radius: 8px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 12px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.section-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 14px 20px;
  border-bottom: 1px solid var(--border);
  gap: 12px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 9px 12px;
  border-radius: 7px;
  border: 1px solid var(--border);
  background: none;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink2);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
}

.action-btn:hover {
  background: var(--panel);
  border-color: var(--borderStrong);
}

.action-btn--warn {
  border-color: var(--warn);
  color: var(--warn);
}

.action-btn--warn:hover {
  background: var(--warn-soft);
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 16px;
}

.modal {
  background: var(--surface);
  border-radius: 14px;
  width: 100%;
  max-width: 480px;
  border: 1px solid var(--border);
  box-shadow: 0 24px 48px rgba(0, 0, 0, 0.18);
  display: flex;
  flex-direction: column;
  max-height: 90vh;
  overflow: hidden;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 18px 20px 16px;
  gap: 12px;
}

.modal-eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.8px;
  color: var(--ink3);
  text-transform: uppercase;
  margin-bottom: 3px;
}

.modal-title {
  font-size: 17px;
  font-weight: 700;
  color: var(--ink);
}

.modal-close-btn {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: var(--panel);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--ink3);
  flex-shrink: 0;
  transition: all 0.15s;
  margin-top: 2px;
}

.modal-close-btn:hover {
  background: var(--border);
  color: var(--ink);
}

.modal-warning {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 0 20px 4px;
  padding: 10px 12px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  font-size: 12.5px;
  color: #92400e;
  line-height: 1.5;
}

.modal-body {
  padding: 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  overflow-y: auto;
}

.modal-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 12px 20px;
  border-top: 1px solid var(--border);
  background: var(--panel);
  border-radius: 0 0 14px 14px;
  flex-shrink: 0;
}

.modal-signed {
  font-size: 12px;
  color: var(--ink3);
}

.modal-signed strong {
  color: var(--ink2);
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.form-label-caps {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  color: var(--ink3);
}

.form-hint {
  font-size: 11px;
  color: var(--ink4);
  margin-top: 2px;
}

.form-input,
.form-select,
.form-textarea {
  width: 100%;
  padding: 9px 11px;
  font-size: 13.5px;
  color: var(--ink);
  background: var(--surface);
  border: 1.5px solid var(--border);
  border-radius: 8px;
  font-family: inherit;
  transition: border-color 0.15s, box-shadow 0.15s;
  box-sizing: border-box;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-select {
  cursor: pointer;
}

.form-textarea {
  resize: vertical;
  min-height: 64px;
}

/* State cards */
.state-cards {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
}

.state-card {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 4px;
  padding: 10px 11px;
  border-radius: 9px;
  border: 1.5px solid var(--border);
  background: var(--surface);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: border-color 0.15s, background 0.15s;
}

.state-card:hover {
  border-color: var(--borderStrong);
  background: var(--panel);
}

.state-card-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
}

.state-card-desc {
  font-size: 11px;
  color: var(--ink3);
  line-height: 1.4;
}

.state-icon {
  width: 22px;
  height: 22px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 2px;
}

.state-icon--done   { background: var(--ok-soft);   color: var(--ok); }
.state-icon--missed { background: var(--warn-soft);  color: var(--warn); }
.state-icon--skipped{ background: var(--accent-soft); color: var(--accent-fg); }

.state-card--done   { border-color: var(--ok);   background: var(--ok-soft); }
.state-card--missed { border-color: var(--warn);  background: var(--warn-soft); }
.state-card--skipped{ border-color: var(--accent); background: var(--accent-soft); }

/* Time + variance row */
.time-variance-row {
  display: flex;
  gap: 12px;
}

.variance-box {
  padding: 9px 11px;
  font-size: 13.5px;
  font-family: var(--mono);
  color: var(--ink);
  background: var(--panel);
  border: 1.5px solid var(--border);
  border-radius: 8px;
  min-height: 38px;
  display: flex;
  align-items: center;
}

/* Notify row */
.notify-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  cursor: pointer;
  padding: 10px 12px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
}

.notify-check {
  margin-top: 2px;
  flex-shrink: 0;
  width: 15px;
  height: 15px;
  cursor: pointer;
  accent-color: var(--accent);
}

.notify-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.notify-desc {
  font-size: 11.5px;
  color: var(--ink3);
  margin-top: 1px;
}

/* Modal Transitions */
.fade-modal-enter-active,
.fade-modal-leave-active {
  transition: all 0.25s ease;
}

.fade-modal-enter-from,
.fade-modal-leave-to {
  opacity: 0;
}

.fade-modal-enter-from .modal,
.fade-modal-leave-to .modal {
  transform: scale(0.95) translateY(-10px);
  opacity: 0;
}

.fade-modal-enter-active .modal,
.fade-modal-leave-active .modal {
  transition: all 0.25s ease;
}

/* Responsive Layout */
.detail-grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 14px;
}

.timing-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

@media (max-width: 1024px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .timing-grid {
    grid-template-columns: 1fr;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 8px;
  }

  .modal {
    max-width: 100%;
    margin: 16px;
    border-radius: 12px;
  }

  .modal-header {
    padding: 16px 20px;
  }

  .modal-body {
    padding: 20px;
  }

  .modal-footer {
    padding: 14px 20px;
  }
}

@media (max-width: 480px) {
  .team-badge-lg {
    width: 36px;
    height: 36px;
    font-size: 11px;
  }

  .action-btn {
    font-size: 12px;
    padding: 8px 10px;
  }

  .modal {
    border-radius: 16px 16px 0 0;
    margin: 0;
    max-height: 80vh;
  }

  .modal-body {
    max-height: 60vh;
    overflow-y: auto;
  }
}
</style>
