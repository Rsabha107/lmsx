<template>
  <app-layout>
    <div class="page-header">
      <div>
        <p class="page-sub">Live execution · {{ schedule.length }} jobs</p>
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <h1 class="page-title">Jobs for</h1>
          
          <!-- Plan Selector -->
          <div v-if="plans && plans.length > 0" style="position: relative;">
            <!-- Trigger: shows selected plan header -->
            <div @click="showPlanDropdown = !showPlanDropdown" style="cursor: pointer; user-select: none;">
              <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 20px; font-weight: 700; color: #111827; letter-spacing: -0.3px;">
                  {{ selectedPlanObj ? selectedPlanObj.name : 'Select a Plan' }}
                </span>
                <svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="color: #6B7280; flex-shrink: 0; margin-top: 2px;">
                  <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
              </div>
            </div>

            <!-- Dropdown -->
            <div v-if="showPlanDropdown" class="plans-dropdown" style="position: absolute; top: calc(100% + 10px); left: 0; background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; z-index: 1000; box-shadow: 0 8px 28px rgba(0,0,0,0.12); overflow: hidden; width: min(380px, calc(100vw - 36px));">
              <!-- Search -->
              <div style="padding: 12px 12px 8px;">
                <input
                  type="text"
                  placeholder="Search plans..."
                  v-model="planSearchTerm"
                  @click.stop
                  style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 13px; color: #374151; background: #F9FAFB; outline: none; box-sizing: border-box;"
                />
              </div>

              <!-- Grouped plan list -->
              <div style="max-height: 340px; overflow-y: auto; padding-bottom: 4px;">
                <template v-for="group in groupedFilteredPlans" :key="group.status">
                  <div v-if="group.plans.length">
                    <div style="padding: 8px 14px 4px; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #9CA3AF;">
                      {{ group.label }}
                    </div>
                    <div
                      v-for="plan in group.plans"
                      :key="plan.id"
                      @click="selectPlan(plan.id)"
                      :style="{
                        padding: '10px 14px',
                        cursor: 'pointer',
                        borderLeft: activePlan == plan.id ? '3px solid #3B82F6' : '3px solid transparent',
                        background: activePlan == plan.id ? '#EFF6FF' : 'transparent',
                        display: 'flex',
                        alignItems: 'center',
                        justifyContent: 'space-between',
                        transition: 'background 0.1s',
                      }"
                      @mouseenter="e => { if (activePlan != plan.id) e.currentTarget.style.background = '#F9FAFB' }"
                      @mouseleave="e => { e.currentTarget.style.background = activePlan == plan.id ? '#EFF6FF' : 'transparent' }"
                    >
                      <div>
                        <div style="font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 2px;">{{ plan.name }}</div>
                        <div style="font-size: 11px; color: #9CA3AF;">
                          <span style="font-family: monospace;">{{ plan.code }}</span>
                          <span style="margin: 0 4px;">·</span><span style="font-family: monospace;">{{ formatDateTime(plan.date) }}</span>
                          <span style="margin: 0 4px;">·</span>{{ plan.movements_count }} movements
                          <span style="margin: 0 4px;">·</span>{{ plan.teams_count }} teams
                        </div>
                      </div>
                      <svg v-if="activePlan == plan.id" width="16" height="16" viewBox="0 0 20 20" fill="none" style="color: #3B82F6; flex-shrink: 0; margin-left: 10px;">
                        <path d="M4 10L8 14L16 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                    </div>
                  </div>
                </template>
                <div v-if="allFilteredPlansEmpty" style="padding: 20px 14px; text-align: center; color: #9CA3AF; font-size: 12px;">
                  No plans found
                </div>
              </div>
            </div>
          </div>

          <!-- Close dropdown overlay -->
          <div v-if="showPlanDropdown" @click="showPlanDropdown = false" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 999;"></div>
        </div>
      </div>
      <div class="page-header-actions">
        <RefreshButton :only="['schedule']" @refresh="handleRefresh" />
        <Button variant="secondary" size="sm">
          <template #icon><svg-icon name="filter" :size="14" /></template>
          Filter
        </Button>
        <Button variant="primary" size="sm">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New job
        </Button>
      </div>
    </div>

    <!-- Filter pills -->
    <div class="filter-bar">
      <button v-for="f in filters" :key="f.value"
        :class="['filter-pill', activeFilter === f.value ? 'filter-pill--active' : '']"
        @click="activeFilter = f.value">
        {{ f.label }}
        <span class="filter-count">{{ f.count }}</span>
      </button>
    </div>

    <div class="jobs-layout">
      <!-- Job list -->
      <div class="jobs-list-card">
        <div class="job-list-header">
          <div class="jl-col-job">JOB</div>
          <div class="jl-col-stage">STAGE</div>
          <div class="jl-col-route">TEAM · ROUTE</div>
          <div class="jl-col-progress">PROGRESS</div>
          <div class="jl-col-eta">ETA</div>
          <div class="jl-col-alerts">ALERTS</div>
        </div>
        <div class="jobs-list-scroll">
          <div
            v-for="job in filtered" :key="job.id"
            @click="selectJob(job)"
            :class="['job-item', selectedJob?.id === job.id ? 'job-item--active' : '']"
          >
            <div class="jl-col-job">
              <span class="jl-job-id">{{ job.id }}</span>
              <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                <span class="jl-job-phase">{{ job.kind || '—' }}</span>
                <span v-if="job.date" class="jl-job-date">{{ formatDate(job.date) }}</span>
              </div>
            </div>
            <div class="jl-col-stage">
              <status-pill :tone="statusTone(job.status)" :dot="true" size="sm">
                {{ stagePillLabel(job.status) }}
              </status-pill>
            </div>
            <div class="jl-col-route">
              <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                <span class="jl-team">{{ job.team }}</span>
                <span v-if="job.event_name" class="jl-event-badge">{{ job.event_code || job.event_name }}</span>
              </div>
              <span class="jl-route">{{ job.from }} → {{ job.to }}</span>
            </div>
            <div class="jl-col-progress">
              <div class="jl-progress-bar">
                <div class="jl-progress-fill" :style="{
                  width: `${jobProgress(job)}%`,
                  background: job.status === 'delayed' ? 'var(--warn)' : job.status === 'completed' ? 'var(--ok)' : 'var(--accent)'
                }"/>
              </div>
              <span class="jl-steps">{{ jobStepsText(job) }}</span>
            </div>
            <div class="jl-col-eta">
              <span :class="['jl-eta-time', job.delay ? 'jl-eta-time--delayed' : '']">{{ job.arr }}</span>
              <span v-if="job.delay" class="jl-eta-delay">+{{ job.delay }}m</span>
            </div>
            <div class="jl-col-alerts">
              <span v-if="job.alerts" class="jl-alert-badge">
                <svg width="11" height="11" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;">
                  <path d="M8 2.5L13.5 12.5H2.5L8 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                  <line x1="8" y1="7" x2="8" y2="10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                  <circle cx="8" cy="11.5" r="0.75" fill="currentColor"/>
                </svg>
                {{ job.alerts }}
              </span>
              <span v-else class="jl-no-alert">—</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Detail panel -->
      <div v-if="selectedJob" class="job-detail-panel">
        <!-- Header card -->
        <div class="detail-card">
          <div class="detail-header-top">
            <span class="team-badge">{{ selectedJob.code }}</span>
            <div>
              <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 2px;">
                <div class="detail-id">{{ selectedJob.id }} · {{ selectedJob.team }}</div>
                <span v-if="selectedJob.event_name" class="detail-event-badge">{{ selectedJob.event_code || selectedJob.event_name }}</span>
              </div>
              <div v-if="selectedJob.date" class="detail-date">{{ formatDateLong(selectedJob.date) }}</div>
            </div>
            <status-pill :tone="statusTone(selectedJob.status)" :dot="true" size="sm">
              {{ statusLabel(selectedJob.status) }}
            </status-pill>
            <div class="detail-actions">
              <Button variant="secondary" size="sm">Contact</Button>
              <Button 
                v-if="selectedJob.status !== 'in-progress'" 
                variant="primary" 
                size="sm" 
                @click="startJob">Start Job</Button>
              <Button variant="primary" size="sm" @click="openOverrideModal">Override</Button>
            </div>
          </div>
          <div class="detail-subtitle">
            {{ selectedJob.from }} → {{ selectedJob.to }} · {{ selectedJob.vehicle }} · {{ selectedJob.pax }} pax
          </div>

          <div :class="['detail-stats', (selectedJob.status === 'completed' && timeVariance) || selectedJob.updated_at ? 'detail-stats--five' : '']">
            <mini-stat label="Window" :value="`${selectedJob.dep} – ${selectedJob.arr}`"/>
            <mini-stat label="Progress" :value="`${progressPercentage}%`"/>
            <mini-stat label="Checks" :value="`${doneCount}/${totalChecks}`"/>
            <mini-stat 
              v-if="selectedJob.status === 'completed' && timeVariance" 
              label="Time Variance" 
              :value="timeVariance" 
              :tone="timeVarianceTone"/>
            <mini-stat 
              v-else-if="selectedJob.updated_at" 
              label="Last Update" 
              :value="formatTimeAgo(selectedJob.updated_at)"/>
            <mini-stat label="Status" :value="statusLabel(selectedJob.status)" :tone="statusTone(selectedJob.status)"/>
          </div>
        </div>

        <div class="detail-grid">
          <!-- Checkpoint timeline -->
          <div v-if="selectedJob.checkpoints && selectedJob.checkpoints.length" class="checkpoint-section">
            <div class="checkpoint-content">
              <CheckpointTimeline :checkpoints="selectedJob.checkpoints" />
            </div>
          </div>

          <!-- Crew card -->
          <div class="crew-section">
            <div class="checkpoint-header">
              <h3 class="section-title">Crew</h3>
              <span class="section-kicker">On this job</span>
            </div>
            <div class="crew-list">
              <div v-for="(person, label) in crewMembers" :key="label" class="crew-item">
                <div class="crew-avatar">
                  {{ getInitials(person.name) }}
                </div>
                <div class="crew-info">
                  <div class="crew-name">{{ person.name }}</div>
                  <div class="crew-role">{{ label }}</div>
                  <a v-if="person.phone" :href="`tel:${person.phone}`" class="crew-phone">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    {{ person.phone }}
                  </a>
                </div>
                <status-pill tone="ok" :dot="true" size="sm">On shift</status-pill>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="job-detail-empty">
        Select a job to view details
      </div>
    </div>
    <!-- Override Checkpoint Modal -->
    <Modal :show="showOverrideModal" @close="showOverrideModal = false" maxWidth="500px">
      <template #title>
        <span class="override-modal-title-wrap">
          <span class="override-privileged-badge">PRIVILEGED ACTION · {{ selectedJob?.id }}</span>
          Override checkpoint
        </span>
      </template>

      <div class="override-body">
        <div class="override-warning">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>Field supervisors normally log checkpoints from the mobile app. Overrides bypass that — they're logged to the audit trail with your name, role, and reason.</span>
        </div>

        <div class="override-field">
          <label class="override-label">CHECKPOINT</label>
          <select v-model="overrideCheckpoint" class="override-select override-select--checkpoints">
            <option v-for="cp in selectedJob?.checkpoints" :key="cp.id" :value="cp" :class="cp.state === 'done' ? 'checkpoint-option--done' : ''">
              {{ cp.label || cp.name }} — {{ cp.status || cp.state }} {{ cp.state === 'done' && cp.scheduled_at && cp.completed_at ? `(${cp.scheduled_at} → ${cp.completed_at} ✓)` : `(scheduled ${cp.scheduled_at || cp.at})` }}{{ (cp.requires_photo || cp.requiresPhoto || cp.requires_signature || cp.requiresSignature) ? ' 📋' : '' }}
            </option>
          </select>
          <div v-if="overrideCheckpoint?.requires_photo || overrideCheckpoint?.requiresPhoto || overrideCheckpoint?.requires_signature || overrideCheckpoint?.requiresSignature" class="override-field-hint">
            <strong>Evidence required:</strong> 
            <span v-if="overrideCheckpoint?.requires_photo || overrideCheckpoint?.requiresPhoto">Photo</span>
            <span v-if="(overrideCheckpoint?.requires_photo || overrideCheckpoint?.requiresPhoto) && (overrideCheckpoint?.requires_signature || overrideCheckpoint?.requiresSignature)"> and </span>
            <span v-if="overrideCheckpoint?.requires_signature || overrideCheckpoint?.requiresSignature">Signature</span>
          </div>
        </div>

        <div class="override-field">
          <label class="override-label">NEW STATE</label>
          <div class="override-states" :style="{ gridTemplateColumns: `repeat(${overrideStates.length}, 1fr)` }">
            <button v-for="s in overrideStates" :key="s.value"
              :class="['override-state-btn', `override-state-btn--${s.value}`, overrideState === s.value ? 'override-state-btn--active' : '']"
              @click="overrideState = s.value">
              <span class="override-state-icon">{{ s.icon }}</span>
              <strong>{{ s.label }}</strong>
              <small>{{ s.desc }}</small>
            </button>
          </div>
        </div>

        <div v-if="overrideState === 'done'" class="override-two-col">
          <div class="override-field">
            <label class="override-label">ACTUAL TIME</label>
            <input type="time" v-model="overrideTime" class="override-input" />
            <div class="override-field-hint">When it actually happened</div>
          </div>
          <div class="override-field">
            <label class="override-label">VARIANCE VS. PLANNED ({{ overrideCheckpoint?.scheduled_at || overrideCheckpoint?.at || '—' }})</label>
            <div class="override-variance" :class="overrideVarianceMinutes > 0 ? 'is-late' : overrideVarianceMinutes < 0 ? 'is-early' : ''">
              {{ overrideVarianceText }}
            </div>
          </div>
        </div>

        <div class="override-field">
          <label class="override-label">REASON (REQUIRED)</label>
          <select v-model="overrideReason" class="override-select">
            <option value="" disabled>Select a reason…</option>
            <option value="no_signal">No signal</option>
            <option value="device_offline">Device offline</option>
            <option value="supervisor_error">Supervisor error</option>
            <option value="late_arrival">Late arrival</option>
            <option value="operational_change">Operational change</option>
            <option value="other">Other</option>
          </select>
        </div>

        <div class="override-field">
          <textarea v-model="overrideNotes" class="override-textarea" placeholder="Additional notes (optional)" rows="3" />
        </div>

        <!-- Photo Upload (if required) -->
        <div v-if="overrideState === 'done' && (overrideCheckpoint?.requires_photo || overrideCheckpoint?.requiresPhoto)" class="override-field">
          <label class="override-label">PHOTO (REQUIRED)</label>
          <input 
            type="file" 
            accept="image/*" 
            @change="handlePhotoUpload" 
            class="override-file-input"
            ref="photoInput"
          />
          <div v-if="overridePhotoPreview" class="photo-preview">
            <img :src="overridePhotoPreview" alt="Photo preview" @click="openPhotoLightbox" style="cursor: pointer;" />
            <button type="button" @click="clearPhoto" class="clear-photo-btn">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                <path d="M12 4L4 12M4 4L12 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
              </svg>
            </button>
          </div>
          <div class="override-field-hint">Upload a photo as evidence</div>
        </div>

        <!-- Signature Canvas (if required) -->
        <div v-if="overrideState === 'done' && (overrideCheckpoint?.requires_signature || overrideCheckpoint?.requiresSignature)" class="override-field">
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

        <label class="override-notify">
          <input type="checkbox" v-model="overrideNotify" class="override-notify-check" />
          <div class="override-notify-text">
            <strong>Notify liaison and supervisor</strong>
            <span>Push the override to field devices so everyone stays in sync</span>
          </div>
        </label>
      </div>

      <template #footer>
        <div class="override-footer-inner">
          <span class="override-signed-as">Signed as <strong>Logistics Manager</strong></span>
          <div style="display:flex;gap:8px;">
            <Button variant="secondary" size="sm" @click="showOverrideModal = false" :disabled="overrideProcessing">Cancel</Button>
            <Button variant="primary" size="sm" :disabled="!canSubmitOverride" :processing="overrideProcessing" @click="submitOverride">Override &amp; log</Button>
          </div>
        </div>
      </template>
    </Modal>

    <!-- Photo Lightbox -->
    <div v-if="showPhotoLightbox" class="photo-lightbox" @click="closePhotoLightbox">
      <div class="photo-lightbox-content" @click.stop>
        <img :src="overridePhotoPreview" alt="Photo full view" />
        <button type="button" @click="closePhotoLightbox" class="photo-lightbox-close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
            <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import MiniStat from '../Components/MiniStat.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import Modal from '../Components/Modal.vue';
