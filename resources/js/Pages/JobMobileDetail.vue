<template>
  <app-layout>
    <div class="mobile-detail-container">
      <!-- Back Button -->
      <div class="mobile-back">
        <inertia-link href="/jobs/mobile" class="back-btn">
          <svg-icon name="chevron" style="transform:rotate(180deg)" :size="20" />
        </inertia-link>
      </div>

      <!-- Route Header -->
      <div class="route-header">
        <div class="route-header-content">
          <div class="team-badge-md">{{ job.code }}</div>
          <div class="route-header-info">
            <div class="team-name">{{ job.team }}</div>
            <div class="job-id">
              {{ job.id }}
              <span v-if="job.source" :class="['source-badge', `source-badge--${job.source}`]">
                {{ job.source === 'database' ? 'DB' : 'DEMO' }}
              </span>
            </div>
          </div>
          <status-pill tone="ok" :dot="true" size="sm">
            {{ statusLabel(job.status) }}
          </status-pill>
        </div>

        <!-- From/To Section -->
        <div class="route-compact">
          <svg-icon name="plane" :size="14" style="color: var(--ink3);" />
          <span>{{ job.from }}</span>
          <span style="color: var(--ink4); margin: 0 4px;">→</span>
          <span>{{ job.to }}</span>
        </div>

        <!-- Quick Stats -->
        <div class="quick-stats">
          <div class="stat-item">
            <svg-icon name="clock" :size="14" />
            <span class="mono">{{ job.dep }} – {{ job.arr }}</span>
          </div>
          <div class="stat-item">
            <svg-icon name="bus" :size="14" />
            <span>{{ job.vehicle }}</span>
          </div>
          <div class="stat-item">
            <svg-icon name="user" :size="14" />
            <span class="mono">{{ job.pax }} pax</span>
          </div>
        </div>

        <div v-if="job.delay" class="delay-alert">
          <svg-icon name="warn" :size="14" />
          <span>Delayed +{{ job.delay }}m</span>
        </div>
      </div>

      <!-- Checkpoints Section -->
      <div class="checkpoints-section">
        <div class="section-title">
          <div class="section-label">CHECKPOINT TIMELINE</div>
          <div class="section-count">{{ completedCount }} of {{ checkpoints.length }} complete</div>
        </div>

        <div class="checkpoint-list">
          <div
            v-for="(cp, i) in checkpoints"
            :key="cp.id"
            :class="['checkpoint-item', i === checkpoints.length - 1 ? 'checkpoint-item--last' : '', !isCheckpointEnabled(i) ? 'checkpoint-item--disabled' : '']"
            @click="isCheckpointEnabled(i) && cp.status !== 'done' ? openCheckpointModal(cp) : null"
            :style="{ cursor: isCheckpointEnabled(i) && cp.status !== 'done' ? 'pointer' : cp.status === 'done' ? 'default' : 'not-allowed' }"
          >
            <!-- Connector Line -->
            <div v-if="i !== checkpoints.length - 1" :class="['checkpoint-connector', `checkpoint-connector--${cp.status}`]"></div>
            
            <!-- Icon -->
            <div :class="['checkpoint-icon', `checkpoint-icon--${cp.status}`]">
              <svg-icon v-if="cp.status === 'done'" name="check" :size="12" />
              <svg-icon v-else-if="cp.status === 'active'" name="clock" :size="12" />
              <div v-else class="checkpoint-dot"></div>
            </div>

            <!-- Content -->
            <div class="checkpoint-content">
              <div class="checkpoint-label">
                {{ cp.label }}
                <span v-if="cp.requires_photo" class="checkpoint-req-badge checkpoint-req-badge--photo">📷</span>
                <span v-if="cp.requires_signature" class="checkpoint-req-badge checkpoint-req-badge--sign">✍</span>
              </div>
              <div class="checkpoint-time">
                <span class="time-planned">Est. {{ cp.time }}</span>
                <span v-if="cp.actual" :class="['time-actual', isDelayed(cp.time, cp.actual) ? 'time-actual--delayed' : '']">Actual {{ cp.actual }}</span>
                <span v-else-if="cp.status === 'active'" class="time-progress">Awaiting supervisor confirmation</span>
              </div>
            </div>

            <!-- Status Badge -->
            <div :class="['checkpoint-badge', `checkpoint-badge--${cp.status}`]">
              <svg-icon v-if="cp.status === 'done'" name="check" :size="10" />
            </div>
          </div>
        </div>
      </div>

      <!-- Actions Section -->
      <div class="actions-section">
        <Button variant="secondary" size="sm" style="flex: 1;">
          <template #icon><svg-icon name="phone" :size="14" /></template>
          Contact
        </Button>
        <Button variant="primary" size="sm" style="flex: 1;">
          <template #icon><svg-icon name="bell" :size="14" /></template>
          Notify
        </Button>
      </div>
    </div>

    <!-- Checkpoint Confirmation Modal -->
    <teleport to="body">
      <transition name="slide-up">
        <div v-if="showCheckpointModal" class="mobile-modal">
          <div class="mobile-modal-overlay" @click="showCheckpointModal = false"></div>
          <div class="mobile-modal-content">
            <div class="mobile-modal-handle"></div>
            
            <div class="modal-header">
              <div>
                <div class="modal-title">Confirm {{ selectedCheckpoint.label }}</div>
                <div class="modal-subtitle">{{ job.id }} · {{ job.team }}</div>
              </div>
              <button class="modal-close-btn" @click="showCheckpointModal = false">
                <svg-icon name="x" :size="20" />
              </button>
            </div>

            <div class="form-section">
              <div class="form-field">
                <label class="form-label">Actual Time</label>
                <div class="time-input-wrapper">
                  <input
                    v-model="actualTime"
                    type="time"
                    class="form-input"
                    :placeholder="selectedCheckpoint?.time"
                  />
                  <button class="now-btn" @click="setCurrentTime" type="button">
                    Now
                  </button>
                </div>
              </div>

              <div class="form-field">
                <label class="form-label">Note (Optional)</label>
                <textarea
                  v-model="checkpointNote"
                  class="form-textarea"
                  rows="3"
                  placeholder="Add any relevant notes..."
                ></textarea>
              </div>

              <div v-if="selectedCheckpoint?.requires_photo" class="form-field">
                <label class="form-label">Photo Required 📷</label>
                <div class="photo-capture-wrapper">
                  <div v-if="photoPreview" class="photo-preview">
                    <img :src="photoPreview" alt="Captured photo" />
                    <button class="remove-photo-btn" @click="removePhoto" type="button">
                      ✕
                    </button>
                  </div>
                  <div v-else class="photo-buttons">
                    <input
                      ref="photoInput"
                      type="file"
                      accept="image/*"
                      capture="environment"
                      @change="handlePhotoCapture"
                      style="display: none;"
                    />
                    <button class="photo-btn photo-btn--camera" @click="openCamera" type="button">
                      📷 Take Photo
                    </button>
                    <button class="photo-btn photo-btn--upload" @click="openFileUpload" type="button">
                      📁 Upload
                    </button>
                  </div>
                </div>
              </div>

              <div v-if="selectedCheckpoint?.requires_signature" class="form-field">
                <label class="form-label">Signature Required ✍</label>
                <div class="signature-pad-wrapper">
                  <canvas
                    ref="signatureCanvas"
                    class="signature-canvas"
                    width="320"
                    height="150"
                    @mousedown="startDrawing"
                    @mousemove="draw"
                    @mouseup="stopDrawing"
                    @mouseleave="stopDrawing"
                    @touchstart.prevent="startDrawing"
                    @touchmove.prevent="draw"
                    @touchend.prevent="stopDrawing"
                  ></canvas>
                  <button class="clear-signature-btn" @click="clearSignature" type="button">
                    Clear
                  </button>
                </div>
              </div>
            </div>

            <div class="modal-actions">
              <Button variant="secondary" size="sm" style="flex: 1;" @click="showCheckpointModal = false" :disabled="processing">
                Cancel
              </Button>
              <Button variant="primary" size="sm" style="flex: 1;" @click="confirmCheckpoint" :disabled="processing" :processing="processing">
                <template #icon><svg-icon name="check" :size="14" /></template>
                {{ processing ? 'Processing...' : 'Confirm' }}
              </Button>
            </div>
          </div>
        </div>
      </transition>
    </teleport>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link as InertiaLink, router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';

