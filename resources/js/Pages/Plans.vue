<template>
  <app-layout>
    <div class="page-header">
      <!-- Plan picker dropdown -->
      <div style="position: relative">
        <!-- Back to Plans link (when a plan is selected) -->
        <div v-if="selectedPlanObj" style="margin-bottom: 6px">
          <button
            @click="viewAllPlans"
            style="
              display: inline-flex;
              align-items: center;
              gap: 4px;
              background: none;
              border: none;
              color: var(--accent);
              font-size: 12px;
              font-weight: 600;
              cursor: pointer;
              padding: 0;
            "
          >
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M15 19l-7-7 7-7"
              />
            </svg>
            View All Plans
          </button>
        </div>

        <!-- Trigger: shows selected plan header -->
        <div
          @click="showPlanDropdown = !showPlanDropdown"
          style="cursor: pointer; user-select: none"
        >
          <div
            v-if="selectedPlanObj"
            style="
              display: flex;
              align-items: center;
              gap: 8px;
              margin-bottom: 3px;
              flex-wrap: wrap;
            "
          >
            <span style="font-size: 11px; color: #6b7280; font-weight: 500">{{
              selectedPlanObj.code
            }}</span>
            <span style="font-size: 11px; color: #d1d5db; font-weight: 500"
              >·</span
            >
            <span style="font-size: 11px; color: #6b7280">{{
              formatDateTime(selectedPlanObj.date)
            }}</span>
            <span :style="planStatusPillStyle(selectedPlanObj.status)">
              <span :style="planStatusDotStyle(selectedPlanObj.status)"></span>
              {{ selectedPlanObj.status.toUpperCase() }}
            </span>
          </div>
          <div style="display: flex; align-items: center; gap: 8px">
            <span
              style="
                font-size: 20px;
                font-weight: 700;
                color: #111827;
                letter-spacing: -0.3px;
              "
            >
              {{ selectedPlanObj ? selectedPlanObj.name : "Select a Plan" }}
            </span>
            <svg
              width="18"
              height="18"
              viewBox="0 0 20 20"
              fill="none"
              style="color: #6b7280; flex-shrink: 0; margin-top: 2px"
            >
              <path
                d="M5 7.5L10 12.5L15 7.5"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </div>
        </div>

        <!-- Dropdown -->
        <div
          v-if="showPlanDropdown"
          class="plans-dropdown"
          style="
            position: absolute;
            top: calc(100% + 10px);
            left: 0;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            z-index: 1000;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.12);
            overflow: hidden;
            width: min(380px, calc(100vw - 36px));
          "
        >
          <!-- Search -->
          <div style="padding: 12px 12px 8px">
            <input
              type="text"
              placeholder="Search plans..."
              v-model="planSearchTerm"
              @click.stop
              style="
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                font-size: 13px;
                color: #374151;
                background: #f9fafb;
                outline: none;
                box-sizing: border-box;
              "
            />
          </div>

          <!-- Grouped plan list -->
          <div style="max-height: 340px; overflow-y: auto; padding-bottom: 4px">
            <template v-for="group in groupedFilteredPlans" :key="group.status">
              <div v-if="group.plans.length">
                <div
                  style="
                    padding: 8px 14px 4px;
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    color: #9ca3af;
                  "
                >
                  {{ group.label }}
                </div>
                <div
                  v-for="plan in group.plans"
                  :key="plan.id"
                  @click="selectPlan(plan.id)"
                  :style="{
                    padding: '10px 14px',
                    cursor: 'pointer',
                    borderLeft:
                      activePlan === plan.id
                        ? '3px solid #3B82F6'
                        : '3px solid transparent',
                    background:
                      activePlan === plan.id ? '#EFF6FF' : 'transparent',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    transition: 'background 0.1s',
                  }"
                  @mouseenter="
                    (e) => {
                      if (activePlan !== plan.id)
                        e.currentTarget.style.background = '#F9FAFB';
                    }
                  "
                  @mouseleave="
                    (e) => {
                      e.currentTarget.style.background =
                        activePlan === plan.id ? '#EFF6FF' : 'transparent';
                    }
                  "
                >
                  <div>
                    <div
                      style="
                        font-size: 13px;
                        font-weight: 600;
                        color: #111827;
                        margin-bottom: 2px;
                      "
                    >
                      {{ plan.name }}
                    </div>
                    <div style="font-size: 11px; color: #9ca3af">
                      <span style="font-family: monospace">{{
                        plan.code
                      }}</span>
                      <span style="margin: 0 4px">·</span
                      ><span style="font-family: monospace">{{
                        formatDateTime(plan.date)
                      }}</span>
                      <span style="margin: 0 4px">·</span
                      >{{ plan.movements_count }} movements
                      <span style="margin: 0 4px">·</span
                      >{{ plan.teams_count }} teams
                    </div>
                  </div>
                  <svg
                    v-if="activePlan === plan.id"
                    width="16"
                    height="16"
                    viewBox="0 0 20 20"
                    fill="none"
                    style="color: #3b82f6; flex-shrink: 0; margin-left: 10px"
                  >
                    <path
                      d="M4 10L8 14L16 6"
                      stroke="currentColor"
                      stroke-width="2"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    />
                  </svg>
                </div>
              </div>
            </template>
            <div
              v-if="allFilteredPlansEmpty"
              style="
                padding: 20px 14px;
                text-align: center;
                color: #9ca3af;
                font-size: 12px;
              "
            >
              No plans found
            </div>
          </div>

          <!-- New plan footer -->
          <div style="border-top: 1px solid #f3f4f6; padding: 10px 14px">
            <button
              @click="
                showNewPlan = true;
                showPlanDropdown = false;
              "
              style="
                background: none;
                border: none;
                color: #3b82f6;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                padding: 0;
                display: flex;
                align-items: center;
                gap: 5px;
              "
            >
              <span style="font-size: 17px; line-height: 1; margin-top: -1px"
                >+</span
              >
              New plan...
            </button>
          </div>
        </div>
      </div>

      <!-- Close dropdown overlay -->
      <div
        v-if="showPlanDropdown"
        @click="showPlanDropdown = false"
        style="
          position: fixed;
          top: 0;
          left: 0;
          right: 0;
          bottom: 0;
          z-index: 999;
        "
      ></div>

      <div
        style="
          display: flex;
          flex-direction: column;
          gap: 12px;
          align-items: flex-end;
        "
      >
        <div style="display: flex; gap: 12px; align-items: center">
          <RefreshButton
            :only="[
              'activeEvent',
              'plans',
              'teams',
              'movementTemplates',
              'vehicles',
              'drivers',
              'supervisors',
            ]"
          />
          <!-- Only show view toggle when a plan is selected -->
          <div v-if="selectedPlanObj" class="view-toggle">
            <button
              :class="[
                'toggle-btn',
                view === 'day' ? 'toggle-btn--active' : '',
              ]"
              @click="switchView('day')"
            >
              By Day
            </button>
            <button
              :class="[
                'toggle-btn',
                view === 'team' ? 'toggle-btn--active' : '',
              ]"
              @click="switchView('team')"
            >
              By Team
            </button>
          </div>
        </div>

        <!-- Action buttons only show when a plan is selected -->
        <div v-if="selectedPlanObj" class="page-header-actions">
          <Button
            variant="secondary"
            size="sm"
            @click="duplicatePlan(selectedPlanObj)"
          >
            Duplicate plan
          </Button>
          <Button
            v-if="view === 'day'"
            variant="secondary"
            size="sm"
            @click="addMovement"
          >
            <template #icon><svg-icon name="plus" :size="14" /></template>
            Add movement
          </Button>
          <Button v-else variant="secondary" size="sm" @click="syncFlightFeeds">
            <template #icon><svg-icon name="refresh" :size="14" /></template>
            Sync flight feeds
          </Button>
          <Button
            v-if="view === 'day'"
            variant="primary"
            size="sm"
            @click="generateJobs"
          >
            <template #icon
              ><svg-icon name="plus" :size="14" style="color: #fff"
            /></template>
            Generate jobs
          </Button>
        </div>
        <!-- Hidden: New team plan button - needs architectural review -->
        <!-- <Button v-else variant="primary" size="sm" @click="openNewTeamPlan">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New team plan
        </Button> -->
      </div>
    </div>

    <!-- Stats grid (only show when plan is selected) -->
    <div v-if="view === 'day' && selectedPlanObj" class="stats-grid">
      <!-- <MiniStat label="Plan" :value="selectedPlanObj.name" /> -->
      <MiniStat
        label="Movements"
        :value="selectedPlanObj.movements_count || 0"
      />
      <MiniStat label="Teams" :value="selectedPlanObj.teams_count || 0" />
      <MiniStat label="Jobs generated" :value="jobsGenerated" />
      <MiniStat label="Conflicts" :value="0" tone="warn" />
      <MiniStat label="Passengers" :value="totalPassengers" />

    </div>

    <!-- By Day view -->
    <template v-if="view === 'day'">
      <!-- Tabs (only show when a plan is selected) -->
      <div
        v-if="activePlan"
        style="
          display: flex;
          margin-bottom: 12px;
          border-bottom: 1px solid var(--border);
        "
      >
        <button
          v-for="t in tabs"
          :key="t.id"
          @click="activeTab = t.id"
          class="tab"
          :class="{ 'tab--active': activeTab === t.id }"
        >
          {{ t.label }}
          <span
            class="tab-count"
            :style="{
              background:
                t.danger && t.count > 0 ? 'var(--danger-soft)' : 'var(--panel)',
              color: t.danger && t.count > 0 ? 'var(--danger)' : 'var(--ink3)',
            }"
          >
            {{ t.count }}
          </span>
        </button>
      </div>

      <!-- Plans tab -->
      <div v-if="activeTab === 'plans'" style="flex: 1; overflow: auto">
        <div class="plan-table-card">
          <div
            style="
              padding: 14px 16px;
              border-bottom: 1px solid var(--border);
              display: flex;
              justify-content: space-between;
              align-items: center;
            "
          >
            <div>
              <div
                style="
                  font-size: 11px;
                  letter-spacing: 1px;
                  text-transform: uppercase;
                  color: var(--ink3);
                  font-weight: 700;
                "
              >
                Plans {{ allPlans.length }}
              </div>
              <!-- <div style="font-size: 14px; font-weight: 700; color: var(--ink); margin-top: 2px;"> Execution Plans</div> -->
            </div>
            <Button variant="primary" size="sm" @click="showNewPlan = true">
              <template #icon><svg-icon name="plus" :size="14" /></template>
              New Plan
            </Button>
          </div>
          <div
            style="
              display: grid;
              grid-template-columns: 120px 1.5fr 1fr 140px 140px 100px 120px;
              gap: 10px;
              padding: 10px 14px;
              border-bottom: 1px solid var(--border);
              font-size: 11px;
              font-weight: 700;
              color: var(--ink3);
              letter-spacing: 0.6px;
              text-transform: uppercase;
              position: sticky;
              top: 0;
              background: var(--surface);
            "
          >
            <div>Code</div>
            <div>Name</div>
            <div>Template</div>
            <div>Date</div>
            <div>Status</div>
            <div>Movements</div>
            <div>Actions</div>
          </div>
          <div
            v-if="!allPlans || allPlans.length === 0"
            style="padding: 40px; text-align: center; color: var(--ink3)"
          >
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px">
              No plans yet
            </div>
            <div style="font-size: 12px">
              Create your first plan to get started
            </div>
          </div>
          <div
            v-for="(plan, i) in allPlans"
            :key="plan.id"
            @click="selectPlan(plan.id)"
            :style="{
              display: 'grid',
              gridTemplateColumns: '120px 1.5fr 1fr 140px 140px 100px 120px',
              gap: '10px',
              padding: '12px 14px',
              borderBottom:
                i === allPlans.length - 1 ? 'none' : '1px solid var(--border)',
              alignItems: 'center',
              cursor: 'pointer',
              transition: 'background 0.13s',
            }"
            @mouseenter="$event.currentTarget.style.background = 'var(--panel)'"
            @mouseleave="$event.currentTarget.style.background = 'transparent'"
          >
            <div
              style="
                font-family: var(--mono);
                font-size: 11px;
                color: var(--ink);
                font-weight: 700;
              "
            >
              {{ plan.code }}
            </div>
            <div style="font-size: 13px; color: var(--ink); font-weight: 600">
              {{ plan.name }}
            </div>
            <div style="font-size: 12px; color: var(--ink3)">
              <span v-if="plan.movement_template">{{
                plan.movement_template.name
              }}</span>
              <span v-else style="font-style: italic">Blank</span>
            </div>
            <div
              style="
                font-family: var(--mono);
                font-size: 11px;
                color: var(--ink2);
              "
            >
              {{ formatDateTime(plan.date) }}
            </div>
            <div>
              <span
                :class="['status-badge', `status-badge--${plan.status}`]"
                style="
                  display: inline-flex;
                  align-items: center;
                  gap: 4px;
                  padding: 2px 8px;
                  border-radius: 999px;
                  font-size: 10px;
                  font-weight: 600;
                "
              >
                {{ plan.status }}
              </span>
            </div>
            <div
              style="
                font-size: 12px;
                color: var(--ink2);
                font-family: var(--mono);
              "
            >
              {{ plan.movements_count || 0 }}
            </div>
            <div
              style="display: flex; gap: 6px; justify-content: center"
              @click.stop
            >
              <TableActions
                :show-duplicate="true"
                @duplicate="duplicatePlan(plan)"
                @edit="editPlan(plan)"
                @delete="deletePlan(plan)"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Movements tab -->
      <div
        v-else-if="activeTab === 'movements'"
        style="flex: 1; overflow: hidden; display: flex; gap: 12px"
      >
        <div
          class="plan-table-card"
          :style="{
            flex: 1,
            minWidth: 0,
            display: 'flex',
            flexDirection: 'column',
            overflow: 'hidden',
          }"
        >
          <div
            v-if="selectedPlanMovements.length === 0"
            style="padding: 40px; text-align: center; color: var(--ink3)"
          >
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px">
              No movements yet
            </div>
            <div style="font-size: 12px">
              Add movements to this plan to get started
            </div>
          </div>

          <template v-else>
            <!-- Filter Controls -->
            <div
              style="
                padding: 12px 14px;
                border-bottom: 1px solid var(--border);
                display: flex;
                align-items: center;
                gap: 12px;
                background: var(--surface);
              "
            >
              <div
                style="
                  font-size: 11px;
                  font-weight: 600;
                  color: var(--ink3);
                  text-transform: uppercase;
                  letter-spacing: 0.5px;
                "
              >
                Filter:
              </div>
              <select
                v-model="movementsTeamFilter"
                style="
                  padding: 6px 10px;
                  border: 1px solid var(--border);
                  border-radius: 6px;
                  font-size: 12px;
                  color: var(--ink);
                  background: var(--surface);
                  cursor: pointer;
                  min-width: 180px;
                "
              >
                <option :value="null">
                  All Teams ({{ selectedPlanMovements.length }})
                </option>
                <option
                  v-for="team in teamsInCurrentPlan"
                  :key="team.id"
                  :value="team.id"
                >
                  {{ team.team_name || team.team }} ({{
                    teamMovementCount(team.id)
                  }})
                </option>
              </select>
              <div
                v-if="movementsTeamFilter"
                style="font-size: 11px; color: var(--ink3)"
              >
                Showing {{ filteredPlanMovements.length }} of
                {{ selectedPlanMovements.length }} movements
              </div>
            </div>

            <div
              style="
                display: grid;
                grid-template-columns: 60px 100px 1fr 1.4fr 120px 110px 90px 80px 100px 90px;
                gap: 10px;
                padding: 10px 14px;
                border-bottom: 1px solid var(--border);
                font-size: 11px;
                font-weight: 700;
                color: var(--ink3);
                letter-spacing: 0.6px;
                text-transform: uppercase;
                background: var(--surface);
                position: sticky;
                top: 0;
                z-index: 10;
              "
            >
              <div>ID</div>
              <div>Phase</div>
              <div>Team</div>
              <div>Route</div>
              <div>Window</div>
              <div>Vehicle</div>
              <div>Pax</div>
              <div>Checks</div>
              <div>Job</div>
              <div>Actions</div>
            </div>
            <div
              style="overflow-y: auto; flex: 1; max-height: calc(100vh - 400px)"
            >
              <div
                v-for="(mv, i) in filteredPlanMovements"
                :key="mv.id"
                :style="{
                  display: 'grid',
                  gridTemplateColumns:
                    '60px 100px 1fr 1.4fr 120px 110px 90px 80px 100px 90px',
                  gap: '10px',
                  padding: '12px 14px',
                  borderBottom:
                    i === filteredPlanMovements.length - 1
                      ? 'none'
                      : '1px solid var(--border)',
                  alignItems: 'center',
                  cursor: 'pointer',
                  transition: 'background 0.13s',
                  borderLeft:
                    selectedMovement?.id === mv.id
                      ? '3px solid var(--accent)'
                      : '3px solid transparent',
                  background:
                    selectedMovement?.id === mv.id
                      ? 'var(--accent-soft, #EEF0FE)'
                      : 'transparent',
                }"
                @click="selectMovement(mv)"
                @mouseenter="
                  selectedMovement?.id !== mv.id &&
                    ($event.currentTarget.style.background = 'var(--panel)')
                "
                @mouseleave="
                  selectedMovement?.id !== mv.id &&
                    ($event.currentTarget.style.background = 'transparent')
                "
              >
                <div
                  style="
                    font-family: var(--mono);
                    font-size: 11px;
                    color: var(--ink);
                    font-weight: 700;
                  "
                >
                  {{ mv.code || `M${i + 1}` }}
                </div>
                <span
                  v-if="mv.match_id"
                  class="kind-badge kind-badge--match"
                  style="background: #fef3c7; color: #92400e; border-color: #fbbf24;"
                  >Match {{ mv.match?.match_number || '' }}</span
                >
                <span
                  v-else-if="mv.kind"
                  :class="['kind-badge', `kind-badge--${mv.kind}`]"
                  >{{ mv.kind }}</span
                >
                <span
                  v-else
                  style="
                    font-size: 11px;
                    color: var(--ink3);
                    font-style: italic;
                  "
                  >-</span
                >
                <div
                  style="font-size: 12px; color: var(--ink); font-weight: 600; display: flex; align-items: center; gap: 4px;"
                >
                  {{ mv.team?.team_name || "-" }}
                  <svg
                    v-if="mv.match_id"
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    style="color: #f59e0b"
                    :title="`Match: ${mv.match?.match_number || 'Unknown'}`"
                  >
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                  </svg>
                </div>
                <div style="font-size: 11px; color: var(--ink3)">
                  {{ mv.from_location || "-" }} → {{ mv.to_location || "-" }}
                </div>
                <div
                  style="
                    font-family: var(--mono);
                    font-size: 11px;
                    color: var(--ink2);
                  "
                >
                  {{ formatTime(mv.window_start) }} –
                  {{ formatTime(mv.window_end) }}
                </div>
                <div style="font-size: 11px; color: var(--ink2)">
                  {{ mv.vehicle?.code || "-" }}
                </div>
                <div
                  style="
                    font-size: 12px;
                    color: var(--ink2);
                    font-family: var(--mono);
                  "
                >
                  {{ mv.passengers || 0 }}
                </div>
                <div
                  style="
                    font-size: 12px;
                    color: var(--ink3);
                    font-family: var(--mono);
                  "
                >
                  <template v-if="mv.checkpoint_template?.checkpoints?.length">
                    {{ getCompletedCheckpointsCount(mv) }}/{{
                      mv.checkpoint_template.checkpoints.length
                    }}
                  </template>
                  <template v-else>-</template>
                </div>
                <div @click.stop>
                  <span
                    v-if="mv.job_id"
                    @click="$inertia.visit(`/job/${mv.job_id}`)"
                    style="
                      font-family: var(--mono);
                      font-size: 11px;
                      color: var(--accent);
                      font-weight: 600;
                      cursor: pointer;
                    "
                    >{{ mv.job_id }}</span
                  >
                  <Button
                    v-else
                    variant="secondary"
                    size="sm"
                    style="padding: 3px 8px; font-size: 11px"
                    @click="generateSingleJob(mv)"
                    :processing="generatingJobForMovement === mv.id"
                    :disabled="generatingJobForMovement === mv.id"
                    >Generate</Button
                  >
                </div>
                <div style="display: flex; gap: 4px" @click.stop>
                  <TableActions
                    @edit="editMovement(mv)"
                    @delete="deleteMovement(mv)"
                  />
                </div>
              </div>
            </div>
          </template>
        </div>

        <!-- Movement Detail Panel -->
        <transition name="slide-card">
          <div v-if="selectedMovement" class="movement-detail-panel">
            <!-- Header -->
            <div class="detail-card-header">
              <div
                style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  margin-bottom: 6px;
                  flex-wrap: wrap;
                "
              >
                <span
                  style="
                    font-family: var(--mono);
                    font-size: 13px;
                    font-weight: 700;
                    color: var(--ink);
                  "
                >
                  {{ selectedMovement.code || "MVT" }}
                </span>
                <span
                  v-if="selectedMovement.match_id"
                  class="kind-badge kind-badge--match"
                  style="font-size: 10px; background: #fef3c7; color: #92400e; border-color: #fbbf24;"
                >
                  Match
                </span>
                <span
                  v-else-if="selectedMovement.kind"
                  :class="[
                    'kind-badge',
                    `kind-badge--${selectedMovement.kind}`,
                  ]"
                  style="font-size: 10px"
                >
                  {{ selectedMovement.kind }}
                </span>
                <span
                  v-if="selectedMovement.status"
                  class="dc-pill dc-pill--ghost"
                  >{{ selectedMovement.status }}</span
                >
              </div>
              <div
                style="
                  font-size: 15px;
                  font-weight: 700;
                  color: var(--ink);
                  margin-bottom: 3px;
                "
              >
                {{ selectedMovement.team?.team_name || "—" }}
              </div>
              <div style="font-size: 12px; color: var(--ink3)">
                {{ selectedMovement.from_location || "Origin" }} →
                {{ selectedMovement.to_location || "Destination" }}
              </div>

              <!-- Stats grid -->
              <div class="dc-stats-grid">
                <div class="dc-stat">
                  <div class="dc-stat-label">Window</div>
                  <div class="dc-stat-value">
                    {{ formatTime(selectedMovement.window_start) || "—" }} →
                    {{ formatTime(selectedMovement.window_end) || "—" }}
                  </div>
                </div>
                <div class="dc-stat">
                  <div class="dc-stat-label">Pax</div>
                  <div class="dc-stat-value">
                    {{ selectedMovement.passengers ?? "—" }}
                  </div>
                </div>
                <!-- <div class="dc-stat">
                  <div class="dc-stat-label">Checkpoint Template</div>
                  <div class="dc-stat-value">{{ selectedMovement.checkpoint_template?.code || '—' }}</div>
                </div> -->
              </div>

              <button
                @click="selectedMovement = null"
                class="detail-card-close"
                style="position: absolute; top: 14px; right: 14px"
              >
                <svg-icon name="x" :size="16" />
              </button>
            </div>

            <!-- Checkpoints -->
            <div class="detail-card-content">
              <CheckpointTimeline
                :checkpoints="
                  selectedMovement.checkpoints ||
                  selectedMovement.checkpoint_template?.checkpoints
                "
                title="Checkpoints"
                empty-message="No checkpoints defined"
              />

              <!-- Match Info -->
              <div
                v-if="selectedMovement.match_id && selectedMovement.match"
                style="
                  margin-top: 16px;
                  padding-top: 16px;
                  border-top: 1px solid var(--border);
                "
              >
                <div
                  style="
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 0.6px;
                    text-transform: uppercase;
                    color: var(--ink3);
                    margin-bottom: 8px;
                    display: flex;
                    align-items: center;
                    gap: 4px;
                  "
                >
                  <svg
                    width="12"
                    height="12"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    style="color: #f59e0b"
                  >
                    <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                  </svg>
                  Match Information
                </div>
                <div class="detail-row">
                  <span class="detail-label">Match</span>
                  <span class="detail-value" style="font-weight: 600">{{
                    selectedMovement.match.match_number || "—"
                  }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Teams</span>
                  <span class="detail-value">{{
                    selectedMovement.match.team1?.team_name || selectedMovement.match.team1?.team || "—"
                  }} vs {{
                    selectedMovement.match.team2?.team_name || selectedMovement.match.team2?.team || "—"
                  }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Venue</span>
                  <span class="detail-value">{{
                    selectedMovement.match.venue?.name || "—"
                  }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Kick-off</span>
                  <span class="detail-value">{{
                    selectedMovement.match.kick_off ? formatDateTime(selectedMovement.match.kick_off) : "—"
                  }}</span>
                </div>
              </div>

              <!-- Job Info -->
              <div
                style="
                  margin-top: 16px;
                  padding-top: 16px;
                  border-top: 1px solid var(--border);
                "
              >
                <div
                  style="
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 0.6px;
                    text-transform: uppercase;
                    color: var(--ink3);
                    margin-bottom: 8px;
                  "
                >
                  Job Information
                </div>
                <div class="detail-row">
                  <span class="detail-label">Vehicle</span>
                  <span class="detail-value">{{
                    typeof selectedMovement.vehicle === "string"
                      ? selectedMovement.vehicle || "—"
                      : selectedMovement.vehicle?.code ||
                        selectedMovement.vehicle?.vehicle_type ||
                        "—"
                  }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Driver</span>
                  <span class="detail-value">{{
                    typeof selectedMovement.driver === "string"
                      ? selectedMovement.driver || "—"
                      : selectedMovement.driver?.name || "—"
                  }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Field Supervisor</span>
                  <span class="detail-value">{{
                    typeof selectedMovement.field_supervisor === "string"
                      ? selectedMovement.field_supervisor || "—"
                      : selectedMovement.field_supervisor?.name || "—"
                  }}</span>
                </div>
                <div
                  v-if="selectedMovement.job_id || selectedMovement.jobId"
                  class="detail-row"
                >
                  <span class="detail-label">Job ID</span>
                  <span
                    class="detail-value mono"
                    style="color: var(--accent); cursor: pointer"
                    @click="
                      $inertia.visit(
                        `/job/${
                          selectedMovement.job_id || selectedMovement.jobId
                        }`
                      )
                    "
                  >
                    {{ selectedMovement.job_id || selectedMovement.jobId }}
                  </span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Status</span>
                  <span class="detail-value">{{
                    selectedMovement.status || "Pending"
                  }}</span>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="detail-card-footer">
              <Button
                variant="secondary"
                size="sm"
                @click="selectedMovement = null"
                >Close</Button
              >
              <div style="flex: 1"></div>
              <Button
                variant="primary"
                size="sm"
                @click="
                  editMovement(selectedMovement);
                  selectedMovement = null;
                "
                >Edit Movement</Button
              >
            </div>
          </div>
        </transition>
      </div>

      <!-- Checkpoints tab -->
      <div
        v-else-if="activeTab === 'checkpoints'"
        style="
          flex: 1;
          overflow: auto;
          display: grid;
          grid-template-columns: 1fr 320px;
          gap: 12px;
        "
      >
        <div class="plan-table-card">
          <div
            style="
              padding: 14px 16px;
              border-bottom: 1px solid var(--border);
              display: flex;
              justify-content: space-between;
              align-items: center;
            "
          >
            <div>
              <div
                style="
                  font-size: 11px;
                  letter-spacing: 1px;
                  text-transform: uppercase;
                  color: var(--ink3);
                  font-weight: 700;
                "
              >
                Checkpoint Library
              </div>
              <div
                style="
                  font-size: 14px;
                  font-weight: 700;
                  color: var(--ink);
                  margin-top: 2px;
                "
              >
                Sequence
              </div>
            </div>
            <Button variant="secondary" size="sm">
              <template #icon><svg-icon name="plus" :size="14" /></template>
              Add step
            </Button>
          </div>
          <div style="padding: 14px">
            <div
              v-for="c in checkpoints"
              :key="c.id"
              style="
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px;
                margin-bottom: 6px;
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 8px;
                cursor: grab;
              "
            >
              <div
                style="
                  font-family: var(--mono);
                  font-size: 11px;
                  color: var(--ink4);
                "
              >
                ⋮⋮
              </div>
              <div
                style="
                  width: 28px;
                  height: 28px;
                  border-radius: 999px;
                  background: var(--ink);
                  color: #fff;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  font-weight: 700;
                  font-size: 12px;
                  font-family: var(--mono);
                "
              >
                {{ c.order }}
              </div>
              <div style="flex: 1">
                <div
                  style="font-size: 13px; font-weight: 600; color: var(--ink)"
                >
                  {{ c.name }}
                </div>
                <div
                  style="
                    font-size: 11px;
                    color: var(--ink3);
                    font-family: var(--mono);
                  "
                >
                  {{ c.id }}
                </div>
              </div>
              <status-pill :tone="cpTypeTone[c.type]" size="sm">{{
                c.type
              }}</status-pill>
            </div>
          </div>
        </div>
        <div class="plan-table-card">
          <div
            style="padding: 14px 16px; border-bottom: 1px solid var(--border)"
          >
            <div
              style="
                font-size: 11px;
                letter-spacing: 1px;
                text-transform: uppercase;
                color: var(--ink3);
                font-weight: 700;
              "
            >
              Configuration
            </div>
            <div
              style="
                font-size: 14px;
                font-weight: 700;
                color: var(--ink);
                margin-top: 2px;
              "
            >
              Rules
            </div>
          </div>
          <div style="padding: 14px 16px">
            <div
              v-for="r in [
                { label: 'Require photo at handoff', on: true },
                { label: 'Require supervisor signature', on: true },
                { label: 'Allow offline capture', on: true },
                { label: 'Auto-skip if late > 10m', on: false },
                { label: 'Auto-notify liaison on delay', on: true },
              ]"
              :key="r.label"
              style="
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 8px 0;
                border-bottom: 1px solid var(--border);
              "
            >
              <span style="font-size: 12px; color: var(--ink)">{{
                r.label
              }}</span>
              <div
                :style="{
                  width: '32px',
                  height: '18px',
                  borderRadius: '999px',
                  padding: '2px',
                  background: r.on ? 'var(--accent)' : 'var(--borderStrong)',
                  display: 'flex',
                  alignItems: 'center',
                }"
              >
                <div
                  :style="{
                    width: '14px',
                    height: '14px',
                    borderRadius: '999px',
                    background: '#fff',
                    transform: r.on ? 'translateX(14px)' : 'translateX(0)',
                    transition: 'transform 0.15s',
                    boxShadow: '0 1px 2px rgba(0,0,0,0.15)',
                  }"
                />
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Conflicts tab -->
      <div
        v-else-if="activeTab === 'conflicts'"
        style="
          flex: 1;
          overflow: auto;
          display: flex;
          flex-direction: column;
          gap: 10px;
        "
      >
        <div
          style="
            padding: 12px;
            background: var(--warn-soft);
            border: 1px solid var(--warn);
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
          "
        >
          <svg-icon
            name="warn"
            :size="20"
            style="color: var(--warn); flex-shrink: 0"
          />
          <div style="flex: 1; font-size: 12px; color: #92400e">
            <b>{{ conflicts.length }} conflicts detected</b> — review and
            resolve before generating jobs.
            {{ conflicts.filter((c) => c.sev === "high").length }} require
            immediate attention.
          </div>
          <Button variant="secondary" size="sm">Auto-resolve</Button>
        </div>
        <div v-for="c in conflicts" :key="c.id" class="plan-table-card">
          <div
            style="
              padding: 14px;
              display: flex;
              gap: 12px;
              align-items: flex-start;
            "
          >
            <div
              :style="{
                width: '36px',
                height: '36px',
                borderRadius: '8px',
                flexShrink: 0,
                background:
                  c.sev === 'high'
                    ? 'var(--danger-soft)'
                    : c.sev === 'medium'
                    ? 'var(--warn-soft)'
                    : 'var(--panel)',
                color:
                  c.sev === 'high'
                    ? 'var(--danger)'
                    : c.sev === 'medium'
                    ? 'var(--warn)'
                    : 'var(--ink3)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
              }"
            >
              <svg-icon name="warn" :size="18" />
            </div>
            <div style="flex: 1">
              <div
                style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  margin-bottom: 4px;
                "
              >
                <status-pill :tone="sevTone[c.sev]" :dot="true" size="sm">{{
                  c.sev
                }}</status-pill>
                <span
                  style="font-size: 13px; font-weight: 700; color: var(--ink)"
                  >{{ c.type }}</span
                >
                <span
                  style="
                    font-size: 11px;
                    color: var(--ink4);
                    font-family: var(--mono);
                  "
                  >{{ c.id }}</span
                >
              </div>
              <div
                style="
                  font-size: 12px;
                  color: var(--ink2);
                  line-height: 1.5;
                  margin-bottom: 8px;
                "
              >
                {{ c.text }}
              </div>
              <div
                style="
                  display: flex;
                  gap: 6px;
                  align-items: center;
                  flex-wrap: wrap;
                "
              >
                <span style="font-size: 11px; color: var(--ink3)"
                  >Affects:</span
                >
                <span
                  v-for="a in c.affects"
                  :key="a"
                  style="
                    font-family: var(--mono);
                    font-size: 11px;
                    padding: 2px 6px;
                    background: var(--panel);
                    border: 1px solid var(--border);
                    border-radius: 4px;
                    color: var(--ink);
                  "
                  >{{ a }}</span
                >
              </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 4px">
              <Button variant="primary" size="sm">Resolve</Button>
              <Button variant="ghost" size="sm">Dismiss</Button>
            </div>
          </div>
        </div>
      </div>

      <!-- Templates tab -->
      <div
        v-else-if="activeTab === 'templates'"
        style="
          flex: 1;
          overflow: auto;
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
          gap: 12px;
          align-content: start;
        "
      >
        <div v-for="t in templates" :key="t.id" class="plan-table-card">
          <div style="padding: 16px">
            <div
              style="
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                margin-bottom: 10px;
              "
            >
              <div>
                <div
                  style="font-size: 14px; font-weight: 700; color: var(--ink)"
                >
                  {{ t.name }}
                </div>
                <div
                  style="
                    font-size: 11px;
                    color: var(--ink3);
                    font-family: var(--mono);
                    margin-top: 2px;
                  "
                >
                  {{ t.id }}
                </div>
              </div>
              <status-pill tone="neutral" size="sm"
                >{{ t.legs }} legs</status-pill
              >
            </div>
            <div
              style="font-size: 12px; color: var(--ink3); margin-bottom: 12px"
            >
              Avg duration:
              <b style="color: var(--ink2); font-family: var(--mono)">{{
                t.avg
              }}</b>
            </div>
            <div style="display: flex; gap: 6px">
              <Button
                variant="secondary"
                size="sm"
                style="flex: 1"
                @click="openPreviewModal(t)"
                >Preview</Button
              >
              <Button variant="primary" size="sm" style="flex: 1">Apply</Button>
            </div>
          </div>
        </div>
        <div
          class="plan-table-card"
          style="
            border: 2px dashed var(--borderStrong);
            background: var(--panel);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 140px;
            cursor: pointer;
          "
        >
          <div style="text-align: center; color: var(--ink3)">
            <div style="font-size: 22px; margin-bottom: 4px">+</div>
            <div style="font-size: 12px; font-weight: 600">New template</div>
          </div>
        </div>
      </div>
    </template>

    <!-- By Team view -->
    <template v-else>
      <div
        style="
          display: grid;
          grid-template-columns: 260px 1fr;
          gap: 12px;
          height: calc(100vh - 280px);
          min-height: 500px;
        "
      >
        <!-- Team list -->
        <div
          style="
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
          "
        >
          <div
            style="
              padding: 10px 14px;
              border-bottom: 1px solid var(--border);
              display: flex;
              justify-content: space-between;
              align-items: center;
            "
          >
            <div
              style="
                font-size: 11px;
                font-weight: 700;
                color: var(--ink3);
                letter-spacing: 0.6px;
                text-transform: uppercase;
              "
            >
              Teams ({{ teamGroups.length }})
            </div>
            <button
              v-if="selectedPlanObj"
              @click="showAddTeamModal = true"
              style="
                background: var(--accent);
                border: none;
                color: white;
                font-size: 11px;
                font-weight: 600;
                cursor: pointer;
                padding: 5px 10px;
                border-radius: 6px;
                display: flex;
                align-items: center;
                gap: 4px;
                transition: all 0.15s;
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
              "
              @mouseenter="$event.currentTarget.style.background = '#2563EB'"
              @mouseleave="
                $event.currentTarget.style.background = 'var(--accent)'
              "
            >
              <span style="font-size: 16px; line-height: 1; font-weight: 700"
                >+</span
              >Add Team
            </button>
          </div>
          <div style="overflow: auto; flex: 1">
            <div
              v-for="group in teamGroups"
              :key="group.team"
              @click="selectedTeam = group.team"
              :style="{
                padding: '12px 14px',
                cursor: 'pointer',
                borderBottom: '1px solid var(--border)',
                borderLeft:
                  selectedTeam === group.team
                    ? '3px solid var(--accent)'
                    : '3px solid transparent',
                background:
                  selectedTeam === group.team
                    ? 'var(--accent-soft)'
                    : 'transparent',
              }"
            >
              <div
                style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  margin-bottom: 4px;
                "
              >
                <span class="team-badge-sm">{{ group.code }}</span>
                <span
                  style="font-size: 13px; font-weight: 700; color: var(--ink)"
                  >{{ group.team }}</span
                >
              </div>
              <div
                style="font-size: 11px; color: var(--ink3); margin-bottom: 4px"
              >
                {{ group.country || "—" }} · {{ teamTotalPax(group) }} pax
              </div>
              <div
                style="
                  font-size: 10px;
                  color: var(--ink3);
                  margin-bottom: 4px;
                  font-family: var(--font-mono, monospace);
                "
              >
                {{ teamArrivalDate(group) }} → {{ teamDepartureDate(group) }}
              </div>
              <div
                style="
                  display: flex;
                  justify-content: flex-end;
                  align-items: center;
                "
              >
                <status-pill
                  :tone="teamStatusTone(group)"
                  :dot="true"
                  size="sm"
                >
                  {{ teamStatusLabel(group) }}
                </status-pill>
              </div>
            </div>
          </div>
        </div>

        <!-- Team detail -->
        <div
          v-if="selectedTeamObj"
          style="display: flex; gap: 12px; overflow: hidden"
        >
          <div
            style="
              display: flex;
              flex-direction: column;
              gap: 12px;
              overflow: auto;
              flex: 1;
              min-width: 0;
            "
          >
            <!-- Team header card -->
            <div
              style="
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 16px;
              "
            >
              <div
                style="
                  display: flex;
                  justify-content: space-between;
                  align-items: flex-start;
                  margin-bottom: 14px;
                "
              >
                <div style="flex: 1">
                  <div
                    style="
                      display: flex;
                      align-items: center;
                      gap: 10px;
                      margin-bottom: 6px;
                    "
                  >
                    <span class="team-badge">{{ selectedTeamObj.code }}</span>
                    <div
                      style="
                        font-size: 18px;
                        font-weight: 700;
                        color: var(--ink);
                        letter-spacing: -0.3px;
                      "
                    >
                      {{ selectedTeamObj.team }}
                    </div>
                  </div>
                  <div style="font-size: 12px; color: var(--ink3)">
                    {{ teamOrigin(selectedTeamObj) }} →
                    {{ teamDestination(selectedTeamObj) }} ·
                    {{ teamTotalPax(selectedTeamObj) }} passengers · Liaison:
                    <span style="color: var(--accent); cursor: pointer">{{
                      teamLiaison(selectedTeamObj)
                    }}</span>
                  </div>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px; flex-shrink: 0">
                  <Button variant="secondary" size="sm" @click="exportPlan">
                    <template #icon
                      ><svg-icon name="download" :size="14"
                    /></template>
                    Export plan
                  </Button>
                  <Button variant="primary" size="sm" @click="addLeg">
                    <template #icon
                      ><svg-icon name="plus" :size="14" style="color: #fff"
                    /></template>
                    Add Leg
                  </Button>
                </div>
              </div>

              <!-- Mini Stats -->
              <div
                style="
                  display: grid;
                  grid-template-columns: repeat(4, 1fr);
                  gap: 10px;
                "
              >
                <MiniStat
                  label="Movements"
                  :value="selectedTeamObj.items.length"
                />
                <MiniStat
                  label="Completed"
                  :value="selectedTeamObj.items.filter((m) => m.actual).length"
                  tone="ok"
                />
                <MiniStat
                  label="Total Pax"
                  :value="teamTotalPax(selectedTeamObj)"
                />
                <MiniStat
                  label="Status"
                  :value="teamStatusLabel(selectedTeamObj)"
                  :tone="teamStatusTone(selectedTeamObj)"
                />
              </div>
            </div>

            <!-- Plan legs card -->
            <div
              style="
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 10px;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                max-height: 600px;
              "
            >
              <div
                style="
                  padding: 14px 16px;
                  border-bottom: 1px solid var(--border);
                  display: flex;
                  justify-content: space-between;
                  align-items: center;
                "
              >
                <div>
                  <div
                    style="font-size: 11px; color: var(--ink3); margin-top: 2px"
                  >
                    Planned vs. actual
                  </div>
                  <div
                    style="font-size: 14px; font-weight: 700; color: var(--ink)"
                  >
                    Plan legs
                  </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px">
                  <div style="display: flex; gap: 4px">
                    <button
                      @click="teamMovementKindFilter = null"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === null
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === null
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      All
                    </button>
                    <button
                      @click="teamMovementKindFilter = 'arrival'"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === 'arrival'
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === 'arrival'
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      Arrival
                    </button>
                    <button
                      @click="teamMovementKindFilter = 'departure'"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === 'departure'
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === 'departure'
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      Departure
                    </button>
                    <button
                      @click="teamMovementKindFilter = 'transfer'"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === 'transfer'
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === 'transfer'
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      Transfer
                    </button>
                    <button
                      @click="teamMovementKindFilter = 'match'"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === 'match'
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === 'match'
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      Match
                    </button>
                  </div>
                  <div style="font-size: 11px; color: var(--ink3)">
                    <b style="color: var(--ink2)">{{
                      filteredTeamMovements.filter((m) => m.actual).length
                    }}</b>
                    completed
                  </div>
                </div>
              </div>
              <div
                style="
                  display: grid;
                  grid-template-columns: 28px 100px 1fr 1.4fr 1.4fr 110px 110px 90px;
                  gap: 10px;
                  padding: 10px 14px;
                  border-bottom: 1px solid var(--border);
                  font-size: 11px;
                  font-weight: 700;
                  color: var(--ink3);
                  letter-spacing: 0.6px;
                  text-transform: uppercase;
                  background: var(--surface);
                  position: sticky;
                  top: 0;
                  z-index: 1;
                "
              >
                <div />
                <div>Leg</div>
                <div>Type</div>
                <div>From → To</div>
                <div>Planned / Actual</div>
                <div>Source</div>
                <div>Linked Job</div>
                <div>Status</div>
              </div>
              <div style="overflow-y: auto; flex: 1">
                <div
                  v-for="(mv, i) in filteredTeamMovements"
                  :key="mv.id"
                  :style="{
                    display: 'grid',
                    gridTemplateColumns:
                      '28px 100px 1fr 1.4fr 1.4fr 110px 110px 90px',
                    gap: '10px',
                    padding: '12px 14px',
                    borderBottom:
                      i === selectedTeamObj.items.length - 1
                        ? 'none'
                        : '1px solid var(--border)',
                    alignItems: 'center',
                    cursor: 'pointer',
                    transition: 'background 0.13s',
                    borderLeft:
                      selectedMovement?.id === mv.id
                        ? '3px solid var(--accent)'
                        : '3px solid transparent',
                    background:
                      selectedMovement?.id === mv.id
                        ? 'var(--accent-soft, #EEF0FE)'
                        : 'transparent',
                  }"
                  @click="selectMovement(mv)"
                  @mouseenter="
                    selectedMovement?.id !== mv.id &&
                      ($event.currentTarget.style.background = 'var(--panel)')
                  "
                  @mouseleave="
                    selectedMovement?.id !== mv.id &&
                      ($event.currentTarget.style.background = 'transparent')
                  "
                >
                  <svg-icon
                    :name="mvIcon(mv)"
                    :size="16"
                    :style="{ color: mvIconColor(mv) }"
                  />
                  <div
                    style="
                      font-family: var(--font-mono, monospace);
                      font-size: 11px;
                      color: var(--ink);
                      font-weight: 600;
                    "
                  >
                    L{{ i + 1 }}
                  </div>
                  <div
                    style="
                      font-size: 12px;
                      color: var(--ink2);
                      text-transform: capitalize;
                    "
                  >
                    <span
                      v-if="mv.match_id"
                      class="kind-badge kind-badge--match"
                      style="background: #fef3c7; color: #92400e; border-color: #fbbf24; font-size: 11px;"
                    >
                      Match {{ mv.match?.match_number || '' }}
                    </span>
                    <span v-else>{{ mv.kind }}</span>
                  </div>
                  <div style="font-size: 12px; color: var(--ink)">
                    <div>{{ movementFromLocation(mv) }}</div>
                    <div style="color: var(--ink3); font-size: 11px">
                      → {{ movementToLocation(mv) }}
                    </div>
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      font-family: var(--font-mono, monospace);
                      line-height: 1.5;
                    "
                  >
                    <div style="color: var(--ink3)">
                      plan {{ mv.dep }} – {{ mv.arr }}
                    </div>
                    <div
                      :style="{
                        color: mv.actual
                          ? mv.status === 'delayed'
                            ? 'var(--warn)'
                            : 'var(--ok)'
                          : 'var(--ink4)',
                      }"
                    >
                      {{
                        mv.actual
                          ? `act  ${mv.actual}${
                              mv.actual_arr ? " – " + mv.actual_arr : ""
                            }`
                          : mv.status === "scheduled"
                          ? "pending"
                          : "in progress"
                      }}
                    </div>
                  </div>
                  <div
                    style="
                      font-size: 10px;
                      color: var(--ink3);
                      font-family: var(--font-mono, monospace);
                    "
                  >
                    <span
                      v-if="mv.source && mv.source.includes('live')"
                      style="color: var(--accent); margin-right: 4px"
                      >●</span
                    >
                    {{ mv.source || "manual" }}
                  </div>
                  <div
                    style="
                      font-family: var(--font-mono, monospace);
                      font-size: 11px;
                    "
                  >
                    <span
                      v-if="mv.jobId"
                      @click="$inertia.visit(`/job/${mv.jobId}`)"
                      style="
                        color: var(--accent);
                        font-weight: 600;
                        cursor: pointer;
                      "
                      >{{ mv.jobId }} →</span
                    >
                    <span v-else style="color: var(--ink4)">—</span>
                  </div>
                  <status-pill
                    :tone="statusTone(mv.status)"
                    :dot="true"
                    size="sm"
                  >
                    {{
                      mv.status === "delayed" && mv.delay
                        ? `+${mv.delay}m`
                        : statusLabel(mv.status)
                    }}
                  </status-pill>
                </div>
              </div>
            </div>
          </div>

          <!-- Movement Detail Panel (By Team) -->
          <transition name="slide-card">
            <div v-if="selectedMovement" class="movement-detail-panel">
              <!-- Header -->
              <div class="detail-card-header">
                <div
                  style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    margin-bottom: 6px;
                    flex-wrap: wrap;
                  "
                >
                  <span
                    style="
                      font-family: var(--mono);
                      font-size: 13px;
                      font-weight: 700;
                      color: var(--ink);
                    "
                  >
                    {{ selectedMovement.code || "MVT" }}
                  </span>
                  <span
                    v-if="selectedMovement.match_id"
                    class="kind-badge"
                    style="font-size: 10px; background: #fef3c7; color: #92400e; border: 1px solid #fbbf24;"
                  >
                    Match {{ selectedMovement.match?.match_number || '' }} {{ selectedMovement.match?.match_number || '' }}
                  </span>
                  <span
                    v-else-if="selectedMovement.kind"
                    :class="['kind-badge', `kind-badge--${selectedMovement.kind}`]"
                    style="font-size: 10px"
                  >
                    {{ selectedMovement.kind }}
                  </span>
                  <span
                    v-if="selectedMovement.status"
                    class="dc-pill dc-pill--ghost"
                  >
                    {{ selectedMovement.status }}
                  </span>
                </div>
                <div
                  style="
                    font-size: 15px;
                    font-weight: 700;
                    color: var(--ink);
                    margin-bottom: 3px;
                  "
                >
                  {{ selectedMovement.team?.team_name || "—" }}
                </div>
                <div style="font-size: 12px; color: var(--ink3)">
                  {{ movementFromLocation(selectedMovement) || "Origin" }} →
                  {{ movementToLocation(selectedMovement) || "Destination" }}
                </div>

                <!-- Stats grid -->
                <div class="dc-stats-grid">
                  <div class="dc-stat">
                    <div class="dc-stat-label">Window</div>
                    <div class="dc-stat-value">
                      {{
                        selectedMovement.dep ||
                        formatTime(selectedMovement.window_start) ||
                        "—"
                      }}
                      →
                      {{
                        selectedMovement.arr ||
                        formatTime(selectedMovement.window_end) ||
                        "—"
                      }}
                    </div>
                  </div>
                  <div class="dc-stat">
                    <div class="dc-stat-label">Pax</div>
                    <div class="dc-stat-value">
                      {{
                        selectedMovement.pax ??
                        selectedMovement.passengers ??
                        "—"
                      }}
                    </div>
                  </div>
                </div>

                <button
                  @click="selectedMovement = null"
                  class="detail-card-close"
                  style="position: absolute; top: 14px; right: 14px"
                >
                  <svg-icon name="x" :size="16" />
                </button>
              </div>

              <!-- Checkpoints -->
              <div class="detail-card-content">
                <CheckpointTimeline
                  :checkpoints="
                    selectedMovement.checkpoints ||
                    selectedMovement.checkpoint_template?.checkpoints
                  "
                  title="Checkpoints"
                  empty-message="No checkpoints defined"
                />

                <!-- Job Info -->
                <div
                  style="
                    margin-top: 16px;
                    padding-top: 16px;
                    border-top: 1px solid var(--border);
                  "
                >
                  <div
                    style="
                      font-size: 10px;
                      font-weight: 700;
                      letter-spacing: 0.6px;
                      text-transform: uppercase;
                      color: var(--ink3);
                      margin-bottom: 8px;
                    "
                  >
                    Job Information
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Vehicle</span>
                    <span class="detail-value">{{
                      typeof selectedMovement.vehicle === "string"
                        ? selectedMovement.vehicle || "—"
                        : selectedMovement.vehicle?.code ||
                          selectedMovement.vehicle?.vehicle_type ||
                          "—"
                    }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Driver</span>
                    <span class="detail-value">{{
                      typeof selectedMovement.driver === "string"
                        ? selectedMovement.driver || "—"
                        : selectedMovement.driver?.name || "—"
                    }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Field Supervisor</span>
                    <span class="detail-value">{{
                      typeof selectedMovement.field_supervisor === "string"
                        ? selectedMovement.field_supervisor || "—"
                        : selectedMovement.field_supervisor?.name || "—"
                    }}</span>
                  </div>
                  <div
                    v-if="selectedMovement.job_id || selectedMovement.jobId"
                    class="detail-row"
                  >
                    <span class="detail-label">Job ID</span>
                    <span
                      class="detail-value mono"
                      style="color: var(--accent); cursor: pointer"
                      @click="
                        $inertia.visit(
                          `/job/${
                            selectedMovement.job_id || selectedMovement.jobId
                          }`
                        )
                      "
                    >
                      {{ selectedMovement.job_id || selectedMovement.jobId }}
                    </span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Status</span>
                    <span class="detail-value">{{
                      selectedMovement.status || "Pending"
                    }}</span>
                  </div>
                </div>
              </div>

              <!-- Footer -->
              <div class="detail-card-footer">
                <Button
                  variant="secondary"
                  size="sm"
                  @click="selectedMovement = null"
                  >Close</Button
                >
                <div style="flex: 1"></div>
                <Button
                  variant="primary"
                  size="sm"
                  @click="
                    editMovement(selectedMovement);
                    selectedMovement = null;
                  "
                  >Edit Movement</Button
                >
              </div>
            </div>
          </transition>
        </div>

        <!-- Empty state -->
        <div
          v-else
          style="
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 12px;
            color: var(--ink3);
          "
        >
          <svg
            width="48"
            height="48"
            viewBox="0 0 20 20"
            fill="none"
            style="opacity: 0.3"
          >
            <path
              d="M10 2L2 7L10 12L18 7L10 2Z"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <path
              d="M2 12L10 17L18 12"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <div style="font-size: 13px">Select a team to view movements</div>
        </div>
      </div>
    </template>

    <!-- New Plan modal -->
    <teleport to="body">
      <div
        v-if="showNewPlan"
        class="modal-backdrop"
        @click.self="showNewPlan = false"
      >
        <div class="modal" style="max-width: 580px">
          <div class="modal-header">
            <span class="modal-title">New Plan</span>
            <button class="modal-close" @click="showNewPlan = false">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="new-plan-body">
            <div class="modal-body">
              <div
                v-if="!activeEvent"
                style="
                  margin-bottom: 16px;
                  padding: 12px;
                  background: #fef3c7;
                  border: 1px solid #fcd34d;
                  border-radius: 6px;
                  font-size: 12px;
                  color: #92400e;
                "
              >
                ⚠️ No active event selected. Please select an event from the
                dropdown above to create plans.
              </div>
              <div
                v-else
                style="
                  margin-bottom: 16px;
                  padding: 10px;
                  background: var(--panel);
                  border: 1px solid var(--border);
                  border-radius: 6px;
                  font-size: 12px;
                "
              >
                <div
                  style="
                    color: var(--ink3);
                    margin-bottom: 4px;
                    font-size: 10px;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                    font-weight: 600;
                  "
                >
                  Creating plan for:
                </div>
                <div style="color: var(--ink); font-weight: 500">
                  {{ activeEvent.name }}
                </div>
              </div>

              <!-- Mode Selector -->
              <div
                style="
                  display: grid;
                  grid-template-columns: 1fr 1fr;
                  gap: 8px;
                  margin-bottom: 16px;
                "
              >
                <button
                  class="am-mode-btn"
                  :class="{ 'am-mode-btn--active': newPlanMode === 'single' }"
                  @click="newPlanMode = 'single'"
                >
                  <div style="font-size: 13px; font-weight: 700">
                    Single Plan
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      color: inherit;
                      opacity: 0.7;
                      margin-top: 1px;
                    "
                  >
                    One plan for specific team/date
                  </div>
                </button>
                <button
                  class="am-mode-btn"
                  :class="{ 'am-mode-btn--active': newPlanMode === 'bulk' }"
                  @click="newPlanMode = 'bulk'"
                >
                  <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M17.8 19.2L16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>
                    </svg>
                    Bulk Create by Arrival
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      color: inherit;
                      opacity: 0.7;
                      margin-top: 1px;
                    "
                  >
                    Group teams by arrival date
                  </div>
                </button>
              </div>

              <!-- Single Plan Mode -->
              <div v-if="newPlanMode === 'single'">
                <div
                  style="
                    display: grid;
                    grid-template-columns: 1fr 120px;
                    gap: 12px;
                  "
                >
                  <div class="form-field">
                    <label>Date <span style="color: #dc2626">*</span></label>
                    <input
                      v-model="newPlanDate"
                      type="date"
                      :style="
                        newPlanErrors.date ? { borderColor: '#DC2626' } : {}
                      "
                      @input="newPlanErrors.date = ''"
                    />
                    <span
                      v-if="newPlanErrors.date"
                      style="
                        color: #dc2626;
                        font-size: 12px;
                        margin-top: 4px;
                        display: block;
                      "
                      >{{ newPlanErrors.date }}</span
                    >
                  </div>
                  <div class="form-field">
                    <label>Start time</label>
                    <input v-model="newPlanStartTime" type="time" />
                  </div>
                </div>
                <div class="form-field">
                  <label>Name</label>
                  <input
                    v-model="newPlanName"
                    type="text"
                    placeholder="e.g., Match Day 4"
                    :style="
                      newPlanErrors.name ? { borderColor: '#DC2626' } : {}
                    "
                    @input="newPlanErrors.name = ''"
                  />
                  <span
                    v-if="newPlanErrors.name"
                    style="
                      color: #dc2626;
                      font-size: 12px;
                      margin-top: 4px;
                      display: block;
                    "
                    >{{ newPlanErrors.name }}</span
                  >
                </div>
                <div class="form-field">
                  <label>Team</label>
                  <select
                    v-model="newPlanTeamId"
                    :style="
                      newPlanErrors.team_id ? { borderColor: '#DC2626' } : {}
                    "
                    @change="handleTeamChange"
                  >
                    <option :value="null">All teams</option>
                    <option
                      v-for="team in props.teams"
                      :key="team.id"
                      :value="team.id"
                    >
                      {{
                        (team.team_name || team.team || "Unnamed Team") +
                        (team.code ? ` (${team.code})` : "")
                      }}
                    </option>
                  </select>
                  <span
                    v-if="newPlanErrors.team_id"
                    style="
                      color: #dc2626;
                      font-size: 12px;
                      margin-top: 4px;
                      display: block;
                    "
                    >{{ newPlanErrors.team_id }}</span
                  >
                </div>

                <!-- Flight selector - only show if team has multiple flights -->
                <div v-if="needsFlightSelection" class="form-field">
                  <label>Flight <span style="color: #dc2626">*</span></label>
                  <select
                    v-model="newPlanFlightId"
                    :style="
                      newPlanErrors.flight_id ? { borderColor: '#DC2626' } : {}
                    "
                  >
                    <option :value="null">Select a flight...</option>
                    <option
                      v-for="flight in selectedTeamFlights"
                      :key="flight.id"
                      :value="flight.id"
                    >
                      {{ flight.flight_number || "Flight" }}
                      <template v-if="flight.origin_airport"
                        >from
                        {{
                          flight.origin_airport.iata ||
                          flight.origin_airport.name
                        }}</template
                      >
                      <template v-if="flight.scheduled_at">
                        -
                        {{
                          new Date(flight.scheduled_at).toLocaleString(
                            "en-US",
                            {
                              month: "short",
                              day: "numeric",
                              hour: "2-digit",
                              minute: "2-digit",
                            }
                          )
                        }}</template
                      >
                      <template v-if="flight.party_size_total">
                        ({{ flight.party_size_total }} pax)</template
                      >
                    </option>
                  </select>
                  <span
                    v-if="newPlanErrors.flight_id"
                    style="
                      color: #dc2626;
                      font-size: 12px;
                      margin-top: 4px;
                      display: block;
                    "
                    >{{ newPlanErrors.flight_id }}</span
                  >
                </div>

                <div class="form-field">
                  <label>Based on template</label>
                  <select v-model="newPlanTemplate">
                    <option value="">Blank</option>
                    <option
                      v-for="template in props.movementTemplates"
                      :key="template.id"
                      :value="template.id"
                    >
                      {{ template.name }} ({{ template.code }})
                    </option>
                  </select>
                </div>

                <!-- Template Preview -->
                <div
                  v-if="selectedNewPlanTemplate"
                  style="
                    margin-top: 16px;
                    border: 1px solid var(--border);
                    border-radius: 10px;
                    overflow: hidden;
                  "
                >
                  <!-- Header -->
                  <div
                    style="
                      padding: 14px 16px;
                      border-bottom: 1px solid var(--border);
                      background: var(--panel);
                      display: flex;
                      align-items: flex-start;
                      justify-content: space-between;
                      gap: 12px;
                    "
                  >
                    <div>
                      <div
                        style="
                          font-size: 14px;
                          font-weight: 700;
                          color: var(--ink);
                          margin-bottom: 4px;
                        "
                      >
                        {{ selectedNewPlanTemplate.name }}
                      </div>
                      <div style="font-size: 12px; color: var(--ink3)">
                        {{ selectedNewPlanTemplate.legs?.length || 0 }} legs
                        <template
                          v-if="
                            selectedNewPlanTemplate.estimated_duration_minutes
                          "
                        >
                          · avg
                          {{
                            Math.floor(
                              selectedNewPlanTemplate.estimated_duration_minutes /
                                60
                            )
                          }}h
                          {{
                            selectedNewPlanTemplate.estimated_duration_minutes %
                            60
                          }}m
                        </template>
                      </div>
                    </div>
                    <span
                      style="
                        flex-shrink: 0;
                        padding: 3px 8px;
                        border-radius: 6px;
                        font-size: 11px;
                        font-weight: 700;
                        font-family: var(--mono);
                        background: var(--accent-soft);
                        color: var(--accent);
                      "
                      >{{ selectedNewPlanTemplate.code }}</span
                    >
                  </div>

                  <!-- Legs -->
                  <div
                    v-if="
                      selectedNewPlanTemplate.legs &&
                      selectedNewPlanTemplate.legs.length > 0
                    "
                    style="max-height: 320px; overflow-y: auto"
                  >
                    <div
                      style="
                        padding: 7px 16px;
                        background: var(--panel);
                        border-bottom: 1px solid var(--border);
                      "
                    >
                      <span
                        style="
                          font-size: 10px;
                          font-weight: 700;
                          letter-spacing: 1px;
                          text-transform: uppercase;
                          color: var(--ink3);
                        "
                        >Will generate
                        {{
                          selectedNewPlanTemplate.legs.length
                        }}
                        movements</span
                      >
                    </div>
                    <div
                      v-for="(leg, i) in selectedNewPlanTemplate.legs"
                      :key="i"
                      :style="{
                        display: 'grid',
                        gridTemplateColumns: '40px 90px 1fr auto',
                        gap: '10px',
                        padding: '10px 16px',
                        alignItems: 'center',
                        borderBottom:
                          i < selectedNewPlanTemplate.legs.length - 1
                            ? '1px solid var(--border)'
                            : 'none',
                        background: 'var(--surface)',
                      }"
                    >
                      <div
                        style="
                          font-family: var(--mono);
                          font-size: 11px;
                          font-weight: 700;
                          color: var(--ink);
                        "
                      >
                        M{{ i + 1 }}
                      </div>
                      <span
                        v-if="leg.leg_type"
                        :class="['kind-badge', `kind-badge--${leg.leg_type}`]"
                        >{{ leg.leg_type }}</span
                      >
                      <span v-else style="font-size: 11px; color: var(--ink3)"
                        >—</span
                      >
                      <div style="display: flex; flex-direction: column; gap: 3px;">
                        <div
                          style="
                            font-size: 12px;
                            color: var(--ink);
                            font-weight: 500;
                          "
                        >
                          <template v-if="leg.from_location && leg.to_location">
                            {{ leg.from_location }} → {{ leg.to_location }}
                          </template>
                          <template v-else-if="leg.checkpoint_template">
                            {{ leg.checkpoint_template.name }}
                          </template>
                          <template v-else>
                            Movement {{ i + 1 }}
                          </template>
                        </div>
                        <div v-if="leg.checkpoint_template" style="font-size: 11px; color: var(--ink3);">
                          <span style="font-family: var(--mono); font-weight: 600;">{{ leg.checkpoint_template.code }}</span> · {{ leg.checkpoint_template.name }}
                        </div>
                      </div>
                      <div
                        style="
                          font-family: var(--mono);
                          font-size: 11px;
                          color: var(--ink3);
                          white-space: nowrap;
                        "
                      >
                        {{ previewLegTime(i, "start") }} →
                        {{ previewLegTime(i, "end") }}
                      </div>
                    </div>
                  </div>

                  <!-- Heads-up notice -->
                  <div
                    style="
                      background: #fffbeb;
                      border-top: 1px solid #fde68a;
                      padding: 10px 14px;
                      font-size: 12px;
                      color: #92400e;
                      display: flex;
                      gap: 5px;
                      align-items: baseline;
                    "
                  >
                    <span style="font-weight: 700; white-space: nowrap"
                      >Heads-up</span
                    >
                    <span
                      >· Vehicles &amp; drivers will be auto-assigned from the
                      available pool. Conflicts will surface in the Conflicts
                      tab.</span
                    >
                  </div>
                </div>
              </div>

              <!-- Bulk Plan Mode -->
              <div v-else-if="newPlanMode === 'bulk'">
                <div class="form-field">
                  <label
                    >Movement Template
                    <span style="color: #dc2626">*</span></label
                  >
                  <select
                    v-model="newPlanTemplate"
                    :style="
                      newPlanErrors.template ? { borderColor: '#DC2626' } : {}
                    "
                  >
                    <option value="">Select a template...</option>
                    <option
                      v-for="template in props.movementTemplates"
                      :key="template.id"
                      :value="template.id"
                    >
                      {{ template.name }} ({{ template.code }})
                    </option>
                  </select>
                  <span
                    v-if="newPlanErrors.template"
                    style="
                      color: #dc2626;
                      font-size: 12px;
                      margin-top: 4px;
                      display: block;
                    "
                    >{{ newPlanErrors.template }}</span
                  >
                </div>

                <!-- Bulk Preview -->
                <div
                  style="
                    margin-top: 16px;
                    border: 1px solid var(--border);
                    border-radius: 10px;
                    overflow: hidden;
                  "
                >
                  <div
                    style="
                      padding: 14px 16px;
                      border-bottom: 1px solid var(--border);
                      background: var(--panel);
                    "
                  >
                    <div
                      style="
                        font-size: 13px;
                        font-weight: 700;
                        color: var(--ink);
                        margin-bottom: 6px;
                      "
                    >
                      Bulk Creation Preview
                    </div>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap">
                      <div>
                        <span
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                          >{{ bulkPreview.plansCount }}</span
                        >
                        <span
                          style="
                            font-size: 12px;
                            color: var(--ink3);
                            margin-left: 4px;
                          "
                          >plans</span
                        >
                      </div>
                      <div>
                        <span
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                          >{{ bulkPreview.teamsWithDates }}</span
                        >
                        <span
                          style="
                            font-size: 12px;
                            color: var(--ink3);
                            margin-left: 4px;
                          "
                          >teams</span
                        >
                      </div>
                      <div v-if="bulkPreview.teamsWithoutDates > 0">
                        <span
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: #dc2626;
                          "
                          >{{ bulkPreview.teamsWithoutDates }}</span
                        >
                        <span
                          style="
                            font-size: 12px;
                            color: var(--ink3);
                            margin-left: 4px;
                          "
                          >missing dates</span
                        >
                      </div>
                    </div>
                  </div>

                  <!-- Plans List -->
                  <div
                    v-if="bulkPreview.plans.length > 0"
                    style="max-height: 320px; overflow-y: auto"
                  >
                    <div
                      style="
                        padding: 7px 16px;
                        background: var(--panel);
                        border-bottom: 1px solid var(--border);
                      "
                    >
                      <span
                        style="
                          font-size: 10px;
                          font-weight: 700;
                          letter-spacing: 1px;
                          text-transform: uppercase;
                          color: var(--ink3);
                        "
                        >Plans to be created</span
                      >
                    </div>
                    <div
                      v-for="(plan, i) in bulkPreview.plans"
                      :key="plan.date"
                      :style="{
                        padding: '12px 16px',
                        borderBottom:
                          i < bulkPreview.plans.length - 1
                            ? '1px solid var(--border)'
                            : 'none',
                        background: 'var(--surface)',
                      }"
                    >
                      <div
                        style="
                          display: flex;
                          justify-content: space-between;
                          align-items: center;
                          margin-bottom: 6px;
                        "
                      >
                        <div
                          style="
                            font-size: 13px;
                            font-weight: 600;
                            color: var(--ink);
                          "
                        >
                          {{ formatDate(plan.date) }}
                        </div>
                        <div
                          style="
                            font-size: 11px;
                            color: var(--ink3);
                            font-weight: 600;
                          "
                        >
                          {{ plan.teams.length }}
                          {{ plan.teams.length === 1 ? "team" : "teams" }}
                        </div>
                      </div>
                      <div style="display: flex; flex-wrap: wrap; gap: 4px">
                        <div
                          v-for="team in plan.teams"
                          :key="team.id"
                          style="display: inline-block"
                        >
                          <span
                            @click="team.has_multiple_flights && toggleTeamFlightExpansion(team.id)"
                            :style="{
                              fontSize: '10px',
                              padding: '2px 6px',
                              background: 'var(--panel)',
                              border: '1px solid var(--border)',
                              borderRadius: '4px',
                              color: 'var(--ink2)',
                              fontWeight: '600',
                              cursor: team.has_multiple_flights ? 'pointer' : 'default',
                              display: 'inline-flex',
                              alignItems: 'center',
                              gap: '4px',
                            }"
                            :title="team.has_multiple_flights ? 'Click to select flight' : ''"
                          >
                            {{ team.code }}
                            <span
                              v-if="team.has_multiple_flights"
                              style="
                                background: #3b82f6;
                                color: white;
                                padding: 0 4px;
                                border-radius: 3px;
                                font-size: 9px;
                                font-weight: 700;
                              "
                            >
                              {{ team.flights.length }}
                            </span>
                          </span>
                          
                          <!-- Flight Selection Dropdown -->
                          <div
                            v-if="expandedTeams.has(team.id)"
                            style="
                              position: absolute;
                              margin-top: 2px;
                              background: var(--surface);
                              border: 1px solid var(--border);
                              border-radius: 6px;
                              box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                              padding: 8px;
                              z-index: 1000;
                              min-width: 200px;
                            "
                          >
                            <div
                              style="
                                font-size: 10px;
                                font-weight: 700;
                                color: var(--ink3);
                                margin-bottom: 6px;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                              "
                            >
                              Select Flight
                            </div>
                            <div
                              v-for="flight in team.flights"
                              :key="flight.id"
                              @click="selectTeamFlight(team.id, flight.id)"
                              :style="{
                                padding: '6px 8px',
                                borderRadius: '4px',
                                cursor: 'pointer',
                                background: flight.id === team.selected_flight?.id ? '#dbeafe' : 'transparent',
                                border: flight.id === team.selected_flight?.id ? '1px solid #3b82f6' : '1px solid transparent',
                                marginBottom: '4px',
                              }"
                            >
                              <div style="font-size: 11px; font-weight: 600; color: var(--ink)">
                                {{ flight.flight_number || 'N/A' }}
                              </div>
                              <div style="font-size: 10px; color: var(--ink3)">
                                {{ flight.scheduled_time || '—' }}
                                <span v-if="flight.origin_airport">
                                  • {{ flight.origin_airport }}
                                </span>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Teams Without Dates -->
                  <div
                    v-if="bulkPreview.teamsWithoutDates > 0"
                    style="
                      background: #fef3c7;
                      border-top: 1px solid #fcd34d;
                      padding: 10px 14px;
                    "
                  >
                    <div
                      style="
                        font-size: 12px;
                        color: #92400e;
                        margin-bottom: 4px;
                      "
                    >
                      <span style="font-weight: 700"
                        >⚠️ {{ bulkPreview.teamsWithoutDates }} teams missing
                        arrival dates</span
                      >
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px">
                      <span
                        v-for="team in bulkPreview.teamsWithoutDatesList"
                        :key="team.id"
                        style="
                          font-size: 10px;
                          padding: 2px 6px;
                          background: #fffbeb;
                          border: 1px solid #fcd34d;
                          border-radius: 4px;
                          color: #92400e;
                          font-weight: 600;
                        "
                      >
                        {{ team.code }}
                      </span>
                    </div>
                  </div>

                  <!-- Empty State -->
                  <div
                    v-if="bulkPreview.plans.length === 0"
                    style="
                      padding: 40px 20px;
                      text-align: center;
                      color: var(--ink3);
                    "
                  >
                    <div
                      style="
                        font-size: 13px;
                        font-weight: 600;
                        margin-bottom: 4px;
                      "
                    >
                      No teams with arrival dates
                    </div>
                    <div style="font-size: 12px">
                      Please set arrival dates for teams first
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <Button
              variant="ghost"
              size="sm"
              @click="showNewPlan = false"
              :disabled="newPlanProcessing"
              >Cancel</Button
            >
            <Button
              v-if="newPlanMode === 'single'"
              variant="primary"
              size="sm"
              @click="createPlan"
              :disabled="newPlanProcessing"
            >
              <span v-if="newPlanProcessing">Creating...</span>
              <span v-else>Create Plan</span>
            </Button>
            <Button
              v-else-if="newPlanMode === 'bulk'"
              variant="primary"
              size="sm"
              @click="createBulkPlans"
              :disabled="newPlanProcessing || bulkPreview.plans.length === 0"
            >
              <span v-if="newPlanProcessing"
                >Creating {{ bulkPreview.plansCount }} Plans...</span
              >
              <span v-else>Create {{ bulkPreview.plansCount }} Plans</span>
            </Button>
          </div>
        </div>
      </div>

      <!-- Edit Plan Modal -->
      <div
        v-if="showEditPlan"
        class="modal-backdrop"
        @click.self="showEditPlan = false"
      >
        <div class="modal">
          <div class="modal-header">
            <span class="modal-title">Edit Plan</span>
            <button class="modal-close" @click="showEditPlan = false">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <div class="form-field">
              <label>Plan code</label>
              <input
                :value="editingPlan?.code"
                type="text"
                disabled
                style="
                  background: var(--panel);
                  color: var(--ink3);
                  cursor: not-allowed;
                "
              />
              <span
                style="
                  color: var(--ink3);
                  font-size: 11px;
                  margin-top: 4px;
                  display: block;
                "
                >Code cannot be changed</span
              >
            </div>
            <div class="form-field">
              <label>Plan name <span style="color: #dc2626">*</span></label>
              <input
                v-model="editPlanName"
                type="text"
                placeholder="e.g. Match Day 5 – Official"
                :style="editPlanErrors.name ? { borderColor: '#DC2626' } : {}"
                @input="editPlanErrors.name = ''"
              />
              <span
                v-if="editPlanErrors.name"
                style="
                  color: #dc2626;
                  font-size: 12px;
                  margin-top: 4px;
                  display: block;
                "
              >
                <template v-if="Array.isArray(editPlanErrors.name)">
                  <div v-for="(msg, idx) in editPlanErrors.name" :key="idx">
                    {{ msg }}
                  </div>
                </template>
                <template v-else>{{ editPlanErrors.name }}</template>
              </span>
            </div>
            <div
              style="display: grid; grid-template-columns: 1fr 120px; gap: 12px"
            >
              <div class="form-field">
                <label>Date <span style="color: #dc2626">*</span></label>
                <input
                  v-model="editPlanDate"
                  type="date"
                  :style="editPlanErrors.date ? { borderColor: '#DC2626' } : {}"
                  @input="editPlanErrors.date = ''"
                />
                <span
                  v-if="editPlanErrors.date"
                  style="
                    color: #dc2626;
                    font-size: 12px;
                    margin-top: 4px;
                    display: block;
                  "
                >
                  <template v-if="Array.isArray(editPlanErrors.date)">
                    <div v-for="(msg, idx) in editPlanErrors.date" :key="idx">
                      {{ msg }}
                    </div>
                  </template>
                  <template v-else>{{ editPlanErrors.date }}</template>
                </span>
              </div>
              <div class="form-field">
                <label>Start time</label>
                <input v-model="editPlanStartTime" type="time" />
              </div>
            </div>
            <div class="form-field">
              <label>Based on template</label>
              <select v-model="editPlanTemplate">
                <option value="">Blank</option>
                <option
                  v-for="template in props.movementTemplates"
                  :key="template.id"
                  :value="template.id"
                >
                  {{ template.name }} ({{ template.code }})
                </option>
              </select>
            </div>
            <div class="form-field">
              <label>Status <span style="color: #dc2626">*</span></label>
              <select
                v-model="editPlanStatus"
                :style="editPlanErrors.status ? { borderColor: '#DC2626' } : {}"
                @change="editPlanErrors.status = ''"
              >
                <option value="draft">Draft</option>
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="archived">Archived</option>
              </select>
              <span
                v-if="editPlanErrors.status"
                style="
                  color: #dc2626;
                  font-size: 12px;
                  margin-top: 4px;
                  display: block;
                "
              >
                <template v-if="Array.isArray(editPlanErrors.status)">
                  <div v-for="(msg, idx) in editPlanErrors.status" :key="idx">
                    {{ msg }}
                  </div>
                </template>
                <template v-else>{{ editPlanErrors.status }}</template>
              </span>
            </div>
          </div>
          <div class="modal-footer">
            <Button
              variant="ghost"
              size="sm"
              @click="showEditPlan = false"
              :disabled="editPlanProcessing"
              >Cancel</Button
            >
            <Button
              variant="primary"
              size="sm"
              @click="updatePlan"
              :disabled="editPlanProcessing"
            >
              <span v-if="editPlanProcessing">Updating...</span>
              <span v-else>Update Plan</span>
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Generate Jobs Modal -->
    <teleport to="body">
      <div
        v-if="showGenerateJobs"
        class="modal-backdrop"
        @click.self="showGenerateJobs = false"
      >
        <div class="modal gen-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">LOGISTICS PLANNING · GENERATE</div>
              <div class="modal-title" style="font-size: 18px">
                Generate Logistics Jobs
              </div>
              <div class="gen-subtitle">
                {{ selectedPlanObj?.code }} ·
                {{ formatDateTime(selectedPlanObj?.date) }} &mdash;
                {{ genMovements.length }} movements ready ·
                {{ genAlreadyCount }} already generated
              </div>
            </div>
            <button class="modal-close" @click="showGenerateJobs = false">
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <!-- Two-column body -->
          <div class="gen-body">
            <!-- Left: movements -->
            <div class="gen-left">
              <div class="gen-section-head">
                <span class="gen-section-label"
                  >MOVEMENTS TO GENERATE ·
                  {{ genSelectedIds.length }} SELECTED</span
                >
                <button class="gen-deselect" @click="genSelectedIds = []">
                  Deselect all
                </button>
              </div>

              <div class="gen-movements">
                <label
                  v-for="mv in genMovements"
                  :key="mv.id"
                  class="gen-mv-row"
                  :class="{
                    'gen-mv-row--checked': genSelectedIds.includes(mv.id),
                  }"
                >
                  <input
                    type="checkbox"
                    :checked="genSelectedIds.includes(mv.id)"
                    @change="toggleGenMovement(mv.id)"
                    class="gen-checkbox"
                  />
                  <div class="gen-mv-id">
                    {{ mv.code || `M${genMovements.indexOf(mv) + 1}` }}
                  </div>
                  <span
                    v-if="mv.match_id"
                    class="kind-badge kind-badge--match"
                    style="font-size: 10px; white-space: nowrap; background: #fef3c7; color: #92400e; border-color: #fbbf24;"
                    >Match {{ mv.match?.match_number || '' }}</span
                  >
                  <span
                    v-else-if="mv.kind"
                    :class="['kind-badge', `kind-badge--${mv.kind}`]"
                    style="font-size: 10px; white-space: nowrap"
                    >{{ mv.kind }}</span
                  >
                  <span v-else style="font-size: 10px; color: var(--ink3)"
                    >—</span
                  >
                  <div class="gen-mv-info">
                    <div class="gen-mv-team">
                      {{ mv.team?.team_name || "—" }}
                    </div>
                    <div class="gen-mv-route">
                      {{ mv.from_location || "—" }} →
                      {{ mv.to_location || "—" }}
                    </div>
                  </div>
                  <div class="gen-mv-times">
                    <div
                      style="
                        font-family: var(--mono);
                        font-size: 11px;
                        color: var(--ink2);
                      "
                    >
                      {{ formatTime(mv.window_start) || "—" }} →
                      {{ formatTime(mv.window_end) || "—" }}
                    </div>
                    <div style="font-size: 10.5px; color: var(--ink3)">
                      {{ mv.passengers || 0 }} pax ·
                      {{ mv.checkpoint_template?.checkpoints?.length || 0 }} chk
                    </div>
                  </div>
                  <div
                    class="gen-mv-vehicle"
                    :class="mv.vehicle ? '' : 'gen-mv-vehicle--warn'"
                  >
                    <svg-icon v-if="!mv.vehicle" name="warn" :size="12" />
                    {{
                      mv.vehicle?.code ||
                      mv.vehicle?.vehicle_type ||
                      "no vehicle"
                    }}
                  </div>
                </label>

                <div
                  v-if="genMovements.length === 0"
                  style="
                    padding: 20px;
                    text-align: center;
                    color: var(--ink3);
                    font-size: 13px;
                  "
                >
                  All movements already have jobs generated.
                </div>
              </div>

              <div v-if="conflicts.length > 0" class="gen-conflict-banner">
                <svg-icon name="warn" :size="14" style="flex-shrink: 0" />
                <span
                  ><b>{{ conflicts.length }} unresolved conflicts</b> — jobs
                  will be generated with warnings. Review in the Conflicts tab
                  to resolve before dispatch.</span
                >
              </div>
            </div>

            <!-- Right: template + options + summary -->
            <div class="gen-right">
              <div class="gen-section-label" style="margin-bottom: 8px">
                OPTIONS
              </div>
              <div class="gen-options">
                <!-- <div class="gen-option-row">
                  <div>
                    <div class="gen-option-title">Auto-assign vehicles &amp; drivers</div>
                    <div class="gen-option-desc">Use Fleet availability · capacity ≥ pax</div>
                  </div>
                  <button class="gen-toggle" :class="{ 'gen-toggle--on': genAutoAssign }" @click="genAutoAssign = !genAutoAssign">
                    <span class="gen-toggle-knob" />
                  </button>
                </div> -->
                <div class="gen-option-row">
                  <div>
                    <div class="gen-option-title">Notify team liaisons</div>
                    <div class="gen-option-desc">
                      Push to MS Teams on generation
                    </div>
                  </div>
                  <button
                    class="gen-toggle"
                    :class="{ 'gen-toggle--on': genNotifyLiaisons }"
                    @click="genNotifyLiaisons = !genNotifyLiaisons"
                  >
                    <span class="gen-toggle-knob" />
                  </button>
                </div>
              </div>

              <div class="gen-summary">
                <div class="gen-section-label" style="margin-bottom: 8px">
                  Summary
                </div>
                <ul class="gen-summary-list">
                  <li>
                    Generating <b>{{ genSelectedIds.length }} jobs</b>
                  </li>
                  <!-- <li>Auto-assign: <b>{{ genAutoAssign ? 'on' : 'off' }}</b></li> -->
                  <li>
                    Liaison notify:
                    <b>{{ genNotifyLiaisons ? "on" : "off" }}</b>
                  </li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <div class="gen-footer-count">
              Selected <b>{{ genSelectedIds.length }}</b> of
              <b>{{ genMovements.length }}</b> movements
            </div>
            <div style="display: flex; gap: 8px">
              <Button
                variant="secondary"
                size="sm"
                @click="showGenerateJobs = false"
                :disabled="genProcessing"
                >Cancel</Button
              >
              <Button
                variant="primary"
                size="sm"
                @click="confirmGenerateJobs"
                :disabled="genSelectedIds.length === 0 || genProcessing"
              >
                {{
                  genProcessing
                    ? "Generating..."
                    : `Generate ${genSelectedIds.length} jobs →`
                }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Generation Progress Modal -->
    <teleport to="body">
      <div
        v-if="showGenProgress"
        class="modal-backdrop"
        @click.self="genDone ? (showGenProgress = false) : null"
      >
        <div class="modal gen-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">LOGISTICS PLANNING · GENERATE</div>
              <div class="modal-title" style="font-size: 18px">
                Generate Logistics Jobs
              </div>
              <div class="gen-subtitle">
                {{ selectedPlanObj?.code }} ·
                {{ selectedPlanObj?.date }} &mdash;
                {{ genProgressTotal }} movements ready ·
                {{ genAlreadyCount }} already generated
              </div>
            </div>
            <button
              class="modal-close"
              :style="{
                opacity: genDone ? 1 : 0.35,
                cursor: genDone ? 'pointer' : 'default',
              }"
              @click="genDone ? (showGenProgress = false) : null"
            >
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <!-- Progress body -->
          <div class="gen-progress-body">
            <div class="gen-progress-label">
              {{ genDone ? "COMPLETE" : "GENERATING" }}
            </div>
            <div class="gen-progress-counter">
              {{ genProgressCurrent }}
              <span style="color: var(--ink3); font-weight: 400">/</span>
              {{ genProgressTotal }}
            </div>
            <div class="gen-progress-bar-track">
              <div
                class="gen-progress-bar-fill"
                :style="{
                  width: genProgressTotal
                    ? `${(genProgressCurrent / genProgressTotal) * 100}%`
                    : '0%',
                }"
              />
            </div>
            <div class="gen-log" v-if="genLog.length > 0">
              <div v-for="(line, i) in genLog" :key="i" class="gen-log-line">
                <span class="gen-log-check">✓</span> {{ line }}
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <div v-if="!genDone" class="gen-generating-hint">
              Please don't close while
              <span style="color: var(--accent); font-weight: 500"
                >generating...</span
              >
            </div>
            <div
              v-else
              style="
                display: flex;
                gap: 8px;
                width: 100%;
                justify-content: flex-end;
              "
            >
              <Button
                variant="secondary"
                size="sm"
                @click="showGenProgress = false"
                >Close</Button
              >
              <Button
                variant="primary"
                size="sm"
                @click="showGenProgress = false"
                >View jobs →</Button
              >
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- New Team Plan Modal -->
    <teleport to="body">
      <div
        v-if="showNewTeamPlan"
        class="modal-backdrop"
        @click.self="showNewTeamPlan = false"
      >
        <div class="modal ntp-modal">
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">TEAM ITINERARY</div>
              <div class="modal-title" style="font-size: 18px">
                New team plan
              </div>
            </div>
            <button class="modal-close" @click="showNewTeamPlan = false">
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <div class="ntp-body">
            <!-- Row 1: Team Name + Country + Code -->
            <div class="ntp-row ntp-row--3">
              <div class="form-field">
                <label class="ntp-label">TEAM NAME</label>
                <input
                  v-model="ntpTeam"
                  class="ntp-input"
                  placeholder="e.g. Atlético Costa"
                />
              </div>
              <div class="form-field">
                <label class="ntp-label">COUNTRY</label>
                <input
                  v-model="ntpCountry"
                  class="ntp-input"
                  placeholder="e.g. Portugal"
                />
              </div>
              <div class="form-field">
                <label class="ntp-label">CODE</label>
                <input
                  v-model="ntpCode"
                  class="ntp-input"
                  placeholder="ATL"
                  maxlength="3"
                  style="text-transform: uppercase"
                />
              </div>
            </div>

            <!-- Row 2: Origin + Destination -->
            <div class="ntp-row ntp-row--2">
              <div class="form-field">
                <label class="ntp-label">ORIGIN (FLYING FROM)</label>
                <input
                  v-model="ntpOrigin"
                  class="ntp-input"
                  placeholder="e.g. Lisbon (LIS)"
                />
              </div>
              <div class="form-field">
                <label class="ntp-label">FINAL DESTINATION</label>
                <input
                  v-model="ntpDestination"
                  class="ntp-input"
                  placeholder="e.g. Stadium Azure"
                />
              </div>
            </div>

            <!-- Row 3: Dates + Passengers + Liaison -->
            <div class="ntp-row ntp-row--4">
              <div class="form-field">
                <label class="ntp-label">ARRIVAL DATE</label>
                <input v-model="ntpArrival" type="date" class="ntp-input" />
              </div>
              <div class="form-field">
                <label class="ntp-label">DEPARTURE DATE</label>
                <input v-model="ntpDeparture" type="date" class="ntp-input" />
              </div>
              <div class="form-field">
                <label class="ntp-label">PASSENGERS</label>
                <input
                  v-model="ntpPassengers"
                  type="number"
                  class="ntp-input"
                  placeholder="28"
                  min="1"
                />
              </div>
              <div class="form-field">
                <label class="ntp-label">LIAISON</label>
                <input
                  v-model="ntpLiaison"
                  class="ntp-input"
                  placeholder="e.g. T. Ngwenya"
                />
              </div>
            </div>

            <!-- Starting legs -->
            <div class="form-field">
              <label
                class="ntp-label"
                style="margin-bottom: 8px; display: block"
                >STARTING LEGS</label
              >
              <div class="ntp-legs">
                <label
                  class="ntp-leg-card"
                  :class="{ 'ntp-leg-card--selected': ntpLegs === 'blank' }"
                >
                  <input
                    type="radio"
                    v-model="ntpLegs"
                    value="blank"
                    class="ntp-radio"
                  />
                  <div>
                    <div class="ntp-leg-title">Blank</div>
                    <div class="ntp-leg-desc">Add legs manually</div>
                  </div>
                </label>
                <label
                  class="ntp-leg-card"
                  :class="{ 'ntp-leg-card--selected': ntpLegs === 'standard' }"
                >
                  <input
                    type="radio"
                    v-model="ntpLegs"
                    value="standard"
                    class="ntp-radio"
                  />
                  <div>
                    <div class="ntp-leg-title">
                      Standard arrival + departure
                    </div>
                    <div class="ntp-leg-desc">
                      Auto-adds flight in, transfer, flight out
                    </div>
                  </div>
                </label>
                <label
                  class="ntp-leg-card"
                  :class="{
                    'ntp-leg-card--selected': ntpLegs === 'full-match',
                  }"
                >
                  <input
                    type="radio"
                    v-model="ntpLegs"
                    value="full-match"
                    class="ntp-radio"
                  />
                  <div>
                    <div class="ntp-leg-title">Full match-day stay</div>
                    <div class="ntp-leg-desc">
                      Arrival + training + match + return + departure
                    </div>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <div class="modal-footer" style="justify-content: space-between">
            <div class="ntp-footer-hint">{{ ntpFooterHint }}</div>
            <div style="display: flex; gap: 8px">
              <Button
                variant="secondary"
                size="sm"
                @click="showNewTeamPlan = false"
                >Cancel</Button
              >
              <Button
                variant="primary"
                size="sm"
                @click="createTeamPlan"
                :disabled="!ntpTeam"
                >Create team plan</Button
              >
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Generation Success Modal -->
    <teleport to="body">
      <div
        v-if="showGenSuccess"
        class="modal-backdrop"
        @click.self="showGenSuccess = false"
      >
        <div class="modal gen-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">LOGISTICS PLANNING · GENERATE</div>
              <div class="modal-title" style="font-size: 18px">
                Generate Logistics Jobs
              </div>
              <div class="gen-subtitle">
                {{ selectedPlanObj?.code }} ·
                {{ selectedPlanObj?.date }} &mdash;
                {{ genProgressTotal }} movements ready ·
                {{ genAlreadyCount }} already generated
              </div>
            </div>
            <button class="modal-close" @click="showGenSuccess = false">
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <!-- Success body -->
          <div class="gen-success-body">
            <!-- Green banner -->
            <div class="gen-success-banner">
              <div class="gen-success-icon">
                <svg-icon name="check" :size="18" />
              </div>
              <div>
                <div class="gen-success-title">
                  {{ genProgressTotal }} logistics jobs generated
                </div>
                <div class="gen-success-desc">
                  Jobs are queued in execution mode. Supervisors will update
                  checkpoints via mobile.
                </div>
              </div>
            </div>

            <!-- Summary grid -->
            <div class="gen-success-grid">
              <div class="gen-success-col">
                <div class="gen-section-label" style="margin-bottom: 10px">
                  CREATED
                </div>
                <div
                  v-for="id in genCreatedIds"
                  :key="id"
                  class="gen-success-id"
                >
                  {{ id }}
                </div>
              </div>
              <div class="gen-success-col">
                <div class="gen-section-label" style="margin-bottom: 10px">
                  NOTIFICATIONS SENT
                </div>
                <template v-if="genNotifySnapshot">
                  <div class="gen-notif-line">✉ 3 liaisons via MS Teams</div>
                  <div class="gen-notif-line">✉ 5 supervisors via push</div>
                  <div class="gen-notif-line">✉ Fleet providers by email</div>
                </template>
                <div v-else class="gen-notif-line" style="color: var(--ink4)">
                  Notifications disabled
                </div>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <div class="gen-success-footer-hint">
              All jobs visible in the
              <a
                :href="`/jobs?plan=${selectedPlanObj?.id}`"
                style="
                  color: var(--accent);
                  font-weight: 600;
                  text-decoration: none;
                "
                >Jobs</a
              >
              queue.
            </div>
            <div style="display: flex; gap: 8px">
              <Button
                variant="secondary"
                size="sm"
                @click="showGenSuccess = false"
                >Close</Button
              >
              <Button
                variant="primary"
                size="sm"
                @click="$inertia.visit(`/jobs?plan=${selectedPlanObj?.id}`)"
                >Open Jobs queue →</Button
              >
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Add Leg Modal -->
    <teleport to="body">
      <div
        v-if="showAddLeg"
        class="modal-backdrop"
        @click.self="showAddLeg = false"
      >
        <div class="modal al-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">
                {{ selectedTeamObj?.team ?? selectedTeam }} · L{{
                  (selectedTeamObj?.items.length ?? 0) + 1
                }}
              </div>
              <div class="modal-title" style="font-size: 18px">Add leg</div>
            </div>
            <button class="modal-close" @click="showAddLeg = false">
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <div class="modal-body" style="gap: 14px">
            <!-- Leg type selector -->
            <div>
              <div
                style="
                  font-size: 10px;
                  font-weight: 700;
                  letter-spacing: 0.8px;
                  text-transform: uppercase;
                  color: var(--ink3);
                  margin-bottom: 8px;
                "
              >
                Leg Type
              </div>
              <div
                style="
                  display: grid;
                  grid-template-columns: repeat(5, 1fr);
                  gap: 6px;
                "
              >
                <button
                  v-for="t in alTypes"
                  :key="t.id"
                  class="al-type-btn"
                  :class="{ 'al-type-btn--active': alType === t.id }"
                  @click="alType = t.id"
                >
                  <span class="al-type-icon">
                    <svg
                      v-if="t.id === 'flight'"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <path
                        d="M21 16V14l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"
                      />
                    </svg>
                    <svg
                      v-else-if="t.id === 'transfer'"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <rect x="1" y="3" width="15" height="13" rx="2" />
                      <path d="M16 8h4l3 3v5h-7V8z" />
                      <circle cx="5.5" cy="18.5" r="2.5" />
                      <circle cx="18.5" cy="18.5" r="2.5" />
                    </svg>
                    <svg
                      v-else-if="t.id === 'training'"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <circle cx="12" cy="12" r="10" />
                      <path d="M12 8v4l3 3" />
                    </svg>
                    <svg
                      v-else-if="t.id === 'match'"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <circle cx="12" cy="12" r="10" />
                      <path
                        d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z"
                      />
                      <path d="M2 12h20" />
                    </svg>
                    <svg
                      v-else-if="t.id === 'return'"
                      width="16"
                      height="16"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="1.8"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    >
                      <polyline points="1 4 1 10 7 10" />
                      <path d="M3.51 15a9 9 0 102.13-9.36L1 10" />
                    </svg>
                  </span>
                  <span style="font-size: 12px; font-weight: 600">{{
                    t.label
                  }}</span>
                </button>
              </div>
              <div
                style="font-size: 11.5px; color: var(--ink3); margin-top: 8px"
              >
                {{ alTypeDesc }}
              </div>
            </div>

            <!-- Flight fields -->
            <template v-if="alType === 'flight'">
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>FLIGHT NUMBER</label>
                  <input
                    type="text"
                    v-model="alFlightNumber"
                    placeholder="e.g. AF812"
                  />
                </div>
                <div class="form-field">
                  <label>DATE</label>
                  <input type="date" v-model="alDate" />
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>ORIGIN AIRPORT</label>
                  <input
                    type="text"
                    v-model="alOrigin"
                    placeholder="e.g. MAD"
                  />
                </div>
                <div class="form-field">
                  <label>DESTINATION AIRPORT</label>
                  <input
                    type="text"
                    v-model="alDestination"
                    placeholder="CDG T2"
                  />
                </div>
              </div>
              <!-- Live carrier feed info box -->
              <div class="al-info-box al-info-box--accent">
                <div style="display: flex; align-items: flex-start; gap: 10px">
                  <svg
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    style="
                      flex-shrink: 0;
                      margin-top: 1px;
                      color: var(--accent);
                    "
                  >
                    <path
                      d="M21 16V14l-8-5V3.5a1.5 1.5 0 00-3 0V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"
                    />
                  </svg>
                  <div>
                    <div
                      style="
                        font-size: 12.5px;
                        font-weight: 700;
                        color: var(--accent);
                        margin-bottom: 3px;
                      "
                    >
                      Live carrier feed
                    </div>
                    <div
                      style="
                        font-size: 11.5px;
                        color: var(--ink3);
                        line-height: 1.5;
                      "
                    >
                      Times will auto-update from AF / SK / AR / EK / JL · No
                      LMS job is generated for flights.
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <!-- Non-flight fields -->
            <template v-else>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>FROM</label>
                  <input
                    type="text"
                    v-model="alFrom"
                    :placeholder="
                      alType === 'return' ? 'Stadium Azure' : 'Hotel Aurora'
                    "
                  />
                </div>
                <div class="form-field">
                  <label>TO</label>
                  <input
                    type="text"
                    v-model="alTo"
                    :placeholder="
                      alType === 'training'
                        ? 'Training A'
                        : alType === 'match'
                        ? 'Stadium Azure'
                        : 'Hotel Aurora'
                    "
                  />
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>DATE</label>
                  <input type="date" v-model="alDate" />
                </div>
                <div class="form-field">
                  <label>VEHICLE</label>
                  <select v-model="alVehicle">
                    <option value="">Auto-assign</option>
                    <option value="bus-01">Bus 01</option>
                    <option value="bus-02">Bus 02</option>
                    <option value="van-01">Van 01</option>
                  </select>
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>START</label>
                  <input type="time" v-model="alStart" />
                </div>
                <div class="form-field">
                  <label>END (ETA)</label>
                  <input type="time" v-model="alEnd" />
                </div>
              </div>
              <!-- What happens info box -->
              <div class="al-info-box">
                <div
                  style="
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 0.8px;
                    text-transform: uppercase;
                    color: var(--ink3);
                    margin-bottom: 8px;
                  "
                >
                  What happens when you add this
                </div>
                <div
                  style="
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    margin-bottom: 8px;
                    flex-wrap: wrap;
                  "
                >
                  <span class="al-flow-pill al-flow-pill--blue"
                    >Leg L{{ (selectedTeamObj?.items.length ?? 0) + 1 }}</span
                  >
                  <span style="font-size: 12px; color: var(--ink3)">→</span>
                  <span class="al-flow-pill al-flow-pill--purple"
                    >Movement M{{ props.schedule.length + 1 }}</span
                  >
                  <span style="font-size: 12px; color: var(--ink3)">→</span>
                  <span class="al-flow-pill al-flow-pill--gray"
                    >Job (on generate)</span
                  >
                  <template v-if="alType === 'training' || alType === 'match'">
                    <span class="al-flow-pill al-flow-pill--gray">+return</span>
                  </template>
                </div>
                <div
                  style="
                    font-size: 11.5px;
                    color: var(--ink3);
                    line-height: 1.5;
                  "
                >
                  Adds to
                  <b style="color: var(--ink)">{{
                    selectedTeamObj?.team ?? selectedTeam
                  }}</b
                  >'s itinerary and creates a new row in
                  <b style="color: var(--ink); font-family: var(--mono)">{{
                    selectedPlanObj?.code
                  }}</b
                  >. Vehicle &amp; driver assigned from the pool. Checkpoints
                  use the default library.
                </div>
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="modal-footer">
            <Button variant="secondary" size="sm" @click="showAddLeg = false"
              >Cancel</Button
            >
            <Button variant="primary" size="sm" @click="confirmAddLeg"
              >Add leg</Button
            >
          </div>
        </div>
      </div>
    </teleport>

    <!-- Add Movement Modal -->
    <teleport to="body">
      <div
        v-if="showAddMovement"
        class="modal-backdrop"
        @click.self="showAddMovement = false"
      >
        <div class="modal am-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">
                {{ selectedPlanObj?.code }} · {{ selectedPlanObj?.date }}
              </div>
              <div class="modal-title" style="font-size: 18px">
                Add movement
              </div>
            </div>
            <button class="modal-close" @click="showAddMovement = false">
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <!-- Mode tabs -->
          <div
            style="
              display: grid;
              grid-template-columns: 1fr 1fr;
              gap: 8px;
              padding: 14px 20px;
              border-bottom: 1px solid var(--border);
            "
          >
            <button
              class="am-mode-btn"
              :class="{ 'am-mode-btn--active': amMode === 'manual' }"
              @click="amMode = 'manual'"
            >
              <div style="font-size: 13px; font-weight: 700">Manual entry</div>
              <div
                style="
                  font-size: 11px;
                  color: inherit;
                  opacity: 0.7;
                  margin-top: 1px;
                "
              >
                Single ad-hoc movement
              </div>
            </button>
            <button
              class="am-mode-btn"
              :class="{ 'am-mode-btn--active': amMode === 'template' }"
              @click="amMode = 'template'"
            >
              <div style="font-size: 13px; font-weight: 700">From template</div>
              <div
                style="
                  font-size: 11px;
                  color: inherit;
                  opacity: 0.7;
                  margin-top: 1px;
                "
              >
                Expand to multiple movements
              </div>
            </button>
          </div>

          <!-- Form body -->
          <div class="modal-body" style="gap: 12px">
            <!-- Manual entry -->
            <template v-if="amMode === 'manual'">
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>PHASE</label>
                  <select v-model="amPhase">
                    <option value="arrival">Arrival</option>
                    <option value="departure">Departure</option>
                    <option value="transfer">Transfer</option>
                    <option value="training">Training</option>
                    <option value="match">Match</option>
                  </select>
                </div>
                <div class="form-field">
                  <label>TEAM</label>
                  <select v-model="amTeam">
                    <option value="">Select team...</option>
                    <option value="team-a">Team A</option>
                    <option value="team-b">Team B</option>
                    <option value="team-c">Team C</option>
                  </select>
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>FROM</label>
                  <input
                    type="text"
                    v-model="amFrom"
                    placeholder="e.g. Hotel Aurora"
                  />
                </div>
                <div class="form-field">
                  <label>TO</label>
                  <input
                    type="text"
                    v-model="amTo"
                    placeholder="e.g. Stadium Azure"
                  />
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>START</label>
                  <input type="time" v-model="amStart" />
                </div>
                <div class="form-field">
                  <label>END (ETA)</label>
                  <input type="time" v-model="amEnd" />
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>VEHICLE</label>
                  <select v-model="amVehicle">
                    <option value="">Auto-assign...</option>
                    <option value="bus-01">Bus 01</option>
                    <option value="bus-02">Bus 02</option>
                    <option value="van-01">Van 01</option>
                  </select>
                </div>
                <div class="form-field">
                  <label>PASSENGERS</label>
                  <input
                    type="number"
                    v-model="amPassengers"
                    placeholder="0"
                    min="1"
                  />
                </div>
              </div>

              <!-- Checkpoint sequence -->
              <div class="am-checkpoints">
                <div
                  style="
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 0.8px;
                    text-transform: uppercase;
                    color: var(--ink3);
                    margin-bottom: 8px;
                  "
                >
                  Checkpoint Sequence
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 6px">
                  <span
                    v-for="(cp, i) in amCheckpoints"
                    :key="i"
                    class="am-cp-pill"
                  >
                    {{ i + 1 }}. {{ cp }}
                  </span>
                </div>
                <div
                  style="font-size: 11px; color: var(--ink3); margin-top: 8px"
                >
                  Using default checkpoint library ·
                  <a
                    href="#"
                    @click.prevent
                    style="
                      color: var(--accent);
                      text-decoration: none;
                      font-weight: 500;
                    "
                    >customize</a
                  >
                </div>
              </div>
            </template>

            <!-- From template -->
            <template v-else>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>TEMPLATE</label>
                  <select v-model="amTemplate">
                    <option
                      v-for="t in movementTemplateLibrary"
                      :key="t.id"
                      :value="t.id"
                    >
                      {{ t.name }}
                    </option>
                  </select>
                </div>
                <div class="form-field">
                  <label>TEAM</label>
                  <select v-model="amTeam">
                    <option value="">Select team...</option>
                    <option value="fc-meridian">FC Meridian</option>
                    <option value="team-a">Team A</option>
                    <option value="team-b">Team B</option>
                    <option value="team-c">Team C</option>
                  </select>
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label>DATE</label>
                  <input type="date" v-model="amDate" />
                </div>
                <div class="form-field">
                  <label>BASE TIME (FIRST LEG)</label>
                  <input type="time" v-model="amBaseTime" />
                </div>
              </div>

              <!-- Template preview card -->
              <div
                style="
                  border: 1px solid var(--border);
                  border-radius: 10px;
                  overflow: hidden;
                "
              >
                <div
                  style="
                    padding: 12px 14px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    border-bottom: 1px solid var(--border);
                    background: var(--surface);
                  "
                >
                  <div>
                    <div
                      style="
                        font-size: 13px;
                        font-weight: 700;
                        color: var(--ink);
                      "
                    >
                      {{ amTemplateObj.name }}
                    </div>
                    <div
                      style="
                        font-size: 11px;
                        color: var(--ink3);
                        margin-top: 2px;
                      "
                    >
                      {{ amTemplateObj.legs }} legs · avg
                      {{ amTemplateObj.avg }}
                    </div>
                  </div>
                  <span
                    style="
                      font-family: var(--mono);
                      font-size: 11px;
                      font-weight: 700;
                      color: var(--accent);
                      background: var(--accent-soft);
                      padding: 3px 8px;
                      border-radius: 6px;
                    "
                    >{{ amTemplateObj.id }}</span
                  >
                </div>

                <div
                  style="
                    padding: 8px 14px;
                    font-size: 10px;
                    font-weight: 700;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    color: var(--ink3);
                    border-bottom: 1px solid var(--border);
                    background: var(--panel);
                  "
                >
                  WILL GENERATE {{ amTemplateLegs.length }} MOVEMENTS
                </div>

                <div
                  v-for="(leg, i) in amTemplateLegs"
                  :key="i"
                  :style="{
                    display: 'grid',
                    gridTemplateColumns: '40px 90px 1fr auto',
                    gap: '10px',
                    padding: '10px 14px',
                    alignItems: 'center',
                    borderBottom:
                      i < amTemplateLegs.length - 1
                        ? '1px solid var(--border)'
                        : 'none',
                    background: 'var(--surface)',
                  }"
                >
                  <div
                    style="
                      font-family: var(--mono);
                      font-size: 11px;
                      font-weight: 700;
                      color: var(--ink3);
                    "
                  >
                    M{{ leg.idx }}
                  </div>
                  <span
                    style="
                      padding: 2px 8px;
                      border-radius: 4px;
                      font-size: 10px;
                      font-weight: 600;
                      white-space: nowrap;
                      background: #eef2ff;
                      color: #4f46e5;
                    "
                    >{{ leg.phase }}</span
                  >
                  <div style="font-size: 12px; color: var(--ink)">
                    {{ leg.from
                    }}<template v-if="leg.to"> → {{ leg.to }}</template>
                  </div>
                  <div
                    style="
                      font-family: var(--mono);
                      font-size: 11px;
                      color: var(--ink2);
                      white-space: nowrap;
                    "
                  >
                    {{ leg.dep }} → {{ leg.arr }}
                  </div>
                </div>
              </div>

              <!-- Heads-up notice -->
              <div
                style="
                  background: #fffbeb;
                  border: 1px solid #fde68a;
                  border-radius: 8px;
                  padding: 10px 14px;
                  font-size: 12px;
                  color: #92400e;
                "
              >
                <span style="font-weight: 700">Heads-up</span> · Vehicles &amp;
                drivers will be auto-assigned from the available pool. Conflicts
                will surface in the Conflicts tab.
              </div>
            </template>
          </div>

          <!-- Footer -->
          <div class="modal-footer" style="justify-content: space-between">
            <div
              style="font-size: 12px; color: var(--accent); font-weight: 500"
            >
              Adds
              {{ amMode === "template" ? amTemplateLegs.length : 1 }} movement{{
                amMode === "template" && amTemplateLegs.length !== 1 ? "s" : ""
              }}
              · <span style="color: var(--ink3)">Job not yet generated</span>
            </div>
            <div style="display: flex; gap: 8px">
              <Button
                variant="secondary"
                size="sm"
                @click="showAddMovement = false"
                >Cancel</Button
              >
              <Button variant="secondary" size="sm" @click="saveMovement(true)"
                >Save &amp; add another</Button
              >
              <Button variant="primary" size="sm" @click="saveMovement(false)"
                >Add to plan</Button
              >
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Edit Movement Modal -->
    <teleport to="body">
      <div
        v-if="showEditMovement"
        class="modal-backdrop"
        @click.self="showEditMovement = false"
      >
        <div class="modal am-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">
                {{ editingMovement?.code || "Movement" }}
              </div>
              <div class="modal-title" style="font-size: 18px">
                Edit movement
              </div>
            </div>
            <button class="modal-close" @click="showEditMovement = false">
              <svg-icon name="x" :size="16" />
            </button>
          </div>

          <!-- Form body -->
          <div class="modal-body" style="gap: 12px">
            <!-- Read-only Flight/Team Info Card -->
            <div
              v-if="(editingMovement?.flight || editingMovement?.team) && emKind === 'arrival'"
              style="
                padding: 14px;
                background: var(--panel);
                border: 1.5px solid var(--border);
                border-radius: 10px;
              "
            >
              <div
                style="
                  font-size: 10px;
                  font-weight: 700;
                  letter-spacing: 0.8px;
                  text-transform: uppercase;
                  color: var(--ink3);
                  margin-bottom: 10px;
                "
              >
                📋 MOVEMENT DETAILS
              </div>

              <div style="display: grid; gap: 12px">
                <!-- Flight Info (if exists) -->
                <div
                  v-if="editingMovement?.flight"
                  style="
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                  "
                >
                  <div>
                    <div
                      style="
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: var(--ink3);
                        margin-bottom: 3px;
                      "
                    >
                      Flight
                    </div>
                    <div
                      style="
                        font-size: 13px;
                        font-weight: 600;
                        color: var(--ink);
                      "
                    >
                      {{ editingMovement.flight.flight_number || "—" }}
                    </div>
                  </div>
                  <div>
                    <div
                      style="
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: var(--ink3);
                        margin-bottom: 3px;
                      "
                    >
                      Route
                    </div>
                    <div style="font-size: 13px; color: var(--ink)">
                      {{ editingMovement.flight.origin_airport?.iata || "—" }}
                      →
                      {{
                        editingMovement.flight.destination_airport?.iata || "—"
                      }}
                    </div>
                  </div>
                  <div>
                    <div
                      style="
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: var(--ink3);
                        margin-bottom: 3px;
                      "
                    >
                      Passengers
                    </div>
                    <div style="font-size: 13px; color: var(--ink)">
                      {{ editingMovement.flight.party_size_total || 0 }} pax
                    </div>
                  </div>
                  <div>
                    <div
                      style="
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: var(--ink3);
                        margin-bottom: 3px;
                      "
                    >
                      Scheduled
                    </div>
                    <div style="font-size: 13px; color: var(--ink)">
                      {{
                        editingMovement.flight.scheduled_at
                          ? formatDateTime(editingMovement.flight.scheduled_at)
                          : "—"
                      }}
                    </div>
                  </div>
                </div>

                <!-- Team Info -->
                <div v-if="editingMovement?.team">
                  <div
                    style="
                      font-size: 10px;
                      font-weight: 600;
                      text-transform: uppercase;
                      color: var(--ink3);
                      margin-bottom: 3px;
                    "
                  >
                    Team
                  </div>
                  <div
                    style="font-size: 13px; font-weight: 600; color: var(--ink)"
                  >
                    {{
                      editingMovement.team.team_name ||
                      editingMovement.team.team
                    }}
                    ({{ editingMovement.team.code }})
                  </div>
                </div>

                <!-- Phase and Route -->
                <div
                  style="
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                  "
                >
                  <div>
                    <div
                      style="
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: var(--ink3);
                        margin-bottom: 3px;
                      "
                    >
                      Phase
                    </div>
                    <span
                      v-if="emMatchId"
                      class="kind-badge kind-badge--match"
                      style="background: #fef3c7; color: #92400e; border-color: #fbbf24;"
                      >Match {{ relevantMatches.find(m => m.id === emMatchId)?.match_number || '' }}</span
                    >
                    <span
                      v-else-if="emKind"
                      :class="['kind-badge', `kind-badge--${emKind}`]"
                      >{{ emKind }}</span
                    >
                    <span v-else style="font-size: 13px; color: var(--ink3)"
                      >—</span
                    >
                  </div>
                  <div>
                    <div
                      style="
                        font-size: 10px;
                        font-weight: 600;
                        text-transform: uppercase;
                        color: var(--ink3);
                        margin-bottom: 3px;
                      "
                    >
                      Route
                    </div>
                    <div style="font-size: 13px; color: var(--ink)">
                      {{ emFrom || "—" }} → {{ emTo || "—" }}
                    </div>
                  </div>
                </div>
              </div>

              <div
                style="
                  margin-top: 8px;
                  padding: 8px;
                  background: var(--surface);
                  border-radius: 6px;
                  font-size: 11px;
                  color: var(--ink3);
                  display: flex;
                  align-items: center;
                  gap: 6px;
                "
              >
                <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
                Flight and team details are read-only. Adjust operational fields
                below.
              </div>
            </div>

            <!-- Divider -->
            <div
              v-if="(editingMovement?.flight || editingMovement?.team) && emKind === 'arrival'"
              style="height: 1px; background: var(--border); margin: 4px 0"
            ></div>

            <!-- Operational Fields Header -->
            <div
              style="
                font-size: 10px;
                font-weight: 700;
                letter-spacing: 0.8px;
                text-transform: uppercase;
                color: var(--ink3);
                margin-bottom: -4px;
              "
            >
              ⚙️ OPERATIONAL ADJUSTMENTS
            </div>

            <!-- Editable Operational Fields -->
            <div
              style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
            >
              <div class="form-field">
                <label>WINDOW START</label>
                <input type="datetime-local" v-model="emWindowStart" />
              </div>
              <div class="form-field">
                <label>WINDOW END</label>
                <input type="datetime-local" v-model="emWindowEnd" />
              </div>
            </div>
            <div
              style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
            >
              <div class="form-field">
                <label>VEHICLE</label>
                <select v-model="emVehicleId">
                  <option value="">Select vehicle...</option>
                  <option
                    v-for="vehicle in props.vehicles"
                    :key="vehicle.id"
                    :value="vehicle.id"
                  >
                    {{ vehicle.code }} - {{ vehicle.vehicle_type }}
                  </option>
                </select>
              </div>
              <div class="form-field">
                <label>DRIVER</label>
                <select v-model="emDriverId">
                  <option value="">Select driver...</option>
                  <option
                    v-for="driver in props.drivers"
                    :key="driver.id"
                    :value="driver.id"
                  >
                    {{ driver.name }}
                  </option>
                </select>
              </div>
            </div>
            <div class="form-field">
              <label>FIELD SUPERVISOR</label>
              <select v-model="emFieldSupervisorId">
                <option value="">Select supervisor...</option>
                <option
                  v-for="supervisor in props.supervisors"
                  :key="supervisor.id"
                  :value="supervisor.id"
                >
                  {{ supervisor.name }}
                </option>
              </select>
            </div>
            <div class="form-field">
              <label>MATCH (OPTIONAL)</label>
              <select v-model="emMatchId">
                <option :value="null">None - Regular logistics</option>
                <option 
                  v-for="match in relevantMatches" 
                  :key="match.id" 
                  :value="match.id"
                >
                  {{ match.match_number }} - 
                  {{ match.team1?.team_name || match.team1?.team }} vs {{ match.team2?.team_name || match.team2?.team }} - 
                  {{ formatDateTime(match.kick_off) }} @ {{ match.venue?.name }}
                </option>
              </select>
              <div
                v-if="emMatchId && relevantMatches.length > 0"
                style="
                  margin-top: 6px;
                  padding: 8px;
                  background: var(--accent-soft);
                  border-radius: 6px;
                  font-size: 11px;
                  color: var(--ink2);
                  display: flex;
                  align-items: center;
                  gap: 6px;
                "
              >
                <svg
                  width="14"
                  height="14"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="2"
                >
                  <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                </svg>
                This movement is linked to a match. Ideal window should be 2-3h before kick-off.
              </div>
            </div>
            <div class="form-field">
              <label>NOTES</label>
              <textarea
                v-model="emNotes"
                placeholder="Additional notes..."
                rows="3"
                style="
                  width: 100%;
                  padding: 8px 12px;
                  border: 1px solid var(--border);
                  border-radius: 8px;
                  font-size: 13px;
                  font-family: inherit;
                  resize: vertical;
                "
              ></textarea>
            </div>
          </div>

          <!-- Footer -->
          <div class="modal-footer" style="justify-content: flex-end">
            <div style="display: flex; gap: 8px">
              <Button
                variant="secondary"
                size="sm"
                @click="showEditMovement = false"
                :disabled="editMovementProcessing"
                >Cancel</Button
              >
              <Button
                variant="primary"
                size="sm"
                @click="submitEditMovement"
                :disabled="editMovementProcessing"
              >
                {{ editMovementProcessing ? "Updating..." : "Update movement" }}
              </Button>
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Delete Confirmation Modal -->
    <teleport to="body">
      <div
        v-if="showDeleteConfirmation && deletingPlan"
        class="modal-backdrop"
        @click.self="cancelDeletePlan"
      >
        <div class="modal">
          <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                style="color: #dc2626"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
              <span class="modal-title">Delete Plan</span>
            </div>
            <button class="modal-close" @click="cancelDeletePlan">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px">
              Are you sure you want to delete
              <strong>{{ deletingPlan.name }}</strong
              >?
            </p>
            <p style="font-size: 13px; color: var(--ink2); margin-bottom: 8px">
              This will also delete:
            </p>
            <ul
              style="
                font-size: 13px;
                color: var(--ink2);
                margin-left: 20px;
                margin-bottom: 12px;
              "
            >
              <li>{{ deletingPlan.movements_count || 0 }} movement(s)</li>
              <li>All associated jobs and checkpoints</li>
            </ul>
            <p style="font-size: 13px; color: #dc2626; font-weight: 600">
              This action cannot be undone.
            </p>
          </div>
          <div class="modal-footer">
            <Button
              variant="secondary"
              size="sm"
              @click="cancelDeletePlan"
              :disabled="deletePlanProcessing"
              >Cancel</Button
            >
            <Button
              variant="primary"
              size="sm"
              @click="confirmDeletePlan"
              :disabled="deletePlanProcessing"
              style="background: #dc2626; border-color: #dc2626"
            >
              {{ deletePlanProcessing ? "Deleting..." : "Delete Plan" }}
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Delete Movement Confirmation Modal -->
    <teleport to="body">
      <div
        v-if="showDeleteMovementConfirmation && deletingMovement"
        class="modal-backdrop"
        @click.self="cancelDeleteMovement"
      >
        <div class="modal">
          <div class="modal-header">
            <div style="display: flex; align-items: center; gap: 8px">
              <svg
                width="24"
                height="24"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                style="color: #dc2626"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
              <span class="modal-title">Delete Movement</span>
            </div>
            <button class="modal-close" @click="cancelDeleteMovement">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px">
              Are you sure you want to delete movement
              <strong>{{
                deletingMovement.code || `M${deletingMovement.id}`
              }}</strong
              >?
            </p>
            <p style="font-size: 13px; color: var(--ink2); margin-bottom: 8px">
              Movement details:
            </p>
            <ul
              style="
                font-size: 13px;
                color: var(--ink2);
                margin-left: 20px;
                margin-bottom: 12px;
              "
            >
              <li>
                Team: {{ deletingMovement.team?.team_name || "Not assigned" }}
              </li>
              <li>
                Route: {{ deletingMovement.from_location || "-" }} →
                {{ deletingMovement.to_location || "-" }}
              </li>
              <li v-if="deletingMovement.job_id">
                Associated Job: {{ deletingMovement.job_id }}
              </li>
            </ul>
            <p style="font-size: 13px; color: #dc2626; font-weight: 600">
              This action cannot be undone.
            </p>
          </div>
          <div class="modal-footer">
            <Button
              variant="secondary"
              size="sm"
              @click="cancelDeleteMovement"
              :disabled="deleteMovementProcessing"
              >Cancel</Button
            >
            <Button
              variant="primary"
              size="sm"
              @click="confirmDeleteMovement"
              :disabled="deleteMovementProcessing"
              style="background: #dc2626; border-color: #dc2626"
            >
              {{ deleteMovementProcessing ? "Deleting..." : "Delete Movement" }}
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Template Preview modal -->
    <teleport to="body">
      <div
        v-if="showPreviewModal && selectedTemplate"
        class="modal-backdrop"
        @click.self="showPreviewModal = false"
      >
        <div class="modal" style="max-width: 720px">
          <div class="modal-header">
            <div>
              <div
                style="
                  display: flex;
                  align-items: center;
                  gap: 8px;
                  margin-bottom: 4px;
                "
              >
                <span class="modal-title">{{ selectedTemplate.name }}</span>
                <status-pill tone="neutral" size="sm"
                  >{{ selectedTemplate.legs }} legs</status-pill
                >
              </div>
              <div
                style="
                  font-size: 11px;
                  color: var(--ink3);
                  font-family: var(--mono);
                "
              >
                {{ selectedTemplate.id }}
              </div>
            </div>
            <button class="modal-close" @click="showPreviewModal = false">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body" style="padding: 0">
            <!-- Template info -->
            <div
              style="
                padding: 16px 20px;
                border-bottom: 1px solid var(--border);
                background: var(--panel);
              "
            >
              <div
                style="
                  font-size: 12px;
                  color: var(--ink2);
                  line-height: 1.6;
                  margin-bottom: 8px;
                "
              >
                {{ selectedTemplate.description }}
              </div>
              <div style="display: flex; gap: 16px; font-size: 12px">
                <div>
                  <span style="color: var(--ink3)">Avg duration:</span>
                  <b
                    style="
                      color: var(--ink);
                      font-family: var(--mono);
                      margin-left: 4px;
                    "
                    >{{ selectedTemplate.avg }}</b
                  >
                </div>
                <div>
                  <span style="color: var(--ink3)">Total legs:</span>
                  <b
                    style="
                      color: var(--ink);
                      font-family: var(--mono);
                      margin-left: 4px;
                    "
                    >{{ selectedTemplate.legs }}</b
                  >
                </div>
              </div>
            </div>

            <!-- Movement sequence -->
            <div style="padding: 16px 20px">
              <div
                style="
                  font-size: 11px;
                  letter-spacing: 1px;
                  text-transform: uppercase;
                  color: var(--ink3);
                  font-weight: 700;
                  margin-bottom: 12px;
                "
              >
                Movement Sequence
              </div>
              <div style="display: flex; flex-direction: column; gap: 8px">
                <div
                  v-for="mv in selectedTemplate.movements"
                  :key="mv.order"
                  style="
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                    padding: 12px;
                    background: var(--panel);
                    border: 1px solid var(--border);
                    border-radius: 8px;
                  "
                >
                  <div
                    style="
                      width: 32px;
                      height: 32px;
                      border-radius: 999px;
                      background: var(--accent);
                      color: #fff;
                      display: flex;
                      align-items: center;
                      justify-content: center;
                      font-weight: 700;
                      font-size: 13px;
                      flex-shrink: 0;
                    "
                  >
                    {{ mv.order }}
                  </div>
                  <div style="flex: 1">
                    <div
                      style="
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        margin-bottom: 4px;
                      "
                    >
                      <span :class="['kind-badge', `kind-badge--${mv.type}`]">{{
                        mv.type
                      }}</span>
                      <span
                        style="
                          font-size: 13px;
                          font-weight: 600;
                          color: var(--ink);
                        "
                        >{{ mv.from }} → {{ mv.to }}</span
                      >
                    </div>
                    <div
                      style="
                        display: flex;
                        gap: 12px;
                        font-size: 11px;
                        color: var(--ink3);
                      "
                    >
                      <div>
                        <span style="color: var(--ink4)">Duration:</span>
                        <b
                          style="color: var(--ink2); font-family: var(--mono)"
                          >{{ mv.duration }}</b
                        >
                      </div>
                      <div>
                        <span style="color: var(--ink4)">Vehicle:</span>
                        <b style="color: var(--ink2)">{{ mv.vehicle }}</b>
                      </div>
                      <div>
                        <span style="color: var(--ink4)">Pax:</span>
                        <b
                          style="color: var(--ink2); font-family: var(--mono)"
                          >{{ mv.pax }}</b
                        >
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <Button variant="ghost" size="sm" @click="showPreviewModal = false"
              >Close</Button
            >
            <Button
              variant="primary"
              size="sm"
              @click="applyTemplate(selectedTemplate)"
              >Apply Template</Button
            >
          </div>
        </div>
      </div>

      <!-- Add Team to Plan Modal -->
      <div
        v-if="showAddTeamModal"
        class="modal-backdrop"
        @click.self="showAddTeamModal = false"
      >
        <div class="modal" style="max-width: 500px">
          <div class="modal-header">
            <span class="modal-title">Add Team to Plan</span>
            <button class="modal-close" @click="showAddTeamModal = false">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <div
              v-if="selectedPlanObj"
              style="
                padding: 10px 12px;
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 8px;
                margin-bottom: 16px;
              "
            >
              <div
                style="
                  font-size: 11px;
                  color: var(--ink3);
                  margin-bottom: 4px;
                  text-transform: uppercase;
                  letter-spacing: 0.5px;
                  font-weight: 600;
                "
              >
                Current Plan
              </div>
              <div style="font-size: 13px; font-weight: 700; color: var(--ink)">
                {{ selectedPlanObj.name }}
              </div>
              <div style="font-size: 11px; color: var(--ink3); margin-top: 2px">
                {{ formatDateTime(selectedPlanObj.date) }}
              </div>
            </div>

            <div
              v-if="teamsInCurrentPlan.length > 0"
              style="margin-bottom: 16px"
            >
              <div
                style="
                  font-size: 11px;
                  color: var(--ink3);
                  margin-bottom: 8px;
                  text-transform: uppercase;
                  letter-spacing: 0.5px;
                  font-weight: 600;
                "
              >
                Teams already in this plan:
              </div>
              <div style="display: flex; flex-wrap: wrap; gap: 6px">
                <span
                  v-for="team in teamsInCurrentPlan"
                  :key="team.id"
                  class="team-badge-sm"
                  >{{ team.code || team.team_name }}</span
                >
              </div>
            </div>

            <div
              v-if="!activeEvent"
              style="
                margin-bottom: 16px;
                padding: 12px;
                background: #fef3c7;
                border: 1px solid #fcd34d;
                border-radius: 6px;
                font-size: 12px;
                color: #92400e;
              "
            >
              ⚠️ No active event selected. Please select an event from the
              dropdown above to see teams.
            </div>

            <div
              v-else
              style="
                margin-bottom: 12px;
                padding: 10px;
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 6px;
                font-size: 12px;
              "
            >
              <div
                style="
                  color: var(--ink3);
                  margin-bottom: 4px;
                  font-size: 10px;
                  text-transform: uppercase;
                  letter-spacing: 0.5px;
                  font-weight: 600;
                "
              >
                Teams from Event:
              </div>
              <div style="color: var(--ink); font-weight: 500">
                {{ activeEvent.name }}
              </div>
            </div>

            <div class="form-field">
              <label>Select Team <span style="color: #dc2626">*</span></label>
              <select
                v-model="addTeamId"
                :style="addTeamErrors.team_id ? { borderColor: '#DC2626' } : {}"
                @change="addTeamErrors.team_id = ''"
              >
                <option :value="null">Choose a team...</option>
                <option
                  v-for="team in availableTeamsToAdd"
                  :key="team.id"
                  :value="team.id"
                >
                  {{ team.team_name || team.team }} ({{ team.code }})
                </option>
              </select>
              <span
                v-if="addTeamErrors.team_id"
                style="
                  color: #dc2626;
                  font-size: 12px;
                  margin-top: 4px;
                  display: block;
                "
                >{{ addTeamErrors.team_id }}</span
              >
              <span
                v-if="availableTeamsToAdd.length === 0"
                style="
                  color: var(--ink3);
                  font-size: 12px;
                  margin-top: 4px;
                  display: block;
                "
                >All teams have been added to this plan</span
              >
            </div>

            <div class="form-field">
              <label>Based on template</label>
              <select v-model="addTeamTemplate">
                <option value="">Blank (add movements manually)</option>
                <option
                  v-for="template in props.movementTemplates"
                  :key="template.id"
                  :value="template.id"
                >
                  {{ template.name }} ({{ template.code }})
                </option>
              </select>
              <span
                style="
                  color: var(--ink3);
                  font-size: 11px;
                  margin-top: 4px;
                  display: block;
                "
              >
                {{
                  addTeamTemplate
                    ? "Template movements will be created for this team"
                    : "No movements will be created initially"
                }}
              </span>
            </div>
          </div>
          <div class="modal-footer">
            <Button
              variant="ghost"
              size="sm"
              @click="showAddTeamModal = false"
              :disabled="addTeamProcessing"
              >Cancel</Button
            >
            <Button
              variant="primary"
              size="sm"
              @click="addTeamToPlan"
              :disabled="addTeamProcessing || availableTeamsToAdd.length === 0"
            >
              <span v-if="addTeamProcessing">Adding...</span>
              <span v-else>Add Team</span>
            </Button>
          </div>
        </div>
      </div>
    </teleport>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "../Components/AppLayout.vue";
