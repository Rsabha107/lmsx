<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Matches</h1>
        <p class="page-sub">{{ filteredMatches.length }} of {{ matches.length }} matches</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['matches']" />
        <Button variant="secondary" size="sm">
          <template #icon>
            <svg-icon name="filter" :size="14" />
          </template>
          Filter
        </Button>
        <Button variant="primary" size="sm" @click="openAddModal">
          <template #icon>
            <svg-icon name="plus" :size="14" style="color: #fff;" />
          </template>
          Add Match
        </Button>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="stats-grid">
      <mini-stat label="Total Matches" :value="matches.length" />
      <mini-stat label="Upcoming" :value="upcomingMatches" tone="primary" />
      <mini-stat label="Venues" :value="uniqueVenues" />
      <mini-stat label="Stages" :value="uniqueStages" tone="ok" />
    </div>

    <!-- Matches Table Controls -->
    <div class="table-header">
      <div class="table-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search matches..." 
            class="search-input"
          />
        </div>
        <select v-model="filterStage" class="filter-select">
          <option value="">All Stages</option>
          <option v-for="stage in uniqueStagesArray" :key="stage" :value="stage">
            {{ stage }}
          </option>
        </select>
      </div>
      <div class="table-controls">
        <div class="view-toggle" v-if="!isMobile">
          <button @click="viewMode = 'grid'" :class="['toggle-btn', activeViewMode === 'grid' ? 'toggle-btn--active' : '']" title="Grid view">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="1" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="9.5" y="1" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="1" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
              <rect x="9.5" y="9.5" width="5.5" height="5.5" rx="1.5" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </button>
          <button @click="viewMode = 'table'" :class="['toggle-btn', activeViewMode === 'table' ? 'toggle-btn--active' : '']" title="Table view">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
              <rect x="1" y="2" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="1" y="6.75" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
              <rect x="1" y="11.5" width="14" height="2.5" rx="1" stroke="currentColor" stroke-width="1.5"/>
            </svg>
          </button>
        </div>
        <ColumnToggle 
          v-if="!isMobile && activeViewMode === 'table'"
          :columns="availableColumns" 
          :visible-columns="visibleColumns" 
          @toggle="toggleColumn"
        />
      </div>
    </div>

    <!-- Matches Grid/Table and Detail Panel Container -->
    <div class="matches-container">
      <!-- Grid View -->
      <div v-if="activeViewMode === 'grid'" class="matches-grid-wrapper" :class="{ 'with-panel': selectedMatch }">
        <div class="matches-grid">
          <div v-for="match in filteredMatches" :key="match.id" class="match-card" @click="selectMatch(match)" :class="{ 'match-card--selected': selectedMatch?.id === match.id }">
            <div class="mc-header">
              <div class="match-badge-md">
                {{ match.match_number }}
              </div>
              <div class="mc-stage">
                <span v-if="match.stage" style="font-size: 10px; color: var(--ink3); font-weight: 600;">{{ match.stage }}</span>
              </div>
            </div>
            <div class="mc-teams">
              <div class="mc-team">
                <span class="mc-team-badge">{{ match.team1?.code || '—' }}</span>
                <span class="mc-team-name">{{ match.team1?.team_name || 'TBD' }}</span>
              </div>
              <div class="mc-vs">vs</div>
              <div class="mc-team">
                <span class="mc-team-badge">{{ match.team2?.code || '—' }}</span>
                <span class="mc-team-name">{{ match.team2?.team_name || 'TBD' }}</span>
              </div>
            </div>
            <div class="mc-info">
              <div class="mc-info-row" v-if="match.venue">
                <svg-icon name="home" :size="12" />
                <span>{{ match.venue }}</span>
              </div>
              <div class="mc-info-row" v-if="match.event">
                <svg-icon name="calendar" :size="12" />
                <span>{{ match.event }}</span>
              </div>
              <div class="mc-info-row" v-if="match.match_date">
                <svg-icon name="clock" :size="12" />
                <span>{{ formatDate(match.match_date) }}</span>
              </div>
            </div>
            <div class="mc-footer">
              <button class="mc-action-btn" @click.stop="editMatch(match)">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                  <path d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Edit
              </button>
              <button class="mc-action-btn" @click.stop="openDeleteModal(match)">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                  <path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Delete
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Table View -->
      <div v-else class="table-card" :class="{ 'with-panel': selectedMatch }">
        <div style="overflow-x: auto;">
          <table class="matches-table">
          <thead>
            <tr>
              <th>Match #</th>
              <th v-if="visibleColumns.event">Event</th>
              <th v-if="visibleColumns.venue">Venue</th>
              <th>TEAM 1</th>
              <th>TEAM 2</th>
              <th v-if="visibleColumns.stage">Stage</th>
              <th v-if="visibleColumns.matchDate">Match Date</th>
              <th v-if="visibleColumns.gatesOpening">Gates Opening</th>
              <th v-if="visibleColumns.kickOff">Kick Off</th>
              <th class="center" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="match in filteredMatches" 
              :key="match.id" 
              class="table-row" 
              :class="{ 'table-row--selected': selectedMatch?.id === match.id }"
              @click="selectMatch(match)"
            >
              <td class="code-cell">
                <span class="match-badge-sm">{{ match.match_number }}</span>
              </td>
              <td v-if="visibleColumns.event">{{ match.event || '—' }}</td>
              <td v-if="visibleColumns.venue">{{ match.venue || '—' }}</td>
              <td class="team-cell">
                <div class="team-display">
                  <span class="team-code">{{ match.team1?.code || '—' }}</span>
                  <span class="team-name">{{ match.team1?.team_name || 'TBD' }}</span>
                </div>
              </td>
              <td class="team-cell">
                <div class="team-display">
                  <span class="team-code">{{ match.team2?.code || '—' }}</span>
                  <span class="team-name">{{ match.team2?.team_name || 'TBD' }}</span>
                </div>
              </td>
              <td v-if="visibleColumns.stage">{{ match.stage || '—' }}</td>
              <td v-if="visibleColumns.matchDate" class="mono">{{ formatDate(match.match_date) }}</td>
              <td v-if="visibleColumns.gatesOpening" class="mono">{{ formatDate(match.gates_opening) }}</td>
              <td v-if="visibleColumns.kickOff" class="mono">{{ formatDate(match.kick_off) }}</td>
              <td class="actions-cell" @click.stop>
                <TableActions 
                  :is-deleting="deleting && matchToDelete?.id === match.id" 
                  @edit="editMatch(match)" 
                  @delete="openDeleteModal(match)" 
                />
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <!-- Match Detail Card -->
      <transition name="slide-card">
        <div v-if="selectedMatch" class="detail-card">
          <div class="detail-card-header">
            <div class="detail-card-title-section">
              <div class="detail-card-match-badge">
                <span class="match-badge-lg">{{ selectedMatch.match_number }}</span>
              </div>
              <div>
                <h3 class="detail-card-match-name">{{ selectedMatch.team1?.team_name || 'TBD' }} vs {{ selectedMatch.team2?.team_name || 'TBD' }}</h3>
                <div class="detail-card-match-meta">
                  <span>{{ selectedMatch.stage || 'No stage' }}</span>
                </div>
              </div>
            </div>
            <button @click="selectedMatch = null" class="detail-card-close">
              <svg-icon name="x" :size="18" />
            </button>
          </div>

          <div class="detail-card-content">
            <!-- Match Info -->
            <div class="detail-section">
              <h4 class="detail-section-title">Match Details</h4>
              <div class="detail-row">
                <span class="detail-label">Match Number</span>
                <span class="detail-value mono">{{ selectedMatch.match_number }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Event</span>
                <span class="detail-value">{{ selectedMatch.event || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Venue</span>
                <span class="detail-value">{{ selectedMatch.venue || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Stage</span>
                <span class="detail-value">{{ selectedMatch.stage || '—' }}</span>
              </div>
            </div>

            <!-- Teams -->
            <div class="detail-section">
              <h4 class="detail-section-title">Teams</h4>
              <div class="detail-row">
                <span class="detail-label">Team 1</span>
                <div class="team-detail">
                  <span class="team-code-badge">{{ selectedMatch.team1?.code || '—' }}</span>
                  <span class="detail-value">{{ selectedMatch.team1?.team_name || 'TBD' }}</span>
                </div>
              </div>
              <div class="detail-row">
                <span class="detail-label">Team 2</span>
                <div class="team-detail">
                  <span class="team-code-badge">{{ selectedMatch.team2?.code || '—' }}</span>
                  <span class="detail-value">{{ selectedMatch.team2?.team_name || 'TBD' }}</span>
                </div>
              </div>
            </div>

            <!-- Schedule -->
            <div class="detail-section">
              <h4 class="detail-section-title">Schedule</h4>
              <div class="detail-row">
                <span class="detail-label">Match Date</span>
                <span class="detail-value mono">{{ formatDate(selectedMatch.match_date) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Gates Opening</span>
                <span class="detail-value mono">{{ formatDate(selectedMatch.gates_opening) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Kick Off</span>
                <span class="detail-value mono">{{ formatDate(selectedMatch.kick_off) }}</span>
              </div>
            </div>
          </div>

          <div class="detail-card-footer">
            <Button variant="secondary" size="sm" @click="selectedMatch = null">Close</Button>
            <Button variant="primary" size="sm" @click="editMatch(selectedMatch); selectedMatch = null;">Edit Match</Button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Add Match Modal -->
    <Modal :show="showAddModal" @close="showAddModal = false" max-width="600px">
      <template #title>Add New Match</template>
      
      <form @submit.prevent="submitMatch" class="match-form" style="position: relative;">
        <!-- Processing Overlay -->
        <div v-if="processing" class="processing-overlay">
          <div class="processing-spinner"></div>
          <div class="processing-text">Adding match...</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Match Number <span class="required">*</span></label>
            <input 
              v-model="formData.match_number" 
              type="text" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.match_number }"
              placeholder="e.g., M001" 
              @input="validationErrors.match_number = ''"
            />
            <span v-if="validationErrors.match_number" class="form-error">{{ validationErrors.match_number }}</span>
          </div>
          
          <div class="form-group">
            <label class="form-label">Stage</label>
            <input 
              v-model="formData.stage" 
              type="text" 
              class="form-input" 
              placeholder="e.g., Group Stage, Quarter Final" 
            />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Event</label>
            <input v-model="formData.event" type="text" class="form-input" placeholder="Event name" />
          </div>

          <div class="form-group">
            <label class="form-label">Venue</label>
            <input v-model="formData.venue" type="text" class="form-input" placeholder="Stadium or venue" />
          </div>
        </div>

        <div class="form-section-title">Teams</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Team 1</label>
            <select v-model="formData.team1_id" class="form-input">
              <option value="">Select Team 1</option>
              <option v-for="team in teams" :key="team.code" :value="team.code">
                {{ team.code }} - {{ team.team_name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Team 2</label>
            <select v-model="formData.team2_id" class="form-input">
              <option value="">Select Team 2</option>
              <option v-for="team in teams" :key="team.code" :value="team.code">
                {{ team.code }} - {{ team.team_name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-section-title">Schedule</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Match Date</label>
            <input ref="matchDateInput" v-model="formData.match_date" type="text" class="form-input" placeholder="YYYY-MM-DD" />
          </div>

          <div class="form-group">
            <label class="form-label">Gates Opening</label>
            <input ref="gatesOpeningInput" v-model="formData.gates_opening" type="text" class="form-input" placeholder="HH:MM" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Kick Off</label>
          <input ref="kickOffInput" v-model="formData.kick_off" type="text" class="form-input" placeholder="HH:MM" />
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showAddModal = false" :disabled="processing">Cancel</Button>
        <Button variant="primary" size="sm" @click="submitMatch" :processing="processing" :disabled="processing">Add Match</Button>
      </template>
    </Modal>

    <!-- Edit Match Modal -->
    <Modal :show="showEditModal" @close="showEditModal = false" max-width="600px">
      <template #title>Edit Match</template>
      
      <form @submit.prevent="submitMatch" class="match-form" style="position: relative;">
        <!-- Processing Overlay -->
        <div v-if="processing" class="processing-overlay">
          <div class="processing-spinner"></div>
          <div class="processing-text">Updating match...</div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Match Number <span class="required">*</span></label>
            <input 
              v-model="formData.match_number" 
              type="text" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.match_number }"
              placeholder="e.g., M001" 
              @input="validationErrors.match_number = ''"
            />
            <span v-if="validationErrors.match_number" class="form-error">{{ validationErrors.match_number }}</span>
          </div>
          
          <div class="form-group">
            <label class="form-label">Stage</label>
            <input 
              v-model="formData.stage" 
              type="text" 
              class="form-input" 
              placeholder="e.g., Group Stage, Quarter Final" 
            />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Event</label>
            <input v-model="formData.event" type="text" class="form-input" placeholder="Event name" />
          </div>

          <div class="form-group">
            <label class="form-label">Venue</label>
            <input v-model="formData.venue" type="text" class="form-input" placeholder="Stadium or venue" />
          </div>
        </div>

        <div class="form-section-title">Teams</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Team 1</label>
            <select v-model="formData.team1_id" class="form-input">
              <option value="">Select Team 1</option>
              <option v-for="team in teams" :key="team.code" :value="team.code">
                {{ team.code }} - {{ team.team_name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Team 2</label>
            <select v-model="formData.team2_id" class="form-input">
              <option value="">Select Team 2</option>
              <option v-for="team in teams" :key="team.code" :value="team.code">
                {{ team.code }} - {{ team.team_name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-section-title">Schedule</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Match Date</label>
            <input ref="matchDateInput" v-model="formData.match_date" type="text" class="form-input" placeholder="YYYY-MM-DD" />
          </div>

          <div class="form-group">
            <label class="form-label">Gates Opening</label>
            <input ref="gatesOpeningInput" v-model="formData.gates_opening" type="text" class="form-input" placeholder="HH:MM" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Kick Off</label>
          <input ref="kickOffInput" v-model="formData.kick_off" type="text" class="form-input" placeholder="HH:MM" />
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showEditModal = false" :disabled="processing">Cancel</Button>
        <Button variant="primary" size="sm" @click="submitMatch" :processing="processing" :disabled="processing">Update Match</Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :show="showDeleteModal" @close="closeDeleteModal" max-width="500px">
      <template #title>Delete Match</template>
      
      <div style="padding: 0;">
        <p style="margin: 0 0 12px; color: var(--ink2); font-size: 14px;">
          Are you sure you want to delete <strong>Match {{ matchToDelete?.match_number }}</strong>?
        </p>
        <p style="margin: 0; color: var(--ink3); font-size: 13px;">
          This action cannot be undone.
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="closeDeleteModal" :disabled="deleting">Cancel</Button>
        <Button 
          variant="primary" 
          size="sm" 
          @click="deleteMatch" 
          :processing="deleting" 
          :disabled="deleting" 
          style="background: #EF4444;"
        >
          Delete
        </Button>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';
import AppLayout from '../Components/AppLayout.vue';
import MiniStat from '../Components/MiniStat.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';
import Modal from '../Components/Modal.vue';
import ColumnToggle from '../Components/ColumnToggle.vue';
import TableActions from '../Components/TableActions.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  matches: {
    type: Array,
    required: true,
  },
  teams: {
    type: Array,
    required: true,
  },
});

const selectedMatch = ref(null);
const searchQuery = ref('');
const filterStage = ref('');
const viewMode = ref('table'); // 'grid' or 'table'
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);
const isMobile = computed(() => windowWidth.value < 768);

// Auto-switch to grid view on mobile
const activeViewMode = computed(() => {
  return isMobile.value ? 'grid' : viewMode.value;
});

function onResize() {
  windowWidth.value = window.innerWidth;
}

const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const processing = ref(false);
const deleting = ref(false);
const validationErrors = ref({});
const matchToDelete = ref(null);
const matchDateInput = ref(null);
const gatesOpeningInput = ref(null);
const kickOffInput = ref(null);
let matchDatePicker = null;
let gatesOpeningPicker = null;
let kickOffPicker = null;

const formData = ref({
  id: null,
  match_number: '',
  event: '',
  venue: '',
  team1_id: '',
  team2_id: '',
  stage: '',
  match_date: '',
  gates_opening: '',
  kick_off: '',
});

const visibleColumns = ref({
  event: true,
  venue: true,
  stage: true,
  matchDate: true,
  gatesOpening: false,
  kickOff: true,
});

const availableColumns = [
  { key: 'event', label: 'Event', required: false },
  { key: 'venue', label: 'Venue', required: false },
  { key: 'stage', label: 'Stage', required: false },
  { key: 'matchDate', label: 'Match Date', required: false },
  { key: 'gatesOpening', label: 'Gates Opening', required: false },
  { key: 'kickOff', label: 'Kick Off', required: false },
];

const uniqueStagesArray = computed(() => {
  const stages = props.matches
    .map(m => m.stage)
    .filter(Boolean);
  return [...new Set(stages)].sort();
});

const filteredMatches = computed(() => {
  let result = props.matches;

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(match => 
      match.match_number?.toLowerCase().includes(query) ||
      match.event?.toLowerCase().includes(query) ||
      match.venue?.toLowerCase().includes(query) ||
      match.stage?.toLowerCase().includes(query) ||
      match.team1?.team_name?.toLowerCase().includes(query) ||
      match.team2?.team_name?.toLowerCase().includes(query) ||
      match.team1?.code?.toLowerCase().includes(query) ||
      match.team2?.code?.toLowerCase().includes(query)
    );
  }

  // Filter by stage
  if (filterStage.value) {
    result = result.filter(match => match.stage === filterStage.value);
  }

  return result;
});

const upcomingMatches = computed(() => {
  const now = new Date();
  return props.matches.filter(m => m.match_date && new Date(m.match_date) > now).length;
});

const uniqueVenues = computed(() => {
  const venues = props.matches.map(m => m.venue).filter(Boolean);
  return new Set(venues).size;
});

const uniqueStages = computed(() => {
  const stages = props.matches.map(m => m.stage).filter(Boolean);
  return new Set(stages).size;
});

// Watch for modal opening to initialize flatpickr
watch(showAddModal, async (newVal) => {
  if (newVal) {
    await nextTick();
    destroyFlatpickr();
    setTimeout(initializeFlatpickr, 50);
  } else {
    destroyFlatpickr();
    validationErrors.value = {};
  }
});

watch(showEditModal, async (newVal) => {
  if (newVal) {
    await nextTick();
    destroyFlatpickr();
    setTimeout(initializeFlatpickr, 50);
  } else {
    destroyFlatpickr();
    validationErrors.value = {};
  }
});

function selectMatch(match) {
  selectedMatch.value = match;
}

function toggleColumn(key) {
  visibleColumns.value[key] = !visibleColumns.value[key];
}

function openAddModal() {
  // Reset form data
  formData.value = {
    id: null,
    match_number: '',
    event: '',
    venue: '',
    team1_id: '',
    team2_id: '',
    stage: '',
    match_date: '',
    gates_opening: '',
    kick_off: '',
  };
  showAddModal.value = true;
  validationErrors.value = {};
}

function editMatch(match) {
  formData.value = {
    id: match.id,
    match_number: match.match_number,
    event: match.event || '',
    venue: match.venue || '',
    team1_id: match.team1_id || '',
    team2_id: match.team2_id || '',
    stage: match.stage || '',
    match_date: match.match_date ? formatDateOnly(match.match_date) : '',
    gates_opening: match.gates_opening ? formatTime(match.gates_opening) : '',
    kick_off: match.kick_off ? formatTime(match.kick_off) : '',
  };
  showEditModal.value = true;
  validationErrors.value = {};
}

function openDeleteModal(match) {
  matchToDelete.value = match;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  matchToDelete.value = null;
  showDeleteModal.value = false;
}

function deleteMatch() {
  if (!matchToDelete.value) return;
  
  deleting.value = true;
  router.delete(`/matches/${matchToDelete.value.id}`, {
    onSuccess: () => {
      showDeleteModal.value = false;
      matchToDelete.value = null;
      deleting.value = false;
    },
    onError: () => {
      deleting.value = false;
    },
    onFinish: () => {
      deleting.value = false;
    },
  });
}

function validateForm() {
  const errors = {};
  
  if (!formData.value.match_number || formData.value.match_number.trim() === '') {
    errors.match_number = 'Match number is required';
  }
  
  validationErrors.value = errors;
  return Object.keys(errors).length === 0;
}

function submitMatch() {
  if (!validateForm()) {
    return;
  }
  
  processing.value = true;
  const url = showEditModal.value ? `/matches/${formData.value.id}` : '/matches';
  const method = showEditModal.value ? 'put' : 'post';
  
  router[method](url, formData.value, {
    onSuccess: () => {
      showAddModal.value = false;
      showEditModal.value = false;
      processing.value = false;
      // Reset form
      formData.value = {
        id: null,
        match_number: '',
        event: '',
        venue: '',
        team1_id: '',
        team2_id: '',
        stage: '',
        match_date: '',
        gates_opening: '',
        kick_off: '',
      };
    },
    onError: () => {
      processing.value = false;
    },
    onFinish: () => {
      processing.value = false;
    },
  });
}

function initializeFlatpickr() {
  if (matchDateInput.value) {
    matchDatePicker = flatpickr(matchDateInput.value, {
      enableTime: false,
      dateFormat: 'Y-m-d',
      allowInput: true,
    });
  }
  
  if (gatesOpeningInput.value) {
    gatesOpeningPicker = flatpickr(gatesOpeningInput.value, {
      enableTime: true,
      noCalendar: true,
      time_24hr: true,
      dateFormat: 'H:i',
      allowInput: true,
    });
  }
  
  if (kickOffInput.value) {
    kickOffPicker = flatpickr(kickOffInput.value, {
      enableTime: true,
      noCalendar: true,
      time_24hr: true,
      dateFormat: 'H:i',
      allowInput: true,
    });
  }
}

function destroyFlatpickr() {
  if (matchDatePicker) {
    matchDatePicker.destroy();
    matchDatePicker = null;
  }
  if (gatesOpeningPicker) {
    gatesOpeningPicker.destroy();
    gatesOpeningPicker = null;
  }
  if (kickOffPicker) {
    kickOffPicker.destroy();
    kickOffPicker = null;
  }
}

function formatDate(dateString) {
  if (!dateString) return '—';
  
  // Parse string directly to avoid timezone conversion issues
  const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})(?::\d{2})?/);
  if (match) {
    const [, year, month, day, hours, minutes] = match;
    return `${year}-${month}-${day} ${hours}:${minutes}`;
  }
  
  // If format doesn't match, return original
  return dateString;
}

function formatTime(dateString) {
  if (!dateString) return '';
  
  // Extract time from datetime string
  const match = dateString.match(/(\d{2}):(\d{2})(?::\d{2})?/);
  if (match) {
    const [, hours, minutes] = match;
    return `${hours}:${minutes}`;
  }
  
  return dateString;
}

function formatDateOnly(dateString) {
  if (!dateString) return '';
  
  // Extract date only from datetime string
  const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (match) {
    const [, year, month, day] = match;
    return `${year}-${month}-${day}`;
  }
  
  return dateString;
}

onMounted(() => {
  window.addEventListener('resize', onResize);
});

onUnmounted(() => {
  destroyFlatpickr();
  window.removeEventListener('resize', onResize);
});
</script>

<style scoped>
.page-header { 
  display: flex; align-items: flex-start; justify-content: space-between; 
  gap: 16px; margin-bottom: 16px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }

.header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-wrap: wrap;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 10px;
  margin-bottom: 16px;
}

@media (max-width: 1024px) {
  .stats-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 640px) {
  .stats-grid { grid-template-columns: 1fr; }
}

.table-card {
  background: var(--surface); 
  border: 1px solid var(--border);
  border-radius: 10px; 
  overflow: hidden;
  margin-bottom: 20px;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 0 8px 0;
  margin-bottom: 8px;
}

.table-controls {
  display: flex;
  gap: 8px;
  align-items: center;
}

.search-box {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  transition: border-color 0.13s;
}

.search-box:focus-within {
  border-color: var(--accent);
}

.search-box svg {
  color: var(--ink3);
  flex-shrink: 0;
}

.search-input {
  border: none;
  background: none;
  outline: none;
  font-size: 13px;
  color: var(--ink);
  width: 200px;
  padding: 0;
}

.search-input::placeholder {
  color: var(--ink4);
}

.filter-select {
  padding: 6px 12px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 7px;
  font-size: 13px;
  color: var(--ink2);
  cursor: pointer;
  transition: border-color 0.13s;
  outline: none;
}

.filter-select:hover {
  border-color: var(--accent);
}

.filter-select:focus {
  border-color: var(--accent);
}

.view-toggle {
  display: flex;
  gap: 2px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 7px;
  padding: 3px;
}

.toggle-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  background: transparent;
  color: var(--ink3);
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.13s;
}

.toggle-btn:hover {
  color: var(--ink);
  background: var(--surface);
}

.toggle-btn--active {
  background: var(--surface);
  color: var(--accent);
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

/* Grid View Styles */
.matches-grid-wrapper {
  flex: 1;
  min-width: 0;
}

.matches-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px;
}

.match-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
  cursor: pointer;
  transition: all 0.15s;
}

.match-card:hover {
  border-color: var(--accent);
  box-shadow: 0 2px 8px rgba(15, 23, 36, 0.08);
}

.match-card--selected {
  background: var(--accent-soft, #EEF0FE);
  border-color: var(--accent);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15);
}

.mc-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}

.match-badge-md {
  width: 40px;
  height: 40px;
  border-radius: 6px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.mc-stage {
  flex: 1;
  text-align: right;
  padding-top: 2px;
}

.mc-teams {
  margin-bottom: 12px;
  padding: 12px 0;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}

.mc-team {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
}

.mc-team:last-child {
  margin-bottom: 0;
}

.mc-team-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 32px;
  height: 20px;
  padding: 0 6px;
  border-radius: 4px;
  background: var(--panel);
  color: var(--ink);
  font-size: 10px;
  font-weight: 700;
  flex-shrink: 0;
}

.mc-team-name {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
}

.mc-vs {
  text-align: center;
  font-size: 11px;
  font-weight: 600;
  color: var(--ink3);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin: 6px 0;
}

.mc-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.mc-info-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--ink3);
}

.mc-info-row svg {
  color: var(--ink4);
  flex-shrink: 0;
}

.mc-footer {
  display: flex;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.mc-action-btn {
  flex: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 5px;
  padding: 6px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: transparent;
  color: var(--ink3);
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.13s;
  font-family: inherit;
}

.mc-action-btn:hover {
  background: var(--panel);
  color: var(--ink);
  border-color: var(--ink3);
}

.matches-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.matches-table thead th {
  padding: 9px 14px;
  text-align: left;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: var(--ink3);
  border-bottom: 1px solid var(--border);
  background: var(--panel);
  white-space: nowrap;
  position: sticky;
  top: 0;
  z-index: 10;
}

.matches-table tbody tr {
  transition: background-color 0.13s;
  cursor: pointer;
}

.matches-table tbody tr:hover {
  background: var(--panel);
}

.matches-table tbody tr.table-row--selected {
  background: var(--accent-soft, #EEF0FE);
  border-left: 3px solid var(--accent);
}

.matches-table tbody tr.table-row--selected:hover {
  background: var(--accent-soft, #EEF0FE);
}

.matches-table tbody td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}

.matches-table tbody tr:last-child td {
  border-bottom: none;
}

.code-cell {
  padding: 11px 14px;
}

.match-badge-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 36px;
  height: 24px;
  padding: 0 8px;
  border-radius: 5px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 11px;
  font-weight: 700;
}

.team-cell {
  font-weight: 500;
}

.team-display {
  display: flex;
  align-items: center;
  gap: 8px;
}

.team-code {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 18px;
  padding: 0 5px;
  border-radius: 3px;
  background: var(--panel);
  font-size: 10px;
  font-weight: 700;
  color: var(--ink);
}

.team-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
}

.mono {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  color: var(--ink3);
}

.center {
  text-align: center !important;
}

.matches-table td.center {
  text-align: center;
}

.matches-table th.center {
  text-align: center;
}

.actions-cell {
  padding: 8px 14px !important;
}

@media (max-width: 768px) {
  .table-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .table-controls {
    flex-wrap: wrap;
  }

  .search-box {
    flex: 1;
    min-width: 200px;
  }

  .search-input {
    width: 100%;
  }

  .filter-select {
    flex: 1;
    min-width: 150px;
  }
}

/* Form Styles */
.match-form {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.form-section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink2);
  margin-top: 8px;
  margin-bottom: -8px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.form-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--ink2);
}

