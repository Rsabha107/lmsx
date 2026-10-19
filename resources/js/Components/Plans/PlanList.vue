<template>
  <div style="flex: 1; overflow: auto">
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
                >{{ plans.length }}</span
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
            @click="emit('new-plan')"
          >
            <template #icon><svg-icon name="plus" :size="14" /></template>
            New Plan
          </Button>
        </div>
        
        <!-- Plan Type Filters -->
        <div style="display: flex; gap: 6px; flex-wrap: wrap; align-items: center;">
          <input
            v-model="planListSearch"
            type="search"
            placeholder="Search code, name, template, team..."
            aria-label="Search plans"
            style="width: 240px; padding: 5px 10px; border: 1px solid var(--border); border-radius: 8px; font-size: 12px; background: var(--surface); color: var(--ink); outline: none;"
          />
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
          <button
            v-if="planTypesCounts.empty > 0 || emptyPlansOnly"
            @click="emptyPlansOnly = !emptyPlansOnly"
            :class="['filter-chip', { 'filter-chip--active': emptyPlansOnly }]"
            title="Plans that have no movements"
          >
            No movements
            <span class="filter-chip-count">{{ planTypesCounts.empty }}</span>
          </button>
        </div>
      </div>

    <div class="plan-table-card">
      <div v-if="visiblePlans.length" class="plans-bulk-bar">
        <label class="plans-bulk-select">
          <input
            type="checkbox"
            :checked="allVisiblePlansSelected"
            :indeterminate="selectedVisiblePlans.length > 0 && !allVisiblePlansSelected"
            @change="toggleSelectAllPlans"
          />
          Select all ({{ visiblePlans.length }})
        </label>
        <template v-if="selectedVisiblePlans.length">
          <span class="plans-bulk-count">{{ selectedVisiblePlans.length }} selected</span>
          <Button variant="secondary" size="sm" @click="selectedIds = new Set()">Clear</Button>
          <Button
            variant="primary"
            size="sm"
            style="background: #dc2626; border-color: #dc2626"
            @click="emit('bulk-delete', selectedVisiblePlans)"
          >Delete selected</Button>
        </template>
      </div>
      <!-- TABLE VIEW HEADER -->
      <div
        v-if="plansPageView === 'table'"
        style="
          display: grid;
          grid-template-columns: 28px 80px 120px 1.5fr 1fr 120px 100px 120px;
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
        <div></div>
        <div>Type</div>
        <div>Code</div>
        <div>Name</div>
        <div>Template</div>
        <div>Status</div>
        <div>Movements</div>
        <div>Actions</div>
      </div>
      <div
        v-if="!plans || plans.length === 0"
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
            @click="emit('new-plan')"
          >
            <template #icon><svg-icon name="plus" :size="14" /></template>
            Create Your First Plan
          </Button>
          <p v-if="!canCreatePlan" class="prereq-blocked">{{ prerequisiteHint }}</p>
        </div>
      </div>
      
      <!-- Empty state for filtered results -->
      <div
        v-else-if="plans.length > 0 && plansByDate.length === 0"
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
          @click="emit('open', plan.id)"
          :style="{
            display: 'grid',
            gridTemplateColumns: '28px 80px 120px 1.5fr 1fr 120px 100px 120px',
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
          <div @click.stop>
            <input
              type="checkbox"
              :checked="selectedIds.has(plan.id)"
              :aria-label="`Select ${plan.name}`"
              @change="togglePlanSelection(plan)"
            />
          </div>
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
            @duplicate="emit('duplicate', plan)"
            @edit="emit('edit', plan)"
            @delete="emit('delete', plan)"
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
                @click="emit('open', plan.id)"
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
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <input
                      type="checkbox"
                      :checked="selectedIds.has(plan.id)"
                      :aria-label="`Select ${plan.name}`"
                      @click.stop
                      @change="togglePlanSelection(plan)"
                    />
                    <Badge type="plan-type" :variant="plan.planType">
                      <template v-if="plan.planType === 'arrival'">✈️</template>
                      <template v-else-if="plan.planType === 'match'">⚽</template>
                      <template v-else-if="plan.planType === 'departure'">🛫</template>
                      <template v-else-if="plan.planType === 'transfer'">🚌</template>
                      <template v-else>📋</template>
                    </Badge>
                  </div>
                  <div @click.stop>
                    <TableActions
                      :show-duplicate="true"
                      @duplicate="emit('duplicate', plan)"
                      @edit="emit('edit', plan)"
                      @delete="emit('delete', plan)"
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
</template>

