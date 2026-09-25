<template>
  <app-layout>
    <!-- No Active Event State -->
    <div v-if="!activeEvent" class="empty-state-full">
      <div class="empty-state-icon">📅</div>
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">
        Please select an event from the dropdown above to view plans.
      </p>
    </div>

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
            <!-- All Plans option -->
            <div
              @click="selectAllPlans"
              :style="{
                padding: '10px 14px',
                cursor: 'pointer',
                borderLeft:
                  activePlan === null
                    ? '3px solid #3B82F6'
                    : '3px solid transparent',
                background:
                  activePlan === null ? '#EFF6FF' : 'transparent',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'space-between',
                transition: 'background 0.1s',
                marginBottom: '8px',
              }"
              @mouseenter="
                (e) => {
                  if (activePlan !== null)
                    e.currentTarget.style.background = '#F9FAFB';
                }
              "
              @mouseleave="
                (e) => {
                  e.currentTarget.style.background =
                    activePlan === null ? '#EFF6FF' : 'transparent';
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
                  {{ inAllPlansView ? 'All Plans' : 'All Movements' }} (Event-wide)
                </div>
                <div style="font-size: 11px; color: #9ca3af">
                  View all movements across all plans
                </div>
              </div>
              <svg
                v-if="activePlan === null"
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
            <!-- All Plans Card -->
            <div
              @click="selectAllPlans"
              :style="{
                padding: '12px',
                cursor: 'pointer',
                borderRadius: '8px',
                border: activePlan === null ? '2px solid #3B82F6' : '1px solid #e5e7eb',
                background: activePlan === null ? '#EFF6FF' : '#fff',
                transition: 'all 0.15s',
                marginBottom: '8px',
              }"
              @mouseenter="
                (e) => {
                  if (activePlan !== null) {
                    e.currentTarget.style.borderColor = '#cbd5e1';
                    e.currentTarget.style.boxShadow = '0 2px 6px rgba(0,0,0,0.08)';
                  }
                }
              "
              @mouseleave="
                (e) => {
                  e.currentTarget.style.borderColor = activePlan === null ? '#3B82F6' : '#e5e7eb';
                  e.currentTarget.style.boxShadow = 'none';
                }
              "
            >
              <div style="display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 6px;">
                <div style="font-size: 13px; font-weight: 700; color: #111827;">
                  {{ inAllPlansView ? 'All Plans' : 'All Movements' }}
                </div>
                <svg
                  v-if="activePlan === null"
                  width="18"
                  height="18"
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
              <div style="font-size: 11px; color: #6b7280;">
                Event-wide view of all movements
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

    <!-- Stats grid (only show when plan is selected) -->
    <div v-if="view === 'day' && selectedPlanObj" class="stats-grid">
      <!-- <MiniStat label="Plan" :value="selectedPlanObj.name" /> -->
      <MiniStat
        label="Movements"
        :value="selectedPlanObj.movements_count || 0"
      />
      <MiniStat label="Teams" :value="selectedPlanObj.teams_count || 0" />
      <MiniStat label="Jobs generated" :value="jobsGenerated" />
      <MiniStat label="Conflicts" :value="conflicts.length" tone="warn" />
      <MiniStat label="Passengers" :value="totalPassengers" />

    </div>

    <!-- By Plan view -->
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
          <div
            style="
              padding: 0 0 14px;
            "
          >
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
              <div style="display: flex; align-items: center; gap: 12px;">
                <div
                  style="
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    font-size: 11px;
                    letter-spacing: 1px;
                    text-transform: uppercase;
                    color: var(--ink3);
                    font-weight: 700;
                  "
                >
                  Plans
                  <span
                    style="
                      display: inline-flex;
                      align-items: center;
                      justify-content: center;
                      min-width: 20px;
                      height: 20px;
                      padding: 0 6px;
                      border-radius: 999px;
                      background: var(--accent-soft);
                      color: var(--accent);
                      font-size: 11px;
                      font-weight: 700;
                      letter-spacing: normal;
                      text-transform: none;
                    "
                    >{{ allPlans.length }}</span
                  >
                </div>
                <!-- View Toggle -->
                <div class="view-toggle">
                  <button
                    @click="plansPageView = 'table'"
                    :class="['toggle-btn', plansPageView === 'table' ? 'toggle-btn--active' : '']"
                    title="Table view"
                  >
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" style="display: block;">
                      <rect x="1" y="2" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                      <rect x="1" y="6.75" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                      <rect x="1" y="11.5" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                  </button>
                  <button
                    @click="plansPageView = 'grid'"
                    :class="['toggle-btn', plansPageView === 'grid' ? 'toggle-btn--active' : '']"
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
              <Button
                variant="primary"
                size="sm"
                :disabled="!canCreatePlan"
                :title="prerequisiteHint"
                @click="showNewPlan = true"
              >
                <template #icon><svg-icon name="plus" :size="14" /></template>
                New Plan
              </Button>
            </div>
            
            <!-- Plan Type Filters -->
            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
              <button
                @click="planTypeFilter = null"
                :class="['filter-chip', { 'filter-chip--active': planTypeFilter === null }]"
              >
                All
                <span class="filter-chip-count">{{ planTypesCounts.all }}</span>
              </button>
              <button
                @click="planTypeFilter = 'arrival'"
                :class="['filter-chip', { 'filter-chip--active': planTypeFilter === 'arrival' }]"
              >
                ✈️ Arrival
                <span class="filter-chip-count">{{ planTypesCounts.arrival }}</span>
              </button>
              <button
                @click="planTypeFilter = 'match'"
                :class="['filter-chip', { 'filter-chip--active': planTypeFilter === 'match' }]"
              >
                ⚽ Match
                <span class="filter-chip-count">{{ planTypesCounts.match }}</span>
              </button>
              <button
                @click="planTypeFilter = 'departure'"
                :class="['filter-chip', { 'filter-chip--active': planTypeFilter === 'departure' }]"
              >
                🛫 Departure
                <span class="filter-chip-count">{{ planTypesCounts.departure }}</span>
              </button>
              <button
                @click="planTypeFilter = 'transfer'"
                :class="['filter-chip', { 'filter-chip--active': planTypeFilter === 'transfer' }]"
              >
                🚌 Transfer
                <span class="filter-chip-count">{{ planTypesCounts.transfer }}</span>
              </button>
              <button
                v-if="planTypesCounts.other > 0"
                @click="planTypeFilter = 'other'"
                :class="['filter-chip', { 'filter-chip--active': planTypeFilter === 'other' }]"
              >
                Other
                <span class="filter-chip-count">{{ planTypesCounts.other }}</span>
              </button>
            </div>
          </div>

        <div class="plan-table-card">
          <!-- TABLE VIEW HEADER -->
          <div
            v-if="plansPageView === 'table'"
            style="
              display: grid;
              grid-template-columns: 80px 120px 1.5fr 1fr 120px 100px 120px;
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
            <div>Type</div>
            <div>Code</div>
            <div>Name</div>
            <div>Template</div>
            <div>Status</div>
            <div>Movements</div>
            <div>Actions</div>
          </div>
          <div
            v-if="!allPlans || allPlans.length === 0"
            class="plans-empty-state"
          >
            <div class="empty-state-icon">
              <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="12" y1="18" x2="12" y2="12"></line>
                <line x1="9" y1="15" x2="15" y2="15"></line>
              </svg>
            </div>
            <h3 class="empty-state-title">No Plans Yet</h3>
            <p class="empty-state-description">Movement plans organize team transportation logistics. Create your first plan to get started.</p>
            
            <div class="plans-instructions">
              <div class="instruction-step">
                <span class="step-number">1</span>
                <div class="step-content">
                  <strong>Set Up Prerequisites</strong>
                  <ul class="prereq-list">
                    <li v-for="item in prerequisites" :key="item.label" class="prereq-item">
                      <span class="prereq-mark" :class="item.met ? 'prereq-mark--ok' : (item.required ? 'prereq-mark--missing' : 'prereq-mark--optional')">
                        <svg-icon :name="item.met ? 'check' : 'x'" :size="11" />
                      </span>
                      <a :href="item.href" class="prereq-label" :class="{ 'prereq-label--met': item.met }">{{ item.label }}</a>
                      <span v-if="!item.required" class="prereq-optional">optional</span>
                    </li>
                  </ul>
                </div>
              </div>
              <div class="instruction-step">
                <span class="step-number">2</span>
                <div class="step-content">
                  <strong>Create a Plan</strong>
                  <p>Click "New Plan" to create an arrival, match, departure, or transfer plan</p>
                </div>
              </div>
              <div class="instruction-step">
                <span class="step-number">3</span>
                <div class="step-content">
                  <strong>Add Movements</strong>
                  <p>Select a template and configure pickup/dropoff locations for each team</p>
                </div>
              </div>
              <div class="instruction-step">
                <span class="step-number">4</span>
                <div class="step-content">
                  <strong>Generate Jobs</strong>
                  <p>Convert your plan into executable operations and assign resources</p>
                </div>
              </div>
            </div>
            
            <div class="empty-state-actions">
              <Button
                variant="primary"
                size="sm"
                :disabled="!canCreatePlan"
                :title="prerequisiteHint"
                @click="showNewPlan = true"
              >
                <template #icon><svg-icon name="plus" :size="14" /></template>
                Create Your First Plan
              </Button>
              <p v-if="!canCreatePlan" class="prereq-blocked">{{ prerequisiteHint }}</p>
            </div>
          </div>
          
          <!-- Empty state for filtered results -->
          <div
            v-else-if="allPlans.length > 0 && plansByDate.length === 0"
            style="padding: 40px; text-align: center; color: var(--ink3)"
          >
            <div style="font-size: 14px; font-weight: 600; margin-bottom: 8px">
              No plans match this filter
            </div>
            <div style="font-size: 12px">
              Try selecting a different plan type
            </div>
          </div>
          
          <!-- TABLE VIEW: Grouped by Date -->
          <template v-if="plansPageView === 'table'">
          <!-- Grouped by Date -->
          <template v-for="dateGroup in plansByDate" :key="dateGroup.date">
            <!-- Date Header -->
            <div
              style="
                padding: 10px 14px;
                background: var(--bg);
                border-bottom: 1px solid var(--border);
                display: flex;
                align-items: center;
                justify-content: space-between;
                position: sticky;
                top: 46px;
                z-index: 1;
              "
            >
              <div style="display: flex; align-items: center; gap: 8px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                  <line x1="16" y1="2" x2="16" y2="6"></line>
                  <line x1="8" y1="2" x2="8" y2="6"></line>
                  <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span style="font-size: 12px; font-weight: 700; color: var(--ink);">
                  {{ formatDate(dateGroup.date) }}
                </span>
              </div>
              <span style="font-size: 11px; color: var(--ink3); font-weight: 600;">
                {{ dateGroup.plans.length }} {{ dateGroup.plans.length === 1 ? 'plan' : 'plans' }}
              </span>
            </div>
            
            <!-- Plans for this date -->
            <div
              v-for="(plan, i) in dateGroup.plans"
              :key="plan.id"
              @click="selectPlan(plan.id)"
              :style="{
                display: 'grid',
                gridTemplateColumns: '80px 120px 1.5fr 1fr 120px 100px 120px',
                gap: '10px',
                padding: '12px 14px',
                borderBottom:
                  i === dateGroup.plans.length - 1 ? 'none' : '1px solid var(--border)',
                alignItems: 'center',
                cursor: 'pointer',
                transition: 'background 0.13s',
              }"
              @mouseenter="$event.currentTarget.style.background = 'var(--panel)'"
              @mouseleave="$event.currentTarget.style.background = 'transparent'"
            >
              <!-- Type Badge -->
              <div>
                <Badge type="plan-type" :variant="plan.planType">
                  <template v-if="plan.planType === 'arrival'">✈️</template>
                  <template v-else-if="plan.planType === 'match'">⚽</template>
                  <template v-else-if="plan.planType === 'departure'">🛫</template>
                  <template v-else-if="plan.planType === 'transfer'">🚌</template>
                  <template v-else>📋</template>
                </Badge>
              </div>
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
            <div>
              <Badge type="status" :variant="plan.status">
                {{ plan.status }}
              </Badge>
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
                @delete="confirmDeletePlan(plan)"
              />
            </div>
          </div>
        </template>
          </template>
          
          <!-- GRID VIEW: Grouped by Date -->
          <template v-if="plansPageView === 'grid'">
            <div style="padding: 16px;">
              <template v-for="dateGroup in plansByDate" :key="'grid-' + dateGroup.date">
                <!-- Date Header -->
                <div style="margin-bottom: 12px; padding-bottom: 8px; border-bottom: 2px solid var(--border);">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                      <line x1="16" y1="2" x2="16" y2="6"></line>
                      <line x1="8" y1="2" x2="8" y2="6"></line>
                      <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span style="font-size: 13px; font-weight: 700; color: var(--ink);">
                      {{ formatDate(dateGroup.date) }}
                    </span>
                    <span style="font-size: 11px; color: var(--ink3); font-weight: 600;">
                      · {{ dateGroup.plans.length }} {{ dateGroup.plans.length === 1 ? 'plan' : 'plans' }}
                    </span>
                  </div>
                </div>
                
                <!-- Plans Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; margin-bottom: 24px;">
                  <div
                    v-for="plan in dateGroup.plans"
                    :key="'card-' + plan.id"
                    @click="selectPlan(plan.id)"
                    style="
                      padding: 14px;
                      border: 1px solid var(--border);
                      border-radius: 10px;
                      cursor: pointer;
                      transition: all 0.15s;
                      background: var(--surface);
                    "
                    @mouseenter="$event.currentTarget.style.borderColor = 'var(--accent)'; $event.currentTarget.style.boxShadow = '0 2px 8px rgba(59, 130, 246, 0.15)'"
                    @mouseleave="$event.currentTarget.style.borderColor = 'var(--border)'; $event.currentTarget.style.boxShadow = 'none'"
                  >
                    <!-- Header: Type + Actions -->
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;">
                      <Badge type="plan-type" :variant="plan.planType">
                        <template v-if="plan.planType === 'arrival'">✈️</template>
                        <template v-else-if="plan.planType === 'match'">⚽</template>
                        <template v-else-if="plan.planType === 'departure'">🛫</template>
                        <template v-else-if="plan.planType === 'transfer'">🚌</template>
                        <template v-else>📋</template>
                      </Badge>
                      <div @click.stop>
                        <TableActions
                          :show-duplicate="true"
                          @duplicate="duplicatePlan(plan)"
                          @edit="editPlan(plan)"
                          @delete="confirmDeletePlan(plan)"
                        />
                      </div>
                    </div>
                    
                    <!-- Code -->
                    <div style="font-family: var(--mono); font-size: 11px; color: var(--ink3); font-weight: 700; margin-bottom: 6px;">
                      {{ plan.code }}
                    </div>
                    
                    <!-- Name -->
                    <div style="font-size: 14px; color: var(--ink); font-weight: 600; margin-bottom: 10px; line-height: 1.3;">
                      {{ plan.name }}
                    </div>
                    
                    <!-- Template -->
                    <div style="font-size: 11px; color: var(--ink3); margin-bottom: 10px;">
                      <span style="font-weight: 600;">Template:</span>
                      <span v-if="plan.movement_template"> {{ plan.movement_template.name }}</span>
                      <span v-else style="font-style: italic;"> Blank</span>
                    </div>
                    
                    <!-- Footer: Status + Movements -->
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                      <Badge type="status" :variant="plan.status">
                        {{ plan.status }}
                      </Badge>
                      <div style="font-size: 12px; color: var(--ink2); font-family: var(--mono); font-weight: 600;">
                        {{ plan.movements_count || 0 }} mvs
                      </div>
                    </div>
                  </div>
                </div>
              </template>
            </div>
          </template>
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
                  {{ date }}
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
              </select>
              <div
                v-if="movementsTeamFilter || movementsDateFilter || movementsPhaseFilter || movementsJobFilter"
                style="font-size: 11px; color: var(--ink3)"
              >
                Showing {{ filteredPlanMovements.length }} of
                {{ selectedPlanMovements.length }} movements
              </div>
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

            <div
              style="
                display: grid;
                grid-template-columns: 28px 5fr 7fr 6fr 5.5fr 14fr 9fr 7fr 4fr 4.5fr 7fr 7fr;
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
              <div>ID</div>
              <div>Date</div>
              <div>Phase</div>
              <div style="display: flex; align-items: center; gap: 4px;">
                Ref Time
                <InfoIcon :size="12" @click="showRefTimeInfoModal = true" />
              </div>
              <div>Team & Route</div>
              <div>Window</div>
              <div>Vehicle</div>
              <div>Pax</div>
              <div>Checks</div>
              <div style="display: flex; align-items: center; gap: 4px; justify-content: center;">
                Job
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
                  gridTemplateColumns:
                    '28px 5fr 7fr 6fr 5.5fr 14fr 9fr 7fr 4fr 4.5fr 7fr 7fr',
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
                    {{ mv.code || `M${i + 1}` }}
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
                <div v-if="mv.match_id" style="display: flex; flex-direction: column; align-items: flex-start; gap: 3px; min-width: 0;">
                  <Badge
                    type="kind"
                    variant="match"
                    >Match {{ mv.match?.match_number || '' }}</Badge
                  >
                  <span
                    style="font-size: 10.5px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%;"
                    :title="matchLineup(mv.match)"
                  >{{ matchLineup(mv.match) }}</span>
                </div>
                <Badge
                  v-else-if="mv.kind"
                  type="kind"
                  :variant="mv.kind"
                  >{{ mv.kind }}</Badge
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
                <div style="font-size: 11px; color: var(--ink3);">
                  <span v-if="isBusMovement(mv)" style="font-weight: 700; color: var(--ink2);">
                    BUS
                  </span>
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
                  <template v-if="isBusMovement(mv)">BUS</template>
                  <template v-else>
                    {{ formatTime(mv.window_start) }} –
                    {{ formatTime(mv.window_end) }}
                  </template>
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
                  <span
                    v-else-if="isBusMovement(mv)"
                    title="BUS movement — no reference time, can't generate a job"
                    style="font-size: 11px; color: var(--ink3); font-style: italic;"
                    >N/A</span
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
          v-if="conflicts.length === 0"
          style="
            padding: 12px;
            background: var(--ok-soft, var(--panel));
            border: 1px solid var(--ok, var(--border));
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
          "
        >
          <svg-icon
            name="check"
            :size="20"
            style="color: var(--ok); flex-shrink: 0"
          />
          <div style="flex: 1; font-size: 12px; color: var(--ink2)">
            <b>No conflicts detected</b> — every movement in this event has a
            valid window, free resources and realistic timings.
          </div>
        </div>
        <div
          v-else
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
                <span
                  v-if="c.when"
                  style="font-size: 11px; color: var(--ink3)"
                  >· {{ c.when }}</span
                >
                <span
                  v-if="c.plan"
                  style="font-size: 11px; color: var(--ink3)"
                  >· {{ c.plan }}</span
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
                v-if="c.hint"
                style="
                  font-size: 11.5px;
                  color: var(--ink3);
                  line-height: 1.5;
                  margin-bottom: 8px;
                "
              >
                <b style="color: var(--ink2)">Suggested fix:</b> {{ c.hint }}
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
              <Button variant="secondary" size="sm" @click="activeTab = 'movements'"
                >View</Button
              >
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
                    <span v-if="isBusMovement(mv)" style="font-weight: 700; color: var(--ink2);">
                      BUS
                    </span>
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
                      <template v-if="isBusMovement(mv)">BUS</template>
                      <template v-else>{{ mv.dep }} – {{ mv.arr }}</template>
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
        v-dialog="() => (showNewPlan = false)"
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
                  grid-template-columns: 1fr 1fr 1fr;
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
                    Bulk by Flights
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      color: inherit;
                      opacity: 0.7;
                      margin-top: 1px;
                    "
                  >
                    Group by arrival date
                  </div>
                </button>
                <button
                  class="am-mode-btn"
                  :class="{ 'am-mode-btn--active': newPlanMode === 'matches' }"
                  @click="newPlanMode = 'matches'"
                >
                  <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                    </svg>
                    Bulk by Matches
                  </div>
                  <div
                    style="
                      font-size: 11px;
                      color: inherit;
                      opacity: 0.7;
                      margin-top: 1px;
                    "
                  >
                    5h before each match
                  </div>
                </button>
              </div>

              <!-- Single Plan Mode -->
              <div v-if="newPlanMode === 'single'">
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

                <!-- Movement Reorder - only show if template selected with legs -->
                <div v-if="selectedNewPlanTemplate?.legs?.length > 0" class="form-field">
                  <label>Movement Position</label>
                  <select v-model="newPlanMovementPosition">
                    <option :value="null">Original order (all movements)</option>
                    <option
                      v-for="(leg, index) in selectedNewPlanTemplate.legs"
                      :key="index"
                      :value="index"
                    >
                      M{{ nextMovementNumber + index }}:
                      <template v-if="leg.from_location && leg.to_location">
                        {{ leg.from_location }} → {{ leg.to_location }}
                      </template>
                      <template v-else-if="leg.checkpoint_template">
                        {{ leg.checkpoint_template.name }}
                      </template>
                      <template v-else>
                        Movement {{ index + 1 }}
                      </template>
                    </option>
                  </select>
                  <div style="font-size: 11px; color: var(--ink3); margin-top: 6px;">
                    <template v-if="newPlanMovementPosition !== null">
                      Only movement M{{ nextMovementNumber + newPlanMovementPosition }} will be created from this template
                    </template>
                    <template v-else>
                      All {{ selectedNewPlanTemplate.legs.length }} movements will be created in order
                    </template>
                  </div>
                </div>
                
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

                <!-- Duplicate Checking Loading State -->
                <div
                  v-if="duplicateCheckLoading"
                  style="
                    padding: 10px 12px;
                    background: #F3F4F6;
                    border: 1px solid #D1D5DB;
                    border-radius: 8px;
                    margin-bottom: 16px;
                    font-size: 12px;
                    color: #6B7280;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                  "
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                    <line x1="12" y1="2" x2="12" y2="6"></line>
                    <line x1="12" y1="18" x2="12" y2="22"></line>
                    <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
                    <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                    <line x1="2" y1="12" x2="6" y2="12"></line>
                    <line x1="18" y1="12" x2="22" y2="12"></line>
                    <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
                    <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
                  </svg>
                  Checking for duplicates...
                </div>

                <!-- Duplicate Warning/Error -->
                <div v-if="duplicateCheck?.exists && !duplicateCheckLoading">
                  <!-- Strict Duplicate (Blocking) -->
                  <div
                    v-if="duplicateCheck.strict"
                    style="
                      padding: 12px;
                      background: #FEE2E2;
                      border: 1px solid #FCA5A5;
                      border-radius: 8px;
                      margin-bottom: 16px;
                      font-size: 12px;
                    "
                  >
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="15" y1="9" x2="9" y2="15"></line>
                        <line x1="9" y1="9" x2="15" y2="15"></line>
                      </svg>
                      <div style="flex: 1;">
                        <div style="font-weight: 700; color: #991B1B; margin-bottom: 4px;">Duplicate Movement</div>
                        <div style="color: #7F1D1D; margin-bottom: 8px;">{{ duplicateCheck.message }}</div>
                        <a
                          v-if="duplicateCheck.existing"
                          :href="`/plans#movement-${duplicateCheck.existing.id}`"
                          style="
                            color: #DC2626;
                            font-weight: 600;
                            text-decoration: underline;
                          "
                        >
                          View existing: {{ duplicateCheck.existing.code }}
                          <template v-if="duplicateCheck.existing.plan_name"> ({{ duplicateCheck.existing.plan_name }})</template>
                        </a>
                      </div>
                    </div>
                  </div>

                  <!-- Soft Warning (Allows with confirmation) -->
                  <div
                    v-else
                    style="
                      padding: 12px;
                      background: #FEF3C7;
                      border: 1px solid #FCD34D;
                      border-radius: 8px;
                      margin-bottom: 16px;
                      font-size: 12px;
                    "
                  >
                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#92400E" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                      </svg>
                      <div style="flex: 1;">
                        <div style="font-weight: 700; color: #78350F; margin-bottom: 4px;">Similar Movement Found</div>
                        <div style="color: #92400E; margin-bottom: 8px;">{{ duplicateCheck.message }}</div>
                        <div style="color: #92400E; font-size: 11px; margin-bottom: 8px;">You can proceed, but please confirm this is not a duplicate.</div>
                        <a
                          v-if="duplicateCheck.existing"
                          :href="`/plans#movement-${duplicateCheck.existing.id}`"
                          target="_blank"
                          style="
                            color: #B45309;
                            font-weight: 600;
                            text-decoration: underline;
                          "
                        >
                          View similar: {{ duplicateCheck.existing.code }}
                          <template v-if="duplicateCheck.existing.plan_name"> ({{ duplicateCheck.existing.plan_name }})</template>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Flight selector - only show if team has multiple flights -->
                <div v-if="needsFlightSelection" class="form-field">
                  <div style="
                    padding: 10px 12px;
                    background: #EFF6FF;
                    border: 1px solid #BFDBFE;
                    border-radius: 6px;
                    margin-bottom: 12px;
                    font-size: 12px;
                    color: #1E40AF;
                    display: flex;
                    align-items: flex-start;
                    gap: 8px;
                  ">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                      <circle cx="12" cy="12" r="10"></circle>
                      <line x1="12" y1="16" x2="12" y2="12"></line>
                      <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <div>
                      <strong style="display: block; margin-bottom: 2px;">Multiple flights detected</strong>
                      This team has multiple flights scheduled. Please select which flight this plan is for.
                    </div>
                  </div>
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
                      <Badge
                        v-if="leg.leg_type"
                        type="kind"
                        :variant="leg.leg_type"
                        >{{ leg.leg_type }}</Badge
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
                      v-for="template in bulkArrivalMovementTemplates"
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
                  v-if="newPlanTemplate"
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
                          v-if="bulkAllowedPreview.plansCount < bulkPreview.plansCount"
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                        >
                          {{ bulkAllowedPreview.plansCount }}
                          <span style="text-decoration: line-through; opacity: 0.4; margin-left: 4px;">{{ bulkPreview.plansCount }}</span>
                        </span>
                        <span
                          v-else
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
                          v-if="bulkAllowedPreview.teamsCount < bulkPreview.teamsWithDates"
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                        >
                          {{ bulkAllowedPreview.teamsCount }}
                          <span style="text-decoration: line-through; opacity: 0.4; margin-left: 4px;">{{ bulkPreview.teamsWithDates }}</span>
                        </span>
                        <span
                          v-else
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

                  <!-- Bulk Duplicate Check Loading State -->
                  <div
                    v-if="bulkDuplicateCheckLoading"
                    style="
                      padding: 10px 12px;
                      background: #F3F4F6;
                      border-bottom: 1px solid #D1D5DB;
                      font-size: 12px;
                      color: #6B7280;
                      display: flex;
                      align-items: center;
                      gap: 8px;
                    "
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                      <line x1="12" y1="2" x2="12" y2="6"></line>
                      <line x1="12" y1="18" x2="12" y2="22"></line>
                      <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
                      <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                      <line x1="2" y1="12" x2="6" y2="12"></line>
                      <line x1="18" y1="12" x2="22" y2="12"></line>
                      <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
                      <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
                    </svg>
                    Checking for duplicates...
                  </div>

                  <!-- Bulk Duplicate Summary -->
                  <div
                    v-else-if="bulkDuplicateChecks.size > 0"
                    style="
                      padding: 10px 12px;
                      border-bottom: 1px solid var(--border);
                    "
                  >
                    <!-- Strict duplicates (blocking) -->
                    <div
                      v-if="Array.from(bulkDuplicateChecks.values()).some(c => c.exists && c.strict)"
                      style="
                        padding: 10px;
                        background: #FEE2E2;
                        border: 1px solid #FCA5A5;
                        border-radius: 6px;
                        margin-bottom: 8px;
                        font-size: 12px;
                      "
                    >
                      <div style="display: flex; align-items: center; gap: 8px; color: #991B1B; font-weight: 700;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="15" y1="9" x2="9" y2="15"></line>
                          <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        {{ Array.from(bulkDuplicateChecks.values()).filter(c => c.exists && c.strict).length }} 
                        team(s) have duplicate conflicts (marked with ❌)
                      </div>
                      <div style="color: #7F1D1D; margin-top: 4px; font-size: 11px;">
                        These teams cannot be created. Please resolve the duplicates first.
                      </div>
                    </div>

                    <!-- Soft warnings -->
                    <div
                      v-if="Array.from(bulkDuplicateChecks.values()).some(c => c.exists && !c.strict)"
                      style="
                        padding: 10px;
                        background: #FEF3C7;
                        border: 1px solid #FCD34D;
                        border-radius: 6px;
                        font-size: 12px;
                      "
                    >
                      <div style="display: flex; align-items: center; gap: 8px; color: #92400E; font-weight: 700;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                          <line x1="12" y1="9" x2="12" y2="13"></line>
                          <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        {{ Array.from(bulkDuplicateChecks.values()).filter(c => c.exists && !c.strict).length }}
                        team(s) have similar movements (marked with ⚠️)
                      </div>
                      <div style="color: #92400E; margin-top: 4px; font-size: 11px;">
                        You can proceed, but please verify these are not duplicates.
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
                          style="display: inline-block; position: relative;"
                        >
                          <span
                            @click="team.has_multiple_flights && toggleTeamFlightExpansion(team.id)"
                            :style="{
                              fontSize: '10px',
                              padding: '2px 6px',
                              background: bulkDuplicateChecks.get(team.id)?.exists && bulkDuplicateChecks.get(team.id)?.strict ? '#FEE2E2' : 
                                          bulkDuplicateChecks.get(team.id)?.exists ? '#FEF3C7' : 'var(--panel)',
                              border: bulkDuplicateChecks.get(team.id)?.exists && bulkDuplicateChecks.get(team.id)?.strict ? '1px solid #FCA5A5' :
                                      bulkDuplicateChecks.get(team.id)?.exists ? '1px solid #FCD34D' : '1px solid var(--border)',
                              borderRadius: '4px',
                              color: bulkDuplicateChecks.get(team.id)?.exists && bulkDuplicateChecks.get(team.id)?.strict ? '#991B1B' :
                                     bulkDuplicateChecks.get(team.id)?.exists ? '#92400E' : 'var(--ink2)',
                              fontWeight: '600',
                              cursor: team.has_multiple_flights ? 'pointer' : 'default',
                              display: 'inline-flex',
                              alignItems: 'center',
                              gap: '4px',
                            }"
                            :title="bulkDuplicateChecks.get(team.id)?.exists ? 
                                    (bulkDuplicateChecks.get(team.id)?.strict ? '❌ Duplicate (blocked)' : '⚠️ Similar movement found') : 
                                    (team.has_multiple_flights ? 'Click to select flight' : '')"
                          >
                            <!-- Duplicate indicator icon -->
                            <span v-if="bulkDuplicateChecks.get(team.id)?.exists && bulkDuplicateChecks.get(team.id)?.strict">❌</span>
                            <span v-else-if="bulkDuplicateChecks.get(team.id)?.exists">⚠️</span>
                            
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
                        {{ bulkPreview.isDepartureTemplate ? 'departure' : 'arrival' }} dates</span
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

              <!-- Bulk by Matches Mode -->
              <div v-else-if="newPlanMode === 'matches'">
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
                      v-for="template in matchDayMovementTemplates"
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

                <!-- Matches Preview -->
                <div
                  v-if="newPlanTemplate"
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
                      Match-Day Plans Preview
                    </div>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap">
                      <div>
                        <span
                          v-if="matchAllowedPreview.plansCount < matchesPreview.plansCount"
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                        >
                          {{ matchAllowedPreview.plansCount }}
                          <span style="text-decoration: line-through; opacity: 0.4; margin-left: 4px;">{{ matchesPreview.plansCount }}</span>
                        </span>
                        <span
                          v-else
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                          >{{ matchesPreview.plansCount }}</span
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
                          v-if="matchAllowedPreview.teamsCount < matchesPreview.teamsCount"
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                        >
                          {{ matchAllowedPreview.teamsCount }}
                          <span style="text-decoration: line-through; opacity: 0.4; margin-left: 4px;">{{ matchesPreview.teamsCount }}</span>
                        </span>
                        <span
                          v-else
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: var(--accent);
                          "
                          >{{ matchesPreview.teamsCount }}</span
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
                      <div>
                        <span
                          style="
                            font-size: 20px;
                            font-weight: 700;
                            color: #f59e0b;
                          "
                          >{{ matchesPreview.matchesCount }}</span
                        >
                        <span
                          style="
                            font-size: 12px;
                            color: var(--ink3);
                            margin-left: 4px;
                          "
                          >matches</span
                        >
                      </div>
                    </div>
                    <div
                      v-if="matchesPreview.plans.length > 0"
                      style="
                        margin-top: 10px;
                        padding: 8px 10px;
                        background: #fef3c7;
                        border: 1px solid #fbbf24;
                        border-radius: 6px;
                        font-size: 11px;
                        color: #92400e;
                        display: flex;
                        align-items: center;
                        gap: 6px;
                      "
                    >
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                      </svg>
                      Plans start {{ describeKickoffOffset(matchStartOffsetMinutes) }}
                      <span style="opacity: 0.8">· from {{ matchStartOffsetSource }}</span>
                    </div>
                  </div>

                  <!-- Match Duplicate Check Loading State -->
                  <div
                    v-if="matchDuplicateCheckLoading"
                    style="
                      padding: 10px 12px;
                      background: #F3F4F6;
                      border-bottom: 1px solid #D1D5DB;
                      font-size: 12px;
                      color: #6B7280;
                      display: flex;
                      align-items: center;
                      gap: 8px;
                    "
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="animation: spin 1s linear infinite;">
                      <line x1="12" y1="2" x2="12" y2="6"></line>
                      <line x1="12" y1="18" x2="12" y2="22"></line>
                      <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"></line>
                      <line x1="16.24" y1="16.24" x2="19.07" y2="19.07"></line>
                      <line x1="2" y1="12" x2="6" y2="12"></line>
                      <line x1="18" y1="12" x2="22" y2="12"></line>
                      <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"></line>
                      <line x1="16.24" y1="7.76" x2="19.07" y2="4.93"></line>
                    </svg>
                    Checking for duplicates...
                  </div>

                  <!-- Match Duplicate Summary -->
                  <div
                    v-else-if="matchDuplicateChecks.size > 0"
                    style="
                      padding: 10px 12px;
                      border-bottom: 1px solid var(--border);
                    "
                  >
                    <!-- Strict duplicates (blocking) -->
                    <div
                      v-if="Array.from(matchDuplicateChecks.values()).some(c => c.exists && c.strict)"
                      style="
                        padding: 10px;
                        background: #FEE2E2;
                        border: 1px solid #FCA5A5;
                        border-radius: 6px;
                        margin-bottom: 8px;
                        font-size: 12px;
                      "
                    >
                      <div style="display: flex; align-items: center; gap: 8px; color: #991B1B; font-weight: 700;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <circle cx="12" cy="12" r="10"></circle>
                          <line x1="15" y1="9" x2="9" y2="15"></line>
                          <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                        {{ Array.from(matchDuplicateChecks.values()).filter(c => c.exists && c.strict).length }} 
                        team(s) have duplicate conflicts (marked with ❌)
                      </div>
                      <div style="color: #7F1D1D; margin-top: 4px; font-size: 11px;">
                        These teams cannot be created. Please resolve the duplicates first.
                      </div>
                    </div>

                    <!-- Soft warnings -->
                    <div
                      v-if="Array.from(matchDuplicateChecks.values()).some(c => c.exists && !c.strict)"
                      style="
                        padding: 10px;
                        background: #FEF3C7;
                        border: 1px solid #FCD34D;
                        border-radius: 6px;
                        font-size: 12px;
                      "
                    >
                      <div style="display: flex; align-items: center; gap: 8px; color: #92400E; font-weight: 700;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                          <line x1="12" y1="9" x2="12" y2="13"></line>
                          <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                        {{ Array.from(matchDuplicateChecks.values()).filter(c => c.exists && !c.strict).length }}
                        team(s) have similar movements (marked with ⚠️)
                      </div>
                      <div style="color: #92400E; margin-top: 4px; font-size: 11px;">
                        You can proceed, but please verify these are not duplicates.
                      </div>
                    </div>
                  </div>

                  <!-- Matches List (Grouped by Date) -->
                  <div
                    v-if="matchesPreview.plans.length > 0"
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
                      v-for="(plan, planIdx) in matchesPreview.plans"
                      :key="`${plan.date}_${plan.match_id}`"
                      :style="{
                        borderBottom:
                          planIdx < matchesPreview.plans.length - 1
                            ? '1px solid var(--border)'
                            : 'none',
                      }"
                    >
                      <!-- Match Header -->
                      <div
                        style="
                          padding: 10px 16px;
                          background: var(--panel);
                          border-bottom: 1px solid var(--border);
                        "
                      >
                        <div
                          style="
                            font-size: 12px;
                            font-weight: 700;
                            color: var(--accent);
                            display: flex;
                            align-items: center;
                            justify-content: space-between;
                          "
                        >
                          <div style="display: flex; align-items: center; gap: 8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                              <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" />
                            </svg>
                            {{ plan.match_number }}
                          </div>
                          <span
                            style="
                              font-size: 11px;
                              font-weight: 500;
                              color: var(--ink3);
                            "
                          >
                            {{ formatDate(plan.date) }} • {{ plan.plan_time }}
                          </span>
                        </div>
                        <div style="font-size: 11px; color: var(--ink3); margin-top: 4px;">
                          📍 {{ plan.venue }} • ⚽ {{ plan.kick_off_time }}
                        </div>
                      </div>

                      <!-- Teams in this match -->
                      <div style="padding: 12px 16px; display: flex; gap: 8px; flex-wrap: wrap; background: var(--surface); border-bottom: 1px solid var(--border);">
                        <div
                          v-for="team in plan.teams"
                          :key="team.team_id"
                          :style="{
                            flex: '1',
                            minWidth: '150px',
                            display: 'flex',
                            alignItems: 'center',
                            gap: '6px',
                            padding: '6px 10px',
                            borderRadius: '6px',
                            border: '1px solid',
                            background: matchDuplicateChecks.get(team.team_id)?.exists && matchDuplicateChecks.get(team.team_id)?.strict ? '#FEE2E2' : 
                                       matchDuplicateChecks.get(team.team_id)?.exists ? '#FEF3C7' : '#EFF6FF',
                            borderColor: matchDuplicateChecks.get(team.team_id)?.exists && matchDuplicateChecks.get(team.team_id)?.strict ? '#FCA5A5' :
                                        matchDuplicateChecks.get(team.team_id)?.exists ? '#FCD34D' : '#BFDBFE'
                          }"
                          :title="matchDuplicateChecks.get(team.team_id)?.exists ? 
                                  (matchDuplicateChecks.get(team.team_id)?.strict ? '❌ Duplicate (blocked)' : '⚠️ Similar movement found') : ''"
                        >
                          <span
                            :style="{
                              fontSize: '11px',
                              fontWeight: '700',
                              fontFamily: 'var(--mono)',
                              color: matchDuplicateChecks.get(team.team_id)?.exists && matchDuplicateChecks.get(team.team_id)?.strict ? '#991B1B' :
                                     matchDuplicateChecks.get(team.team_id)?.exists ? '#92400E' : '#1E40AF'
                            }"
                          >
                            <span v-if="matchDuplicateChecks.get(team.team_id)?.exists && matchDuplicateChecks.get(team.team_id)?.strict">❌ </span>
                            <span v-else-if="matchDuplicateChecks.get(team.team_id)?.exists">⚠️ </span>
                            {{ team.team_code }}
                          </span>
                          <span 
                            :style="{
                              fontSize: '11px',
                              color: matchDuplicateChecks.get(team.team_id)?.exists && matchDuplicateChecks.get(team.team_id)?.strict ? '#7F1D1D' :
                                     matchDuplicateChecks.get(team.team_id)?.exists ? '#78350F' : '#1E3A8A'
                            }"
                          >
                            {{ team.team_name }}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Empty State -->
                  <div
                    v-if="matchesPreview.plans.length === 0"
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
                      No matches found
                    </div>
                    <div style="font-size: 12px">
                      Please create matches for teams first
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
              :disabled="newPlanProcessing || duplicateCheckLoading || (duplicateCheck?.exists && duplicateCheck?.strict)"
            >
              <span v-if="newPlanProcessing">Creating...</span>
              <span v-else-if="duplicateCheckLoading">Checking...</span>
              <span v-else>Create Plan</span>
            </Button>
            <Button
              v-else-if="newPlanMode === 'bulk'"
              variant="primary"
              size="sm"
              @click="createBulkPlans"
              :disabled="newPlanProcessing || bulkAllowedPreview.plansCount === 0 || bulkDuplicateCheckLoading"
            >
              <span v-if="newPlanProcessing">Creating {{ bulkAllowedPreview.plansCount }} Plans...</span>
              <span v-else-if="bulkDuplicateCheckLoading">Checking...</span>
              <span v-else-if="bulkAllowedPreview.plansCount === 0 && bulkPreview.plans.length > 0">
                Cannot Create (all teams have conflicts)
              </span>
              <span v-else-if="bulkAllowedPreview.plansCount < bulkPreview.plansCount">
                Create {{ bulkAllowedPreview.plansCount }} Plans ({{ bulkAllowedPreview.teamsCount }} teams)
              </span>
              <span v-else>Create {{ bulkPreview.plansCount }} Plans</span>
            </Button>
            <Button
              v-else-if="newPlanMode === 'matches'"
              variant="primary"
              size="sm"
              @click="createMatchPlans"
              :disabled="newPlanProcessing || matchAllowedPreview.plansCount === 0 || matchDuplicateCheckLoading"
            >
              <span v-if="newPlanProcessing">Creating {{ matchAllowedPreview.plansCount }} Plans...</span>
              <span v-else-if="matchDuplicateCheckLoading">Checking...</span>
              <span v-else-if="matchAllowedPreview.plansCount === 0 && matchesPreview.plans.length > 0">
                Cannot Create (all teams have conflicts)
              </span>
              <span v-else-if="matchAllowedPreview.plansCount < matchesPreview.plansCount">
                Create {{ matchAllowedPreview.plansCount }} Plans ({{ matchAllowedPreview.teamsCount }} teams)
              </span>
              <span v-else>Create {{ matchesPreview.plansCount }} Match Plans</span>
            </Button>
          </div>
        </div>
      </div>

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
                    {{ mv.code || `M${genMovements.indexOf(mv) + 1}` }}
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
                      {{ formatTime(mv.window_start) || "—" }} →
                      {{ formatTime(mv.window_end) || "—" }}
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
      <div
        v-if="showEditMovement"
        v-dialog="() => (showEditMovement = false)"
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
                    style="font-size: 13px; font-weight: 600; color: var(--ink); display: flex; align-items: center; gap: 6px;"
                  >
                    <flag-icon :code="editingMovement.team.country_id" />
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
                    <Badge
                      v-if="emMatchId"
                      type="kind"
                      variant="match"
                      >Match {{ relevantMatches.find(m => m.id === emMatchId)?.match_number || '' }}</Badge
                    >
                    <Badge
                      v-else-if="emKind"
                      type="kind"
                      :variant="emKind"
                      >{{ emKind }}</Badge
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
                    {{ vehicle.code }} - {{ vehicle.vehicle_type }}{{ vehicle.capacity ? ` (${vehicle.capacity} pax)` : '' }}
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
            <p style="font-size: 14px; color: var(--ink); margin-bottom: 12px">
              Are you sure you want to delete
              <strong>{{ selectedMovementIds.size }}</strong>
              selected movement{{ selectedMovementIds.size === 1 ? "" : "s" }}?
            </p>
            <p style="font-size: 12px; color: var(--ink3); margin-bottom: 8px">
              Movements that already have a generated job will be skipped.
            </p>
            <p style="font-size: 13px; color: #dc2626; font-weight: 600">
              This action cannot be undone.
            </p>
          </div>
          <div class="modal-footer">
            <Button
              variant="secondary"
              size="sm"
              @click="cancelBulkDeleteMovements"
              :disabled="bulkDeletingMovements"
              >Cancel</Button
            >
            <Button
              variant="primary"
              size="sm"
              @click="confirmDeleteSelectedMovements"
              :disabled="bulkDeletingMovements"
              style="background: #dc2626; border-color: #dc2626"
            >
              {{
                bulkDeletingMovements
                  ? "Deleting..."
                  : `Delete Selected (${selectedMovementIds.size})`
              }}
            </Button>
          </div>
        </div>
      </div>
    </teleport>

    <!-- Template Preview modal -->
    <teleport to="body">
      <div
        v-if="showPreviewModal && selectedTemplate"
        v-dialog="() => (showPreviewModal = false)"
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
                      <Badge type="kind" :variant="mv.type">{{
                        mv.type
                      }}</Badge>
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
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "../Composables/useToast";
import axios from "axios";
import AppLayout from "../Components/AppLayout.vue";
import StatusPill from "../Components/StatusPill.vue";
import SvgIcon from "../Components/SvgIcon.vue";
import MiniStat from "../Components/MiniStat.vue";
import Button from "../Components/Button.vue";
import RefreshButton from "../Components/RefreshButton.vue";
import TableActions from "../Components/TableActions.vue";
import CheckpointTimeline from "../Components/CheckpointTimeline.vue";
import Badge from "../Components/Badge.vue";
import InfoIcon from "../Components/InfoIcon.vue";
import FlagIcon from "../Components/FlagIcon.vue";
import ConfirmModal from "../Components/ConfirmModal.vue";
import FormDateField from "../Components/FormDateField.vue";

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