.required {
  color: #ef4444;
}

.form-input,
.form-textarea {
  padding: 8px 12px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 13px;
  color: var(--ink);
  background: var(--surface);
  transition: border-color 0.15s;
  font-family: inherit;
}

.form-input:focus,
.form-textarea:focus {
  outline: none;
  border-color: var(--accent);
}

.form-input--error {
  border-color: #ef4444;
}

.form-input--error:focus {
  border-color: #ef4444;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.form-error {
  font-size: 12px;
  color: #ef4444;
  margin-top: -2px;
  display: block;
}

select.form-input {
  cursor: pointer;
}

@media (max-width: 640px) {
  .form-row {
    grid-template-columns: 1fr;
  }
}

/* Flatpickr custom styling */
:deep(.flatpickr-calendar) {
  font-family: inherit;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--border);
  border-radius: 8px;
}

:deep(.flatpickr-day.selected) {
  background: var(--accent);
  border-color: var(--accent);
}

:deep(.flatpickr-day.today) {
  border-color: var(--accent);
}

:deep(.flatpickr-day:hover) {
  background: var(--panel);
}

:deep(.flatpickr-time input) {
  font-size: 13px;
}

:deep(.flatpickr-current-month) {
  font-size: 14px;
}

/* Matches Container & Detail Card Styles */
.matches-container {
  display: flex;
  gap: 16px;
  align-items: flex-start;
}