<script setup>
import { computed, ref } from 'vue';
import Badge from '../Badge.vue';
import Button from '../Button.vue';
import SvgIcon from '../SvgIcon.vue';
import TableActions from '../TableActions.vue';
import { formatDate } from '../../Composables/planFormat';

// The event's plans grouped by date, with search, type filters and bulk selection.
const props = defineProps({
  plans: { type: Array, default: () => [] },
  prerequisites: { type: Array, default: () => [] },
  canCreatePlan: { type: Boolean, default: false },
  prerequisiteHint: { type: String, default: '' },
});

// Plan ids ticked for bulk delete; kept by the page so it can clear them after deleting.
const selectedIds = defineModel('selectedIds', { type: Set, required: true });

const emit = defineEmits(['new-plan', 'open', 'duplicate', 'edit', 'delete', 'bulk-delete']);

const plansPageView = ref('grid'); // 'table' or 'grid' for main plans overview page

// Plan type filter for By Plan view
const planTypeFilter = ref(null); // null = all, 'arrival', 'match', 'transfer', 'other'
const emptyPlansOnly = ref(false);
const planListSearch = ref("");

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
  let filteredPlans = props.plans;
  
  // Apply type filter
  if (planTypeFilter.value) {
    filteredPlans = filteredPlans.filter(plan => detectPlanType(plan) === planTypeFilter.value);
  }
  if (emptyPlansOnly.value) {
    filteredPlans = filteredPlans.filter(plan => !plan.movements_count);
  }
  const term = planListSearch.value.trim().toLowerCase();
  if (term) {
    filteredPlans = filteredPlans.filter(plan => [
      plan.code,
      plan.name,
      plan.movement_template?.name,
      ...(plan.movements || []).map(mv => mv.team?.team_name),
    ].some(text => text?.toLowerCase().includes(term)));
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
  
  // Dates ascending; within a day, by the plan's first movement time.
  const firstStart = (plan) => Math.min(
    ...(plan.movements || []).map((mv) => (mv.window_start ? new Date(mv.window_start).getTime() : Infinity)),
    Infinity,
  );
  Object.values(grouped).forEach((group) => {
    group.plans.sort((a, b) => firstStart(a) - firstStart(b) || (a.name || '').localeCompare(b.name || ''));
  });

  return Object.values(grouped).sort((a, b) => {
    if (!a.date || !b.date) return 0;
    return a.date.localeCompare(b.date);
  });
});

// Count plans by type
const planTypesCounts = computed(() => {
  const counts = {
    all: props.plans.length,
    arrival: 0,
    match: 0,
    departure: 0,
    transfer: 0,
    other: 0,
    empty: 0
  };
  
  props.plans.forEach(plan => {
    const type = detectPlanType(plan);
    counts[type]++;
    if (!plan.movements_count) counts.empty++;
  });
  
  return counts;
});

const visiblePlans = computed(() => plansByDate.value.flatMap((group) => group.plans));
// Only what's on screen counts, so a filter change never deletes hidden plans.
const selectedVisiblePlans = computed(() =>
  visiblePlans.value.filter((plan) => selectedIds.value.has(plan.id))
);
const allVisiblePlansSelected = computed(
  () =>
    visiblePlans.value.length > 0 &&
    selectedVisiblePlans.value.length === visiblePlans.value.length
);

function togglePlanSelection(plan) {
  const next = new Set(selectedIds.value);
  next.has(plan.id) ? next.delete(plan.id) : next.add(plan.id);
  selectedIds.value = next;
}

function toggleSelectAllPlans() {
  selectedIds.value = allVisiblePlansSelected.value
    ? new Set()
    : new Set(visiblePlans.value.map((plan) => plan.id));
}
</script>

<style scoped>
/* Moved with the plan list from Plans.vue. */
.plans-empty-state {
  padding: 60px 40px;
  text-align: center;
  background: var(--panel);
  border-radius: 10px;
  margin: 20px;
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
.plan-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  overflow-x: auto;
}
.plans-bulk-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 14px;
  border-bottom: 1px solid var(--border);
  background: var(--panel);
}
.plans-bulk-select {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: var(--ink2);
  cursor: pointer;
  margin-right: auto;
}
.plans-bulk-count {
  font-size: 12px;
  font-weight: 700;
  color: var(--ink);
}
.plan-table-card :deep(.action-btn--delete) {
  color: #EF4444;
}
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
</style>
