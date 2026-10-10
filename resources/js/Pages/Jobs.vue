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
          <span class="jl-filter-label">Sort &amp; filter</span>
          <ColumnFilter label="Date & time" column="job" :state="jobColumns" :rows="listedJobs" :sort-labels="dateSortLabels" count-unit="job" />
          <ColumnFilter label="Stage" column="stage" :state="jobColumns" :rows="listedJobs" count-unit="job" />
          <ColumnFilter label="Team" column="team" :state="jobColumns" :rows="listedJobs" count-unit="job" />
          <ColumnFilter label="Progress" column="progress" :state="jobColumns" :rows="listedJobs" :filterable="false" :sort-labels="numberSortLabels" />
          <ColumnFilter label="Estimated arrival" column="eta" :state="jobColumns" :rows="listedJobs" :filterable="false" :sort-labels="dateSortLabels" />
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

          <!-- Job Items, grouped by day while the list is in date order -->
          <template v-for="(job, i) in filtered" :key="job.id">
            <div v-if="showDays && (i === 0 || filtered[i - 1].date !== job.date)" class="jl-day">
              <span>{{ dayHeading(job.date) }}</span>
              <span class="jl-day-count">{{ dayCounts[job.date ?? ''] }} job{{ dayCounts[job.date ?? ''] === 1 ? '' : 's' }}</span>
            </div>
            <div
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
              <div class="jl-time" :title="job.id">
                <span class="jl-time-main">{{ jobTime(job).time }}</span>
                <span class="jl-time-label">{{ jobTime(job).label }}</span>
              </div>
              <div class="jl-body">
                <div class="jl-line1">
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
                <div class="jl-sub" :title="isFlightJob(job) ? flightTitle(job.flight) : undefined">
                  {{ jobSubtitle(job) }}
                  <span v-if="isFlightJob(job) && job.flight.actual_time" class="jl-flight-ok"> · {{ job.flight.direction === 'arrival' ? 'Landed' : 'Took off' }} {{ job.flight.actual_time }}</span>
                  <span v-else-if="isFlightJob(job) && job.flight.estimated_time && job.flight.estimated_time !== job.flight.scheduled_time" class="jl-flight-warn"> · Est {{ job.flight.estimated_time }}</span>
                </div>
                <div class="jl-ids">
                  <span v-if="job.job_info?.movement_code" title="Movement number">{{ job.job_info.movement_code }}</span>
                  <span title="Job number">{{ job.id }}</span>
                </div>
                <div class="jl-prog">
                  <div class="jl-progress-bar">
                    <div class="jl-progress-fill" :style="{
                      width: `${jobProgress(job)}%`,
                      background: job.status === 'delayed' ? 'var(--warn)' : job.status === 'completed' ? 'var(--ok)' : 'var(--accent)'
                    }"/>
                  </div>
                  <span class="jl-steps">{{ jobStepsShort(job) }}</span>
                </div>
              </div>
              <div class="jl-side">
                <status-pill :tone="statusTone(job.status)" size="sm">{{ statusLabel(job.status) }}</status-pill>
                <span v-if="job.delay" class="jl-eta-delay">+{{ job.delay }}m</span>
                <span v-if="job.alerts" class="jl-alert-badge" :title="`${job.alerts} alert(s)`">
                  <svg width="11" height="11" viewBox="0 0 16 16" fill="none" style="flex-shrink:0;">
                    <path d="M8 2.5L13.5 12.5H2.5L8 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
                    <line x1="8" y1="7" x2="8" y2="10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <circle cx="8" cy="11.5" r="0.75" fill="currentColor"/>
                  </svg>
                  {{ job.alerts }}
                </span>
              </div>
            </div>
          </template>
        </div>
      </div>

      <!-- Detail panel -->
      <div v-if="selectedJob" class="job-detail-panel">
        <!-- Header card -->
        <div class="detail-card jh">
          <div class="jh-top">
            <flag-icon class="jh-flag" :code="selectedJob.country_code" :fallback="selectedJob.flag" />
            <div class="jh-title">
              <div class="jh-name-row">
                <h2 class="jh-name">{{ selectedJob.team }}</h2>
                <span class="jh-code">{{ selectedJob.code }}</span>
                <status-pill :tone="statusTone(selectedJob.status)" size="sm">{{ statusLabel(selectedJob.status) }}</status-pill>
                <span v-if="selectedJob.kind" class="detail-kind-badge" :class="`detail-kind-badge--${selectedJob.kind}`">{{ selectedJob.kind }}</span>
                <span v-if="selectedJob.functional_area" class="detail-fa-badge">{{ formatFunctionalArea(selectedJob.functional_area) }}</span>
              </div>
              <div class="jh-sub">
                {{ selectedJob.id }}<template v-if="selectedJob.date"> · {{ longDate(selectedJob.date) }}</template><template v-if="selectedJob.event_name"> · {{ selectedJob.event_name }}</template>
              </div>
            </div>
            <div class="jh-actions">
              <Button v-if="selectedJob.status !== 'in-progress' && canStartJob(selectedJob)" variant="primary" size="md" @click="promptStartJob">Start job</Button>
              <Button v-else-if="canReinstateJob(selectedJob)" variant="primary" size="md" @click="promptReinstateJob">Reinstate</Button>
              <div v-if="hasMoreActions" ref="moreRef" class="jh-more">
                <Button variant="secondary" size="md" :aria-expanded="moreOpen" @click="moreOpen = !moreOpen">More <span class="jh-caret">▾</span></Button>
                <div v-if="moreOpen" class="jh-menu" role="menu">
                  <button v-if="selectedJob.status === 'in-progress'" type="button" role="menuitem" @click="runMore(promptRevertJob)">Mark scheduled</button>
                  <button v-if="canOverride && selectedJob.status !== 'cancelled'" type="button" role="menuitem" @click="runMore(openOverrideModal)">Override</button>
                  <button v-if="canCancelJob(selectedJob)" type="button" role="menuitem" class="jh-menu-danger" @click="runMore(promptCancelJob)">Cancel job</button>
                  <button v-if="canDeleteJobs" type="button" role="menuitem" class="jh-menu-danger" @click="runMore(() => promptDeleteJobs([selectedJob]))">Delete</button>
                </div>
              </div>
            </div>
          </div>

          <div class="jh-route">
            <div class="jh-end">
              <span class="jh-label">From</span>
              <span class="jh-place">{{ formatJobFromLocation(selectedJob) }}</span>
              <span class="jh-note">Pickup {{ selectedJob.pickup || '--:--' }}</span>
            </div>
            <span class="jh-arrow" aria-hidden="true">
              <svg width="34" height="12" viewBox="0 0 34 12" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M1 6h31M27 1l5 5-5 5" /></svg>
            </span>
            <div class="jh-end">
              <span class="jh-label">To</span>
              <span class="jh-place">{{ formatJobToLocation(selectedJob) }}</span>
              <span v-if="destinationNote(selectedJob)" class="jh-note">{{ destinationNote(selectedJob) }}</span>
            </div>
          </div>

          <div class="jh-stats">
            <div class="jh-stat" :title="selectedJob.pickup_checkpoint ? `First checkpoint: ${selectedJob.pickup_checkpoint}` : 'Planned pickup time'">
              <span class="jh-stat-label">Pickup</span>
              <span class="jh-stat-value">{{ selectedJob.pickup || '--:--' }}</span>
            </div>
            <div class="jh-stat" :title="`${doneCount} of ${totalChecks} checkpoints done`">
              <span class="jh-stat-label">Progress</span>
              <span class="jh-stat-value">{{ progressPercentage }}%</span>
            </div>
            <div class="jh-stat">
              <span class="jh-stat-label">Passengers</span>
              <span class="jh-stat-value">{{ selectedJob.pax ?? 0 }} pax</span>
            </div>
            <div v-if="selectedJob.status === 'completed' && timeVariance" class="jh-stat">
              <span class="jh-stat-label">Time variance</span>
              <span :class="['jh-stat-value', `jh-stat-value--${timeVarianceTone}`]">{{ timeVariance }}</span>
            </div>
            <div v-else-if="lastCompletedCheckpoint" class="jh-stat" :title="lastCompletedCheckpoint.name">
              <span class="jh-stat-label">Last checkpoint</span>
              <span class="jh-stat-value">{{ lastCompletedCheckpoint.completed_at }}</span>
            </div>
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
            <div class="checkpoint-header">
              <h3 class="section-title">Checkpoints</h3>
              <span class="section-kicker info-mono">{{ doneCount }}/{{ totalChecks }}</span>
            </div>
            <div class="checkpoint-content">
              <div v-if="nextCheckpoint" class="next-step">
                <div class="next-step-text">
                  <span class="next-step-label">Next step</span>
                  <span class="next-step-name">{{ nextCheckpoint.name }}</span>
                  <span v-if="nextStepNeedsEvidence" class="next-step-hint">Needs a photo, signature or count — complete it in the mobile app.</span>
                </div>
                <Button
                  v-if="canCompleteNextStep"
                  variant="primary"
                  size="md"
                  @click="openCompleteModal"
                >Mark complete</Button>
              </div>
              <CheckpointTimeline :checkpoints="selectedJob.checkpoints" hide-title />
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
              <h3 class="section-title">Details</h3>
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
              <template v-if="detailFlight(selectedJob)">
                <dt>Flight</dt>
                <dd>{{ detailFlight(selectedJob) }}</dd>
              </template>
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

    <Modal :show="showCompleteModal" max-width="460px" @close="closeCompleteModal">
      <template #title>Complete checkpoint</template>
      <p class="complete-step-name">{{ nextCheckpoint?.name }}</p>
      <div class="complete-grid">
        <div class="complete-field">
          <label class="complete-label" for="complete-time">ACTUAL TIME</label>
          <input id="complete-time" v-model="completeTime" type="time" class="override-input" />
          <div class="complete-hint">When it actually happened</div>
        </div>
        <div class="complete-field">
          <label class="complete-label">VARIANCE VS. PLANNED ({{ nextCheckpoint?.scheduled_at || '—' }})</label>
          <div class="override-variance" :class="completeVarianceMinutes > 0 ? 'is-late' : completeVarianceMinutes < 0 ? 'is-early' : ''">
            {{ completeVarianceLabel }}
          </div>
          <label class="override-exclude-date">
            <input v-model="completeExcludeDate" type="checkbox" />
            <span>Exclude date from calculation (compare time of day only)</span>
          </label>
        </div>
      </div>
      <label class="complete-label" for="complete-reason" style="margin-top: 14px;">
        REASON<span v-if="completeReasonRequired" class="complete-required"> (REQUIRED, COMPLETED LATE)</span><span v-else> (OPTIONAL)</span>
      </label>
      <select id="complete-reason" v-model="completeReason" class="override-select">
        <option value="">Select a reason…</option>
        <option v-for="r in COMPLETE_REASONS" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>
      <p v-if="completeError" style="margin: 8px 0 0; color: var(--danger); font-size: 12px;">{{ completeError }}</p>
      <template #footer>
        <Button variant="secondary" size="sm" :disabled="completingStep" @click="closeCompleteModal">Cancel</Button>
        <Button variant="primary" size="sm" :processing="completingStep" :disabled="completingStep || !canSubmitComplete" @click="submitComplete">Mark complete</Button>
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
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';
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
import { useToast } from '../Composables/useToast';

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

