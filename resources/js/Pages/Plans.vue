<template>
  <app-layout>
    <NoActiveEvent v-if="!activeEvent" what="plans" />

    <!-- Active Event Content -->
    <div v-else>
    <div class="page-header">
      <!-- Plan picker dropdown -->
      <div style="position: relative">
        <!-- Back to Plans link (when viewing movements) -->
        <div v-if="selectedPlanObj || (activePlan === null && activeTab !== 'plans')" style="margin-bottom: 6px">
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
          <!-- All Plans header info -->
          <div
            v-if="!selectedPlanObj && activePlan === null && activeTab !== 'plans'"
            style="
              display: flex;
              align-items: center;
              gap: 8px;
              margin-bottom: 3px;
              flex-wrap: wrap;
            "
          >
            <span style="font-size: 11px; color: #6b7280; font-weight: 500">
              {{ allPlans.length }} plans
            </span>
            <span style="font-size: 11px; color: #d1d5db; font-weight: 500">·</span>
            <span style="font-size: 11px; color: #6b7280; font-weight: 500">
              Viewing all movements
            </span>
          </div>
          <!-- Selected Plan header info -->
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
              {{ selectedPlanObj ? selectedPlanObj.name : (activePlan === null && activeTab !== 'plans' ? 'All Movements (Event-wide)' : 'Select a Plan') }}
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
            width: min(480px, calc(100vw - 36px));
          "
        >
          <!-- Search -->
          <div style="padding: 12px 12px 8px">
            <div style="display: flex; gap: 8px;">
              <input
                type="text"
                placeholder="Search plans..."
                v-model="planSearchTerm"
                @click.stop
                style="
                  flex: 1;
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
              <!-- View Toggle -->
              <div class="view-toggle" style="flex-shrink: 0; height: fit-content;">
                <button
                  @click="planDropdownView = 'list'"
                  :class="['toggle-btn', planDropdownView === 'list' ? 'toggle-btn--active' : '']"
                  title="List view"
                >
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: block;">
                    <rect x="1" y="2" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="1" y="6.75" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="1" y="11.5" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                  </svg>
                </button>
                <button
                  @click="planDropdownView = 'grid'"
                  :class="['toggle-btn', planDropdownView === 'grid' ? 'toggle-btn--active' : '']"
                  title="Grid view"
                >
                  <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: block;">
                    <rect x="1" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="9.5" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="1" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                    <rect x="9.5" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>

          <!-- Grouped plan list (LIST VIEW) -->
          <div v-if="planDropdownView === 'list'" style="max-height: 340px; overflow-y: auto; padding-bottom: 4px">
            <!-- Event-wide: the plan list, or every plan's movements -->
            <div
              v-for="opt in allPlanOptions"
              :key="opt.id"
              role="button"
              tabindex="0"
              class="plan-dd-all"
              :class="{ 'plan-dd-all--on': opt.active }"
              @click="opt.select()"
              @keydown.enter.prevent="opt.select()"
            >
              <div>
                <div style="font-size: 13px; font-weight: 600; color: #111827; margin-bottom: 2px">{{ opt.label }}</div>
                <div style="font-size: 11px; color: #9ca3af">{{ opt.sub }}</div>
              </div>
              <svg
                v-if="opt.active"
                width="16"
                height="16"
                viewBox="0 0 20 20"
                fill="none"
                style="color: #3b82f6; flex-shrink: 0; margin-left: 10px"
              >
                <path d="M4 10L8 14L16 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
            </div>
            <div style="height: 8px"></div>
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

          <!-- Grouped plan grid (GRID VIEW) -->
          <div v-if="planDropdownView === 'grid'" style="max-height: 400px; overflow-y: auto; padding: 8px 12px;">
            <!-- Event-wide: the plan list, or every plan's movements -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
              <div
                v-for="opt in allPlanOptions"
                :key="opt.id"
                role="button"
                tabindex="0"
                @click="opt.select()"
                @keydown.enter.prevent="opt.select()"
                :style="{
                  padding: '12px',
                  cursor: 'pointer',
                  borderRadius: '8px',
                  border: opt.active ? '2px solid #3B82F6' : '1px solid #e5e7eb',
                  background: opt.active ? '#EFF6FF' : '#fff',
                }"
              >
                <div style="font-size: 13px; font-weight: 700; color: #111827; margin-bottom: 4px;">{{ opt.label }}</div>
                <div style="font-size: 11px; color: #6b7280;">{{ opt.sub }}</div>
              </div>
            </div>

            <!-- Plans Grid -->
            <template v-for="group in groupedFilteredPlans" :key="group.status">
              <div v-if="group.plans.length">
                <div style="padding: 8px 4px 6px; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #9ca3af;">
                  {{ group.label }}
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 8px; margin-bottom: 12px;">
                  <div
                    v-for="plan in group.plans"
                    :key="plan.id"
                    @click="selectPlan(plan.id)"
                    :style="{
                      padding: '12px',
                      cursor: 'pointer',
                      borderRadius: '8px',
                      border: activePlan === plan.id ? '2px solid #3B82F6' : '1px solid #e5e7eb',
                      background: activePlan === plan.id ? '#EFF6FF' : '#fff',
                      transition: 'all 0.15s',
                      position: 'relative',
                    }"
                    @mouseenter="
                      (e) => {
                        if (activePlan !== plan.id) {
                          e.currentTarget.style.borderColor = '#cbd5e1';
                          e.currentTarget.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                        }
                      }
                    "
                    @mouseleave="
                      (e) => {
                        e.currentTarget.style.borderColor = activePlan === plan.id ? '#3B82F6' : '#e5e7eb';
                        e.currentTarget.style.boxShadow = 'none';
                      }
                    "
                  >
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px;">
                      <div style="font-size: 11px; font-weight: 700; font-family: monospace; color: #6b7280;">
                        {{ plan.code }}
                      </div>
                      <svg
                        v-if="activePlan === plan.id"
                        width="16"
                        height="16"
                        viewBox="0 0 20 20"
                        fill="none"
                        style="color: #3b82f6; flex-shrink: 0;"
                      >
                        <path
                          d="M4 10L8 14L16 6"
                          stroke="currentColor"
                          stroke-width="2.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </div>
                    <div style="font-size: 12px; font-weight: 600; color: #111827; margin-bottom: 6px; line-height: 1.3;">
                      {{ plan.name }}
                    </div>
                    <div style="font-size: 10px; color: #9ca3af; margin-bottom: 6px;">
                      {{ formatDateTime(plan.date) }}
                    </div>
                    <div style="display: flex; gap: 8px; font-size: 10px; color: #6b7280;">
                      <span>{{ plan.movements_count }} mvs</span>
                      <span>·</span>
                      <span>{{ plan.teams_count }} teams</span>
                    </div>
                  </div>
                </div>
              </div>
            </template>
            <div
              v-if="allFilteredPlansEmpty"
              style="
                padding: 40px 14px;
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
              :disabled="!canCreatePlan"
              :title="prerequisiteHint"
              @click="
                showNewPlan = true;
                showPlanDropdown = false;
              "
              :style="{
                background: 'none',
                border: 'none',
                color: canCreatePlan ? '#3b82f6' : 'var(--ink4)',
                fontSize: '13px',
                fontWeight: 600,
                cursor: canCreatePlan ? 'pointer' : 'not-allowed',
                padding: 0,
                display: 'flex',
                alignItems: 'center',
                gap: '5px',
              }"
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
          <!-- Show view toggle when viewing movements (selected plan or all plans) -->
          <div v-if="selectedPlanObj || (activePlan === null && activeTab !== 'plans')" class="view-toggle">
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
                view === 'plan' ? 'toggle-btn--active' : '',
              ]"
              @click="switchView('plan')"
            >
              By Plan
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

        <!-- Hidden: New team plan button - needs architectural review -->
        <!-- <Button v-else variant="primary" size="sm" @click="openNewTeamPlan">
          <template #icon><svg-icon name="plus" :size="14" style="color: #fff;" /></template>
          New team plan
        </Button> -->
      </div>
    </div>

    <!-- Guided steps for the open plan; every step links to the screen that does it. -->
    <PlanChecklist
      v-if="view === 'plan' && selectedPlanObj"
      :plan="selectedPlanObj"
      :open-conflicts="openConflicts"
      :can-manage="canManagePlans"
      @edit="editPlan(selectedPlanObj)"
      @movements="activeTab = 'movements'"
      @add-movement="activeTab = 'movements'; addMovement()"
      @conflicts="openPlanConflicts"
      @generate="generateFromChecklist"
    />

    <!-- Stats grid (only show when plan is selected) -->
    <div v-if="view === 'plan' && selectedPlanObj" class="stats-grid">
      <!-- <MiniStat label="Plan" :value="selectedPlanObj.name" /> -->
      <MiniStat
        label="Movements"
        :value="selectedPlanObj.movements_count || 0"
      />
      <MiniStat label="Teams" :value="selectedPlanObj.teams_count || 0" />
      <MiniStat label="Jobs generated" :value="jobsGenerated" />
      <MiniStat label="Conflicts" :value="openConflicts.length" tone="warn" />
      <MiniStat label="Passengers" :value="totalPassengers" />

    </div>

    <!-- By Plan view -->
    <template v-if="view === 'plan'">
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

      <PlanList
        v-if="activeTab === 'plans'"
        v-model:selected-ids="selectedPlanIds"
        :plans="allPlans"
        :open-conflicts="openConflicts"
        :prerequisites="prerequisites"
        :can-create-plan="canCreatePlan"
        :prerequisite-hint="prerequisiteHint"
        @new-plan="showNewPlan = true"
        @open="selectPlan"
        @duplicate="duplicatePlan"
        @edit="editPlan"
        @delete="deletePlan"
        @bulk-delete="openBulkDeletePlans"
      />

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
                flex-wrap: wrap;
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
              <select
                v-model="movementsPhaseFilter"
                style="
                  padding: 6px 10px;
                  border: 1px solid var(--border);
                  border-radius: 6px;
                  font-size: 12px;
                  color: var(--ink);
                  background: var(--surface);
                  cursor: pointer;
                  min-width: 150px;
                "
              >
                <option :value="null">
                  All Phases
                </option>
                <option
                  v-for="phase in phasesInCurrentPlan"
                  :key="phase"
                  :value="phase"
                >
                  {{ phaseLabels[phase] || phase }} ({{ phaseMovementCount(phase) }})
                </option>
              </select>
              <select
                v-model="movementsDateFilter"
                style="
                  padding: 6px 10px;
                  border: 1px solid var(--border);
                  border-radius: 6px;
                  font-size: 12px;
                  color: var(--ink);
                  background: var(--surface);
                  cursor: pointer;
                  min-width: 140px;
                "
              >
                <option :value="null">
                  All Dates
                </option>
                <option
                  v-for="date in datesInCurrentPlan"
                  :key="date"
                  :value="date"
                >
                  {{ date }} ({{ dateMovementCount(date) }})
                </option>
              </select>
              <select
                v-model="movementsJobFilter"
                style="
                  padding: 6px 10px;
                  border: 1px solid var(--border);
                  border-radius: 6px;
                  font-size: 12px;
                  color: var(--ink);
                  background: var(--surface);
                  cursor: pointer;
                  min-width: 170px;
                "
              >
                <option :value="null">
                  All Movements
                </option>
                <option value="ready">
                  Ready for Generation ({{ readyForGenerationCount }})
                </option>
                <option value="not-ready">
                  Not Ready ({{ notReadyForGenerationCount }})
                </option>
                <option value="generated">
                  Generated ({{ generatedCount }})
                </option>
                <option value="conflicts">
                  Has Conflicts ({{ conflictedMovementCount }})
                </option>
              </select>
              <span v-if="movementsFocusIds" class="conflict-focus-chip">
                Conflict {{ movementsFocusLabel }}
                <button type="button" aria-label="Clear conflict focus" @click="clearConflictFocus">✕</button>
              </span>
              <div
                v-if="movementsTeamFilter || movementsDateFilter || movementsPhaseFilter || movementsJobFilter || movementsFocusIds || movementColumnsActive"
                style="font-size: 11px; color: var(--ink3)"
              >
                Showing {{ filteredPlanMovements.length }} of
                {{ selectedPlanMovements.length }} movements
              </div>
              <a
                v-if="movementColumnsActive"
                style="font-size: 11px; cursor: pointer; color: var(--accent); text-decoration: underline;"
                @click="movementColumns.clearAll()"
                >Clear column sort &amp; filters</a
              >
              <div
                v-if="selectedMovementIds.size > 0"
                style="display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--ink3);"
              >
                <span>{{ selectedMovementIds.size }} selected</span>
                <a
                  style="cursor: pointer; color: var(--accent); text-decoration: underline;"
                  @click="selectedMovementIds = new Set()"
                  >Clear</a
                >
                <Button
                  variant="secondary"
                  size="sm"
                  style="color: #dc2626; border-color: #dc2626"
                  @click="showBulkDeleteMovementsConfirmation = true"
                >
                  Delete Selected ({{ selectedMovementIds.size }})
                </Button>
              </div>
              <div style="margin-left: auto; display: flex; gap: 8px;">
                <Button
                  variant="secondary"
                  size="sm"
                  @click="addMovement"
                >
                  <template #icon><svg-icon name="plus" :size="14" /></template>
                  Add movement
                </Button>
                <div :title="genCanGenerateTooltip">
                  <Button
                    variant="primary"
                    size="sm"
                    @click="generateJobs"
                    :disabled="!genCanGenerate"
                  >
                    <template #icon
                      ><svg-icon name="plus" :size="14" style="color: #fff"
                    /></template>
                    Generate jobs{{ genCanGenerate ? ` (${genReadyMovements.length})` : '' }}
                  </Button>
                </div>
              </div>
            </div>
            <div
              v-if="false && !activePlan && activeTab === 'movements' && !genCanGenerate && genMovements.length > 0"
              style="
                padding: 10px 14px;
                background: #fef3c7;
                border-bottom: 1px solid #fbbf24;
                font-size: 12px;
                color: #92400e;
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
              "
            >
              <svg
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                style="color: #f59e0b;"
              >
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              Job generation requires all movements to be from the same plan. Filter movements from different plans or select a specific plan.
            </div>

            <!-- Columns keep a minimum width; narrow screens scroll sideways instead of overlapping. -->
            <div style="overflow-x: auto; display: flex; flex-direction: column; flex: 1; min-height: 0;">
            <div style="min-width: 1180px; display: flex; flex-direction: column; flex: 1; min-height: 0;">
            <div
              :style="{ gridTemplateColumns: movementGridColumns }"
              style="
                display: grid;
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
              <div style="display: flex; align-items: center;">
                <input
                  type="checkbox"
                  :checked="allMovementsSelected"
                  @change="toggleSelectAllMovements"
                  style="cursor: pointer;"
                  title="Select all"
                />
              </div>
              <div>
                <ColumnFilter label="ID" column="id" :state="movementColumns" :rows="prefilteredPlanMovements" :count-by="planOf" count-unit="plan" />
              </div>
              <div>
                <ColumnFilter label="Date" column="date" :state="movementColumns" :rows="prefilteredPlanMovements" :sort-labels="dateSortLabels" :count-by="planOf" count-unit="plan" />
              </div>
              <div>
                <ColumnFilter label="Phase" column="phase" :state="movementColumns" :rows="prefilteredPlanMovements" :count-by="planOf" count-unit="plan" />
              </div>
              <div style="display: flex; align-items: center; gap: 4px;">
                <ColumnFilter label="Ref Time" column="ref" :state="movementColumns" :rows="prefilteredPlanMovements" :filterable="false" :sort-labels="dateSortLabels" style="width: auto" />
                <InfoIcon :size="12" @click="showRefTimeInfoModal = true" />
              </div>
              <div>
                <ColumnFilter label="Team & Route" column="team" :state="movementColumns" :rows="prefilteredPlanMovements" :count-by="planOf" count-unit="plan" />
              </div>
              <div title="From the first checkpoint time to that time plus the movement's duration">
                <ColumnFilter label="Window" column="window" :state="movementColumns" :rows="prefilteredPlanMovements" :filterable="false" :sort-labels="dateSortLabels" />
              </div>
              <div>
                <ColumnFilter label="Vehicle" column="vehicle" :state="movementColumns" :rows="prefilteredPlanMovements" :count-by="planOf" count-unit="plan" />
              </div>
              <div>
                <ColumnFilter label="Pax" column="pax" :state="movementColumns" :rows="prefilteredPlanMovements" :filterable="false" :sort-labels="numberSortLabels" />
              </div>
              <div>Checks</div>
              <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                <ColumnFilter label="Job" column="job" :state="movementColumns" :rows="prefilteredPlanMovements" :count-by="planOf" count-unit="plan" style="width: auto" />
                <InfoIcon :size="12" @click="showJobInfoModal = true" />
              </div>
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
                  gridTemplateColumns: movementGridColumns,
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
                <div style="display: flex; align-items: center;" @click.stop>
                  <input
                    type="checkbox"
                    :checked="selectedMovementIds.has(mv.id)"
                    @change="toggleMovementSelection(mv.id)"
                    style="cursor: pointer;"
                  />
                </div>
                <div style="white-space: nowrap;">
                  <div
                    style="
                      font-family: var(--mono);
                      font-size: 11px;
                      color: var(--ink);
                      font-weight: 700;
                    "
                  >
                    {{ mv.code || formatMovementCode(i + 1) }}
                    <span
                      v-if="conflictsByMovement.get(mv.id)"
                      :class="['conflict-marker', `conflict-marker--${worstSeverity(mv.id)}`]"
                      :title="conflictsByMovement.get(mv.id).map((c) => `${c.sev.toUpperCase()}: ${c.type}`).join('\n')"
                      @click.stop="resolvingConflict = conflictsByMovement.get(mv.id)[0]"
                    >⚠ {{ conflictsByMovement.get(mv.id).length }}</span>
                  </div>
                  <div
                    v-if="!activePlan && mv.plan_code"
                    style="
                      font-size: 9px;
                      color: var(--accent);
                      margin-top: 2px;
                      font-family: var(--mono);
                      cursor: pointer;
                      text-decoration: underline;
                    "
                    :title="'Go to ' + mv.plan_name"
                    @click.stop="selectPlan(mv.plan_id)"
                  >
                    {{ mv.plan_code }}
                  </div>
                </div>
                <div style="font-size: 11px; color: var(--ink3);">
                  {{ mv.window_start ? formatDate(mv.window_start) : '—' }}
                </div>
                <div v-if="mv.match_id || mv.kind" class="phase-cell" :title="phaseLines(mv).join(' · ')">
                  <Badge
                    type="kind"
                    :variant="mv.match_id ? 'match' : mv.kind"
                    >{{ mv.match_id ? 'Match' : mv.kind }}</Badge
                  >
                  <span v-if="phaseLines(mv)[0]" class="phase-line phase-line--ref">{{ phaseLines(mv)[0] }}</span>
                  <span v-if="phaseLines(mv)[1]" class="phase-line">{{ phaseLines(mv)[1] }}</span>
                </div>
                <span
                  v-else
                  style="
                    font-size: 11px;
                    color: var(--ink3);
                    font-style: italic;
                  "
                  >-</span
                >
                <div style="font-size: 11px; color: var(--ink3);">
                  <span v-if="isBusMovement(mv)" style="font-weight: 700; color: var(--ink2);">Bus<template v-if="mv.flight?.scheduled_at"> {{ formatTime(mv.flight.scheduled_at) }}</template></span>
                  <span v-else-if="(mv.kind === 'arrival' || mv.kind === 'departure') && mv.flight?.scheduled_at">
                    {{ formatTime(mv.flight.scheduled_at) }}
                  </span>
                  <span v-else-if="mv.match_id && mv.match?.kick_off">
                    {{ formatTime(mv.match.kick_off) }}
                  </span>
                  <span v-else>—</span>
                  <div v-if="(mv.kind === 'arrival' || mv.kind === 'departure') && mv.flight?.planned_bags" style="color: var(--ink4);">
                    {{ mv.flight.planned_bags }} bags
                  </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 3px;">
                  <div style="font-size: 12px; color: var(--ink); font-weight: 600; display: flex; align-items: center; gap: 4px;">
                    <flag-icon :code="mv.team?.country_id" />
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
                  <div style="font-size: 11px; color: var(--ink3);">
                    {{ formatMovementFromLocation(mv) }} → {{ formatMovementToLocation(mv) }}
                  </div>
                </div>
                <div
                  style="
                    font-family: var(--mono);
                    font-size: 11px;
                    color: var(--ink2);
                  "
                >
                  {{ formatWindow(mv) }}
                </div>
                <div style="font-size: 11px; color: var(--ink2)">
                  {{ mv.vehicle?.code || "-" }}
                  <span v-if="mv.units?.length" class="unit-more" :title="unitsLabel(mv)">+{{ mv.units.length }}</span>
                </div>
                <div
                  style="
                    font-size: 12px;
                    color: var(--ink2);
                    font-family: var(--mono);
                  "
                >
                  {{ mv.flight?.party_size_total ?? mv.pax ?? mv.passengers ?? '—' }}
                </div>
                <div
                  style="
                    font-size: 12px;
                    color: var(--ink3);
                    font-family: var(--mono);
                  "
                >
                  <template v-if="mv.checkpoints_total">
                    {{ mv.checkpoints_completed || 0 }}/{{ mv.checkpoints_total }}
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
                  <div v-else style="display: flex; flex-direction: column; align-items: center; gap: 4px;">
                    <Button
                      variant="secondary"
                      size="sm"
                      style="padding: 3px 8px; font-size: 11px"
                      @click="generateSingleJob(mv)"
                      :processing="generatingJobForMovement === mv.id"
                      :disabled="generatingJobForMovement === mv.id || !mv.field_supervisor_id || !mv.vehicle_id"
                      :title="!mv.field_supervisor_id ? 'Assign a supervisor before generating job' : !mv.vehicle_id ? 'Assign a vehicle before generating job' : ''"
                      >Generate</Button
                    >
                    <div v-if="!mv.field_supervisor_id || !mv.vehicle_id" style="display: flex; gap: 4px; align-items: center;">
                      <span v-if="!mv.field_supervisor_id" title="No supervisor assigned" style="color: #F59E0B; font-size: 14px;">⚠️</span>
                      <span v-if="!mv.vehicle_id" title="No vehicle assigned" style="color: #F59E0B; font-size: 14px;">🚗</span>
                    </div>
                  </div>
                </div>
                <div style="display: flex; gap: 4px" @click.stop>
                  <TableActions
                    @edit="editMovement(mv)"
                    @delete="deleteMovement(mv)"
                  />
                </div>
              </div>
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
                <Badge
                  v-if="selectedMovement.match_id"
                  type="kind"
                  variant="match"
                  :custom-style="{ fontSize: '10px' }"
                >
                  Match
                </Badge>
                <Badge
                  v-else-if="selectedMovement.kind"
                  type="kind"
                  :variant="selectedMovement.kind"
                  :custom-style="{ fontSize: '10px' }"
                >
                  {{ selectedMovement.kind }}
                </Badge>
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
                  display: flex;
                  align-items: center;
                  gap: 6px;
                "
              >
                <flag-icon :code="selectedMovement.team?.country_id" />
                {{ selectedMovement.team?.team_name || "—" }}
              </div>
              <div style="font-size: 12px; color: var(--ink3)">
                {{ formatMovementFromLocation(selectedMovement) || "Origin" }} →
                {{ formatMovementToLocation(selectedMovement) || "Destination" }}
              </div>

              <!-- Stats grid -->
              <div class="dc-stats-grid">
                <div class="dc-stat" title="From the first checkpoint time to that time plus the movement's duration">
                  <div class="dc-stat-label">Window</div>
                  <div class="dc-stat-value">
                    {{ formatWindow(selectedMovement) }}
                  </div>
                </div>
                <div class="dc-stat">
                  <div class="dc-stat-label">Pax</div>
                  <div class="dc-stat-value">
                    {{ selectedMovement.flight?.party_size_total ?? selectedMovement.passengers ?? "—" }}
                  </div>
                </div>
                <div class="dc-stat" v-if="selectedMovement.flight?.planned_bags">
                  <div class="dc-stat-label">Bags</div>
                  <div class="dc-stat-value">{{ selectedMovement.flight.planned_bags }}</div>
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
              <div v-if="checkpointsLoading" style="padding: 24px; text-align: center; font-size: 12px; color: var(--ink3);">
                Loading checkpoints…
              </div>
              <CheckpointTimeline
                v-else
                :checkpoints="selectedMovement.checkpoints || []"
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
                  <span class="detail-value" style="display: inline-flex; align-items: center; gap: 4px; flex-wrap: wrap;">
                    <flag-icon :code="selectedMovement.match.team1?.country_id" />
                    {{ selectedMovement.match.team1?.team_name || selectedMovement.match.team1?.team || "—" }}
                    vs
                    <flag-icon :code="selectedMovement.match.team2?.country_id" />
                    {{ selectedMovement.match.team2?.team_name || selectedMovement.match.team2?.team || "—" }}
                  </span>
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
                    selectedMovement.match.kick_off ? formatTime(selectedMovement.match.kick_off) : "—"
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
                <div v-if="selectedMovement.units?.length" class="detail-row">
                  <span class="detail-label">Extra vehicles</span>
                  <span class="detail-value">{{ unitsLabel(selectedMovement) }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Field Supervisor</span>
                  <span class="detail-value">{{
                    typeof selectedMovement.field_supervisor === "string"
                      ? selectedMovement.field_supervisor || "—"
                      : selectedMovement.field_supervisor?.name || "—"
                  }}</span>
                </div>
                <div v-if="selectedMovement.extra_supervisors?.length" class="detail-row">
                  <span class="detail-label">Extra supervisors</span>
                  <span class="detail-value">{{ selectedMovement.extra_supervisors.map((s) => s.name).join(', ') }}</span>
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

      <!-- Conflicts tab -->
      <ConflictsTab
        v-else-if="activeTab === 'conflicts'"
        v-model:scope="conflictScope"
        :conflicts="conflicts"
        :plan-movement-ids="selectedPlanObj ? selectedPlanMovements.map((mv) => mv.id) : null"
        @resolve="resolvingConflict = $event"
        @view="viewConflict"
      />
    </template>

    <!-- By Day view -->
    <template v-else-if="view === 'day'">
      <div
        v-if="dayViewDays.length === 0"
        class="plan-table-card"
        style="padding: 40px; text-align: center; color: var(--ink3)"
      >
        <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px">
          No scheduled movements
        </div>
        <div style="font-size: 12px">
          Movements appear here once they have a time.
        </div>
      </div>

      <div v-else class="dv">
        <!-- Date strip -->
        <div class="dv-strip">
          <button
            class="dv-strip-nav"
            :disabled="dayViewDayIndex <= 0"
            aria-label="Previous day"
            @click="stepDayView(-1)"
          >‹</button>
          <div ref="dayStripEl" class="dv-strip-days">
            <button
              v-for="d in dayViewDays"
              :key="d.key"
              :class="['dv-day', { 'dv-day--active': d.key === dayViewDate, 'dv-day--today': d.key === dayViewToday }]"
              @click="dayViewDate = d.key"
            >
              <span class="dv-day-dow">{{ dayLabel(d.key).dow }}</span>
              <span class="dv-day-num">{{ dayLabel(d.key).day }}</span>
              <span class="dv-day-mon">{{ dayLabel(d.key).month }}</span>
              <span class="dv-day-count">
                {{ d.count }}
                <span v-if="d.attention" class="dv-day-warn" :title="`${d.attention} need attention`">●</span>
              </span>
            </button>
          </div>
          <button
            class="dv-strip-nav"
            :disabled="dayViewDayIndex >= dayViewDays.length - 1"
            aria-label="Next day"
            @click="stepDayView(1)"
          >›</button>
        </div>

        <!-- Day summary -->
        <div class="dv-stats">
          <MiniStat label="Movements" :value="dayViewStats.movements" />
          <MiniStat label="Teams moving" :value="dayViewStats.teams" />
          <MiniStat label="Passengers" :value="dayViewStats.pax" />
          <MiniStat label="Needs crew" :value="dayViewStats.needsCrew" :tone="dayViewStats.needsCrew ? 'warn' : null" />
          <MiniStat label="Conflicts" :value="dayViewStats.conflicts" :tone="dayViewStats.conflicts ? 'danger' : null" />
          <MiniStat label="Jobs generated" :value="`${dayViewStats.jobs}/${dayViewStats.movements}`" :tone="dayViewStats.jobs === dayViewStats.movements ? 'ok' : null" />
        </div>

        <!-- Filters -->
        <div class="dv-filters">
          <button
            :class="['dv-chip', { 'dv-chip--active': !dayViewPhaseFilter }]"
            @click="dayViewPhaseFilter = null"
          >All ({{ dayViewMovements.length }})</button>
          <button
            v-for="p in dayViewPhases"
            :key="p.phase"
            :class="['dv-chip', { 'dv-chip--active': dayViewPhaseFilter === p.phase }]"
            @click="dayViewPhaseFilter = p.phase"
          >
            <span :class="['dv-dot', `dv-kind--${p.phase}`]"></span>
            {{ phaseLabels[p.phase] || p.phase }} ({{ p.count }})
          </button>
          <label class="dv-toggle">
            <input v-model="dayViewAttentionOnly" type="checkbox" />
            Needs attention only
          </label>
          <span v-if="dayViewUntimedCount" class="dv-muted" style="margin-left: auto">
            {{ dayViewUntimedCount }} movement{{ dayViewUntimedCount === 1 ? '' : 's' }} without a time not shown
          </span>
        </div>

        <div v-if="dayViewFiltered.length === 0" class="plan-table-card" style="padding: 32px; text-align: center; color: var(--ink3); font-size: 13px">
          Nothing matches these filters on {{ formatDate(dayViewDate) }}.
        </div>

        <div v-else :class="['dv-body', { 'dv-body--panel': selectedMovement }]">
          <div class="dv-main">
          <!-- Timeline: one row per team -->
          <div class="plan-table-card dv-timeline">
            <div class="dv-card-title">Timeline · {{ formatDate(dayViewDate) }}</div>
            <div class="dv-tl-grid">
              <div class="dv-tl-label"></div>
              <div class="dv-tl-axis">
                <span
                  v-for="h in dayViewTimeline.hours"
                  :key="h.left"
                  class="dv-tl-hour"
                  :style="{ left: h.left + '%' }"
                >{{ h.label }}</span>
              </div>
              <template v-for="row in dayViewTimeline.rows" :key="row.key">
                <div class="dv-tl-label">
                  <flag-icon :code="row.team?.country_id" />
                  <span>{{ row.team?.team_name || 'No team' }}</span>
                </div>
                <div class="dv-tl-track" :style="{ height: row.lanes * 24 + 6 + 'px' }">
                  <span
                    v-for="h in dayViewTimeline.hours"
                    :key="h.left"
                    class="dv-tl-gridline"
                    :style="{ left: h.left + '%' }"
                  ></span>
                  <button
                    v-for="bar in row.bars"
                    :key="bar.mv.id"
                    :class="['dv-tl-bar', `dv-kind--${movementPhase(bar.mv)}`, { 'dv-tl-bar--attention': needsAttention(bar.mv), 'dv-tl-bar--selected': selectedMovement?.id === bar.mv.id }]"
                    :style="{ left: bar.left + '%', width: bar.width + '%', top: bar.lane * 24 + 3 + 'px' }"
                    :title="`${bar.mv.code || ''} · ${formatWindow(bar.mv)}\n${formatMovementFromLocation(bar.mv)} → ${formatMovementToLocation(bar.mv)}`"
                    @click="selectMovement(bar.mv)"
                  >{{ bar.mv.code }}</button>
                </div>
              </template>
            </div>
          </div>

          <!-- Agenda -->
          <div class="plan-table-card dv-agenda">
            <div v-for="group in dayViewHourGroups" :key="group.hour" class="dv-hour">
              <div class="dv-hour-label">{{ group.hour }}:00</div>
              <div class="dv-hour-items">
                <div
                  v-for="mv in group.items"
                  :key="mv.id"
                  :class="['dv-row', { 'dv-row--attention': needsAttention(mv), 'dv-row--selected': selectedMovement?.id === mv.id }]"
                  @click="selectMovement(mv)"
                >
                  <div class="dv-row-time">
                    {{ formatWindow(mv) }}
                  </div>
                  <div class="dv-row-main">
                    <div class="dv-row-head">
                      <Badge type="kind" :variant="mv.match_id ? 'match' : mv.kind">{{ mv.match_id ? 'Match' : mv.kind }}</Badge>
                      <flag-icon :code="mv.team?.country_id" />
                      <span class="dv-row-team">{{ mv.team?.team_name || '—' }}</span>
                      <span class="dv-row-code">{{ mv.code }}</span>
                      <span
                        v-if="conflictsByMovement.get(mv.id)"
                        :class="['conflict-marker', `conflict-marker--${worstSeverity(mv.id)}`]"
                        :title="conflictsByMovement.get(mv.id).map((c) => `${c.sev.toUpperCase()}: ${c.type}`).join('\n')"
                        @click.stop="resolvingConflict = conflictsByMovement.get(mv.id)[0]"
                      >⚠ {{ conflictsByMovement.get(mv.id).length }}</span>
                      <a
                        v-if="!activePlan && mv.plan_code"
                        class="dv-row-plan"
                        :title="'Open ' + mv.plan_name"
                        @click.stop="openPlanFromDay(mv)"
                      >{{ mv.plan_code }}</a>
                    </div>
                    <div class="dv-muted">
                      {{ formatMovementFromLocation(mv) }} → {{ formatMovementToLocation(mv) }}
                      · {{ mv.flight?.party_size_total ?? mv.passengers ?? '—' }} pax
                      <template v-if="phaseLines(mv)[0]"> · {{ phaseLines(mv).join(' · ') }}</template>
                    </div>
                  </div>
                  <div class="dv-row-crew">
                    <span :class="['dv-crew', { 'dv-crew--missing': !mv.vehicle_id }]">🚐 {{ mv.vehicle?.code || 'No vehicle' }}</span>
                    <span :class="['dv-crew', { 'dv-crew--missing': !mv.driver_id }]">🧑‍✈️ {{ mv.driver?.name || 'No driver' }}</span>
                    <span :class="['dv-crew', { 'dv-crew--missing': !mv.field_supervisor_id }]">📋 {{ mv.field_supervisor?.name || 'No supervisor' }}</span>
                  </div>
                  <div class="dv-row-job" @click.stop>
                    <span
                      v-if="mv.checkpoints_total"
                      class="dv-checks"
                      :title="`${mv.checkpoints_completed || 0} of ${mv.checkpoints_total} checkpoints done`"
                    >✓ {{ mv.checkpoints_completed || 0 }}/{{ mv.checkpoints_total }}</span>
                    <span
                      v-if="mv.job_id"
                      class="dv-job-link"
                      @click="$inertia.visit(`/job/${mv.job_id}`)"
                    >{{ mv.job_id }}</span>
                    <Button
                      v-else
                      variant="secondary"
                      size="sm"
                      style="padding: 3px 8px; font-size: 11px"
                      :processing="generatingJobForMovement === mv.id"
                      :disabled="generatingJobForMovement === mv.id || !mv.field_supervisor_id || !mv.vehicle_id"
                      :title="!mv.field_supervisor_id ? 'Assign a supervisor before generating job' : !mv.vehicle_id ? 'Assign a vehicle before generating job' : ''"
                      @click="generateSingleJob(mv)"
                    >Generate</Button>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>

          <!-- Movement Detail Panel (By Day) -->
          <transition name="slide-card">
            <div v-if="selectedMovement" class="movement-detail-panel dv-panel">
              <div class="detail-card-header">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap">
                  <span style="font-family: var(--mono); font-size: 13px; font-weight: 700; color: var(--ink)">
                    {{ selectedMovement.code || "MVT" }}
                  </span>
                  <Badge
                    v-if="selectedMovement.match_id || selectedMovement.kind"
                    type="kind"
                    :variant="selectedMovement.match_id ? 'match' : selectedMovement.kind"
                    :custom-style="{ fontSize: '10px' }"
                  >{{ selectedMovement.match_id ? 'Match' : selectedMovement.kind }}</Badge>
                  <span v-if="selectedMovement.status" class="dc-pill dc-pill--ghost">{{ selectedMovement.status }}</span>
                </div>
                <div style="font-size: 15px; font-weight: 700; color: var(--ink); margin-bottom: 3px; display: flex; align-items: center; gap: 6px">
                  <flag-icon :code="selectedMovement.team?.country_id" />
                  {{ selectedMovement.team?.team_name || "—" }}
                </div>
                <div style="font-size: 12px; color: var(--ink3)">
                  {{ formatMovementFromLocation(selectedMovement) || "Origin" }} →
                  {{ formatMovementToLocation(selectedMovement) || "Destination" }}
                </div>
                <div class="dc-stats-grid">
                  <div class="dc-stat" style="grid-column: span 2">
                    <div class="dc-stat-label">Window</div>
                    <div class="dc-stat-value">{{ formatWindow(selectedMovement) }}</div>
                  </div>
                  <div class="dc-stat">
                    <div class="dc-stat-label">Pax</div>
                    <div class="dc-stat-value">{{ selectedMovement.flight?.party_size_total ?? selectedMovement.passengers ?? "—" }}</div>
                  </div>
                  <div v-if="selectedMovement.flight?.planned_bags" class="dc-stat">
                    <div class="dc-stat-label">Bags</div>
                    <div class="dc-stat-value">{{ selectedMovement.flight.planned_bags }}</div>
                  </div>
                </div>
                <button
                  class="detail-card-close"
                  style="position: absolute; top: 14px; right: 14px"
                  aria-label="Close"
                  @click="selectedMovement = null"
                >
                  <svg-icon name="x" :size="16" />
                </button>
              </div>

              <div class="detail-card-content">
                <div v-if="checkpointsLoading" style="padding: 24px; text-align: center; font-size: 12px; color: var(--ink3)">
                  Loading checkpoints…
                </div>
                <CheckpointTimeline
                  v-else
                  :checkpoints="selectedMovement.checkpoints || []"
                  title="Checkpoints"
                  empty-message="No checkpoints defined"
                />

                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border)">
                  <div class="dv-card-title" style="margin-bottom: 8px">Crew</div>
                  <div class="detail-row">
                    <span class="detail-label">Vehicle</span>
                    <span :class="['detail-value', { 'dv-missing-text': !selectedMovement.vehicle_id }]">{{ selectedMovement.vehicle?.code || "Not assigned" }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Driver</span>
                    <span :class="['detail-value', { 'dv-missing-text': !selectedMovement.driver_id }]">{{ selectedMovement.driver?.name || "Not assigned" }}</span>
                  </div>
                  <div v-if="selectedMovement.units?.length" class="detail-row">
                    <span class="detail-label">Extra vehicles</span>
                    <span class="detail-value">{{ unitsLabel(selectedMovement) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Field Supervisor</span>
                    <span :class="['detail-value', { 'dv-missing-text': !selectedMovement.field_supervisor_id }]">{{ selectedMovement.field_supervisor?.name || "Not assigned" }}</span>
                  </div>
                  <div v-if="selectedMovement.extra_supervisors?.length" class="detail-row">
                    <span class="detail-label">Extra supervisors</span>
                    <span class="detail-value">{{ selectedMovement.extra_supervisors.map((s) => s.name).join(', ') }}</span>
                  </div>
                  <div v-if="selectedMovement.job_id" class="detail-row">
                    <span class="detail-label">Job ID</span>
                    <span
                      class="detail-value mono"
                      style="color: var(--accent); cursor: pointer"
                      @click="$inertia.visit(`/job/${selectedMovement.job_id}`)"
                    >{{ selectedMovement.job_id }}</span>
                  </div>
                </div>
              </div>

              <div class="detail-card-footer">
                <Button variant="secondary" size="sm" @click="selectedMovement = null">Close</Button>
                <div style="flex: 1"></div>
                <Button
                  variant="primary"
                  size="sm"
                  @click="editMovement(selectedMovement); selectedMovement = null"
                >Edit Movement</Button>
              </div>
            </div>
          </transition>
        </div>
      </div>
    </template>

    <!-- By Team view -->
    <template v-else-if="view === 'team'">
      <div
        v-if="movementsByTeamLoading"
        style="
          display: flex;
          align-items: center;
          justify-content: center;
          height: calc(100vh - 280px);
          min-height: 500px;
          color: var(--ink3);
          font-size: 13px;
        "
      >
        Loading team movements…
      </div>
      <div
        v-else
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
                  margin-top: 32px;
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
                    <button
                      @click="teamMovementKindFilter = 'training'"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === 'training'
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === 'training'
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      Training
                    </button>
                    <button
                      @click="teamMovementKindFilter = 'daily_ops'"
                      :style="{
                        padding: '4px 10px',
                        border: '1px solid var(--border)',
                        borderRadius: '6px',
                        background:
                          teamMovementKindFilter === 'daily_ops'
                            ? 'var(--accent)'
                            : 'var(--surface)',
                        color:
                          teamMovementKindFilter === 'daily_ops'
                            ? '#fff'
                            : 'var(--ink3)',
                        fontSize: '11px',
                        fontWeight: '600',
                        cursor: 'pointer',
                        transition: 'all 0.15s',
                      }"
                    >
                      Daily Ops
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
                  grid-template-columns: 28px 100px 90px 80px 120px 1fr 1.4fr 110px 110px 90px;
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
                <div>Date</div>
                <div style="display: flex; align-items: center; gap: 4px;">
                  Ref Time
                  <InfoIcon :size="12" @click="showRefTimeInfoModal = true" />
                </div>
                <div>Type</div>
                <div>From → To</div>
                <div title="Planned window (first checkpoint to that time plus the movement's duration), with the actual time underneath">Window</div>
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
                      '28px 100px 90px 80px 120px 1fr 1.4fr 110px 110px 90px',
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
                  <div>
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
                      v-if="!activePlan && mv.plan_code"
                      style="
                        font-size: 9px;
                        color: var(--accent);
                        margin-top: 2px;
                        font-family: var(--font-mono, monospace);
                        cursor: pointer;
                        text-decoration: underline;
                      "
                      :title="'Go to ' + mv.plan_name"
                      @click.stop="selectPlan(mv.plan_id)"
                    >
                      {{ mv.plan_code }}
                    </div>
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      color: var(--ink2);
                      font-family: var(--font-mono, monospace);
                    "
                  >
                    {{ mv.window_start ? formatDate(mv.window_start) : '—' }}
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      color: var(--ink2);
                      font-family: var(--font-mono, monospace);
                    "
                  >
                    <span v-if="isBusMovement(mv)" style="font-weight: 700; color: var(--ink2);">Bus<template v-if="mv.flight?.scheduled_at"> {{ formatTime(mv.flight.scheduled_at) }}</template></span>
                    <span v-else-if="(mv.kind === 'arrival' || mv.kind === 'departure') && mv.flight?.scheduled_at">
                      {{ formatTime(mv.flight.scheduled_at) }}
                    </span>
                    <span v-else-if="mv.match_id && mv.match?.kick_off">
                      {{ formatTime(mv.match.kick_off) }}
                    </span>
                    <span v-else>—</span>
                    <div v-if="(mv.kind === 'arrival' || mv.kind === 'departure') && mv.flight?.planned_bags" style="color: var(--ink4);">
                      {{ mv.flight.planned_bags }} bags
                    </div>
                  </div>
                  <div v-if="mv.match_id" style="display: flex; flex-direction: column; align-items: flex-start; gap: 3px; min-width: 0;">
                    <Badge
                      type="kind"
                      variant="match"
                    >
                      Match {{ mv.match?.match_number || '' }}
                    </Badge>
                    <span
                      style="font-size: 10.5px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;"
                      :title="matchLineup(mv.match)"
                    >{{ matchLineup(mv.match) }}</span>
                  </div>
                  <Badge
                    v-else-if="mv.kind"
                    type="kind"
                    :variant="mv.kind"
                  >
                    {{ mv.kind }}
                  </Badge>
                  <span v-else>{{ mv.kind }}</span>
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
                      {{ formatWindow(mv) }}
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
                  <Badge
                    v-if="selectedMovement.match_id"
                    type="kind"
                    variant="match"
                    :custom-style="{ fontSize: '10px' }"
                  >
                    Match {{ selectedMovement.match?.match_number || '' }} · {{ matchLineup(selectedMovement.match) }}
                  </Badge>
                  <Badge
                    v-else-if="selectedMovement.kind"
                    type="kind"
                    :variant="selectedMovement.kind"
                    :custom-style="{ fontSize: '10px' }"
                  >
                    {{ selectedMovement.kind }}
                  </Badge>
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
                    display: flex;
                    align-items: center;
                    gap: 6px;
                  "
                >
                  <flag-icon :code="selectedMovement.team?.country_id" />
                  {{ selectedMovement.team?.team_name || "—" }}
                </div>
                <div style="font-size: 12px; color: var(--ink3)">
                  {{ movementFromLocation(selectedMovement) || "Origin" }} →
                  {{ movementToLocation(selectedMovement) || "Destination" }}
                </div>

                <!-- Stats grid -->
                <div class="dc-stats-grid">
                  <div class="dc-stat" title="From the first checkpoint time to that time plus the movement's duration">
                    <div class="dc-stat-label">Window</div>
                    <div class="dc-stat-value">
                      {{ formatWindow(selectedMovement) }}
                    </div>
                  </div>
                  <div class="dc-stat">
                    <div class="dc-stat-label">Pax</div>
                    <div class="dc-stat-value">
                      {{
                        selectedMovement.flight?.party_size_total ??
                        selectedMovement.pax ??
                        selectedMovement.passengers ??
                        "—"
                      }}
                    </div>
                  </div>
                  <div class="dc-stat" v-if="selectedMovement.flight?.planned_bags">
                    <div class="dc-stat-label">Bags</div>
                    <div class="dc-stat-value">{{ selectedMovement.flight.planned_bags }}</div>
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
                <div v-if="checkpointsLoading" style="padding: 24px; text-align: center; font-size: 12px; color: var(--ink3);">
                  Loading checkpoints…
                </div>
                <CheckpointTimeline
                  v-else
                  :checkpoints="selectedMovement.checkpoints || []"
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
                  <div v-if="selectedMovement.units?.length" class="detail-row">
                    <span class="detail-label">Extra vehicles</span>
                    <span class="detail-value">{{ unitsLabel(selectedMovement) }}</span>
                  </div>
                  <div class="detail-row">
                    <span class="detail-label">Field Supervisor</span>
                    <span class="detail-value">{{
                      typeof selectedMovement.field_supervisor === "string"
                        ? selectedMovement.field_supervisor || "—"
                        : selectedMovement.field_supervisor?.name || "—"
                    }}</span>
                  </div>
                  <div v-if="selectedMovement.extra_supervisors?.length" class="detail-row">
                    <span class="detail-label">Extra supervisors</span>
                    <span class="detail-value">{{ selectedMovement.extra_supervisors.map((s) => s.name).join(', ') }}</span>
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
      <NewPlanModal
        v-if="showNewPlan"
        :active-event="activeEvent"
        :teams="props.teams"
        :movement-templates="props.movementTemplates"
        :matches="props.matches"
        :next-movement-number="nextMovementNumber"
        @close="showNewPlan = false"
      />

      <!-- Edit Plan Modal -->
      <div
        v-if="showEditPlan"
        v-dialog="() => (showEditPlan = false)"
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

    <!-- Ref Time Info Modal -->
    <teleport to="body">
      <div
        v-if="showRefTimeInfoModal"
        v-dialog="() => (showRefTimeInfoModal = false)"
        class="modal-backdrop"
        @click.self="showRefTimeInfoModal = false"
      >
        <div class="modal" style="max-width: 500px">
          <div class="modal-header">
            <span class="modal-title">About Reference Time</span>
            <button class="modal-close" @click="showRefTimeInfoModal = false">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <div style="display: flex; flex-direction: column; gap: 16px;">
              <div>
                <h3 style="font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 8px;">
                  What is Reference Time?
                </h3>
                <p style="font-size: 13px; color: var(--ink2); margin: 0; line-height: 1.6;">
                  The <strong>Reference Time</strong> is the key scheduling anchor for a movement. 
                  It determines when the movement should be planned relative to other activities.
                </p>
              </div>
              
              <div>
                <h3 style="font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 8px;">
                  Reference Time Types
                </h3>
                <ul style="font-size: 13px; color: var(--ink2); margin: 0; padding-left: 20px; line-height: 1.8;">
                  <li><strong>Arrival movements:</strong> Uses the flight's scheduled arrival time</li>
                  <li><strong>Match movements:</strong> Uses the match kick-off time</li>
                  <li><strong>Other movements:</strong> Uses the movement's scheduled time</li>
                </ul>
              </div>
              
              <div>
                <h3 style="font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 8px;">
                  Why It Matters
                </h3>
                <p style="font-size: 13px; color: var(--ink2); margin: 0; line-height: 1.6;">
                  Reference times help you coordinate transportation windows, ensuring vehicles 
                  arrive on time relative to flights, matches, or other critical events.
                </p>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="showRefTimeInfoModal = false">
              Got it
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Job Info Modal -->
    <teleport to="body">
      <div
        v-if="showJobInfoModal"
        v-dialog="() => (showJobInfoModal = false)"
        class="modal-backdrop"
        @click.self="showJobInfoModal = false"
      >
        <div class="modal" style="max-width: 500px">
          <div class="modal-header">
            <span class="modal-title">About Jobs</span>
            <button class="modal-close" @click="showJobInfoModal = false">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <div style="display: flex; flex-direction: column; gap: 16px;">
              <div>
                <h3 style="font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 8px;">
                  What is a Job?
                </h3>
                <p style="font-size: 13px; color: var(--ink2); margin: 0; line-height: 1.6;">
                  A <strong>Job</strong> is an executable logistics operation created from a planned movement. 
                  It represents the actual task that will be performed by drivers and tracked by supervisors.
                </p>
              </div>
              
              <div>
                <h3 style="font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 8px;">
                  Movement vs Job
                </h3>
                <ul style="font-size: 13px; color: var(--ink2); margin: 0; padding-left: 20px; line-height: 1.8;">
                  <li><strong>Movement:</strong> A planned transportation activity with route and timing details</li>
                  <li><strong>Job:</strong> An assigned operation with checkpoints, resources, and real-time tracking</li>
                </ul>
              </div>
              
              <div>
                <h3 style="font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 8px;">
                  How to Generate
                </h3>
                <p style="font-size: 13px; color: var(--ink2); margin: 0; line-height: 1.6;">
                  Click the <strong>Generate</strong> button next to a movement to create a job. 
                  Requirements: movement must have a supervisor assigned. Once generated, 
                  the job ID becomes clickable to view execution progress.
                </p>
              </div>
            </div>
          </div>
          <div class="modal-footer" style="justify-content: flex-end;">
            <Button variant="secondary" size="sm" @click="showJobInfoModal = false">
              Got it
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Generate Jobs Modal -->
    <teleport to="body">
      <div
        v-if="showGenerateJobs"
        v-dialog="() => (showGenerateJobs = false)"
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
                {{ genReadyMovements.length }} of {{ genMovements.length }} movements ready ·
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
                    'gen-mv-row--disabled': !isReadyForGeneration(mv),
                  }"
                  :title="genRowBlockedReason(mv)"
                >
                  <input
                    type="checkbox"
                    :checked="genSelectedIds.includes(mv.id)"
                    :disabled="!isReadyForGeneration(mv)"
                    @change="toggleGenMovement(mv.id)"
                    class="gen-checkbox"
                  />
                  <div class="gen-mv-id">
                    {{ mv.code || formatMovementCode(genMovements.indexOf(mv) + 1) }}
                  </div>
                  <div v-if="mv.match_id" style="display: flex; flex-direction: column; align-items: flex-start; gap: 2px;">
                    <Badge
                      type="kind"
                      variant="match"
                      :custom-style="{ fontSize: '10px', whiteSpace: 'nowrap' }"
                      >Match {{ mv.match?.match_number || '' }}</Badge
                    >
                    <span style="font-size: 10px; color: var(--ink3); white-space: nowrap;">{{ matchLineup(mv.match) }}</span>
                  </div>
                  <Badge
                    v-else-if="mv.kind"
                    type="kind"
                    :variant="mv.kind"
                    :custom-style="{ fontSize: '10px', whiteSpace: 'nowrap' }"
                    >{{ mv.kind }}</Badge
                  >
                  <span v-else style="font-size: 10px; color: var(--ink3)"
                    >—</span
                  >
                  <div class="gen-mv-info">
                    <div class="gen-mv-team" style="display: flex; align-items: center; gap: 4px; white-space: normal;">
                      <flag-icon :code="mv.team?.country_id" />
                      <span style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ mv.team?.team_name || "—" }}</span>
                    </div>
                    <div class="gen-mv-route">
                      {{ formatMovementFromLocation(mv) }} →
                      {{ formatMovementToLocation(mv) }}
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
                      Pickup {{ formatTime(mv.window_start) || "—" }}
                    </div>
                    <div style="font-size: 10.5px; color: var(--ink3)">
                      {{ mv.flight?.party_size_total ?? mv.pax ?? mv.passengers ?? 0 }} pax ·
                      {{ mv.checkpoints_total || 0 }} chk
                    </div>
                  </div>
                  <div class="gen-mv-assign">
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
                    <div
                      v-if="!mv.field_supervisor_id"
                      class="gen-mv-vehicle gen-mv-vehicle--warn"
                    >
                      <svg-icon name="warn" :size="12" />
                      no supervisor
                    </div>
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

              <div v-if="openConflicts.length > 0" class="gen-conflict-banner">
                <svg-icon name="warn" :size="14" style="flex-shrink: 0" />
                <span
                  ><b>{{ openConflicts.length }} unresolved conflicts</b> — jobs
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
        v-dialog="() => genDone && (showGenProgress = false)"
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
        v-dialog="() => (showNewTeamPlan = false)"
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
        v-dialog="() => (showGenSuccess = false)"
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
                href="/jobs"
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
                @click="$inertia.visit('/jobs')"
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
        v-dialog="() => (showAddLeg = false)"
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
                    >Movement M{{ (props.movementsByTeam?.reduce((sum, group) => sum + (group.items?.length || 0), 0) || 0) + 1 }}</span
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
        v-dialog="() => (showAddMovement = false)"
        class="modal-backdrop"
        @click.self="showAddMovement = false"
      >
        <div class="modal am-modal">
          <!-- Header -->
          <div class="modal-header" style="align-items: flex-start">
            <div>
              <div class="modal-eyebrow">
                {{ selectedPlanObj?.code }} · {{ (selectedPlanObj?.date ?? '').slice(0, 10) }}
              </div>
              <div class="modal-title" style="font-size: 18px">
                Add movement
              </div>
            </div>
            <button class="modal-close" type="button" aria-label="Close" @click="showAddMovement = false">
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
            <p v-if="amError" class="am-error-banner" role="alert">{{ amError }}</p>

            <!-- Manual entry -->
            <template v-if="amMode === 'manual'">
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-phase">PHASE</label>
                  <select id="am-phase" v-model="amPhase">
                    <option value="arrival">Arrival</option>
                    <option value="departure">Departure</option>
                    <option value="transfer">Transfer</option>
                    <option value="training">Training</option>
                    <option value="match">Match</option>
                  </select>
                </div>
                <div class="form-field">
                  <label for="am-team">TEAM</label>
                  <select id="am-team" v-model="amTeam" :class="{ 'field--invalid': amErrors.team_id }">
                    <option value="">Select team...</option>
                    <option v-for="t in props.teams" :key="t.id" :value="t.id">
                      {{ t.code }} · {{ t.team_name }}
                    </option>
                  </select>
                  <span v-if="amErrors.team_id" class="am-field-error">{{ amErrors.team_id }}</span>
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-from">FROM</label>
                  <input
                    id="am-from"
                    type="text"
                    v-model="amFrom"
                    :class="{ 'field--invalid': amErrors.from_location }"
                    placeholder="e.g. Hotel Aurora"
                  />
                  <span v-if="amErrors.from_location" class="am-field-error">{{ amErrors.from_location }}</span>
                </div>
                <div class="form-field">
                  <label for="am-to">TO</label>
                  <input
                    id="am-to"
                    type="text"
                    v-model="amTo"
                    :class="{ 'field--invalid': amErrors.to_location }"
                    placeholder="e.g. Stadium Azure"
                  />
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-start">START</label>
                  <FormDateField id="am-start" v-model="amStart" mode="time" input-class="" placeholder="HH:MM" />
                </div>
                <div class="form-field">
                  <label for="am-end">END (ETA)</label>
                  <FormDateField
                    id="am-end"
                    v-model="amEnd"
                    mode="time"
                    placeholder="HH:MM"
                    :input-class="amErrors.window_end ? 'field--invalid' : ''"
                  />
                  <span v-if="amErrors.window_end" class="am-field-error">{{ amErrors.window_end }}</span>
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-vehicle">VEHICLE</label>
                  <select id="am-vehicle" v-model="amVehicle">
                    <option value="">Auto-assign...</option>
                    <option v-for="v in props.vehicles" :key="v.id" :value="v.id">
                      {{ v.code }}{{ v.vehicle_type ? ` · ${v.vehicle_type}` : '' }}
                    </option>
                  </select>
                </div>
                <div class="form-field">
                  <label for="am-pax">PASSENGERS</label>
                  <input
                    id="am-pax"
                    type="number"
                    v-model="amPassengers"
                    placeholder="0"
                    min="0"
                  />
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-fa">FUNCTIONAL AREA</label>
                  <select id="am-fa" v-model="amFunctionalArea">
                    <option value="">Unassigned</option>
                    <option value="LOG">LOG</option>
                    <option value="AND">AND</option>
                    <option value="MOB">MOB</option>
                  </select>
                </div>
                <div class="form-field">
                  <label for="am-cpt">CHECKPOINT TEMPLATE</label>
                  <select id="am-cpt" v-model="amCheckpointTemplate" :class="{ 'field--invalid': amErrors.checkpoint_template_id }">
                    <option value="">
                      {{ amTemplatesLoading ? 'Loading…' : 'Select template...' }}
                    </option>
                    <option v-for="t in amCheckpointTemplates" :key="t.id" :value="t.id">
                      {{ t.name }}
                    </option>
                  </select>
                  <span v-if="amErrors.checkpoint_template_id" class="am-field-error">{{ amErrors.checkpoint_template_id }}</span>
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
                <div v-if="amCheckpointNames.length" style="display: flex; flex-wrap: wrap; gap: 6px">
                  <span
                    v-for="(cp, i) in amCheckpointNames"
                    :key="i"
                    class="am-cp-pill"
                  >
                    {{ i + 1 }}. {{ cp }}
                  </span>
                </div>
                <div
                  style="font-size: 11px; color: var(--ink3); margin-top: 8px"
                >
                  <span v-if="amCheckpointNames.length">
                    From <strong>{{ amCheckpointTemplateObj?.name }}</strong> ·
                    {{ amCheckpointNames.length }} checkpoint{{ amCheckpointNames.length === 1 ? '' : 's' }}
                  </span>
                  <span v-else-if="amTemplatesLoading">Loading checkpoint templates…</span>
                  <span v-else-if="amCheckpointTemplates.length === 0">
                    No checkpoint template exists for a {{ amPhase }} movement. Create one in the Library first.
                  </span>
                  <span v-else>Pick a checkpoint template to see its sequence.</span>
                </div>
              </div>
            </template>

            <!-- From template -->
            <template v-else>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-template">TEMPLATE</label>
                  <select id="am-template" v-model="amTemplate" :class="{ 'field--invalid': amErrors.movement_template_id }">
                    <option value="">Select template...</option>
                    <option
                      v-for="t in props.movementTemplates"
                      :key="t.id"
                      :value="t.id"
                    >
                      {{ t.name }}
                    </option>
                  </select>
                  <span v-if="amErrors.movement_template_id" class="am-field-error">{{ amErrors.movement_template_id }}</span>
                </div>
                <div class="form-field">
                  <label for="am-template-team">TEAM</label>
                  <select id="am-template-team" v-model="amTeam" :class="{ 'field--invalid': amErrors.team_id }">
                    <option value="">Select team...</option>
                    <option v-for="t in props.teams" :key="t.id" :value="t.id">
                      {{ t.code }} · {{ t.team_name }}
                    </option>
                  </select>
                  <span v-if="amErrors.team_id" class="am-field-error">{{ amErrors.team_id }}</span>
                </div>
              </div>
              <div
                style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px"
              >
                <div class="form-field">
                  <label for="am-date">DATE</label>
                  <FormDateField
                    id="am-date"
                    v-model="amDate"
                    display-format="d/m/Y"
                    value-format="Y-m-d"
                    input-class=""
                    disabled
                  />
                </div>
                <div class="form-field">
                  <label for="am-base-time">BASE TIME (FIRST LEG)</label>
                  <FormDateField id="am-base-time" v-model="amBaseTime" mode="time" input-class="" placeholder="HH:MM" />
                </div>
              </div>

              <!-- Template preview card -->
              <div
                v-if="amTemplateObj"
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
                      {{ amTemplateLegs.length }} leg{{ amTemplateLegs.length === 1 ? '' : 's' }}
                      <span v-if="amTemplateObj.estimated_duration_minutes">
                        · approx {{ formatDurationMinutes(amTemplateObj.estimated_duration_minutes) }}
                      </span>
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
                    >{{ amTemplateObj.code }}</span
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
                :disabled="amProcessing"
                @click="showAddMovement = false"
                >Cancel</Button
              >
              <Button
                variant="secondary"
                size="sm"
                :disabled="amProcessing"
                @click="saveMovement(true)"
                >Save &amp; add another</Button
              >
              <Button
                variant="primary"
                size="sm"
                :disabled="amProcessing"
                :processing="amProcessing"
                @click="saveMovement(false)"
                >Add to plan</Button
              >
            </div>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Edit Movement Modal -->
    <teleport to="body">
      <EditMovementModal
        v-if="editingMovement"
        :key="editingMovement.id"
        :movement="editingMovement"
        :plan-date="selectedPlanObj?.date ?? null"
        :vehicles="props.vehicles"
        :drivers="props.drivers"
        :supervisors="props.supervisors"
        :matches="props.matches"
        @close="editingMovement = null"
      />
    </teleport>

    <!-- Bulk Delete Plans Modal -->
    <teleport to="body">
      <div
        v-if="showBulkDeletePlans"
        v-dialog="cancelBulkDeletePlans"
        class="modal-backdrop"
        @click.self="cancelBulkDeletePlans"
      >
        <div class="modal">
          <div class="modal-header">
            <span class="modal-title" style="color: #dc2626">
              Delete {{ bulkDeletePlans.length }} plan(s)
            </span>
            <button class="modal-close" @click="cancelBulkDeletePlans">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px">
              Are you sure you want to delete
              <strong>{{ bulkDeleteSummary.deletable.length }}</strong> plan(s)?
            </p>
            <div
              v-if="bulkDeleteSummary.movements > 0"
              style="
                font-size: 13px;
                color: #92400e;
                background: #fef3c7;
                border: 1px solid #fcd34d;
                border-radius: 6px;
                padding: 10px 12px;
                margin-bottom: 12px;
              "
            >
              <strong>{{ bulkDeleteSummary.movements }}</strong>
              movement(s) already exist in these plans and will be deleted as well.
            </div>
            <div
              v-if="bulkDeleteSummary.blocked.length"
              style="
                font-size: 13px;
                color: #991b1b;
                background: #fee2e2;
                border: 1px solid #fca5a5;
                border-radius: 6px;
                padding: 10px 12px;
                margin-bottom: 12px;
              "
            >
              {{ bulkDeleteSummary.blocked.length }} plan(s) have movements with
              generated jobs and will be skipped:
              {{ bulkDeleteSummary.blocked.map((p) => p.name).join(", ") }}
            </div>
            <div
              v-if="bulkDeleteSummary.deletable.length > ($page.props.deletionRules?.approveOver?.plans ?? Infinity)"
              class="delete-approval-note"
            >
              More than {{ $page.props.deletionRules.approveOver.plans }} plans: another admin has to approve
              in Setups → Recycle bin before they are deleted.
            </div>
            <div
              v-if="bulkDeleteSummary.movements > 0"
              class="form-field"
              style="margin-bottom: 12px"
            >
              <label for="bulk-delete-plans-confirm">
                Type <strong>DELETE</strong> to confirm
              </label>
              <input
                id="bulk-delete-plans-confirm"
                v-model="bulkDeleteConfirm"
                type="text"
                autocomplete="off"
                @input="bulkDeleteError = ''"
                @keydown.enter.prevent="canConfirmBulkDelete && confirmBulkDeletePlans()"
              />
            </div>
            <p
              v-if="bulkDeleteError"
              style="font-size: 12px; color: #dc2626; margin-bottom: 6px"
            >
              {{ bulkDeleteError }}
            </p>
            <p style="font-size: 13px; color: var(--ink3)">
              Moves to Setups → Recycle bin; it can be restored for {{ $page.props.deletionRules?.keepDays ?? 30 }} days.
            </p>
          </div>
          <div class="modal-footer">
            <Button
              variant="secondary"
              size="sm"
              :disabled="bulkDeleteProcessing"
              @click="cancelBulkDeletePlans"
              >Cancel</Button
            >
            <Button
              variant="primary"
              size="sm"
              :disabled="bulkDeleteProcessing || !canConfirmBulkDelete"
              style="background: #dc2626; border-color: #dc2626"
              @click="confirmBulkDeletePlans"
            >
              {{ bulkDeleteProcessing ? "Deleting..." : `Delete ${bulkDeleteSummary.deletable.length} Plan(s)` }}
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Delete Confirmation Modal -->
    <teleport to="body">
      <div
        v-if="showDeleteConfirmation && deletingPlan"
        v-dialog="cancelDeletePlan"
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

            <p
              v-if="deleteStep === 'checking'"
              style="font-size: 13px; color: var(--ink2)"
            >
              Checking for existing movements...
            </p>

            <template v-if="deleteStep === 'verify'">
              <div
                v-if="deleteCheck.movements_count > 0"
                style="
                  font-size: 13px;
                  color: #92400e;
                  background: #fef3c7;
                  border: 1px solid #fcd34d;
                  border-radius: 6px;
                  padding: 10px 12px;
                  margin-bottom: 12px;
                "
              >
                <strong>{{ deleteCheck.movements_count }}</strong>
                movement(s) already exist in this plan and will be deleted as
                well.
              </div>
              <p
                v-else
                style="font-size: 13px; color: var(--ink2); margin-bottom: 12px"
              >
                This plan has no movements.
              </p>

              <div
                v-if="deleteCheck.jobs_count > 0"
                style="
                  font-size: 13px;
                  color: #991b1b;
                  background: #fee2e2;
                  border: 1px solid #fca5a5;
                  border-radius: 6px;
                  padding: 10px 12px;
                  margin-bottom: 12px;
                "
              >
                {{ deleteCheck.jobs_count }} movement(s) have generated jobs.
                Delete those jobs first before deleting this plan.
              </div>

              <div v-else-if="deleteCheck.movements_count > 0" class="form-field" style="margin-bottom: 12px">
                <label for="delete-plan-confirm-name">
                  Type <strong>{{ deletingPlan.name }}</strong> to confirm
                </label>
                <input
                  id="delete-plan-confirm-name"
                  v-model="deleteConfirmName"
                  type="text"
                  autocomplete="off"
                  :style="deletePlanError ? { borderColor: '#DC2626' } : {}"
                  @input="deletePlanError = ''"
                  @keydown.enter.prevent="deleteNameMatches && confirmDeletePlan()"
                />
                <p
                  v-if="deletePlanError"
                  style="font-size: 12px; color: #dc2626; margin-top: 4px"
                >
                  {{ deletePlanError }}
                </p>
              </div>
              <p
                v-else-if="deletePlanError"
                style="font-size: 12px; color: #dc2626; margin-bottom: 6px"
              >
                {{ deletePlanError }}
              </p>
            </template>

            <p
              v-if="deleteStep === 'confirm' && deletePlanError"
              style="font-size: 12px; color: #dc2626; margin-bottom: 6px"
            >
              {{ deletePlanError }}
            </p>

            <p style="font-size: 13px; color: var(--ink3)">
              Moves to Setups → Recycle bin; it can be restored for {{ $page.props.deletionRules?.keepDays ?? 30 }} days.
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
              v-if="deleteStep !== 'verify'"
              variant="primary"
              size="sm"
              @click="checkPlanBeforeDelete"
              :disabled="deleteStep === 'checking'"
              style="background: #dc2626; border-color: #dc2626"
            >
              {{ deleteStep === "checking" ? "Checking..." : "Yes, continue" }}
            </Button>
            <Button
              v-else
              variant="primary"
              size="sm"
              @click="confirmDeletePlan"
              :disabled="
                deletePlanProcessing ||
                !deleteNameMatches ||
                deleteCheck.jobs_count > 0
              "
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
        v-dialog="cancelDeleteMovement"
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
              <span class="modal-title">{{ deletingMovement.job_id ? "Cannot Delete Movement" : "Delete Movement" }}</span>
            </div>
            <button class="modal-close" @click="cancelDeleteMovement">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <div v-if="deletingMovement.job_id" class="delete-blocked">
              Movement
              <strong>{{ deletingMovement.code || formatMovementCode(deletingMovement.id) }}</strong>
              has a generated job, <strong>{{ deletingMovement.job_id }}</strong>, so it can't be deleted.
              Delete the job in the Jobs Queue first, then delete the movement.
            </div>
            <p v-else style="font-size: 14px; color: var(--ink); margin-bottom: 12px">
              Are you sure you want to delete movement
              <strong>{{
                deletingMovement.code || formatMovementCode(deletingMovement.id)
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
              <li style="display: flex; align-items: center; gap: 4px;">
                Team:
                <flag-icon :code="deletingMovement.team?.country_id" />
                {{ deletingMovement.team?.team_name || "Not assigned" }}
              </li>
              <li>
                Route: {{ formatMovementFromLocation(deletingMovement) || "-" }} →
                {{ formatMovementToLocation(deletingMovement) || "-" }}
              </li>
              <li v-if="deletingMovement.job_id">
                Associated Job: {{ deletingMovement.job_id }}
              </li>
            </ul>
            <p v-if="!deletingMovement.job_id" style="font-size: 13px; color: var(--ink3)">
              Moves to Setups → Recycle bin; it can be restored for {{ $page.props.deletionRules?.keepDays ?? 30 }} days.
            </p>
          </div>
          <div class="modal-footer">
            <Button
              variant="secondary"
              size="sm"
              @click="cancelDeleteMovement"
              :disabled="deleteMovementProcessing"
              >{{ deletingMovement.job_id ? "Close" : "Cancel" }}</Button
            >
            <Button
              v-if="!deletingMovement.job_id"
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

    <!-- Bulk Delete Movements Confirmation Modal -->
    <teleport to="body">
      <div
        v-if="showBulkDeleteMovementsConfirmation"
        v-dialog="cancelBulkDeleteMovements"
        class="modal-backdrop"
        @click.self="cancelBulkDeleteMovements"
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
              <span class="modal-title">Delete Selected Movements</span>
            </div>
            <button class="modal-close" @click="cancelBulkDeleteMovements">
              <svg-icon name="x" />
            </button>
          </div>
          <div class="modal-body">
            <p
              v-if="bulkMovementDelete.deletable.length"
              style="font-size: 14px; color: var(--ink); margin-bottom: 12px"
            >
              Are you sure you want to delete
              <strong>{{ bulkMovementDelete.deletable.length }}</strong>
              movement{{ bulkMovementDelete.deletable.length === 1 ? "" : "s" }}?
            </p>
            <div v-if="bulkMovementDelete.withJobs.length" class="delete-blocked">
              <template v-if="bulkMovementDelete.deletable.length">
                {{ bulkMovementDelete.withJobs.length }} selected movement{{ bulkMovementDelete.withJobs.length === 1 ? " has" : "s have" }}
                a generated job and will be kept:
              </template>
              <template v-else>
                {{ bulkMovementDelete.withJobs.length === 1 ? "The selected movement has" : "All selected movements have" }}
                a generated job, so nothing can be deleted:
              </template>
              <ul class="delete-blocked__list">
                <li v-for="mv in bulkMovementDelete.withJobs" :key="mv.id">
                  <strong>{{ mv.code || formatMovementCode(mv.id) }}</strong>
                  · {{ mv.team?.team_name || "No team" }} · {{ mv.job_id }}
                </li>
              </ul>
              Delete the job in the Jobs Queue first to remove its movement.
            </div>
            <p
              v-if="bulkMovementDelete.deletable.length"
              style="font-size: 13px; color: var(--ink3)"
            >
              Moves to Setups → Recycle bin; it can be restored for {{ $page.props.deletionRules?.keepDays ?? 30 }} days.
            </p>
          </div>
          <div class="modal-footer">
            <Button
              variant="secondary"
              size="sm"
              @click="cancelBulkDeleteMovements"
              :disabled="bulkDeletingMovements"
              >{{ bulkMovementDelete.deletable.length ? "Cancel" : "Close" }}</Button
            >
            <Button
              v-if="bulkMovementDelete.deletable.length"
              variant="primary"
              size="sm"
              @click="confirmDeleteSelectedMovements"
              :disabled="bulkDeletingMovements"
              style="background: #dc2626; border-color: #dc2626"
            >
              {{
                bulkDeletingMovements
                  ? "Deleting..."
                  : `Delete ${bulkMovementDelete.deletable.length} Movement${bulkMovementDelete.deletable.length === 1 ? "" : "s"}`
              }}
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <teleport to="body">
      <!-- Add Team to Plan Modal -->
      <div
        v-if="showAddTeamModal"
        v-dialog="() => (showAddTeamModal = false)"
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

    <!-- Nothing can be generated: explains why -->
    <ConfirmModal
      :show="showGenerateBlocked"
      title="Cannot Generate Jobs"
      :message="genBlockedMessage"
      confirm-label="Got it"
      hide-cancel
      @close="showGenerateBlocked = false"
      @confirm="showGenerateBlocked = false"
    />

    <!-- Some movements are incomplete and will be left out of the batch -->
    <ConfirmModal
      :show="showGenerateSkipConfirm"
      title="Skip Incomplete Movements?"
      :message="genSkipMessage"
      confirm-label="Continue"
      @close="showGenerateSkipConfirm = false"
      @confirm="openGenerateJobsModal"
    />

    <ConflictResolvePanel
      :conflict="resolvingConflict"
      :conflicts="conflicts"
      @close="resolvingConflict = null"
    />
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "../Composables/useToast";
import axios from "axios";
import AppLayout from "../Components/AppLayout.vue";
import NoActiveEvent from "../Components/NoActiveEvent.vue";
import StatusPill from "../Components/StatusPill.vue";
import SvgIcon from "../Components/SvgIcon.vue";
import MiniStat from "../Components/MiniStat.vue";
import Button from "../Components/Button.vue";
import RefreshButton from "../Components/RefreshButton.vue";
import TableActions from "../Components/TableActions.vue";
import CheckpointTimeline from "../Components/CheckpointTimeline.vue";
import Badge from "../Components/Badge.vue";
import ConflictResolvePanel from "../Components/ConflictResolvePanel.vue";
import InfoIcon from "../Components/InfoIcon.vue";
import FlagIcon from "../Components/FlagIcon.vue";
import ConfirmModal from "../Components/ConfirmModal.vue";
import NewPlanModal from "../Components/Plans/NewPlanModal.vue";
import EditMovementModal from "../Components/Plans/EditMovementModal.vue";
import ConflictsTab from "../Components/Plans/ConflictsTab.vue";
import PlanList from "../Components/Plans/PlanList.vue";
import PlanChecklist from "../Components/Plans/PlanChecklist.vue";
import { formatDate, formatDateTime, formatMovementCode, legMinutes } from "../Composables/planFormat";
import FormDateField from "../Components/FormDateField.vue";
import ColumnFilter from "../Components/ColumnFilter.vue";
import { useColumnFilters } from "../Composables/useColumnFilters";

const { success: showSuccessToast, error: showErrorToast } = useToast();

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
  venueCount: { type: Number, default: 0 },
  conflicts: { type: Array, default: () => [] },
  nextMovementNumber: { type: Number, default: 1 },
});


