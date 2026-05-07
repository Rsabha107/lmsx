<template>
  <app-layout>
    <!-- Header -->
    <div class="page-header">
      <div>
        <p class="page-sub">Business Intelligence</p>
        <h1 class="page-title">Analytics</h1>
      </div>
      <div class="page-header-actions">
        <select v-model="selectedPeriod" @change="reloadData" class="period-selector">
          <option value="24h">Last 24 Hours</option>
          <option value="7d">Last 7 Days</option>
          <option value="30d">Last 30 Days</option>
          <option value="90d">Last 90 Days</option>
        </select>
        <RefreshButton :only="[]" />
        <Button variant="secondary" size="sm" @click="exportData('json')">
          <template #icon><svg-icon name="download" :size="14" /></template>
          Export JSON
        </Button>
        <Button variant="secondary" size="sm" @click="exportData('csv')">
          <template #icon><svg-icon name="download" :size="14" /></template>
          Export CSV
        </Button>
      </div>
    </div>

    <!-- Real-Time Stats -->
    <section class="analytics-section">
      <h2 class="section-title">Real-Time Operations</h2>
      <div class="stats-grid stats-grid--7">
        <MiniStat label="Active Jobs" :value="realTimeStats.active_jobs" tone="live" />
        <MiniStat label="Delayed" :value="realTimeStats.delayed_jobs" tone="warn" />
        <MiniStat label="Completed Today" :value="realTimeStats.completed_today" tone="ok" />
        <MiniStat label="Pending" :value="realTimeStats.pending_jobs" tone="primary" />
        <MiniStat label="Vehicles Active" :value="realTimeStats.vehicles_active" />
        <MiniStat label="Drivers On Shift" :value="realTimeStats.drivers_on_shift" />
        <MiniStat label="Checkpoints Today" :value="realTimeStats.checkpoints_completed_today" />
      </div>
    </section>

    <!-- Performance Metrics -->
    <section class="analytics-section">
      <h2 class="section-title">Performance & Efficiency</h2>
      <div class="stats-grid stats-grid--4">
        <div class="metric-card">
          <div class="metric-label">On-Time Performance</div>
          <div class="metric-value" :class="getPerformanceClass(performanceMetrics.on_time_percentage)">
            {{ performanceMetrics.on_time_percentage }}%
          </div>
          <div class="metric-sublabel">{{ performanceMetrics.jobs_analyzed }} jobs analyzed</div>
          <div class="metric-bar">
            <div class="metric-bar-fill" :style="{ width: performanceMetrics.on_time_percentage + '%', background: getPerformanceColor(performanceMetrics.on_time_percentage) }"></div>
          </div>
        </div>
        
        <div class="metric-card">
          <div class="metric-label">Average Delay</div>
          <div class="metric-value" :class="getDelayClass(performanceMetrics.average_delay_minutes)">
            {{ performanceMetrics.average_delay_minutes }} min
          </div>
          <div class="metric-sublabel">Per completed job</div>
        </div>
        
        <div class="metric-card">
          <div class="metric-label">Checkpoint Adherence</div>
          <div class="metric-value" :class="getPerformanceClass(performanceMetrics.checkpoint_adherence)">
            {{ performanceMetrics.checkpoint_adherence }}%
          </div>
          <div class="metric-sublabel">Within 5 min window</div>
          <div class="metric-bar">
            <div class="metric-bar-fill" :style="{ width: performanceMetrics.checkpoint_adherence + '%', background: getPerformanceColor(performanceMetrics.checkpoint_adherence) }"></div>
          </div>
        </div>

        <div class="metric-card">
          <div class="metric-label">Jobs Analyzed</div>
          <div class="metric-value">{{ performanceMetrics.jobs_analyzed }}</div>
          <div class="metric-sublabel">In selected period</div>
        </div>
      </div>
    </section>

    <!-- Resource Utilization & Compliance -->
    <div class="analytics-row">
      <section class="analytics-section analytics-section--half">
        <h2 class="section-title">Resource Utilization</h2>
        <div class="stats-grid stats-grid--2">
          <div class="metric-card">
            <div class="metric-label">Vehicle Utilization</div>
            <div class="metric-value">{{ resourceUtilization.vehicle_utilization_rate }}%</div>
            <div class="metric-sublabel">{{ resourceUtilization.total_vehicles }} total vehicles</div>
            <div class="metric-bar">
              <div class="metric-bar-fill" :style="{ width: resourceUtilization.vehicle_utilization_rate + '%' }"></div>
            </div>
          </div>
          
          <div class="metric-card">
            <div class="metric-label">Driver Utilization</div>
            <div class="metric-value">{{ resourceUtilization.driver_utilization_rate }}%</div>
            <div class="metric-sublabel">{{ resourceUtilization.total_drivers }} total drivers</div>
            <div class="metric-bar">
              <div class="metric-bar-fill" :style="{ width: resourceUtilization.driver_utilization_rate + '%' }"></div>
            </div>
          </div>
        </div>
      </section>

      <section class="analytics-section analytics-section--half">
        <h2 class="section-title">Quality & Compliance</h2>
        <div class="stats-grid stats-grid--3">
          <div class="metric-card">
            <div class="metric-label">Photo Compliance</div>
            <div class="metric-value" :class="getPerformanceClass(complianceMetrics.photo_compliance)">
              {{ complianceMetrics.photo_compliance }}%
            </div>
            <div class="metric-bar">
              <div class="metric-bar-fill" :style="{ width: complianceMetrics.photo_compliance + '%', background: getPerformanceColor(complianceMetrics.photo_compliance) }"></div>
            </div>
          </div>
          
          <div class="metric-card">
            <div class="metric-label">Signature Compliance</div>
            <div class="metric-value" :class="getPerformanceClass(complianceMetrics.signature_compliance)">
              {{ complianceMetrics.signature_compliance }}%
            </div>
            <div class="metric-bar">
              <div class="metric-bar-fill" :style="{ width: complianceMetrics.signature_compliance + '%', background: getPerformanceColor(complianceMetrics.signature_compliance) }"></div>
            </div>
          </div>
          
          <div class="metric-card">
            <div class="metric-label">Override Rate</div>
            <div class="metric-value" :class="getOverrideClass(complianceMetrics.override_rate)">
              {{ complianceMetrics.override_rate }}%
            </div>
            <div class="metric-sublabel">{{ complianceMetrics.checkpoints_analyzed }} checkpoints</div>
          </div>
        </div>
      </section>
    </div>

    <!-- Checkpoint Analysis -->
    <section class="analytics-section" v-if="hasCheckpointData">
      <h2 class="section-title">Checkpoint Analysis by Type</h2>
      <div class="table-card">
        <table class="analytics-table">
          <thead>
            <tr>
              <th>Checkpoint Type</th>
              <th>Count</th>
              <th>Avg Variance</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(data, type) in checkpointAnalysis" :key="type">
              <td class="table-label">{{ formatCheckpointType(type) }}</td>
              <td>{{ data.count }}</td>
              <td :class="getVarianceClass(data.avg_variance)">
                {{ data.avg_variance > 0 ? '+' : '' }}{{ data.avg_variance }} min
              </td>
              <td>
                <status-pill :tone="getVarianceTone(data.avg_variance)" size="sm">
                  {{ getVarianceLabel(data.avg_variance) }}
                </status-pill>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Team Patterns & Plan Effectiveness -->
    <div class="analytics-row">
      <section class="analytics-section analytics-section--half">
        <h2 class="section-title">Movement Patterns</h2>
        <div class="pattern-grid">
          <div v-for="(count, kind) in teamPatterns.kind_distribution" :key="kind" class="pattern-item">
            <div class="pattern-kind">{{ formatKind(kind) }}</div>
            <div class="pattern-count">{{ count }} movements</div>
            <div class="pattern-pax" v-if="teamPatterns.avg_passengers_by_kind[kind]">
              Avg {{ teamPatterns.avg_passengers_by_kind[kind] }} pax
            </div>
          </div>
        </div>
      </section>

      <section class="analytics-section analytics-section--half">
        <h2 class="section-title">Plan Effectiveness</h2>
        <div class="stats-grid stats-grid--2">
          <MiniStat label="Total Plans" :value="planEffectiveness.total_plans" />
          <MiniStat label="Completed" :value="planEffectiveness.completed_plans" tone="ok" />
          <MiniStat label="Active Plans" :value="planEffectiveness.active_plans" tone="live" />
          <MiniStat label="Avg Movements/Plan" :value="planEffectiveness.avg_movements_per_plan" />
        </div>
      </section>
    </div>

    <!-- Trends Chart -->
    <section class="analytics-section">
      <h2 class="section-title">Trends Over Time</h2>
      <div class="chart-card">
        <v-chart class="chart" :option="chartOptions" autoresize />
      </div>
    </section>

  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { LineChart } from 'echarts/charts';