import Button from '../Components/Button.vue';
import CheckpointTimeline from '../Components/CheckpointTimeline.vue';

const props = defineProps({
  schedule: { type: Array, default: () => [] },
  plans: { type: Array, default: () => [] },
  activePlan: { type: [Number, String], default: null },
});

const selectedJob = ref(null);
const activeFilter = ref('all');
const showPlanDropdown = ref(false);
const planSearchTerm = ref('');

const PLAN_STATUS_GROUPS = [
  { status: 'active',    label: 'ACTIVE' },
  { status: 'draft',     label: 'DRAFTS' },
  { status: 'upcoming',  label: 'UPCOMING' },
  { status: 'completed', label: 'COMPLETED' },
];

const PLAN_STATUS_STYLE = {
  active:    { bg: '#D1FAE5', color: '#065F46', dot: '#059669' },
  draft:     { bg: '#FEF3C7', color: '#92400E', dot: '#D97706' },
  upcoming:  { bg: '#DBEAFE', color: '#1E40AF', dot: '#3B82F6' },
  completed: { bg: '#F3F4F6', color: '#374151', dot: '#9CA3AF' },
};

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'live':        { tone: 'live',    label: 'Live' },
  'pending':     { tone: 'primary', label: 'Scheduled' },
  'dispatched':  { tone: 'primary', label: 'Dispatched' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'completed':   { tone: 'ok',      label: 'Done' },
  'cancelled':   { tone: 'neutral', label: 'Cancelled' },
  'queued':      { tone: 'neutral', label: 'Queued' },
  'issue':       { tone: 'warn',    label: 'Issue' },
};
function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
function statusLabel(s) { return statusMap[s]?.label ?? s; }