.table-card {
  flex: 1;
  min-width: 0;
}

.detail-card {
  width: 380px;
  flex-shrink: 0;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  max-height: 600px;
  box-shadow: 0 2px 8px rgba(15, 23, 36, 0.08);
}

.detail-card-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 16px;
  border-bottom: 1px solid var(--border);
  background: var(--panel);
  gap: 12px;
}

.detail-card-title-section {
  display: flex;
  gap: 12px;
  align-items: flex-start;
  flex: 1;
  min-width: 0;
}

.detail-card-match-badge {
  flex-shrink: 0;
}

.match-badge-lg {
  width: 44px;
  height: 44px;
  border-radius: 8px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 12px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  letter-spacing: 0.5px;
}

.detail-card-match-name {
  font-size: 15px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
  line-height: 1.2;
}

.detail-card-match-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink3);
  font-weight: 500;
}

.detail-card-close {
  border: none;
  background: transparent;
  color: var(--ink3);
  cursor: pointer;
  padding: 4px;
  border-radius: 6px;
  transition: all 0.15s;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.detail-card-close:hover {
  background: var(--border);
  color: var(--ink);
}

.detail-card-content {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
}

.detail-section {
  margin-bottom: 20px;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}

.detail-section:last-child {
  border-bottom: none;
  margin-bottom: 0;
  padding-bottom: 0;
}

.detail-section-title {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--ink3);
  margin: 0 0 10px;
}

