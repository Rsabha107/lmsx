<template>
  <app-layout>
    <div class="mobile-container">
      <!-- Header -->
      <div class="mobile-header">
        <h1 class="mobile-title">My Jobs</h1>
        <div class="mobile-subtitle">{{ schedule.length }} active jobs</div>
        <div class="mobile-supervisor">Priya Anand · Supervisor</div>
      </div>

      <!-- Job Cards -->
      <div class="job-cards">
        <div
          v-for="job in schedule"
          :key="job.id"
          @click="selectJob(job)"
          class="job-card"
        >
          <div class="job-card-header">
            <div class="job-card-left">
              <div class="team-badge">{{ job.code }}</div>
              <div>
                <div class="job-card-team">{{ job.team }}</div>
                <div class="job-card-id">
                  {{ job.jobId || job.id }}
                  <span v-if="job.source" :class="['source-badge', `source-badge--${job.source}`]">
                    {{ job.source === 'database' ? 'DB' : 'DEMO' }}
                  </span>
                </div>
              </div>
            </div>
            <status-pill :tone="statusTone(job.status)" :dot="true" size="sm">
              {{ statusLabel(job.status) }}
            </status-pill>
          </div>

          <div class="job-card-route">
            <svg-icon name="plane" :size="14" style="color: var(--ink3);" />
            <span>{{ job.from }}</span>
            <span style="color: var(--ink4); margin: 0 4px;">→</span>
            <span>{{ job.to }}</span>
          </div>

          <div class="job-card-details">
            <div class="detail-item">
              <svg-icon name="clock" :size="14" />
              <span class="mono">{{ job.dep }} – {{ job.arr }}</span>
            </div>
            <div class="detail-item">
              <svg-icon name="bus" :size="14" />
              <span>{{ job.vehicle }}</span>
            </div>
            <div class="detail-item">
              <svg-icon name="user" :size="14" />
              <span class="mono">{{ job.pax }} pax</span>
            </div>
          </div>

          <div v-if="job.delay" class="job-card-alert">
            <svg-icon name="warn" :size="14" />
            <span>Delayed +{{ job.delay }}m</span>
          </div>
        </div>
      </div>

      <!-- Floating Action Button -->
      <button class="fab" @click="showNewJob = true">
        <svg-icon name="plus" :size="20" style="color: #fff;" />
      </button>
    </div>
  </app-layout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';

const props = defineProps({
  schedule: { type: Array, default: () => [] },
});

const showNewJob = ref(false);

function selectJob(job) {
  router.visit(`/jobs/mobile/${job.id}`);
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
.mobile-container {
  max-width: 480px;
  margin: 0 auto;
  padding: 16px;
  padding-bottom: 80px;
}

.mobile-header {
  margin-bottom: 20px;
}

.mobile-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
  letter-spacing: -0.4px;
}

.mobile-subtitle {
  font-size: 13px;
  color: var(--ink3);
  margin-bottom: 6px;
}

.mobile-supervisor {
  font-size: 12px;
  color: var(--ink4);
}

/* Job Cards */
.job-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.job-card {
  background: var(--surface);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  cursor: pointer;
  transition: all 0.2s;
}

.job-card:active {
  transform: scale(0.98);
}

.job-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  gap: 12px;
}

.job-card-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.team-badge {
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

.job-card-team {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.job-card-id {
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

.job-card-route {
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

.job-card-details {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ink3);
}

.job-card-alert {
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

/* Floating Action Button */
.fab {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: var(--accent);
  color: #fff;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.fab:active {
  transform: scale(0.95);
}

.mono {
  font-family: var(--mono);
}
</style>
