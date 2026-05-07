<template>
  <app-layout>
    <div class="page-header">
      <div>
        <p class="page-sub">Live execution · {{ schedule.length }} jobs</p>
        <h1 class="page-title">Jobs</h1>
      </div>
      <div class="page-header-actions">
        <button class="btn btn--secondary btn--sm">
          <svg-icon name="filter" :size="14" /> Filter
        </button>
        <button class="btn btn--primary btn--sm">
          <svg-icon name="plus" :size="14" style="color: #fff;" /> New job
        </button>
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
        <div class="jobs-list-scroll">
          <div
            v-for="job in filtered" :key="job.id"
            @click="selectJob(job)"
            :class="['job-item', selectedJob?.id === job.id ? 'job-item--active' : '']"
          >
            <div class="job-item-header">
              <div class="job-item-header-left">
                <span class="team-badge-sm">{{ job.code }}</span>
                <span class="job-id mono">{{ job.id }}</span>
              </div>
              <status-pill :tone="statusTone(job.status)" :dot="true" size="sm">
                {{ statusLabel(job.status) }}
              </status-pill>
            </div>
            <div class="job-route">{{ job.from }} → {{ job.to }}</div>
            <div class="job-window-row">
              <span class="mono">{{ job.dep }} – {{ job.arr }}</span>
              <span v-if="job.delay" :style="{ fontSize: '11px', fontFamily: 'var(--font-mono, monospace)', fontWeight: '600', color: 'var(--warn)' }">
                +{{ job.delay }}m
              </span>
              <span v-else :style="{ fontSize: '11px', fontFamily: 'var(--font-mono, monospace)', fontWeight: '600', color: jobEtaColor(job) }">
                ETA {{ job.arr }}
              </span>
            </div>
            <!-- Progress bar -->
            <div class="progress-bar">
              <div class="progress-fill" :style="{ 
                width: `${jobProgress(job)}%`, 
                background: job.status === 'delayed' ? 'var(--danger)' : job.status === 'done' ? 'var(--ok)' : 'var(--accent)' 
              }"/>
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
            <div class="detail-id">{{ selectedJob.id }} · {{ selectedJob.team }}</div>
            <status-pill :tone="statusTone(selectedJob.status)" :dot="true" size="sm">
              {{ statusLabel(selectedJob.status) }}
            </status-pill>
            <div class="detail-actions">
              <button class="btn btn--secondary btn--sm">Contact</button>
              <button class="btn btn--primary btn--sm">Override</button>
            </div>
          </div>
          <div class="detail-subtitle">
            {{ selectedJob.from }} → {{ selectedJob.to }} · {{ selectedJob.vehicle }} · {{ selectedJob.pax }} pax
          </div>

          <div class="detail-stats">
            <mini-stat label="Window" :value="`${selectedJob.dep} – ${selectedJob.arr}`"/>
            <mini-stat label="Progress" :value="`${jobProgress(selectedJob)}%`"/>
            <mini-stat label="Checks" :value="`${selectedJob.checksComplete || 0}/${selectedJob.checksTotal || 7}`"/>
            <mini-stat label="Status" :value="statusLabel(selectedJob.status)" :tone="statusTone(selectedJob.status)"/>
          </div>

          <div class="detail-full-link">
            <span @click="$inertia.visit(`/job/${selectedJob.id}`)" style="color: var(--accent); cursor: pointer; text-decoration: none; font-size: 13px; font-weight: 600;">
              Full detail →
            </span>
          </div>
        </div>

        <div class="detail-grid">
          <!-- Checkpoint timeline -->
          <div v-if="selectedJob.checkpoints && selectedJob.checkpoints.length" class="checkpoint-section">
            <div class="checkpoint-header">
              <h3 class="section-title">Checkpoint timeline</h3>
              <span class="section-kicker">{{ doneCount }} of {{ selectedJob.checkpoints.length }} complete</span>
            </div>
            <div class="checkpoint-content">
              <checkpoint-timeline :items="selectedJob.checkpoints"/>
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
                  {{ getInitials(person) }}
                </div>
                <div class="crew-info">
                  <div class="crew-name">{{ person }}</div>
                  <div class="crew-role">{{ label }}</div>
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
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '../../Components/AppLayout.vue';
import StatusPill from '../../Components/StatusPill.vue';
import SvgIcon from '../../Components/SvgIcon.vue';
import MiniStat from '../../Components/MiniStat.vue';
import CheckpointTimeline from '../../Components/CheckpointTimeline.vue';