import StatusPill from "../Components/StatusPill.vue";
import SvgIcon from "../Components/SvgIcon.vue";
import MiniStat from "../Components/MiniStat.vue";
import Button from "../Components/Button.vue";
import RefreshButton from "../Components/RefreshButton.vue";
import TableActions from "../Components/TableActions.vue";
import CheckpointTimeline from "../Components/CheckpointTimeline.vue";

const props = defineProps({
  schedule: { type: Array, default: () => [] },
  activeEvent: { type: Object, default: null },
  activePlan: { type: [Number, String], default: null },
  plans: { type: Array, default: () => [] },
  movementTemplates: { type: Array, default: () => [] },
  teams: { type: Array, default: () => [] },
  movementsByTeam: { type: Array, default: () => [] },
  vehicles: { type: Array, default: () => [] },
  drivers: { type: Array, default: () => [] },
  supervisors: { type: Array, default: () => [] },
  matches: { type: Array, default: () => [] },
});

const view = ref("day");
const showNewPlan = ref(false);
const newPlanMode = ref("single"); // 'single' or 'bulk'
const newPlanDate = ref("");
const newPlanStartTime = ref("09:00");
const newPlanName = ref("");
const newPlanTeamId = ref(null);
const newPlanFlightId = ref(null);
const newPlanAccommodationId = ref(null);
const newPlanTemplate = ref("");
const newPlanProcessing = ref(false);
const newPlanErrors = ref({});
const expandedTeams = ref(new Set()); // Track which teams have flight selector expanded
const teamFlightSelections = ref({}); // Track selected flight per team

