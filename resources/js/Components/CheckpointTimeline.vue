<template>
  <div>
    <!-- Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
      <div style="font-size: 10px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--ink3);">
        {{ title }}
        <span v-if="checkpoints?.length"> · {{ completedCount }}/{{ checkpoints.length }}</span>
      </div>
      <div v-if="hasMobileUpdates"
           style="display: flex; align-items: center; gap: 4px; font-size: 10px; font-weight: 600; color: var(--accent);">
        <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2" ry="2"/><circle cx="12" cy="18" r="0.5" fill="currentColor"/></svg>
        Updated via mobile
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
            <div v-else-if="getVisualState(cp, idx) === 'active'" style="width: 6px; height: 6px; border-radius: 999px; background: var(--accent);"></div>
          </div>
          <div
            v-if="idx < checkpoints.length - 1"
            class="dc-cp-line"
            :style="{ background: getVisualState(cp, idx) === 'done' ? 'var(--ok)' : 'var(--borderStrong, #d0d5df)' }"
          ></div>
        </div>

        <!-- Content -->
        <div :style="{ flex: 1, paddingBottom: idx < checkpoints.length - 1 ? '14px' : 0 }">
          <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
            <div>
              <div style="display: flex; align-items: center; gap: 5px; flex-wrap: wrap;">
                <span style="font-size: 13px; font-weight: 600;"
                      :style="{ color: getVisualState(cp, idx) === 'pending' ? 'var(--ink3)' : 'var(--ink)' }">
                  {{ cp.name }}
                </span>
                <!-- Photo badge -->
                <span v-if="cp.requires_photo"
                      :style="{ display: 'inline-flex', alignItems: 'center', gap: '2px', fontSize: '10px', fontWeight: 600, padding: '1px 5px', borderRadius: '3px', background: cp.has_photo ? '#f0fdf4' : '#fffbeb', color: cp.has_photo ? '#166534' : '#92400e' }">
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                  {{ cp.has_photo ? 'Done' : 'Required' }}
                </span>
                <!-- Signature badge -->
                <span v-if="cp.requires_signature"
                      :style="{ display: 'inline-flex', alignItems: 'center', gap: '2px', fontSize: '10px', fontWeight: 600, padding: '1px 5px', borderRadius: '3px', background: cp.has_signature ? '#f0fdf4' : '#fffbeb', color: cp.has_signature ? '#166534' : '#92400e' }">
                  <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
                  {{ cp.has_signature ? 'Signed' : 'Required' }}
                </span>
              </div>
              <div v-if="completedBy(cp)" style="font-size: 10px; color: var(--ink3); margin-top: 1px;">
                {{ completedBy(cp) }}{{ cp.completion_method === 'mobile' ? ' (mobile)' : '' }}
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
                <span v-if="cp.completed_at" style="color: var(--ink3);">{{ formatTime(cp.completed_at) }}</span>
                <span v-else-if="cp.scheduled_at && getVisualState(cp, idx) !== 'done'"
                      style="color: var(--ink4);">{{ formatTime(cp.scheduled_at) }}</span>
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  checkpoints: { type: Array, default: () => [] },
  title:        { type: String, default: 'Checkpoints' },
  emptyMessage: { type: String, default: 'No checkpoints defined' },
});

function getCheckpointState(cp) {
  if (!cp) return 'pending';
  if (cp.state === 'completed' || cp.state === 'done' || cp.completed_at) return 'done';
  if (cp.state === 'started' || cp.started_at) return 'active';
  return 'pending';
}

const completedCount = computed(() =>
  props.checkpoints.filter(cp => getCheckpointState(cp) === 'done').length
);

const nextCheckpointIdx = computed(() => {
  const hasProgress = props.checkpoints.some(cp => getCheckpointState(cp) === 'done');
  if (!hasProgress) return -1;
  return props.checkpoints.findIndex(cp => getCheckpointState(cp) !== 'done');
});

function getVisualState(cp, idx) {
  if (getCheckpointState(cp) === 'done') return 'done';
  if (idx === nextCheckpointIdx.value) return 'active';
  return 'pending';
}

function completedBy(cp) {
  return cp.by || cp.completed_by || null;
}

function formatTime(value) {
  if (!value) return '';
  const m = String(value).match(/(\d{2}):(\d{2})/);
  if (m) return `${m[1]}:${m[2]}`;
  const d = new Date(value);
  if (isNaN(d)) return value;
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
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
.dc-cp-line { width: 2px; flex: 1; min-height: 16px; }
</style>
