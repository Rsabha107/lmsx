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
          <flag-icon :code="job.country_code" :fallback="job.flag" />
          <h1 style="font-size: 24px; font-weight: 700; color: var(--ink); letter-spacing: -0.4px; margin: 0;">{{ job.team }}</h1>
          <span v-if="queueJob?.event_name" class="detail-event-badge" :title="queueJob.event_code || ''">{{ queueJob.event_name }}</span>
          <status-pill :tone="statusTone(job.status)" :dot="true" size="sm">
            {{ job.delay ? `+${job.delay}m delayed` : statusLabel(job.status) }}
          </status-pill>
        </div>
        <div v-if="queueJob" class="detail-meta">
          <span v-if="queueJob.kind" class="detail-kind-badge" :class="`detail-kind-badge--${queueJob.kind}`">{{ queueJob.kind }}</span>
          <span v-if="queueJob.date" class="detail-date">{{ formatDateLong(queueJob.date) }}</span>
          <span class="detail-subtitle">
            {{ formatJobFromLocation(queueJob) }} → {{ formatJobToLocation(queueJob) }}<template v-if="queueJob.flight?.flight_number && !queueJob.flight.is_bus"> · ✈ {{ queueJob.flight.flight_number }}</template> · {{ queueJob.vehicle }}<template v-if="queueJob.units?.length"> +{{ queueJob.units.length }}</template> · {{ queueJob.pax }} pax
          </span>
        </div>
      </div>
      <div style="display: flex; gap: 8px;">
        <Button variant="secondary" size="sm" :processing="explaining" @click="explainDelay">
          <template #icon><svg-icon name="ai" :size="14" /></template>
          Explain delay
        </Button>
        <Button v-if="canOverride" variant="primary" size="sm" @click="showOverride = true">Override</Button>
        <template v-if="job.can_change_status">
          <Button v-if="canRevertJob(job)" variant="secondary" size="sm" @click="promptRevert">Mark Scheduled</Button>
          <Button v-else-if="canStartJob(job)" variant="primary" size="sm" @click="promptStart">Start Job</Button>
          <Button v-if="canCancelJob(job)" variant="secondary" size="sm" style="color: var(--danger);" @click="promptCancel">Cancel Job</Button>
          <Button v-if="canReinstateJob(job)" variant="primary" size="sm" @click="promptReinstate">Reinstate</Button>
        </template>
      </div>
    </div>

    <div v-if="job.issues?.length" class="issue-banner">
      <svg-icon name="warn" :size="16" style="flex-shrink:0;" />
      <div>
        <strong>{{ job.issues.length }} open issue{{ job.issues.length === 1 ? '' : 's' }}:</strong>
        <span v-for="(issue, i) in job.issues" :key="i">{{ i ? ' · ' : ' ' }}{{ issue.label }}<template v-if="issue.notes"> ({{ issue.notes }})</template></span>
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
          <Button v-if="canOverride" variant="ghost" size="sm" @click="showOverride = true">Mark checkpoint</Button>
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
                <!-- Skipped: amber ring with arrow -->
                <div v-else-if="cp.status === 'skipped'" style="width: 28px; height: 28px; border-radius: 999px; background: #fffbeb; border: 2px solid #f59e0b; color: #b45309; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 13px; font-weight: 700;">⤼</div>
                <!-- Pending: grey ring -->
                <div v-else style="width: 28px; height: 28px; border-radius: 999px; background: transparent; border: 2px solid var(--border); flex-shrink: 0;"></div>
                <!-- Connector line -->
                <div v-if="i < checkpoints.length - 1" :style="{
                  width: '2px', flex: 1, minHeight: '16px',
                  background: ['done', 'skipped'].includes(cp.status) ? 'var(--ok)' : 'var(--border)',
                }" />
              </div>

              <!-- Content -->
              <div style="flex: 1; padding: 4px 0 14px;">
                <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                  <span style="font-size: 13px; font-weight: 600; color: var(--ink); line-height: 20px;">{{ cp.label }}</span>
                  <!-- Photo badge -->
                  <span v-if="cp.requires_photo"
                        @click="cp.has_photo && cp.photo_url ? openPhotoViewer(cp.photo_url, cp.label) : null"
                        :style="{ 
                          display: 'inline-flex', 
                          alignItems: 'center', 
                          gap: '2px', 
                          fontSize: '10px', 
                          fontWeight: 600, 
                          padding: '1px 5px', 
                          borderRadius: '3px', 
                          background: cp.has_photo ? '#f0fdf4' : '#fffbeb', 
                          color: cp.has_photo ? '#166534' : '#92400e',
                          cursor: cp.has_photo && cp.photo_url ? 'pointer' : 'default',
                          transition: 'all 0.15s'
                        }"
                        :class="{ 'evidence-badge-hover': cp.has_photo && cp.photo_url }">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    {{ cp.has_photo ? 'View' : 'Required' }}
                  </span>
                  <!-- Signature badge -->
                  <span v-if="cp.requires_signature"
                        @click="cp.has_signature && cp.signature_url ? openSignatureViewer(cp.signature_url, cp.label) : null"
                        :style="{ 
                          display: 'inline-flex', 
                          alignItems: 'center', 
                          gap: '2px', 
                          fontSize: '10px', 
                          fontWeight: 600, 
                          padding: '1px 5px', 
                          borderRadius: '3px', 
                          background: cp.has_signature ? '#f0fdf4' : '#fffbeb', 
                          color: cp.has_signature ? '#166534' : '#92400e',
                          cursor: cp.has_signature && cp.signature_url ? 'pointer' : 'default',
                          transition: 'all 0.15s'
                        }"
                        :class="{ 'evidence-badge-hover': cp.has_signature && cp.signature_url }">
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                    {{ cp.has_signature ? 'View' : 'Required' }}
                  </span>
                </div>
                <div v-if="cp.status === 'active'" style="font-size: 12px; color: var(--accent); margin-top: 2px;">Awaiting supervisor confirmation</div>
                <div v-else-if="cp.status === 'done' && (cp.by || cp.was_overridden)" style="font-size: 12px; color: var(--ink3); margin-top: 2px;">
                  {{ cp.was_overridden ? 'Overridden' : 'Completed' }}<template v-if="cp.by"> by {{ cp.by }}</template>
                </div>
                <div v-else-if="cp.status === 'skipped'" style="font-size: 12px; color: #b45309; margin-top: 2px;">
                  Skipped<template v-if="cp.skipped_by"> by {{ cp.skipped_by }}</template><template v-if="cp.skip_reason"> · {{ cp.skip_reason }}</template>
                </div>
              </div>

              <!-- Time display -->
              <div style="padding: 4px 0; text-align: right; font-family: var(--mono); font-size: 12.5px; white-space: nowrap; flex-shrink: 0; line-height: 20px;">
                <template v-if="cp.status === 'done' && cp.actual">
                  <span style="text-decoration: line-through; color: var(--ink4); margin-right: 5px;">{{ cp.time }}</span>
                  <span :style="{ color: cp.delay > 0 ? '#f59e0b' : 'var(--ok)', fontWeight: 600 }">{{ cp.actual }}</span>
                  <div v-if="cp.delay" :style="{ fontSize: '11px', color: cp.delay > 0 ? '#f59e0b' : 'var(--ok)' }">
                    {{ cp.delay > 0 ? `+${cp.delay}m late` : `${-cp.delay}m early` }}
                  </div>
                </template>
                <template v-else-if="cp.status === 'skipped'">
                  <span style="text-decoration: line-through; color: var(--ink4);">{{ cp.time }}</span>
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
        <!-- Job -->
        <div v-if="jobInfo" class="section-card">
          <div class="section-header">
            <div>
              <div style="font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: var(--ink3); font-weight: 700;">{{ job.id }}</div>
              <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;">Job</div>
            </div>
          </div>
          <dl class="info-list">
            <dt>Movement</dt>
            <dd class="info-mono">{{ jobInfo.movement_code || '—' }}</dd>
            <dt>Plan</dt>
            <dd>
              {{ jobInfo.plan_name || '—' }}
              <span v-if="jobInfo.plan_code" class="info-mono info-muted"> · {{ jobInfo.plan_code }}</span>
            </dd>
            <dt>Sequence</dt>
            <dd>{{ jobInfo.sequence || '—' }}</dd>
            <dt>Generated</dt>
            <dd>{{ formatStamp(jobInfo.generated_at) }}</dd>
            <template v-if="jobInfo.dispatched_at">
              <dt>Dispatched</dt>
              <dd>{{ formatStamp(jobInfo.dispatched_at) }}</dd>
            </template>
            <template v-if="jobInfo.started_at">
              <dt>Started</dt>
              <dd>{{ formatStamp(jobInfo.started_at) }}</dd>
            </template>
            <template v-if="jobInfo.completed_at">
              <dt>Completed</dt>
              <dd>{{ formatStamp(jobInfo.completed_at) }}</dd>
            </template>
            <template v-if="jobInfo.notes">
              <dt>Notes</dt>
              <dd class="info-notes">{{ jobInfo.notes }}</dd>
            </template>
          </dl>
        </div>

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
                <div :style="{ fontSize: '11px', marginTop: '4px', fontWeight: 600, color: timingTone(job.dep_actual, job.dep_delay) }">{{ timingText(job.dep_actual, job.dep_delay, 'Not started') }}</div>
              </div>
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Arrival</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); font-family: var(--mono); letter-spacing: -0.4px;">{{ job.arr }}</div>
                <div :style="{ fontSize: '11px', marginTop: '4px', fontWeight: 600, color: timingTone(job.arr_actual, job.arr_delay) }">{{ timingText(job.arr_actual, job.arr_delay, 'Planned') }}</div>
              </div>
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Passengers</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); font-family: var(--mono); letter-spacing: -0.4px;">{{ job.pax }}</div>
              </div>
              <div style="padding: 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px;">
                <div style="font-size: 11px; color: var(--ink3); margin-bottom: 4px;">Vehicle</div>
                <div style="font-size: 18px; font-weight: 700; color: var(--ink); letter-spacing: -0.4px;">{{ job.vehicle }}</div>
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
            <div v-for="crew in crewMembers" :key="`${crew.role}-${crew.name}`" style="display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border);">
              <div style="width: 32px; height: 32px; border-radius: 999px; background: var(--accent-soft); color: var(--accent-fg); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; flex-shrink: 0;">
                {{ crew.initials }}
              </div>
              <div style="flex: 1;">
                <div style="font-size: 13px; font-weight: 600; color: var(--ink);">{{ crew.name }}</div>
                <div style="font-size: 11px; color: var(--ink3);">{{ crew.role }}</div>
                <div v-if="crew.detail" style="font-size: 11px; color: var(--ink3);">{{ crew.detail }}</div>
              </div>
              <a v-if="crew.phone" :href="`tel:${crew.phone}`" class="crew-call" :title="`Call ${crew.name}`">
                <svg-icon name="phone" :size="13" /> {{ crew.phone }}
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Photo Viewer Modal -->
    <teleport to="body">
      <transition name="fade-modal">
        <div v-if="showPhotoViewer" @click="closePhotoViewer" class="evidence-viewer-overlay">
          <div class="evidence-viewer-content" @click.stop>
            <button @click="closePhotoViewer" class="evidence-viewer-close">
              <svg-icon name="x" :size="20" />
            </button>
            <img v-if="currentPhoto" :src="currentPhoto" alt="Checkpoint photo" class="evidence-viewer-image" />
            <div v-if="currentEvidenceName" class="evidence-viewer-label">{{ currentEvidenceName }}</div>
          </div>
        </div>
      </transition>
    </teleport>

    <!-- Signature Viewer Modal -->
    <teleport to="body">
      <transition name="fade-modal">
        <div v-if="showSignatureViewer" @click="closeSignatureViewer" class="evidence-viewer-overlay">
          <div class="evidence-viewer-content" @click.stop>
            <button @click="closeSignatureViewer" class="evidence-viewer-close">
              <svg-icon name="x" :size="20" />
            </button>
            <img v-if="currentSignature" :src="currentSignature" alt="Checkpoint signature" class="evidence-viewer-image" />
            <div v-if="currentEvidenceName" class="evidence-viewer-label">Signature - {{ currentEvidenceName }}</div>
          </div>
        </div>
      </transition>
    </teleport>

    <JobOverrideModal
      v-if="canOverride"
      :show="showOverride"
      :job="queueJob"
      :drivers="drivers"
      :supervisors="supervisors"
      :vehicles="vehicles"
      @close="showOverride = false"
      @saved="onChanged"
    />

    <ConfirmModal
      :show="pendingStatus !== null"
      :title="pendingStatus?.title || ''"
      :message="pendingStatus?.message || ''"
      :confirm-label="pendingStatus?.confirmLabel || 'Confirm'"
      :tone="pendingStatus?.tone || 'primary'"
      :note="pendingStatus?.note"
      :processing="statusChanging"
      @close="pendingStatus = null"
      @confirm="confirmStatus"
    />

    <ConfirmModal
      :show="showStatusError"
      title="Cannot Change Job Status"
      :message="statusErrorMessage"
      confirm-label="Got it"
      hide-cancel
      @close="showStatusError = false"
      @confirm="showStatusError = false"
    />

    <!-- AI Explain Delay Modal -->
    <teleport to="body">
      <transition name="fade-modal">
        <div v-if="showExplainModal" v-dialog="() => (showExplainModal = false)" class="modal-backdrop" @click.self="showExplainModal = false">
          <div class="modal">
            <div class="modal-header">
              <div>
                <div class="modal-eyebrow">DALEEL · {{ job.id }}</div>
                <div class="modal-title">Explain delay</div>
              </div>
              <button class="modal-close-btn" @click="showExplainModal = false">
                <svg-icon name="x" :size="16" />
              </button>
            </div>
            <div class="modal-body">
              <div v-if="explaining" style="color: var(--ink3); font-size: 13.5px; font-style: italic;">Thinking…</div>
              <div v-else style="font-size: 13.5px; color: var(--ink); line-height: 1.5;" :class="{ 'explain-degraded': !explainOk }">
                <markdown-answer :text="explainAnswer" />
              </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end;">
              <Button variant="secondary" size="sm" @click="showExplainModal = false">Close</Button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </app-layout>