import { GridComponent, TooltipComponent, LegendComponent, TitleComponent } from 'echarts/components';
import VChart from 'vue-echarts';
import AppLayout from '../Components/AppLayout.vue';
import MiniStat from '../Components/MiniStat.vue';
import StatusPill from '../Components/StatusPill.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import Button from '../Components/Button.vue';
import SvgIcon from '../Components/SvgIcon.vue';

use([CanvasRenderer, LineChart, GridComponent, TooltipComponent, LegendComponent, TitleComponent]);

const props = defineProps({
  realTimeStats: { type: Object, required: true },
  performanceMetrics: { type: Object, required: true },
  resourceUtilization: { type: Object, required: true },
  complianceMetrics: { type: Object, required: true },
  checkpointAnalysis: { type: Object, required: true },
  teamPatterns: { type: Object, required: true },
  planEffectiveness: { type: Object, required: true },
  trendsData: { type: Object, required: true },
});

const selectedPeriod = ref('7d');

const hasCheckpointData = computed(() => {
  return Object.keys(props.checkpointAnalysis).length > 0;
});

const chartOptions = computed(() => {
  // Extract dates and values from trendsData
  const dates = Object.keys(props.trendsData.daily_jobs).sort();
  const jobsData = dates.map(date => props.trendsData.daily_jobs[date]);
  const delaysData = dates.map(date => props.trendsData.daily_delays[date] || 0);
  
  return {
    tooltip: {
      trigger: 'axis',
      backgroundColor: 'rgba(255, 255, 255, 0.95)',
      borderColor: '#E5E7EB',
      borderWidth: 1,
      textStyle: {
        color: '#111827',
        fontSize: 12,
      },
      padding: [8, 12],
      axisPointer: {
        type: 'cross',
        crossStyle: {
          color: '#9CA3AF'
        }
      }
    },
    legend: {
      data: ['Jobs', 'Delays'],
      bottom: 10,
      textStyle: {
        color: '#6B7280',
        fontSize: 12,
        fontWeight: 600,
      },
      itemWidth: 20,
      itemHeight: 12,
    },
    grid: {
      left: '3%',
      right: '4%',
      bottom: '15%',
      top: '5%',
      containLabel: true
    },
    xAxis: {
      type: 'category',
      boundaryGap: false,
      data: dates,
      axisLine: {
        lineStyle: {
          color: '#E5E7EB'
        }
      },
      axisLabel: {
        color: '#6B7280',
        fontSize: 11,
        fontWeight: 500,
      },
      splitLine: {
        show: false
      }
    },
    yAxis: [
      {
        type: 'value',
        name: 'Jobs',
        nameTextStyle: {
          color: '#6B7280',
          fontSize: 11,
          fontWeight: 600,
        },
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        },
        axisLabel: {
          color: '#6B7280',
          fontSize: 11,
        },
        splitLine: {
          lineStyle: {
            color: '#F3F4F6',
            type: 'dashed'
          }
        }
      },
      {
        type: 'value',
        name: 'Delays',
        nameTextStyle: {
          color: '#6B7280',
          fontSize: 11,
          fontWeight: 600,
        },
        axisLine: {
          show: false
        },
        axisTick: {
          show: false
        },
        axisLabel: {
          color: '#6B7280',
          fontSize: 11,
        },
        splitLine: {
          show: false
        }
      }
    ],
    series: [
      {
        name: 'Jobs',
        type: 'line',
        smooth: true,
        yAxisIndex: 0,
        data: jobsData,
        lineStyle: {
          color: '#3B82F6',
          width: 2.5
        },
        itemStyle: {
          color: '#3B82F6'
        },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(59, 130, 246, 0.2)' },
              { offset: 1, color: 'rgba(59, 130, 246, 0.0)' }
            ]
          }
        },
        emphasis: {
          focus: 'series'
        }
      },
      {
        name: 'Delays',
        type: 'line',
        smooth: true,
        yAxisIndex: 1,
        data: delaysData,
        lineStyle: {
          color: '#F59E0B',
          width: 2.5
        },
        itemStyle: {
          color: '#F59E0B'
        },
        areaStyle: {
          color: {
            type: 'linear',
            x: 0,
            y: 0,
            x2: 0,
            y2: 1,
            colorStops: [
              { offset: 0, color: 'rgba(245, 158, 11, 0.15)' },
              { offset: 1, color: 'rgba(245, 158, 11, 0.0)' }
            ]
          }
        },
        emphasis: {
          focus: 'series'
        }
      }
    ]
  };
});