function jobStepsShort(job) {
  return job.checkpoints?.length
    ? `${job.checkpoints.filter(c => c.state === 'done' || c.status === 'done').length}/${job.checkpoints.length}`
    : '';
}

// The list is grouped by day only while it is in date order; any column sort flattens it.
const showDays = computed(() => jobColumns.sort.value.length === 0);

const dayCounts = computed(() => filtered.value.reduce((counts, j) => {
  counts[j.date ?? ''] = (counts[j.date ?? ''] ?? 0) + 1;
  return counts;
}, {}));

function dayHeading(date) {
  const m = String(date ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return 'No date';
  return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]))
    .toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' })
    .replace(',', '');
}

// The time that matters for the job: kick-off, the flight, else when it starts.
function jobTime(job) {
  if (job.kind === 'match' && job.match?.kick_off) return { time: job.match.kick_off.slice(11, 16), label: 'Kick-off' };
  if (isFlightJob(job) && job.flight.scheduled_time) {
    return { time: job.flight.scheduled_time, label: job.flight.direction === 'arrival' ? 'Arrives' : 'Departs' };
  }
  return { time: timeOf(job) ?? '--:--', label: 'Starts' };
}

function jobSubtitle(job) {
  const route = `${formatJobFromLocation(job)} → ${formatJobToLocation(job)}`;
  const lead = job.match?.lineup
    || (isFlightJob(job) ? (job.flight.is_bus ? 'By road' : (job.flight.flight_number || 'Flight TBC')) : '');
  return lead ? `${lead} · ${route}` : route;
}