</template>

<script setup>
import { useStatusLabels } from '../Composables/useStatusLabels';
import { ref, computed } from 'vue';
import { Link as InertiaLink, router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';
import FlagIcon from '../Components/FlagIcon.vue';
import MarkdownAnswer from '../Components/MarkdownAnswer.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import JobOverrideModal from '../Components/JobOverrideModal.vue';
import { useJobStatusActions, canStartJob, canRevertJob, canCancelJob, canReinstateJob } from '../Composables/useJobStatusActions';
import { formatJobFromLocation, formatJobToLocation } from '../Composables/useJobLocations';

const props = defineProps({
  job: { type: Object, default: () => ({}) },
  checkpoints: { type: Array, default: () => [] },
  crewMembers: { type: Array, default: () => [] },
  queueJob: { type: Object, default: null },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  vehicles: { type: Array, default: () => [] },
});

const showOverride = ref(false);
const canOverride = computed(() => props.job.can_override && props.job.status !== 'cancelled');

function onChanged() {
  showOverride.value = false;
  router.reload();
}

const {
  pending: pendingStatus,
  changing: statusChanging,
  showError: showStatusError,
  errorMessage: statusErrorMessage,
  promptStart,
  promptRevert,
  promptCancel,
  promptReinstate,
  confirm: confirmStatus,
} = useJobStatusActions(() => props.job, onChanged);

const formatDate = (key) => new Date(`${key}T00:00:00`).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });

