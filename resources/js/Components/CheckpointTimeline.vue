<template>
  <div>
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <div style="font-size: 10px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--ink3);">
        {{ title }}
        <span v-if="checkpoints?.length"> · {{ completedCount }}/{{ checkpoints.length }}</span>
        <span v-if="skippedCount" style="color: var(--ink3);"> · {{ skippedCount }} skipped</span>
      </div>
      <div v-if="hasMobileUpdates"
           style="display: flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; color: var(--accent);">
        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><circle cx="12" cy="18" r="0.5" fill="currentColor"/></svg>
        Updated via mobile
      </div>
    </div>

    <!-- Photo Viewer Modal -->
    <div v-if="showPhotoModal" @click="closePhotoModal" class="photo-modal-overlay">
      <div class="photo-modal-content" @click.stop>
        <button @click="closePhotoModal" class="photo-modal-close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
        <img v-if="currentPhoto" :src="currentPhoto" alt="Checkpoint evidence" class="photo-modal-image" />
        <div v-if="currentCheckpointName" class="photo-modal-label">{{ currentCheckpointName }}</div>
      </div>
    </div>

    <!-- Signature Viewer Modal -->
    <div v-if="showSignatureModal" @click="closeSignatureModal" class="photo-modal-overlay">
      <div class="photo-modal-content" @click.stop>
        <button @click="closeSignatureModal" class="photo-modal-close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
        <img v-if="currentSignature" :src="currentSignature" alt="Checkpoint signature" class="photo-modal-image" />
        <div v-if="currentCheckpointName" class="photo-modal-label">Signature - {{ currentCheckpointName }}</div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-if="!checkpoints?.length" style="padding: 24px; text-align: center; border: 1px dashed var(--border); border-radius: 8px;">
      <div style="font-size: 12px; color: var(--ink3);">{{ emptyMessage }}</div>
    </div>

    <!-- Timeline -->
    <div v-else>
      <div
        v-for="(cp, idx) in checkpoints"
        :key="cp.id ?? idx"
        style="display: flex; gap: 10px;"
      >
        <!-- Spine -->
        <div style="display: flex; flex-direction: column; align-items: center; flex-shrink: 0;">
          <div :class="['dc-cp-dot', `dc-cp-dot--${getVisualState(cp, idx)}`]">
            <svg v-if="getVisualState(cp, idx) === 'done'" xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 12 12" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="2 6 5 9 10 3"/></svg>
            <span v-else-if="getVisualState(cp, idx) === 'skipped'" style="font-size: 10px; font-weight: 700; color: #fff; line-height: 1;">↷</span>
            <div v-else-if="getVisualState(cp, idx) === 'active'" style="width: 6px; height: 6px; border-radius: 999px; background: var(--accent);"></div>
          </div>
          <div
            v-if="idx < checkpoints.length - 1"
            class="dc-cp-line"
            :style="{ background: isSettled(cp) ? (getVisualState(cp, idx) === 'skipped' ? 'var(--border-strong, #d0d5df)' : 'var(--ok)') : 'var(--borderStrong, #d0d5df)' }"
          ></div>
        </div>

        <!-- Content -->
        <div :style="{ flex: 1, paddingBottom: idx < checkpoints.length - 1 ? '14px' : 0 }">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
            <div>
              <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                <span style="font-size: 13px; font-weight: 600;"
                      :style="{
                        color: getVisualState(cp, idx) === 'pending' ? 'var(--ink3)' : 'var(--ink)',
                        textDecoration: getVisualState(cp, idx) === 'skipped' ? 'line-through' : 'none',
                      }">
                  {{ cp.name }}
                </span>
                <span v-if="getVisualState(cp, idx) === 'skipped'"
                      style="font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 3px; background: var(--panel); color: var(--ink3); border: 1px solid var(--border);">
                  SKIPPED
                </span>
                <!-- Photo badge -->
                <span v-if="cp.requires_photo"
                      @click="cp.has_photo && cp.photo_url ? openPhotoModal(cp.photo_url, cp.name) : null"
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
                        transition: 'transform 0.15s'
                      }"
                      :class="{ 'evidence-badge-clickable': cp.has_photo && cp.photo_url }">
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  {{ cp.has_photo ? 'Done' : 'Required' }}
                </span>
                <!-- Signature badge -->
                <span v-if="cp.requires_signature"
                      @click="cp.has_signature && cp.signature_url ? openSignatureModal(cp.signature_url, cp.name) : null"
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
                        transition: 'transform 0.15s'
                      }"
                      :class="{ 'evidence-badge-clickable': cp.has_signature && cp.signature_url }">
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                  {{ cp.has_signature ? 'Signed' : 'Required' }}
                </span>
              </div>
              <div v-if="completedBy(cp)" style="font-size: 10px; color: var(--ink3); margin-top: 1px;">
                {{ completedBy(cp) }}{{ cp.completion_method === 'mobile' ? ' (mobile)' : '' }}
              </div>
              <div v-if="getVisualState(cp, idx) === 'skipped' && skipDetail(cp)"
                   style="font-size: 10px; color: var(--ink3); margin-top: 1px;">
                {{ skipDetail(cp) }}
              </div>
            </div>

            <!-- Time column -->
            <div style="flex-shrink: 0; padding-top: 2px; display: flex; flex-direction: column; align-items: flex-end; gap: 2px;">
              <span v-if="getVisualState(cp, idx) === 'active'"
                    style="font-size: 11px; font-weight: 700; color: var(--accent); letter-spacing: 0.5px;">
                AWAITING
              </span>
              <span style="font-size: 12px; font-family: var(--mono); font-weight: 500; display: flex; align-items: center; gap: 4px;">
                <span v-if="cp.scheduled_at && getVisualState(cp, idx) === 'done'"
                      style="text-decoration: line-through; color: var(--ink4);">{{ formatTime(cp.scheduled_at) }}</span>
                <span v-if="cp.completed_at" :style="{ color: getTimeColor(cp), fontWeight: '600' }">{{ formatTime(cp.completed_at) }}</span>
                <span v-else-if="cp.scheduled_at && getVisualState(cp, idx) !== 'done'"
                      style="color: var(--ink4);">{{ formatTime(cp.scheduled_at) }}</span>
                <span v-else-if="!cp.scheduled_at && cp.estimated_at"
                      style="color: var(--ink4); font-style: italic;"
                      title="Estimated — not yet scheduled">~{{ formatTime(cp.estimated_at) }}</span>
              </span>
              <span v-if="cp.requires_baggage_count && bagCountLine(cp)"
                    style="font-size: 10px; color: var(--ink4); font-family: var(--mono); white-space: nowrap;">
                {{ bagCountLine(cp) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  title:        { type: String, default: 'Checkpoints' },
  emptyMessage: { type: String, default: 'No checkpoints defined' },
});