const props = defineProps({
  job: { type: Object, default: () => ({}) },
  checkpoints: { type: Array, default: () => [] },
  mockJob: { type: Object, default: null },
  mockCheckpoints: { type: Array, default: () => [] },
});

const showCheckpointModal = ref(false);
const selectedCheckpoint = ref(null);
const actualTime = ref('');
const checkpointNote = ref('');
const signatureCanvas = ref(null);
const signatureData = ref(null);
const photoInput = ref(null);
const photoData = ref(null);
const photoPreview = ref(null);
const processing = ref(false);
let isDrawing = false;

const completedCount = computed(() => 
  props.checkpoints.filter(cp => cp.status === 'done').length
);

function isCheckpointEnabled(index) {
  // Checkpoint is enabled if all previous checkpoints are done
  for (let i = 0; i < index; i++) {
    if (props.checkpoints[i].status !== 'done') {
      return false;
    }
  }
  return true;
}

function isDelayed(estimatedTime, actualTime) {
  // Parse time strings (format: HH:MM)
  const parseTime = (timeStr) => {
    const [hours, minutes] = timeStr.split(':').map(Number);
    return hours * 60 + minutes;
  };
  
  const estMinutes = parseTime(estimatedTime);
  const actMinutes = parseTime(actualTime);
  const diff = Math.abs(actMinutes - estMinutes);
  
  return diff > 10;
}