const jobInfo = computed(() => props.queueJob?.job_info ?? null);

// Parsed as text: Date() reads a bare 'YYYY-MM-DD' as UTC and can show the previous day.
const formatDateLong = (key) => new Date(`${key}T00:00:00`).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });

// Server sends local "YYYY-MM-DD HH:MM"; built by hand so no timezone shift is applied.
function formatStamp(value) {
  const m = value?.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
  if (!m) return '—';
  return `${formatDate(`${m[1]}-${m[2]}-${m[3]}`)} · ${m[4]}`;
}

function timingText(actual, delay, fallback) {
  if (!actual) return fallback;
  if (!delay) return `Actual ${actual} · on time`;
  return `Actual ${actual} · ${delay > 0 ? `+${delay}m late` : `${-delay}m early`}`;
}

const timingTone = (actual, delay) => (!actual ? 'var(--ink3)' : delay > 0 ? '#f59e0b' : 'var(--ok)');

// Photo/Signature viewer state
const showPhotoViewer = ref(false);
const showSignatureViewer = ref(false);
const currentPhoto = ref(null);
const currentSignature = ref(null);
const currentEvidenceName = ref(null);

function openPhotoViewer(photoUrl, checkpointName) {
  if (!photoUrl) return;
  currentPhoto.value = photoUrl;
  currentEvidenceName.value = checkpointName;
  showPhotoViewer.value = true;
}