// Edit plan state
const showEditPlan = ref(false);
const editingPlan = ref(null);
const editPlanName = ref("");
const editPlanDate = ref("");
const editPlanStartTime = ref("09:00");
const editPlanTemplate = ref("");
const editPlanStatus = ref("draft");
const editPlanProcessing = ref(false);
const editPlanErrors = ref({});

// Delete plan state
const showDeleteConfirmation = ref(false);
const deletingPlan = ref(null);
const deletePlanProcessing = ref(false);

// Edit movement state
const showEditMovement = ref(false);
const editingMovement = ref(null);
const editMovementProcessing = ref(false);

// Delete movement state
const showDeleteMovementConfirmation = ref(false);
const deletingMovement = ref(null);
const deleteMovementProcessing = ref(false);

// Selected movement for detail panel
const selectedMovement = ref(null);

// Mock plans data (used as fallback if no database plans)
const plans = [
  {
    id: "p1",
    code: "PLN-2026-0418",
    name: "Match Day 4 – Active",
    date: "2026-04-18",
    status: "active",
    movements_count: 6,
    teams_count: 5,
  },
  {
    id: "p2",
    code: "PLN-2026-0419",
    name: "Match Day 5 – Draft",
    date: "2026-04-19",
    status: "draft",
    movements_count: 3,
    teams_count: 5,
  },
  {
    id: "p3",
    code: "PLN-2026-0417",
    name: "Training 19 Apr",
    date: "2026-04-17",
    status: "completed",
    movements_count: 8,
    teams_count: 4,
  },
  {
    id: "p4",
    code: "PLN-2026-0420",
    name: "Match Day 6 – Upcoming",
    date: "2026-04-20",
    status: "upcoming",
    movements_count: 0,
    teams_count: 0,
  },
];

