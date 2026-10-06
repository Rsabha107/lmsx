<template>
  <app-layout>
    <!-- No Active Event State -->
    <div v-if="!hasActiveEvent" class="empty-state-full">
      <div class="empty-state-icon">📅</div>
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">
        Please select an event from the dropdown above to view the jobs queue.
      </p>
    </div>

    <!-- Active Event Content -->
    <div v-else class="jobs-page">
    <div class="page-header">
      <div style="display: flex; align-items: center; gap: 12px;">
        <div>
          <p class="page-sub">Live execution · {{ schedule.length }} jobs</p>
          <h1 class="page-title">Jobs</h1>
        </div>
        <RefreshButton :only="['schedule']" @refresh="handleRefresh" />
      </div>
      <div class="page-header-actions">
        <Button v-if="false" variant="secondary" size="sm">
          <template #icon><svg-icon name="filter" :size="14" /></template>
          Filter
        </Button>
        <Button v-if="false" variant="primary" size="sm">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New job
        </Button>
      </div>
    </div>

    <!-- Quick filters -->
    <div class="quick-filters">
      <div class="quick-filter-section">
        <span class="quick-filter-label">Date:</span>
        <button 
          v-for="option in dateOptions" 
          :key="option.value"
          :class="['quick-filter-btn', dateFilter === option.value ? 'quick-filter-btn--active' : '']"
          @click="dateFilter = option.value">
          {{ option.label }}
        </button>
      </div>

      <div class="quick-filter-section">
        <span class="quick-filter-label">Resource:</span>
        <button 
          :class="['quick-filter-btn', resourceFilter === 'all' ? 'quick-filter-btn--active' : '']"
          @click="resourceFilter = 'all'; selectedResource = ''">
          All
        </button>
        <button 
          v-for="type in resourceTypes" 
          :key="type.value"
          :class="['quick-filter-btn', resourceFilter === type.value ? 'quick-filter-btn--active' : '']"
          @click="resourceFilter = type.value; selectedResource = ''">
          {{ type.label }}
        </button>
        <select 
          v-if="resourceFilter !== 'all'"
          v-model="selectedResource" 
          class="resource-select-mini"
          @change="applyResourceFilter">
          <option value="">{{ resourceFilter === 'status' ? 'All Statuses' : resourceFilter === 'kind' ? 'All Kinds' : `All ${resourceFilter}s` }}</option>
          <option v-for="resource in resourceOptions" :key="resource" :value="resource">
            {{ resourceFilter === 'status' ? statusLabel(resource) : resourceFilter === 'kind' ? kindLabel(resource) : resource }}
          </option>
        </select>
      </div>

      <div class="job-search">
        <svg-icon name="search" :size="14" />
        <input
          v-model="searchQuery"
          type="search"
          class="job-search-input"
          placeholder="Search jobs, teams, flights, drivers, venues…"
          aria-label="Search jobs"
        />
        <button v-if="searchQuery" type="button" class="job-search-clear" aria-label="Clear search" @click="searchQuery = ''">
          <svg-icon name="x" :size="12" />
        </button>
      </div>

      <button v-if="jobColumnsActive" type="button" class="quick-filter-btn" @click="jobColumns.clearAll()">
        Clear column sort &amp; filters
      </button>
    </div>

    <job-day-timeline
      :jobs="scopedJobs"
      :selected-job-id="selectedJob?.id"
      v-model:selected-date="statsDate"
      @select="selectJob"
    />

    <job-stats-panel
      :jobs="scopedJobs"
      :kind="activeKind"
      :day-picker="false"
      v-model:selected-date="statsDate"
    />

    <div class="jobs-layout" :class="{ 'jobs-layout--full': !selectedJob }">
      <!-- Job list -->
      <div :class="['jobs-list-card', { 'jobs-list-card--selectable': canDeleteJobs }]">
        <div v-if="canDeleteJobs && selectedVisibleJobs.length" class="job-bulk-bar">
          <span class="job-bulk-count">{{ selectedVisibleJobs.length }} selected</span>
          <Button variant="secondary" size="sm" @click="selectedJobIds = new Set()">Clear</Button>
          <Button
            variant="primary"
            size="sm"
            style="background: var(--danger); border-color: var(--danger);"
            @click="promptDeleteJobs(selectedVisibleJobs)"
          >Delete selected</Button>
        </div>
        <div class="job-list-header">
          <div v-if="canDeleteJobs" class="jl-col-select">
            <input
              type="checkbox"
              :checked="allVisibleSelected"
              :indeterminate="selectedVisibleJobs.length > 0 && !allVisibleSelected"
              :disabled="filtered.length === 0"
              aria-label="Select all visible jobs"
              @change="toggleSelectAll"
            />
          </div>
          <div class="jl-col-job">
            <ColumnFilter label="Job" column="job" :state="jobColumns" :rows="listedJobs" :sort-labels="dateSortLabels" count-unit="job" />
          </div>
          <div class="jl-col-stage">
            <ColumnFilter label="Stage" column="stage" :state="jobColumns" :rows="listedJobs" count-unit="job" />
          </div>
          <div class="jl-col-route">
            <ColumnFilter label="Team · Route" column="team" :state="jobColumns" :rows="listedJobs" count-unit="job" />
          </div>
          <div class="jl-col-progress">
            <ColumnFilter label="Progress" column="progress" :state="jobColumns" :rows="listedJobs" :filterable="false" :sort-labels="numberSortLabels" />
          </div>
          <div class="jl-col-eta">
            <ColumnFilter label="ETA" column="eta" :state="jobColumns" :rows="listedJobs" :filterable="false" :sort-labels="dateSortLabels" align="right" />
          </div>
          <div class="jl-col-alerts">
            <ColumnFilter label="Alerts" column="alerts" :state="jobColumns" :rows="listedJobs" :filterable="false" :sort-labels="numberSortLabels" align="center" />
          </div>
        </div>
        <div class="jobs-list-scroll">
          <!-- Empty State -->
          <div v-if="filtered.length === 0" class="jobs-empty-state">
            <div class="empty-state-icon">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
              </svg>
            </div>
            <h3 class="empty-state-title">No jobs found</h3>
            <p class="empty-state-message">
              {{ schedule.length === 0 ? 'There are no jobs scheduled yet.' : 'No jobs match the current filters.' }}
            </p>
            <Button v-if="jobColumnsActive" variant="secondary" size="sm" @click="jobColumns.clearAll()">Clear column filters</Button>
          </div>

          <!-- Job Items -->
          <div
            v-for="job in filtered" :key="job.id"
            @click="selectJob(job)"
            :class="['job-item', selectedJob?.id === job.id ? 'job-item--active' : '']"
          >
            <div v-if="canDeleteJobs" class="jl-col-select" @click.stop>
              <input
                type="checkbox"
                :checked="selectedJobIds.has(job.db_id)"
                :aria-label="`Select ${job.id}`"
                @change="toggleJobSelection(job)"
              />
            </div>
            <div class="jl-col-job">
              <span class="jl-job-id" :title="job.id">{{ job.id }}</span>
              <div style="display: flex; align-items: center; gap: 4px; flex-wrap: nowrap;">
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
                <flag-icon :code="job.country_code" :fallback="job.flag" />
                <span class="jl-team">{{ job.team }}</span>
                <span
                  v-if="job.kind"
                  class="jl-job-phase"
                  :class="`jl-job-phase--${job.kind}`"
                >{{ job.kind }}<template v-if="job.match?.match_number"> {{ job.match.match_number }}</template></span>
                <span v-if="job.functional_area" class="jl-fa-badge">{{ job.functional_area }}</span>
                <span
                  v-if="openIssueCount(job)"
                  class="jl-issue-badge"
                  :title="`${openIssueCount(job)} unresolved issue(s) reported from the field`"
                >⚑ {{ openIssueCount(job) }}</span>
              </div>
              <span v-if="job.match?.lineup" class="jl-lineup" :title="job.match.lineup">{{ job.match.lineup }}</span>
              <span
                v-if="isFlightJob(job)"
                class="jl-flight"
                :title="flightTitle(job.flight)"
              >
                <span class="jl-flight-no">{{ job.flight.is_bus ? 'By road' : (job.flight.flight_number || 'Flight TBC') }}</span>
                <template v-if="flightRoute(job.flight)"> · {{ flightRoute(job.flight) }}</template>
                <template v-if="job.flight.scheduled_time"> · {{ job.flight.direction === 'arrival' ? 'Arr' : 'Dep' }} <strong>{{ job.flight.scheduled_time }}</strong></template>
                <span v-if="job.flight.actual_time" class="jl-flight-ok"> · {{ job.flight.direction === 'arrival' ? 'Landed' : 'Took off' }} {{ job.flight.actual_time }}</span>
                <span v-else-if="job.flight.estimated_time && job.flight.estimated_time !== job.flight.scheduled_time" class="jl-flight-warn"> · Est {{ job.flight.estimated_time }}</span>
              </span>
              <span class="jl-route" :title="`${formatJobFromLocation(job)} → ${formatJobToLocation(job)}`">{{ formatJobFromLocation(job) }} → {{ formatJobToLocation(job) }}</span>
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
            <flag-icon :code="selectedJob.country_code" :fallback="selectedJob.flag" />
            <div>
              <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap; margin-bottom: 2px;">
                <div class="detail-id">{{ selectedJob.id }} · {{ selectedJob.team }}</div>
                <span v-if="selectedJob.event_name" class="detail-event-badge">{{ selectedJob.event_code || selectedJob.event_name }}</span>
                <span v-if="selectedJob.functional_area" class="detail-fa-badge">{{ formatFunctionalArea(selectedJob.functional_area) }}</span>
              </div>
            </div>
            <status-pill :tone="statusTone(selectedJob.status)" :dot="true" size="sm">
              {{ statusLabel(selectedJob.status) }}
            </status-pill>
            <div class="detail-actions">
              <Button
                v-if="canCancelJob(selectedJob)"
                variant="secondary"
                size="sm"
                style="color: var(--danger);"
                @click="promptCancelJob">Cancel Job</Button>
              <Button
                v-if="canReinstateJob(selectedJob)"
                variant="primary"
                size="sm"
                @click="promptReinstateJob">Reinstate</Button>
              <Button
                v-if="selectedJob.status === 'in-progress'"
                variant="secondary"
                size="sm"
                @click="promptRevertJob">Mark Scheduled</Button>
              <Button
                v-else-if="canStartJob(selectedJob)"
                variant="primary"
                size="sm"
                @click="promptStartJob">Start Job</Button>
              <Button v-if="canOverride && selectedJob.status !== 'cancelled'" variant="primary" size="sm" @click="openOverrideModal">Override</Button>
              <Button
                v-if="canDeleteJobs"
                variant="secondary"
                size="sm"
                style="color: var(--danger);"
                @click="promptDeleteJobs([selectedJob])"
              >Delete</Button>
            </div>
          </div>
          <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 14px; flex-wrap: wrap;">
            <span v-if="selectedJob.kind" class="detail-kind-badge" :class="`detail-kind-badge--${selectedJob.kind}`">{{ selectedJob.kind }}</span>
            <span v-if="selectedJob.date" class="detail-date">{{ formatDateLong(selectedJob.date) }}</span>
            <div class="detail-subtitle" style="margin: 0;">
              {{ formatJobFromLocation(selectedJob) }} → {{ formatJobToLocation(selectedJob) }}<template v-if="selectedJob.flight?.flight_number && !selectedJob.flight.is_bus"> · ✈ {{ selectedJob.flight.flight_number }}</template> · {{ selectedJob.vehicle }}<template v-if="selectedJob.units?.length"> +{{ selectedJob.units.length }}</template> · {{ selectedJob.pax }} pax
            </div>
          </div>

          <div :class="['detail-stats', (selectedJob.status === 'completed' && timeVariance) || selectedJob.updated_at ? 'detail-stats--five' : '']">
            <mini-stat
              label="Pickup"
              :value="selectedJob.pickup || '--:--'"
              :title="selectedJob.pickup_checkpoint ? `First checkpoint: ${selectedJob.pickup_checkpoint}` : 'Planned pickup time'"
            />
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

        <!-- Field-reported issues -->
        <div v-if="jobIssues.length" class="detail-card">
          <div class="issues-head">
            <span class="issues-title">Reported issues</span>
            <span class="issues-count">{{ openIssues.length }} open · {{ jobIssues.length }} total</span>
          </div>
          <div v-for="issue in jobIssues" :key="issue.id" class="issue-row" :class="{ 'issue-row--resolved': issue.resolved_at }">
            <span :class="['issue-dot', `issue-dot--${issue.severity}`]"></span>
            <div class="issue-main">
              <div class="issue-label">
                {{ issue.label }}
                <span v-if="issue.resolved_at" class="issue-resolved-badge">RESOLVED</span>
              </div>
              <div v-if="issue.notes" class="issue-notes">{{ issue.notes }}</div>
              <div class="issue-meta">
                {{ issue.reported_by }} · {{ issue.reported_at }}
                <template v-if="issue.resolved_at"> · resolved {{ issue.resolved_at }}</template>
              </div>
            </div>
            <Button
              v-if="!issue.resolved_at"
              variant="secondary"
              size="sm"
              :disabled="resolvingIssueId === issue.id"
              @click="resolveIssue(issue)"
            >
              {{ resolvingIssueId === issue.id ? 'Resolving…' : 'Resolve' }}
            </Button>
          </div>
        </div>

        <div class="detail-grid">
          <!-- Checkpoint timeline -->
          <div v-if="selectedJob.checkpoints && selectedJob.checkpoints.length" class="checkpoint-section">
            <div class="checkpoint-content">
              <CheckpointTimeline :checkpoints="selectedJob.checkpoints" />
            </div>
          </div>

          <div class="side-column">
          <!-- Crew card -->
          <div class="crew-section">
            <div class="checkpoint-header">
              <h3 class="section-title">Crew</h3>
              <span class="section-kicker">On this job</span>
            </div>
            <div class="crew-list">
              <div v-for="(person, label) in crewMembers" :key="label" class="crew-item">
                <div :class="['crew-avatar', { 'crew-avatar--vehicle': person.vehicle }]">
                  <svg v-if="person.vehicle" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="13" rx="2"/><path d="M3 11h18"/><circle cx="7.5" cy="19.5" r="1.5"/><circle cx="16.5" cy="19.5" r="1.5"/></svg>
                  <template v-else>{{ getInitials(person.name) }}</template>
                </div>
                <div class="crew-info">
                  <div class="crew-name">{{ person.name }}</div>
                  <div class="crew-role">{{ label }}<template v-if="person.detail"> · {{ person.detail }}</template></div>
                  <a v-if="person.phone" :href="`tel:${person.phone}`" class="crew-phone">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                    </svg>
                    {{ person.phone }}
                  </a>
                </div>
                <status-pill v-if="!person.vehicle" tone="ok" :dot="true" size="sm">On shift</status-pill>
              </div>
            </div>
          </div>

          <div class="detail-card">
            <div class="info-head">
              <h3 class="section-title">Job</h3>
              <span class="section-kicker">{{ selectedJob.id }}</span>
            </div>
            <dl class="info-list">
              <dt>Movement</dt>
              <dd class="info-mono">{{ selectedJob.job_info?.movement_code || '—' }}</dd>
              <dt>Plan</dt>
              <dd>
                {{ selectedJob.job_info?.plan_name || '—' }}
                <span v-if="selectedJob.job_info?.plan_code" class="info-mono info-muted"> · {{ selectedJob.job_info.plan_code }}</span>
              </dd>
              <dt>Sequence</dt>
              <dd>{{ selectedJob.job_info?.sequence || '—' }}</dd>
              <dt>Generated</dt>
              <dd>{{ formatStamp(selectedJob.job_info?.generated_at) }}</dd>
              <template v-if="selectedJob.job_info?.dispatched_at">
                <dt>Dispatched</dt>
                <dd>{{ formatStamp(selectedJob.job_info.dispatched_at) }}</dd>
              </template>
              <template v-if="selectedJob.job_info?.started_at">
                <dt>Started</dt>
                <dd>{{ formatStamp(selectedJob.job_info.started_at) }}</dd>
              </template>
              <template v-if="selectedJob.job_info?.completed_at">
                <dt>Completed</dt>
                <dd>{{ formatStamp(selectedJob.job_info.completed_at) }}</dd>
              </template>
              <template v-if="selectedJob.job_info?.notes">
                <dt>Notes</dt>
                <dd class="info-notes">{{ selectedJob.job_info.notes }}</dd>
              </template>
            </dl>
          </div>

          <div v-if="selectedJob.match" class="detail-card">
            <div class="info-head">
              <h3 class="section-title">Match</h3>
              <span class="section-kicker">{{ selectedJob.match.match_number }}</span>
            </div>
            <div class="info-lineup">
              {{ selectedJob.match.team1 }} <span class="info-vs">vs</span> {{ selectedJob.match.team2 }}
            </div>
            <dl class="info-list">
              <dt>Kick-off</dt>
              <dd>{{ formatStamp(selectedJob.match.kick_off) }}</dd>
              <template v-if="selectedJob.match.gates_opening">
                <dt>Gates open</dt>
                <dd>{{ selectedJob.match.gates_opening }}</dd>
              </template>
              <dt>Venue</dt>
              <dd>{{ selectedJob.match.venue?.name || '—' }}</dd>
              <dt>Stage</dt>
              <dd>{{ selectedJob.match.stage || '—' }}</dd>
            </dl>
          </div>

          <div v-if="isFlightJob(selectedJob)" class="detail-card">
            <div class="info-head">
              <h3 class="section-title">{{ selectedJob.flight.direction === 'arrival' ? 'Arrival flight' : 'Departure flight' }}</h3>
              <span class="section-kicker info-mono">{{ selectedJob.flight.is_bus ? 'By road' : (selectedJob.flight.flight_number || 'TBC') }}</span>
            </div>
            <div v-if="flightRoute(selectedJob.flight)" class="info-lineup">{{ flightRoute(selectedJob.flight) }}</div>
            <dl class="info-list">
              <dt>{{ selectedJob.flight.direction === 'arrival' ? 'Scheduled arrival' : 'Scheduled departure' }}</dt>
              <dd>
                <template v-if="selectedJob.flight.scheduled_time">
                  <strong>{{ selectedJob.flight.scheduled_time }}</strong> · {{ selectedJob.flight.scheduled_date }}
                </template>
                <template v-else>—</template>
              </dd>
              <template v-if="selectedJob.flight.estimated_time">
                <dt>Estimated</dt>
                <dd :class="{ 'info-warn': selectedJob.flight.estimated_time !== selectedJob.flight.scheduled_time }">{{ selectedJob.flight.estimated_time }}</dd>
              </template>
              <template v-if="selectedJob.flight.actual_time">
                <dt>{{ selectedJob.flight.direction === 'arrival' ? 'Landed' : 'Took off' }}</dt>
                <dd class="info-ok">{{ selectedJob.flight.actual_time }}</dd>
              </template>
              <template v-if="selectedJob.flight.delay_minutes">
                <dt>Delay</dt>
                <dd class="info-warn">+{{ selectedJob.flight.delay_minutes }} min</dd>
              </template>
              <template v-if="selectedJob.flight.terminal || selectedJob.flight.gate">
                <dt>Terminal / gate</dt>
                <dd>{{ [selectedJob.flight.terminal, selectedJob.flight.gate].filter(Boolean).join(' · ') }}</dd>
              </template>
              <template v-if="selectedJob.flight.flight_status">
                <dt>Status</dt>
                <dd style="text-transform: capitalize;">{{ selectedJob.flight.flight_status }}</dd>
              </template>
            </dl>
          </div>
          </div>
        </div>
      </div>
      <div v-else class="job-detail-empty">
        <div v-if="schedule.length === 0" class="job-detail-instructions">
          <div class="instructions-icon">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="12" y1="16" x2="12" y2="12"></line>
              <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
          </div>
          <h3 class="instructions-title">How to Create Jobs</h3>
          <div class="instructions-steps">
            <div class="instruction-step">
              <span class="step-number">1</span>
              <div class="step-content">
                <strong>Create Movement Plans</strong>
                <p>Go to the Plans page and create movement templates for teams</p>
              </div>
            </div>
            <div class="instruction-step">
              <span class="step-number">2</span>
              <div class="step-content">
                <strong>Generate Jobs</strong>
                <p>Use the job generation service to create operations from your plans</p>
              </div>
            </div>
            <div class="instruction-step">
              <span class="step-number">3</span>
              <div class="step-content">
                <strong>Assign Resources</strong>
                <p>Assign drivers, vehicles, and supervisors to each job</p>
              </div>
            </div>
            <div class="instruction-step">
              <span class="step-number">4</span>
              <div class="step-content">
                <strong>Monitor Execution</strong>
                <p>Track job progress and checkpoints in real-time on this page</p>
              </div>
            </div>
          </div>
          <div class="instructions-footer">
            <Button variant="primary" size="sm" @click="router.visit('/plans')">
              <template #icon>
                <svg-icon name="arrow-right" :size="14" style="color: #fff;" />
              </template>
              Go to Plans
            </Button>
          </div>
        </div>
        <div v-else>
          Select a job to view details
        </div>
      </div>
    </div>
    <JobOverrideModal
      :show="showOverrideModal"
      :job="selectedJob"
      :drivers="props.drivers"
      :supervisors="props.supervisors"
      :vehicles="props.vehicles"
      @close="showOverrideModal = false"
      @saved="onOverrideSaved"
    />
    </div>

    <ConfirmModal
      :show="pendingStatusChange !== null"
      :title="pendingStatusChange?.title || ''"
      :message="pendingStatusChange?.message || ''"
      :confirm-label="pendingStatusChange?.confirmLabel || 'Confirm'"
      :tone="pendingStatusChange?.tone || 'primary'"
      :note="pendingStatusChange?.note"
      :processing="statusChanging"
      @close="pendingStatusChange = null"
      @confirm="confirmStatusChange"
    />

    <Modal :show="pendingDelete !== null" max-width="500px" @close="closeDeleteJobs">
      <template #title>{{ pendingDelete?.length === 1 ? 'Delete Job?' : 'Delete Jobs?' }}</template>
      <p style="margin: 0; color: var(--ink2); font-size: 14px;" v-html="deleteMessage"></p>
      <div v-if="deleteNeedsTyping" style="margin-top: 14px;">
        <label for="delete-jobs-confirm" style="display: block; font-size: 13px; color: var(--ink2); margin-bottom: 6px;">
          Type <strong>{{ deleteConfirmPhrase }}</strong> to confirm
        </label>
        <input
          id="delete-jobs-confirm"
          v-model="deleteConfirmText"
          type="text"
          class="override-input"
          autocomplete="off"
          @keydown.enter.prevent="canConfirmDelete && confirmDeleteJobs()"
        />
      </div>
      <p v-if="deleteError" style="margin: 8px 0 0; color: var(--danger); font-size: 12px;">{{ deleteError }}</p>
      <p style="margin: 12px 0 0; color: var(--ink3); font-size: 13px;">This action cannot be undone.</p>
      <template #footer>
        <Button variant="secondary" size="sm" :disabled="deletingJobs" @click="closeDeleteJobs">Cancel</Button>
        <Button
          variant="primary"
          size="sm"
          style="background: var(--danger); border-color: var(--danger);"
          :processing="deletingJobs"
          :disabled="deletingJobs || !canConfirmDelete"
          @click="confirmDeleteJobs"
        >{{ pendingDelete?.length === 1 ? 'Delete Job' : `Delete ${pendingDelete?.length || 0} Jobs` }}</Button>
      </template>
    </Modal>

    <ConfirmModal
      :show="showStatusError"
      title="Cannot Change Job Status"
      :message="statusErrorMessage"
      confirm-label="Got it"
      hide-cancel
      @close="showStatusError = false"
      @confirm="showStatusError = false"
    />
  </app-layout>