// Photo/Signature viewer state
const showPhotoModal = ref(false);
const showSignatureModal = ref(false);
const currentPhoto = ref(null);
const currentSignature = ref(null);
const currentCheckpointName = ref(null);

function openPhotoModal(photoUrl, checkpointName) {
  if (!photoUrl) return;
  currentPhoto.value = photoUrl;
  currentCheckpointName.value = checkpointName;
  showPhotoModal.value = true;
}

function closePhotoModal() {
  showPhotoModal.value = false;
  currentPhoto.value = null;
  currentCheckpointName.value = null;
}

function openSignatureModal(signatureUrl, checkpointName) {
  if (!signatureUrl) return;
  currentSignature.value = signatureUrl;
  currentCheckpointName.value = checkpointName;
  showSignatureModal.value = true;
}

function closeSignatureModal() {
  showSignatureModal.value = false;
  currentSignature.value = null;
  currentCheckpointName.value = null;
}

function getCheckpointState(cp) {
  if (!cp) return 'pending';
  if (cp.state === 'skipped' || cp.status === 'skipped') return 'skipped';
  if (cp.state === 'completed' || cp.state === 'done' || cp.completed_at) return 'done';
  if (cp.state === 'started' || cp.started_at) return 'active';
  return 'pending';
}

/** Done or skipped - either way the step is settled and not outstanding. */
function isSettled(cp) {
  return ['done', 'skipped'].includes(getCheckpointState(cp));
}

const completedCount = computed(() =>
  props.checkpoints.filter(cp => getCheckpointState(cp) === 'done').length
);

const skippedCount = computed(() =>
  props.checkpoints.filter(cp => getCheckpointState(cp) === 'skipped').length
);

const nextCheckpointIdx = computed(() => {
  const hasProgress = props.checkpoints.some(isSettled);
  if (!hasProgress) return -1;
  return props.checkpoints.findIndex(cp => !isSettled(cp));
});