.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding: 6px 0;
  gap: 10px;
}

.detail-label {
  font-size: 12px;
  color: var(--ink3);
  font-weight: 500;
  flex-shrink: 0;
  min-width: 100px;
}

.detail-value {
  font-size: 12px;
  color: var(--ink);
  font-weight: 500;
  text-align: right;
  word-break: break-word;
}

.team-detail {
  display: flex;
  align-items: center;
  gap: 6px;
}

.team-code-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 28px;
  height: 20px;
  padding: 0 5px;
  border-radius: 4px;
  background: var(--panel);
  font-size: 10px;
  font-weight: 700;
  color: var(--ink);
  border: 1px solid var(--border);
}

.detail-card-footer {
  display: flex;
  gap: 8px;
  padding: 12px 16px;
  border-top: 1px solid var(--border);
  background: var(--panel);
  justify-content: flex-end;
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

@media (max-width: 1200px) {
  .matches-container {
    flex-direction: column;
  }

  .table-card.with-panel,
  .matches-grid-wrapper.with-panel {
    width: 100%;
  }

  .detail-card {
    width: 100%;
  }
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    align-items: stretch;
  }

  .header-actions {
    width: 100%;
  }

  .table-header {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .table-controls {
    flex-wrap: wrap;
    width: 100%;
  }

  .search-box {
    flex: 1;
    min-width: 200px;
  }

  .search-input {
    width: 100%;
  }

  .filter-select {
    flex: 1;
    min-width: 150px;
  }

  /* Adjust grid for mobile */
  .matches-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}

/* Processing Overlay */
.processing-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(2px);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  z-index: 100;
  border-radius: 8px;
}

.processing-spinner {
  width: 40px;
  height: 40px;
  border: 3px solid var(--border);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.processing-text {
  font-size: 14px;
  font-weight: 500;
  color: var(--ink2);
}
</style>