// Use props.plans if available, otherwise fallback to mock plans
const allPlans = computed(() => (props.plans?.length ? props.plans : plans));

// Initialize activePlan from backend prop (session-based, shared with Jobs page)
const activePlan = ref(props.activePlan);

// Handle team selection change to update date and start time from team's arrival
function handleTeamChange() {
  newPlanErrors.value.team_id = "";

  // Reset flight and accommodation selection when team changes
  newPlanFlightId.value = null;
  newPlanAccommodationId.value = null;

  if (newPlanTeamId.value && props.teams) {
    const selectedTeam = props.teams.find((t) => t.id === newPlanTeamId.value);

    if (selectedTeam) {
      // Auto-select flight if only one exists
      if (selectedTeam.flights && selectedTeam.flights.length === 1) {
        newPlanFlightId.value = selectedTeam.flights[0].id;
      }

      // Auto-select accommodation if team has one
      if (selectedTeam.stay) {
        newPlanAccommodationId.value = selectedTeam.stay.id;
      }

      if (selectedTeam.arrival_date_time) {
        const dt = selectedTeam.arrival_date_time;
        const dateMatch = dt.match(/(\d{4}-\d{2}-\d{2})/);
        if (dateMatch) newPlanDate.value = dateMatch[1];
        const timeMatch = dt.match(/[T ](\d{2}):(\d{2})/);
        if (timeMatch)
          newPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
      }
    }
  }
}