const stageLabelMap = {
  'in-progress': 'live',
  'live':        'live',
  'pending':     'scheduled',
  'dispatched':  'dispatched',
  'delayed':     'delayed',
  'completed':   'completed',
  'cancelled':   'cancelled',
  'queued':      'queued',
  'issue':       'issue',
};
function stagePillLabel(s) { return stageLabelMap[s] ?? s; }

function jobStepsText(job) {
  if (job.checkpoints && job.checkpoints.length > 0) {
    const done = job.checkpoints.filter(c => c.state === 'done' || c.status === 'done').length;
    return `${done}/${job.checkpoints.length} steps`;
  }
  return '';
}

function selectJob(job) {
  selectedJob.value = job;
}

function handleRefresh() {
  // Store the current job ID before refresh
  const currentJobId = selectedJob.value?.id;
  
  // After refresh completes, re-select the same job if it was selected
  if (currentJobId) {
    nextTick(() => {
      selectedJob.value = props.schedule.find(j => j.id === currentJobId) || null;
    });
  }
}

function jobProgress(job) {
  // If job has checkpoints, calculate actual progress
  if (job.checkpoints && job.checkpoints.length > 0) {
    const completed = job.checkpoints.filter(c => c.state === 'done' || c.status === 'done').length;
    return Math.round((completed / job.checkpoints.length) * 100);
  }
  
  // Fallback to status-based progress if no checkpoints
  if (job.status === 'completed') return 100;
  if (job.status === 'in-progress') return 65;
  if (job.status === 'delayed') return 45;
  if (job.status === 'dispatched') return 30;
  return 10;
}