function getVisualState(cp, idx) {
  const state = getCheckpointState(cp);
  if (state === 'done' || state === 'skipped') return state;
  if (idx === nextCheckpointIdx.value) return 'active';
  return 'pending';
}

function skipDetail(cp) {
  const parts = [];
  if (cp.skip_reason) parts.push(cp.skip_reason);
  if (cp.skipped_by) parts.push(`by ${cp.skipped_by}`);
  if (cp.skipped_at) parts.push(`at ${cp.skipped_at}`);
  return parts.join(' · ');
}

function completedBy(cp) {
  return cp.by || cp.completed_by || null;
}

function bagCountLine(cp) {
  const parts = [];
  if (cp.planned_bags != null) parts.push(`Planned ${cp.planned_bags}`);
  if (cp.bags_loaded != null) parts.push(`Actual ${cp.bags_loaded}`);
  if (cp.food_bags != null) parts.push(`Food ${cp.food_bags}`);
  if (cp.oversized_pieces != null) parts.push(`Oversized ${cp.oversized_pieces}`);
  return parts.join(' · ');
}

function formatTime(value) {
  if (!value) return '';
  const m = String(value).match(/(\d{2}):(\d{2})/);
  if (m) return `${m[1]}:${m[2]}`;
  const d = new Date(value);
  if (isNaN(d)) return value;
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function getTimeColor(cp) {
  // Use timestamps for accurate comparison (handles dates and midnight crossover)
  if (cp.completed_ts && cp.scheduled_ts) {
    return cp.completed_ts > cp.scheduled_ts ? '#d97706' : '#16a34a';
  }
  
  // Fallback for old data without timestamps
  if (!cp.completed_at || !cp.scheduled_at) return 'var(--ink3)';
  
  // Parse times for comparison (legacy fallback - has midnight crossover bug)
  const parseTime = (value) => {
    const m = String(value).match(/(\d{2}):(\d{2})/);
    if (m) return parseInt(m[1]) * 60 + parseInt(m[2]);
    const d = new Date(value);
    if (!isNaN(d)) return d.getHours() * 60 + d.getMinutes();
    return null;
  };
  
  const actualMinutes = parseTime(cp.completed_at);
  const estimateMinutes = parseTime(cp.scheduled_at);
  
  if (actualMinutes === null || estimateMinutes === null) return 'var(--ink3)';
  
  // Late: amber, On-time or early: green
  return actualMinutes > estimateMinutes ? '#d97706' : '#16a34a';
}

const hasMobileUpdates = computed(() =>
  props.checkpoints.some(cp => cp.completion_method === 'mobile')
);
</script>

<style scoped>
.dc-cp-dot {
  width: 18px; height: 18px; border-radius: 999px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.dc-cp-dot--done    { background: var(--ok, #22a06b); border: 2px solid var(--ok, #22a06b); }
.dc-cp-dot--active  { background: #fff; border: 2px solid var(--accent, #4f46e5); box-shadow: 0 0 0 4px var(--accent-soft, rgba(79,70,229,0.12)); }
.dc-cp-dot--pending { background: var(--panel); border: 2px solid var(--borderStrong, #d0d5df); }
.dc-cp-dot--skipped { background: var(--ink4); border: 2px solid var(--ink4); }
.dc-cp-line { width: 2px; flex: 1; min-height: 16px; }

/* Evidence badge hover effect */
.evidence-badge-clickable:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Photo viewer modal */
.photo-modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.85);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
  backdrop-filter: blur(4px);
  animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

.photo-modal-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  animation: scaleIn 0.2s ease;
}

@keyframes scaleIn {
  from {
    transform: scale(0.95);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.photo-modal-close {
  position: absolute;
  top: 12px;
  right: 12px;
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.6);
  border: none;
  color: white;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: background 0.15s;
  z-index: 10;
}

.photo-modal-close:hover {
  background: rgba(0, 0, 0, 0.8);
}

.photo-modal-image {
  max-width: 100%;
  max-height: calc(90vh - 100px);
  border-radius: 8px;
  object-fit: contain;
}

.photo-modal-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink, #111827);
  text-align: center;
  padding: 8px 16px;
  background: var(--panel, #f9fafb);
  border-radius: 6px;
}
</style>