</template>

<script setup>
import { useStatusLabels } from '../Composables/useStatusLabels';
import { ref, computed, nextTick, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import MiniStat from '../Components/MiniStat.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import Modal from '../Components/Modal.vue';
import Button from '../Components/Button.vue';
import CheckpointTimeline from '../Components/CheckpointTimeline.vue';
import FlagIcon from '../Components/FlagIcon.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import JobOverrideModal from '../Components/JobOverrideModal.vue';
import { useJobStatusActions, canStartJob, canCancelJob, canReinstateJob } from '../Composables/useJobStatusActions';
import { formatJobFromLocation, formatJobToLocation } from '../Composables/useJobLocations';
import JobStatsPanel from '../Components/JobStatsPanel.vue';
import JobDayTimeline from '../Components/JobDayTimeline.vue';
import ColumnFilter from '../Components/ColumnFilter.vue';
import { useColumnFilters } from '../Composables/useColumnFilters';

const page = usePage();
const hasActiveEvent = computed(() => !!page.props.activeEventId);

const props = defineProps({
  schedule: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  vehicles: { type: Array, default: () => [] },
});

const selectedJob = ref(null);
const {
  pending: pendingStatusChange,
  changing: statusChanging,
  showError: showStatusError,
  errorMessage: statusErrorMessage,
  promptStart: promptStartJob,
  promptRevert: promptRevertJob,
  promptCancel: promptCancelJob,
  promptReinstate: promptReinstateJob,
  confirm: confirmStatusChange,
} = useJobStatusActions(() => selectedJob.value, reloadSelectedJob);
const resolvingIssueId = ref(null);

const jobIssues = computed(() => selectedJob.value?.issues ?? []);
const openIssues = computed(() => jobIssues.value.filter(i => !i.resolved_at));

function openIssueCount(job) {
  return (job.issues ?? []).filter(i => !i.resolved_at).length;
}

function resolveIssue(issue) {
  resolvingIssueId.value = issue.id;

  router.post(`/job-issues/${issue.id}/resolve`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      const currentJobId = selectedJob.value?.id;
      router.reload({
        only: ['schedule'],
        onSuccess: () => {
          if (currentJobId) {
            selectedJob.value = props.schedule.find(j => j.id === currentJobId);
          }
        },
      });
    },
    onFinish: () => {
      resolvingIssueId.value = null;
    },
  });
}