function openCheckpointModal(checkpoint) {
  selectedCheckpoint.value = checkpoint;
  actualTime.value = checkpoint.actual || '';
  checkpointNote.value = '';
  signatureData.value = null;
  photoData.value = null;
  photoPreview.value = null;
  showCheckpointModal.value = true;
  
  // Initialize signature canvas after modal is shown
  if (checkpoint.requires_signature) {
    setTimeout(() => {
      initSignatureCanvas();
    }, 100);
  }
}

function setCurrentTime() {
  const now = new Date();
  actualTime.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
}

function initSignatureCanvas() {
  if (!signatureCanvas.value) return;
  const canvas = signatureCanvas.value;
  const ctx = canvas.getContext('2d');
  ctx.strokeStyle = '#1e293b';
  ctx.lineWidth = 2;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';
}

function getCoordinates(event) {
  const canvas = signatureCanvas.value;
  if (!canvas) return { x: 0, y: 0 };
  
  const rect = canvas.getBoundingClientRect();
  const scaleX = canvas.width / rect.width;
  const scaleY = canvas.height / rect.height;
  
  if (event.touches && event.touches.length > 0) {
    return {
      x: (event.touches[0].clientX - rect.left) * scaleX,
      y: (event.touches[0].clientY - rect.top) * scaleY
    };
  }
  
  return {
    x: (event.clientX - rect.left) * scaleX,
    y: (event.clientY - rect.top) * scaleY
  };
}

function startDrawing(event) {
  if (!signatureCanvas.value) return;
  isDrawing = true;
  const coords = getCoordinates(event);
  const ctx = signatureCanvas.value.getContext('2d');
  ctx.beginPath();
  ctx.moveTo(coords.x, coords.y);
}

function draw(event) {
  if (!isDrawing || !signatureCanvas.value) return;
  const coords = getCoordinates(event);
  const ctx = signatureCanvas.value.getContext('2d');
  ctx.lineTo(coords.x, coords.y);
  ctx.stroke();
}

function stopDrawing() {
  if (!isDrawing) return;
  isDrawing = false;
  if (signatureCanvas.value) {
    signatureData.value = signatureCanvas.value.toDataURL('image/png');
  }
}

