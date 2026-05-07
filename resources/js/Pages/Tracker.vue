<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Live Tracker</h1>
        <p class="page-sub">{{ liveJobs.length }} vehicles in motion</p>
      </div>
      <div class="page-header-actions" style="display: flex; gap: 8px; align-items: center;">
        <RefreshButton :only="['liveJobs']" />
        <div class="live-indicator">
          <div class="live-dot" />
          <span>Live</span>
        </div>
      </div>
    </div>

    <div class="tracker-layout">
      <!-- Map -->
      <div class="map-card">
        <div class="map-title">Schematic Map</div>
        <svg class="schematic" viewBox="0 0 600 380" xmlns="http://www.w3.org/2000/svg">
          <!-- Road lines between nodes -->
          <g class="roads">
            <line v-for="road in roads" :key="road.id"
              :x1="road.x1" :y1="road.y1" :x2="road.x2" :y2="road.y2"
              stroke="var(--border-strong)" stroke-width="2" stroke-dasharray="6 4"
            />
          </g>

          <!-- Active job progress lines -->
          <g v-for="job in liveJobs" :key="job.id">
            <line
              :x1="nodePos(job.from).x" :y1="nodePos(job.from).y"
              :x2="progressPos(job).x"  :y2="progressPos(job).y"
              :stroke="jobColor(job.status)" stroke-width="3" stroke-linecap="round"
            />
          </g>

          <!-- Nodes -->
          <g v-for="node in mapNodes" :key="node.id" class="map-node" @click="selectedNode = node">
            <circle
              :cx="node.x * 600" :cy="node.y * 380"
              :r="nodeActive(node.id) ? 16 : 12"
              :fill="nodeColor(node.type)"
              stroke="var(--surface)" stroke-width="3"
              style="transition: r 0.2s"
            />
            <text
              :x="node.x * 600" :y="node.y * 380 + 4"
              text-anchor="middle" fill="#fff"
              font-size="9" font-weight="700" font-family="Inter, sans-serif"
            >{{ node.label }}</text>
          </g>

          <!-- Vehicle dots -->
          <g v-for="job in liveJobs" :key="`v-${job.id}`">
            <circle
              :cx="progressPos(job).x" :cy="progressPos(job).y"
              r="8"
              :fill="jobColor(job.status)"
              stroke="var(--surface)" stroke-width="2"
            />
            <text
              :x="progressPos(job).x" :y="progressPos(job).y + 4"
              text-anchor="middle" fill="#fff"
              font-size="7" font-weight="700" font-family="Inter, sans-serif"
            >{{ job.code }}</text>
          </g>
        </svg>

        <!-- Legend -->
        <div class="map-legend">
          <div class="legend-item"><span class="legend-dot" style="background:#22D3EE"/> In Progress</div>
          <div class="legend-item"><span class="legend-dot" style="background:#F59E0B"/> Delayed</div>
          <div class="legend-item"><span class="legend-dot" style="background:#6366F1"/> Airport</div>
          <div class="legend-item"><span class="legend-dot" style="background:#16A34A"/> Hotel</div>
          <div class="legend-item"><span class="legend-dot" style="background:#EF4444"/> Stadium</div>
        </div>
      </div>

      <!-- Jobs sidebar -->
      <div class="live-jobs">
        <div class="live-jobs-title">Active Jobs</div>
        <div class="live-job-card" v-for="job in allJobs" :key="job.id">
          <div class="ljc-top">
            <span class="team-badge-sm">{{ job.code }}</span>
            <span class="ljc-id mono">{{ job.id }}</span>
            <status-pill :tone="statusTone(job.status)" style="margin-left:auto">
              {{ job.delay ? `+${job.delay}m` : statusLabel(job.status) }}
            </status-pill>
          </div>
          <div class="ljc-route">{{ job.from }} → {{ job.to }}</div>
          <div v-if="job.progress !== undefined" class="ljc-progress-bar">
            <div class="ljc-progress-fill" :style="{ width: `${job.progress * 100}%`, background: jobColor(job.status) }" />
          </div>
          <div v-if="job.progress !== undefined" class="ljc-progress-label">
            {{ Math.round(job.progress * 100) }}% complete
          </div>
        </div>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  mapNodes: { type: Array, default: () => [] },
  liveJobs: { type: Array, default: () => [] },
  schedule: { type: Array, default: () => [] },
});