const view = ref("day");
const showNewPlan = ref(false);
const newPlanMode = ref("single"); // 'single' or 'bulk' or 'matches'
const newPlanDate = ref("");
const newPlanStartTime = ref("09:00");
const newPlanName = ref("");
const newPlanTeamId = ref(null);
const newPlanFlightId = ref(null);
const newPlanAccommodationId = ref(null);
const newPlanTemplate = ref("");
const newPlanMovementPosition = ref(null); // Selected movement position from template
const newPlanProcessing = ref(false);
const newPlanErrors = ref({});
const expandedTeams = ref(new Set()); // Track which teams have flight selector expanded
const teamFlightSelections = ref({}); // Track selected flight per team

// Duplicate checking state
const duplicateCheck = ref(null); // { exists, strict, message, existing }
const duplicateCheckLoading = ref(false);
const duplicateConfirmed = ref(false); // User confirmed they want to create despite warning

// Bulk duplicate checking state
const bulkDuplicateChecks = ref(new Map()); // Map<team_id, { exists, strict, message, existing }>
const bulkDuplicateCheckLoading = ref(false);

// Match duplicate checking state
const matchDuplicateChecks = ref(new Map()); // Map<team_id, { exists, strict, message, existing }>
const matchDuplicateCheckLoading = ref(false);

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