// Watch for changes to activePlan and persist to backend session
watch(activePlan, (newValue) => {
  if (newValue) {
    // Send to backend session (shared with Jobs page)
    fetch("/session/active-plan", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-TOKEN":
          document.querySelector('meta[name="csrf-token"]')?.content || "",
      },
      body: JSON.stringify({ plan_id: newValue }),
    }).catch((error) => {
      console.error("Failed to update active plan in session:", error);
    });
  }
});

watch(newPlanTeamId, (teamId) => {
  if (teamId && props.teams) {
    const selectedTeam = props.teams.find((t) => t.id === teamId);
    if (selectedTeam && selectedTeam.arrival_date_time) {
      const dt = selectedTeam.arrival_date_time;
      const dateMatch = dt.match(/(\d{4}-\d{2}-\d{2})/);
      if (dateMatch) newPlanDate.value = dateMatch[1];
      const timeMatch = dt.match(/[T ](\d{2}):(\d{2})/);
      if (timeMatch) newPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
    }
  }
});

// Reset flight selections when modal is closed
watch(showNewPlan, (isOpen) => {
  if (!isOpen) {
    expandedTeams.value.clear();
    teamFlightSelections.value = {};
  }
});

const showPlanDropdown = ref(false);
const planSearchTerm = ref("");
const selectedTeam = ref("");