// A plan can't be built without these, so the New Plan actions stay disabled
// until they exist. Venues are listed for completeness but aren't required —
// a movement can be created with free-text locations.
const prerequisites = computed(() => [
  { label: 'Teams', met: props.teams.length > 0, required: true, href: '/event-teams' },
  { label: 'Matches', met: props.matches.length > 0, required: true, href: '/matches' },
  { label: 'Movement templates', met: props.movementTemplates.length > 0, required: true, href: '/library' },
  { label: 'Venues', met: props.venueCount > 0, required: false, href: '/venues' },
]);

const missingPrerequisites = computed(() =>
  prerequisites.value.filter(p => p.required && !p.met)
);

const canCreatePlan = computed(() => missingPrerequisites.value.length === 0);

const prerequisiteHint = computed(() =>
  canCreatePlan.value
    ? ''
    : `Add ${missingPrerequisites.value.map(p => p.label.toLowerCase()).join(', ')} for this event first`
);

const view = ref("plan");
const showNewPlan = ref(false);

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
// 'confirm' -> 'checking' -> 'verify'
const deleteStep = ref("confirm");
const deleteCheck = ref({ movements_count: 0, jobs_count: 0 });
const deleteConfirmName = ref("");
const deletePlanError = ref("");
const deleteNameMatches = computed(
  () =>
    !!deletingPlan.value &&
    // An empty plan loses nothing, so it needs no typed confirmation.
    (deleteCheck.value.movements_count === 0 ||
      deleteConfirmName.value.trim() === (deletingPlan.value.name || "").trim())
);