// Filter refs
const dateFilter = ref('all');
const resourceFilter = ref('all');
const selectedResource = ref('');

const dateOptions = [
  { value: 'all', label: 'All' },
  { value: 'today', label: 'Today' },
  { value: 'week', label: 'Week' },
];

const resourceTypes = [
  { value: 'driver', label: 'Driver' },
  { value: 'vehicle', label: 'Vehicle' },
  { value: 'supervisor', label: 'Supervisor' },
  { value: 'status', label: 'Status' },
  { value: 'kind', label: 'Kind' },
];

function kindLabel(k) {
  if (!k) return k;
  return k === 'daily_ops' ? 'Daily Ops' : k.charAt(0).toUpperCase() + k.slice(1);
}

function applyResourceFilter() {
  // Trigger reactivity
}

const statusMap = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'live':        { tone: 'live',    label: 'In Progress' },
  'pending':     { tone: 'primary', label: 'Scheduled' },
  'dispatched':  { tone: 'primary', label: 'Dispatched' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'completed':   { tone: 'ok',      label: 'Done' },
  'cancelled':   { tone: 'neutral', label: 'Cancelled' },
  'queued':      { tone: 'neutral', label: 'Queued' },
  'issue':       { tone: 'warn',    label: 'Issue' },
};
function statusTone(s) { return statusMap[s]?.tone ?? 'neutral'; }
const { statusLabel: sharedStatusLabel } = useStatusLabels();
function statusLabel(s) { return sharedStatusLabel(s, statusMap[s]?.label); }
function stagePillLabel(s) { return statusLabel(s).toLowerCase(); }