// Team filter for movements table in By Day view
const movementsTeamFilter = ref(null);
const teamMovementKindFilter = ref(null); // null = all, or 'arrival', 'departure', 'transfer', 'match', etc.

// Add Team to Plan modal state
const showAddTeamModal = ref(false);
const addTeamId = ref(null);
const addTeamTemplate = ref("");
const addTeamProcessing = ref(false);
const addTeamErrors = ref({});

// Initialize activeTab based on whether there's a valid stored plan
const activeTab = ref(props.activePlan ? "movements" : "plans");

const showPreviewModal = ref(false);
const selectedTemplate = ref(null);
const showGenerateJobs = ref(false);
const genSelectedIds = ref([]);
const genTemplate = ref("TPL-ARR");
const genAutoAssign = ref(true);
const genNotifyLiaisons = ref(true);
const genProcessing = ref(false);
const generatingJobForMovement = ref(null);

const showGenProgress = ref(false);
const genProgressCurrent = ref(0);
const genProgressTotal = ref(0);
const genLog = ref([]);
const genDone = ref(false);

const showGenSuccess = ref(false);
const genCreatedIds = ref([]);
const genNotifySnapshot = ref(true);

const showAddMovement = ref(false);
const amMode = ref("manual");
const amPhase = ref("arrival");
const amTeam = ref("");
const amFrom = ref("");
const amTo = ref("");
const amStart = ref("15:00");
const amEnd = ref("15:45");
const amVehicle = ref("");
const amPassengers = ref("");
const amTemplate = ref("TPL-MATCH");
const amDate = ref("");
const amBaseTime = ref("14:00");

// Edit Movement form state
const emTeamId = ref("");
const emKind = ref("");
const emFrom = ref("");
const emTo = ref("");
const emWindowStart = ref("");
const emWindowEnd = ref("");
const emVehicleId = ref("");
const emDriverId = ref("");
const emFieldSupervisorId = ref("");
const emPassengers = ref("");
const emFlightNumber = ref("");
const emNotes = ref("");
const emMatchId = ref(null);

const movementTemplateLibrary = [
  {
    id: "TPL-MATCH",
    name: "Match-day Round-trip",
    legs: 5,
    avg: "5h 30m",
    legs_def: [
      {
        phase: "Daily ops",
        from: "Hotel",
        to: "Training",
        offsetMin: 0,
        durMin: 30,
      },
      {
        phase: "Daily ops",
        from: "Training",
        to: "Hotel",
        offsetMin: 150,
        durMin: 30,
      },
      {
        phase: "Daily ops",
        from: "Hotel",
        to: "Stadium",
        offsetMin: 360,
        durMin: 45,
      },
      {
        phase: "Daily ops",
        from: "Stadium",
        to: "Hotel",
        offsetMin: 600,
        durMin: 35,
      },
      {
        phase: "Daily ops",
        from: "Post-match press",
        to: "",
        offsetMin: 640,
        durMin: 30,
      },
    ],
  },
  {
    id: "TPL-ARR",
    name: "Standard Arrival",
    legs: 3,
    avg: "2h 10m",
    legs_def: [
      {
        phase: "Arrival",
        from: "Airport",
        to: "Hotel",
        offsetMin: 0,
        durMin: 45,
      },
      {
        phase: "Arrival",
        from: "Hotel",
        to: "Training",
        offsetMin: 60,
        durMin: 40,
      },
      {
        phase: "Arrival",
        from: "Training",
        to: "Hotel",
        offsetMin: 180,
        durMin: 30,
      },
    ],
  },
  {
    id: "TPL-DEP",
    name: "Standard Departure",
    legs: 3,
    avg: "2h 00m",
    legs_def: [
      {
        phase: "Departure",
        from: "Hotel",
        to: "Stadium",
        offsetMin: 0,
        durMin: 40,
      },
      {
        phase: "Departure",
        from: "Stadium",
        to: "Airport",
        offsetMin: 90,
        durMin: 45,
      },
      {
        phase: "Departure",
        from: "Airport",
        to: "Departure Gate",
        offsetMin: 180,
        durMin: 30,
      },
    ],
  },
];

const amTemplateObj = computed(
  () =>
    movementTemplateLibrary.find((t) => t.id === amTemplate.value) ??
    movementTemplateLibrary[0]
);

function addMinutesToTime(base, offset) {
  const [h, m] = base.split(":").map(Number);
  const total = (h * 60 + m + offset) % (24 * 60);
  return `${String(Math.floor(total / 60)).padStart(2, "0")}:${String(
    total % 60
  ).padStart(2, "0")}`;
}

const amTemplateLegs = computed(() => {
  const tpl = amTemplateObj.value;
  if (!tpl) return [];
  const base = props.schedule.length;
  return tpl.legs_def.map((leg, i) => ({
    ...leg,
    dep: addMinutesToTime(amBaseTime.value, leg.offsetMin),
    arr: addMinutesToTime(amBaseTime.value, leg.offsetMin + leg.durMin),
    idx: base + i + 1,
  }));
});

const amCheckpoints = [
  "Vehicle dispatch",
  "Arrived at origin",
  "Team on board",
  "Bags loaded",
  "Depart origin",
  "Arrive at destination",
  "Handoff complete",
];

const showAddLeg = ref(false);
const alType = ref("transfer");
const alFrom = ref("");
const alTo = ref("");
const alDate = ref("");
const alVehicle = ref("");
const alStart = ref("09:00");
const alEnd = ref("09:45");

const alFlightNumber = ref("");
const alOrigin = ref("");
const alDestination = ref("");

const alTypes = [
  {
    id: "flight",
    label: "Flight",
    desc: "Auto-synced via carrier feed (no job)",
  },
  {
    id: "transfer",
    label: "Transfer",
    desc: "Ground movement → creates Movement + Job",
  },
  {
    id: "training",
    label: "Training",
    desc: "Hotel → Training pitch + return",
  },
  { id: "match", label: "Match", desc: "Hotel → Stadium + return" },
  { id: "return", label: "Return", desc: "Stadium/training → Hotel" },
];

const alTypeDesc = computed(
  () => alTypes.find((t) => t.id === alType.value)?.desc ?? ""
);

const showNewTeamPlan = ref(false);
const ntpTeam = ref("");
const ntpCountry = ref("");
const ntpCode = ref("");
const ntpOrigin = ref("");
const ntpDestination = ref("");
const ntpArrival = ref("");
const ntpDeparture = ref("");
const ntpPassengers = ref("");
const ntpLiaison = ref("");
const ntpLegs = ref("standard");

const ntpLegCount = { blank: 0, standard: 3, "full-match": 5 };
const ntpFooterHint = computed(() => {
  const n = ntpLegCount[ntpLegs.value];
  return n ? `Creates plan with ${n} pre-filled legs` : "";
});

const checkpointTemplates = [
  { id: "TPL-ARR", name: "Standard Arrival", steps: 3, avg: "2h 10m" },
  { id: "TPL-MATCH", name: "Match-day Round-trip", steps: 5, avg: "5h 30m" },
  { id: "TPL-DEP", name: "Standard Departure", steps: 3, avg: "2h 00m" },
];

const genMovements = computed(() =>
  selectedPlanMovements.value.filter((mv) => !mv.job_id)
);
const genAlreadyCount = computed(
  () => selectedPlanMovements.value.filter((mv) => mv.job_id).length
);
const selectedCheckpointTemplate = computed(
  () => checkpointTemplates.find((t) => t.id === genTemplate.value) ?? null
);

const tabs = computed(() => {
  const allTabs = [
    { id: "plans", label: "Plans", count: allPlans.value.length },
    {
      id: "movements",
      label: "Movements",
      count: selectedPlanMovements.value.length,
    },
    { id: "checkpoints", label: "Checkpoints", count: 12 },
    { id: "conflicts", label: "Conflicts", count: 3, danger: true },
    { id: "templates", label: "Templates", count: 5 },
  ];

  // If a plan is selected, hide the Plans tab, Checkpoints tab, and Templates tab
  if (activePlan.value) {
    return allTabs.filter((tab) => tab.id !== "plans" && tab.id !== "checkpoints" && tab.id !== "templates");
  }

  // If no plan selected, show only Plans tab
  return allTabs.filter((tab) => tab.id === "plans");
});

// Mock data for tabs
const checkpoints = [
  { id: "CP001", order: 1, name: "Vehicle Dispatch", type: "dispatch" },
  { id: "CP002", order: 2, name: "Team Pickup", type: "boarding" },
  { id: "CP003", order: 3, name: "Venue Arrival", type: "arrival" },
  { id: "CP004", order: 4, name: "Security Check", type: "arrival" },
  { id: "CP005", order: 5, name: "Team Handoff", type: "handoff" },
];

const conflicts = [
  {
    id: "C001",
    sev: "high",
    type: "Vehicle Overlap",
    text: "VEH-04 is assigned to two movements with overlapping time windows.",
    affects: ["M1", "M4"],
  },
  {
    id: "C002",
    sev: "medium",
    type: "Tight Turnaround",
    text: "Only 15 minutes between movements for Team ARG – may not be sufficient.",
    affects: ["M2", "M3"],
  },
  {
    id: "C003",
    sev: "low",
    type: "Route Optimization",
    text: "Movement M5 could be combined with M6 for efficiency.",
    affects: ["M5", "M6"],
  },
];

const templates = [
  {
    id: "T001",
    name: "Match Day - Standard",
    legs: 4,
    avg: "3h 45m",
    description:
      "Standard match day transportation protocol for team movements",
    movements: [
      {
        order: 1,
        from: "Team Hotel",
        to: "Stadium",
        type: "transfer",
        duration: "45m",
        vehicle: "Coach",
        pax: 35,
      },
      {
        order: 2,
        from: "Stadium",
        to: "Media Center",
        type: "transfer",
        duration: "20m",
        vehicle: "Van",
        pax: 8,
      },
      {
        order: 3,
        from: "Media Center",
        to: "Stadium",
        type: "transfer",
        duration: "15m",
        vehicle: "Van",
        pax: 8,
      },
      {
        order: 4,
        from: "Stadium",
        to: "Team Hotel",
        type: "transfer",
        duration: "50m",
        vehicle: "Coach",
        pax: 35,
      },
    ],
  },
  {
    id: "T002",
    name: "Training Session",
    legs: 2,
    avg: "2h 15m",
    description: "Daily training ground transportation routine",
    movements: [
      {
        order: 1,
        from: "Team Hotel",
        to: "Training Ground",
        type: "transfer",
        duration: "30m",
        vehicle: "Coach",
        pax: 35,
      },
      {
        order: 2,
        from: "Training Ground",
        to: "Team Hotel",
        type: "transfer",
        duration: "30m",
        vehicle: "Coach",
        pax: 35,
      },
    ],
  },
  {
    id: "T003",
    name: "Arrival Protocol",
    legs: 3,
    avg: "4h 30m",
    description:
      "Team arrival at destination city with airport pickup and hotel check-in",
    movements: [
      {
        order: 1,
        from: "Airport Gate",
        to: "Airport Arrivals",
        type: "arrival",
        duration: "30m",
        vehicle: "Walk",
        pax: 35,
      },
      {
        order: 2,
        from: "Airport Arrivals",
        to: "Team Hotel",
        type: "transfer",
        duration: "45m",
        vehicle: "Coach",
        pax: 35,
      },
      {
        order: 3,
        from: "Team Hotel",
        to: "Training Ground",
        type: "transfer",
        duration: "40m",
        vehicle: "Coach",
        pax: 35,
      },
    ],
  },
  {
    id: "T004",
    name: "Departure Protocol",
    legs: 3,
    avg: "3h 15m",
    description:
      "Team departure from city with hotel checkout and airport drop-off",
    movements: [
      {
        order: 1,
        from: "Team Hotel",
        to: "Airport",
        type: "transfer",
        duration: "45m",
        vehicle: "Coach",
        pax: 35,
      },
      {
        order: 2,
        from: "Airport Drop-off",
        to: "Airport Check-in",
        type: "departure",
        duration: "20m",
        vehicle: "Walk",
        pax: 35,
      },
      {
        order: 3,
        from: "Airport Check-in",
        to: "Departure Gate",
        type: "departure",
        duration: "40m",
        vehicle: "Walk",
        pax: 35,
      },
    ],
  },
  {
    id: "T005",
    name: "Media Day",
    legs: 2,
    avg: "1h 45m",
    description: "Press conference and media obligations transportation",
    movements: [
      {
        order: 1,
        from: "Team Hotel",
        to: "Media Center",
        type: "transfer",
        duration: "25m",
        vehicle: "Van",
        pax: 12,
      },
      {
        order: 2,
        from: "Media Center",
        to: "Team Hotel",
        type: "transfer",
        duration: "25m",
        vehicle: "Van",
        pax: 12,
      },
    ],
  },
];

const cpTypeTone = {
  dispatch: "primary",
  arrival: "ok",
  boarding: "live",
  departure: "primary",
  handoff: "neutral",
};

const sevTone = {
  high: "danger",
  medium: "warn",
  low: "neutral",
};

const dayGroups = computed(() => [
  {
    date: "Sat, 18 Apr 2026",
    items: props.schedule,
  },
]);

const teamGroups = computed(() => {
  // Use database data if available, otherwise fallback to flat file data
  if (props.movementsByTeam && props.movementsByTeam.length > 0) {
    // Filter movements by selected plan if one is selected
    if (activePlan.value) {
      return props.movementsByTeam
        .map((group) => ({
          ...group,
          items: group.items.filter((mv) => mv.plan_id === activePlan.value),
        }))
        .filter((group) => group.items.length > 0); // Remove teams with no movements in this plan
    }
    return props.movementsByTeam;
  }

  // Fallback to flat file data for backward compatibility
  const map = {};
  for (const mv of props.schedule) {
    if (!map[mv.team])
      map[mv.team] = { team: mv.team, code: mv.code, items: [] };
    map[mv.team].items.push(mv);
  }
  return Object.values(map);
});

const selectedTeamObj = computed(() => {
  if (!selectedTeam.value) {
    // Auto-select first team if none selected
    if (teamGroups.value.length > 0) {
      selectedTeam.value = teamGroups.value[0].team;
    }
    return null;
  }
  return teamGroups.value.find((g) => g.team === selectedTeam.value);
});

const filteredTeamMovements = computed(() => {
  if (!selectedTeamObj.value || !selectedTeamObj.value.items) {
    return [];
  }
  if (!teamMovementKindFilter.value) {
    return selectedTeamObj.value.items;
  }
  return selectedTeamObj.value.items.filter(
    (mv) => mv.kind === teamMovementKindFilter.value
  );
});

// Computed property for matches relevant to the movement being edited
const relevantMatches = computed(() => {
  if (!props.matches || !editingMovement.value?.team_id) return [];
  
  // Show matches where the movement's team is playing
  const teamCode = editingMovement.value.team?.code;
  return props.matches.filter(match => 
    match.team1_id === teamCode || match.team2_id === teamCode
  );
});

function mvIcon(mv) {
  console.log("Determining icon for movement:", mv.kind);
  if (mv.kind === "arrival") {
    console.log("Icon for arrival");
    return "arrival";
  }
  if (mv.kind === "departure") {
    console.log("Icon for departure");
    return "departure";
  }
  return "bus";
}

