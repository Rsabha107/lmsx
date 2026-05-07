<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Teams & Delegations</h1>
        <p class="page-sub">{{ filteredTeams.length }} of {{ teams.length }} teams</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['teams']" />
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
          Add Team
        </Button>
      </div>
    </div>

    <!-- Summary stats -->
    <div class="stats-grid">
      <mini-stat label="Total Teams" :value="teams.length" />
      <mini-stat label="Total Delegation" :value="totalDelegation" />
      <mini-stat label="Players" :value="totalPlayers" tone="primary" />
      <mini-stat label="Staff" :value="totalStaff" tone="ok" />
    </div>

    <!-- Teams Table Controls -->
    <div class="table-header">
      <div class="table-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Search teams..." 
            class="search-input"
          />
        </div>
        <select v-model="filterClassification" class="filter-select">
          <option value="">All Classifications</option>
          <option v-for="classification in uniqueClassifications" :key="classification" :value="classification">
            {{ classification }}
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

    <!-- Teams Grid/Table and Detail Panel Container -->
    <div class="teams-container">
      <!-- Grid View -->
      <div v-if="activeViewMode === 'grid'" class="teams-grid-wrapper" :class="{ 'with-panel': selectedTeam }">
        <div class="teams-grid">
          <div v-for="team in filteredTeams" :key="team.code" class="team-card" @click="selectTeam(team)" :class="{ 'team-card--selected': selectedTeam?.code === team.code }">
            <div class="tc-header">
              <div class="team-badge-md">
                {{ team.code }}
              </div>
              <div class="tc-classification">
                <span v-if="team.classification" style="font-size: 10px; color: var(--ink3); font-weight: 600;">{{ team.classification.name }}</span>
              </div>
            </div>
            <div class="tc-name">{{ team.team_name }}</div>
            <div class="tc-country">
              <span v-if="team.flag" class="tc-flag">{{ team.flag }}</span>
              <span>{{ team.country?.country_name || '—' }}</span>
            </div>
            <div class="tc-stats">
              <div class="tc-stat">
                <div class="tc-stat-value">{{ team.party_size_total }}</div>
                <div class="tc-stat-label">Total</div>
              </div>
              <div class="tc-stat">
                <div class="tc-stat-value">{{ team.party_size_players }}</div>
                <div class="tc-stat-label">Players</div>
              </div>
              <div class="tc-stat">
                <div class="tc-stat-value">{{ team.party_size_staff }}</div>
                <div class="tc-stat-label">Staff</div>
              </div>
            </div>
            <div class="tc-info">
              <div class="tc-info-row" v-if="team.hotel_name">
                <svg-icon name="home" :size="12" />
                <span>{{ team.hotel_name }}</span>
              </div>
              <div class="tc-info-row" v-if="team.sc_liaison_name">
                <svg-icon name="user" :size="12" />
                <span>{{ team.sc_liaison_name }}</span>
              </div>
            </div>
            <div class="tc-footer">
              <button class="tc-action-btn" @click.stop="editTeam(team)">
                <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                  <path d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Edit
              </button>
              <button class="tc-action-btn" @click.stop="openDeleteModal(team)">
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
      <div v-else class="table-card" :class="{ 'with-panel': selectedTeam }">
        <div style="overflow-x: auto;">
          <table class="teams-table">
          <thead>
            <tr>
              <th>Code</th>
              <th>Team Name</th>
              <th v-if="visibleColumns.country">Country</th>
              <th v-if="visibleColumns.hotel">Hotel</th>
              <th v-if="visibleColumns.liaison">SC Liaison</th>
              <th v-if="visibleColumns.group">Group/Pool</th>
              <th v-if="visibleColumns.delegationSize" class="center">Party</th>
              <th v-if="visibleColumns.players" class="center">Players</th>
              <th v-if="visibleColumns.staff" class="center">Staff</th>
              <th v-if="visibleColumns.training">Training Ground</th>
              <th v-if="visibleColumns.originAirport">Origin Airport</th>
              <th v-if="visibleColumns.destinationAirport">Destination Airport</th>
              <th v-if="visibleColumns.gate">Gate</th>
              <th v-if="visibleColumns.arrival">Arrival</th>
              <th v-if="visibleColumns.departure">Departure</th>
              <th v-if="visibleColumns.headOfDelegation">Head of Delegation</th>
              <th v-if="visibleColumns.liaisonPhone">Liaison Phone</th>
              <th v-if="visibleColumns.bibColor">Bib Color</th>
              <th v-if="visibleColumns.notes">Notes</th>
              <th class="center" style="width: 120px;">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="team in filteredTeams" 
              :key="team.code" 
              class="table-row" 
              :class="{ 
                'table-row--selected': selectedTeam?.code === team.code,
                'table-row--multi-flight': team.arrival_manifest && team.arrival_manifest.flights && team.arrival_manifest.flights.length > 0
              }"
              @click="selectTeam(team)"
            >
              <td class="code-cell">
                <span 
                  class="team-badge-sm" 
                  :style="team.bib_accent_color ? { 
                    color: team.bib_accent_color, 
                    backgroundColor: team.bib_accent_color + '15',
                    border: `1px solid ${team.bib_accent_color}`
                  } : {}"
                >{{ team.code }}</span>
              </td>
              <td class="team-name-cell">
                <div class="team-name-primary">
                  {{ team.team_name }}
                  <span v-if="team.arrival_manifest && team.arrival_manifest.flights && team.arrival_manifest.flights.length > 0" class="multi-flight-badge" :title="`${team.arrival_manifest.flights.length} flights`">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                    </svg>
                    {{ team.arrival_manifest.flights.length }}
                  </span>
                </div>
                <div class="team-name-secondary">
                  <span class="flag-small">{{ team.flag }}</span>
                  <span class="classification-small">{{ team.classification?.name || '—' }}</span>
                </div>
              </td>
              <td v-if="visibleColumns.country">
                <div class="country-name">{{ team.country?.country_name || team.country_id }}</div>
                <div class="country-code">{{ team.country?.country_code || '' }}</div>
              </td>
              <td v-if="visibleColumns.hotel">{{ team.hotel_name }}</td>
              <td v-if="visibleColumns.liaison">{{ team.sc_liaison_name }}</td>
              <td v-if="visibleColumns.group" class="mono">{{ team.group_pool }}</td>
              <td v-if="visibleColumns.delegationSize" class="mono center">{{ team.party_size_total }}</td>
              <td v-if="visibleColumns.players" class="mono center">{{ team.party_size_players }}</td>
              <td v-if="visibleColumns.staff" class="mono center">{{ team.party_size_staff }}</td>
              <td v-if="visibleColumns.training">{{ team.training_ground }}</td>
              <td v-if="visibleColumns.originAirport">{{ team.origin_airport?.name || '—' }}</td>
              <td v-if="visibleColumns.destinationAirport">{{ team.destination_airport?.name || '—' }}</td>
              <td v-if="visibleColumns.gate" class="mono">{{ team.gate || '—' }}</td>
              <td v-if="visibleColumns.arrival" class="mono">{{ formatDate(team.arrival_date_time) }}</td>
              <td v-if="visibleColumns.departure" class="mono">{{ formatDate(team.departure_date_time) }}</td>
              <td v-if="visibleColumns.headOfDelegation">{{ team.head_of_delegation }}</td>
              <td v-if="visibleColumns.liaisonPhone" class="mono">{{ team.sc_liaison_phone }}</td>
              <td v-if="visibleColumns.bibColor">
                <div v-if="team.bib_accent_color" class="color-swatch-wrapper">
                  <span class="color-swatch" :style="{ backgroundColor: team.bib_accent_color }"></span>
                  <span class="color-code">{{ team.bib_accent_color }}</span>
                </div>
                <span v-else>—</span>
              </td>
              <td v-if="visibleColumns.notes" class="notes-cell">{{ team.notes || '—' }}</td>
              <td class="actions-cell" @click.stop>
                <TableActions 
                  :is-deleting="deleting && teamToDelete?.code === team.code" 
                  @edit="editTeam(team)" 
                  @delete="openDeleteModal(team)" 
                />
              </td>
            </tr>
          </tbody>
        </table>
        </div>
      </div>

      <!-- Team Detail Card -->
      <transition name="slide-card">
        <div v-if="selectedTeam" class="detail-card">
          <div class="detail-card-header">
            <div class="detail-card-title-section">
              <div class="detail-card-team-badge">
                <span 
                  class="team-badge-lg" 
                  :style="selectedTeam.bib_accent_color ? { 
                    color: selectedTeam.bib_accent_color, 
                    backgroundColor: selectedTeam.bib_accent_color + '15',
                    border: `2px solid ${selectedTeam.bib_accent_color}`
                  } : {}"
                >{{ selectedTeam.code }}</span>
              </div>
              <div>
                <h3 class="detail-card-team-name">{{ selectedTeam.team_name }}</h3>
                <div class="detail-card-team-meta">
                  <span class="flag-large">{{ selectedTeam.flag }}</span>
                  <span>{{ selectedTeam.classification?.name || 'No classification' }}</span>
                </div>
              </div>
            </div>
            <button @click="selectedTeam = null" class="detail-card-close">
              <svg-icon name="x" :size="18" />
            </button>
          </div>

          <div class="detail-card-content">
            <!-- Country Info -->
            <div class="detail-section">
              <h4 class="detail-section-title">Country</h4>
              <div class="detail-row">
                <span class="detail-label">Name</span>
                <span class="detail-value">{{ selectedTeam.country?.country_name || selectedTeam.country_id }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Code</span>
                <span class="detail-value mono">{{ selectedTeam.country?.country_code || '—' }}</span>
              </div>
            </div>

            <!-- Party Size -->
            <div class="detail-section">
              <h4 class="detail-section-title">Delegation Size</h4>
              <div class="detail-stats">
                <div class="detail-stat">
                  <span class="detail-stat-value">{{ selectedTeam.party_size_total }}</span>
                  <span class="detail-stat-label">Total</span>
                </div>
                <div class="detail-stat">
                  <span class="detail-stat-value" style="color: var(--accent);">{{ selectedTeam.party_size_players }}</span>
                  <span class="detail-stat-label">Players</span>
                </div>
                <div class="detail-stat">
                  <span class="detail-stat-value" style="color: #0D9488;">{{ selectedTeam.party_size_staff }}</span>
                  <span class="detail-stat-label">Staff</span>
                </div>
              </div>
            </div>

            <!-- Accommodation & Training -->
            <div class="detail-section">
              <h4 class="detail-section-title">Accommodation & Training</h4>
              <div class="detail-row">
                <span class="detail-label">Hotel</span>
                <span class="detail-value">{{ selectedTeam.hotel_name || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Training Ground</span>
                <span class="detail-value">{{ selectedTeam.training_ground || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Group/Pool</span>
                <span class="detail-value mono">{{ selectedTeam.group_pool || '—' }}</span>
              </div>
            </div>

            <!-- Airports -->
            <div class="detail-section">
              <h4 class="detail-section-title">Airports</h4>
              <div class="detail-row">
                <span class="detail-label">Origin</span>
                <span class="detail-value">{{ selectedTeam.origin_airport?.name || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Destination</span>
                <span class="detail-value">{{ selectedTeam.destination_airport?.name || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Flight Number</span>
                <span class="detail-value mono">{{ selectedTeam.flight_number || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Gate</span>
                <span class="detail-value mono">{{ selectedTeam.gate || '—' }}</span>
              </div>
            </div>

            <!-- Schedule -->
            <div class="detail-section">
              <h4 class="detail-section-title">Schedule</h4>
              <div class="detail-row">
                <span class="detail-label">Arrival</span>
                <span class="detail-value mono">{{ formatDate(selectedTeam.arrival_date_time) }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Departure</span>
                <span class="detail-value mono">{{ formatDate(selectedTeam.departure_date_time) }}</span>
              </div>
            </div>

            <!-- Multiple Arrivals -->
            <div v-if="selectedTeam.arrival_manifest && selectedTeam.arrival_manifest.flights && selectedTeam.arrival_manifest.flights.length > 0" class="detail-section">
              <h4 class="detail-section-title">Multiple Arrivals ({{ selectedTeam.arrival_manifest.flights.length }} flights)</h4>
              <div v-for="(flight, index) in selectedTeam.arrival_manifest.flights" :key="index" class="flight-detail">
                <div class="flight-detail-header">
                  <span class="flight-badge">Flight {{ index + 1 }}</span>
                  <span class="flight-number">{{ flight.flight_number }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Group</span>
                  <span class="detail-value">{{ flight.group }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Passengers</span>
                  <span class="detail-value">{{ flight.passengers }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Arrival</span>
                  <span class="detail-value mono">{{ flight.arrival_time }}</span>
                </div>
                <div class="detail-row">
                  <span class="detail-label">Terminal/Gate</span>
                  <span class="detail-value">{{ flight.terminal }} - {{ flight.gate }}</span>
                </div>
              </div>
            </div>

            <!-- Contacts -->
            <div class="detail-section">
              <h4 class="detail-section-title">Contacts</h4>
              <div class="detail-row">
                <span class="detail-label">Head of Delegation</span>
                <span class="detail-value">{{ selectedTeam.head_of_delegation || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">SC Liaison</span>
                <span class="detail-value">{{ selectedTeam.sc_liaison_name || '—' }}</span>
              </div>
              <div class="detail-row">
                <span class="detail-label">Liaison Phone</span>
                <span class="detail-value mono">{{ selectedTeam.sc_liaison_phone || '—' }}</span>
              </div>
            </div>

            <!-- Bib Color -->
            <div v-if="selectedTeam.bib_accent_color" class="detail-section">
              <h4 class="detail-section-title">Bib Color</h4>
              <div class="detail-row">
                <span class="detail-label">Color</span>
                <div class="color-display">
                  <span class="color-swatch-lg" :style="{ backgroundColor: selectedTeam.bib_accent_color }"></span>
                  <span class="detail-value mono">{{ selectedTeam.bib_accent_color }}</span>
                </div>
              </div>
            </div>

            <!-- Notes -->
            <div v-if="selectedTeam.notes" class="detail-section">
              <h4 class="detail-section-title">Notes</h4>
              <p class="detail-notes">{{ selectedTeam.notes }}</p>
            </div>
          </div>

          <div class="detail-card-footer">
            <Button variant="secondary" size="sm" @click="selectedTeam = null">Close</Button>
            <Button variant="primary" size="sm" @click="editTeam(selectedTeam); selectedTeam = null;">Edit Team</Button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Add Team Modal -->
    <Modal :show="showAddModal" @close="showAddModal = false" max-width="700px">
      <template #title>Add New Team</template>
      
      <form @submit.prevent="submitTeam" class="team-form" style="position: relative;">
        <!-- Processing Overlay -->
        <div v-if="processing" class="processing-overlay">
          <div class="processing-spinner"></div>
          <div class="processing-text">Adding team...</div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Team Code <span class="required">*</span></label>
            <input 
              v-model="formData.code" 
              type="text" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.code }"
              maxlength="10" 
              placeholder="e.g., MER" 
              @input="validationErrors.code = ''"
            />
            <span v-if="validationErrors.code" class="form-error">{{ validationErrors.code }}</span>
          </div>
          
          <div class="form-group">
            <label class="form-label">Team Name <span class="required">*</span></label>
            <input 
              v-model="formData.team_name" 
              type="text" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.team_name }"
              placeholder="e.g., Mercure FC" 
              @input="validationErrors.team_name = ''"
            />
            <span v-if="validationErrors.team_name" class="form-error">{{ validationErrors.team_name }}</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Country <span class="required">*</span></label>
            <select 
              v-model="formData.country_id" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.country_id }"
              @change="validationErrors.country_id = ''"
            >
              <option value="">Select Country</option>
              <option v-for="country in countries" :key="country.country_code" :value="country.country_code">
                {{ country.country_name }}
              </option>
            </select>
            <span v-if="validationErrors.country_id" class="form-error">{{ validationErrors.country_id }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Classification Type</label>
            <select v-model="formData.classification_type_id" class="form-input">
              <option value="">Select Type</option>
              <option v-for="classification in classifications" :key="classification.id" :value="classification.id">
                {{ classification.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Flag</label>
            <input v-model="formData.flag" type="text" class="form-input" maxlength="10" placeholder="e.g., 🇫🇷" />
          </div>

          <div class="form-group">
            <label class="form-label">Group/Pool</label>
            <input v-model="formData.group_pool" type="text" class="form-input" placeholder="e.g., Group A" />
          </div>
        </div>

        <div class="form-section-title">Party Size</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Total <span style="color: var(--ink3); font-weight: 400;">({{ hasMultipleFlights ? 'base + flights' : 'players + staff' }})</span></label>
            <input 
              :value="displayPartyTotal" 
              type="number" 
              class="form-input" 
              min="0" 
              readonly
              style="background: var(--sky-dust); cursor: not-allowed;"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Players</label>
            <input v-model.number="formData.party_size_players" type="number" class="form-input" min="0" />
          </div>

          <div class="form-group">
            <label class="form-label">Staff</label>
            <input v-model.number="formData.party_size_staff" type="number" class="form-input" min="0" />
          </div>
        </div>

        <div class="form-section-title">Accommodation & Training</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Hotel Name</label>
            <input v-model="formData.hotel_name" type="text" class="form-input" placeholder="Hotel name" />
          </div>

          <div class="form-group">
            <label class="form-label">Training Ground</label>
            <input v-model="formData.training_ground" type="text" class="form-input" placeholder="Training ground" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Origin Airport</label>
            <select v-model="formData.origin_airport_id" class="form-input">
              <option value="">Select Origin Airport</option>
              <option v-for="airport in airports" :key="airport.id" :value="airport.id">
                {{ airport.code }} - {{ airport.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Destination Airport</label>
            <select v-model="formData.destination_airport_id" class="form-input">
              <option value="">Select Destination Airport</option>
              <option v-for="airport in airports" :key="airport.id" :value="airport.id">
                {{ airport.code }} - {{ airport.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-section-title">Flight Information</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Flight Number</label>
            <input v-model="formData.flight_number" type="text" class="form-input" placeholder="e.g., AC123" />
          </div>

          <div class="form-group">
            <label class="form-label">Gate</label>
            <input v-model="formData.gate" type="text" class="form-input" placeholder="e.g., A12" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Arrival Date & Time</label>
            <input ref="arrivalDateInput" v-model="formData.arrival_date_time" type="text" class="form-input" placeholder="YYYY-MM-DD HH:MM" />
          </div>

          <div class="form-group">
            <label class="form-label">Departure Date & Time</label>
            <input ref="departureDateInput" v-model="formData.departure_date_time" type="text" class="form-input" placeholder="YYYY-MM-DD HH:MM" />
          </div>
        </div>

        <!-- Multiple Arrivals Section -->
        <div class="form-section-title">Multiple Arrivals (Optional)</div>
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label checkbox-label">
            <input type="checkbox" v-model="hasMultipleFlights" class="form-checkbox" />
            <span>This team arrives on multiple flights</span>
          </label>
          <p class="form-helper">Enable if the team is split across different flights with different arrival times.</p>
        </div>

        <div v-if="hasMultipleFlights" class="multi-flights-section">
          <div v-for="(flight, index) in formData.flights" :key="index" class="flight-entry">
            <div class="flight-entry-header">
              <h5 class="flight-entry-title">Flight {{ index + 1 }}</h5>
              <button type="button" @click="removeFlight(index)" class="btn-remove">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                  <path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Remove
              </button>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Flight Number</label>
                <input v-model="flight.flight_number" class="form-input" placeholder="e.g., AF123" />
              </div>
              <div class="form-group">
                <label class="form-label">Group/Category</label>
                <input v-model="flight.group" class="form-input" placeholder="e.g., Players, Staff, Equipment" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Passengers</label>
                <input v-model.number="flight.passengers" type="number" class="form-input" min="0" />
              </div>
              <div class="form-group">
                <label class="form-label">Arrival Time</label>
                <input v-model="flight.arrival_time" class="form-input" placeholder="YYYY-MM-DD HH:MM" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Terminal</label>
                <input v-model="flight.terminal" class="form-input" placeholder="e.g., CDG T2" />
              </div>
              <div class="form-group">
                <label class="form-label">Gate</label>
                <input v-model="flight.gate" class="form-input" placeholder="e.g., A12" />
              </div>
            </div>
          </div>
          <button type="button" @click="addFlight" class="btn-add-flight">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;">
              <path d="M8 2V14M2 8H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Add Another Flight
          </button>
        </div>

        <div class="form-section-title">Contacts</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Head of Delegation</label>
            <input v-model="formData.head_of_delegation" type="text" class="form-input" placeholder="Name" />
          </div>

          <div class="form-group">
            <label class="form-label">SC Liaison Name</label>
            <input v-model="formData.sc_liaison_name" type="text" class="form-input" placeholder="Name" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">SC Liaison Phone</label>
            <input v-model="formData.sc_liaison_phone" type="text" class="form-input" placeholder="Phone number" />
          </div>

          <div class="form-group">
            <label class="form-label">Bib Accent Color</label>
            <input v-model="formData.bib_accent_color" type="text" class="form-input" placeholder="#0055A4" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="formData.notes" class="form-textarea" rows="3" placeholder="Additional notes..."></textarea>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showAddModal = false" :disabled="processing">Cancel</Button>
        <Button variant="primary" size="sm" @click="submitTeam" :processing="processing" :disabled="processing">Add Team</Button>
      </template>
    </Modal>

    <!-- Edit Team Modal -->
    <Modal :show="showEditModal" @close="showEditModal = false" max-width="700px">
      <template #title>Edit Team</template>
      
      <form @submit.prevent="submitTeam" class="team-form" style="position: relative;">
        <!-- Processing Overlay -->
        <div v-if="processing" class="processing-overlay">
          <div class="processing-spinner"></div>
          <div class="processing-text">Updating team...</div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Team Code <span class="required">*</span></label>
            <input 
              v-model="formData.code" 
              type="text" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.code }"
              maxlength="10" 
              placeholder="e.g., MER" 
              @input="validationErrors.code = ''"
              disabled
            />
            <span v-if="validationErrors.code" class="form-error">{{ validationErrors.code }}</span>
          </div>
          
          <div class="form-group">
            <label class="form-label">Team Name <span class="required">*</span></label>
            <input 
              v-model="formData.team_name" 
              type="text" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.team_name }"
              placeholder="e.g., Mercure FC" 
              @input="validationErrors.team_name = ''"
            />
            <span v-if="validationErrors.team_name" class="form-error">{{ validationErrors.team_name }}</span>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Country <span class="required">*</span></label>
            <select 
              v-model="formData.country_id" 
              class="form-input" 
              :class="{ 'form-input--error': validationErrors.country_id }"
              @change="validationErrors.country_id = ''"
            >
              <option value="">Select Country</option>
              <option v-for="country in countries" :key="country.country_code" :value="country.country_code">
                {{ country.country_name }}
              </option>
            </select>
            <span v-if="validationErrors.country_id" class="form-error">{{ validationErrors.country_id }}</span>
          </div>

          <div class="form-group">
            <label class="form-label">Classification Type</label>
            <select v-model="formData.classification_type_id" class="form-input">
              <option value="">Select Type</option>
              <option v-for="classification in classifications" :key="classification.id" :value="classification.id">
                {{ classification.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Flag</label>
            <input v-model="formData.flag" type="text" class="form-input" maxlength="10" placeholder="e.g., 🇫🇷" />
          </div>

          <div class="form-group">
            <label class="form-label">Group/Pool</label>
            <input v-model="formData.group_pool" type="text" class="form-input" placeholder="e.g., Group A" />
          </div>
        </div>

        <div class="form-section-title">Party Size</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Total <span style="color: var(--ink3); font-weight: 400;">({{ hasMultipleFlights ? 'base + flights' : 'players + staff' }})</span></label>
            <input 
              :value="displayPartyTotal" 
              type="number" 
              class="form-input" 
              min="0" 
              readonly
              style="background: var(--sky-dust); cursor: not-allowed;"
            />
          </div>

          <div class="form-group">
            <label class="form-label">Players</label>
            <input v-model.number="formData.party_size_players" type="number" class="form-input" min="0" />
          </div>

          <div class="form-group">
            <label class="form-label">Staff</label>
            <input v-model.number="formData.party_size_staff" type="number" class="form-input" min="0" />
          </div>
        </div>

        <div class="form-section-title">Accommodation & Training</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Hotel Name</label>
            <input v-model="formData.hotel_name" type="text" class="form-input" placeholder="Hotel name" />
          </div>

          <div class="form-group">
            <label class="form-label">Training Ground</label>
            <input v-model="formData.training_ground" type="text" class="form-input" placeholder="Training ground" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Origin Airport</label>
            <select v-model="formData.origin_airport_id" class="form-input">
              <option value="">Select Origin Airport</option>
              <option v-for="airport in airports" :key="airport.id" :value="airport.id">
                {{ airport.code }} - {{ airport.name }}
              </option>
            </select>
          </div>

          <div class="form-group">
            <label class="form-label">Destination Airport</label>
            <select v-model="formData.destination_airport_id" class="form-input">
              <option value="">Select Destination Airport</option>
              <option v-for="airport in airports" :key="airport.id" :value="airport.id">
                {{ airport.code }} - {{ airport.name }}
              </option>
            </select>
          </div>
        </div>

        <div class="form-section-title">Flight Information</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Flight Number</label>
            <input v-model="formData.flight_number" type="text" class="form-input" placeholder="e.g., AC123" />
          </div>

          <div class="form-group">
            <label class="form-label">Gate</label>
            <input v-model="formData.gate" type="text" class="form-input" placeholder="e.g., A12" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Arrival Date & Time</label>
            <input ref="arrivalDateInput" v-model="formData.arrival_date_time" type="text" class="form-input" placeholder="YYYY-MM-DD HH:MM" />
          </div>

          <div class="form-group">
            <label class="form-label">Departure Date & Time</label>
            <input ref="departureDateInput" v-model="formData.departure_date_time" type="text" class="form-input" placeholder="YYYY-MM-DD HH:MM" />
          </div>
        </div>

                <!-- Multiple Arrivals Section (Edit) -->
        <div class="form-section-title" style="margin-top: 20px;">Multiple Arrivals (Optional)</div>
        <div class="form-group" style="margin-bottom: 12px;">
          <label class="form-label checkbox-label">
            <input type="checkbox" v-model="hasMultipleFlights" class="form-checkbox" />
            <span>This team arrives on multiple flights</span>
          </label>
          <p class="form-helper">Enable if the team is split across different flights with different arrival times.</p>
        </div>

                <div v-if="hasMultipleFlights" class="multi-flights-section">
          <div v-for="(flight, index) in formData.flights" :key="index" class="flight-entry">
            <div class="flight-entry-header">
              <h5 class="flight-entry-title">Flight {{ index + 1 }}</h5>
              <button type="button" @click="removeFlight(index)" class="btn-remove">
                <svg width="14" height="14" viewBox="0 0 16 16" fill="none">
                  <path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Remove
              </button>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Flight Number</label>
                <input v-model="flight.flight_number" class="form-input" placeholder="e.g., AF123" />
              </div>
              <div class="form-group">
                <label class="form-label">Group/Category</label>
                <input v-model="flight.group" class="form-input" placeholder="e.g., Players, Staff, Equipment" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Passengers</label>
                <input v-model.number="flight.passengers" type="number" class="form-input" min="0" />
              </div>
              <div class="form-group">
                <label class="form-label">Arrival Time</label>
                <input v-model="flight.arrival_time" class="form-input" placeholder="YYYY-MM-DD HH:MM" />
              </div>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Terminal</label>
                <input v-model="flight.terminal" class="form-input" placeholder="e.g., CDG T2" />
              </div>
              <div class="form-group">
                <label class="form-label">Gate</label>
                <input v-model="flight.gate" class="form-input" placeholder="e.g., A12" />
              </div>
            </div>
          </div>
          <button type="button" @click="addFlight" class="btn-add-flight">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" style="margin-right: 6px;">
              <path d="M8 2V14M2 8H14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Add Another Flight
          </button>
        </div>

        <div class="form-section-title">Contacts</div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Head of Delegation</label>
            <input v-model="formData.head_of_delegation" type="text" class="form-input" placeholder="Name" />
          </div>

          <div class="form-group">
            <label class="form-label">SC Liaison Name</label>
            <input v-model="formData.sc_liaison_name" type="text" class="form-input" placeholder="Name" />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">SC Liaison Phone</label>
            <input v-model="formData.sc_liaison_phone" type="text" class="form-input" placeholder="Phone number" />
          </div>

          <div class="form-group">
            <label class="form-label">Bib Accent Color</label>
            <input v-model="formData.bib_accent_color" type="text" class="form-input" placeholder="#0055A4" />
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="formData.notes" class="form-textarea" rows="3" placeholder="Additional notes..."></textarea>
        </div>

      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showEditModal = false" :disabled="processing">Cancel</Button>
        <Button variant="primary" size="sm" @click="submitTeam" :processing="processing" :disabled="processing">Update Team</Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal :show="showDeleteModal" @close="closeDeleteModal" max-width="500px">
      <template #title>Delete Team</template>
      
      <div style="padding: 0;">
        <p style="margin: 0 0 12px; color: var(--ink2); font-size: 14px;">
          Are you sure you want to delete <strong>{{ teamToDelete?.team_name }}</strong>?
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
          @click="deleteTeam" 
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
import StatusPill from '../Components/StatusPill.vue';
import MiniStat from '../Components/MiniStat.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import Button from '../Components/Button.vue';
import Modal from '../Components/Modal.vue';
import ColumnToggle from '../Components/ColumnToggle.vue';
import TableActions from '../Components/TableActions.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  teams: {
    type: Array,
    required: true,
  },
  classifications: {
    type: Array,
    required: true,
  },
  countries: {
    type: Array,
    required: true,
  },
  airports: {
    type: Array,
    required: true,
  },
});

const selectedTeam = ref(null);
const searchQuery = ref('');
const filterClassification = ref('');
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
const teamToDelete = ref(null);
const arrivalDateInput = ref(null);
const departureDateInput = ref(null);
const hasMultipleFlights = ref(false);
let arrivalPicker = null;
let departurePicker = null;

const formData = ref({
  code: '',
  team_name: '',
  country_id: '',
  flag: '',
  group_pool: '',
  classification_type_id: '',
  party_size_total: 0,
  party_size_players: 0,
  party_size_staff: 0,
  hotel_name: '',
  training_ground: '',
  origin_airport_id: '',
  destination_airport_id: '',
  gate: '',
  flight_number: '',
  arrival_date_time: '',
  departure_date_time: '',
  arrival_manifest: null,
  flights: [],
  head_of_delegation: '',
  sc_liaison_name: '',
  sc_liaison_phone: '',
  bib_accent_color: '',
  notes: '',
});

const visibleColumns = ref({
  country: true,
  group: false,
  delegationSize: true,
  players: false,
  staff: false,
  hotel: true,
  training: false,
  originAirport: false,
  destinationAirport: false,
  gate: false,
  arrival: false,
  departure: false,
  headOfDelegation: false,
  liaison: true,
  liaisonPhone: false,
  bibColor: false,
  notes: false,
});

const availableColumns = [
  { key: 'country', label: 'Country', required: false },
  { key: 'group', label: 'Group/Pool', required: false },
  { key: 'delegationSize', label: 'Party', required: false },
  { key: 'players', label: 'Players', required: false },
  { key: 'staff', label: 'Staff', required: false },
  { key: 'hotel', label: 'Hotel', required: false },
  { key: 'training', label: 'Training Ground', required: false },
  { key: 'originAirport', label: 'Origin Airport', required: false },
  { key: 'destinationAirport', label: 'Destination Airport', required: false },
  { key: 'gate', label: 'Gate', required: false },
  { key: 'arrival', label: 'Arrival', required: false },
  { key: 'departure', label: 'Departure', required: false },
  { key: 'headOfDelegation', label: 'Head of Delegation', required: false },
  { key: 'liaison', label: 'SC Liaison', required: false },
  { key: 'liaisonPhone', label: 'Liaison Phone', required: false },
  { key: 'bibColor', label: 'Bib Color', required: false },
  { key: 'notes', label: 'Notes', required: false },
];

const uniqueClassifications = computed(() => {
  const classifications = props.teams
    .map(t => t.classification?.name)
    .filter(Boolean);
  return [...new Set(classifications)].sort();
});

const filteredTeams = computed(() => {
  let result = props.teams;

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(team => 
      team.code?.toLowerCase().includes(query) ||
      team.team_name?.toLowerCase().includes(query) ||
      team.country?.country_name?.toLowerCase().includes(query) ||
      team.country_id?.toLowerCase().includes(query) ||
      team.hotel_name?.toLowerCase().includes(query) ||
      team.group_pool?.toLowerCase().includes(query)
    );
  }

  // Filter by classification
  if (filterClassification.value) {
    result = result.filter(team => 
      team.classification?.name === filterClassification.value
    );
  }

  return result;
});

const totalDelegation = computed(() => 
  filteredTeams.value.reduce((sum, t) => sum + t.party_size_total, 0)
);

const totalPlayers = computed(() => 
  filteredTeams.value.reduce((sum, t) => sum + t.party_size_players, 0)
);

const totalStaff = computed(() => 
  filteredTeams.value.reduce((sum, t) => sum + t.party_size_staff, 0)
);

// Calculate total passengers from all flights
const totalPassengersFromFlights = computed(() => {
  if (!hasMultipleFlights.value || !formData.value.flights.length) {
    return 0;
  }
  return formData.value.flights.reduce((sum, flight) => sum + (flight.passengers || 0), 0);
});

// Calculate total from players + staff (when not using multiple flights)
const calculatedPartyTotal = computed(() => {
  const players = formData.value.party_size_players || 0;
  const staff = formData.value.party_size_staff || 0;
  return players + staff;
});

// Get the appropriate total based on mode
const displayPartyTotal = computed(() => {
  if (hasMultipleFlights.value) {
    return calculatedPartyTotal.value + totalPassengersFromFlights.value;
  }
  return calculatedPartyTotal.value;
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

function selectTeam(team) {
  selectedTeam.value = team;
}

function toggleColumn(key) {
  visibleColumns.value[key] = !visibleColumns.value[key];
}

function addFlight() {
  formData.value.flights.push({
    flight_number: '',
    group: '',
    passengers: 0,
    arrival_time: '',
    terminal: '',
    gate: ''
  });
}

function removeFlight(index) {
  formData.value.flights.splice(index, 1);
}

function openAddModal() {
  // Reset form data
  formData.value = {
    code: '',
    team_name: '',
    country_id: '',
    flag: '',
    group_pool: '',
    classification_type_id: '',
    party_size_total: 0,
    party_size_players: 0,
    party_size_staff: 0,
    hotel_name: '',
    training_ground: '',
    origin_airport_id: '',
    destination_airport_id: '',
    gate: '',
    flight_number: '',
    arrival_date_time: '',
    departure_date_time: '',
    arrival_manifest: null,
    flights: [],
    head_of_delegation: '',
    sc_liaison_name: '',
    sc_liaison_phone: '',
    bib_accent_color: '',
    notes: '',
  };
  hasMultipleFlights.value = false;
  showAddModal.value = true;
  validationErrors.value = {};
}

function editTeam(team) {
  // Check if team has multiple flights
  const hasManifest = team.arrival_manifest && team.arrival_manifest.flights && team.arrival_manifest.flights.length > 0;
  hasMultipleFlights.value = hasManifest;
  
  formData.value = {
    code: team.code,
    team_name: team.team_name,
    country_id: team.country_id,
    flag: team.flag || '',
    group_pool: team.group_pool || '',
    classification_type_id: team.classification_type_id || '',
    party_size_total: team.party_size_total || 0,
    party_size_players: team.party_size_players || 0,
    party_size_staff: team.party_size_staff || 0,
    hotel_name: team.hotel_name || '',
    training_ground: team.training_ground || '',
    origin_airport_id: team.origin_airport_id || '',
    destination_airport_id: team.destination_airport_id || '',
    gate: team.gate || '',
    flight_number: team.flight_number || '',
    arrival_date_time: team.arrival_date_time ? formatDate(team.arrival_date_time) : '',
    departure_date_time: team.departure_date_time ? formatDate(team.departure_date_time) : '',
    arrival_manifest: team.arrival_manifest || null,
    flights: hasManifest ? team.arrival_manifest.flights : [],
    head_of_delegation: team.head_of_delegation || '',
    sc_liaison_name: team.sc_liaison_name || '',
    sc_liaison_phone: team.sc_liaison_phone || '',
    bib_accent_color: team.bib_accent_color || '',
    notes: team.notes || '',
  };
  showEditModal.value = true;
  validationErrors.value = {};
}

function openDeleteModal(team) {
  teamToDelete.value = team;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  teamToDelete.value = null;
  showDeleteModal.value = false;
}

function deleteTeam() {
  if (!teamToDelete.value) return;
  
  deleting.value = true;
  router.delete(`/teams/${teamToDelete.value.code}`, {
    onSuccess: () => {
      showDeleteModal.value = false;
      teamToDelete.value = null;
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
  
  if (!formData.value.code || formData.value.code.trim() === '') {
    errors.code = 'Team code is required';
  } else if (formData.value.code.length > 10) {
    errors.code = 'Team code must not exceed 10 characters';
  }
  
  if (!formData.value.team_name || formData.value.team_name.trim() === '') {
    errors.team_name = 'Team name is required';
  }
  
  if (!formData.value.country_id || formData.value.country_id === '') {
    errors.country_id = 'Country is required';
  }
  
  validationErrors.value = errors;
  return Object.keys(errors).length === 0;
}

function submitTeam() {
  if (!validateForm()) {
    return;
  }
  
  // Always set party_size_total to the calculated value
  formData.value.party_size_total = displayPartyTotal.value;
  
  // Build arrival_manifest if multiple flights are enabled
  if (hasMultipleFlights.value && formData.value.flights.length > 0) {
    formData.value.arrival_manifest = {
      primary_arrival: formData.value.arrival_date_time,
      flights: formData.value.flights
    };
  } else {
    formData.value.arrival_manifest = null;
  }
  
  processing.value = true;
  const url = showEditModal.value ? `/teams/${formData.value.code}` : '/teams';
  const method = showEditModal.value ? 'put' : 'post';
  
  router[method](url, formData.value, {
    onSuccess: () => {
      showAddModal.value = false;
      showEditModal.value = false;
      processing.value = false;
      // Reset form
      hasMultipleFlights.value = false;
      formData.value = {
        code: '',
        team_name: '',
        country_id: '',
        flag: '',
        group_pool: '',
        classification_type_id: '',
        party_size_total: 0,
        party_size_players: 0,
        party_size_staff: 0,
        hotel_name: '',
        training_ground: '',
        origin_airport_id: '',
        destination_airport_id: '',
        gate: '',
        flight_number: '',
        arrival_date_time: '',
        departure_date_time: '',
        arrival_manifest: null,
        flights: [],
        head_of_delegation: '',
        sc_liaison_name: '',
        sc_liaison_phone: '',
        bib_accent_color: '',
        notes: '',
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
  if (arrivalDateInput.value) {
    arrivalPicker = flatpickr(arrivalDateInput.value, {
      enableTime: true,
      time_24hr: true,
      dateFormat: 'Y-m-d H:i',
      allowInput: true,
    });
  }
  
  if (departureDateInput.value) {
    departurePicker = flatpickr(departureDateInput.value, {
      enableTime: true,
      time_24hr: true,
      dateFormat: 'Y-m-d H:i',
      allowInput: true,
    });
  }
}

function destroyFlatpickr() {
  if (arrivalPicker) {
    arrivalPicker.destroy();
    arrivalPicker = null;
  }
  if (departurePicker) {
    departurePicker.destroy();
    departurePicker = null;
  }
}

function formatDate(dateString) {
  if (!dateString) return '—';
  
  // Parse string directly to avoid timezone conversion issues
  // Supports formats: YYYY-MM-DD HH:MM:SS or YYYY-MM-DD HH:MM
  const match = dateString.match(/^(\d{4})-(\d{2})-(\d{2})[\sT](\d{2}):(\d{2})(?::\d{2})?/);
  if (match) {
    const [, year, month, day, hours, minutes] = match;
    return `${year}-${month}-${day} ${hours}:${minutes}`;
  }
  
  // If format doesn't match, return original
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
.teams-grid-wrapper {
  flex: 1;
  min-width: 0;
}

.teams-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 14px;
}

.team-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 16px;
  cursor: pointer;
  transition: all 0.15s;
}

.team-card:hover {
  border-color: var(--accent);
  box-shadow: 0 2px 8px rgba(15, 23, 36, 0.08);
}

.team-card--selected {
  background: var(--accent-soft, #EEF0FE);
  border-color: var(--accent);
  box-shadow: 0 2px 8px rgba(99, 102, 241, 0.15);
}

.tc-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 12px;
}

.team-badge-md {
  width: 36px;
  height: 36px;
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

.tc-classification {
  flex: 1;
  text-align: right;
  padding-top: 2px;
}

.tc-name {
  font-size: 14px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 6px;
  line-height: 1.3;
}

.tc-country {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink3);
  margin-bottom: 12px;
}

.tc-flag {
  font-size: 14px;
}

.tc-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 8px;
  margin-bottom: 12px;
  padding: 10px 0;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
}

.tc-stat {
  text-align: center;
}

.tc-stat-value {
  font-size: 18px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
  margin-bottom: 4px;
}

.tc-stat-label {
  font-size: 10px;
  color: var(--ink3);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.tc-info {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 12px;
}

.tc-info-row {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--ink3);
}

.tc-info-row svg {
  color: var(--ink4);
  flex-shrink: 0;
}

.tc-footer {
  display: flex;
  gap: 8px;
  padding-top: 12px;
  border-top: 1px solid var(--border);
}

.tc-action-btn {
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

.tc-action-btn:hover {
  background: var(--panel);
  color: var(--ink);
  border-color: var(--ink3);
}

.teams-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}

.teams-table thead th {
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

.teams-table tbody tr {
  transition: background-color 0.13s;
  cursor: pointer;
}

.teams-table tbody tr:hover {
  background: var(--panel);
}

.teams-table tbody tr.table-row--selected {
  background: var(--accent-soft, #EEF0FE);
  border-left: 3px solid var(--accent);
}

.teams-table tbody tr.table-row--selected:hover {
  background: var(--accent-soft, #EEF0FE);
}

.teams-table tbody tr.table-row--multi-flight {
  border-left: 3px solid #0EA5E9;
}

.teams-table tbody tr.table-row--multi-flight.table-row--selected {
  border-left: 3px solid var(--accent);
}

.teams-table tbody td {
  padding: 11px 14px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}

.teams-table tbody tr:last-child td {
  border-bottom: none;
}

.code-cell {
  padding: 11px 14px;
}

.team-badge-sm {
  width: 26px;
  height: 26px;
  border-radius: 5px;
  flex-shrink: 0;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 9px;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.team-name-cell {
  font-weight: 600;
}

.team-name-primary {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 3px;
}

.team-name-secondary {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11.5px;
  color: var(--ink3);
}

.multi-flight-badge {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px 6px;
  margin-left: 6px;
  background: #E0F2FE;
  color: #0369A1;
  border: 1px solid #BAE6FD;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  vertical-align: middle;
}

.multi-flight-badge svg {
  flex-shrink: 0;
}

.flag-small {
  font-size: 12px;
  line-height: 1;
}

.classification-small {
  color: var(--ink3);
  font-weight: 500;
}

.country-name {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
}

.country-code {
  font-size: 11px;
  color: var(--ink3);
  font-family: var(--font-mono, monospace);
}

.mono {
  font-family: var(--font-mono, monospace);
  font-size: 12px;
  color: var(--ink3);
}

.center {
  text-align: center !important;
}

.teams-table td.center {
  text-align: center;
}

.teams-table th.center {
  text-align: center;
}

.notes-cell {
  color: var(--ink3);
  font-size: 12.5px;
  max-width: 200px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.color-swatch-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.color-swatch {
  display: inline-block;
  width: 20px;
  height: 20px;
  border-radius: 4px;
  border: 1px solid var(--border);
  flex-shrink: 0;
}

.color-code {
  font-family: var(--font-mono, monospace);
  font-size: 11px;
  color: var(--ink3);
  text-transform: uppercase;
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
.team-form {
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

.form-textarea {
  resize: vertical;
  min-height: 60px;
}

select.form-input {
  cursor: pointer;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 500;
  color: var(--ink);
}

.form-checkbox {
  width: 18px;
  height: 18px;
  border: 2px solid var(--border);
  border-radius: 4px;
  cursor: pointer;
  accent-color: var(--accent);
}

.form-helper {
  font-size: 12px;
  color: var(--ink3);
  margin: 4px 0 0 26px;
}

.multi-flights-section {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 16px;
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  margin-bottom: 8px;
}

.flight-entry {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 14px;
}

.flight-entry-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--border);
}

.flight-entry-title {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  margin: 0;
}

.btn-remove {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border: 1px solid #ef4444;
  border-radius: 5px;
  background: transparent;
  color: #ef4444;
  font-size: 12px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}

.btn-remove:hover {
  background: #ef4444;
  color: white;
}

.btn-add-flight {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  padding: 10px 16px;
  border: 1px dashed var(--border);
  border-radius: 6px;
  background: transparent;
  color: var(--accent);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}

.btn-add-flight:hover {
  background: var(--accent-soft);
  border-color: var(--accent);
}

.flight-detail {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 10px;
}

.flight-detail:last-child {
  margin-bottom: 0;
}

.flight-detail-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

.flight-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 3px 8px;
  background: var(--accent-soft);
  color: var(--accent);
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-radius: 4px;
}

.flight-number {
  font-size: 13px;
  font-weight: 600;
  color: var(--ink);
  font-family: var(--font-mono, monospace);
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

/* Teams Container & Detail Card Styles */
.teams-container {
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

.detail-card-team-badge {
  flex-shrink: 0;
}

.team-badge-lg {
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

.detail-card-team-name {
  font-size: 16px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
  line-height: 1.2;
}

.detail-card-team-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--ink3);
  font-weight: 500;
}

.flag-large {
  font-size: 16px;
  line-height: 1;
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

.detail-stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 10px;
  margin-top: 8px;
}

.detail-stat {
  background: var(--panel);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 10px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.detail-stat-value {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  line-height: 1;
}

.detail-stat-label {
  font-size: 10px;
  color: var(--ink3);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.detail-notes {
  font-size: 12px;
  color: var(--ink2);
  line-height: 1.5;
  margin: 0;
  padding: 10px;
  background: var(--panel);
  border-radius: 8px;
  border: 1px solid var(--border);
}

.color-display {
  display: flex;
  align-items: center;
  gap: 8px;
}

.color-swatch-lg {
  display: inline-block;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  border: 1px solid var(--border);
  flex-shrink: 0;
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
  .teams-container {
    flex-direction: column;
  }

  .table-card.with-panel,
  .teams-grid-wrapper.with-panel {
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
  .teams-grid {
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