function jobEtaColor(job) {
  if (job.status === 'delayed') return 'var(--danger)';
  if (job.status === 'in-progress') return 'var(--warn)';
  if (job.status === 'completed') return 'var(--ok)';
  return 'var(--ok)';
}

const doneCount = computed(() => {
  if (!selectedJob.value || !selectedJob.value.checkpoints) return 0;
  return selectedJob.value.checkpoints.filter(c => c.state === 'done' || c.status === 'done').length;
});

const totalChecks = computed(() => {
  if (!selectedJob.value || !selectedJob.value.checkpoints) return 0;
  return selectedJob.value.checkpoints.length;
});

const progressPercentage = computed(() => {
  if (totalChecks.value === 0) return 0;
  return Math.round((doneCount.value / totalChecks.value) * 100);
});

const timeVariance = computed(() => {
  if (!selectedJob.value || !selectedJob.value.checkpoints) return null;
  
  let totalVarianceMinutes = 0;
  let hasVariance = false;
  
  selectedJob.value.checkpoints.forEach(checkpoint => {
    if ((checkpoint.state === 'done' || checkpoint.status === 'done') && 
        checkpoint.scheduled_at && 
        checkpoint.completed_at) {
      // Parse times (format: "HH:mm")
      const [sh, sm] = checkpoint.scheduled_at.split(':').map(Number);
      const [ch, cm] = checkpoint.completed_at.split(':').map(Number);
      
      if (!isNaN(sh) && !isNaN(sm) && !isNaN(ch) && !isNaN(cm)) {
        const scheduledMinutes = sh * 60 + sm;
        const completedMinutes = ch * 60 + cm;
        const variance = completedMinutes - scheduledMinutes;
        totalVarianceMinutes += variance;
        hasVariance = true;
      }
    }
  });
  
  if (!hasVariance) return null;
  
  if (totalVarianceMinutes === 0) return 'On time';
  if (totalVarianceMinutes > 0) return `+${totalVarianceMinutes} min`;
  return `${totalVarianceMinutes} min`;
});

const timeVarianceTone = computed(() => {
  if (!timeVariance.value || timeVariance.value === 'On time') return 'ok';
  if (timeVariance.value.startsWith('+')) return 'warn';
  return 'ok'; // Early completion
});

const crewMembers = computed(() => {
  if (!selectedJob.value) return {};
  return {
    Supervisor: {
      name: selectedJob.value.supervisor,
      phone: selectedJob.value.supervisor_phone,
    },
    Driver: {
      name: selectedJob.value.driver,
      phone: selectedJob.value.driver_phone,
    },
  };
});

function getInitials(name) {
  return name.split(' ').map(s => s[0]).join('').slice(0, 2);
}