function mvIconColor(mv) {
  const statusColors = {
    done: "var(--ok)",
    "in-progress": "var(--live)",
    delayed: "var(--warn)",
  };
  return statusColors[mv.status] || "var(--ink4)";
}

function teamTotalPax(group) {
  // Use team's party_size_total if available from database
  if (group.party_size_total !== undefined && group.party_size_total !== null) {
    return group.party_size_total;
  }
  // Fallback to summing movement passengers for backward compatibility
  return group.items.reduce((sum, mv) => sum + (mv.pax || 0), 0);
}

function teamTimeRange(group) {
  if (!group.items.length) return "—";
  const times = group.items.map((mv) => mv.dep).sort();
  return `${times[0]} → ${times[times.length - 1]}`;
}

function teamStatusTone(group) {
  const statuses = group.items.map((mv) => mv.status);
  if (statuses.some((s) => s === "in-progress")) return "live";
  if (statuses.some((s) => s === "delayed")) return "warn";
  if (statuses.every((s) => s === "done")) return "ok";
  return "primary";
}

function teamStatusLabel(group) {
  const statuses = group.items.map((mv) => mv.status);
  if (statuses.some((s) => s === "in-progress")) return "active";
  if (statuses.some((s) => s === "delayed")) return "delayed";
  if (statuses.every((s) => s === "done")) return "completed";
  return "scheduled";
}

function teamOrigin(group) {
  // If team object has origin_airport from database, use it
  if (group.origin_airport) {
    return group.origin_airport;
  }

  // Otherwise try to extract from first arrival movement
  const arrival = group.items.find((mv) => mv.kind === "arrival");
  if (arrival) {
    // Extract city/airport code from location like "CDG T2" or "Madrid (MAD)"
    return arrival.from;
  }
  return "—";
}

function teamDestination(group) {
  // If team object has destination_airport from database, use it
  if (group.destination_airport) {
    return group.destination_airport;
  }

  // Otherwise try to extract from movements
  const departure = group.items.find((mv) => mv.kind === "departure");
  if (departure) {
    return departure.to;
  }
  // Fallback to first movement destination
  if (group.items.length > 0) {
    return group.items[0].to;
  }
  return "—";
}

function teamLiaison(group) {
  // If team object has liaison name from database, use it
  if (group.liaison) {
    return group.liaison;
  }

  // Fallback to generic name
  return `Liaison (${group.code})`;
}

function teamArrivalDate(group) {
  if (group.arrival_date_time) {
    return formatDateTime(group.arrival_date_time);
  }
  return "—";
}

function teamDepartureDate(group) {
  if (group.departure_date_time) {
    return formatDateTime(group.departure_date_time);
  }
  return "—";
}

function formatLocationWithAirport(location, airportCode, movementKind) {
  if (!location) return "—";

  // Only add airport code for arrival/departure movements and if airport code exists
  if (
    airportCode &&
    (movementKind === "arrival" || movementKind === "departure")
  ) {
    // Check if location already contains the airport code
    if (location.toUpperCase().includes(airportCode.toUpperCase())) {
      return location;
    }
    // Add airport code in parentheses
    return `${location} (${airportCode})`;
  }

  return location;
}

function formatLocationWithHotel(location, hotelName) {
  if (!location || !hotelName) return location || "—";

  // Check if location already contains the hotel name
  if (location.toLowerCase().includes(hotelName.toLowerCase())) {
    return location;
  }

  const hotelTerms = ["hotel", "team hotel"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a hotel reference
  if (hotelTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${hotelName})`;
  }

  return location;
}

function formatLocationWithTrainingGround(location, trainingGroundName) {
  if (!location || !trainingGroundName) return location || "—";

  // Check if location already contains the training ground name
  if (location.toLowerCase().includes(trainingGroundName.toLowerCase())) {
    return location;
  }

  const trainingTerms = ["training", "training ground"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a training ground reference
  if (trainingTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${trainingGroundName})`;
  }

  return location;
}

function movementFromLocation(mv) {
  if (!selectedTeamObj.value) return mv.from || "—";

  let location = mv.from || "—";

  // For arrival movements, augment with origin airport code
  if (mv.kind === "arrival" && selectedTeamObj.value.origin_airport) {
    location = formatLocationWithAirport(
      location,
      selectedTeamObj.value.origin_airport,
      mv.kind
    );
  }

  // Augment hotel references with actual hotel name
  if (selectedTeamObj.value.hotel_name) {
    location = formatLocationWithHotel(
      location,
      selectedTeamObj.value.hotel_name
    );
  }

  // Augment training ground references with actual training ground name
  if (selectedTeamObj.value.training_ground) {
    location = formatLocationWithTrainingGround(
      location,
      selectedTeamObj.value.training_ground
    );
  }

  return location;
}

function movementToLocation(mv) {
  if (!selectedTeamObj.value) return mv.to || "—";

  let location = mv.to || "—";

  // For departure movements, augment with destination airport code
  if (mv.kind === "departure" && selectedTeamObj.value.destination_airport) {
    location = formatLocationWithAirport(
      location,
      selectedTeamObj.value.destination_airport,
      mv.kind
    );
  }

  // Augment hotel references with actual hotel name
  if (selectedTeamObj.value.hotel_name) {
    location = formatLocationWithHotel(
      location,
      selectedTeamObj.value.hotel_name
    );
  }

  // Augment training ground references with actual training ground name
  if (selectedTeamObj.value.training_ground) {
    location = formatLocationWithTrainingGround(
      location,
      selectedTeamObj.value.training_ground
    );
  }

  return location;
}

const PLAN_STATUS_GROUPS = [
  { status: "active", label: "ACTIVE" },
  { status: "draft", label: "DRAFTS" },
  { status: "upcoming", label: "UPCOMING" },
  { status: "completed", label: "COMPLETED" },
];

const PLAN_STATUS_STYLE = {
  active: { bg: "#D1FAE5", color: "#065F46", dot: "#059669" },
  draft: { bg: "#FEF3C7", color: "#92400E", dot: "#D97706" },
  upcoming: { bg: "#DBEAFE", color: "#1E40AF", dot: "#3B82F6" },
  completed: { bg: "#F3F4F6", color: "#374151", dot: "#9CA3AF" },
};

// selectedPlanObj and related computed properties (allPlans defined earlier for initialization)
const selectedPlanObj = computed(
  () => allPlans.value.find((p) => p.id === activePlan.value) ?? null
);
const selectedPlanMovements = computed(
  () => selectedPlanObj.value?.movements ?? []
);

// Total passengers in the selected plan
const totalPassengers = computed(() => {
  return selectedPlanMovements.value.reduce((sum, mv) => sum + (mv.passengers || 0), 0);
});

// Filtered movements based on team filter
const filteredPlanMovements = computed(() => {
  if (!movementsTeamFilter.value) {
    return selectedPlanMovements.value;
  }
  return selectedPlanMovements.value.filter(
    (mv) => mv.team_id === movementsTeamFilter.value
  );
});

// Get unique teams that have movements in the current plan
const teamsInCurrentPlan = computed(() => {
  const teamMap = new Map();
  selectedPlanMovements.value.forEach((mv) => {
    if (mv.team) {
      teamMap.set(mv.team.id, mv.team);
    }
  });
  return Array.from(teamMap.values());
});

// Get teams that are not yet in the current plan
const availableTeamsToAdd = computed(() => {
  if (!props.teams) return [];
  const teamsInPlan = new Set(teamsInCurrentPlan.value.map((t) => t.id));
  return props.teams.filter((team) => !teamsInPlan.has(team.id));
});

// Count movements per team
function teamMovementCount(teamId) {
  return selectedPlanMovements.value.filter((mv) => mv.team_id === teamId)
    .length;
}

const selectedNewPlanTemplate = computed(() => {
  if (!newPlanTemplate.value) return null;
  return props.movementTemplates.find((t) => t.id === newPlanTemplate.value);
});

const selectedNewPlanTeam = computed(() => {
  if (!newPlanTeamId.value) return null;
  return props.teams.find((t) => t.id === newPlanTeamId.value);
});

const selectedTeamFlights = computed(() => {
  return selectedNewPlanTeam.value?.flights || [];
});

const needsFlightSelection = computed(() => {
  return selectedTeamFlights.value.length > 1;
});

// Bulk plan preview - group teams by arrival date
const bulkPreview = computed(() => {
  const teamsWithDates = [];
  const teamsWithoutDates = [];
  const dateGroups = {};

  // Group teams by arrival date
  props.teams.forEach((team) => {
    // Get selected flight or default to first flight
    const selectedFlightId = teamFlightSelections.value[team.id] || team.selected_flight_id;
    const selectedFlight = team.flights?.find(f => f.id === selectedFlightId) || team.flights?.[0];
    
    // Ensure team has valid display properties with fallbacks
    const normalizedTeam = {
      ...team,
      code: team.code || team.team || 'TEAM',
      team_name: team.team_name || team.team || 'Unnamed Team',
      arrival_date_time: selectedFlight?.scheduled_at || team.arrival_date_time,
      arrival_date: selectedFlight?.scheduled_date || team.arrival_date,
      selected_flight: selectedFlight,
      has_multiple_flights: (team.flights?.length || 0) > 1,
    };
    
    if (normalizedTeam.arrival_date_time || normalizedTeam.arrival_date) {
      const arrivalDate = normalizedTeam.arrival_date_time || normalizedTeam.arrival_date;
      const dateOnly = arrivalDate.split(/[ T]/)[0]; // Extract YYYY-MM-DD

      if (!dateGroups[dateOnly]) {
        dateGroups[dateOnly] = [];
      }
      dateGroups[dateOnly].push(normalizedTeam);
      teamsWithDates.push(normalizedTeam);
    } else {
      teamsWithoutDates.push(normalizedTeam);
    }
  });

  // Convert to array of plans sorted by date
  const plans = Object.entries(dateGroups)
    .map(([date, teams]) => ({
      date,
      teams: teams.sort((a, b) => (a.code || "").localeCompare(b.code || "")),
    }))
    .sort((a, b) => a.date.localeCompare(b.date));

  return {
    plans,
    plansCount: plans.length,
    teamsWithDates: teamsWithDates.length,
    teamsWithoutDates: teamsWithoutDates.length,
    teamsWithoutDatesList: teamsWithoutDates.sort((a, b) =>
      (a.code || "").localeCompare(b.code || "")
    ),
  };
});

function previewLegTime(index, type) {
  const legs = selectedNewPlanTemplate.value?.legs;
  if (!legs) return "—";
  if (!newPlanDate.value) return "—";

  // Parse date and start time
  const [hours, minutes] = (newPlanStartTime.value || "09:00")
    .split(":")
    .map(Number);
  const base = new Date(newPlanDate.value);
  if (isNaN(base.getTime())) return "—";

  // Set the start time
  base.setHours(hours, minutes, 0, 0);

  // Calculate cumulative offset from previous legs
  let offsetMin = 0;
  for (let i = 0; i < index; i++)
    offsetMin += legs[i].estimated_duration_minutes || 0;

  const start = new Date(base.getTime() + offsetMin * 60000);
  const end = new Date(
    start.getTime() + (legs[index].estimated_duration_minutes || 0) * 60000
  );
  const fmt = (d) => d.toTimeString().slice(0, 5);
  return type === "start" ? fmt(start) : fmt(end);
}

const planStatusPillStyle = (status) => {
  const s = PLAN_STATUS_STYLE[status] ?? PLAN_STATUS_STYLE.upcoming;
  return {
    display: "inline-flex",
    alignItems: "center",
    gap: "4px",
    padding: "1px 7px",
    borderRadius: "999px",
    fontSize: "10px",
    fontWeight: "600",
    background: s.bg,
    color: s.color,
  };
};

const planStatusDotStyle = (status) => {
  const s = PLAN_STATUS_STYLE[status] ?? PLAN_STATUS_STYLE.upcoming;
  return {
    width: "5px",
    height: "5px",
    borderRadius: "50%",
    background: s.dot,
    display: "inline-block",
    flexShrink: 0,
  };
};

const groupedFilteredPlans = computed(() => {
  const term = planSearchTerm.value.toLowerCase();
  return PLAN_STATUS_GROUPS.map((g) => ({
    ...g,
    plans: allPlans.value.filter(
      (p) =>
        p.status === g.status && (!term || p.name.toLowerCase().includes(term))
    ),
  }));
});

const allFilteredPlansEmpty = computed(() =>
  groupedFilteredPlans.value.every((g) => g.plans.length === 0)
);

function formatDateTime(dateString) {
  if (!dateString) return "";

  // Handle "YYYY-MM-DD HH:mm:ss" format without timezone conversion
  if (
    typeof dateString === "string" &&
    dateString.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/)
  ) {
    const [datePart, timePart] = dateString.split(" ");
    const [year, month, day] = datePart.split("-");
    const [hour, minute] = timePart.split(":");
    const monthNames = [
      "Jan",
      "Feb",
      "Mar",
      "Apr",
      "May",
      "Jun",
      "Jul",
      "Aug",
      "Sep",
      "Oct",
      "Nov",
      "Dec",
    ];
    return `${day} ${monthNames[parseInt(month) - 1]} ${hour}:${minute}`;
  }

  // Fallback: just return date for other formats
  const date = new Date(dateString);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
}

function formatDate(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString + "T00:00:00"); // Avoid timezone issues
  const monthNames = [
    "Jan",
    "Feb",
    "Mar",
    "Apr",
    "May",
    "Jun",
    "Jul",
    "Aug",
    "Sep",
    "Oct",
    "Nov",
    "Dec",
  ];
  const day = date.getDate();
  const month = monthNames[date.getMonth()];
  const year = date.getFullYear();
  return `${day} ${month} ${year}`;
}

function toggleTeamFlightExpansion(teamId) {
  if (expandedTeams.value.has(teamId)) {
    expandedTeams.value.delete(teamId);
  } else {
    expandedTeams.value.add(teamId);
  }
  // Trigger reactivity
  expandedTeams.value = new Set(expandedTeams.value);
}

function selectTeamFlight(teamId, flightId) {
  teamFlightSelections.value[teamId] = flightId;
  // Close the expansion after selection
  expandedTeams.value.delete(teamId);
  expandedTeams.value = new Set(expandedTeams.value);
}

function formatTime(dateString) {
  if (!dateString) return "-";
  // Extract time from datetime string without timezone conversion
  // Expected format: "2026-05-05T22:00:00.000000Z" or "2026-05-05 22:00:00"
  const timeMatch = dateString.match(/(\d{2}):(\d{2})/);
  if (timeMatch) {
    return `${timeMatch[1]}:${timeMatch[2]}`;
  }
  // Fallback to Date parsing if no match (shouldn't happen)
  const date = new Date(dateString);
  const hours = String(date.getHours()).padStart(2, "0");
  const minutes = String(date.getMinutes()).padStart(2, "0");
  return `${hours}:${minutes}`;
}

// Get checkpoint state (pending, started, completed, skipped)
function getCheckpointState(checkpoint) {
  if (!checkpoint) return "pending";
  if (checkpoint.state === "completed" || checkpoint.completed_at)
    return "done";
  if (checkpoint.state === "started" || checkpoint.started_at) return "active";
  if (checkpoint.state === "skipped") return "pending"; // Show as pending for now
  return "pending";
}

// Count completed checkpoints for a movement
function getCompletedCheckpointsCount(movement) {
  const checkpoints =
    movement.checkpoints || movement.checkpoint_template?.checkpoints || [];
  return checkpoints.filter((cp) => {
    const state = getCheckpointState(cp);
    return state === "done";
  }).length;
}

function selectPlan(planId) {
  activePlan.value = planId;
  planSearchTerm.value = "";
  showPlanDropdown.value = false;
  // Switch to Movements tab when selecting a plan
  activeTab.value = "movements";
}

function viewAllPlans() {
  activePlan.value = null;
  view.value = "day"; // Switch to day view so tabs are visible
  activeTab.value = "plans";
  showPlanDropdown.value = false;
}

function createPlan() {
  // Reset errors
  newPlanErrors.value = {};

  // Validate required fields
  if (!newPlanDate.value) {
    newPlanErrors.value.date = "Date is required";
    return;
  }

  // Validate flight selection if team has multiple flights
  if (needsFlightSelection.value && !newPlanFlightId.value) {
    newPlanErrors.value.flight_id = "Please select a flight";
    return;
  }

  newPlanProcessing.value = true;

  router.post(
    "/plans",
    {
      date: newPlanDate.value,
      start_time: newPlanStartTime.value || "09:00",
      name: newPlanName.value || null,
      team_id: newPlanTeamId.value || null,
      flight_id: newPlanFlightId.value || null,
      accommodation_id: newPlanAccommodationId.value || null,
      movement_template_id: newPlanTemplate.value || null,
    },
    {
      onSuccess: () => {
        showNewPlan.value = false;
        newPlanDate.value = "";
        newPlanStartTime.value = "09:00";
        newPlanName.value = "";
        newPlanTeamId.value = null;
        newPlanFlightId.value = null;
        newPlanAccommodationId.value = null;
        newPlanTemplate.value = "";
        newPlanErrors.value = {};
      },
      onError: (errors) => {
        newPlanErrors.value = errors;
      },
      onFinish: () => {
        newPlanProcessing.value = false;
      },
    }
  );
}

function createBulkPlans() {
  // Reset errors
  newPlanErrors.value = {};

  // Validate movement template selection
  if (!newPlanTemplate.value) {
    newPlanErrors.value.template =
      "Movement template is required for bulk creation";
    return;
  }

  // Check if there are plans to create
  if (bulkPreview.value.plans.length === 0) {
    newPlanErrors.value.template = "No teams with arrival dates found";
    return;
  }

  newPlanProcessing.value = true;

  // Prepare bulk data: one plan per unique arrival date with all teams and their specific times
  const bulkData = bulkPreview.value.plans.map((plan) => {
    // Map each team with its specific arrival time and flight
    const teams = plan.teams.map((team) => {
      let startTime = '09:00'; // fallback
      if (team.arrival_date_time) {
        const timeMatch = team.arrival_date_time.match(/[T ](\d{2}):(\d{2})/);
        if (timeMatch) {
          startTime = `${timeMatch[1]}:${timeMatch[2]}`;
        }
      }
      
      return {
        team_id: team.id,
        start_time: startTime,
        flight_id: team.selected_flight?.id || null,
      };
    });
    
    return {
      date: plan.date,
      teams: teams,
      movement_template_id: newPlanTemplate.value,
    };
  });

  router.post(
    "/plans/bulk",
    {
      plans: bulkData,
    },
    {
      onSuccess: () => {
        showNewPlan.value = false;
        newPlanMode.value = "single";
        newPlanTemplate.value = "";
        newPlanErrors.value = {};
      },
      onError: (errors) => {
        newPlanErrors.value = errors;
      },
      onFinish: () => {
        newPlanProcessing.value = false;
      },
    }
  );
}

function addTeamToPlan() {
  // Reset errors
  addTeamErrors.value = {};

  // Validate required fields
  if (!addTeamId.value) {
    addTeamErrors.value.team_id = "Please select a team";
    return;
  }

  if (!selectedPlanObj.value) {
    console.error("No plan selected");
    return;
  }

  addTeamProcessing.value = true;

  // Create movements for the team using the template via the plan's addMovement endpoint
  router.post(
    `/plans/${selectedPlanObj.value.id}/movements`,
    {
      team_id: addTeamId.value,
      movement_template_id: addTeamTemplate.value || null,
    },
    {
      onSuccess: () => {
        showAddTeamModal.value = false;
        addTeamId.value = null;
        addTeamTemplate.value = "";
        addTeamErrors.value = {};
        // Switch to team view to show the newly added team
        view.value = "team";
      },
      onError: (errors) => {
        addTeamErrors.value = errors;
      },
      onFinish: () => {
        addTeamProcessing.value = false;
      },
    }
  );
}

function duplicatePlan(plan) {
  if (!plan) {
    console.log("Duplicate plan: no plan provided");
    return;
  }

  // Extract just the date part (YYYY-MM-DD) without time
  let planDate = plan.date;
  if (planDate && planDate.includes("T")) {
    planDate = planDate.split("T")[0];
  }

  // Prepare duplicate plan data
  const duplicateData = {
    date: planDate,
    movement_template_id: plan.movement_template_id || null,
    // Backend should automatically suffix the name with " (Copy)" or similar
  };

  router.post("/plans", duplicateData, {
    onSuccess: () => {
      console.log("Plan duplicated successfully");
    },
    onError: (errors) => {
      console.error("Failed to duplicate plan:", errors);
    },
  });
}

function editPlan(plan) {
  editingPlan.value = plan;
  editPlanName.value = plan.name;
  // Extract date part (YYYY-MM-DD)
  const dateStr = plan.date;
  if (dateStr) {
    // If it includes time, extract just the date part
    editPlanDate.value = dateStr.includes("T")
      ? dateStr.split("T")[0]
      : dateStr;
  } else {
    editPlanDate.value = "";
  }
  // Extract start time from first movement or default to 09:00
  if (
    plan.movements &&
    plan.movements.length > 0 &&
    plan.movements[0].window_start
  ) {
    const departure = plan.movements[0].window_start;
    // Extract time from datetime string without timezone conversion
    const timeMatch = departure.match(/(\d{2}):(\d{2})/);
    if (timeMatch) {
      editPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
    } else {
      editPlanStartTime.value = "09:00";
    }
  } else {
    editPlanStartTime.value = "09:00";
  }
  // Set movement template ID
  editPlanTemplate.value = plan.movement_template_id || "";
  editPlanStatus.value = plan.status || "draft";
  editPlanErrors.value = {};
  showEditPlan.value = true;
}

function updatePlan() {
  if (!editingPlan.value) return;

  // Reset errors
  editPlanErrors.value = {};

  // Validate required fields
  let hasErrors = false;

  if (!editPlanName.value || editPlanName.value.trim() === "") {
    editPlanErrors.value.name = "Plan name is required";
    hasErrors = true;
  }

  if (!editPlanDate.value) {
    editPlanErrors.value.date = "Date is required";
    hasErrors = true;
  }

  if (hasErrors) {
    return;
  }

  editPlanProcessing.value = true;

  router.put(
    `/plans/${editingPlan.value.id}`,
    {
      name: editPlanName.value,
      date: editPlanDate.value,
      start_time: editPlanStartTime.value,
      movement_template_id: editPlanTemplate.value || null,
      status: editPlanStatus.value,
    },
    {
      onSuccess: () => {
        showEditPlan.value = false;
        editingPlan.value = null;
        editPlanName.value = "";
        editPlanDate.value = "";
        editPlanStartTime.value = "09:00";
        editPlanTemplate.value = "";
        editPlanStatus.value = "draft";
        editPlanErrors.value = {};
      },
      onError: (errors) => {
        editPlanErrors.value = errors;
      },
      onFinish: () => {
        editPlanProcessing.value = false;
      },
    }
  );
}

function deletePlan(plan) {
  deletingPlan.value = plan;
  showDeleteConfirmation.value = true;
}

function confirmDeletePlan() {
  if (!deletingPlan.value) return;

  deletePlanProcessing.value = true;
  const deletedPlanId = deletingPlan.value.id; // Store ID before clearing

  router.delete(`/plans/${deletedPlanId}`, {
    onSuccess: () => {
      showDeleteConfirmation.value = false;
      deletingPlan.value = null;
      // If the deleted plan was selected, clear selection
      if (activePlan.value === deletedPlanId) {
        activePlan.value = null;
        activeTab.value = "plans";
      }
    },
    onError: (errors) => {
      console.error("Failed to delete plan:", errors);
    },
    onFinish: () => {
      deletePlanProcessing.value = false;
    },
  });
}

function cancelDeletePlan() {
  showDeleteConfirmation.value = false;
  deletingPlan.value = null;
}

function switchView(newView) {
  view.value = newView;
  selectedMovement.value = null; // Clear selected movement when switching views
}

function selectMovement(movement) {
  // Toggle: if clicking the same movement, deselect it
  if (selectedMovement.value?.id === movement.id) {
    selectedMovement.value = null;
  } else {
    selectedMovement.value = movement;
    // Debug: log checkpoint data
    console.log("Selected movement:", movement);
    console.log(
      "Checkpoints:",
      movement.checkpoints || movement.checkpoint_template?.checkpoints
    );
    if (movement.checkpoints) {
      movement.checkpoints.forEach((cp, i) => {
        console.log(`Checkpoint ${i}:`, {
          name: cp.name,
          state: cp.state,
          completed_at: cp.completed_at,
          started_at: cp.started_at,
          completed_by: cp.completed_by,
        });
      });
    }
  }
}