function jobStepsText(job) {
  if (job.checkpoints && job.checkpoints.length > 0) {
    const done = job.checkpoints.filter(c => c.state === 'done' || c.status === 'done').length;
    return `${done}/${job.checkpoints.length} steps`;
  }
  return '';
}

function selectJob(job) {
  // Toggle: clicking the already-selected row closes the detail panel
  selectedJob.value = selectedJob.value?.id === job.id ? null : job;
}

// Switching event keeps this page's state, so drop the selection it can't show.
watch(() => page.props.activeEventId, () => {
  selectedJob.value = null;
  statsDate.value = null;
});

// A reload hands over new job objects; follow the selected one or let it go.
watch(() => props.schedule, (schedule) => {
  if (!selectedJob.value) return;
  selectedJob.value = schedule.find(j => j.id === selectedJob.value.id) || null;
});

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
        checkpoint.scheduled_ts && 
        checkpoint.completed_ts) {
      // Use timestamps for accurate calculation (handles dates and midnight crossover)
      const varianceSeconds = checkpoint.completed_ts - checkpoint.scheduled_ts;
      const varianceMinutes = Math.round(varianceSeconds / 60);
      totalVarianceMinutes += varianceMinutes;
      hasVariance = true;
    }
  });
  
  if (!hasVariance) return null;
  
  if (totalVarianceMinutes === 0) return 'On time';
  if (totalVarianceMinutes > 0) return `${totalVarianceMinutes} min late`;
  return `${Math.abs(totalVarianceMinutes)} min early`;
});