// Bulk plan deletion ("View All Plans")
const selectedPlanIds = ref(new Set());
// The plans the bulk-delete dialog acts on: those ticked and on screen when it opened.
const bulkDeletePlans = ref([]);
const showBulkDeletePlans = ref(false);
const bulkDeleteConfirm = ref("");
const bulkDeleteError = ref("");
const bulkDeleteProcessing = ref(false);

const planJobCount = (plan) => (plan.movements || []).filter((mv) => mv.job_id).length;

const bulkDeleteSummary = computed(() => {
  const plans = bulkDeletePlans.value;
  const deletable = plans.filter((plan) => planJobCount(plan) === 0);
  return {
    deletable,
    blocked: plans.filter((plan) => planJobCount(plan) > 0),
    movements: deletable.reduce(
      (sum, plan) => sum + (plan.movements?.length ?? plan.movements_count ?? 0),
      0
    ),
  };
});

// Mirrors PlanManagementController::destroyBulk: typing is only demanded when movements would be lost.
const canConfirmBulkDelete = computed(
  () =>
    bulkDeleteSummary.value.deletable.length > 0 &&
    (bulkDeleteSummary.value.movements === 0 || bulkDeleteConfirm.value.trim() === "DELETE")
);

function openBulkDeletePlans(plans) {
  bulkDeletePlans.value = plans;
  bulkDeleteConfirm.value = "";
  bulkDeleteError.value = "";
  showBulkDeletePlans.value = true;
}