// "Sat, 7 Nov 2026" from a 'YYYY-MM-DD' date, without a timezone shift.
function longDate(date) {
  const m = String(date ?? '').match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return '';
  const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
  return `${d.toLocaleDateString('en-GB', { weekday: 'short' })}, ${d.getDate()} ${d.toLocaleDateString('en-GB', { month: 'short' })} ${d.getFullYear()}`;
}

// The line under the destination: the flight, the match, or the vehicle.
function destinationNote(job) {
  if (isFlightJob(job) && !job.flight.is_bus && job.flight.flight_number) {
    const time = job.flight.scheduled_time ? ` · ${job.flight.direction === 'arrival' ? 'arr' : 'dep'} ${job.flight.scheduled_time}` : '';
    return `Flight ${job.flight.flight_number}${time}`;
  }
  if (job.kind === 'match' && job.match?.lineup) {
    return `${job.match.lineup}${job.match.kick_off ? ` · KO ${job.match.kick_off.slice(11, 16)}` : ''}`;
  }
  return job.vehicle && job.vehicle !== 'Unassigned' ? job.vehicle : '';
}

// "QR 1376 → HIA · 00:05" for the Details card.
function detailFlight(job) {
  if (!isFlightJob(job) || job.flight.is_bus || !job.flight.flight_number) return '';
  const to = job.flight.destination_airport ? ` → ${job.flight.destination_airport}` : '';
  const time = job.flight.scheduled_time ? ` · ${job.flight.scheduled_time}` : '';
  return `${job.flight.flight_number}${to}${time}`;
}