const timeVarianceTone = computed(() => {
  if (!timeVariance.value || timeVariance.value === 'On time') return 'ok';
  if (timeVariance.value.endsWith('late')) return 'warn';
  return 'ok'; // Early completion
});

const crewMembers = computed(() => {
  if (!selectedJob.value) return {};
  const crew = {
    Supervisor: {
      name: selectedJob.value.supervisor,
      phone: selectedJob.value.supervisor_phone,
    },
    Driver: {
      name: selectedJob.value.driver,
      phone: selectedJob.value.driver_phone,
    },
    Vehicle: {
      name: selectedJob.value.vehicle,
      detail: selectedJob.value.vehicle_detail,
      vehicle: true,
    },
  };
  (selectedJob.value.extra_supervisors ?? []).forEach((s, i) => {
    crew[`Supervisor ${i + 2}`] = { name: s.name };
  });
  (selectedJob.value.units ?? []).forEach((unit, i) => {
    crew[`Driver ${i + 2}`] = { name: unit.driver || 'Unassigned', phone: unit.driver_phone };
    crew[`Vehicle ${i + 2}`] = { name: unit.vehicle || 'Unassigned', detail: unit.vehicle_detail, vehicle: true };
  });
  return crew;
});

function getInitials(name) {
  return name.split(' ').map(s => s[0]).join('').slice(0, 2);
}

function formatDate(dateString) {
  if (!dateString) return '';

  // Parsed from the string rather than via Date(), which reads a bare
  // 'YYYY-MM-DD' as UTC midnight and can render the previous day.
  const match = String(dateString).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (match) {
    const [, year, month, day] = match;
    return `${day}/${month}/${year}`;
  }

  const date = new Date(dateString);
  if (isNaN(date)) return '';

  const day = String(date.getDate()).padStart(2, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  return `${day}/${month}/${date.getFullYear()}`;
}

function formatDateLong(dateString) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
  return date.toLocaleDateString('en-US', options);
}

// Server sends local "YYYY-MM-DD HH:MM"; built by hand so no timezone shift is applied.
function formatStamp(value) {
  const m = value?.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}:\d{2})/);
  if (!m) return '—';
  const date = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
  const day = date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
  return `${day} · ${m[4]}`;
}

function isFlightJob(job) {
  return !!job?.flight && (job.kind === 'arrival' || job.kind === 'departure');
}

function flightRoute(flight) {
  if (!flight?.origin_airport && !flight?.destination_airport) return '';
  return `${flight.origin_airport || '?'} → ${flight.destination_airport || '?'}`;
}