function cancelBulkDeletePlans() {
  if (!bulkDeleteProcessing.value) showBulkDeletePlans.value = false;
}

function confirmBulkDeletePlans() {
  if (!canConfirmBulkDelete.value) return;

  const ids = bulkDeleteSummary.value.deletable.map((plan) => plan.id);
  bulkDeleteProcessing.value = true;

  router.delete("/plans/bulk-delete", {
    data: { ids, confirm: bulkDeleteConfirm.value.trim() },
    preserveScroll: true,
    onSuccess: () => {
      showBulkDeletePlans.value = false;
      selectedPlanIds.value = new Set();
      if (ids.includes(activePlan.value)) {
        activePlan.value = null;
        activeTab.value = "plans";
      }
    },
    onError: (errors) => {
      bulkDeleteError.value = errors.confirm || "Failed to delete the selected plans.";
    },
    onFinish: () => {
      bulkDeleteProcessing.value = false;
    },
  });
}

// The movement open in the Edit Movement dialog.
const editingMovement = ref(null);

// Delete movement state
const showDeleteMovementConfirmation = ref(false);
const deletingMovement = ref(null);
const deleteMovementProcessing = ref(false);

// Bulk delete movements state
const selectedMovementIds = ref(new Set());
const showBulkDeleteMovementsConfirmation = ref(false);
const bulkDeletingMovements = ref(false);