function clearSignature() {
  if (!signatureCanvas.value) return;
  const canvas = signatureCanvas.value;
  const ctx = canvas.getContext('2d');
  ctx.clearRect(0, 0, canvas.width, canvas.height);
  signatureData.value = null;
}

function openCamera() {
  if (photoInput.value) {
    photoInput.value.setAttribute('capture', 'environment');
    photoInput.value.click();
  }
}

function openFileUpload() {
  if (photoInput.value) {
    photoInput.value.removeAttribute('capture');
    photoInput.value.click();
  }
}

function handlePhotoCapture(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  
  // Validate file type
  if (!file.type.startsWith('image/')) {
    alert('Please select an image file.');
    return;
  }
  
  // Validate file size (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    alert('Photo size must be less than 5MB.');
    return;
  }
  
  const reader = new FileReader();
  reader.onload = (e) => {
    photoData.value = e.target.result;
    photoPreview.value = e.target.result;
  };
  reader.readAsDataURL(file);
}

function removePhoto() {
  photoData.value = null;
  photoPreview.value = null;
  if (photoInput.value) {
    photoInput.value.value = '';
  }
}

function confirmCheckpoint() {
  if (!selectedCheckpoint.value || processing.value) return;
  
  // Validate signature if required
  if (selectedCheckpoint.value.requires_signature && !signatureData.value) {
    alert('Please provide a signature before confirming.');
    return;
  }
  
  // Validate photo if required
  if (selectedCheckpoint.value.requires_photo && !photoData.value) {
    alert('Please take or upload a photo before confirming.');
    return;
  }

  processing.value = true;
  const checkpointId = selectedCheckpoint.value.id;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

  fetch(`/jobs/checkpoint/${checkpointId}/complete`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken || '',
    },
    body: JSON.stringify({
      actual_time: actualTime.value,
      notes: checkpointNote.value,
      signature: signatureData.value,
      photo: photoData.value,
    }),
  })
    .then(response => {
      if (!response.ok) {
        return response.json().then(data => {
          throw new Error(data.message || `HTTP ${response.status}: ${response.statusText}`);
        }).catch(jsonError => {
          throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        });
      }
      return response.json();
    })
    .then(data => {
      if (data.success) {
        showCheckpointModal.value = false;
        actualTime.value = '';
        checkpointNote.value = '';
        signatureData.value = null;
        photoData.value = null;
        photoPreview.value = null;
        // Reload the page to get fresh data
        router.reload({ only: ['job', 'checkpoints'] });
      } else {
        processing.value = false;
        alert('Failed to complete checkpoint: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      processing.value = false;
      console.error('Failed to complete checkpoint:', error);
      alert('Error: ' + error.message);
    });
}

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
</script>

<style scoped>
.mobile-detail-container {
  max-width: 480px;
  margin: 0 auto;
  padding: 16px;
  padding-bottom: 24px;
}

/* Back Button */
.mobile-back {
  margin-bottom: 16px;
}

.back-btn {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--panel);
  border: 2px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--ink2);
  transition: all 0.2s;
  text-decoration: none;
}

.back-btn:active {
  background: var(--border);
  transform: scale(0.95);
}

/* Route Header */
.route-header {
  background: var(--surface);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.route-header-content {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}

.team-badge-md {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.route-header-info {
  flex: 1;
  min-width: 0;
}

.team-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.job-id {
  font-size: 11px;
  font-family: var(--mono);
  color: var(--ink3);
  display: flex;
  align-items: center;
  gap: 6px;
}

.source-badge {
  font-size: 8px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.5px;
  font-family: var(--font-sans, sans-serif);
}

.source-badge--database {
  background: #dcfce7;
  color: #166534;
}

.source-badge--mock {
  background: #fef3c7;
  color: #92400e;
}

/* Route Compact */
.route-compact {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--ink2);
  margin-bottom: 12px;
  padding: 10px;
  background: var(--panel);
  border-radius: 8px;
}

/* Quick Stats */
.quick-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ink3);
}