function flightTitle(flight) {
  const verb = flight.direction === 'arrival' ? 'Scheduled arrival' : 'Scheduled departure';
  return [flight.flight_number, flightRoute(flight), flight.scheduled_time && `${verb} ${flight.scheduled_date} ${flight.scheduled_time}`]
    .filter(Boolean)
    .join(' · ');
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

function formatFunctionalArea(code) {
  const areas = {
    'LOG': 'LOG - Logistics',
    'AND': 'AND - Arrival & Departure',
    'MOB': 'MOB - Mobility'
  };
  return areas[code] || code;
}

const timeOf = (j) => [j.pickup, j.dep].find((t) => t && t !== '--:--') || null;
const startOf = (j) => (j.date ? `${j.date} ${timeOf(j) ?? '99:99'}` : null);
const etaOf = (j) => (j.date && j.arr && j.arr !== '--:--' ? `${j.date} ${j.arr}` : null);

const dateSortLabels = { asc: 'Earliest first', desc: 'Latest first' };
const numberSortLabels = { asc: 'Smallest first', desc: 'Largest first' };

const jobColumns = useColumnFilters({
  job: { value: (j) => (j.date ? formatDate(j.date) : ''), sort: startOf },
  stage: { value: (j) => statusLabel(j.status) },
  team: { value: (j) => j.team || '' },
  progress: { sort: (j) => jobProgress(j) },
  eta: { sort: etaOf },
  alerts: { sort: (j) => j.alerts || 0 },
});
const jobColumnsActive = jobColumns.active;

// Jobs in view before any header filter; header filters list their values from here.
const listedJobs = computed(() => {
  let jobs = scopedJobs.value;

  // The stats panel's day selection narrows the list as well, so chart and
  // table always describe the same set of jobs.
  if (statsDate.value) {
    jobs = jobs.filter(j => j.date === statsDate.value);
  }

  // Sort by date and pickup time (asc, undated last)
  return [...jobs].sort((a, b) => (startOf(a) ?? '\uffff').localeCompare(startOf(b) ?? '\uffff'));
});

const filtered = computed(() => jobColumns.apply(listedJobs.value));

const statsDate = ref(null);

const activeKind = computed(() =>
  resourceFilter.value === 'kind' ? selectedResource.value : null);

const scopedJobs = computed(() => {
  let jobs = props.schedule;

  // Date filter
  if (dateFilter.value !== 'all') {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    
    if (dateFilter.value === 'today') {
      jobs = jobs.filter(j => {
        if (!j.date) return false;
        const jobDate = new Date(j.date);
        const jobDay = new Date(jobDate.getFullYear(), jobDate.getMonth(), jobDate.getDate());
        return jobDay.getTime() === today.getTime();
      });
    } else if (dateFilter.value === 'week') {
      const weekStart = new Date(today);
      weekStart.setDate(today.getDate() - today.getDay()); // Start of week (Sunday)
      const weekEnd = new Date(weekStart);
      weekEnd.setDate(weekStart.getDate() + 6); // End of week (Saturday)
      
      jobs = jobs.filter(j => {
        if (!j.date) return false;
        const jobDate = new Date(j.date);
        const jobDay = new Date(jobDate.getFullYear(), jobDate.getMonth(), jobDate.getDate());
        return jobDay >= weekStart && jobDay <= weekEnd;
      });
    }
  }
  
  // Resource filter
  if (resourceFilter.value !== 'all' && selectedResource.value) {
    if (resourceFilter.value === 'driver') {
      jobs = jobs.filter(j => j.driver === selectedResource.value);
    } else if (resourceFilter.value === 'vehicle') {
      jobs = jobs.filter(j => j.vehicle === selectedResource.value);
    } else if (resourceFilter.value === 'supervisor') {
      jobs = jobs.filter(j => j.supervisor === selectedResource.value);
    } else if (resourceFilter.value === 'status') {
      jobs = jobs.filter(j => j.status === selectedResource.value);
    } else if (resourceFilter.value === 'kind') {
      jobs = jobs.filter(j => j.kind === selectedResource.value);
    }
  }

  // Every word must appear somewhere on the job.
  const terms = searchQuery.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
  if (terms.length) {
    jobs = jobs.filter(j => {
      const text = searchIndex.value.get(j.id) ?? '';
      return terms.every(t => text.includes(t));
    });
  }

  return jobs;
});

const searchQuery = ref('');

function collectText(value, out, depth = 0) {
  if (value == null || depth > 4) return;
  if (typeof value === 'string' || typeof value === 'number') {
    out.push(String(value));
  } else if (typeof value === 'object') {
    for (const v of Object.values(value)) collectText(v, out, depth + 1);
  }
}

// Every value on the job (nested flight, match, crew and checkpoints too), plus
// the labels the list and detail panel derive from them.
const searchIndex = computed(() => {
  const index = new Map();
  for (const job of props.schedule) {
    const parts = [
      statusLabel(job.status),
      kindLabel(job.kind),
      formatJobFromLocation(job),
      formatJobToLocation(job),
      job.date ? formatDate(job.date) : '',
    ];
    collectText(job, parts);
    index.set(job.id, parts.join(' ').toLowerCase());
  }
  return index;
});

const resourceOptions = computed(() => {
  if (resourceFilter.value === 'driver') {
    return [...new Set(props.schedule.map(j => j.driver).filter(Boolean))].sort();
  }
  if (resourceFilter.value === 'vehicle') {
    return [...new Set(props.schedule.map(j => j.vehicle).filter(Boolean))].sort();
  }
  if (resourceFilter.value === 'supervisor') {
    return [...new Set(props.schedule.map(j => j.supervisor).filter(Boolean))].sort();
  }
  if (resourceFilter.value === 'status') {
    return [...new Set(props.schedule.map(j => j.status).filter(Boolean))];
  }
  if (resourceFilter.value === 'kind') {
    return [...new Set(props.schedule.map(j => j.kind).filter(Boolean))].sort();
  }
  return [];
});

// Override modal
const canOverride = computed(() => page.props.auth?.can?.['jobs.override'] === true);

// Job deletion (single from the detail panel, many via the list checkboxes)
const canDeleteJobs = computed(() => page.props.auth?.can?.['plans.manage'] === true);
const selectedJobIds = ref(new Set());
// Only what's on screen counts, so a filter change never deletes hidden jobs.
const selectedVisibleJobs = computed(() => filtered.value.filter(j => selectedJobIds.value.has(j.db_id)));
const allVisibleSelected = computed(() =>
  filtered.value.length > 0 && selectedVisibleJobs.value.length === filtered.value.length);
const pendingDelete = ref(null);
const deletingJobs = ref(false);
const deleteConfirmText = ref('');
const deleteError = ref('');

const isStartedJob = (j) => ['in-progress', 'completed'].includes(j.status);
// Mirrors LmsController::destroyJobs: typing is only demanded when field records would be lost.
const deleteNeedsTyping = computed(() => (pendingDelete.value || []).some(isStartedJob));
const deleteConfirmPhrase = computed(() =>
  pendingDelete.value?.length === 1 ? pendingDelete.value[0].id : 'DELETE');
const canConfirmDelete = computed(() =>
  !deleteNeedsTyping.value || deleteConfirmText.value.trim() === deleteConfirmPhrase.value);

function toggleJobSelection(job) {
  const next = new Set(selectedJobIds.value);
  next.has(job.db_id) ? next.delete(job.db_id) : next.add(job.db_id);
  selectedJobIds.value = next;
}

function toggleSelectAll() {
  selectedJobIds.value = allVisibleSelected.value
    ? new Set()
    : new Set(filtered.value.map(j => j.db_id));
}

function promptDeleteJobs(jobs) {
  if (!jobs.length) return;
  deleteConfirmText.value = '';
  deleteError.value = '';
  pendingDelete.value = [...jobs];
}

function closeDeleteJobs() {
  if (!deletingJobs.value) pendingDelete.value = null;
}

const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c => `&#${c.charCodeAt(0)};`);