// Bulk delete movements state
const selectedMovementIds = ref(new Set());
const showBulkDeleteMovementsConfirmation = ref(false);
const bulkDeletingMovements = ref(false);

// Selected movement for detail panel
const selectedMovement = ref(null);

// Use database plans
const allPlans = computed(() => props.plans || []);

// Initialize activePlan from backend prop (session-based, shared with Jobs page)
const activePlan = ref(props.activePlan);

// Handle team selection change. Date/start-time defaulting is handled by
// the unified watcher below (it needs the selected template too, to know
// whether to default from the team's arrival, departure, or match).
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
    }
  }
}

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

// Reset all New Plan form state when the modal is closed, so reopening it
// never shows a stale value from a previous attempt.
watch(showNewPlan, (isOpen) => {
  if (!isOpen) {
    newPlanMode.value = "single";
    newPlanDate.value = "";
    newPlanStartTime.value = "09:00";
    newPlanName.value = "";
    newPlanTeamId.value = null;
    newPlanFlightId.value = null;
    newPlanAccommodationId.value = null;
    newPlanTemplate.value = "";
    newPlanMovementPosition.value = null;
    newPlanProcessing.value = false;
    newPlanErrors.value = {};
    expandedTeams.value.clear();
    teamFlightSelections.value = {};
  }
});