function reloadData() {
  router.visit(`/analytics?period=${selectedPeriod.value}`, {
    preserveScroll: true,
  });
}

function exportData(format) {
  window.location.href = `/analytics/export?period=${selectedPeriod.value}&format=${format}`;
}

function getPerformanceClass(value) {
  if (value >= 90) return 'metric-value--excellent';
  if (value >= 75) return 'metric-value--good';
  if (value >= 60) return 'metric-value--fair';
  return 'metric-value--poor';
}

function getPerformanceColor(value) {
  if (value >= 90) return '#059669';
  if (value >= 75) return '#3B82F6';
  if (value >= 60) return '#F59E0B';
  return '#DC2626';
}

function getDelayClass(value) {
  if (value <= 5) return 'metric-value--excellent';
  if (value <= 10) return 'metric-value--good';
  if (value <= 20) return 'metric-value--fair';
  return 'metric-value--poor';
}

function getOverrideClass(value) {
  if (value <= 5) return 'metric-value--excellent';
  if (value <= 10) return 'metric-value--good';
  if (value <= 20) return 'metric-value--fair';
  return 'metric-value--poor';
}

function getVarianceClass(value) {
  if (value <= 0) return 'variance-early';
  if (value <= 5) return 'variance-good';
  if (value <= 10) return 'variance-fair';
  return 'variance-late';
}