const deleteMessage = computed(() => {
  const jobs = pendingDelete.value || [];
  const started = jobs.filter(isStartedJob).length;
  const subject = jobs.length === 1
    ? `job <strong>${escapeHtml(jobs[0].id)}</strong>`
    : `<strong>${jobs.length}</strong> jobs`;

  return `This will permanently delete ${subject} together with their checkpoints, captured photos/signatures and field issues.<br><br>`
    + 'The movements are kept and jobs can be generated for them again.'
    + (started
      ? `<br><br><strong style="color: var(--danger);">${started} ${started === 1 ? 'job is' : 'jobs are'} in progress or completed</strong> — their field records will be lost.`
      : '');
});

function confirmDeleteJobs() {
  const ids = (pendingDelete.value || []).map(j => j.db_id);
  if (!ids.length || !canConfirmDelete.value) return;

  deletingJobs.value = true;
  router.delete('/jobs', {
    data: { ids, confirm: deleteConfirmText.value.trim() },
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      pendingDelete.value = null;
      selectedJobIds.value = new Set();
    },
    onError: (errors) => {
      deleteError.value = errors.confirm || 'Failed to delete the selected job(s).';
    },
    onFinish: () => {
      deletingJobs.value = false;
    },
  });
}
const showOverrideModal = ref(false);

function openOverrideModal() {
  showOverrideModal.value = true;
}

function onOverrideSaved() {
  showOverrideModal.value = false;
  reloadSelectedJob();
}

// Refresh the queue and keep the same job open.
function reloadSelectedJob() {
  const currentJobId = selectedJob.value?.id;
  router.reload({
    only: ['schedule'],
    onSuccess: () => {
      if (currentJobId) selectedJob.value = props.schedule.find(j => j.id === currentJobId);
    },
  });
}
</script>

<style scoped>
.empty-state-full {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;
}
.empty-state-text {
  font-size: 14px;
  color: var(--ink3);
  max-width: 400px;
}

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

.jobs-page { padding-bottom: 5px; }

.jobs-layout {
  display: grid; grid-template-columns: 580px 1fr; gap: 12px;
  min-height: 0; flex: 1;
  height: calc(100vh - 240px);
  max-height: 800px;
  margin-top: 24px;
}
.jobs-layout--full {
  grid-template-columns: 1fr;
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

.jobs-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  flex: 1;
  min-height: 300px;
}

.empty-state-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  background: var(--panel);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 16px;
  color: var(--ink3);
}

.empty-state-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 6px;
}

.empty-state-message {
  font-size: 13px;
  color: var(--ink3);
  margin: 0;
  max-width: 400px;
}

.job-list-header {
  display: grid;
  grid-template-columns: minmax(78px, 1fr) minmax(88px, 0.8fr) minmax(100px, 3fr) minmax(108px, 1.2fr) minmax(62px, 0.7fr) minmax(52px, 0.6fr);
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
  grid-template-columns: minmax(78px, 1fr) minmax(88px, 0.8fr) minmax(100px, 3fr) minmax(108px, 1.2fr) minmax(62px, 0.7fr) minmax(52px, 0.6fr);
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

.jobs-list-card--selectable .job-list-header,
.jobs-list-card--selectable .job-item {
  grid-template-columns: 16px minmax(78px, 1fr) minmax(88px, 0.8fr) minmax(100px, 3fr) minmax(108px, 1.2fr) minmax(62px, 0.7fr) minmax(52px, 0.6fr);
}
.jl-col-select { display: flex; align-items: center; justify-content: center; }
.jl-col-select input { margin: 0; cursor: pointer; }
.job-bulk-bar {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  background: var(--accent-soft);
}
.job-bulk-count { font-size: 12px; font-weight: 700; color: var(--ink); margin-right: auto; }

.jl-col-job {
  display: flex; flex-direction: column; gap: 2px; min-width: 0;
}
.jl-job-id {
  font-size: 10px; font-weight: 700; color: var(--ink);
  font-family: var(--font-mono, monospace);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
  max-width: 100%;
}
.jl-job-type {
  font-size: 10px; color: var(--ink3); font-weight: 500;
  white-space: nowrap;
}
.jl-job-phase {
  font-size: 9px; color: var(--ink3); font-weight: 700;
  text-transform: capitalize;
  white-space: nowrap;
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--panel);
  flex-shrink: 0;
}
.jl-job-phase--arrival { background: var(--ok-soft); color: var(--ok); }
.jl-job-phase--departure { background: var(--danger-soft); color: var(--danger); }
.jl-job-phase--transfer { background: var(--accent-soft); color: var(--accent-fg); }
.jl-job-phase--match { background: #fef3c7; color: #92400e; }
.jl-job-phase--training { background: #ede9fe; color: #6d28d9; }
.jl-job-phase--daily_ops { background: var(--panel); color: var(--ink3); }

.jl-lineup {
  font-size: 10.5px; font-weight: 600; color: var(--ink2);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  display: block; max-width: 100%;
}

.jl-flight {
  font-size: 10.5px; color: var(--ink3);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  display: block; max-width: 100%;
}
.jl-flight strong { color: var(--ink); font-family: var(--font-mono, monospace); }
.jl-flight-no { font-family: var(--font-mono, monospace); font-weight: 700; color: var(--ink2); }
.jl-flight-ok { color: var(--ok); font-weight: 600; }
.jl-flight-warn { color: var(--warn); font-weight: 600; }

.jl-job-date {
  font-size: 9px;
  font-weight: 600;
  color: var(--ink3);
  background: var(--panel);
  padding: 1px 4px;
  border-radius: 3px;
  font-family: var(--font-mono, monospace);
  white-space: nowrap;
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
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
  max-width: 100%;
}

.jl-fa-badge {
  font-size: 9px;
  font-weight: 700;
  color: var(--green);
  background: rgba(34, 197, 94, 0.1);
  padding: 1px 5px;
  border-radius: 3px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.jl-issue-badge {
  font-size: 9px;
  font-weight: 700;
  color: #b91c1c;
  background: rgba(239, 68, 68, 0.12);
  padding: 1px 5px;
  border-radius: 3px;
  letter-spacing: 0.3px;
}

.issues-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
}
.issues-title { font-size: 13px; font-weight: 700; color: var(--ink); }
.issues-count { font-size: 11px; color: var(--ink3); }

.issue-row {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-top: 1px solid var(--border);
}
.issue-row--resolved { opacity: 0.55; }

.issue-dot {
  width: 8px; height: 8px; border-radius: 50%;
  margin-top: 5px; flex-shrink: 0;
  background: var(--ink4);
}
.issue-dot--danger { background: var(--danger); }
.issue-dot--warn { background: var(--warn); }

.issue-main { flex: 1; min-width: 0; }
.issue-label { font-size: 13px; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 6px; }
.issue-resolved-badge {
  font-size: 9px; font-weight: 700; letter-spacing: 0.3px;
  color: var(--ink3); background: var(--panel);
  border: 1px solid var(--border); border-radius: 3px; padding: 1px 4px;
}
.issue-notes { font-size: 12px; color: var(--ink2); margin-top: 2px; }
.issue-meta { font-size: 11px; color: var(--ink3); margin-top: 3px; }

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
  /* The panel can run past the fixed-height layout, so the page padding alone wouldn't clear it. */
  padding-bottom: 5px;
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

.info-head { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 10px; }
.info-lineup { font-size: 14px; font-weight: 700; color: var(--ink); margin-bottom: 10px; }
.info-vs { font-weight: 500; color: var(--ink3); margin: 0 4px; }
.info-list {
  display: grid; grid-template-columns: max-content 1fr; gap: 6px 14px;
  margin: 0; font-size: 12.5px;
}
.info-list dt { color: var(--ink3); font-weight: 500; }
.info-list dd { margin: 0; color: var(--ink); min-width: 0; overflow-wrap: anywhere; }
.info-mono { font-family: var(--mono, ui-monospace, monospace); font-size: 11.5px; }
.info-muted { color: var(--ink3); }
.info-notes { white-space: pre-wrap; }
.info-warn { color: var(--warn); font-weight: 600; }
.info-ok { color: var(--ok); font-weight: 600; }

.job-detail-empty {
  background: var(--panel); border: 1px dashed var(--border);
  border-radius: 10px; padding: 60px 40px;
  display: flex; align-items: center; justify-content: center;
  color: var(--ink4); font-size: 13px;
  align-self: start;
}

.job-detail-instructions {
  text-align: center;
  max-width: 500px;
  width: 100%;
}

.instructions-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 16px;
  color: var(--accent);
}

.instructions-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--ink);
  margin: 0 0 24px;
}