.delay-alert {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding: 8px 10px;
  background: var(--warn-soft);
  color: var(--warn);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

/* Checkpoints Section */
.checkpoints-section {
  background: var(--surface);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.section-label {
  font-size: 10px;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--ink3);
  font-weight: 700;
}

.section-count {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink2);
  margin-left: auto;
}

.checkpoint-list {
  display: flex;
  flex-direction: column;
  position: relative;
  padding-left: 4px;
}

.checkpoint-item {
  display: flex;
  gap: 14px;
  position: relative;
  padding-bottom: 14px;
  transition: background 0.2s;
}

.checkpoint-item--last {
  padding-bottom: 0;
}

.checkpoint-item:active {
  background: var(--panel);
  margin: 0 -8px 0 -12px;
  padding-left: 16px;
  padding-right: 8px;
  padding-bottom: 14px;
  border-radius: 8px;
}

.checkpoint-item--last:active {
  padding-bottom: 0;
}

.checkpoint-item--disabled {
  opacity: 0.4;
  cursor: not-allowed !important;
}

.checkpoint-item--disabled:active {
  background: transparent;
  margin: 0;
  padding-bottom: 14px;
  padding-left: 0;
  padding-right: 0;
}

.checkpoint-item--disabled.checkpoint-item--last:active {
  padding-bottom: 0;
}

.checkpoint-connector {
  position: absolute;
  left: 11px;
  top: 24px;
  bottom: 0;
  width: 2px;
  background: var(--border);
}

.checkpoint-connector--done {
  background: var(--ok);
}

.checkpoint-connector--active {
  background: linear-gradient(to bottom, var(--accent) 0%, var(--border) 100%);
}

.checkpoint-connector--pending {
  background: var(--border);
}

.checkpoint-icon {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
  flex-shrink: 0;
  position: relative;
  z-index: 1;
}

.checkpoint-icon--done {
  background: var(--ok);
  color: #fff;
}

.checkpoint-icon--active {
  background: var(--accent);
  color: #fff;
}

.checkpoint-icon--pending {
  background: var(--panel);
  border: 2px solid var(--border);
  color: var(--ink4);
}

.checkpoint-dot {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: var(--ink4);
}

.checkpoint-content {
  flex: 1;
  min-width: 0;
  padding-top: 2px;
}

.checkpoint-label {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
  line-height: 1.3;
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

.checkpoint-req-badge {
  font-size: 10px;
  padding: 2px 4px;
  border-radius: 3px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
}

.checkpoint-req-badge--photo {
  background: #eff6ff;
  color: #1e40af;
}

.checkpoint-req-badge--sign {
  background: #f0fdf4;
  color: #166534;
}

.checkpoint-time {
  display: flex;
  flex-direction: column;
  gap: 2px;
  font-size: 11px;
  font-family: var(--mono);
}

.time-planned {
  color: var(--ink3);
}

.time-actual {
  color: var(--ok);
  font-weight: 600;
}

.time-actual--delayed {
  color: #d97706;
}

.time-progress {
  color: var(--accent);
  font-style: italic;
  font-family: inherit;
  font-size: 10px;
}

.checkpoint-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  flex-shrink: 0;
  margin-top: 2px;
}

.checkpoint-badge--done {
  background: var(--ok-soft);
  color: var(--ok);
}

.checkpoint-badge--active {
  background: var(--accent-soft);
  color: var(--accent-fg);
}

.checkpoint-badge--pending {
  background: transparent;
  border: 1px solid var(--border);
}

/* Actions Section */
.actions-section {
  display: flex;
  gap: 10px;
  margin-top: 12px;
}

/* Mobile Modal */
.mobile-modal {
  position: fixed;
  inset: 0;
  z-index: 1000;
  display: flex;
  align-items: flex-end;
}

.mobile-modal-overlay {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.4);
}