// Movements with a job can't be deleted (deleteMovementsBulk skips them), so the dialog lists them instead.
const bulkMovementDelete = computed(() => {
  const selected = selectedPlanMovements.value.filter((mv) => selectedMovementIds.value.has(mv.id));
  return {
    deletable: selected.filter((mv) => !mv.job_id),
    withJobs: selected.filter((mv) => mv.job_id),
  };
});

// Selected movement for detail panel
const selectedMovement = ref(null);

// Use database plans
const allPlans = computed(() => props.plans || []);

// Initialize activePlan from backend prop (session-based, shared with Jobs page)
const activePlan = ref(props.activePlan);


// Watch for changes to activePlan and persist to backend session
watch(activePlan, (newValue) => {
  selectedMovementIds.value = new Set();

  // Send to backend session (shared with Jobs page)
  // Always persist, including null for "All Movements (Event-wide)"
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
});


// Watch for flash messages from backend
const page = usePage();
// Not deep: a partial reload keeps the old flash object, and a deep watcher would toast it again.
watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      showSuccessToast(flash.success);
    }
    if (flash?.error) {
      showErrorToast(flash.error);
    }
  }
);

const showPlanDropdown = ref(false);
const planDropdownView = ref('list'); // 'list' or 'grid'
const planSearchTerm = ref("");
const selectedTeam = ref("");

// Team filter for movements table in By Plan view
const movementsTeamFilter = ref(null);
const movementsDateFilter = ref(null);
const movementsPhaseFilter = ref(null);
const movementsJobFilter = ref(null); // null = all, 'ready', 'not-ready'

const phaseLabels = {
  arrival: 'Arrival',
  departure: 'Departure',
  transfer: 'Transfer',
  match: 'Match',
  training: 'Training',
  daily_ops: 'Daily Ops',
};

function movementPhase(mv) {
  return mv.match_id ? 'match' : mv.kind;
}
const teamMovementKindFilter = ref(null); // null = all, or 'arrival', 'departure', 'transfer', 'match', etc.

// Add Team to Plan modal state
const showAddTeamModal = ref(false);
const addTeamId = ref(null);
const addTeamTemplate = ref("");
const addTeamProcessing = ref(false);
const addTeamErrors = ref({});

// Initialize activeTab based on whether there's a valid stored plan
// If activePlan is explicitly null and we have plans, this means "All Movements" was selected
const activeTab = ref(
  props.activePlan || (props.activePlan === null && props.plans?.length > 0)
    ? "movements" 
    : "plans"
);

const showJobInfoModal = ref(false);
const showRefTimeInfoModal = ref(false);
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


const amTemplateObj = computed(
  () => props.movementTemplates.find((t) => t.id === amTemplate.value) ?? null
);

function formatDurationMinutes(total) {
  const h = Math.floor(total / 60);
  const m = total % 60;
  return h ? `${h}h${m ? ` ${m}m` : ''}` : `${m}m`;
}

function addMinutesToTime(base, offset) {
  const [h, m] = base.split(":").map(Number);
  const total = (h * 60 + m + offset) % (24 * 60);
  return `${String(Math.floor(total / 60)).padStart(2, "0")}:${String(
    total % 60
  ).padStart(2, "0")}`;
}

// Mirrors the server's expansion: each leg starts where the previous one ended.
const amTemplateLegs = computed(() => {
  const tpl = amTemplateObj.value;
  if (!tpl?.legs?.length) return [];

  const base = props.movementsByTeam?.reduce((sum, group) => sum + (group.items?.length || 0), 0) || 0;
  let cumulative = 0;

  return tpl.legs.map((leg, i) => {
    const duration = legMinutes(tpl, leg);
    const row = {
      phase: leg.leg_type ?? "transfer",
      from: leg.from_location ?? "—",
      to: leg.to_location ?? "—",
      durMin: duration,
      dep: addMinutesToTime(amBaseTime.value, cumulative),
      arr: addMinutesToTime(amBaseTime.value, cumulative + duration),
      idx: base + i + 1,
    };
    cumulative += duration;
    return row;
  });
});