const props = defineProps({
  schedule: { type: Array, default: () => [] },
});

const selectedJob = ref(null);
const activeFilter = ref('all');

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'scheduled':   { tone: 'primary', label: 'Scheduled' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'done':        { tone: 'ok',      label: 'Done' },
};
function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
function statusLabel(s) { return statusMap[s]?.label ?? s; }

function selectJob(job) {
  selectedJob.value = job;
}

function jobProgress(job) {
  if (job.status === 'done') return 100;
  if (job.status === 'in-progress') return 65;
  if (job.status === 'delayed') return 45;
  return 10;
}

function jobEtaColor(job) {
  if (job.status === 'delayed') return 'var(--danger)';
  if (job.status === 'in-progress') return 'var(--warn)';
  return 'var(--ok)';
}

const doneCount = computed(() => {
  if (!selectedJob.value || !selectedJob.value.checkpoints) return 0;
  return selectedJob.value.checkpoints.filter(c => c.state === 'done' || c.status === 'done').length;
});

const crewMembers = computed(() => {
  if (!selectedJob.value) return {};
  return {
    Supervisor: selectedJob.value.supervisor,
    Driver: selectedJob.value.driver,
  };
});

function getInitials(name) {
  return name.split(' ').map(s => s[0]).join('').slice(0, 2);
}

const filters = computed(() => [
  { value: 'all',         label: 'All',         count: props.schedule.length },
  { value: 'in-progress', label: 'In Progress', count: props.schedule.filter(j => j.status === 'in-progress').length },
  { value: 'delayed',     label: 'Delayed',     count: props.schedule.filter(j => j.status === 'delayed').length },
  { value: 'scheduled',   label: 'Scheduled',   count: props.schedule.filter(j => j.status === 'scheduled').length },
  { value: 'done',        label: 'Completed',   count: props.schedule.filter(j => j.status === 'done').length },
]);

const filtered = computed(() =>
  activeFilter.value === 'all'
    ? props.schedule
    : props.schedule.filter(j => j.status === activeFilter.value)
);
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
  display: grid; grid-template-columns: 340px 1fr; gap: 12px;
  min-height: 0; flex: 1;
}
@media (max-width: 1024px) {
  .jobs-layout {
    grid-template-columns: 1fr;
  }
}

.jobs-list-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
  display: flex; flex-direction: column;
}

.jobs-list-scroll {
  overflow: auto; flex: 1;
  display: flex; flex-direction: column;
}

.job-item {
  padding: 12px 14px; cursor: pointer;
  border-bottom: 1px solid var(--border);
  border-left: 3px solid transparent;
  transition: background 0.15s;
}
.job-item:hover { background: var(--panel); }
.job-item--active {
  border-left-color: var(--accent);
  background: var(--accent-soft);
}
.job-item:last-child { border-bottom: none; }

.job-item-header {
  display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;
}
.job-item-header-left {
  display: flex; align-items: center; gap: 8px;
}
.job-id { font-size: 12px; font-weight: 700; color: var(--ink); }

.team-badge-sm {
  width: 26px; height: 26px; border-radius: 5px; flex-shrink: 0;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 9px; font-weight: 700;
  display: inline-flex; align-items: center; justify-content: center;
}
.job-route { font-size: 12px; color: var(--ink2); margin-bottom: 4px; }
.job-window-row {
  display: flex; justify-content: space-between; align-items: center;
  font-size: 11px; color: var(--ink3); margin-bottom: 6px;
}

.progress-bar {
  height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;
}
.progress-fill {
  height: 100%; border-radius: 2px; transition: width 0.3s;
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
  font-size: 18px; font-weight: 700; color: var(--ink); letter-spacing: -0.3px;
  line-height: 34px;
}
@media (max-width: 640px) {
  .detail-id {
    font-size: 16px;
    line-height: 28px;
  }
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
@media (max-width: 640px) {
  .detail-stats {
    grid-template-columns: repeat(2, 1fr);
  }
}

.detail-full-link {
  padding-top: 14px;
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
</style>