const selectedNode = ref(null);

const roads = computed(() => {
  const pairs = [
    ['cdg','aur'], ['ory','sol'], ['aur','azr'], ['ver','trg'],
    ['ver','azr'], ['min','azr'], ['aur','ver'],
  ];
  return pairs.map((p, i) => {
    const a = nodePos2(p[0]);
    const b = nodePos2(p[1]);
    return { id: i, x1: a.x, y1: a.y, x2: b.x, y2: b.y };
  });
});

function nodePos2(id) {
  const n = props.mapNodes.find(n => n.id === id);
  if (!n) return { x: 0, y: 0 };
  return { x: n.x * 600, y: n.y * 380 };
}

function nodePos(id) { return nodePos2(id); }

function progressPos(job) {
  const from = nodePos(job.from);
  const to   = nodePos(job.to);
  const p = job.progress ?? 0;
  return { x: from.x + (to.x - from.x) * p, y: from.y + (to.y - from.y) * p };
}

const nodeTypeColors = { airport: '#6366F1', hotel: '#16A34A', stadium: '#EF4444' };
function nodeColor(type) { return nodeTypeColors[type] ?? '#9AA2B2'; }

function jobColor(status) {
  return status === 'delayed' ? '#F59E0B' : '#22D3EE';
}

function nodeActive(id) {
  return props.liveJobs.some(j => j.from === id || j.to === id);
}

const allJobs = computed(() => {
  const liveIds = new Set(props.liveJobs.map(j => j.id));
  const live = props.liveJobs.map(j => {
    const sched = props.schedule.find(s => s.id === j.id) ?? {};
    return { ...sched, ...j };
  });
  const sched = props.schedule.filter(s => !liveIds.has(s.id));
  return [...live, ...sched];
});

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'scheduled':   { tone: 'primary', label: 'Scheduled' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'done':        { tone: 'ok',      label: 'Done' },
};
function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
function statusLabel(s) { return statusMap[s]?.label ?? s; }
</script>

<style scoped>
.page-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 16px;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.live-indicator {
  display: flex; align-items: center; gap: 6px;
  font-size: 12px; font-weight: 600; color: var(--live);
}
.live-dot {
  width: 8px; height: 8px; border-radius: 50%; background: var(--live);
  animation: pulseDot 1.4s ease-in-out infinite;
}

.tracker-layout {
  display: grid; grid-template-columns: 1fr 300px; gap: 16px;
}
@media (max-width: 900px) { .tracker-layout { grid-template-columns: 1fr; } }

.map-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden;
}
.map-title {
  padding: 12px 16px; border-bottom: 1px solid var(--border);
  font-size: 13.5px; font-weight: 600; color: var(--ink);
}
.schematic {
  width: 100%; height: auto; display: block;
  background: var(--panel);
}
.map-node { cursor: pointer; }
.map-legend {
  display: flex; gap: 16px; flex-wrap: wrap;
  padding: 10px 16px; border-top: 1px solid var(--border);
}
.legend-item { display: flex; align-items: center; gap: 5px; font-size: 11.5px; color: var(--ink3); }
.legend-dot { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }

.live-jobs { display: flex; flex-direction: column; gap: 10px; }
.live-jobs-title { font-size: 13px; font-weight: 700; color: var(--ink); margin-bottom: 4px; }

.live-job-card {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; padding: 12px;
}
.ljc-top { display: flex; align-items: center; gap: 7px; margin-bottom: 6px; }
.team-badge-sm {
  width: 26px; height: 26px; border-radius: 5px; flex-shrink: 0;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 9px; font-weight: 700;
  display: inline-flex; align-items: center; justify-content: center;
}
.ljc-id { font-size: 11.5px; color: var(--ink3); }
.ljc-route { font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 8px; }
.ljc-progress-bar {
  height: 5px; background: var(--border); border-radius: 3px; overflow: hidden; margin-bottom: 4px;
}
.ljc-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s; }
.ljc-progress-label { font-size: 11px; color: var(--ink3); }

.mono { font-family: var(--font-mono, monospace); font-size: 12px; }
</style>