.mobile-modal-content {
  position: relative;
  background: var(--surface);
  border-radius: 24px 24px 0 0;
  padding: 20px;
  width: 100%;
  max-height: 70vh;
  overflow-y: auto;
}

.mobile-modal-handle {
  width: 36px;
  height: 4px;
  background: var(--border);
  border-radius: 2px;
  margin: 0 auto 20px;
}

.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  gap: 12px;
}

.modal-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 2px;
}

.modal-subtitle {
  font-size: 13px;
  color: var(--ink3);
}

.modal-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: var(--panel);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--ink3);
  flex-shrink: 0;
}

.modal-close-btn:active {
  background: var(--border);
}

/* Form Elements */
.form-section {
  margin-bottom: 20px;
}

.form-field {
  margin-bottom: 16px;
}

.form-label {
  display: block;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink2);
  margin-bottom: 8px;
}

.time-input-wrapper {
  display: flex;
  gap: 8px;
  align-items: center;
}

.time-input-wrapper .form-input {
  flex: 1;
}

.now-btn {
  padding: 12px 16px;
  font-size: 13px;
  font-weight: 600;
  color: var(--accent);
  background: var(--accent-soft);
  border: 2px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  white-space: nowrap;
}

.now-btn:active {
  transform: scale(0.95);
  background: var(--accent);
  color: white;
}

.form-input,
.form-textarea {
  width: 100%;
  padding: 12px;
  font-size: 14px;
  color: var(--ink);
  background: var(--panel);
  border: 2px solid var(--border);
  border-radius: 10px;
  font-family: inherit;
  transition: all 0.2s;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--accent);
  background: var(--surface);
}

.form-textarea {
  resize: vertical;
  min-height: 80px;
}

/* Signature Pad */
.signature-pad-wrapper {
  position: relative;
  border: 2px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--surface);
}

.signature-canvas {
  display: block;
  width: 100%;
  height: 150px;
  cursor: crosshair;
  touch-action: none;
}

.clear-signature-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  padding: 6px 12px;
  font-size: 11px;
  font-weight: 600;
  color: var(--danger);
  background: white;
  border: 1px solid var(--border);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.clear-signature-btn:active {
  transform: scale(0.95);
  background: var(--danger);
  color: white;
}

/* Photo Capture */
.photo-capture-wrapper {
  border: 2px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--surface);
  min-height: 150px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.photo-preview {
  position: relative;
  width: 100%;
  height: 100%;
}

.photo-preview img {
  width: 100%;
  height: auto;
  display: block;
  max-height: 300px;
  object-fit: contain;
}

.remove-photo-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 32px;
  height: 32px;
  padding: 0;
  font-size: 16px;
  font-weight: 700;
  color: white;
  background: var(--danger);
  border: none;
  border-radius: 50%;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.remove-photo-btn:active {
  transform: scale(0.9);
  background: #b91c1c;
}

.photo-buttons {
  display: flex;
  gap: 10px;
  padding: 20px;
  width: 100%;
}

.photo-btn {
  flex: 1;
  padding: 14px 16px;
  font-size: 14px;
  font-weight: 600;
  border: 2px solid var(--border);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s;
  font-family: inherit;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}

.photo-btn--camera {
  background: var(--accent-soft);
  color: var(--accent);
  border-color: var(--accent);
}

.photo-btn--camera:active {
  transform: scale(0.95);
  background: var(--accent);
  color: white;
}

.photo-btn--upload {
  background: var(--panel);
  color: var(--ink2);
}

.photo-btn--upload:active {
  transform: scale(0.95);
  background: var(--ink2);
  color: white;
}

.modal-actions {
  display: flex;
  gap: 10px;
}

/* Transitions */
.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.3s ease;
}

.slide-up-enter-from .mobile-modal-overlay,
.slide-up-leave-to .mobile-modal-overlay {
  opacity: 0;
}

.slide-up-enter-from .mobile-modal-content,
.slide-up-leave-to .mobile-modal-content {
  transform: translateY(100%);
}

.mono {
  font-family: var(--mono);
}
</style>