function closePhotoViewer() {
  showPhotoViewer.value = false;
  currentPhoto.value = null;
  currentEvidenceName.value = null;
}

function openSignatureViewer(signatureUrl, checkpointName) {
  if (!signatureUrl) return;
  currentSignature.value = signatureUrl;
  currentEvidenceName.value = checkpointName;
  showSignatureViewer.value = true;
}

function closeSignatureViewer() {
  showSignatureViewer.value = false;
  currentSignature.value = null;
  currentEvidenceName.value = null;
}

const completedCount = computed(() => 
  props.checkpoints.filter(cp => cp.status === 'done').length
);

// This page shows the job's stored status, so it needs every stored value's tone.
const statusMap = {
  'pending': { tone: 'info' },
  'dispatched': { tone: 'primary' },
  'in-progress': { tone: 'live', label: 'In Progress' },
  'completed': { tone: 'ok' },
  'cancelled': { tone: 'neutral' },
  'scheduled': { tone: 'primary', label: 'Scheduled' },
  'delayed': { tone: 'warn', label: 'Delayed' },
  'done': { tone: 'ok', label: 'Done' },
};

function statusTone(s) {
  return statusMap[s]?.tone ?? 'neutral';
}

const { statusLabel: sharedStatusLabel } = useStatusLabels();
function statusLabel(s) {
  return sharedStatusLabel(s, statusMap[s]?.label);
}