function editMovement(movement) {
  editingMovement.value = movement;

  // Populate form fields - handle both "By Day" and "By Team" data structures
  emTeamId.value = movement.team_id || "";
  emKind.value = movement.kind || "";

  // Handle location properties (By Day uses from_location/to_location, By Team uses from/to)
  emFrom.value = movement.from_location || movement.from || "";
  emTo.value = movement.to_location || movement.to || "";

  // Handle datetime properties
  // By Day has window_start/window_end (datetime strings)
  // By Team has dep/arr (time strings like "14:30") - we need to combine with plan date
  if (movement.window_start) {
    emWindowStart.value = formatDateTimeForInput(movement.window_start);
  } else if (movement.dep && selectedPlanObj.value?.date) {
    // Construct datetime from plan date and time
    emWindowStart.value = `${selectedPlanObj.value.date}T${movement.dep}`;
  } else {
    emWindowStart.value = "";
  }

  if (movement.window_end) {
    emWindowEnd.value = formatDateTimeForInput(movement.window_end);
  } else if (movement.arr && selectedPlanObj.value?.date) {
    emWindowEnd.value = `${selectedPlanObj.value.date}T${movement.arr}`;
  } else {
    emWindowEnd.value = "";
  }

  // Handle vehicle/driver/supervisor IDs
  emVehicleId.value = movement.vehicle_id || "";
  emDriverId.value = movement.driver_id || "";
  emFieldSupervisorId.value = movement.field_supervisor_id || "";

  // Handle passengers (By Day uses passengers, By Team uses pax)
  emPassengers.value = movement.passengers ?? movement.pax ?? "";

  emFlightNumber.value = movement.flight_number || "";
  emNotes.value = movement.notes || "";
  emMatchId.value = movement.match_id || null;

  showEditMovement.value = true;
}

function formatDateTimeForInput(dateTime) {
  if (!dateTime) return "";
  // Parse date string directly without timezone conversion
  // Expected format: "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DDTHH:MM:SS.sssZ"
  const match = String(dateTime).match(
    /(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})/
  );
  if (!match) return "";

  const [, year, month, day, hours, minutes] = match;
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function submitEditMovement() {
  if (!editingMovement.value) return;

  editMovementProcessing.value = true;

  // Only send operational fields (not read-only flight/team data)
  router.put(
    `/movements/${editingMovement.value.id}`,
    {
      window_start: emWindowStart.value || null,
      window_end: emWindowEnd.value || null,
      vehicle_id: emVehicleId.value || null,
      driver_id: emDriverId.value || null,
      field_supervisor_id: emFieldSupervisorId.value || null,
      notes: emNotes.value || null,
      match_id: emMatchId.value || null,
    },
    {
      onSuccess: () => {
        showEditMovement.value = false;
        editingMovement.value = null;
      },
      onError: (errors) => {
        console.error("Failed to update movement:", errors);
      },
      onFinish: () => {
        editMovementProcessing.value = false;
      },
    }
  );
}

function deleteMovement(movement) {
  deletingMovement.value = movement;
  showDeleteMovementConfirmation.value = true;
}

function confirmDeleteMovement() {
  if (!deletingMovement.value) return;

  deleteMovementProcessing.value = true;

  router.delete(`/movements/${deletingMovement.value.id}`, {
    onSuccess: () => {
      showDeleteMovementConfirmation.value = false;
      deletingMovement.value = null;
    },
    onError: (errors) => {
      console.error("Failed to delete movement:", errors);
    },
    onFinish: () => {
      deleteMovementProcessing.value = false;
    },
  });
}

function cancelDeleteMovement() {
  showDeleteMovementConfirmation.value = false;
  deletingMovement.value = null;
}

function addMovement() {
  amMode.value = "manual";
  amPhase.value = "arrival";
  amTeam.value = "";
  amFrom.value = "";
  amTo.value = "";
  amStart.value = "15:00";
  amEnd.value = "15:45";
  amVehicle.value = "";
  amPassengers.value = "";
  amTemplate.value = "TPL-MATCH";
  amDate.value = selectedPlanObj.value?.date ?? "";
  amBaseTime.value = "14:00";
  showAddMovement.value = true;
}

function saveMovement(andAnother = false) {
  console.log("Save movement:", {
    phase: amPhase.value,
    team: amTeam.value,
    from: amFrom.value,
    to: amTo.value,
    start: amStart.value,
    end: amEnd.value,
    vehicle: amVehicle.value,
    passengers: amPassengers.value,
  });
  if (andAnother) {
    amFrom.value = "";
    amTo.value = "";
    amPassengers.value = "";
  } else {
    showAddMovement.value = false;
  }
}

function generateJobs() {
  genSelectedIds.value = genMovements.value.map((mv) => mv.id);
  genTemplate.value = "TPL-ARR";
  genAutoAssign.value = true;
  genNotifyLiaisons.value = true;
  showGenerateJobs.value = true;
}

function toggleGenMovement(id) {
  const idx = genSelectedIds.value.indexOf(id);
  if (idx === -1) genSelectedIds.value.push(id);
  else genSelectedIds.value.splice(idx, 1);
}

function confirmGenerateJobs() {
  if (!selectedPlanObj.value?.id) {
    console.error("No plan selected");
    return;
  }

  genProcessing.value = true;

  router.post(
    `/plans/${selectedPlanObj.value.id}/generate-jobs`,
    {
      movement_ids: genSelectedIds.value,
      auto_assign: genAutoAssign.value,
      notify_liaisons: genNotifyLiaisons.value,
    },
    {
      onSuccess: () => {
        showGenerateJobs.value = false;
        genSelectedIds.value = [];
        // Flash message will be shown by backend
      },
      onError: (errors) => {
        console.error("Failed to generate jobs:", errors);
        alert("Failed to generate jobs. Please try again.");
      },
      onFinish: () => {
        genProcessing.value = false;
      },
    }
  );
}

function generateSingleJob(movement) {
  if (!selectedPlanObj.value?.id) {
    console.error("No plan selected");
    return;
  }

  if (!movement?.id) {
    console.error("Invalid movement");
    return;
  }

  generatingJobForMovement.value = movement.id;

  router.post(
    `/plans/${selectedPlanObj.value.id}/generate-jobs`,
    {
      movement_ids: [movement.id],
      auto_assign: true,
      notify_liaisons: false,
    },
    {
      onSuccess: () => {
        // Job generated successfully, page will refresh with updated data
      },
      onError: (errors) => {
        console.error("Failed to generate job:", errors);
        alert("Failed to generate job. Please try again.");
      },
      onFinish: () => {
        generatingJobForMovement.value = null;
      },
    }
  );
}

function openPreviewModal(template) {
  selectedTemplate.value = template;
  showPreviewModal.value = true;
}

function applyTemplate(template) {
  console.log("Applying template:", template.id);
  showPreviewModal.value = false;
}

function openNewTeamPlan() {
  ntpTeam.value = "";
  ntpCountry.value = "";
  ntpCode.value = "";
  ntpOrigin.value = "";
  ntpDestination.value = "";
  ntpArrival.value = "";
  ntpDeparture.value = "";
  ntpPassengers.value = "";
  ntpLiaison.value = "";
  ntpLegs.value = "standard";
  showNewTeamPlan.value = true;
}

function createTeamPlan() {
  console.log("Creating team plan:", {
    team: ntpTeam.value,
    country: ntpCountry.value,
    code: ntpCode.value,
    origin: ntpOrigin.value,
    destination: ntpDestination.value,
    arrival: ntpArrival.value,
    departure: ntpDeparture.value,
    passengers: ntpPassengers.value,
    liaison: ntpLiaison.value,
    legs: ntpLegs.value,
  });
  showNewTeamPlan.value = false;
}

function syncFlightFeeds() {
  console.log("Syncing flight feeds...");
  // TODO: Implement flight feed sync logic
}

function exportPlan() {
  console.log("Exporting plan for team:", selectedTeam.value);
  // TODO: Implement plan export functionality (PDF, Excel, etc.)
}

function addLeg() {
  alType.value = "transfer";
  alFrom.value = "";
  alTo.value = "";
  alDate.value = selectedPlanObj.value?.date ?? "";
  alVehicle.value = "";
  alStart.value = "09:00";
  alEnd.value = "09:45";
  alFlightNumber.value = "";
  alOrigin.value = "";
  alDestination.value = "";
  showAddLeg.value = true;
}

function confirmAddLeg() {
  console.log("Add leg:", {
    type: alType.value,
    team: selectedTeam.value,
    from: alFrom.value,
    to: alTo.value,
    date: alDate.value,
    vehicle: alVehicle.value,
    start: alStart.value,
    end: alEnd.value,
  });
  showAddLeg.value = false;
}

const jobsGenerated = computed(() => {
  // Count movements that have generated jobs
  if (!selectedPlanObj.value || !selectedPlanObj.value.movements) return 0;
  return selectedPlanObj.value.movements.filter((mv) => mv.job_id).length;
});

const statusMap = {
  "in-progress": { tone: "live", label: "In Progress" },
  scheduled: { tone: "primary", label: "Scheduled" },
  delayed: { tone: "warn", label: "Delayed" },
  done: { tone: "ok", label: "Done" },
};
function statusTone(s) {
  return statusMap[s]?.tone ?? "neutral";
}
function statusLabel(s) {
  return statusMap[s]?.label ?? s;
}
</script>

<style scoped>
.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
  flex-wrap: wrap;
}
.stats-grid {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
  margin-bottom: 14px;
}
@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}
@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
.page-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 2px;
}
.page-sub {
  font-size: 13px;
  color: var(--ink3);
  margin: 0;
}
.page-header-actions {
  display: flex;
  gap: 8px;
  flex-shrink: 0;
  flex-wrap: wrap;
  align-items: center;
}

.view-toggle {
  display: flex;
  border: 1px solid var(--border);
  border-radius: 7px;
  overflow: hidden;
}
.toggle-btn {
  padding: 6px 14px;
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12.5px;
  font-weight: 500;
  color: var(--ink3);
}
.toggle-btn:hover {
  background: var(--panel);
}
.toggle-btn--active {
  background: var(--accent);
  color: #fff;
}

.day-group {
  margin-bottom: 20px;
}
.day-group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  padding: 0 2px;
}
.day-group-date {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
}
.day-group-count {
  font-size: 12px;
  color: var(--ink3);
}

.plan-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  overflow-x: auto;
}

.plan-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.plan-table th {
  padding: 8px 12px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ink3);
  border-bottom: 1px solid var(--border);
  background: var(--panel);
  white-space: nowrap;
}
.plan-table td {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.plan-table tr:last-child td {
  border-bottom: none;
}
.plan-row:hover td {
  background: var(--panel);
}

.mono {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  color: var(--ink3);
}
.job-link {
  color: var(--accent);
  cursor: pointer;
  text-decoration: none;
}
.job-link:hover {
  text-decoration: underline;
}
.flex-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}
.team-badge {
  width: 34px;
  height: 34px;
  border-radius: 7px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.team-badge-sm {
  width: 26px;
  height: 26px;
  border-radius: 5px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 9px;
  font-weight: 700;
  flex-shrink: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.route-cell {
  color: var(--ink3);
  font-size: 12px;
}
.row-link {
  color: var(--accent);
  text-decoration: none;
  font-size: 12px;
}
.row-link:hover {
  text-decoration: underline;
}

.kind-badge {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
}
.kind-badge--arrival {
  background: var(--ok-soft);
  color: var(--ok);
}
.kind-badge--departure {
  background: var(--danger-soft);
  color: var(--danger);
}
.kind-badge--transfer {
  background: var(--accent-soft);
  color: var(--accent-fg);
}

/* Status badges */
.status-badge--draft {
  background: #f3f4f6;
  color: #6b7280;
}
.status-badge--upcoming {
  background: #dbeafe;
  color: #1e40af;
}
.status-badge--active {
  background: #d1fae5;
  color: #065f46;
}
.status-badge--completed {
  background: #e0e7ff;
  color: #4338ca;
}
.status-badge--cancelled {
  background: #fee2e2;
  color: #991b1b;
}

/* Modal */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 100;
  padding: 16px;
}
.modal {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 460px;
  border: 1px solid var(--border);
  animation: slideIn 0.2s ease;
}
.modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
}
.modal-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
}
.modal-close {
  background: none;
  border: none;
  cursor: pointer;
  color: var(--ink3);
  padding: 4px;
  display: flex;
  border-radius: 5px;
}
.modal-close:hover {
  background: var(--panel);
  color: var(--ink);
}
.modal-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.modal-footer {
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  padding: 14px 20px;
  border-top: 1px solid var(--border);
}

.gen-modal .modal-footer {
  justify-content: space-between;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.form-field label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--ink2);
}
.form-field input,
.form-field select {
  padding: 8px 10px;
  border-radius: 7px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-family: inherit;
}
.form-field input:focus,
.form-field select:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.12);
}

/* Additional tab and conflict styling */
:root {
  --danger-soft: #fee2e2;
  --warn-soft: #fef3c7;
}

/* Generate Jobs Modal */
.gen-modal {
  max-width: 740px;
}

.new-plan-body {
  max-height: 560px;
  overflow-y: auto;
}

.modal-eyebrow {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.8px;
  text-transform: uppercase;
  color: var(--ink3);
  margin-bottom: 3px;
}

.gen-subtitle {
  font-size: 12px;
  color: var(--ink3);
  margin-top: 4px;
}

.gen-body {
  display: grid;
  grid-template-columns: 1.15fr 1fr;
  border-top: 1px solid var(--border);
  max-height: 460px;
  overflow-y: auto;
}

.gen-left {
  padding: 14px 16px;
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow-y: auto;
}

.gen-right {
  padding: 14px 16px;
  overflow-y: auto;
}

.gen-section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.gen-section-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  color: var(--ink3);
  display: block;
}

.gen-deselect {
  background: none;
  border: none;
  cursor: pointer;
  font-size: 12px;
  font-weight: 600;
  color: var(--accent);
  padding: 0;
  font-family: inherit;
}

.gen-movements {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.gen-mv-row {
  display: grid;
  grid-template-columns: 16px 28px auto 1fr auto auto;
  gap: 7px;
  align-items: center;
  padding: 8px 10px;
  border-radius: 8px;
  border: 1.5px solid var(--border);
  cursor: pointer;
  background: var(--surface);
  transition: border-color 0.12s, background 0.12s;
}

.gen-mv-row--checked {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.gen-checkbox {
  width: 14px;
  height: 14px;
  cursor: pointer;
  accent-color: var(--accent);
  flex-shrink: 0;
}

.gen-mv-id {
  font-family: var(--mono);
  font-size: 10px;
  font-weight: 700;
  color: var(--ink3);
}

.gen-mv-info {
  min-width: 0;
}

.gen-mv-team {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.gen-mv-route {
  font-size: 10.5px;
  color: var(--ink3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.gen-mv-times {
  text-align: right;
}

.gen-mv-vehicle {
  font-size: 11px;
  font-weight: 500;
  color: var(--ink2);
  display: flex;
  align-items: center;
  gap: 3px;
  white-space: nowrap;
}

.gen-mv-vehicle--warn {
  color: var(--warn);
}

.gen-conflict-banner {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  font-size: 12px;
  color: #92400e;
  line-height: 1.4;
  flex-shrink: 0;
}

/* Templates */
.gen-templates {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.gen-tpl-card {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  border: 1.5px solid var(--border);
  cursor: pointer;
  background: var(--surface);
  transition: border-color 0.12s, background 0.12s;
}

.gen-tpl-card--selected {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.gen-tpl-name {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 2px;
}

.gen-tpl-meta {
  font-size: 11px;
  color: var(--ink3);
  font-family: var(--mono);
}

/* Toggles */
.gen-options {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.gen-option-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.gen-option-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}
.gen-option-desc {
  font-size: 11px;
  color: var(--ink3);
  margin-top: 2px;
}

.gen-toggle {
  width: 40px;
  height: 22px;
  border-radius: 999px;
  background: var(--borderStrong);
  border: none;
  cursor: pointer;
  padding: 2px;
  display: flex;
  align-items: center;
  justify-content: flex-start;
  transition: background 0.15s, justify-content 0s;
  flex-shrink: 0;
}

.gen-toggle--on {
  background: var(--accent);
  justify-content: flex-end;
}

.gen-toggle-knob {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
  display: block;
}

/* Summary */
.gen-summary {
  margin-top: 16px;
  padding: 12px;
  background: var(--panel);
  border-radius: 8px;
  border: 1px solid var(--border);
}

.gen-summary-list {
  list-style: disc;
  padding-left: 16px;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 3px;
}

.gen-summary-list li {
  font-size: 12px;
  color: var(--ink2);
}

/* Footer count */
.gen-footer-count {
  font-size: 12px;
  color: var(--ink3);
}

/* Generation Progress Modal */
.gen-progress-body {
  padding: 48px 24px 36px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
  min-height: 280px;
}

.gen-progress-label {
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--ink3);
}

.gen-progress-counter {
  font-size: 52px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -2px;
  line-height: 1;
  font-family: var(--mono);
}

.gen-progress-bar-track {
  width: 260px;
  height: 6px;
  background: var(--border);
  border-radius: 999px;
  overflow: hidden;
}

.gen-progress-bar-fill {
  height: 100%;
  border-radius: 999px;
  background: var(--accent);
  transition: width 0.6s ease;
}

.gen-log {
  width: 100%;
  max-width: 460px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px 14px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.gen-log-line {
  font-size: 12px;
  color: var(--ink2);
  font-family: var(--mono);
  display: flex;
  align-items: center;
  gap: 7px;
}

.gen-log-check {
  color: var(--ok);
  font-weight: 700;
}

.gen-generating-hint {
  font-size: 12px;
  color: var(--ink3);
}

.gen-modal .modal-footer {
  justify-content: space-between;
}

/* Success modal */
.gen-success-body {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.gen-success-banner {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  padding: 14px 16px;
  background: var(--ok-soft);
  border: 1px solid var(--ok);
  border-radius: 10px;
}

.gen-success-icon {
  width: 34px;
  height: 34px;
  border-radius: 999px;
  background: var(--ok);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.gen-success-title {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 3px;
}

.gen-success-desc {
  font-size: 12.5px;
  color: var(--ink3);
  line-height: 1.5;
}

.gen-success-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.gen-success-col {
  padding: 14px 16px;
}
.gen-success-col + .gen-success-col {
  border-left: 1px solid var(--border);
}

.gen-success-id {
  font-family: var(--mono);
  font-size: 13px;
  color: var(--ink2);
  padding: 2px 0;
}

.gen-notif-line {
  font-size: 12.5px;
  font-family: var(--mono);
  color: var(--ink2);
  padding: 3px 0;
}

.gen-success-footer-hint {
  font-size: 12px;
  color: var(--ink3);
}

/* Add Leg Modal */
.al-modal {
  max-width: 520px;
}

.al-type-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 5px;
  padding: 9px 6px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  cursor: pointer;
  color: var(--ink2);
  font-family: inherit;
  transition: border-color 0.12s, background 0.12s;
}
.al-type-btn:hover {
  background: var(--panel);
}
.al-type-btn--active {
  border-color: var(--accent);
  background: var(--accent-soft, #eef2ff);
  color: var(--accent);
}
.al-type-icon {
  display: flex;
  align-items: center;
  justify-content: center;
}

.al-info-box {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px 14px;
}
.al-info-box--accent {
  background: var(--accent-soft, #eef2ff);
  border-color: var(--accent);
}

.al-flow-pill {
  font-size: 11px;
  font-weight: 600;
  padding: 3px 8px;
  border-radius: 5px;
  white-space: nowrap;
}
.al-flow-pill--blue {
  background: #dbeafe;
  color: #1d4ed8;
}
.al-flow-pill--purple {
  background: #ede9fe;
  color: #6d28d9;
}
.al-flow-pill--gray {
  background: var(--panel);
  color: var(--ink3);
  border: 1px solid var(--border);
}

/* Add Movement Modal */
.am-modal {
  max-width: 520px;
}

.am-mode-btn {
  padding: 10px 14px;
  border: 1.5px solid var(--border);
  border-radius: 8px;
  background: var(--surface);
  cursor: pointer;
  text-align: left;
  color: var(--ink);
  font-family: inherit;
  transition: border-color 0.12s, background 0.12s;
}
.am-mode-btn:hover {
  background: var(--panel);
}
.am-mode-btn--active {
  border-color: var(--accent);
  background: var(--accent-soft, #eef2ff);
  color: var(--accent);
}

.am-checkpoints {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px 14px;
}

.am-cp-pill {
  font-size: 11px;
  font-weight: 500;
  color: var(--ink2);
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 5px;
  padding: 3px 8px;
  white-space: nowrap;
}

/* New Team Plan Modal */
.ntp-modal {
  max-width: 620px;
}

.ntp-body {
  padding: 18px 20px;
  display: flex;
  flex-direction: column;
  gap: 14px;
  border-top: 1px solid var(--border);
}

.ntp-row {
  display: grid;
  gap: 10px;
}
.ntp-row--2 {
  grid-template-columns: 1fr 1fr;
}
.ntp-row--3 {
  grid-template-columns: 1fr 180px 80px;
}
.ntp-row--4 {
  grid-template-columns: 1fr 1fr 100px 1fr;
}

.ntp-label {
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.7px;
  text-transform: uppercase;
  color: var(--ink3);
  display: block;
  margin-bottom: 5px;
}

.ntp-input {
  width: 100%;
  padding: 8px 10px;
  border: 1.5px solid var(--border);
  border-radius: 7px;
  background: var(--surface);
  color: var(--ink);
  font-size: 13.5px;
  font-family: inherit;
  box-sizing: border-box;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.ntp-input:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

/* Starting legs */
.ntp-legs {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ntp-leg-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 14px;
  border: 1.5px solid var(--border);
  border-radius: 9px;
  background: var(--surface);
  cursor: pointer;
  transition: border-color 0.12s, background 0.12s;
}

.ntp-leg-card:hover {
  border-color: var(--borderStrong);
  background: var(--panel);
}

.ntp-leg-card--selected {
  border-color: var(--accent);
  background: var(--accent-soft);
}

.ntp-radio {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  accent-color: var(--accent);
  cursor: pointer;
}

.ntp-leg-title {
  font-size: 13px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 1px;
}

.ntp-leg-desc {
  font-size: 12px;
  color: var(--ink3);
}

.ntp-footer-hint {
  font-size: 12px;
  color: var(--accent);
  font-weight: 500;
}

.tabs {
  display: flex;
  gap: 4px;
  margin-bottom: 20px;
  border-bottom: 1px solid var(--border);
}
.tab {
  padding: 9px 16px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  margin-bottom: -1px;
  font-size: 13.5px;
  font-weight: 500;
  color: var(--ink3);
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 6px;
}
.tab:hover {
  color: var(--ink);
}
.tab--active {
  color: var(--accent);
  border-bottom-color: var(--accent);
}
.tab-count {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 0 6px;
  font-size: 11px;
  font-weight: 700;
}

/* Movement Detail Panel */
.movement-detail-panel {
  width: 360px;
  flex-shrink: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  height: 100%;
  max-height: calc(100vh - 280px);
  box-shadow: 0 2px 12px rgba(15, 23, 36, 0.08);
}

.detail-card-header {
  position: relative;
  padding: 14px 44px 14px 14px;
  border-bottom: 1px solid var(--border);
}

.detail-card-close {
  border: none;
  background: transparent;
  color: var(--ink3);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s;
  display: flex;
  align-items: center;
  justify-content: center;
}

.detail-card-close:hover {
  background: var(--border);
  color: var(--ink);
}

/* Stats grid (4 boxes) */
.dc-stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6px;
  margin-top: 12px;
}

.dc-stat {
  padding: 7px 8px;
  background: var(--panel);
  border-radius: 8px;
  border: 1px solid var(--border);
}

.dc-stat-label {
  font-size: 9px;
  color: var(--ink3);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  margin-bottom: 3px;
}

.dc-stat-value {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
  font-family: var(--mono, monospace);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Ghost pill */
.dc-pill {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 600;
}

.dc-pill--ghost {
  background: var(--panel);
  border: 1px solid var(--border);
  color: var(--ink3);
}

/* Checkpoints timeline */
.dc-cp-dot {
  width: 18px;
  height: 18px;
  border-radius: 999px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
}

.dc-cp-dot--done {
  background: var(--ok, #22a06b);
  border: 2px solid var(--ok, #22a06b);
  color: #fff;
}

.dc-cp-dot--active {
  background: #fff;
  border: 2px solid var(--accent, #4f46e5);
  box-shadow: 0 0 0 4px var(--accent-soft, rgba(79, 70, 229, 0.12));
}

.dc-cp-dot--pending {
  background: var(--panel);
  border: 2px solid var(--borderStrong, #d0d5df);
}

.dc-cp-line {
  width: 2px;
  flex: 1;
  background: var(--borderStrong, #d0d5df);
  min-height: 20px;
}

.detail-card-content {
  flex: 1;
  overflow-y: auto;
  padding: 14px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 5px 0;
  gap: 10px;
}

.detail-label {
  font-size: 12px;
  color: var(--ink3);
  font-weight: 500;
  flex-shrink: 0;
  min-width: 70px;
}

.detail-value {
  font-size: 12px;
  color: var(--ink);
  font-weight: 500;
  text-align: right;
  word-break: break-word;
}

.detail-value.mono {
  font-family: var(--mono, monospace);
}

.detail-card-footer {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 12px;
  border-top: 1px solid var(--border);
  background: var(--panel);
}

/* Slide Card Animation */
.slide-card-enter-active,
.slide-card-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-card-enter-from {
  opacity: 0;
  transform: translateX(20px);
}

.slide-card-leave-to {
  opacity: 0;
  transform: translateX(20px);
}
</style>