// Checkpoint templates are filtered by movement type, so they reload with PHASE.
const amCheckpointTemplates = ref([]);
const amTemplatesLoading = ref(false);
const amCheckpointTemplate = ref("");
const amFunctionalArea = ref("");
const amError = ref("");
const amErrors = ref({});
const amProcessing = ref(false);

const amCheckpointTemplateObj = computed(
  () => amCheckpointTemplates.value.find((t) => t.id === amCheckpointTemplate.value) ?? null
);

const amCheckpointNames = computed(
  () => (amCheckpointTemplateObj.value?.checkpoints ?? []).map((c) => c.name)
);

async function loadCheckpointTemplates() {
  amTemplatesLoading.value = true;
  try {
    const { data } = await axios.get("/api/checkpoint-templates", {
      params: { type: amPhase.value },
    });
    amCheckpointTemplates.value = data.templates ?? [];
    if (!amCheckpointTemplates.value.some((t) => t.id === amCheckpointTemplate.value)) {
      amCheckpointTemplate.value = amCheckpointTemplates.value[0]?.id ?? "";
    }
  } catch (e) {
    amCheckpointTemplates.value = [];
    amError.value = "Could not load checkpoint templates.";
  } finally {
    amTemplatesLoading.value = false;
  }
}

watch([amPhase, showAddMovement], ([, open]) => {
  if (open) loadCheckpointTemplates();
});

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

// Candidate pool: the tick-box selection when the user has made one, else
// everything in view. Already-generated legs can never be candidates.
const genMovements = computed(() => {
  const pool = selectedMovementIds.value.size > 0
    ? selectedPlanMovements.value.filter((mv) => selectedMovementIds.value.has(mv.id))
    : selectedPlanMovements.value;

  return pool.filter((mv) => !mv.job_id);
});

const genAlreadyCount = computed(
  () => selectedPlanMovements.value.filter((mv) => mv.job_id).length
);

/** The subset that can actually be generated right now — fully crewed. */
const genReadyMovements = computed(() =>
  genMovements.value.filter((mv) => mv.field_supervisor_id && mv.vehicle_id)
);

/** Ready movements bucketed by plan — generation is a per-plan endpoint. */
const genPlanGroups = computed(() => {
  const groups = new Map();
  for (const mv of genReadyMovements.value) {
    if (!mv.plan_id) continue;
    if (!groups.has(mv.plan_id)) groups.set(mv.plan_id, []);
    groups.get(mv.plan_id).push(mv.id);
  }
  return groups;
});

// Partially-ready batches are allowed: incomplete movements are skipped (with a
// confirmation) rather than blocking the whole run.
const genCanGenerate = computed(() => genReadyMovements.value.length > 0);

const showGenerateSkipConfirm = ref(false);
const genSkipMessage = ref('');
const showGenerateBlocked = ref(false);
const genBlockedMessage = ref('');

const genCanGenerateTooltip = computed(() => {
  const scope = selectedMovementIds.value.size > 0 ? 'selected movement(s)' : 'movement(s)';

  if (genMovements.value.length === 0) {
    return `No ${scope} awaiting job generation`;
  }

  const missingSupervisor = genMovements.value.filter(mv => !mv.field_supervisor_id).length;
  const missingVehicle = genMovements.value.filter(mv => !mv.vehicle_id).length;

  if (genReadyMovements.value.length === 0) {
    if (missingSupervisor > 0 && missingVehicle > 0) {
      return `${missingSupervisor} ${scope} missing supervisor, ${missingVehicle} missing vehicle`;
    }
    if (missingSupervisor > 0) {
      return `${missingSupervisor} ${scope} missing supervisor`;
    }
    if (missingVehicle > 0) {
      return `${missingVehicle} ${scope} missing vehicle`;
    }
  }

  const planCount = genPlanGroups.value.size;
  const across = planCount > 1 ? ` across ${planCount} plans` : '';
  const skipped = genMovements.value.length - genReadyMovements.value.length;

  return `Generate ${genReadyMovements.value.length} job(s)${across}`
    + (skipped > 0 ? ` — ${skipped} incomplete movement(s) will be skipped` : '');
});

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
    {
      id: "conflicts",
      label: "Conflicts",
      count: openConflicts.value.length,
      danger: openConflicts.value.some((c) => c.sev === "high"),
    },
  ];

  // A selected plan shows its movements and conflicts; otherwise only the Plans tab.
  if (activePlan.value) {
    return allTabs.filter((tab) => tab.id !== "plans");
  }

  return allTabs.filter((tab) => tab.id === "plans");
});

const conflicts = computed(() => props.conflicts);
// Accepted conflicts stay listed but no longer count or mark rows.
const openConflicts = computed(() => conflicts.value.filter((c) => !c.accepted));
const resolvingConflict = ref(null);
// The Conflicts tab of an open plan: its own conflicts or the whole event's.
const conflictScope = ref("event");
const canManagePlans = computed(() => page.props.auth?.can?.["plans.manage"] === true);

function openPlanConflicts() {
  conflictScope.value = "plan";
  activeTab.value = "conflicts";
}

// Same path as the movements tab's Generate jobs button, over the whole plan.
function generateFromChecklist() {
  activeTab.value = "movements";
  selectedMovementIds.value = new Set();
  generateJobs();
}

// movement id -> the conflicts it's part of
const conflictsByMovement = computed(() => {
  const map = new Map();
  for (const c of openConflicts.value) {
    for (const id of c.movement_ids || []) {
      if (!map.has(id)) map.set(id, []);
      map.get(id).push(c);
    }
  }
  return map;
});

function worstSeverity(movementId) {
  const sevs = (conflictsByMovement.value.get(movementId) || []).map((c) => c.sev);
  return ["high", "medium", "low"].find((s) => sevs.includes(s)) || "low";
}

// Set by a conflict's View button: just the movements that conflict involves.
const movementsFocusIds = ref(null);
const movementsFocusLabel = ref("");

function viewConflict(c) {
  activePlan.value = null;
  movementsTeamFilter.value = null;
  movementsDateFilter.value = null;
  movementsPhaseFilter.value = null;
  movementsJobFilter.value = null;
  movementColumns.clearAll();
  movementsFocusIds.value = new Set(c.movement_ids || []);
  movementsFocusLabel.value = `${c.type} · ${(c.affects || []).join(", ")}`;
  activeTab.value = "movements";
}

function clearConflictFocus() {
  movementsFocusIds.value = null;
  movementsFocusLabel.value = "";
}