.instructions-steps {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 24px;
  text-align: left;
}

.instruction-step {
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.step-number {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.step-content {
  flex: 1;
}

.step-content strong {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
}

.step-content p {
  font-size: 12px;
  color: var(--ink3);
  margin: 0;
  line-height: 1.5;
}

.instructions-footer {
  display: flex;
  justify-content: center;
  padding-top: 16px;
  border-top: 1px solid var(--border);
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
/* Codes run longer than a trigram (EGY-17, BHR-V), so the badge grows
   sideways from a square minimum rather than wrapping. */
.team-badge {
  min-width: 34px; height: 34px; padding: 0 6px; border-radius: 7px;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700; flex-shrink: 0; white-space: nowrap;
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
  background: var(--accent-soft, var(--accent-ring));
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-fa-badge {
  font-size: 10px;
  font-weight: 700;
  color: var(--green);
  background: rgba(34, 197, 94, 0.1);
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-kind-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 2px 8px;
  border-radius: 4px;
  text-transform: capitalize;
  letter-spacing: 0.3px;
  flex-shrink: 0;
}
.detail-kind-badge--arrival { background: var(--ok-soft); color: var(--ok); }
.detail-kind-badge--departure { background: var(--danger-soft); color: var(--danger); }
.detail-kind-badge--transfer { background: var(--accent-soft); color: var(--accent-fg); }
.detail-kind-badge--match { background: #fef3c7; color: #92400e; border: 1px solid #fbbf24; }
.detail-kind-badge--training { background: #ede9fe; color: #6d28d9; }
.detail-kind-badge--daily_ops { background: var(--panel); color: var(--ink3); }

/* Sits inline beside the kind badge, so it reads as part of that row. */
.detail-date {
  font-size: 11px;
  color: var(--ink3);
  font-weight: 600;
  white-space: nowrap;
}
.detail-subtitle {
  font-size: 12px; color: var(--ink3);
  margin-bottom: 14px;
  min-width: 0;
  flex: 1;
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

.side-column { display: flex; flex-direction: column; gap: 12px; min-width: 0; }

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
.crew-avatar--vehicle { border-radius: 8px; background: var(--live-soft); color: var(--live); }

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
  color: var(--accent-fg, #0F1724);
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

.override-input {
  width: 100%; padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  font-size: 13px; color: var(--ink); font-family: inherit;
  outline: none; box-sizing: border-box;
}
.override-input:focus {
  border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring);
}

/* Quick Filters */
.quick-filters {
  margin-top: 16px;
  display: flex;
  gap: 24px;
  align-items: center;
  flex-wrap: wrap;
}

.quick-filter-section {
  display: flex;
  gap: 8px;
  align-items: center;
}

.quick-filter-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink3);
  white-space: nowrap;
}

.quick-filter-btn {
  padding: 6px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg);
  color: var(--ink2);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.1s;
  font-family: inherit;
  white-space: nowrap;
}

.quick-filter-btn:hover {
  background: var(--panel);
  border-color: var(--ink3);
  color: var(--ink);
}

.quick-filter-btn--active {
  background: var(--accent, #0F1724);
  color: #ffffff;
  border-color: var(--accent, #0F1724);
}

.quick-filter-btn--active:hover {
  background: var(--accent, #0F1724);
  color: #ffffff;
  border-color: var(--accent, #0F1724);
  opacity: 0.9;
}

.resource-select-mini {
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg);
  color: var(--ink1);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  font-family: inherit;
  min-width: 140px;
}

.resource-select-mini:focus {
  outline: none;
  border-color: var(--primary);
}

.job-search {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 280px;
  padding: 0 10px;
  height: 32px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg);
  color: var(--ink3);
}

.job-search:focus-within { border-color: var(--accent); }

.job-search-input {
  flex: 1;
  min-width: 0;
  border: 0;
  outline: none;
  background: transparent;
  color: var(--ink);
  font-size: 12px;
  font-family: inherit;
}

.job-search-input::-webkit-search-cancel-button { display: none; }

.job-search-clear {
  display: flex;
  padding: 2px;
  border: 0;
  background: none;
  color: var(--ink3);
  cursor: pointer;
}

.job-search-clear:hover { color: var(--ink); }

@media (max-width: 768px) {
  .quick-filters {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }
  
  .quick-filter-section {
    flex-wrap: wrap;
  }

  .job-search {
    margin-left: 0;
    min-width: 0;
  }
}
</style>