// Watch for flash messages from backend
const page = usePage();
watch(
  () => page.props.flash,
  (flash) => {
    if (flash?.success) {
      showSuccessToast(flash.success);
    }
    if (flash?.error) {
      showErrorToast(flash.error);
    }
  },
  { deep: true }
);

const showPlanDropdown = ref(false);
const planDropdownView = ref('list'); // 'list' or 'grid'
const plansPageView = ref('grid'); // 'table' or 'grid' for main plans overview page
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

// Plan type filter for By Plan view
const planTypeFilter = ref(null); // null = all, 'arrival', 'match', 'transfer', 'other'

// Function to detect plan type from plan name
function detectPlanType(plan) {
  const nameLower = (plan.name || '').toLowerCase();
  if (nameLower.includes('arrival') || nameLower.includes('airport to hotel')) {
    return 'arrival';
  }
  if (nameLower.includes('match') || nameLower.includes('stadium')) {
    return 'match';
  }
  if (nameLower.includes('departure') || nameLower.includes('hotel to airport')) {
    return 'departure';
  }
  if (nameLower.includes('transfer') || nameLower.includes('training')) {
    return 'transfer';
  }
  return 'other';
}

// Grouped and filtered plans by date
const plansByDate = computed(() => {
  let filteredPlans = allPlans.value;
  
  // Apply type filter
  if (planTypeFilter.value) {
    filteredPlans = filteredPlans.filter(plan => detectPlanType(plan) === planTypeFilter.value);
  }
  
  // Group by date
  const grouped = {};
  filteredPlans.forEach(plan => {
    const date = plan.date;
    // Skip plans without a valid date
    if (!date) return;
    
    if (!grouped[date]) {
      grouped[date] = {
        date,
        plans: []
      };
    }
    grouped[date].plans.push({
      ...plan,
      planType: detectPlanType(plan)
    });
  });
  
  // Convert to array and sort by date (descending)
  return Object.values(grouped).sort((a, b) => {
    if (!a.date || !b.date) return 0;
    return b.date.localeCompare(a.date);
  });
});