const teamGroups = computed(() => {
  // Use database data
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

  return [];
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
  // Get pax from the movement's flight data first, then fallback to movement data
  if (!group.items || group.items.length === 0) {
    return 0;
  }
  
  // Try to find arrival movement first
  const arrivalMovement = group.items.find(mv => mv.kind === 'arrival');
  if (arrivalMovement) {
    // Check flight party_size_total first
    if (arrivalMovement.flight?.party_size_total) {
      return arrivalMovement.flight.party_size_total;
    }
    if (arrivalMovement.pax || arrivalMovement.passengers) {
      return arrivalMovement.pax ?? arrivalMovement.passengers ?? 0;
    }
  }
  
  // Fallback to first movement with pax data (check flight first)
  const movementWithPax = group.items.find(mv => mv.flight?.party_size_total || mv.pax || mv.passengers);
  if (movementWithPax) {
    return movementWithPax.flight?.party_size_total ?? movementWithPax.pax ?? movementWithPax.passengers ?? 0;
  }
  
  return 0;
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
  if (!location || !airportCode) return location || "—";

  // Check if location already contains the airport code
  if (location.toUpperCase().includes(airportCode.toUpperCase())) {
    return location;
  }
  
  // Add airport code in parentheses
  return `${location} (${airportCode})`;
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

function formatLocationWithVenue(location, venueName) {
  if (!location || !venueName) return location || "—";

  // Check if location already contains the venue name
  if (location.toLowerCase().includes(venueName.toLowerCase())) {
    return location;
  }

  const venueTerms = ["stadium", "venue", "ground", "arena"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a venue reference
  if (venueTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${venueName})`;
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
  const locationLower = location.toLowerCase();

  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    let airportCode = null;
    
    // For arrival movements, use destination airport (where arriving)
    if (mv.kind === "arrival") {
      airportCode = mv.flight?.destination_airport || 
                   selectedTeamObj.value.destination_airport;
    }
    // For departure movements, use origin airport (where leaving from)
    else if (mv.kind === "departure") {
      airportCode = mv.flight?.origin_airport || 
                   selectedTeamObj.value.origin_airport;
    }
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode, mv.kind);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = mv.accommodation?.hotel_name || 
                     selectedTeamObj.value.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    // Get venue from movement's match relationship
    const venueName = mv.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  // 4. Training ground locations
  else if (locationLower.includes('training')) {
    if (selectedTeamObj.value.training_ground) {
      location = formatLocationWithTrainingGround(
        location,
        selectedTeamObj.value.training_ground
      );
    }
  }

  return location;
}

function movementToLocation(mv) {
  if (!selectedTeamObj.value) return mv.to || "—";

  let location = mv.to || "—";
  const locationLower = location.toLowerCase();

  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    // For both arrival and departure, to_location typically uses destination airport
    const airportCode = mv.flight?.destination_airport || 
                       selectedTeamObj.value.destination_airport;
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode, mv.kind);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = mv.accommodation?.hotel_name || 
                     selectedTeamObj.value.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    // Get venue from movement's match relationship
    const venueName = mv.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  // 4. Training ground locations
  else if (locationLower.includes('training')) {
    if (selectedTeamObj.value.training_ground) {
      location = formatLocationWithTrainingGround(
        location,
        selectedTeamObj.value.training_ground
      );
    }
  }

  return location;
}

// Format from location for movements table (By Plan view)
function formatMovementFromLocation(mv) {
  if (!mv) return "—";
  
  let location = mv.from_location || "—";
  const locationLower = location.toLowerCase();
  
  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    let airportCode = null;
    
    // For arrival movements, use destination airport
    if (mv.kind === "arrival") {
      airportCode = mv.flight?.destination_airport?.code || 
                   mv.flight?.destination_airport ||
                   mv.team?.destination_airport?.code || 
                   mv.team?.destination_airport;
    }
    // For departure movements, use origin airport
    else if (mv.kind === "departure") {
      airportCode = mv.flight?.origin_airport?.code || 
                   mv.flight?.origin_airport ||
                   mv.team?.origin_airport?.code || 
                   mv.team?.origin_airport;
    }
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode, mv.kind);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = mv.accommodation?.hotel_name || 
                     mv.team?.hotel_name || 
                     props.teams?.find(t => t.id === mv.team_id)?.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    const venueName = mv.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  
  return location;
}

// Format to location for movements table (By Plan view)
function formatMovementToLocation(mv) {
  if (!mv) return "—";
  
  let location = mv.to_location || "—";
  const locationLower = location.toLowerCase();
  
  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    let airportCode = null;
    
    // For both arrival and departure movements, to_location typically uses destination airport
    airportCode = mv.flight?.destination_airport?.code || 
                 mv.flight?.destination_airport ||
                 mv.team?.destination_airport?.code || 
                 mv.team?.destination_airport;
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode, mv.kind);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = mv.accommodation?.hotel_name || 
                     mv.team?.hotel_name || 
                     props.teams?.find(t => t.id === mv.team_id)?.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    const venueName = mv.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
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
// The plans list ("View All Plans"), as opposed to the event-wide movements view.
const inAllPlansView = computed(() => !selectedPlanObj.value && activeTab.value === 'plans');
const allPlanOptions = computed(() => [
  { id: 'plans', label: 'All plans', sub: `The list of the event's ${allPlans.value.length} plans`, active: inAllPlansView.value, select: viewAllPlans },
  { id: 'movements', label: 'All movements', sub: 'Every movement of every plan', active: activePlan.value === null && !inAllPlansView.value, select: selectAllMovements },
]);
const selectedPlanMovements = computed(() => {
  // If no plan is selected, show all movements from all plans
  if (!activePlan.value) {
    return allPlans.value.flatMap(plan => 
      (plan.movements || []).map(mv => ({
        ...mv,
        plan_code: plan.code,
        plan_name: plan.name
      }))
    );
  }
  // Otherwise show movements from selected plan
  return selectedPlanObj.value?.movements ?? [];
});

// Total passengers in the selected plan
const totalPassengers = computed(() => {
  return selectedPlanMovements.value.reduce((sum, mv) => sum + (mv.passengers || 0), 0);
});

// Filtered movements based on team and date filters, before any header filter
const prefilteredPlanMovements = computed(() => {
  let movements = selectedPlanMovements.value;

  if (movementsFocusIds.value) {
    movements = movements.filter((mv) => movementsFocusIds.value.has(mv.id));
  }
  
  // Apply team filter
  if (movementsTeamFilter.value) {
    movements = movements.filter((mv) => mv.team_id === movementsTeamFilter.value);
  }
  
  // Apply date filter
  if (movementsDateFilter.value) {
    movements = movements.filter((mv) => {
      if (!mv.window_start) return false;
      const mvDate = formatDate(mv.window_start);
      return mvDate === movementsDateFilter.value;
    });
  }

  // Apply phase filter
  if (movementsPhaseFilter.value) {
    movements = movements.filter((mv) => movementPhase(mv) === movementsPhaseFilter.value);
  }

  // Apply job-generation filter
  if (movementsJobFilter.value === 'conflicts') {
    movements = movements.filter((mv) => conflictsByMovement.value.has(mv.id));
  } else if (movementsJobFilter.value === 'generated') {
    movements = movements.filter((mv) => mv.job_id);
  } else if (movementsJobFilter.value) {
    const wantReady = movementsJobFilter.value === 'ready';
    movements = movements.filter((mv) => !mv.job_id && isReadyForGeneration(mv) === wantReady);
  }

  // Sort by date and time (asc, untimed last), then phase
  movements = [...movements].sort((a, b) => {
    const aTime = a.window_start ? new Date(a.window_start).getTime() : Infinity;
    const bTime = b.window_start ? new Date(b.window_start).getTime() : Infinity;
    if (aTime !== bTime) return aTime - bTime;

    const aPhase = phaseLabels[movementPhase(a)] || movementPhase(a) || '';
    const bPhase = phaseLabels[movementPhase(b)] || movementPhase(b) || '';
    return aPhase.localeCompare(bPhase);
  });

  return movements;
});

// Raw 'YYYY-MM-DD HH:MM' so sorting follows the wall-clock times the table shows.
const wallClock = (value) => (value ? String(value).replace('T', ' ').slice(0, 16) : null);

function movementRefTime(mv) {
  if ((mv.kind === 'arrival' || mv.kind === 'departure') && mv.flight?.scheduled_at) return wallClock(mv.flight.scheduled_at);
  if (mv.match_id && mv.match?.kick_off) return wallClock(mv.match.kick_off);
  return null;
}

const dateSortLabels = { asc: 'Earliest first', desc: 'Latest first' };
const numberSortLabels = { asc: 'Smallest first', desc: 'Largest first' };
const planOf = (mv) => mv.plan_id;

// "COACH 05 / Ali, TRUCK 02 / Omar" for a movement's extra vehicle + driver pairs.
function unitsLabel(mv) {
  return (mv.units || [])
    .map((u) => `${u.vehicle?.code || u.vehicle?.vehicle_type || 'No vehicle'} / ${u.driver?.name || 'No driver'}`)
    .join(', ');
}

// Select, ID, Date, Phase, Ref Time, Team & Route, Window, Vehicle, Pax, Checks, Job, Actions.
const movementGridColumns =
  '28px minmax(120px, 5fr) minmax(90px, 7fr) minmax(90px, 6fr) minmax(70px, 5.5fr) minmax(190px, 14fr) minmax(110px, 9fr) minmax(80px, 7fr) minmax(44px, 4fr) minmax(50px, 4.5fr) minmax(120px, 7fr) minmax(76px, 7fr)';

const movementColumns = useColumnFilters({
  id: { value: (mv) => mv.code || '' },
  date: { value: (mv) => (mv.window_start ? formatDate(mv.window_start) : ''), sort: (mv) => wallClock(mv.window_start) },
  phase: { value: (mv) => phaseLabels[movementPhase(mv)] || movementPhase(mv) || '' },
  ref: { sort: movementRefTime },
  team: { value: (mv) => mv.team?.team_name || '' },
  window: { sort: (mv) => wallClock(mv.window_start) },
  vehicle: { value: (mv) => mv.vehicle?.code || '' },
  pax: { sort: (mv) => Number(mv.flight?.party_size_total ?? mv.pax ?? mv.passengers) || null },
  job: { value: (mv) => (mv.job_id ? 'Generated' : 'Not generated') },
});
const movementColumnsActive = movementColumns.active;

const filteredPlanMovements = computed(() => movementColumns.apply(prefilteredPlanMovements.value));

// Bulk-selection over the currently filtered movements (By Plan view)
const allMovementsSelected = computed(() => {
  return (
    filteredPlanMovements.value.length > 0 &&
    filteredPlanMovements.value.every((mv) => selectedMovementIds.value.has(mv.id))
  );
});

function toggleSelectAllMovements() {
  if (allMovementsSelected.value) {
    selectedMovementIds.value = new Set();
  } else {
    selectedMovementIds.value = new Set(filteredPlanMovements.value.map((mv) => mv.id));
  }
}

function toggleMovementSelection(id) {
  const next = new Set(selectedMovementIds.value);
  if (next.has(id)) {
    next.delete(id);
  } else {
    next.add(id);
  }
  selectedMovementIds.value = next;
}

// ---- By Day view: one operational day at a time, across every plan in scope ----
const dayViewDate = ref(null); // 'YYYY-MM-DD'
const dayViewPhaseFilter = ref(null);
const dayViewAttentionOnly = ref(false);
const dayStripEl = ref(null);

// Read as text, like formatTime/formatWindow, so no timezone shift moves a movement to another day.
function movementDayKey(mv) {
  return mv.window_start ? String(mv.window_start).slice(0, 10) : null;
}

function missingCrew(mv) {
  if (mv.job_id) return [];
  return [
    !mv.vehicle_id && "vehicle",
    !mv.driver_id && "driver",
    !mv.field_supervisor_id && "supervisor",
  ].filter(Boolean);
}

function needsAttention(mv) {
  return missingCrew(mv).length > 0 || conflictsByMovement.value.has(mv.id);
}

const dayViewToday = (() => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
})();

const dayViewDays = computed(() => {
  const days = new Map();
  for (const mv of selectedPlanMovements.value) {
    const key = movementDayKey(mv);
    if (!key) continue;
    if (!days.has(key)) days.set(key, { key, count: 0, attention: 0 });
    const day = days.get(key);
    day.count++;
    if (needsAttention(mv)) day.attention++;
  }
  return [...days.values()].sort((a, b) => a.key.localeCompare(b.key));
});

const dayViewUntimedCount = computed(
  () => selectedPlanMovements.value.filter((mv) => !mv.window_start).length
);

// Keep the chosen day if it still has movements; otherwise today, the next upcoming day, or the last one.
watch(
  dayViewDays,
  (days) => {
    if (days.some((d) => d.key === dayViewDate.value)) return;
    dayViewDate.value =
      (days.find((d) => d.key >= dayViewToday) ?? days[days.length - 1])?.key ?? null;
  },
  { immediate: true }
);

watch([dayViewDate, view], () => {
  dayViewPhaseFilter.value = null;
  if (selectedMovement.value && movementDayKey(selectedMovement.value) !== dayViewDate.value) {
    selectedMovement.value = null;
  }
  nextTick(() => {
    const strip = dayStripEl.value;
    const active = strip?.querySelector(".dv-day--active");
    if (strip && active) {
      strip.scrollLeft = active.offsetLeft - strip.clientWidth / 2 + active.clientWidth / 2;
    }
  });
});

const dayViewDayIndex = computed(() =>
  dayViewDays.value.findIndex((d) => d.key === dayViewDate.value)
);

function stepDayView(delta) {
  const next = dayViewDays.value[dayViewDayIndex.value + delta];
  if (next) dayViewDate.value = next.key;
}

function dayLabel(key) {
  const d = new Date(`${key}T00:00:00`);
  return {
    dow: d.toLocaleDateString("en-GB", { weekday: "short" }),
    day: d.getDate(),
    month: d.toLocaleDateString("en-GB", { month: "short" }),
  };
}

const dayViewMovements = computed(() =>
  selectedPlanMovements.value
    .filter((mv) => movementDayKey(mv) === dayViewDate.value)
    .sort((a, b) => String(a.window_start).localeCompare(String(b.window_start)))
);

const dayViewFiltered = computed(() =>
  dayViewMovements.value.filter(
    (mv) =>
      (!dayViewPhaseFilter.value || movementPhase(mv) === dayViewPhaseFilter.value) &&
      (!dayViewAttentionOnly.value || needsAttention(mv))
  )
);

const dayViewPhases = computed(() => {
  const counts = new Map();
  for (const mv of dayViewMovements.value) {
    const phase = movementPhase(mv);
    if (phase) counts.set(phase, (counts.get(phase) || 0) + 1);
  }
  return [...counts].map(([phase, count]) => ({ phase, count }));
});

const dayViewStats = computed(() => {
  const mvs = dayViewMovements.value;
  return {
    movements: mvs.length,
    teams: new Set(mvs.map((mv) => mv.team_id).filter(Boolean)).size,
    pax: mvs.reduce((sum, mv) => sum + (Number(mv.flight?.party_size_total ?? mv.passengers) || 0), 0),
    needsCrew: mvs.filter((mv) => missingCrew(mv).length > 0).length,
    conflicts: mvs.filter((mv) => conflictsByMovement.value.has(mv.id)).length,
    jobs: mvs.filter((mv) => mv.job_id).length,
  };
});

const dayViewHourGroups = computed(() => {
  const groups = [];
  for (const mv of dayViewFiltered.value) {
    const hour = formatTime(mv.window_start).slice(0, 2);
    const last = groups[groups.length - 1];
    if (last?.hour === hour) last.items.push(mv);
    else groups.push({ hour, items: [mv] });
  }
  return groups;
});

function minutesOfDay(dateString) {
  const [h, m] = formatTime(dateString).split(":").map(Number);
  return h * 60 + m;
}

const dayViewTimeline = computed(() => {
  const spans = dayViewFiltered.value.map((mv) => {
    const start = minutesOfDay(mv.window_start);
    let end = mv.window_end ? minutesOfDay(mv.window_end) : start + 30;
    if (mv.window_end && String(mv.window_end).slice(0, 10) > movementDayKey(mv)) end += 1440;
    return { mv, start, end: Math.max(end, start + 15) };
  });
  if (spans.length === 0) return { hours: [], rows: [] };

  const from = Math.floor(Math.min(...spans.map((s) => s.start)) / 60) * 60;
  const to = Math.max(from + 240, Math.ceil(Math.max(...spans.map((s) => s.end)) / 60) * 60);
  const pct = (minutes) => ((minutes - from) / (to - from)) * 100;

  const rows = new Map();
  for (const s of spans) {
    const key = s.mv.team_id ?? "none";
    if (!rows.has(key)) rows.set(key, { key, team: s.mv.team, bars: [], laneEnds: [] });
    const row = rows.get(key);
    // Overlapping legs of the same team stack into separate lanes.
    let lane = row.laneEnds.findIndex((laneEnd) => laneEnd <= s.start);
    if (lane === -1) lane = row.laneEnds.length;
    row.laneEnds[lane] = s.end;
    row.bars.push({ mv: s.mv, lane, left: pct(s.start), width: pct(s.end) - pct(s.start) });
  }

  const hours = [];
  for (let t = from; t <= to; t += 60) {
    hours.push({ label: `${String((t / 60) % 24).padStart(2, "0")}:00`, left: pct(t) });
  }

  return {
    hours,
    rows: [...rows.values()]
      .map((row) => ({ ...row, lanes: row.laneEnds.length }))
      .sort((a, b) => (a.team?.team_name || "").localeCompare(b.team?.team_name || "")),
  };
});

function openPlanFromDay(mv) {
  selectPlan(mv.plan_id);
  movementsDateFilter.value = formatDate(mv.window_start);
  view.value = "plan";
}

// Get unique teams that have movements in the current plan
const teamsInCurrentPlan = computed(() => {
  const teamMap = new Map();
  selectedPlanMovements.value.forEach((mv) => {
    if (mv.team) {
      teamMap.set(mv.team.id, mv.team);
    }
  });
  const label = (team) => team.team_name || team.team || "";
  return Array.from(teamMap.values()).sort((a, b) =>
    label(a).localeCompare(label(b), undefined, { sensitivity: "base" })
  );
});

// Get unique dates from movements in the current plan
const datesInCurrentPlan = computed(() => {
  const dateMap = new Map();
  selectedPlanMovements.value.forEach((mv) => {
    if (mv.window_start) {
      const formattedDate = formatDate(mv.window_start);
      if (formattedDate && !dateMap.has(formattedDate)) {
        dateMap.set(formattedDate, new Date(mv.window_start));
      }
    }
  });
  // Sort by actual date value (ascending)
  return Array.from(dateMap.entries())
    .sort((a, b) => a[1] - b[1])
    .map(entry => entry[0]);
});

// Get unique phases (kinds) present among movements in the current plan
const phasesInCurrentPlan = computed(() => {
  const phases = new Set();
  selectedPlanMovements.value.forEach((mv) => {
    const phase = movementPhase(mv);
    if (phase) phases.add(phase);
  });
  return Array.from(phases);
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

function phaseMovementCount(phase) {
  return selectedPlanMovements.value.filter((mv) => movementPhase(mv) === phase)
    .length;
}

function dateMovementCount(date) {
  return selectedPlanMovements.value.filter((mv) => mv.window_start && formatDate(mv.window_start) === date)
    .length;
}

// Ready = not already generated
// and fully crewed — vehicle, driver and supervisor all assigned.
function isReadyForGeneration(mv) {
  return (
    !mv.job_id &&
    Boolean(mv.vehicle_id) &&
    Boolean(mv.driver_id) &&
    Boolean(mv.field_supervisor_id)
  );
}

const readyForGenerationCount = computed(
  () => selectedPlanMovements.value.filter(isReadyForGeneration).length
);

const generatedCount = computed(
  () => selectedPlanMovements.value.filter((mv) => mv.job_id).length
);

const conflictedMovementCount = computed(
  () => selectedPlanMovements.value.filter((mv) => conflictsByMovement.value.has(mv.id)).length
);

// Generated movements have their own option, so they aren't "not ready".
const notReadyForGenerationCount = computed(
  () => selectedPlanMovements.value.length - readyForGenerationCount.value - generatedCount.value
);


function matchLineup(match) {
  const side = (team) => team?.code || team?.team_name || "TBD";
  return `${side(match?.team1)} vs ${side(match?.team2)}`;
}

// Phase column lines under the badge: what it's for, then who or where.
function phaseLines(mv) {
  if (mv.match_id) {
    return [mv.match?.match_number, mv.match ? matchLineup(mv.match) : null].filter(Boolean);
  }
  if ((mv.kind === "arrival" || mv.kind === "departure") && mv.flight) {
    const code = (a) => a?.code || a?.iata || (typeof a === "string" ? a : null);
    const route = [code(mv.flight.origin_airport), code(mv.flight.destination_airport)].filter(Boolean).join(" → ");
    return [mv.flight.flight_number, route].filter(Boolean);
  }
  return [];
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



function isBusMovement(mv) {
  return (
    (mv.kind === "arrival" || mv.kind === "departure") &&
    mv.flight?.flight_number === "BUS"
  );
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

// "12:30 – 14:30": the first checkpoint time, then that plus the movement's duration.
function formatWindow(mv) {
  if (!mv?.window_start) return "—";
  if (!mv.window_end) return formatTime(mv.window_start);
  // Compared as text, like formatTime, so no timezone shift creeps in.
  const nextDay = mv.window_end.slice(0, 10) > mv.window_start.slice(0, 10);
  return `${formatTime(mv.window_start)} – ${formatTime(mv.window_end)}${nextDay ? " +1" : ""}`;
}


function selectPlan(planId) {
  activePlan.value = planId;
  planSearchTerm.value = "";
  showPlanDropdown.value = false;
  // Switch to Movements tab when selecting a plan
  activeTab.value = "movements";
}

// Every plan's movements in one table (each row tagged with its plan code).
function selectAllMovements() {
  activePlan.value = null;
  planSearchTerm.value = "";
  showPlanDropdown.value = false;
  activeTab.value = "movements";
}

function viewAllPlans() {
  activePlan.value = null;
  view.value = "plan"; // Switch to plan view so tabs are visible
  activeTab.value = "plans";
  showPlanDropdown.value = false;
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
  deleteStep.value = "confirm";
  deleteCheck.value = { movements_count: 0, jobs_count: 0 };
  deleteConfirmName.value = "";
  deletePlanError.value = "";
  showDeleteConfirmation.value = true;
}

async function checkPlanBeforeDelete() {
  if (!deletingPlan.value) return;

  deleteStep.value = "checking";
  deletePlanError.value = "";
  try {
    const { data } = await axios.get(
      `/plans/${deletingPlan.value.id}/delete-check`
    );
    deleteCheck.value = {
      movements_count: data.movements_count || 0,
      jobs_count: data.jobs_count || 0,
    };
    deleteStep.value = "verify";
    nextTick(() => document.getElementById("delete-plan-confirm-name")?.focus());
  } catch (e) {
    deleteStep.value = "confirm";
    deletePlanError.value = "Could not check the plan's movements. Please try again.";
  }
}

function confirmDeletePlan() {
  if (!deletingPlan.value || !deleteNameMatches.value) return;

  deletePlanProcessing.value = true;
  deletePlanError.value = "";
  const deletedPlanId = deletingPlan.value.id; // Store ID before clearing

  router.delete(`/plans/${deletedPlanId}`, {
    data: { confirm_name: deleteConfirmName.value },
    preserveScroll: true,
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
      deletePlanError.value =
        errors.confirm_name || "Failed to delete plan.";
    },
    onFinish: () => {
      deletePlanProcessing.value = false;
    },
  });
}

function cancelDeletePlan() {
  showDeleteConfirmation.value = false;
  deletingPlan.value = null;
  deleteConfirmName.value = "";
  deletePlanError.value = "";
}

// "By Team" data isn't sent on the initial page load anymore — it's a
// separate, expensive query+transform over every movement, and "By Plan"
// is the default view most sessions never leave (see
// PlanManagementController@index, movementsByTeam wrapped in
// Inertia::optional()). Fetched live via a partial reload each time the
// user actually switches to this tab, so it can't go stale either.
const movementsByTeamLoading = ref(false);

function switchView(newView) {
  view.value = newView;
  selectedMovement.value = null; // Clear selected movement when switching views

  if (newView === "team") {
    movementsByTeamLoading.value = true;
    router.reload({
      only: ["movementsByTeam"],
      onFinish: () => {
        movementsByTeamLoading.value = false;
      },
    });
  }
}

async function selectMovement(movement) {
  // Toggle: if clicking the same movement, deselect it
  if (selectedMovement.value?.id === movement.id) {
    selectedMovement.value = null;
    return;
  }

  selectedMovement.value = movement;
  await fetchMovementCheckpoints(movement);
}

// Checkpoint detail (state, times, baggage, live estimate) isn't sent for
// every movement on page load anymore — only a cheap total/completed count
// is (see PlanManagementController@index). Fetched fresh from the database
// each time a movement is selected, so it always reflects the latest state
// even if it was just completed from the mobile app.
const checkpointsLoading = ref(false);

async function fetchMovementCheckpoints(movement) {
  checkpointsLoading.value = true;
  try {
    const response = await fetch(`/movements/${movement.id}/checkpoints`, {
      headers: { Accept: "application/json" },
    });
    if (!response.ok) throw new Error("Failed to load checkpoints");
    const data = await response.json();
    movement.checkpoints = data.checkpoints;
    movement.checkpoint_template = data.checkpoint_template;
  } catch (error) {
    console.error("Failed to fetch checkpoints:", error);
  } finally {
    checkpointsLoading.value = false;
  }
}

function editMovement(movement) {
  editingMovement.value = movement;
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

function confirmDeleteSelectedMovements() {
  const ids = bulkMovementDelete.value.deletable.map((mv) => mv.id);
  if (ids.length === 0) return;

  bulkDeletingMovements.value = true;

  router.delete("/movements/bulk-delete", {
    data: { ids },
    preserveScroll: true,
    onSuccess: () => {
      selectedMovementIds.value = new Set();
      showBulkDeleteMovementsConfirmation.value = false;
    },
    onError: () => {
      showErrorToast("Failed to delete movements");
    },
    onFinish: () => {
      bulkDeletingMovements.value = false;
    },
  });
}

function cancelBulkDeleteMovements() {
  showBulkDeleteMovementsConfirmation.value = false;
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
  amTemplate.value = props.movementTemplates[0]?.id ?? "";
  amFunctionalArea.value = "";
  amCheckpointTemplate.value = "";
  amError.value = "";
  amErrors.value = {};
  amProcessing.value = false;
  amDate.value = (selectedPlanObj.value?.date ?? "").slice(0, 10);
  amBaseTime.value = "14:00";
  showAddMovement.value = true;
}

/** Plan date + a HH:mm field, as the datetime the API expects. */
function planDateTime(time) {
  const date = (selectedPlanObj.value?.date ?? "").slice(0, 10);
  return date && time ? `${date} ${time}:00` : null;
}

function saveMovement(andAnother = false) {
  if (amProcessing.value) return;

  if (!selectedPlanObj.value) {
    amError.value = "Select a plan first.";
    return;
  }

  amError.value = "";
  amErrors.value = {};
  amProcessing.value = true;

  const payload = amMode.value === "template"
    ? {
        team_id: amTeam.value || null,
        movement_template_id: amTemplate.value || null,
        base_time: amBaseTime.value || null,
      }
    : {
        team_id: amTeam.value || null,
        checkpoint_template_id: amCheckpointTemplate.value || null,
        kind: amPhase.value,
        functional_area: amFunctionalArea.value || null,
        from_location: amFrom.value,
        to_location: amTo.value,
        window_start: planDateTime(amStart.value),
        window_end: planDateTime(amEnd.value),
        vehicle_id: amVehicle.value || null,
        passengers: amPassengers.value === "" ? 0 : Number(amPassengers.value),
      };

  router.post(`/plans/${selectedPlanObj.value.id}/movements`, payload, {
    preserveScroll: true,
    onSuccess: () => {
      if (andAnother) {
        amFrom.value = "";
        amTo.value = "";
        amPassengers.value = "";
      } else {
        showAddMovement.value = false;
      }
    },
    onError: (errors) => {
      amErrors.value = errors;
      amError.value = Object.values(errors)[0] ?? "Could not add the movement.";
    },
    onFinish: () => {
      amProcessing.value = false;
    },
  });
}

function generateJobs() {
  const validMovements = genReadyMovements.value;
  const missingSupervisor = genMovements.value.filter(mv => !mv.field_supervisor_id).length;
  const missingVehicle = genMovements.value.filter(mv => !mv.vehicle_id).length;

  if (validMovements.length === 0) {
    const reasons = [];
    if (missingSupervisor > 0) reasons.push(`${missingSupervisor} movement(s) missing a supervisor`);
    if (missingVehicle > 0) reasons.push(`${missingVehicle} movement(s) missing a vehicle`);

    genBlockedMessage.value = reasons.length
      ? `No movements are ready for job generation:<br><br>${reasons.map(r => `• ${r}`).join('<br>')}`
        + '<br><br>Assign a supervisor and a vehicle, then try again.'
      : 'No movements are awaiting job generation.';
    showGenerateBlocked.value = true;
    return;
  }

  const skippedCount = genMovements.value.length - validMovements.length;
  if (skippedCount > 0) {
    const reasons = [];
    if (missingSupervisor > 0) reasons.push(`${missingSupervisor} without a supervisor`);
    if (missingVehicle > 0) reasons.push(`${missingVehicle} without a vehicle`);

    genSkipMessage.value =
      `<strong>${skippedCount}</strong> movement(s) will be skipped:<br><br>`
      + reasons.map(r => `• ${r}`).join('<br>')
      + `<br><br>Continue with <strong>${validMovements.length}</strong> movement(s)?`;
    showGenerateSkipConfirm.value = true;
    return;
  }

  openGenerateJobsModal();
}

/** Stages the ready movements and opens the generation options modal. */
function openGenerateJobsModal() {
  showGenerateSkipConfirm.value = false;
  genSelectedIds.value = genReadyMovements.value.map((mv) => mv.id);
  genTemplate.value = "TPL-ARR";
  genAutoAssign.value = true;
  genNotifyLiaisons.value = true;
  showGenerateJobs.value = true;
}

function toggleGenMovement(id) {
  const movement = genMovements.value.find((mv) => mv.id === id);
  if (movement && !isReadyForGeneration(movement)) {
    return;
  }

  const idx = genSelectedIds.value.indexOf(id);
  if (idx === -1) genSelectedIds.value.push(id);
  else genSelectedIds.value.splice(idx, 1);
}

function genRowBlockedReason(mv) {
  const missing = [];
  if (!mv.vehicle_id) missing.push('vehicle');
  if (!mv.field_supervisor_id) missing.push('supervisor');

  return missing.length
    ? `Assign a ${missing.join(' and a ')} before generating a job for this movement`
    : '';
}

// The generate-jobs endpoint is scoped to a single plan, so a selection that
// spans plans is submitted as one request per plan, chained so each starts only
// after the previous succeeds.
function confirmGenerateJobs() {
  const selected = new Set(genSelectedIds.value);
  const batches = [];

  for (const [planId, ids] of genPlanGroups.value) {
    const batch = ids.filter((id) => selected.has(id));
    if (batch.length > 0) {
      batches.push([planId, batch]);
    }
  }

  if (batches.length === 0) {
    genBlockedMessage.value = 'No movements are selected, or the selected movements are not attached to a plan.';
    showGenerateBlocked.value = true;
    return;
  }

  genProcessing.value = true;
  const count = batches.reduce((sum, [, ids]) => sum + ids.length, 0);

  const runBatch = (index) => {
    if (index >= batches.length) {
      const across = batches.length > 1 ? ` across ${batches.length} plans` : '';
      showSuccessToast(`Jobs generated successfully for ${count} movement${count !== 1 ? 's' : ''}${across}`);
      showGenerateJobs.value = false;
      genSelectedIds.value = [];
      selectedMovementIds.value = new Set();
      genProcessing.value = false;
      return;
    }

    const [planId, movementIds] = batches[index];

    router.post(
      `/plans/${planId}/generate-jobs`,
      {
        movement_ids: movementIds,
        auto_assign: genAutoAssign.value,
        notify_liaisons: genNotifyLiaisons.value,
      },
      {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => runBatch(index + 1),
        onError: (errors) => {
          console.error("Failed to generate jobs:", errors);
          showErrorToast(
            batches.length > 1
              ? `Failed on plan ${index + 1} of ${batches.length}. Earlier plans were generated.`
              : "Failed to generate jobs. Please try again."
          );
          genProcessing.value = false;
        },
      }
    );
  };

  runBatch(0);
}

function generateSingleJob(movement) {
  // Use movement's plan_id if in "All Movements" view, otherwise use selected plan
  const targetPlanId = movement?.plan_id || selectedPlanObj.value?.id;
  
  if (!targetPlanId) {
    console.error("No plan ID available");
    return;
  }

  if (!movement?.id) {
    console.error("Invalid movement");
    return;
  }

  if (!movement?.field_supervisor_id) {
    genBlockedMessage.value = 'Assign a <strong>supervisor</strong> to this movement before generating a job.';
    showGenerateBlocked.value = true;
    return;
  }

  if (!movement?.vehicle_id) {
    genBlockedMessage.value = 'Assign a <strong>vehicle</strong> to this movement before generating a job.';
    showGenerateBlocked.value = true;
    return;
  }

  generatingJobForMovement.value = movement.id;

  router.post(
    `/plans/${targetPlanId}/generate-jobs`,
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
.delete-approval-note {
  font-size: 13px; color: var(--ink2); background: var(--accent-soft);
  border-radius: 6px; padding: 10px 12px; margin-bottom: 12px;
}
.plan-dd-all {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  border-left: 3px solid transparent;
  cursor: pointer;
  transition: background 0.1s;
}
.plan-dd-all:hover { background: #f9fafb; }
.plan-dd-all--on, .plan-dd-all--on:hover { border-left-color: #3b82f6; background: #eff6ff; }
.plan-dd-all:focus-visible { outline: 2px solid #3b82f6; outline-offset: -2px; }
.phase-cell {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 2px;
  min-width: 0;
  overflow: hidden;
}
.phase-line {
  font-size: 10.5px;
  color: var(--ink3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100%;
}
.phase-line--ref {
  font-family: var(--mono);
  font-weight: 600;
  color: var(--ink2);
}

.conflict-marker {
  display: inline-block;
  margin-left: 4px;
  padding: 0 4px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  cursor: pointer;
}
.conflict-marker--high { background: var(--danger-soft); color: var(--danger); }
.conflict-marker--medium { background: var(--warn-soft); color: var(--warn); }
.conflict-marker--low { background: var(--panel); color: var(--ink3); }

/* By Day view */
.dv { display: flex; flex-direction: column; gap: 12px; }
.dv-muted { font-size: 11px; color: var(--ink3); }
.dv-card-title {
  font-size: 11px; font-weight: 700; color: var(--ink3);
  letter-spacing: 0.6px; text-transform: uppercase; margin-bottom: 10px;
}
.dv-strip {
  display: flex; align-items: stretch; gap: 6px;
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px; padding: 8px;
}
.dv-strip-nav {
  border: 1px solid var(--border); background: var(--surface); border-radius: 6px;
  width: 28px; font-size: 18px; color: var(--ink2); cursor: pointer; flex-shrink: 0;
}
.dv-strip-nav:disabled { opacity: 0.35; cursor: default; }
.dv-strip-days { display: flex; gap: 6px; overflow-x: auto; flex: 1; scroll-behavior: smooth; }
.dv-day {
  display: flex; flex-direction: column; align-items: center; gap: 1px;
  min-width: 58px; padding: 6px 8px; border-radius: 8px; flex-shrink: 0;
  border: 1px solid var(--border); background: var(--surface); cursor: pointer; color: var(--ink2);
}
.dv-day:hover { background: var(--panel); }
.dv-day--today { border-color: var(--accent); }
.dv-day--active, .dv-day--active:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
.dv-day-dow, .dv-day-mon { font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; }
.dv-day-num { font-size: 17px; font-weight: 700; line-height: 1.1; }
.dv-day-count { font-size: 10.5px; font-family: var(--mono); font-weight: 600; }
.dv-day-warn { color: var(--warn); }
.dv-day--active .dv-day-warn { color: #FDE68A; }
.dv-stats { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
@media (max-width: 1024px) { .dv-stats { grid-template-columns: repeat(3, 1fr); } }
.dv-filters { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.dv-chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 10px; border: 1px solid var(--border); border-radius: 6px;
  background: var(--surface); color: var(--ink3); font-size: 11px; font-weight: 600; cursor: pointer;
}
.dv-chip--active { background: var(--accent); border-color: var(--accent); color: #fff; }
.dv-dot { width: 8px; height: 8px; border-radius: 50%; }
.dv-toggle { display: inline-flex; align-items: center; gap: 6px; margin-left: 8px; font-size: 12px; color: var(--ink2); cursor: pointer; }

.dv-kind--arrival { background: #3B82F6; }
.dv-kind--departure { background: #8B5CF6; }
.dv-kind--transfer { background: #10B981; }
.dv-kind--match { background: #F59E0B; }
.dv-kind--training { background: #06B6D4; }
.dv-kind--daily_ops { background: #64748B; }
.dv-kind--undefined, .dv-kind--null { background: #94A3B8; }

.dv-timeline { padding: 14px 16px; }
.dv-tl-grid { display: grid; grid-template-columns: 180px 1fr; row-gap: 4px; }
.dv-tl-label {
  display: flex; align-items: center; gap: 6px; min-width: 0; padding-right: 8px;
  font-size: 12px; font-weight: 600; color: var(--ink);
}
.dv-tl-label span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-tl-axis { position: relative; height: 18px; border-bottom: 1px solid var(--border); }
.dv-tl-hour {
  position: absolute; transform: translateX(-50%);
  font-size: 10px; font-family: var(--mono); color: var(--ink3);
}
.dv-tl-track { position: relative; background: var(--panel); border-radius: 6px; }
.dv-tl-gridline { position: absolute; top: 0; bottom: 0; width: 1px; background: var(--border); }
.dv-tl-bar {
  position: absolute; height: 20px; min-width: 6px; padding: 0 5px;
  border: none; border-radius: 4px; color: #fff; cursor: pointer;
  font-size: 10px; font-family: var(--mono); font-weight: 700; text-align: left;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dv-tl-bar:hover { filter: brightness(1.1); z-index: 1; }
.dv-tl-bar--attention { box-shadow: 0 0 0 2px var(--danger); }

.dv-agenda { overflow: hidden; }
.dv-hour { display: grid; grid-template-columns: 64px 1fr; border-bottom: 1px solid var(--border); }
.dv-hour:last-child { border-bottom: none; }
.dv-hour-label {
  padding: 12px 10px; font-family: var(--mono); font-size: 12px; font-weight: 700;
  color: var(--ink3); background: var(--panel); border-right: 1px solid var(--border);
}
.dv-row {
  display: grid; grid-template-columns: 110px minmax(0, 1fr) minmax(0, 360px) 90px;
  gap: 12px; align-items: center; padding: 10px 14px;
  border-left: 3px solid transparent; cursor: pointer;
}
.dv-row + .dv-row { border-top: 1px solid var(--border); }
.dv-row:hover { background: var(--panel); }
.dv-row--attention { border-left-color: var(--warn); }
.dv-row-time { font-family: var(--mono); font-size: 12px; font-weight: 600; color: var(--ink); }
.dv-row-main { min-width: 0; display: flex; flex-direction: column; gap: 3px; }
.dv-row-main > .dv-muted { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-row-head { display: flex; align-items: center; gap: 6px; min-width: 0; }
.dv-row-team { font-size: 13px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dv-row-code { font-family: var(--mono); font-size: 11px; color: var(--ink3); }
.dv-row-plan { font-family: var(--mono); font-size: 10px; color: var(--accent); text-decoration: underline; cursor: pointer; }
.dv-row-crew { display: flex; flex-wrap: wrap; gap: 4px; }
.dv-crew {
  font-size: 11px; padding: 2px 6px; border-radius: 4px;
  background: var(--panel); color: var(--ink2); white-space: nowrap;
}
.dv-crew--missing { background: var(--warn-soft); color: var(--warn); font-weight: 600; }
.dv-row-job { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; }
.dv-checks { font-family: var(--mono); font-size: 11px; color: var(--ink3); }
.dv-row--selected, .dv-row--selected:hover { background: var(--accent-soft, #EEF0FE); }
.dv-tl-bar--selected { outline: 2px solid var(--ink); outline-offset: 1px; z-index: 2; }
.dv-missing-text { color: var(--warn); font-weight: 600; }

.dv-body { display: flex; gap: 12px; align-items: flex-start; }
.dv-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 12px; }
.movement-detail-panel.dv-panel { position: sticky; top: 12px; height: auto; max-height: calc(100vh - 120px); }
/* With the panel open it shows the full crew, so rows keep only what's missing. */
.dv-body--panel .dv-row { grid-template-columns: 100px minmax(0, 1fr) auto 80px; }
.dv-body--panel .dv-crew:not(.dv-crew--missing) { display: none; }
.dv-body--panel .dv-tl-grid { grid-template-columns: 130px 1fr; }
.dv-job-link { font-family: var(--mono); font-size: 11px; color: var(--accent); font-weight: 600; cursor: pointer; }

.conflict-focus-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  max-width: 320px;
  padding: 4px 8px;
  border-radius: 6px;
  background: var(--warn-soft);
  color: var(--ink2);
  font-size: 11.5px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.conflict-focus-chip button {
  border: none;
  background: none;
  cursor: pointer;
  color: var(--ink3);
  padding: 0;
}

.delete-blocked {
  font-size: 13px;
  color: #991b1b;
  background: #fee2e2;
  border: 1px solid #fca5a5;
  border-radius: 6px;
  padding: 10px 12px;
  margin-bottom: 12px;
}
.delete-blocked__list {
  margin: 6px 0 6px 18px;
  max-height: 160px;
  overflow-y: auto;
}

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
.toggle-btn--active:hover {
  background: var(--accent);
  color: #fff;
}






.plan-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  overflow-x: auto;
}





.plan-table-card :deep(.action-btn--delete) {
  color: #EF4444;
}







.mono {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  color: var(--ink3);
}



.team-badge {
  min-width: 34px;
  height: 34px;
  padding: 0 6px;
  white-space: nowrap;
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




/* Badge styles moved to Badge.vue component */

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

.unit-more {
  margin-left: 4px; padding: 0 5px; border-radius: 8px;
  background: var(--accent-soft); color: var(--accent);
  font-size: 10px; font-weight: 700; cursor: help;
}

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 16px;
  min-width: 0;
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
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
}
.form-field select { text-overflow: ellipsis; }
.form-field input:focus,
.form-field select:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-ring);
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

/* Missing a vehicle or supervisor - can't be generated, so it can't be picked */
.gen-mv-row--disabled {
  cursor: not-allowed;
  opacity: 0.55;
  background: var(--panel);
}

.gen-mv-row--disabled .gen-checkbox {
  cursor: not-allowed;
}

.gen-mv-assign {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 3px;
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
  max-height: calc(100vh - 32px);
  display: flex;
  flex-direction: column;
}
.am-modal .modal-header,
.am-modal .modal-footer {
  flex-shrink: 0;
}
.am-modal .modal-body {
  overflow-y: auto;
  flex: 1;
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

.am-error-banner {
  margin: 0;
  padding: 10px 12px;
  border-radius: 8px;
  background: var(--danger-soft);
  color: var(--danger);
  font-size: 12px;
  font-weight: 600;
}

.am-field-error {
  display: block;
  margin-top: 4px;
  font-size: 11px;
  font-weight: 600;
  color: var(--danger);
}

.field--invalid {
  border-color: var(--danger) !important;
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
  box-shadow: 0 0 0 3px var(--accent-ring);
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













/* Plan type badges moved to Badge.vue component */

/* Spin animation for loading indicators */
@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}
</style>