const showExplainModal = ref(false);
const explaining = ref(false);
const explainOk = ref(true);
const explainAnswer = ref('');

async function explainDelay() {
  showExplainModal.value = true;
  explaining.value = true;
  explainOk.value = true;
  explainAnswer.value = '';

  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  try {
    const response = await fetch('/ai/query', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken || '',
      },
      body: JSON.stringify({ question: `Explain the delay for job ${props.job.id}.` }),
    });

    const data = await response.json();
    explainOk.value = data.ok !== false;
    explainAnswer.value = data.ok ? data.answer : (data.message || 'Something went wrong.');
  } catch (e) {
    explainOk.value = false;
    explainAnswer.value = 'Could not reach Daleel. Please try again.';
  } finally {
    explaining.value = false;
  }
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

.issue-banner {
  display: flex; align-items: flex-start; gap: 8px; margin-bottom: 16px; padding: 10px 14px;
  border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; font-size: 13px;
}

.crew-call {
  display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600;
  color: var(--accent); text-decoration: none; white-space: nowrap;
}
.crew-call:hover { text-decoration: underline; }

.team-badge-lg {
  min-width: 42px;
  height: 42px;
  padding: 0 8px;
  white-space: nowrap;
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

.info-list {
  display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px;
  margin: 0; padding: 16px 20px; font-size: 12.5px;
}
.info-list dt { color: var(--ink3); font-weight: 500; }
.info-list dd { margin: 0; color: var(--ink); min-width: 0; overflow-wrap: anywhere; }
.info-mono { font-family: var(--mono, ui-monospace, monospace); font-size: 11.5px; }
.info-muted { color: var(--ink3); }
.info-notes { white-space: pre-wrap; }

.detail-meta { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.detail-event-badge {
  font-size: 10px; font-weight: 700; color: var(--accent); background: var(--accent-soft, var(--accent-ring));
  padding: 2px 8px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;
}
.detail-kind-badge {
  font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px;
  text-transform: capitalize; letter-spacing: 0.3px; flex-shrink: 0;
}
.detail-kind-badge--arrival { background: var(--ok-soft); color: var(--ok); }
.detail-kind-badge--departure { background: var(--danger-soft); color: var(--danger); }
.detail-kind-badge--transfer { background: var(--accent-soft); color: var(--accent-fg); }
.detail-kind-badge--match { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
.detail-kind-badge--training { background: #ede9fe; color: #6d28d9; }
.detail-kind-badge--daily_ops { background: var(--panel); color: var(--ink3); }
.detail-date { font-size: 11px; color: var(--ink3); font-weight: 600; white-space: nowrap; }
.detail-subtitle { font-size: 13px; color: var(--ink3); }

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

.explain-degraded {
  color: var(--warn);
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
  box-shadow: 0 0 0 3px var(--accent-ring);
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
  grid-template-columns: repeat(2, 1fr);
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

/* Evidence viewer (photo/signature) */
.evidence-viewer-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.92);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
  cursor: zoom-out;
}

.evidence-viewer-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  cursor: default;
  animation: zoomIn 0.2s ease-out;
}

@keyframes zoomIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.evidence-viewer-image {
  max-width: 100%;
  max-height: 85vh;
  width: auto;
  height: auto;
  display: block;
  border-radius: 8px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
}

.evidence-viewer-close {
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

.evidence-viewer-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.evidence-viewer-label {
  position: absolute;
  bottom: -40px;
  left: 0;
  right: 0;
  text-align: center;
  color: white;
  font-size: 13px;
  font-weight: 600;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.4);
}

.evidence-badge-hover:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

@media (max-width: 640px) {
  .evidence-viewer-close {
    top: 10px;
    right: 10px;
  }
  
  .evidence-viewer-label {
    bottom: 10px;
  }
}
</style>