function getVarianceTone(value) {
  if (value <= 0) return 'ok';
  if (value <= 5) return 'primary';
  if (value <= 10) return 'warn';
  return 'warn';
}

function getVarianceLabel(value) {
  if (value <= 0) return 'Early';
  if (value <= 5) return 'On Time';
  if (value <= 10) return 'Delayed';
  return 'Very Late';
}

function formatCheckpointType(type) {
  return type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatKind(kind) {
  return kind.charAt(0).toUpperCase() + kind.slice(1);
}
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
  letter-spacing: -0.5px;
}

.page-sub {
  font-size: 10px;
  letter-spacing: 1px;
  text-transform: uppercase;
  color: var(--ink3);
  font-weight: 700;
  margin: 0 0 4px;
}

.page-header-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
  flex-wrap: wrap;
  align-items: center;
}

.period-selector {
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
  background: var(--surface);
  cursor: pointer;
  font-family: inherit;
}

.analytics-section {
  margin-bottom: 32px;
}

.analytics-section--half {
  flex: 1;
  margin-bottom: 0;
}

.analytics-row {
  display: flex;
  gap: 16px;
  margin-bottom: 32px;
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 16px;
  letter-spacing: -0.2px;
}

.stats-grid {
  display: grid;
  gap: 12px;
}

.stats-grid--7 {
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
}

.stats-grid--4 {
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.stats-grid--3 {
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.stats-grid--2 {
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
}

.metric-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
}

.metric-label {
  font-size: 11px;
  font-weight: 600;
  color: var(--ink3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.metric-value {
  font-size: 32px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  margin-bottom: 4px;
}

.metric-value--excellent {
  color: #059669;
}

.metric-value--good {
  color: #3B82F6;
}

.metric-value--fair {
  color: #F59E0B;
}

.metric-value--poor {
  color: #DC2626;
}

.metric-sublabel {
  font-size: 11px;
  color: var(--ink4);
  margin-bottom: 12px;
}

.metric-bar {
  height: 6px;
  background: var(--border);
  border-radius: 3px;
  overflow: hidden;
}

.metric-bar-fill {
  height: 100%;
  background: var(--accent);
  border-radius: 3px;
  transition: width 0.5s ease;
}

.table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.analytics-table {
  width: 100%;
  border-collapse: collapse;
}

.analytics-table thead {
  background: var(--panel);
}

.analytics-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: var(--ink3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid var(--border);
}

.analytics-table td {
  padding: 14px 16px;
  font-size: 13px;
  color: var(--ink);
  border-bottom: 1px solid var(--border);
}

.analytics-table tbody tr:last-child td {
  border-bottom: none;
}

.table-label {
  font-weight: 600;
}

.variance-early {
  color: #059669;
  font-weight: 600;
}

.variance-good {
  color: #3B82F6;
  font-weight: 600;
}

.variance-fair {
  color: #F59E0B;
  font-weight: 600;
}

.variance-late {
  color: #DC2626;
  font-weight: 600;
}

.pattern-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px;
}

.pattern-item {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 14px;
}

.pattern-kind {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 4px;
  text-transform: capitalize;
}

.pattern-count {
  font-size: 20px;
  font-weight: 700;
  color: var(--accent);
  margin-bottom: 2px;
}

.pattern-pax {
  font-size: 11px;
  color: var(--ink3);
}

.chart-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 20px;
  min-height: 400px;
}

.chart {
  width: 100%;
  height: 400px;
}
</style>