// Secondary actions live behind the "More" menu.
const moreOpen = ref(false);
const moreRef = ref(null);
const hasMoreActions = computed(() => {
  const job = selectedJob.value;
  return !!job && (job.status === 'in-progress'
    || (canOverride.value && job.status !== 'cancelled')
    || canCancelJob(job)
    || canDeleteJobs.value);
});

function runMore(action) {
  moreOpen.value = false;
  action();
}

function closeMoreOnOutside(e) {
  if (moreOpen.value && !moreRef.value?.contains(e.target)) moreOpen.value = false;
}
onMounted(() => document.addEventListener('mousedown', closeMoreOnOutside));
onUnmounted(() => document.removeEventListener('mousedown', closeMoreOnOutside));
watch(selectedJob, () => { moreOpen.value = false; });

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

// The most recently completed checkpoint, by completion time.
const lastCompletedCheckpoint = computed(() => (selectedJob.value?.checkpoints ?? [])
  .filter(c => (c.state ?? c.status) === 'done' && c.completed_ts && c.completed_at)
  .reduce((latest, c) => (!latest || c.completed_ts > latest.completed_ts ? c : latest), null));

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
    crew[`Supervisor ${i + 2}`] = { name: s.name, phone: s.phone };
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

// The first checkpoint that is neither done nor skipped.
const nextCheckpoint = computed(() => (selectedJob.value?.checkpoints ?? [])
  .find(c => !['done', 'skipped'].includes(c.state ?? c.status)) ?? null);

const nextStepNeedsEvidence = computed(() => {
  const cp = nextCheckpoint.value;
  return !!cp && !!(cp.requires_photo || cp.requires_signature || cp.requires_baggage_count);
});

const canCompleteNextStep = computed(() => !!nextCheckpoint.value
  && !nextStepNeedsEvidence.value
  && !['cancelled', 'completed'].includes(selectedJob.value?.status));