function formatDate(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${month}/${day}`;
}

function formatDateLong(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}

function formatTimeAgo(dateString) {
  if (!dateString) return '—';
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  
  const diffHours = Math.floor(diffMins / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
}

const filters = computed(() => [
  { value: 'all',         label: 'All',         count: props.schedule.length },
  { value: 'in-progress', label: 'In Progress', count: props.schedule.filter(j => j.status === 'in-progress').length },
  { value: 'delayed',     label: 'Delayed',     count: props.schedule.filter(j => j.status === 'delayed').length },
  { value: 'pending',     label: 'Scheduled',   count: props.schedule.filter(j => j.status === 'pending').length },
  { value: 'completed',   label: 'Completed',   count: props.schedule.filter(j => j.status === 'completed').length },
]);

const filtered = computed(() =>
  activeFilter.value === 'all'
    ? props.schedule
    : props.schedule.filter(j => j.status === activeFilter.value)
);

const selectedPlanObj = computed(() => {
  if (!props.activePlan || !props.plans) return null;
  // Use == for type coercion since activePlan might be a string from query param
  return props.plans.find(p => p.id == props.activePlan);
});

const groupedFilteredPlans = computed(() => {
  const term = planSearchTerm.value.toLowerCase();
  return PLAN_STATUS_GROUPS.map(g => ({
    ...g,
    plans: props.plans.filter(p => p.status === g.status && (!term || p.name.toLowerCase().includes(term) || p.code.toLowerCase().includes(term))),
  }));
});

const allFilteredPlansEmpty = computed(() => groupedFilteredPlans.value.every(g => g.plans.length === 0));

const planStatusPillStyle = (status) => {
  const s = PLAN_STATUS_STYLE[status] ?? PLAN_STATUS_STYLE.upcoming;
  return { display: 'inline-flex', alignItems: 'center', gap: '4px', padding: '1px 7px', borderRadius: '999px', fontSize: '10px', fontWeight: '600', background: s.bg, color: s.color };
};

const planStatusDotStyle = (status) => {
  const s = PLAN_STATUS_STYLE[status] ?? PLAN_STATUS_STYLE.upcoming;
  return { width: '5px', height: '5px', borderRadius: '50%', background: s.dot, display: 'inline-block', flexShrink: 0 };
};

function formatDateTime(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function selectPlan(planId) {
  showPlanDropdown.value = false;
  planSearchTerm.value = '';
  router.visit(`/jobs?plan=${planId}`, {
    preserveScroll: true,
    preserveState: false,
  });
}

// Override modal
const showOverrideModal = ref(false);
const overrideProcessing = ref(false);
const overrideCheckpoint = ref(null);
const overrideState = ref('done');
const overrideTime = ref('');
const overrideReason = ref('');
const overrideNotes = ref('');
const overrideNotify = ref(true);
const overridePhoto = ref(null);
const overridePhotoPreview = ref(null);
const overrideSignature = ref(null);
const photoInput = ref(null);
const signatureCanvas = ref(null);
const isDrawing = ref(false);
const signatureContext = ref(null);
const showPhotoLightbox = ref(false);

const overrideStates = computed(() => {
  return [
    { value: 'done', label: 'Done', icon: '✓', desc: 'Confirm completion manually' },
    { value: 'skipped', label: 'Skipped', icon: '↷', desc: 'No longer applies to this job' }
  ];
});

function openOverrideModal() {
  const active = selectedJob.value?.checkpoints?.find(c => c.status === 'active' || c.state === 'active');
  overrideCheckpoint.value = active ?? selectedJob.value?.checkpoints?.[0] ?? null;
  
  // Debug checkpoint requirements
  console.log('Checkpoint data:', {
    checkpoint: overrideCheckpoint.value,
    requires_photo: overrideCheckpoint.value?.requires_photo,
    requires_signature: overrideCheckpoint.value?.requires_signature,
    requiresPhoto: overrideCheckpoint.value?.requiresPhoto,
    requiresSignature: overrideCheckpoint.value?.requiresSignature
  });
  
  // Set initial state - if checkpoint requires evidence, start with 'done', otherwise 'done'
  const requiresEvidence = overrideCheckpoint.value?.requires_photo || overrideCheckpoint.value?.requiresPhoto || 
                           overrideCheckpoint.value?.requires_signature || overrideCheckpoint.value?.requiresSignature;
  overrideState.value = 'done';
  
  const now = new Date();
  overrideTime.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  overrideReason.value = '';
  overrideNotes.value = '';
  overrideNotify.value = true;
  overridePhoto.value = null;
  overridePhotoPreview.value = null;
  overrideSignature.value = null;
  overrideProcessing.value = false;
  showPhotoLightbox.value = false;
  showOverrideModal.value = true;
  
  // Initialize signature canvas after modal opens
  nextTick(() => {
    initSignatureCanvas();
  });
}

const overrideVarianceMinutes = computed(() => {
  const scheduledTime = overrideCheckpoint.value?.scheduled_at || overrideCheckpoint.value?.at;
  if (!scheduledTime || !overrideTime.value) return null;
  const [sh, sm] = scheduledTime.split(':').map(Number);
  const [ah, am] = overrideTime.value.split(':').map(Number);
  if (isNaN(sh) || isNaN(sm) || isNaN(ah) || isNaN(am)) return null;
  return (ah * 60 + am) - (sh * 60 + sm);
});

const overrideVarianceText = computed(() => {
  const v = overrideVarianceMinutes.value;
  if (v === null) return '—';
  if (v === 0) return 'On time';
  const abs = Math.abs(v);
  return v > 0 ? `+${abs} min late` : `${abs} min early`;
});

const canSubmitOverride = computed(() => {
  if (!overrideReason.value || overrideProcessing.value) return false;
  
  // Check if state is 'done' and checkpoint requires photo or signature
  if (overrideState.value === 'done') {
    const needsPhoto = overrideCheckpoint.value?.requires_photo || overrideCheckpoint.value?.requiresPhoto;
    const needsSignature = overrideCheckpoint.value?.requires_signature || overrideCheckpoint.value?.requiresSignature;
    if (needsPhoto && !overridePhoto.value) return false;
    if (needsSignature && !overrideSignature.value) return false;
  }
  
  return true;
});

// Photo handling
function handlePhotoUpload(event) {
  const file = event.target.files[0];
  if (file) {
    console.log('Photo selected:', {
      name: file.name,
      size: file.size,
      type: file.type
    });
    
    // Validate file
    if (!file.type.startsWith('image/')) {
      alert('Please select a valid image file');
      return;
    }
    
    if (file.size > 10 * 1024 * 1024) { // 10MB
      alert('Photo must be less than 10MB');
      return;
    }
    
    overridePhoto.value = file;
    const reader = new FileReader();
    reader.onload = (e) => {
      overridePhotoPreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  }
}

function clearPhoto() {
  overridePhoto.value = null;
  overridePhotoPreview.value = null;
  if (photoInput.value) {
    photoInput.value.value = '';
  }
}

function openPhotoLightbox() {
  console.log('Opening photo lightbox');
  showPhotoLightbox.value = true;
}

function closePhotoLightbox() {
  console.log('Closing photo lightbox');
  showPhotoLightbox.value = false;
}

// Signature handling
function initSignatureCanvas() {
  if (!signatureCanvas.value) return;
  
  const canvas = signatureCanvas.value;
  canvas.width = canvas.offsetWidth;
  canvas.height = 150;
  signatureContext.value = canvas.getContext('2d');
  
  if (signatureContext.value) {
    signatureContext.value.strokeStyle = '#111827';
    signatureContext.value.lineWidth = 2;
    signatureContext.value.lineCap = 'round';
  }
}

function startSignature(e) {
  if (!signatureCanvas.value || !signatureContext.value) {
    // Initialize if not ready
    initSignatureCanvas();
    if (!signatureContext.value) return;
  }
  
  isDrawing.value = true;
  const rect = signatureCanvas.value.getBoundingClientRect();
  const x = (e.clientX || e.touches[0].clientX) - rect.left;
  const y = (e.clientY || e.touches[0].clientY) - rect.top;
  signatureContext.value.beginPath();
  signatureContext.value.moveTo(x, y);
}

function drawSignature(e) {
  if (!isDrawing.value || !signatureContext.value || !signatureCanvas.value) return;
  
  const rect = signatureCanvas.value.getBoundingClientRect();
  const x = (e.clientX || e.touches?.[0]?.clientX) - rect.left;
  const y = (e.clientY || e.touches?.[0]?.clientY) - rect.top;
  signatureContext.value.lineTo(x, y);
  signatureContext.value.stroke();
}

function endSignature() {
  if (!isDrawing.value || !signatureCanvas.value) return;
  isDrawing.value = false;
  overrideSignature.value = signatureCanvas.value.toDataURL();
}

function clearSignature() {
  if (signatureContext.value && signatureCanvas.value) {
    signatureContext.value.clearRect(0, 0, signatureCanvas.value.width, signatureCanvas.value.height);
    overrideSignature.value = null;
  }
}

function startJob() {
  if (!selectedJob.value) return;

  const csrfToken = document.querySelector('meta[name="csrf-token")')?.content;

  fetch(`/jobs/${selectedJob.value.id}/status`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken || '',
    },
    body: JSON.stringify({ status: 'in-progress' }),
  })
    .then(response => {
      if (!response.ok) throw new Error('Network response was not ok');
      return response.json();
    })
    .then(data => {
      if (data.success) {
        const currentJobId = selectedJob.value?.id;
        router.reload({ 
          only: ['schedule'],
          onSuccess: () => {
            // Re-select the same job after refresh
            if (currentJobId) {
              selectedJob.value = props.schedule.find(j => j.id === currentJobId);
            }
          }
        });
      } else {
        alert('Failed to update job status: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Error updating job status:', error);
      alert('Failed to update job status. Please try again.');
    });
}

function submitOverride() {
  if (!overrideReason.value || !overrideCheckpoint.value) return;

  // Validate required photo
  const needsPhoto = overrideCheckpoint.value?.requires_photo || overrideCheckpoint.value?.requiresPhoto;
  const needsSignature = overrideCheckpoint.value?.requires_signature || overrideCheckpoint.value?.requiresSignature;
  
  if (overrideState.value === 'done' && needsPhoto && !overridePhoto.value) {
    alert('Please upload a photo as required by this checkpoint.');
    return;
  }

  // Validate required signature
  if (overrideState.value === 'done' && needsSignature && !overrideSignature.value) {
    alert('Please provide a signature as required by this checkpoint.');
    return;
  }

  overrideProcessing.value = true;
  
  const checkpointId = overrideCheckpoint.value.id || overrideCheckpoint.value.dbId;
  
  // Build FormData for file uploads
  const formData = new FormData();
  formData.append('state', overrideState.value);
  formData.append('reason', overrideReason.value);
  if (overrideNotes.value) formData.append('notes', overrideNotes.value);

  // Add actual time only for 'done' state
  if (overrideState.value === 'done') {
    formData.append('actual_time', overrideTime.value);
    
    // Add photo if provided
    if (overridePhoto.value && overridePhoto.value instanceof File) {
      console.log('Appending photo to FormData:', {
        name: overridePhoto.value.name,
        size: overridePhoto.value.size,
        type: overridePhoto.value.type
      });
      
      // Create a new File object with a safe filename if needed
      const safeFilename = overridePhoto.value.name.replace(/[^a-zA-Z0-9.-]/g, '_');
      const fileToUpload = new File([overridePhoto.value], safeFilename, { type: overridePhoto.value.type });
      
      formData.append('photo', fileToUpload);
    }
    
    // Add signature if required and provided
    if (needsSignature && overrideSignature.value) {
      formData.append('signature_data', overrideSignature.value);
    }
  }
  
  // Debug: Log FormData contents
  console.log('FormData entries:');
  for (let [key, value] of formData.entries()) {
    if (value instanceof File) {
      console.log(`${key}:`, { name: value.name, size: value.size, type: value.type });
    } else {
      console.log(`${key}:`, value);
    }
  }

  // Get CSRF token
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  // Make API call using fetch
  fetch(`/jobs/checkpoint/${checkpointId}/override`, {
    method: 'POST',
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken || '',
    },
    body: formData,
  })
    .then(response => {
      if (!response.ok) {
        // Try to get validation errors
        return response.json().then(data => {
          throw new Error(data.message || `Server returned ${response.status}`);
        }).catch(() => {
          throw new Error(`Server returned ${response.status}: ${response.statusText}`);
        });
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showOverrideModal.value = false;
        overrideProcessing.value = false;
        const currentJobId = selectedJob.value?.id;
        router.reload({ 
          only: ['schedule'],
          onSuccess: () => {
            // Re-select the same job after refresh
            if (currentJobId) {
              selectedJob.value = props.schedule.find(j => j.id === currentJobId);
            }
          }
        });
      } else {
        overrideProcessing.value = false;
        alert('Failed to update checkpoint: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      overrideProcessing.value = false;
      console.error('Error updating checkpoint:', error);
      alert('Failed to update checkpoint: ' + error.message);
    });
}


</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 14px; flex-wrap: wrap;
}
.page-title { font-size: 22px; font-weight: 700; color: var(--ink); margin: 0; letter-spacing: -0.4px; }
@media (max-width: 640px) {
  .page-title {
    font-size: 20px;
  }
}
.page-sub {
  font-size: 10px; letter-spacing: 1px; text-transform: uppercase;
  color: var(--ink3); font-weight: 700; margin: 0 0 2px;
}
.page-header-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; align-items: center; }
@media (max-width: 640px) {
  .page-header-actions {
    width: 100%;
  }
  .page-header-actions .btn {
    flex: 1;
  }
}

.btn {
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer;
  border: 1px solid transparent;
}
.btn--sm { padding: 6px 12px; }
.btn--primary { background: var(--accent); color: #fff; }
.btn--secondary { background: #fff; border-color: var(--border); color: var(--ink3); }
.btn--secondary:hover { background: var(--panel); color: var(--ink); }

.filter-bar { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
@media (max-width: 640px) {
  .filter-bar {
    overflow-x: auto;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
    -ms-overflow-style: none;
  }
  .filter-bar::-webkit-scrollbar {
    display: none;
  }
}
.filter-pill {
  display: flex; align-items: center; gap: 6px;
  padding: 5px 12px; border-radius: 999px;
  border: none; background: var(--panel);
  font-size: 12px; font-weight: 600; color: var(--ink2); cursor: pointer;
  outline: 1px solid var(--border); font-family: inherit;
}
.filter-pill:hover { background: var(--border); }
.filter-pill--active {
  background: var(--accent); color: #fff;
  outline: none;
}
.filter-count {
  background: rgba(0,0,0,0.1); border-radius: 10px;
  padding: 0 5px; font-size: 10px; font-weight: 700; opacity: 0.75;
}
.filter-pill--active .filter-count { background: rgba(255,255,255,0.25); }

.jobs-layout {
  display: grid; grid-template-columns: 580px 1fr; gap: 12px;
  min-height: 0; flex: 1;
  height: calc(100vh - 240px);
  max-height: 800px;
}
@media (max-width: 1024px) {
  .jobs-layout {
    grid-template-columns: 1fr;
    height: auto;
    max-height: none;
  }
}

.jobs-list-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
  display: flex; flex-direction: column;
  max-height: 100%;
}

.jobs-list-scroll {
  overflow-y: auto;
  overflow-x: hidden;
  flex: 1;
  display: flex; flex-direction: column;
  min-height: 0;
}

.job-list-header {
  display: grid;
  grid-template-columns: 70px 88px 1fr 108px 62px 52px;
  gap: 0 8px;
  padding: 8px 12px 8px 17px;
  border-bottom: 1px solid var(--border);
  background: var(--panel);
  position: sticky;
  top: 0;
  z-index: 1;
  flex-shrink: 0;
}
.job-list-header > div {
  font-size: 10px; font-weight: 700; letter-spacing: 0.6px;
  text-transform: uppercase; color: var(--ink3);
}
.job-list-header .jl-col-eta { text-align: right; }
.job-list-header .jl-col-alerts { text-align: center; }

.job-item {
  display: grid;
  grid-template-columns: 70px 88px 1fr 108px 62px 52px;
  gap: 0 8px;
  padding: 10px 12px 10px 14px;
  cursor: pointer;
  border-bottom: 1px solid var(--border);
  border-left: 3px solid transparent;
  transition: background 0.15s;
  align-items: center;
}
.job-item:hover { background: var(--panel); }
.job-item--active {
  border-left-color: var(--accent);
  background: var(--accent-soft);
}
.job-item:last-child { border-bottom: none; }

.jl-col-job {
  display: flex; flex-direction: column; gap: 2px; min-width: 0;
}
.jl-job-id {
  font-size: 10px; font-weight: 700; color: var(--ink);
  font-family: var(--font-mono, monospace);
}
.jl-job-type {
  font-size: 10px; color: var(--ink3); font-weight: 500;
}
.jl-job-phase {
  font-size: 10px; color: var(--ink3); font-weight: 500;
  text-transform: capitalize;
}

.jl-job-date {
  font-size: 9px;
  font-weight: 600;
  color: var(--ink3);
  background: var(--panel);
  padding: 1px 4px;
  border-radius: 3px;
  font-family: var(--font-mono, monospace);
}

.jl-col-stage {
  display: flex; align-items: center;
}

.jl-col-route {
  display: flex; flex-direction: column; gap: 2px; min-width: 0;
}
.jl-team {
  font-size: 12px; font-weight: 700; color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.jl-route {
  font-size: 10px; color: var(--ink3);
  word-break: break-word;
}

.jl-event-badge {
  font-size: 9px;
  font-weight: 700;
  color: var(--accent);
  background: var(--accent-soft, rgba(99, 102, 241, 0.1));
  padding: 1px 5px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.jl-col-progress {
  display: flex; flex-direction: column; gap: 4px;
}
.jl-progress-bar {
  height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;
}
.jl-progress-fill {
  height: 100%; border-radius: 2px; transition: width 0.3s;
}
.jl-steps {
  font-size: 10px; color: var(--ink3); font-weight: 500;
}

.jl-col-eta {
  display: flex; flex-direction: column; align-items: flex-end; gap: 1px;
}
.jl-eta-time {
  font-size: 13px; font-weight: 700; color: var(--ink);
  font-family: var(--font-mono, monospace);
}
.jl-eta-time--delayed { color: var(--warn); }
.jl-eta-delay {
  font-size: 10px; font-weight: 600; color: var(--warn);
  font-family: var(--font-mono, monospace);
}

.jl-col-alerts {
  display: flex; align-items: center; justify-content: center;
}
.jl-alert-badge {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 11px; font-weight: 700; color: #b45309;
  background: #FEF3C7; padding: 2px 6px; border-radius: 4px;
}
.jl-no-alert {
  font-size: 13px; color: var(--ink4);
}

/* Detail panel */
.job-detail-panel {
  display: flex; flex-direction: column; gap: 14px;
  align-self: start;
}

.detail-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 16px;
}
@media (max-width: 640px) {
  .detail-card {
    padding: 12px;
  }
}

.job-detail-empty {
  background: var(--panel); border: 1px dashed var(--border);
  border-radius: 10px; padding: 60px 40px;
  display: flex; align-items: center; justify-content: center;
  color: var(--ink4); font-size: 13px;
  align-self: start;
}
@media (max-width: 640px) {
  .job-detail-empty {
    padding: 40px 20px;
  }
}

.detail-header-top {
  display: flex; align-items: flex-start; gap: 10px;
  padding-bottom: 14px; border-bottom: 1px solid var(--border);
  margin-bottom: 6px;
}
@media (max-width: 640px) {
  .detail-header-top {
    flex-wrap: wrap;
  }
  .detail-actions {
    width: 100%;
    margin-left: 0;
    justify-content: flex-start;
  }
  .detail-actions .btn {
    flex: 1;
  }
}
.team-badge {
  width: 34px; height: 34px; border-radius: 7px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700; flex-shrink: 0;
  display: inline-flex; align-items: center; justify-content: center;
}
.detail-id {
  font-size: 15px; font-weight: 700; color: var(--ink); letter-spacing: -0.3px;
  line-height: 1.3;
}
@media (max-width: 640px) {
  .detail-id {
    font-size: 14px;
  }
}

.detail-event-badge {
  font-size: 10px;
  font-weight: 700;
  color: var(--accent);
  background: var(--accent-soft, rgba(99, 102, 241, 0.1));
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-date {
  font-size: 11px;
  color: var(--ink3);
  font-weight: 500;
  margin-top: 2px;
}
.detail-subtitle {
  font-size: 12px; color: var(--ink3);
  margin-bottom: 14px;
}
.detail-actions {
  display: flex; gap: 6px; margin-left: auto;
}

.detail-stats {
  display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px;
}
.detail-stats--five {
  grid-template-columns: repeat(5, 1fr);
}
@media (max-width: 640px) {
  .detail-stats {
    grid-template-columns: repeat(2, 1fr);
  }
  .detail-stats--five {
    grid-template-columns: repeat(2, 1fr);
  }
}

.detail-grid {
  display: grid;
  grid-template-columns: 1.2fr 1fr;
  gap: 12px;
}
@media (max-width: 1024px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 640px) {
  .detail-grid {
    gap: 10px;
  }
}

.checkpoint-section {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}

.checkpoint-header {
  padding: 14px 16px; border-bottom: 1px solid var(--border);
  display: flex; justify-content: space-between; align-items: center;
}
@media (max-width: 640px) {
  .checkpoint-header {
    padding: 12px;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
  }
}

.section-title {
  font-size: 14px; font-weight: 700; color: var(--ink);
  margin: 0;
}

.section-kicker {
  font-size: 11px; color: var(--ink3); font-weight: 500;
}

.checkpoint-content {
  padding: 16px;
}
@media (max-width: 640px) {
  .checkpoint-content {
    padding: 12px;
  }
}

.crew-section {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}

.crew-list {
  padding: 0 16px;
}
@media (max-width: 640px) {
  .crew-list {
    padding: 0 12px;
  }
}

.crew-item {
  display: flex; align-items: center; gap: 10px;
  padding: 12px 0; border-bottom: 1px solid var(--border);
}

.crew-item:last-child {
  border-bottom: none;
}

.crew-avatar {
  width: 28px; height: 28px; border-radius: 999px;
  background: var(--accent-soft); color: var(--accent);
  font-size: 11px; font-weight: 700; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}

.crew-info {
  flex: 1;
}

.crew-name {
  font-size: 13px; font-weight: 600; color: var(--ink);
}

.crew-role {
  font-size: 11px; color: var(--ink3);
}

.crew-phone {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 11px;
  color: var(--accent);
  text-decoration: none;
  margin-top: 2px;
  font-family: var(--font-mono, monospace);
  transition: color 0.15s;
}

.crew-phone:hover {
  color: var(--accent-fg, #4338ca);
  text-decoration: underline;
}

.crew-phone svg {
  flex-shrink: 0;
}

/* Override modal */
.btn--dark {
  background: #111827; color: #fff; border-color: #111827;
}
.btn--dark:hover { background: #1f2937; }
.btn--dark:disabled { opacity: 0.4; cursor: not-allowed; }

.override-modal-title-wrap {
  display: flex; flex-direction: column; gap: 2px;
}
.override-privileged-badge {
  font-size: 10px; font-weight: 700; letter-spacing: 0.8px;
  text-transform: uppercase; color: #b45309;
}

.override-body {
  display: flex; flex-direction: column; gap: 16px;
}

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
  border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

.override-select--checkpoints {
  font-family: var(--font-sans, sans-serif);
}

.override-select--checkpoints option {
  padding: 8px;
  line-height: 1.5;
}

/* Note: Limited styling support for option elements across browsers */
.checkpoint-option--done {
  color: #059669;
  font-weight: 500;
}

.override-field-hint { font-size: 11px; color: var(--ink4); }

.override-states {
  display: grid; gap: 8px;
}

.override-state-btn {
  display: flex; flex-direction: column; gap: 3px;
  padding: 10px 12px; border-radius: 8px;
  border: 1.5px solid var(--border); background: var(--surface);
  cursor: pointer; text-align: left; font-family: inherit;
  transition: border-color 0.15s, background 0.15s;
}
.override-state-btn strong { font-size: 13px; font-weight: 700; color: var(--ink); }
.override-state-btn small  { font-size: 10px; color: var(--ink3); line-height: 1.3; }
.override-state-icon { font-size: 14px; margin-bottom: 2px; }

.override-state-btn--done.override-state-btn--active {
  border-color: var(--ok); background: #f0fdf4;
}
.override-state-btn--done.override-state-btn--active .override-state-icon,
.override-state-btn--done.override-state-btn--active strong { color: #166534; }

.override-state-btn--missed.override-state-btn--active {
  border-color: var(--danger); background: #fff7ed;
}
.override-state-btn--missed.override-state-btn--active .override-state-icon,
.override-state-btn--missed.override-state-btn--active strong { color: #c2410c; }

.override-state-btn--skipped.override-state-btn--active {
  border-color: var(--ink3); background: var(--panel);
}

.override-state-btn--success.override-state-btn--active {
  border-color: #059669; background: #D1FAE5;
}
.override-state-btn--success.override-state-btn--active .override-state-icon,
.override-state-btn--success.override-state-btn--active strong { color: #065F46; }

.override-two-col {
  display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}

.override-variance {
  padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--panel);
  font-size: 13px; font-family: var(--font-mono, monospace);
  font-weight: 600; color: var(--ink3);
  min-height: 38px; display: flex; align-items: center;
}
.override-variance.is-late  { color: #c2410c; }
.override-variance.is-early { color: #166534; }

.override-textarea {
  width: 100%; padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  font-size: 13px; color: var(--ink); font-family: inherit;
  outline: none; resize: vertical; box-sizing: border-box;
}
.override-textarea:focus {
  border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
}

.override-notify {
  display: flex; align-items: flex-start; gap: 10px;
  cursor: pointer;
}
.override-notify-check { margin-top: 3px; flex-shrink: 0; accent-color: var(--accent); }
.override-notify-text { display: flex; flex-direction: column; gap: 2px; }
.override-notify-text strong { font-size: 13px; font-weight: 600; color: var(--ink); }
.override-notify-text span   { font-size: 11px; color: var(--ink3); }

.override-footer-inner {
  display: flex; align-items: center; justify-content: space-between; width: 100%;
}
.override-signed-as { font-size: 12px; color: var(--ink3); }
.override-signed-as strong { color: var(--ink); }

/* Photo upload */
.override-file-input {
  width: 100%;
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: var(--surface);
  font-size: 13px;
  color: var(--ink);
  font-family: inherit;
  cursor: pointer;
}

.photo-preview {
  position: relative;
  margin-top: 8px;
  border: 1px solid var(--border);
  border-radius: 7px;
  overflow: hidden;
  max-width: 300px;
}

.photo-preview img {
  width: 100%;
  height: auto;
  display: block;
}

.clear-photo-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
}

.clear-photo-btn:hover {
  background: rgba(0, 0, 0, 0.8);
}

/* Signature pad */
.signature-pad-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.signature-canvas {
  width: 100%;
  height: 150px;
  border: 1px solid var(--border);
  border-radius: 7px;
  background: #FAFAFA;
  cursor: crosshair;
  touch-action: none;
}

.clear-signature-btn {
  align-self: flex-end;
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--surface);
  color: var(--ink2);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}

.clear-signature-btn:hover {
  background: var(--panel);
  border-color: var(--ink3);
}

/* Photo Lightbox */
.photo-lightbox {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.95);
  z-index: 99999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  cursor: zoom-out;
  animation: fadeIn 0.2s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.photo-lightbox-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  cursor: default;
  animation: zoomIn 0.2s ease-out;
}

@keyframes zoomIn {
  from { transform: scale(0.9); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.photo-lightbox-content img {
  max-width: 100%;
  max-height: 90vh;
  width: auto;
  height: auto;
  display: block;
  border-radius: 8px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.photo-lightbox-close {
  position: absolute;
  top: -50px;
  right: 0;
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: rgba(255, 255, 255, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
  backdrop-filter: blur(10px);
}

.photo-lightbox-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

@media (max-width: 640px) {
  .photo-lightbox-close {
    top: 10px;
    right: 10px;
  }
}
</style>