// Count plans by type
const planTypesCounts = computed(() => {
  const counts = {
    all: allPlans.value.length,
    arrival: 0,
    match: 0,
    departure: 0,
    transfer: 0,
    other: 0
  };
  
  allPlans.value.forEach(plan => {
    const type = detectPlanType(plan);
    counts[type]++;
  });
  
  return counts;
});

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

const showPreviewModal = ref(false);
const selectedTemplate = ref(null);
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
    const duration = leg.estimated_duration_minutes ?? 30;
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
// everything in view. Already-generated and BUS legs can never be candidates.
const genMovements = computed(() => {
  const pool = selectedMovementIds.value.size > 0
    ? selectedPlanMovements.value.filter((mv) => selectedMovementIds.value.has(mv.id))
    : selectedPlanMovements.value;

  return pool.filter((mv) => !mv.job_id && !isBusMovement(mv));
});

const genAlreadyCount = computed(
  () => selectedPlanMovements.value.filter((mv) => mv.job_id).length
);
const genBusCount = computed(
  () => selectedPlanMovements.value.filter((mv) => !mv.job_id && isBusMovement(mv)).length
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
    return genBusCount.value > 0
      ? `${genBusCount.value} movement(s) are BUS (no flight) and can't generate a job`
      : `No ${scope} awaiting job generation`;
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
    { id: "checkpoints", label: "Checkpoints", count: 12 },
    {
      id: "conflicts",
      label: "Conflicts",
      count: conflicts.value.length,
      danger: conflicts.value.some((c) => c.sev === "high"),
    },
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

const conflicts = computed(() => props.conflicts);

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

// Computed property for matches relevant to the movement being edited
const relevantMatches = computed(() => {
  if (!props.matches || !editingMovement.value?.team_id) return [];
  
  // Show matches where the movement's team is playing
  const teamId = editingMovement.value.team?.id;
  return props.matches.filter(match =>
    match.team1_id === teamId || match.team2_id === teamId
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

// Filtered movements based on team and date filters
const filteredPlanMovements = computed(() => {
  let movements = selectedPlanMovements.value;
  
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

  // Apply job-generation-readiness filter
  if (movementsJobFilter.value) {
    const wantReady = movementsJobFilter.value === 'ready';
    movements = movements.filter((mv) => isReadyForGeneration(mv) === wantReady);
  }

  // Sort by date (desc), then phase
  movements = [...movements].sort((a, b) => {
    const aTime = a.window_start ? new Date(a.window_start).getTime() : -Infinity;
    const bTime = b.window_start ? new Date(b.window_start).getTime() : -Infinity;
    if (aTime !== bTime) return bTime - aTime;

    const aPhase = phaseLabels[movementPhase(a)] || movementPhase(a) || '';
    const bPhase = phaseLabels[movementPhase(b)] || movementPhase(b) || '';
    return aPhase.localeCompare(bPhase);
  });

  return movements;
});

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

// Ready = not already generated, schedulable (BUS legs have no reference time),
// and fully crewed — vehicle, driver and supervisor all assigned.
function isReadyForGeneration(mv) {
  return (
    !mv.job_id &&
    !isBusMovement(mv) &&
    Boolean(mv.vehicle_id) &&
    Boolean(mv.driver_id) &&
    Boolean(mv.field_supervisor_id)
  );
}

const readyForGenerationCount = computed(
  () => selectedPlanMovements.value.filter(isReadyForGeneration).length
);

const notReadyForGenerationCount = computed(
  () => selectedPlanMovements.value.length - readyForGenerationCount.value
);

const selectedNewPlanTemplate = computed(() => {
  if (!newPlanTemplate.value) return null;
  return props.movementTemplates.find((t) => t.id === newPlanTemplate.value);
});

// "Bulk by Arrival" groups teams by arrival/departure date, so only templates
// meant for those movements make sense to offer there.
const bulkArrivalMovementTemplates = computed(() =>
  props.movementTemplates.filter((t) => ["arrival_day", "departure_day"].includes(t.scenario_type))
);

// "Bulk by Matches" schedules a movement ahead of each match, so only
// match-day templates make sense to offer there.
const matchDayMovementTemplates = computed(() =>
  props.movementTemplates.filter((t) => t.scenario_type === "match_day")
);

// Resolved server-side from Settings (checkpoint override, then event, then global).
const matchStartOffsetMinutes = computed(
  () => selectedNewPlanTemplate.value?.match_start_offset?.minutes ?? 0
);
const matchStartOffsetSource = computed(
  () => selectedNewPlanTemplate.value?.match_start_offset?.source ?? "no offset configured yet"
);

function matchLineup(match) {
  const side = (team) => team?.code || team?.team_name || "TBD";
  return `${side(match?.team1)} vs ${side(match?.team2)}`;
}

function describeKickoffOffset(minutes) {
  if (!minutes) return "at kick-off";
  const abs = Math.abs(minutes);
  const hours = Math.floor(abs / 60);
  const mins = abs % 60;
  const span = [
    hours ? `${hours} hour${hours === 1 ? "" : "s"}` : "",
    mins ? `${mins} min` : "",
  ].filter(Boolean).join(" ");
  return `${span} ${minutes < 0 ? "before" : "after"} kick-off`;
}

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

// Find next match for selected team
const selectedTeamNextMatch = computed(() => {
  if (!newPlanTeamId.value || !props.matches || props.matches.length === 0) {
    return null;
  }
  
  const selectedTeam = selectedNewPlanTeam.value;
  if (!selectedTeam) return null;
  
  const teamId = selectedTeam.id;
  if (!teamId) return null;

  // Find all matches for this team
  const teamMatches = props.matches.filter(match =>
    match.team1_id === teamId || match.team2_id === teamId
  );
  
  if (teamMatches.length === 0) return null;
  
  // Sort by kick_off date and return the first one (or next upcoming)
  const sortedMatches = teamMatches.sort((a, b) => 
    new Date(a.kick_off) - new Date(b.kick_off)
  );
  
  return sortedMatches[0];
});

// Bulk plan preview - group teams by arrival date (or departure date when a departure template is selected)
const bulkPreview = computed(() => {
  const teamsWithDates = [];
  const teamsWithoutDates = [];
  const dateGroups = {};

  const template = selectedNewPlanTemplate.value;
  const templateText = `${template?.scenario_type || ''} ${template?.name || ''} ${template?.code || ''}`.toLowerCase();
  const isMatchTemplate = templateText.includes('match');
  const isDepartureTemplate = !isMatchTemplate && templateText.includes('departure');

  // Group teams by arrival date, unless the selected template is a departure
  // movement, in which case teams must be grouped by their departure date
  props.teams.forEach((team) => {
    const flightPool = isDepartureTemplate ? (team.departure_flights || []) : (team.flights || []);

    // Get selected flight or default to first flight
    const defaultFlightId = isDepartureTemplate ? flightPool[0]?.id : team.selected_flight_id;
    const selectedFlightId = teamFlightSelections.value[team.id] || defaultFlightId;
    const selectedFlight = flightPool.find(f => f.id === selectedFlightId) || flightPool[0];

    // Ensure team has valid display properties with fallbacks
    const normalizedTeam = {
      ...team,
      code: team.code || team.team || 'TEAM',
      team_name: team.team_name || team.team || 'Unnamed Team',
      flights: flightPool,
      arrival_date_time: selectedFlight?.scheduled_at || (isDepartureTemplate ? team.departure_date_time : team.arrival_date_time),
      arrival_date: selectedFlight?.scheduled_date || (isDepartureTemplate ? team.departure_date : team.arrival_date),
      selected_flight: selectedFlight,
      has_multiple_flights: flightPool.length > 1,
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
    isDepartureTemplate,
  };
});

// Computed property for bulk plans excluding conflicts
const bulkAllowedPreview = computed(() => {
  if (bulkPreview.value.plans.length === 0) {
    return {
      plansCount: 0,
      teamsCount: 0,
      hasStrictDuplicates: false,
    };
  }

  let allowedPlansCount = 0;
  let allowedTeamsCount = 0;
  let hasStrictDuplicates = false;

  // For each plan (date group), count teams that don't have strict duplicates
  bulkPreview.value.plans.forEach((plan) => {
    const allowedTeamsInPlan = plan.teams.filter((team) => {
      const check = bulkDuplicateChecks.value.get(team.id);
      const isBlocked = check?.exists && check?.strict;
      if (isBlocked) hasStrictDuplicates = true;
      return !isBlocked;
    });

    if (allowedTeamsInPlan.length > 0) {
      allowedPlansCount++;
      allowedTeamsCount += allowedTeamsInPlan.length;
    }
  });

  return {
    plansCount: allowedPlansCount,
    teamsCount: allowedTeamsCount,
    hasStrictDuplicates,
  };
});

// Computed property for matches preview
const matchesPreview = computed(() => {
  const teamsByMatchTime = {}; // Group teams by match (not just date)
  
  if (!props.matches || props.matches.length === 0) {
    return {
      plans: [],
      plansCount: 0,
      teamsCount: 0,
      matchesCount: 0,
    };
  }

  // For each match, create a separate plan entry
  props.matches.forEach((match) => {
    if (!match.kick_off) return;

    const kickOffDate = new Date(match.kick_off);
    
    // Use the match date (not the plan start date which could roll back to previous day)
    const matchDate = kickOffDate.toISOString().split('T')[0];
    
    // Calculate plan start time from the template's configured match offset
    const planStartDate = new Date(kickOffDate.getTime() + matchStartOffsetMinutes.value * 60 * 1000);
    const planTime = planStartDate.toTimeString().slice(0, 5);
    
    // Create a unique key for this match to group its teams together
    const matchKey = `${matchDate}_${match.id}`;

    // Initialize match group if needed
    if (!teamsByMatchTime[matchKey]) {
      teamsByMatchTime[matchKey] = {
        date: matchDate, // Use match date, not plan start date
        match_id: match.id,
        match_number: match.match_number,
        venue: match.venue?.name || 'TBD',
        kick_off: match.kick_off,
        kick_off_time: formatDateTime(match.kick_off),
        plan_time: planTime,
        teams: [],
      };
    }

    // Add team1
    if (match.team1_id && match.team1) {
      teamsByMatchTime[matchKey].teams.push({
        match_id: match.id,
        team_id: match.team1.id,
        team_code: match.team1.code,
        team_name: match.team1.team_name || match.team1.team,
        match_number: match.match_number,
        opponent: match.team2?.team_name || match.team2?.team || 'TBD',
        venue: match.venue?.name || 'TBD',
        kick_off_time: formatDateTime(match.kick_off),
        kick_off_time_only: kickOffDate.toTimeString().slice(0, 5),
        plan_start_time: formatDateTime(planStartDate.toISOString()),
        plan_time: planTime,
      });
    }

    // Add team2
    if (match.team2_id && match.team2) {
      teamsByMatchTime[matchKey].teams.push({
        match_id: match.id,
        team_id: match.team2.id,
        team_code: match.team2.code,
        team_name: match.team2.team_name || match.team2.team,
        match_number: match.match_number,
        opponent: match.team1?.team_name || match.team1?.team || 'TBD',
        venue: match.venue?.name || 'TBD',
        kick_off_time: formatDateTime(match.kick_off),
        kick_off_time_only: kickOffDate.toTimeString().slice(0, 5),
        plan_start_time: formatDateTime(planStartDate.toISOString()),
        plan_time: planTime,
      });
    }
  });

  // Convert to array and sort by date and time
  const plans = Object.values(teamsByMatchTime).sort((a, b) => {
    const dateCompare = a.date.localeCompare(b.date);
    if (dateCompare !== 0) return dateCompare;
    return a.plan_time.localeCompare(b.plan_time);
  });

  const allTeams = plans.flatMap(p => p.teams);
  const uniqueTeams = new Set(allTeams.map(t => t.team_id));

  // Debug: Log what matches are in the preview
  console.log('Matches Preview:', {
    totalPlans: plans.length,
    dates: [...new Set(plans.map(p => p.date))].sort(),
    planDetails: plans.map(p => ({
      date: p.date,
      match: p.match_number,
      teams: p.teams.map(t => t.team_code)
    }))
  });

  return {
    plans,
    plansCount: plans.length,
    teamsCount: uniqueTeams.size,
    matchesCount: props.matches.length,
  };
});

// Computed property for matches excluding conflicts
const matchAllowedPreview = computed(() => {
  if (matchesPreview.value.plans.length === 0) {
    return {
      plansCount: 0,
      teamsCount: 0,
      hasStrictDuplicates: false,
    };
  }

  let allowedPlansCount = 0;
  let allowedTeamsCount = 0;
  let hasStrictDuplicates = false;

  // For each plan (date group), count teams that don't have strict duplicates
  matchesPreview.value.plans.forEach((plan) => {
    const allowedTeamsInPlan = plan.teams.filter((team) => {
      const check = matchDuplicateChecks.value.get(team.team_id);
      const isBlocked = check?.exists && check?.strict;
      if (isBlocked) hasStrictDuplicates = true;
      return !isBlocked;
    });

    if (allowedTeamsInPlan.length > 0) {
      allowedPlansCount++;
      allowedTeamsCount += allowedTeamsInPlan.length;
    }
  });

  return {
    plansCount: allowedPlansCount,
    teamsCount: allowedTeamsCount,
    hasStrictDuplicates,
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
  
  // Handle different date formats
  let date;
  if (typeof dateString === 'string') {
    // If already contains time, use as-is
    if (dateString.includes('T') || dateString.includes(' ')) {
      date = new Date(dateString);
    } else {
      // YYYY-MM-DD format - add time to avoid timezone issues
      date = new Date(dateString + "T00:00:00");
    }
  } else {
    date = new Date(dateString);
  }
  
  // Check if date is valid
  if (isNaN(date.getTime())) {
    return dateString; // Return original if invalid
  }
  
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


function selectPlan(planId) {
  activePlan.value = planId;
  planSearchTerm.value = "";
  showPlanDropdown.value = false;
  // Switch to Movements tab when selecting a plan
  activeTab.value = "movements";
}

function selectAllPlans() {
  activePlan.value = null;
  planSearchTerm.value = "";
  showPlanDropdown.value = false;
  // Keep current view and tab - just show all movements
}

function viewAllPlans() {
  activePlan.value = null;
  view.value = "day"; // Switch to day view so tabs are visible
  activeTab.value = "plans";
  showPlanDropdown.value = false;
}

// Check for duplicate movements
async function checkForDuplicate() {
  // Reset duplicate state
  duplicateCheck.value = null;
  duplicateConfirmed.value = false;

  // Skip if required fields missing
  if (!newPlanTeamId.value || !newPlanDate.value) {
    return;
  }

  // Only check if we're creating from a template (movements will be generated)
  if (!newPlanTemplate.value) {
    return;
  }

  // Determine what to check based on template legs
  const template = selectedNewPlanTemplate.value;
  if (!template || !template.legs || template.legs.length === 0) {
    return;
  }

  duplicateCheckLoading.value = true;

  try {
    // Determine which legs to check
    let legsToCheck = [];
    
    if (newPlanMovementPosition.value !== null) {
      // User selected a specific movement position - check only that leg
      const selectedLeg = template.legs[newPlanMovementPosition.value];
      if (selectedLeg) {
        legsToCheck = [selectedLeg];
      }
    } else {
      // No specific position selected - check all legs
      legsToCheck = template.legs;
    }

    // Check each leg for duplicates
    for (const leg of legsToCheck) {
      const legType = leg.leg_type?.toLowerCase();
      
      // Skip if leg type is not defined or not a duplicate-checkable type
      if (!legType) continue;
      
      let checkData = {
        team_id: newPlanTeamId.value,
        window_start: newPlanDate.value + ' ' + (newPlanStartTime.value || '09:00') + ':00',
        kind: legType,
      };

      // For arrival/departure legs, check flight duplicates
      if ((legType === 'arrival' || legType === 'departure') && newPlanFlightId.value) {
        checkData.flight_id = newPlanFlightId.value;
        
        const response = await axios.post('/api/check-duplicate', checkData);
        
        if (response.data.exists) {
          // Found a duplicate - stop checking and show error
          duplicateCheck.value = response.data;
          break;
        }
      }
      // For match legs, check match duplicates (if we have match_id in future)
      else if (legType === 'match') {
        // Match duplicates would need match_id - skip for now
        // In the future, we could get match_id from selectedTeamNextMatch
        continue;
      }
      // For transfer/training/daily_ops, check location-based duplicates
      else if (['transfer', 'training', 'daily_ops'].includes(legType) && leg.from_location && leg.to_location) {
        checkData.from_location = leg.from_location;
        checkData.to_location = leg.to_location;
        
        const response = await axios.post('/api/check-duplicate', checkData);
        
        if (response.data.exists) {
          // Found a potential duplicate - show warning
          duplicateCheck.value = response.data;
          // Don't break - soft warnings allow proceeding
          break;
        }
      }
    }
  } catch (error) {
    console.error('Duplicate check failed:', error);
  } finally {
    duplicateCheckLoading.value = false;
  }
}

// Check for duplicates in bulk mode (all teams in bulk preview)
async function checkBulkDuplicates() {
  // Reset state
  bulkDuplicateChecks.value = new Map();
  
  // Skip if no template selected or not in bulk mode
  if (!newPlanTemplate.value || newPlanMode.value !== 'bulk') {
    return;
  }
  
  const template = selectedNewPlanTemplate.value;
  if (!template || !template.legs || template.legs.length === 0) {
    return;
  }
  
  // Skip if no teams in preview
  if (bulkPreview.value.plans.length === 0) {
    return;
  }
  
  bulkDuplicateCheckLoading.value = true;

  try {
    // Build one duplicate-check item per (team, leg) and send them all in a
    // single batched request — with dozens of teams, one request per team
    // (even fired concurrently) is still bottlenecked by the dev server's
    // limited worker pool and can take a minute or more.
    const checks = [];
    const teamIdByKey = new Map();
    let seq = 0;

    for (const plan of bulkPreview.value.plans) {
      for (const team of plan.teams) {
        for (const leg of template.legs) {
          const legType = leg.leg_type?.toLowerCase();

          // Skip if leg type is not defined
          if (!legType) continue;

          // Extract start time from team's arrival_date_time
          let startTime = '09:00';
          if (team.arrival_date_time) {
            const timeMatch = team.arrival_date_time.match(/[T ](\d{2}):(\d{2})/);
            if (timeMatch) {
              startTime = `${timeMatch[1]}:${timeMatch[2]}`;
            }
          }

          const key = String(seq++);
          let checkData = {
            key,
            team_id: team.id,
            window_start: plan.date + ' ' + startTime + ':00',
            kind: legType,
          };

          // For arrival/departure legs, check flight duplicates
          if ((legType === 'arrival' || legType === 'departure')) {
            const flightId = team.selected_flight?.id || team.flights?.[0]?.id;
            if (flightId) {
              checkData.flight_id = flightId;
              checks.push(checkData);
              teamIdByKey.set(key, team.id);
            }
          }
          // For transfer/training/daily_ops, check location-based duplicates
          else if (['transfer', 'training', 'daily_ops'].includes(legType) && leg.from_location && leg.to_location) {
            checkData.from_location = leg.from_location;
            checkData.to_location = leg.to_location;
            checks.push(checkData);
            teamIdByKey.set(key, team.id);
          }
        }
      }
    }

    if (checks.length > 0) {
      const response = await axios.post('/api/check-duplicate-bulk', { checks });
      for (const [key, result] of Object.entries(response.data.results)) {
        const teamId = teamIdByKey.get(key);
        if (result.exists && teamId && !bulkDuplicateChecks.value.has(teamId)) {
          // Store the duplicate check result for this team (first leg to find one wins)
          bulkDuplicateChecks.value.set(teamId, result);
        }
      }
    }
  } catch (error) {
    console.error('Bulk duplicate check failed:', error);
  } finally {
    bulkDuplicateCheckLoading.value = false;
  }
}

// Check for duplicates in matches mode (all teams in matches preview)
async function checkMatchDuplicates() {
  // Reset state
  matchDuplicateChecks.value = new Map();
  
  // Skip if no template selected or not in matches mode
  if (!newPlanTemplate.value || newPlanMode.value !== 'matches') {
    return;
  }
  
  const template = selectedNewPlanTemplate.value;
  if (!template || !template.legs || template.legs.length === 0) {
    return;
  }
  
  // Skip if no teams in preview
  if (matchesPreview.value.plans.length === 0) {
    return;
  }
  
  matchDuplicateCheckLoading.value = true;
  
  // Debug: Log what's being checked
  console.log('Checking match duplicates for:', {
    totalPlans: matchesPreview.value.plans.length,
    dates: matchesPreview.value.plans.map(p => p.date),
    teams: matchesPreview.value.plans.flatMap(p => p.teams.map(t => ({ date: p.date, team: t.team_code, team_id: t.team_id })))
  });
  
  try {
    // Build one duplicate-check item per (team, leg) and send them all in a
    // single batched request — with dozens of teams, one request per team
    // (even fired concurrently) is still bottlenecked by the dev server's
    // limited worker pool and can take a minute or more.
    const checks = [];
    const teamIdByKey = new Map();
    let seq = 0;

    for (const plan of matchesPreview.value.plans) {
      for (const team of plan.teams) {
        for (const leg of template.legs) {
          const legType = leg.leg_type?.toLowerCase();

          // Skip if leg type is not defined
          if (!legType) continue;

          const key = String(seq++);
          let checkData = {
            key,
            team_id: team.team_id,
            window_start: plan.date + ' ' + team.plan_time + ':00',
            kind: legType,
          };

          // For match legs, check match duplicates
          if (legType === 'match' && team.match_id) {
            checkData.match_id = team.match_id;
            checks.push(checkData);
            teamIdByKey.set(key, team.team_id);
          }
          // For arrival/departure legs, check flight duplicates if available
          else if ((legType === 'arrival' || legType === 'departure')) {
            // For match mode, we might not have flight info readily available
            // Skip flight-specific checks for now
            continue;
          }
          // For transfer/training/daily_ops, check location-based duplicates
          else if (['transfer', 'training', 'daily_ops'].includes(legType) && leg.from_location && leg.to_location) {
            checkData.from_location = leg.from_location;
            checkData.to_location = leg.to_location;
            checks.push(checkData);
            teamIdByKey.set(key, team.team_id);
          }
        }
      }
    }

    if (checks.length > 0) {
      const response = await axios.post('/api/check-duplicate-bulk', { checks });
      for (const [key, result] of Object.entries(response.data.results)) {
        const teamId = teamIdByKey.get(key);
        if (result.exists && teamId && !matchDuplicateChecks.value.has(teamId)) {
          // Store the duplicate check result for this team (first leg to find one wins)
          matchDuplicateChecks.value.set(teamId, result);
        }
      }
    }
  } catch (error) {
    console.error('Match duplicate check failed:', error);
  } finally {
    matchDuplicateCheckLoading.value = false;
    
    // Debug: Log duplicate check results
    console.log('Match duplicate check complete:', {
      totalChecked: matchesPreview.value.plans.flatMap(p => p.teams).length,
      duplicatesFound: matchDuplicateChecks.value.size,
      strictDuplicates: Array.from(matchDuplicateChecks.value.entries())
        .filter(([_, check]) => check.exists && check.strict)
        .map(([team_id, check]) => ({ team_id, message: check.message })),
      softWarnings: Array.from(matchDuplicateChecks.value.entries())
        .filter(([_, check]) => check.exists && !check.strict)
        .map(([team_id, check]) => ({ team_id, message: check.message }))
    });
  }
}

// Watch for changes to check duplicates
watch(
  [() => newPlanTeamId.value, () => newPlanFlightId.value, () => newPlanTemplate.value, () => newPlanDate.value, () => newPlanStartTime.value, () => newPlanMovementPosition.value],
  () => {
    if (newPlanMode.value === 'single') {
      checkForDuplicate();
    }
  },
  { deep: true }
);

// Reset duplicate check when modal opens/closes
watch(() => showNewPlan.value, (isOpen) => {
  if (!isOpen) {
    duplicateCheck.value = null;
    duplicateCheckLoading.value = false;
    duplicateConfirmed.value = false;
    bulkDuplicateChecks.value = new Map();
    bulkDuplicateCheckLoading.value = false;
    matchDuplicateChecks.value = new Map();
    matchDuplicateCheckLoading.value = false;
  }
});

// Watch for changes to trigger bulk duplicate checks
watch(
  [() => newPlanTemplate.value, () => bulkPreview.value.plans, () => teamFlightSelections.value],
  () => {
    if (newPlanMode.value === 'bulk') {
      checkBulkDuplicates();
    }
  },
  { deep: true }
);

// Watch for changes to trigger match duplicate checks
watch(
  [() => newPlanTemplate.value, () => matchesPreview.value.plans],
  () => {
    if (newPlanMode.value === 'matches') {
      checkMatchDuplicates();
    }
  },
  { deep: true }
);

// Reset movement position when template changes
watch(() => newPlanTemplate.value, () => {
  newPlanMovementPosition.value = null;
});

// Auto-set date/time from the selected team, based on what kind of
// template is selected: match templates default from the team's next
// match kick-off, departure templates from the team's departure flight,
// and everything else (including blank) from the team's arrival flight.
watch([() => newPlanTemplate.value, () => newPlanTeamId.value], () => {
  if (!newPlanTeamId.value || !props.teams) {
    return;
  }
  const selectedTeam = props.teams.find((t) => t.id === newPlanTeamId.value);
  if (!selectedTeam) {
    return;
  }

  const template = selectedNewPlanTemplate.value;
  const templateText = `${template?.scenario_type || ''} ${template?.name || ''} ${template?.code || ''}`.toLowerCase();
  const isMatchTemplate = templateText.includes('match');
  const isDepartureTemplate = !isMatchTemplate && templateText.includes('departure');

  newPlanErrors.value.date = "";

  if (isMatchTemplate) {
    if (!selectedTeamNextMatch.value) return;

    // Set date and time from the match kick-off and the template's configured offset
    const kickOffDate = new Date(selectedTeamNextMatch.value.kick_off);
    const planStartDate = new Date(kickOffDate.getTime() + matchStartOffsetMinutes.value * 60 * 1000);

    const year = planStartDate.getFullYear();
    const month = String(planStartDate.getMonth() + 1).padStart(2, '0');
    const day = String(planStartDate.getDate()).padStart(2, '0');
    newPlanDate.value = `${year}-${month}-${day}`;

    const hours = String(planStartDate.getHours()).padStart(2, '0');
    const minutes = String(planStartDate.getMinutes()).padStart(2, '0');
    newPlanStartTime.value = `${hours}:${minutes}`;
    return;
  }

  if (isDepartureTemplate) {
    if (!selectedTeam.departure_date_time) {
      newPlanDate.value = "";
      newPlanStartTime.value = "";
      newPlanErrors.value.date = `No departure flight set or created for ${selectedTeam.team_name}.`;
      return;
    }
    const dt = selectedTeam.departure_date_time;
    const dateMatch = dt.match(/(\d{4}-\d{2}-\d{2})/);
    if (dateMatch) newPlanDate.value = dateMatch[1];
    const timeMatch = dt.match(/[T ](\d{2}):(\d{2})/);
    if (timeMatch) newPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
    return;
  }

  // Default: arrival-based
  if (selectedTeam.arrival_date_time) {
    const dt = selectedTeam.arrival_date_time;
    const dateMatch = dt.match(/(\d{4}-\d{2}-\d{2})/);
    if (dateMatch) newPlanDate.value = dateMatch[1];
    const timeMatch = dt.match(/[T ](\d{2}):(\d{2})/);
    if (timeMatch) newPlanStartTime.value = `${timeMatch[1]}:${timeMatch[2]}`;
  }
});

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

  // Check for strict duplicates - block submission
  if (duplicateCheck.value?.exists && duplicateCheck.value?.strict) {
    newPlanErrors.value.duplicate = duplicateCheck.value.message;
    return;
  }

  // Check for soft warning - require confirmation
  if (duplicateCheck.value?.exists && !duplicateCheck.value?.strict && !duplicateConfirmed.value) {
    if (!confirm(duplicateCheck.value.message + '\n\nCreate anyway?')) {
      return;
    }
    duplicateConfirmed.value = true;
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
      movement_position: newPlanMovementPosition.value,
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
        newPlanMovementPosition.value = null;
        newPlanErrors.value = {};
        duplicateCheck.value = null;
        duplicateCheckLoading.value = false;
        duplicateConfirmed.value = false;
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

  // Check if there are any allowed plans (excluding strict duplicates)
  if (bulkAllowedPreview.value.plansCount === 0) {
    newPlanErrors.value.template = "No teams available to create (all have duplicate conflicts)";
    return;
  }

  newPlanProcessing.value = true;

  // Prepare bulk data: one plan per unique arrival date with teams that don't have strict duplicates
  const bulkData = bulkPreview.value.plans
    .map((plan) => {
      // Filter out teams with strict duplicates
      const allowedTeams = plan.teams
        .filter((team) => {
          const check = bulkDuplicateChecks.value.get(team.id);
          return !(check?.exists && check?.strict);
        })
        .map((team) => {
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
        teams: allowedTeams,
        movement_template_id: newPlanTemplate.value,
      };
    })
    .filter(plan => plan.teams.length > 0); // Only include plans that have at least one allowed team

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
        newPlanMovementPosition.value = null;
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

function createMatchPlans() {
  // Reset errors
  newPlanErrors.value = {};

  // Validate movement template selection
  if (!newPlanTemplate.value) {
    newPlanErrors.value.template =
      "Movement template is required for match plan creation";
    return;
  }

  // Check if there are plans to create
  if (matchesPreview.value.plans.length === 0) {
    newPlanErrors.value.template = "No matches found";
    return;
  }

  // Check if there are any allowed plans (excluding strict duplicates)
  if (matchAllowedPreview.value.plansCount === 0) {
    newPlanErrors.value.template = "No teams available to create (all have duplicate conflicts)";
    return;
  }

  newPlanProcessing.value = true;

  // Prepare match plans data grouped by date, filtering out teams with strict duplicates
  const matchPlansData = matchesPreview.value.plans
    .map((plan) => {
      // Filter out teams with strict duplicates
      const allowedTeams = plan.teams
        .filter((team) => {
          const check = matchDuplicateChecks.value.get(team.team_id);
          const isBlocked = check?.exists && check?.strict;
          
          // Debug logging
          if (isBlocked) {
            console.log(`Team ${team.team_code} on ${plan.date} blocked:`, check);
          }
          
          return !isBlocked;
        })
        .map((team) => ({
          team_id: team.team_id,
          start_time: team.plan_time,
          match_id: team.match_id,
        }));

      // Debug logging for empty plans
      if (allowedTeams.length === 0) {
        console.log(`Plan for ${plan.date} (${plan.match_number}) has no allowed teams - will be skipped`);
      }

      return {
        date: plan.date,
        teams: allowedTeams,
        movement_template_id: newPlanTemplate.value,
      };
    })
    .filter(plan => plan.teams.length > 0); // Only include plans that have at least one allowed team

  // Debug: Log what's being sent to backend
  console.log('Sending match plans data:', matchPlansData);
  console.log('Total plans to create:', matchPlansData.length);
  console.log('Plan dates:', matchPlansData.map(p => p.date));

  router.post(
    "/plans/bulk-matches",
    {
      plans: matchPlansData,
    },
    {
      onSuccess: () => {
        showNewPlan.value = false;
        newPlanMode.value = "single";
        newPlanTemplate.value = "";
        newPlanMovementPosition.value = null;
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

  // Populate form fields - handle both "By Plan" and "By Team" data structures
  emTeamId.value = movement.team_id || "";
  emKind.value = movement.kind || "";

  // Handle location properties (By Plan uses from_location/to_location, By Team uses from/to)
  emFrom.value = movement.from_location || movement.from || "";
  emTo.value = movement.to_location || movement.to || "";

  // Handle datetime properties
  // By Plan has window_start/window_end (datetime strings)
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

  // Handle passengers (By Plan uses passengers, By Team uses pax)
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

function confirmDeleteSelectedMovements() {
  const ids = Array.from(selectedMovementIds.value);
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
    if (genBusCount.value > 0) reasons.push(`${genBusCount.value} BUS movement(s) have no reference time`);

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

  if (isBusMovement(movement)) {
    genBlockedMessage.value = 'This is a <strong>BUS</strong> movement with no reference time, so a job cannot be generated for it.';
    showGenerateBlocked.value = true;
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

.plans-empty-state {
  padding: 60px 40px;
  text-align: center;
  background: var(--panel);
  border-radius: 10px;
  margin: 20px;
}

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

.empty-state-icon {
  width: 72px;
  height: 72px;
  border-radius: 14px;
  background: var(--surface);
  border: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 20px;
  color: var(--accent);
}

.empty-state-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 8px;
}

.empty-state-description {
  font-size: 13px;
  color: var(--ink3);
  margin: 0 0 32px;
  max-width: 480px;
  margin-left: auto;
  margin-right: auto;
  line-height: 1.5;
}

.plans-instructions {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  max-width: 700px;
  margin: 0 auto 32px;
  text-align: left;
}

.instruction-step {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  padding: 16px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
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
  margin-bottom: 4px;
}

.step-content p {
  font-size: 12px;
  color: var(--ink3);
  margin: 0;
  line-height: 1.5;
}

.empty-state-actions {
  display: flex;
  justify-content: center;
  gap: 8px;
  flex-direction: column;
  align-items: center;
}

.prereq-blocked {
  margin: 0;
  font-size: 12px;
  color: #b45309;
}

.prereq-list {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 5px;
}

.prereq-item {
  display: flex;
  align-items: center;
  gap: 7px;
  font-size: 12px;
}

.prereq-mark {
  display: grid;
  place-items: center;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  flex: 0 0 auto;
  color: #fff;
}
.prereq-mark--ok { background: var(--ok, #16a34a); }
.prereq-mark--missing { background: var(--danger, #b91c1c); }
.prereq-mark--optional { background: var(--ink4, #9ca3af); }

.prereq-label {
  color: var(--ink3);
  text-decoration: none;
  border-bottom: 1px dotted var(--border);
}
.prereq-label:hover { color: var(--accent); }
.prereq-label--met { color: var(--ink); }

.prereq-optional {
  font-size: 10.5px;
  color: var(--ink4);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

@media (max-width: 768px) {
  .plans-empty-state {
    padding: 40px 20px;
  }
  
  .plans-instructions {
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

.plan-table-card :deep(.action-btn--delete) {
  color: #EF4444;
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

.form-field {
  display: flex;
  flex-direction: column;
  gap: 5px;
  margin-bottom: 16px;
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

/* Filter Chips */
.filter-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 12px;
  border-radius: 999px;
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--ink2);
  font-size: 11px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  white-space: nowrap;
}

.filter-chip:hover {
  background: var(--panel);
  border-color: var(--ink4);
}

.filter-chip--active {
  background: var(--accent);
  color: white;
  border-color: var(--accent);
}

.filter-chip--active:hover {
  background: var(--accent);
  border-color: var(--accent);
}

.filter-chip-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.2);
  font-size: 10px;
  font-weight: 700;
}

.filter-chip--active .filter-chip-count {
  background: rgba(255, 255, 255, 0.25);
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