const toast = useToast();
const completingStep = ref(false);
const showCompleteModal = ref(false);
const completeTime = ref('');
// Same reasons as the Override modal.
const COMPLETE_REASONS = [
  { value: 'no_signal', label: 'No signal' },
  { value: 'device_offline', label: 'Device offline' },
  { value: 'supervisor_error', label: 'Supervisor error' },
  { value: 'late_arrival', label: 'Late arrival' },
  { value: 'operational_change', label: 'Operational change' },
  { value: 'other', label: 'Other' },
];
const completeReason = ref('');
const completeError = ref('');
const completeExcludeDate = ref(false);

function openCompleteModal() {
  const now = new Date();
  completeTime.value = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`;
  completeReason.value = '';
  completeError.value = '';
  completeExcludeDate.value = false;
  showCompleteModal.value = true;
}

function closeCompleteModal() {
  if (!completingStep.value) showCompleteModal.value = false;
}

// Same calculation as the Override modal.
const completeVarianceMinutes = computed(() => {
  const cp = nextCheckpoint.value;
  if (!cp?.scheduled_ts || !completeTime.value) return null;
  const [ah, am] = completeTime.value.split(':').map(Number);
  if (isNaN(ah) || isNaN(am)) return null;

  if (completeExcludeDate.value) {
    // Time of day from the "HH:mm" label, not the timestamp, which would use the browser's timezone.
    const sched = cp.scheduled_at?.match(/^(\d{1,2}):(\d{2})/);
    if (!sched) return null;
    let diff = ah * 60 + am - (Number(sched[1]) * 60 + Number(sched[2]));
    if (diff > 720) diff -= 1440;
    if (diff < -720) diff += 1440;
    return diff;
  }

  const scheduledHour = new Date(cp.scheduled_ts * 1000).getHours();
  const actual = new Date();
  actual.setHours(ah, am, 0, 0);
  // Scheduled late at night, done early morning: the next day.
  if (scheduledHour >= 18 && ah < 6) actual.setDate(actual.getDate() + 1);
  return Math.round((Math.floor(actual.getTime() / 1000) - cp.scheduled_ts) / 60);
});

const completeVarianceLabel = computed(() => {
  const v = completeVarianceMinutes.value;
  if (v === null) return '—';
  if (v === 0) return 'On time';
  return v > 0 ? `${Math.abs(v)} min late` : `${Math.abs(v)} min early`;
});

const completeReasonRequired = computed(() => (completeVarianceMinutes.value ?? 0) > 0);
const canSubmitComplete = computed(() => /^\d{2}:\d{2}$/.test(completeTime.value)
  && (!completeReasonRequired.value || completeReason.value !== ''));

async function submitComplete() {
  const cp = nextCheckpoint.value;
  if (!cp || completingStep.value || !canSubmitComplete.value) return;
  completingStep.value = true;
  completeError.value = '';
  try {
    const response = await fetch(`/jobs/checkpoint/${cp.id}/complete`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      // Same date handling as the Override modal's "exclude date" option.
      body: JSON.stringify({
        actual_time: completeTime.value,
        exclude_date: completeExcludeDate.value,
        notes: COMPLETE_REASONS.find(r => r.value === completeReason.value)?.label ?? null,
      }),
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.success) {
      completeError.value = data.message || 'Could not complete the checkpoint.';
      return;
    }
    showCompleteModal.value = false;
    toast.success('Checkpoint completed');
    reloadSelectedJob();
  } catch {
    completeError.value = 'Could not complete the checkpoint.';
  } finally {
    completingStep.value = false;
  }
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
  display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px;
  padding: 8px 16px;
  border-bottom: 1px solid var(--border);
  background: var(--panel);
  position: sticky;
  top: 0;
  z-index: 2;
  flex-shrink: 0;
}
.jl-filter-label {
  font-size: 10px; font-weight: 700; letter-spacing: 0.6px;
  text-transform: uppercase; color: var(--ink3); margin: 0 4px 0 2px;
}
.job-list-header { gap: 4px 14px; padding: 8px 20px; }
.job-list-header .jl-col-select { width: 16px; flex: 0 0 16px; }
.job-list-header .cf {
  width: auto; flex: 0 0 auto;
  font-size: 12px; font-weight: 600; color: var(--ink2);
}
/* The trigger's negative margin makes it wider than its shrink-wrapped parent, so 100% would clip the label. */
.job-list-header .cf :deep(.cf-trigger) { max-width: none; }

.jl-day {
  display: flex; justify-content: space-between; align-items: center;
  padding: 8px 20px; background: var(--panel);
  border-top: 1px solid var(--border); border-bottom: 1px solid var(--border);
  font-size: 12px; font-weight: 700; color: var(--ink2);
}
.jl-day:first-child { border-top: none; }
.jl-day-count { font-weight: 600; color: var(--ink3); }

.job-item {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr) auto;
  gap: 16px;
  padding: 14px 20px 14px 17px;
  cursor: pointer;
  border-bottom: 1px solid var(--border);
  border-left: 3px solid transparent;
  transition: background 0.15s;
  align-items: center;
}
.jobs-list-card--selectable .job-item { grid-template-columns: 16px 72px minmax(0, 1fr) auto; }
.job-item:hover { background: var(--panel); }
.job-item--active {
  border-left-color: var(--accent);
  background: var(--accent-soft);
}
.job-item:last-child { border-bottom: none; }

.jl-col-select { display: flex; align-items: center; justify-content: center; }
.jl-col-select input { margin: 0; cursor: pointer; }
.jl-time { display: flex; flex-direction: column; align-items: center; gap: 3px; }
.jl-time-main { font-family: var(--font-mono, monospace); font-size: 20px; font-weight: 600; color: var(--ink); line-height: 1; }
.jl-time-label { font-size: 10px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.jl-body { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.jl-line1 { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.jl-sub { font-size: 12.5px; color: var(--ink2); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.jl-ids {
  display: flex; gap: 10px; flex-wrap: wrap;
  font-size: 11px; font-family: var(--font-mono, monospace); color: var(--ink3);
}
.jl-prog { display: flex; align-items: center; gap: 10px; }
.jl-prog .jl-progress-bar { flex: 1; }
.jl-prog .jl-steps { min-width: 30px; text-align: right; font-family: var(--font-mono, monospace); }
.jl-side { display: flex; align-items: center; gap: 8px; align-self: start; }
.job-bulk-bar {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 12px;
  border-bottom: 1px solid var(--border);
  background: var(--accent-soft);
}
.job-bulk-count { font-size: 12px; font-weight: 700; color: var(--ink); margin-right: auto; }

.jl-job-phase {
  font-size: 11px; color: var(--ink3); font-weight: 700;
  text-transform: capitalize;
  white-space: nowrap;
  padding: 2px 8px;
  border-radius: 5px;
  background: var(--panel);
  flex-shrink: 0;
}
.jl-job-phase--arrival { background: var(--ok-soft); color: var(--ok); }
.jl-job-phase--departure { background: var(--danger-soft); color: var(--danger); }
.jl-job-phase--transfer { background: var(--accent-soft); color: var(--accent-fg); }
.jl-job-phase--match { background: #fef3c7; color: #92400e; }
.jl-job-phase--training { background: #ede9fe; color: #6d28d9; }
.jl-job-phase--daily_ops { background: var(--panel); color: var(--ink3); }

.jl-flight-ok { color: var(--ok); font-weight: 600; }
.jl-flight-warn { color: var(--warn); font-weight: 600; }

.jl-team {
  font-size: 15px; font-weight: 700; color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
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

.jl-progress-bar {
  height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;
}
.jl-progress-fill {
  height: 100%; border-radius: 2px; transition: width 0.3s;
}
.jl-steps {
  font-size: 11px; color: var(--ink3); font-weight: 500;
}

.jl-eta-delay {
  font-size: 11px; font-weight: 600; color: var(--warn);
  font-family: var(--font-mono, monospace);
}

.jl-alert-badge {
  display: inline-flex; align-items: center; gap: 3px;
  font-size: 11px; font-weight: 700; color: #b45309;
  background: #FEF3C7; padding: 2px 6px; border-radius: 4px;
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

/* Header card */
.jh { display: flex; flex-direction: column; gap: 16px; padding: 20px 22px; }
.jh-top { display: flex; align-items: flex-start; gap: 14px; }
.jh-flag { font-size: 34px; margin-top: 2px; }
.jh-title { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.jh-name-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.jh-name { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.01em; color: var(--ink); }
.jh-code {
  font-family: var(--font-mono, monospace); font-size: 11px; font-weight: 600; color: var(--ink2);
  background: var(--panel); border: 1px solid var(--border); padding: 2px 7px; border-radius: 5px;
}
.jh-sub { font-size: 12.5px; color: var(--ink3); }
.jh-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.jh-caret { font-size: 10px; margin-left: 4px; }
.jh-more { position: relative; }
.jh-menu {
  position: absolute; right: 0; top: calc(100% + 6px); z-index: 20; min-width: 170px; padding: 6px;
  display: flex; flex-direction: column; background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.16);
}
.jh-menu button {
  border: 0; background: none; text-align: left; padding: 8px 10px; border-radius: 6px;
  font-size: 13px; color: var(--ink); cursor: pointer;
}
.jh-menu button:hover { background: var(--panel); }
.jh-menu .jh-menu-danger { color: var(--danger); }

.jh-route {
  display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 20px; align-items: center;
  padding: 14px 18px; background: var(--panel); border: 1px solid var(--border); border-radius: 12px;
}
.jh-end { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.jh-label { font-size: 10.5px; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--ink3); }
.jh-place { font-size: 14px; font-weight: 700; color: var(--ink); }
.jh-note { font-size: 12px; color: var(--ink3); }
.jh-arrow { color: var(--ink3); display: flex; }

.jh-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
.jh-stat { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
.jh-stat-label { font-size: 12px; color: var(--ink3); }
.jh-stat-value { font-family: var(--font-mono, monospace); font-size: 19px; font-weight: 700; color: var(--ink); }
.jh-stat-value--ok { color: var(--ok); }
.jh-stat-value--warn { color: var(--warn); }
@media (max-width: 640px) {
  .jh-top { flex-wrap: wrap; }
  .jh-actions { width: 100%; }
  .jh-route { grid-template-columns: 1fr; gap: 10px; }
  .jh-arrow { transform: rotate(90deg); }
  .jh-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
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

.next-step {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 12px 14px; margin-bottom: 14px;
  background: var(--accent-soft); border: 1px solid var(--accent); border-radius: 10px;
}
.next-step-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.next-step-label {
  font-size: 10px; font-weight: 700; letter-spacing: 0.6px; text-transform: uppercase; color: var(--accent);
}
.next-step-name { font-size: 14px; font-weight: 600; color: var(--ink); }
.next-step-hint { font-size: 11px; color: var(--ink3); }
@media (max-width: 640px) {
  .next-step { flex-direction: column; align-items: stretch; }
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

.override-select, .override-input {
  width: 100%; padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  font-size: 13px; color: var(--ink); font-family: inherit;
  outline: none; box-sizing: border-box;
}
.override-input:focus {
  border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring);
}

.complete-step-name { margin: 0 0 14px; font-size: 14px; font-weight: 600; color: var(--ink); }
.complete-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.complete-field { display: flex; flex-direction: column; gap: 6px; }
.complete-label { display: block; font-size: 11px; font-weight: 600; color: var(--ink3); margin-bottom: 6px; }
.complete-field .complete-label { margin-bottom: 0; }
.complete-hint { font-size: 11px; color: var(--ink3); }
.override-variance {
  padding: 8px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--panel);
  font-size: 13px; font-family: var(--font-mono, monospace);
  font-weight: 600; color: var(--ink3);
  min-height: 38px; display: flex; align-items: center;
}
.override-variance.is-late { color: #c2410c; }
.override-variance.is-early { color: #166534; }
.override-exclude-date {
  display: flex; align-items: flex-start; gap: 6px;
  font-size: 11px; color: var(--ink3); cursor: pointer;
}
.override-exclude-date input { margin-top: 2px; accent-color: var(--accent); flex-shrink: 0; }
.complete-required { color: var(--danger); font-weight: 500; }
@media (max-width: 480px) {
  .complete-grid { grid-template-columns: 1fr; }
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
