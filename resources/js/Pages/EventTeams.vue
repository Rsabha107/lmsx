<template>
  <app-layout>
    <!-- No Active Event State -->
    <div v-if="!activeEvent" class="empty-state-full">
      <div class="empty-state-icon">📅</div>
      <h2 class="empty-state-title">No Active Event</h2>
      <p class="empty-state-text">
        Please select an event from the dropdown above to view event teams.
      </p>
    </div>

    <!-- Active Event Content -->
    <div v-else>
      <div class="page-header">
        <div>
          <h1 class="page-title">{{ activeEvent.name }}</h1>
          <p class="page-sub">
            {{ eventTeams.length }} team{{
              eventTeams.length !== 1 ? "s" : ""
            }}
            participating
          </p>
        </div>
        <div class="header-actions">
          <RefreshButton :only="['eventTeams', 'activeEvent']" />
          <Button variant="secondary" size="sm" @click="openImportModal">
            <template #icon>
              <svg-icon name="upload" :size="14" />
            </template>
            Import Teams
          </Button>
          <Button variant="primary" size="sm" @click="openTeamForm(null)">
            <template #icon>
              <svg-icon name="plus" :size="14" style="color:#fff;" />
            </template>
            Add Team
          </Button>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-grid">
        <mini-stat label="Total Teams" :value="eventTeams.length" />
        <mini-stat
          label="With Flights"
          :value="teamsWithFlights"
          tone="primary"
        />
        <mini-stat
          label="With Accommodation"
          :value="teamsWithStay"
          tone="ok"
        />
        <mini-stat label="Total Flights" :value="totalFlights" />
      </div>

      <!-- Table controls -->
      <div class="table-header">
        <div class="table-controls">
          <div class="search-box">
            <svg-icon name="search" :size="14" />
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Search teams…"
              class="search-input"
            />
          </div>
        </div>
      </div>

      <!-- Teams Table -->
      <div class="table-card">
        <div style="overflow-x: auto">
          <table class="events-table">
            <thead>
              <tr>
                <th>Team</th>
                <th>Country</th>
                <th>Group / Pool</th>
                <th>Classification</th>
                <th class="center">Flights</th>
                <th>Accommodation</th>
                <th class="center">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="et in filteredTeams" :key="et.id" class="table-row">
                <td>
                  <div style="display: flex; align-items: center; gap: 8px">
                    <span class="team-badge-sm">{{ et.code }}</span>
                    <span style="font-weight: 500">{{
                      et.team_name
                    }}</span>
                  </div>
                </td>
                <td>
                  <span v-if="et.country" style="display: flex; align-items: center; gap: 6px">
                    <flag-icon :code="et.country_id" :fallback="et.country.flag" />
                    {{ et.country.country_name }}</span
                  >
                  <span v-else>—</span>
                </td>
                <td>{{ et.group_pool || "—" }}</td>
                <td>
                  <span v-if="et.classification">{{
                    et.classification.name
                  }}</span>
                  <span v-else>—</span>
                </td>
                <td class="center">
                  <div
                    v-if="et.flights?.length"
                    style="
                      display: flex;
                      flex-direction: column;
                      gap: 2px;
                      align-items: center;
                    "
                  >
                    <div
                      v-for="fl in et.flights"
                      :key="fl.id"
                      style="
                        font-size: 11px;
                        display: flex;
                        align-items: center;
                        gap: 4px;
                      "
                    >
                      <span
                        :class="[
                          'direction-badge-sm',
                          `direction-badge-sm--${fl.direction}`,
                        ]"
                        >{{ fl.direction === "arrival" ? "↓" : "↑" }}</span
                      >
                      <span class="mono">{{ fl.flight_number || "—" }}</span>
                      <span v-if="fl.scheduled_at" class="text-muted">{{
                        formatDateTime(fl.scheduled_at)
                      }}</span>
                      <span v-if="fl.party_size_total" class="party-size-badge-sm">{{ fl.party_size_total }} pax</span>
                    </div>
                  </div>
                  <span v-else style="color: var(--ink3)">—</span>
                </td>
                <td>
                  <div v-if="et.stay" style="font-size: 12px">
                    <div style="font-weight: 500">{{ et.stay.hotel_name }}</div>
                    <div
                      v-if="et.stay.check_in && et.stay.check_out"
                      class="mono text-muted"
                      style="font-size: 11px"
                    >
                      {{ formatDate(et.stay.check_in) }} –
                      {{ formatDate(et.stay.check_out) }}
                    </div>
                  </div>
                  <span v-else style="color: var(--ink3)">—</span>
                </td>
                <td class="center">
                  <div style="display: flex; gap: 4px; justify-content: center">
                    <button
                      class="manage-team-btn"
                      @click="openManageModal(et)"
                      title="Manage flights & stay"
                    >
                      <svg
                        width="13"
                        height="13"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <circle cx="12" cy="12" r="3" />
                        <path
                          d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"
                        />
                      </svg>
                    </button>
                    <button
                      class="manage-team-btn"
                      @click="openTeamForm(et)"
                      title="Edit team"
                    >
                      <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                        <path
                          d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </button>
                    <button
                      class="manage-team-btn"
                      @click="deleteTeam(et)"
                      title="Delete team"
                    >
                      <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                        <path
                          d="M2 4H14M6 4V2H10V4M12 4V14H4V4"
                          stroke="currentColor"
                          stroke-width="1.5"
                          stroke-linecap="round"
                          stroke-linejoin="round"
                        />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredTeams.length === 0">
                <td
                  colspan="7"
                  style="text-align: center; padding: 40px; color: var(--ink3)"
                >
                  No teams found.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Manage Team Modal -->
    <Modal
      :show="showManageModal"
      @close="showManageModal = false"
      max-width="700px"
    >
      <template #title>
        <div style="display: flex; align-items: center; gap: 8px">
          <span class="team-badge-sm">{{ managingTeam?.code }}</span>
          <span>{{ managingTeam?.team_name }}</span>
        </div>
      </template>
      <div class="manage-modal">
        <!-- ── Flights ───────────────────────────────────────── -->
        <div class="manage-section">
          <div class="manage-section-header">
            <h4 class="manage-section-title">Flights</h4>
            <Button variant="secondary" size="sm" @click="openFlightForm(null)">
              <template #icon><svg-icon name="plus" :size="12" /></template>
              Add Flight
            </Button>
          </div>

          <div v-if="managingTeam?.flights?.length" class="flights-list">
            <div
              v-for="fl in managingTeam.flights"
              :key="fl.id"
              class="flight-record"
            >
              <div class="flight-info">
                <span
                  :class="[
                    'direction-badge',
                    `direction-badge--${fl.direction}`,
                  ]"
                  >{{ fl.direction }}</span
                >
                <span class="flight-num">{{ fl.flight_number || "—" }}</span>
                <span
                  class="flight-airports"
                  v-if="fl.origin_airport || fl.destination_airport"
                >
                  {{ fl.origin_airport?.code || "?" }} →
                  {{ fl.destination_airport?.code || "?" }}
                </span>
                <span class="flight-time mono" v-if="fl.scheduled_at">{{
                  formatDateTime(fl.scheduled_at)
                }}</span>
                <span v-if="fl.party_size_total" class="party-size-badge"
                  >{{ fl.party_size_total }} pax</span
                >
                <span v-if="fl.planned_bags" class="party-size-badge"
                  >{{ fl.planned_bags }} bags</span
                >
                <div style="display: flex; gap: 4px; margin-left: auto">
                  <button
                    class="fr-btn"
                    @click="openFlightForm(fl)"
                    title="Edit"
                  >
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                      <path
                        d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                  <button
                    class="fr-btn fr-btn--sync"
                    :disabled="syncingFlightId === fl.id"
                    @click="syncFlight(fl)"
                    title="Sync from Flight Data API"
                  >
                    <span v-if="syncingFlightId === fl.id" class="spinner-sm"></span>
                    <svg v-else width="13" height="13" viewBox="0 0 16 16" fill="none">
                      <path
                        d="M14 8C14 11.3137 11.3137 14 8 14C4.68629 14 2 11.3137 2 8C2 4.68629 4.68629 2 8 2C9.84843 2 11.5053 2.87158 12.5784 4.24996M12 2V5H9"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                  <button
                    class="fr-btn fr-btn--del"
                    @click="deleteFlight(fl)"
                    title="Delete"
                  >
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="none">
                      <path
                        d="M2 4H14M6 4V2H10V4M12 4V14H4V4"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                      />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="empty-state">No flights added yet.</div>
        </div>

        <!-- ── Accommodation ───────────────────────────────────── -->
        <div class="manage-section">
          <div class="manage-section-header">
            <h4 class="manage-section-title">Accommodation</h4>
            <Button
              v-if="!managingTeam?.stay"
              variant="secondary"
              size="sm"
              @click="openStayForm(null)"
            >
              <template #icon><svg-icon name="plus" :size="12" /></template>
              Add Stay
            </Button>
            <Button
              v-else
              variant="secondary"
              size="sm"
              @click="openStayForm(managingTeam.stay)"
              >Edit Stay</Button
            >
          </div>

          <div v-if="managingTeam?.stay" class="stay-record">
            <div class="stay-row">
              <span class="stay-label">Hotel</span
              ><span>{{ managingTeam.stay.hotel_name || "—" }}</span>
            </div>
            <div
              class="stay-row"
              v-if="managingTeam.stay.check_in || managingTeam.stay.check_out"
            >
              <span class="stay-label">Dates</span>
              <span class="mono"
                >{{ formatDate(managingTeam.stay.check_in) }} –
                {{ formatDate(managingTeam.stay.check_out) }}</span
              >
            </div>
            <div class="stay-row" v-if="managingTeam.stay.room_count">
              <span class="stay-label">Rooms</span
              ><span>{{ managingTeam.stay.room_count }}</span>
            </div>
            <button
              class="stay-delete-btn"
              @click="deleteStay(managingTeam.stay)"
            >
              Delete Stay
            </button>
          </div>
          <div v-else class="empty-state">No accommodation added yet.</div>
        </div>

        <!-- ── Training ─────────────────────────────────────────── -->
        <div class="manage-section">
          <div class="manage-section-header">
            <h4 class="manage-section-title">Training</h4>
            <Button
              v-if="!managingTeam?.training"
              variant="secondary"
              size="sm"
              @click="openTrainingForm(null)"
            >
              <template #icon><svg-icon name="plus" :size="12" /></template>
              Add Training
            </Button>
            <Button
              v-else
              variant="secondary"
              size="sm"
              @click="openTrainingForm(managingTeam.training)"
              >Edit Training</Button
            >
          </div>

          <div v-if="managingTeam?.training" class="stay-record">
            <div class="stay-row" v-if="managingTeam.training.training_ground">
              <span class="stay-label">Ground</span
              ><span>{{ managingTeam.training.training_ground }}</span>
            </div>
            <div class="stay-row" v-if="managingTeam.training.training_start_at">
              <span class="stay-label">Start Time</span
              ><span class="mono">{{ formatDateTime(managingTeam.training.training_start_at) }}</span>
            </div>
            <div class="stay-row" v-if="managingTeam.training.notes">
              <span class="stay-label">Notes</span
              ><span>{{ managingTeam.training.notes }}</span>
            </div>
            <button
              class="stay-delete-btn"
              @click="deleteTraining(managingTeam.training)"
            >
              Delete Training
            </button>
          </div>
          <div v-else class="empty-state">No training added yet.</div>
        </div>
      </div>
    </Modal>

    <!-- Flight Form Modal -->
    <Modal
      :show="showFlightForm"
      @close="showFlightForm = false"
      max-width="480px"
    >
      <template #title>{{
        editingFlight ? "Edit Flight" : "Add Flight"
      }}</template>
      <form @submit.prevent="submitFlight" class="team-form">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label"
              >Direction <span class="required">*</span></label
            >
            <select v-model="flightForm.direction" class="form-select" required>
              <option value="arrival">Arrival</option>
              <option value="departure">Departure</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Flight Number</label>
            <input
              v-model="flightForm.flight_number"
              type="text"
              class="form-input"
              placeholder="QR615"
              maxlength="20"
            />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Origin Airport</label>
          <select v-model="flightForm.origin_airport_id" class="form-select">
            <option value="">— None —</option>
            <option v-for="a in airports" :key="a.id" :value="a.id">
              {{ a.code }} · {{ a.name }}
            </option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Destination Airport</label>
          <select
            v-model="flightForm.destination_airport_id"
            class="form-select"
          >
            <option value="">— None —</option>
            <option v-for="a in airports" :key="a.id" :value="a.id">
              {{ a.code }} · {{ a.name }}
            </option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Scheduled Date/Time</label>
            <FormDateField
              v-model="flightForm.scheduled_at"
              mode="datetime"
              display-format="d/m/Y H:i"
              placeholder="dd/mm/yyyy HH:mm"
            />
          </div>
          <div class="form-group">
            <label class="form-label">Gate</label>
            <input
              v-model="flightForm.gate"
              type="text"
              class="form-input"
              maxlength="50"
            />
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Players</label>
            <input
              v-model="flightForm.party_size_players"
              type="number"
              class="form-input"
              min="0"
              placeholder="0"
            />
          </div>
          <div class="form-group">
            <label class="form-label">Staff</label>
            <input
              v-model="flightForm.party_size_staff"
              type="number"
              class="form-input"
              min="0"
              placeholder="0"
            />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Total Party Size (Auto-calculated)</label>
            <input
              v-model="flightForm.party_size_total"
              type="number"
              class="form-input"
              min="0"
              placeholder="0"
              readonly
              style="background: var(--panel); cursor: not-allowed;"
            />
          </div>
          <div class="form-group">
            <label class="form-label">Planned Bags</label>
            <input
              v-model="flightForm.planned_bags"
              type="number"
              class="form-input"
              min="0"
              placeholder="0"
            />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="flightForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            @click="showFlightForm = false"
            >Cancel</Button
          >
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :disabled="processing"
          >
            {{
              processing
                ? "Saving…"
                : editingFlight
                ? "Save Changes"
                : "Add Flight"
            }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Stay Form Modal -->
    <Modal :show="showStayForm" @close="showStayForm = false" max-width="480px">
      <template #title>{{
        editingStay ? "Edit Accommodation" : "Add Accommodation"
      }}</template>
      <form @submit.prevent="submitStay" class="team-form">
        <div class="form-row">
          <div class="form-group" style="flex: 2">
            <label class="form-label">Hotel Name</label>
            <Select
              v-model="stayForm.hotel_name"
              :options="baseCampHotels"
              optionLabel="name"
              optionValue="name"
              filter
              filterPlaceholder="Search hotels..."
              showClear
              placeholder="Select a base camp hotel"
              class="w-full"
            />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input v-model="stayForm.address" type="text" class="form-input" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Check-in</label>
            <input v-model="stayForm.check_in" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Check-out</label>
            <input
              v-model="stayForm.check_out"
              type="date"
              class="form-input"
            />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Rooms</label>
            <input
              v-model="stayForm.room_count"
              type="number"
              class="form-input"
              min="0"
            />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="stayForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            @click="showStayForm = false"
            >Cancel</Button
          >
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :disabled="processing"
          >
            {{
              processing ? "Saving…" : editingStay ? "Save Changes" : "Add Stay"
            }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Training Form Modal -->
    <Modal :show="showTrainingForm" @close="showTrainingForm = false" max-width="480px">
      <template #title>{{
        editingTraining ? "Edit Training" : "Add Training"
      }}</template>
      <form @submit.prevent="submitTraining" class="team-form">
        <div class="form-group">
          <label class="form-label">Training Ground</label>
          <input
            v-model="trainingForm.training_ground"
            type="text"
            class="form-input"
          />
        </div>
        <div class="form-group">
          <label class="form-label">Training Start Time</label>
          <input
            v-model="trainingForm.training_start_at"
            type="datetime-local"
            class="form-input"
          />
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="trainingForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            @click="showTrainingForm = false"
            >Cancel</Button
          >
          <Button
            type="submit"
            variant="primary"
            size="sm"
            :disabled="processing"
          >
            {{
              processing
                ? "Saving…"
                : editingTraining
                ? "Save Changes"
                : "Add Training"
            }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Add/Edit Team Modal -->
    <Modal :show="showTeamForm" @close="showTeamForm = false" max-width="600px">
      <template #title>{{ editingTeam ? "Edit Team" : "Add Team" }}</template>
      <form @submit.prevent="submitTeam" class="team-form">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Team Code <span class="required">*</span></label>
            <input
              v-model="teamForm.code"
              type="text"
              class="form-input"
              maxlength="10"
              placeholder="e.g., MER"
              :disabled="!!editingTeam"
              required
            />
          </div>
          <div class="form-group">
            <label class="form-label">Team Name <span class="required">*</span></label>
            <input
              v-model="teamForm.team_name"
              type="text"
              class="form-input"
              placeholder="e.g., Mercure FC"
              required
            />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Country</label>
            <select v-model="teamForm.country_id" class="form-select">
              <option value="">— Select country —</option>
              <option v-for="c in countries" :key="c.country_code" :value="c.country_code">
                {{ c.country_name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Classification</label>
            <select v-model="teamForm.classification_type_id" class="form-select">
              <option value="">— None —</option>
              <option v-for="c in classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Flag</label>
            <div style="display: flex; align-items: center; gap: 8px; height: 34px">
              <flag-icon :code="teamForm.country_id" :fallback="teamForm.flag" />
              <span style="font-size: 12px; color: var(--ink3)">{{
                teamForm.country_id ? "From selected country" : "Select a country to preview"
              }}</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Group / Pool</label>
            <input v-model="teamForm.group_pool" type="text" class="form-input" placeholder="e.g. Group A" maxlength="50" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Head of Delegation</label>
            <input v-model="teamForm.head_of_delegation" type="text" class="form-input" placeholder="Name" />
          </div>
          <div class="form-group">
            <label class="form-label">Bib Accent Color</label>
            <input v-model="teamForm.bib_accent_color" type="text" class="form-input" placeholder="#0055A4" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="teamForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showTeamForm = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">
            {{ processing ? "Saving…" : editingTeam ? "Save Changes" : "Add Team" }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Sync Result Modal -->
    <Modal :show="showSyncModal" @close="showSyncModal = false" max-width="560px">
      <template #title>
        <div style="display:flex;align-items:center;gap:8px">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M1 4v6h6"/><path d="M23 20v-6h-6"/>
            <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
          </svg>
          <span>{{ syncResult?.flight_number }}</span>
          <span v-if="syncResult?.airline && !syncResult?.date_mismatch" style="font-size:12px;color:var(--ink3);font-weight:400">{{ syncResult.airline }}</span>
          <span v-if="syncResult?.flight_status && !syncResult?.date_mismatch" :class="['sync-status-badge', `sync-status--${syncResult.flight_status}`]">{{ syncResult.flight_status }}</span>
        </div>
      </template>

      <div class="sync-modal-body">
        <div v-if="syncResult?.date_mismatch" class="sync-mismatch-warn">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>
            No AviationStack data is available yet for your planned date of <strong>{{ syncResult.planned_date }}</strong>
            (the closest data it has is from <strong>{{ syncResult.flight_date }}</strong>, a different day for this flight number — details below are not shown since they don't belong to your flight).
            Live tracking data is only available close to the actual travel date; the flight record was <strong>not</strong> updated.
          </span>
        </div>

        <div v-if="syncResult?.source === 'ai' && syncResult?.ai_found" class="sync-mismatch-warn" style="background:var(--accent-soft);border-color:var(--accent);color:var(--ink);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M12 3a1 1 0 01.949.684l1.05 3.15a3 3 0 001.917 1.917l3.15 1.05a1 1 0 010 1.898l-3.15 1.05a3 3 0 00-1.917 1.917l-1.05 3.15a1 1 0 01-1.898 0l-1.05-3.15a3 3 0 00-1.917-1.917l-3.15-1.05a1 1 0 010-1.898l3.15-1.05a3 3 0 001.917-1.917l1.05-3.15A1 1 0 0112 3z"/></svg>
          <span>
            AviationStack has no live tracking data this far ahead, so this was found via AI web search instead
            <span v-if="syncResult.ai_confidence">({{ syncResult.ai_confidence }} confidence)</span>.
            This is <strong>not saved</strong> — review it and enter it manually if it looks right.
            <template v-if="syncResult.ai_notes"><br /><span style="opacity:.85">{{ syncResult.ai_notes }}</span></template>
          </span>
        </div>

        <div v-else-if="syncResult?.source === 'ai' && !syncResult?.ai_found" class="sync-mismatch-warn" style="background:var(--accent-soft);border-color:var(--accent);color:var(--ink);">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path d="M12 3a1 1 0 01.949.684l1.05 3.15a3 3 0 001.917 1.917l3.15 1.05a1 1 0 010 1.898l-3.15 1.05a3 3 0 00-1.917 1.917l-1.05 3.15a1 1 0 01-1.898 0l-1.05-3.15a3 3 0 00-1.917-1.917l-3.15-1.05a1 1 0 010-1.898l3.15-1.05a3 3 0 001.917-1.917l1.05-3.15A1 1 0 0112 3z"/></svg>
          <span>
            AviationStack has no live tracking data this far ahead. AI web search couldn't confirm an exact schedule
            for this date either, but found some relevant context — nothing has been saved.
            <template v-if="syncResult.airline || syncResult?.departure?.iata"><br />{{ syncResult.airline }}<template v-if="syncResult?.departure?.iata"> · {{ syncResult.departure.iata }} → {{ syncResult?.arrival?.iata }}</template></template>
            <template v-if="syncResult.ai_notes"><br /><span style="opacity:.85">{{ syncResult.ai_notes }}</span></template>
          </span>
        </div>

        <div v-if="!syncResult?.date_mismatch && (syncResult?.departure?.scheduled || syncResult?.arrival?.scheduled)" class="sync-card">
          <!-- Route bar -->
          <div class="sync-route-bar">
            <span class="sync-iata-big">{{ syncResult?.departure?.iata || '?' }}</span>
            <div class="sync-route-middle">
              <span class="sync-duration-label">{{ syncDuration }}</span>
              <div class="sync-arrow-line">
                <div class="sync-line-track"></div>
                <svg class="sync-plane-icon" viewBox="0 0 24 24" fill="currentColor" width="16" height="16">
                  <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                </svg>
              </div>
            </div>
            <span class="sync-iata-big">{{ syncResult?.arrival?.iata || '?' }}</span>
          </div>

          <!-- Airport info -->
          <div class="sync-airports-grid">
            <div class="sync-airport-section">
              <div class="sync-airport-city">{{ syncResult?.departure?.airport || syncResult?.departure?.iata }}</div>
              <div class="sync-airport-date">{{ formatSyncDate(syncResult?.departure?.scheduled) }}</div>
              <div class="sync-detail-row">
                <div class="sync-detail-col">
                  <div class="sync-detail-label">{{ syncResult?.departure?.actual ? 'Departed' : 'Scheduled' }}</div>
                  <div class="sync-detail-time-actual">{{ formatSyncTime(syncResult?.departure?.actual || syncResult?.departure?.scheduled) }}</div>
                  <div v-if="syncResult?.departure?.actual" class="sync-detail-time-sched">{{ formatSyncTime(syncResult?.departure?.scheduled) }}</div>
                </div>
                <div class="sync-detail-col">
                  <div class="sync-detail-label">Terminal</div>
                  <div class="sync-detail-val">{{ syncResult?.departure?.terminal || '—' }}</div>
                </div>
                <div class="sync-detail-col">
                  <div class="sync-detail-label">Gate</div>
                  <div class="sync-detail-val">{{ syncResult?.departure?.gate || '—' }}</div>
                </div>
              </div>
            </div>

            <div class="sync-divider-v"></div>

            <div class="sync-airport-section sync-airport-section--right">
              <div class="sync-airport-city">{{ syncResult?.arrival?.airport || syncResult?.arrival?.iata }}</div>
              <div class="sync-airport-date">{{ formatSyncDate(syncResult?.arrival?.scheduled) }}</div>
              <div class="sync-detail-row sync-detail-row--right">
                <div class="sync-detail-col">
                  <div class="sync-detail-label">{{ syncResult?.arrival?.actual ? 'Arrived' : (syncResult?.arrival?.estimated ? 'Estimated' : 'Scheduled') }}</div>
                  <div class="sync-detail-time-actual">{{ formatSyncTime(syncResult?.arrival?.actual || syncResult?.arrival?.estimated || syncResult?.arrival?.scheduled) }}</div>
                  <div v-if="syncResult?.arrival?.actual || syncResult?.arrival?.estimated" class="sync-detail-time-sched">{{ formatSyncTime(syncResult?.arrival?.scheduled) }}</div>
                </div>
                <div class="sync-detail-col">
                  <div class="sync-detail-label">Terminal</div>
                  <div class="sync-detail-val">{{ syncResult?.arrival?.terminal || '—' }}</div>
                </div>
                <div class="sync-detail-col">
                  <div class="sync-detail-label">Gate</div>
                  <div class="sync-detail-val">{{ syncResult?.arrival?.gate || '—' }}</div>
                </div>
              </div>
            </div>
          </div>

          <div class="sync-card-footer">
            <template v-if="syncResult?.source === 'ai'">
              Not saved · Source:
              <a v-if="syncResult.ai_source_url" :href="syncResult.ai_source_url" target="_blank" rel="noopener noreferrer">AI web search</a>
              <template v-else>AI web search</template>
            </template>
            <template v-else-if="syncResult?.source === 'oag'">Updated just now · Source: OAG</template>
            <template v-else>Updated just now · Source: AviationStack</template>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :show="showDeleteModal"
      tone="danger"
      :title="`Delete ${deleteType}`"
      :message="deleteMessage"
      :processing="deleting"
      @close="closeDeleteModal"
      @confirm="confirmDelete"
    />

    <!-- Import Teams Modal -->
    <Modal :show="showImportModal" @close="closeImportModal" max-width="560px">
      <template #title>Import Teams</template>
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div style="font-size: 13px; color: var(--ink2); line-height: 1.5;">
          Upload an Excel (.xlsx/.xls) or CSV file to create or update teams for
          <strong>{{ activeEvent?.name }}</strong>, along with their arrival/departure
          flight and accommodation details. Rows are matched by team code — existing
          teams are updated, never duplicated, and blank cells never erase existing data.
        </div>

        <a :href="importTemplateUrl" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; color: var(--accent); text-decoration: none; width: fit-content;">
          <svg-icon name="download" :size="13" />
          Download import template
        </a>

        <div v-if="$page.props.ui?.utilities !== false" style="padding: 10px 12px; background: var(--panel); border: 1px solid var(--border); border-radius: 8px; font-size: 12px; line-height: 1.55; color: var(--ink3);">
          <strong style="color: var(--ink);">Working from the LOG PMA Scheduler?</strong>
          A workbook like "LOG PMA Scheduler 2026 20260915.xlsx" won't import directly — it holds dozens of
          sheets and its columns differ from this template. Convert the
          <code>Tbl.…Arrivals&amp;Depart</code> sheet first: you'll see how each column was matched and every
          converted row before anything is saved.
          <a
            v-if="$page.props.auth?.can?.['fleet.manage']"
            href="/utilities?tool=teams"
            style="display: inline-flex; align-items: center; gap: 5px; margin-top: 8px; font-weight: 600; color: var(--accent); text-decoration: none;"
          >
            Open the Team Sheet Converter
            <svg-icon name="chevron" :size="12" />
          </a>
        </div>

        <div
          style="border: 1px dashed var(--border); border-radius: 8px; padding: 20px; text-align: center; cursor: pointer;"
          @click="$refs.importFileInput.click()"
          @dragover.prevent
          @drop.prevent="onFileDrop"
        >
          <input ref="importFileInput" type="file" accept=".xlsx,.xls,.csv,.txt" style="display: none;" @change="onFileSelected" />
          <div v-if="!importFile" style="font-size: 12px; color: var(--ink3);">
            Click to choose a file, or drag one here
          </div>
          <div v-else style="font-size: 13px; font-weight: 600; color: var(--ink);">
            {{ importFile.name }}
          </div>
        </div>

        <div v-if="importError" style="padding: 8px 12px; background: #FEE2E2; border: 1px solid #FCA5A5; border-radius: 6px; color: #991B1B; font-size: 12px;">
          {{ importError }}
        </div>
      </div>
      <template #footer>
        <div style="display: flex; gap: 8px; justify-content: flex-end;">
          <Button variant="secondary" size="sm" @click="closeImportModal" :disabled="importing">Cancel</Button>
          <Button variant="primary" size="sm" @click="submitImport" :disabled="importing || !importFile">
            {{ importing ? "Importing..." : "Import" }}
          </Button>
        </div>
      </template>
    </Modal>

    <!-- Import Results Modal -->
    <Modal :show="showImportResultsModal" @close="showImportResultsModal = false" max-width="640px">
      <template #title>Import Results</template>
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;">
          <div style="padding: 10px; background: var(--panel); border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: 700; color: var(--ink);">{{ importResult?.created ?? 0 }}</div>
            <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px;">Created</div>
          </div>
          <div style="padding: 10px; background: var(--panel); border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: 700; color: var(--ink);">{{ importResult?.updated ?? 0 }}</div>
            <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px;">Updated</div>
          </div>
          <div style="padding: 10px; background: var(--panel); border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: 700; color: var(--ink);">{{ importResult?.unchanged ?? 0 }}</div>
            <div style="font-size: 10px; font-weight: 700; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.5px;">Unchanged</div>
          </div>
          <div style="padding: 10px; background: #FEE2E2; border-radius: 6px; text-align: center;">
            <div style="font-size: 18px; font-weight: 700; color: #991B1B;">{{ importResult?.failed?.length ?? 0 }}</div>
            <div style="font-size: 10px; font-weight: 700; color: #991B1B; text-transform: uppercase; letter-spacing: 0.5px;">Failed</div>
          </div>
        </div>

        <div v-if="importResult?.incomplete?.length" style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
          <div style="background: #FEF3C7; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #92400E; text-transform: uppercase; letter-spacing: 0.5px;">
            Missing Info ({{ importResult.incomplete.length }})
          </div>
          <div style="max-height: 160px; overflow-y: auto;">
            <div v-for="item in importResult.incomplete" :key="item.row" style="padding: 8px 12px; border-bottom: 1px solid var(--border); font-size: 12px; display: flex; justify-content: space-between; gap: 10px;">
              <span style="font-weight: 600; color: var(--ink);">{{ item.code }}</span>
              <span style="color: var(--ink3);">missing {{ item.missing.join(", ") }}</span>
            </div>
          </div>
        </div>

        <div v-if="importResult?.flight_changes?.length" style="border: 1px solid var(--border); border-radius: 6px; overflow: hidden;">
          <div style="background: #DBEAFE; padding: 8px 12px; font-size: 11px; font-weight: 700; color: #1E40AF; text-transform: uppercase; letter-spacing: 0.5px;">
            Flight Changes ({{ importResult.flight_changes.length }})
          </div>
          <div style="max-height: 260px; overflow-y: auto;">
            <div v-for="(change, i) in importResult.flight_changes" :key="`fc${i}`" style="padding: 8px 12px; border-bottom: 1px solid var(--border); font-size: 12px;">
              <div style="display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: baseline;">
                <span style="font-weight: 700; color: var(--ink);">{{ change.code }}</span>
                <span style="color: var(--ink3); text-transform: capitalize;">{{ change.direction }}</span>
                <span v-if="change.flight_before !== change.flight_after" style="color: var(--ink);">
                  {{ change.flight_before || '—' }} → <strong>{{ change.flight_after }}</strong>
                </span>
                <span v-else-if="change.flight_after" style="color: var(--ink3);">{{ change.flight_after }}</span>
                <span v-if="change.time_before !== change.time_after" style="color: var(--ink);">
                  {{ change.time_before || '—' }} → <strong>{{ change.time_after }}</strong>
                </span>
                <span v-if="change.pax_before !== change.pax_after" style="color: var(--ink);">
                  pax {{ change.pax_before ?? '—' }} → <strong>{{ change.pax_after }}</strong>
                </span>
              </div>
              <div v-if="!change.movements.length" style="margin-top: 3px; color: var(--ink3);">No planned movements affected.</div>
              <div
                v-for="m in change.movements"
                :key="m.id"
                :style="{ marginTop: '4px', padding: '5px 8px', borderRadius: '4px', background: m.status === 'needs_review' ? '#FEF3C7' : 'var(--panel)' }"
              >
                <div style="display: flex; justify-content: space-between; gap: 10px;">
                  <span style="color: var(--ink);">
                    <strong>{{ m.code }}</strong>
                    <span style="color: var(--ink3); text-transform: capitalize;"> · {{ m.kind }}</span>
                    <span v-if="m.plan" style="color: var(--ink3);"> · {{ m.plan }}</span>
                  </span>
                  <span v-if="m.status === 'needs_review'" style="font-weight: 700; color: #92400E; white-space: nowrap;">Needs review</span>
                  <span v-else-if="m.from !== m.to" style="color: var(--ink); white-space: nowrap;">{{ m.from || '—' }} → <strong>{{ m.to }}</strong></span>
                  <span v-else style="color: #166534; white-space: nowrap;">Updated</span>
                </div>
                <div v-for="(note, n) in m.notes" :key="n" :style="{ fontSize: '11px', color: m.status === 'needs_review' ? '#92400E' : 'var(--ink3)' }">{{ note }}</div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="importResult?.not_in_file?.length" style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 6px; font-size: 12px; color: var(--ink3);">
          <strong style="color: var(--ink);">Not in this file ({{ importResult.not_in_file.length }}):</strong>
          {{ importResult.not_in_file.join(", ") }} — left as they were. Remove them on this page if they have withdrawn.
        </div>

        <div v-if="importResult?.failed?.length" style="border: 1px solid #FCA5A5; border-radius: 6px; overflow: hidden;">
          <div style="background: #FEE2E2; padding: 8px 12px; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 11px; font-weight: 700; color: #991B1B; text-transform: uppercase; letter-spacing: 0.5px;">
              Failed Rows ({{ importResult.failed.length }})
            </span>
            <button @click="downloadFailedRows" style="font-size: 11px; font-weight: 700; color: #991B1B; background: none; border: none; cursor: pointer; text-decoration: underline;">
              Download failed rows
            </button>
          </div>
          <div style="max-height: 160px; overflow-y: auto;">
            <div v-for="item in importResult.failed" :key="item.row" style="padding: 8px 12px; border-bottom: 1px solid var(--border); font-size: 12px;">
              <span style="font-weight: 600; color: var(--ink);">Row {{ item.row }}</span>
              <span style="color: #991B1B;"> — {{ item.error }}</span>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <div style="display: flex; justify-content: flex-end;">
          <Button variant="primary" size="sm" @click="showImportResultsModal = false">Done</Button>
        </div>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch, nextTick } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Components/AppLayout.vue";
import Modal from "@/Components/Modal.vue";
import Button from "@/Components/Button.vue";
import RefreshButton from "@/Components/RefreshButton.vue";
import MiniStat from "@/Components/MiniStat.vue";
import SvgIcon from "@/Components/SvgIcon.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import FlagIcon from "@/Components/FlagIcon.vue";
import FormDateField from "@/Components/FormDateField.vue";
import Select from "primevue/select";

const props = defineProps({
  activeEvent: { type: Object, default: null },
  eventTeams: { type: Array, default: () => [] },
  airports: { type: Array, default: () => [] },
  countries: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
  baseCampHotels: { type: Array, default: () => [] },
});

const searchQuery = ref("");

const filteredTeams = computed(() => {
  let teams = props.eventTeams;

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    teams = teams.filter(
      (et) =>
        et.team_name?.toLowerCase().includes(q) ||
        et.code?.toLowerCase().includes(q) ||
        et.group_pool?.toLowerCase().includes(q)
    );
  }

  return teams;
});

const teamsWithFlights = computed(
  () => props.eventTeams.filter((et) => et.flights?.length > 0).length
);

const teamsWithStay = computed(
  () => props.eventTeams.filter((et) => et.stay).length
);

const totalFlights = computed(() =>
  props.eventTeams.reduce((sum, et) => sum + (et.flights?.length || 0), 0)
);

function formatDate(value) {
  if (!value) return "—";
  const d = new Date(value);
  if (isNaN(d)) return value;
  return d.toLocaleDateString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
  });
}

function formatDateTime(value) {
  if (!value) return "—";
  
  // Handle string format "YYYY-MM-DD HH:mm:ss" without timezone conversion
  if (typeof value === 'string' && value.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/)) {
    const [datePart, timePart] = value.split(' ');
    const [year, month, day] = datePart.split('-');
    const [hour, minute] = timePart.split(':');
    
    const monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${day} ${monthNames[parseInt(month) - 1]} ${hour}:${minute}`;
  }
  
  // Fallback to Date parsing (for other formats)
  const d = new Date(value);
  if (isNaN(d)) return value;
  return (
    d.toLocaleDateString("en-GB", { day: "2-digit", month: "short" }) +
    " " +
    d.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit" })
  );
}

// Format datetime for input field (d/m/Y HH:mm)
function formatDateTimeForInput(value) {
  if (!value) return "";
  
  // Parse the datetime string
  const d = new Date(value);
  if (isNaN(d)) return "";
  
  const day = String(d.getDate()).padStart(2, '0');
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const year = d.getFullYear();
  const hours = String(d.getHours()).padStart(2, '0');
  const minutes = String(d.getMinutes()).padStart(2, '0');
  
  return `${day}/${month}/${year} ${hours}:${minutes}`;
}

// Parse datetime from input field back to ISO format for database
function parseDateTimeFromInput(value) {
  if (!value) return "";
  
  // Expected format: dd/mm/yyyy HH:mm
  const match = value.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2})$/);
  if (!match) return value; // Return as-is if format doesn't match
  
  const [, day, month, year, hours, minutes] = match;
  // Return in format suitable for database: YYYY-MM-DD HH:mm:ss
  return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')} ${hours.padStart(2, '0')}:${minutes}:00`;
}

// ── Manage Team Modal ──────────────────────────────────────────────────────
const showManageModal = ref(false);
const managingTeam = ref(null);
const processing = ref(false);

// ── Sync Result Modal ──────────────────────────────────────────────────────
const showSyncModal = ref(false);
const syncResult = ref(null);
const syncingFlightId = ref(null);

const syncDuration = computed(() => {
  const dep = syncResult.value?.departure?.scheduled;
  const arr = syncResult.value?.arrival?.scheduled;
  if (!dep || !arr) return '';
  const diffMs = new Date(arr) - new Date(dep);
  if (diffMs <= 0) return '';
  const totalMins = Math.round(diffMs / 60000);
  const h = Math.floor(totalMins / 60);
  const m = totalMins % 60;
  return h > 0 ? `${h}h ${m}m` : `${m}m`;
});

function formatSyncTime(isoStr) {
  if (!isoStr) return '—';
  const match = isoStr.match(/T(\d{2}):(\d{2})/);
  if (!match) return '—';
  let h = parseInt(match[1]);
  const m = match[2];
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${m} ${ampm}`;
}

function formatSyncDate(isoStr) {
  if (!isoStr) return '—';
  const match = isoStr.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return '—';
  const d = new Date(`${match[1]}-${match[2]}-${match[3]}T12:00:00Z`);
  return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', timeZone: 'UTC' });
}

function openManageModal(et) {
  managingTeam.value = et;
  showManageModal.value = true;
}

function refreshManagingTeam() {
  const et = managingTeam.value;
  if (!et) return;
  router.reload({
    only: ["eventTeams"],
    onSuccess: () => {
      const fresh = props.eventTeams.find((t) => t.id === et.id);
      if (fresh) managingTeam.value = fresh;
    },
  });
}

// ── Add/Edit Team Modal ────────────────────────────────────────────────────
const showTeamForm = ref(false);
const editingTeam = ref(null);
const teamForm = ref(emptyTeamForm());

function emptyTeamForm() {
  return {
    code: "",
    team_name: "",
    country_id: "",
    flag: "",
    group_pool: "",
    classification_type_id: "",
    head_of_delegation: "",
    bib_accent_color: "",
    notes: "",
  };
}

function openTeamForm(team) {
  processing.value = false;
  editingTeam.value = team;
  teamForm.value = team
    ? {
        code: team.code || "",
        team_name: team.team_name || "",
        country_id: team.country_id || "",
        flag: team.flag || "",
        group_pool: team.group_pool || "",
        classification_type_id: team.classification_type_id || "",
        head_of_delegation: team.head_of_delegation || "",
        bib_accent_color: team.bib_accent_color || "",
        notes: team.notes || "",
      }
    : emptyTeamForm();
  showTeamForm.value = true;
}

function submitTeam() {
  if (!props.activeEvent) return;
  processing.value = true;
  const url = editingTeam.value
    ? `/events/${props.activeEvent.id}/teams/${editingTeam.value.code}`
    : `/events/${props.activeEvent.id}/teams`;
  const method = editingTeam.value ? "put" : "post";

  router[method](url, teamForm.value, {
    onFinish: () => {
      processing.value = false;
      showTeamForm.value = false;
    },
  });
}

function deleteTeam(team) {
  openDeleteModal(
    "Team",
    `Are you sure you want to delete ${team.team_name || "this team"}? This cannot be undone.`,
    { type: "team", code: team.code }
  );
}

// ── Delete Modal ───────────────────────────────────────────────────────────
const showDeleteModal = ref(false);
const deleteMessage = ref("");
const deleteType = ref("");
const deleting = ref(false);
const itemToDelete = ref(null);

function openDeleteModal(type, message, item) {
  deleteType.value = type;
  deleteMessage.value = message;
  itemToDelete.value = item;
  showDeleteModal.value = true;
}

function closeDeleteModal() {
  showDeleteModal.value = false;
  itemToDelete.value = null;
  deleting.value = false;
}

function confirmDelete() {
  if (!itemToDelete.value) return;

  deleting.value = true;
  const item = itemToDelete.value;

  if (item.type === "flight") {
    router.delete(`/events/${managingTeam.value.event_id}/flights/${item.id}`, {
      onSuccess: () => {
        refreshManagingTeam();
        closeDeleteModal();
      },
      onError: () => {
        deleting.value = false;
      },
    });
  } else if (item.type === "stay") {
    router.delete(`/events/${managingTeam.value.event_id}/stays/${item.id}`, {
      onSuccess: () => {
        refreshManagingTeam();
        closeDeleteModal();
      },
      onError: () => {
        deleting.value = false;
      },
    });
  } else if (item.type === "training") {
    router.delete(`/events/${managingTeam.value.event_id}/trainings/${item.id}`, {
      onSuccess: () => {
        refreshManagingTeam();
        closeDeleteModal();
      },
      onError: () => {
        deleting.value = false;
      },
    });
  } else if (item.type === "team") {
    router.delete(`/events/${props.activeEvent.id}/teams/${item.code}`, {
      onSuccess: () => {
        closeDeleteModal();
      },
      onError: () => {
        deleting.value = false;
      },
    });
  }
}

// ── Flights ────────────────────────────────────────────────────────────────
const showFlightForm = ref(false);
const editingFlight = ref(null);
const flightForm = ref(emptyFlightForm());

// Auto-calculate total party size from Players/Staff - suppressed while the form
// is being populated from an existing record, so an imported total-only party
// size (no players/staff breakdown) isn't immediately zeroed back out.
const suppressPartySizeWatch = ref(false);
watch(
  () => [flightForm.value.party_size_players, flightForm.value.party_size_staff],
  ([players, staff]) => {
    if (suppressPartySizeWatch.value) return;
    const playersNum = parseInt(players) || 0;
    const staffNum = parseInt(staff) || 0;
    flightForm.value.party_size_total = playersNum + staffNum || '';
  }
);

function emptyFlightForm() {
  return {
    direction: "arrival",
    flight_number: "",
    origin_airport_id: "",
    destination_airport_id: "",
    scheduled_at: "",
    gate: "",
    party_size_total: "",
    party_size_players: "",
    party_size_staff: "",
    planned_bags: "",
    notes: "",
  };
}

function openFlightForm(fl) {
  processing.value = false;
  editingFlight.value = fl;
  suppressPartySizeWatch.value = true;
  flightForm.value = fl
    ? {
        direction: fl.direction,
        flight_number: fl.flight_number || "",
        origin_airport_id: fl.origin_airport_id || "",
        destination_airport_id: fl.destination_airport_id || "",
        scheduled_at: formatDateTimeForInput(fl.scheduled_at),
        gate: fl.gate || "",
        party_size_total: fl.party_size_total || "",
        party_size_players: fl.party_size_players || "",
        party_size_staff: fl.party_size_staff || "",
        planned_bags: fl.planned_bags || "",
        notes: fl.notes || "",
      }
    : emptyFlightForm();
  showFlightForm.value = true;
  nextTick(() => {
    suppressPartySizeWatch.value = false;
  });
}

function submitFlight() {
  processing.value = true;
  const et = managingTeam.value;
  const url = editingFlight.value
    ? `/events/${et.event_id}/flights/${editingFlight.value.id}`
    : `/events/${et.event_id}/teams/${et.code}/flights`;
  const method = editingFlight.value ? "put" : "post";
  
  // Parse the scheduled_at back to database format
  const formData = {
    ...flightForm.value,
    scheduled_at: parseDateTimeFromInput(flightForm.value.scheduled_at)
  };
  
  router[method](url, formData, {
    onFinish: () => {
      processing.value = false;
      showFlightForm.value = false;
    },
    onSuccess: () => refreshManagingTeam(),
  });
}

function deleteFlight(fl) {
  openDeleteModal(
    "Flight",
    `Are you sure you want to delete ${fl.direction} flight ${
      fl.flight_number || "this flight"
    }?`,
    { type: "flight", id: fl.id }
  );
}

async function syncFlight(fl) {
  if (!fl.flight_number) {
    alert('Flight number is required to sync flight data.');
    return;
  }

  syncingFlightId.value = fl.id;

  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch(`/events/${managingTeam.value.event_id}/flights/${fl.id}/sync`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': csrf,
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    });

    const data = await response.json();

    if (!response.ok) {
      alert(data.message || 'Failed to sync flight data. Please try again.');
      return;
    }

    if (data.success) {
      syncResult.value = data;
      showSyncModal.value = true;
      refreshManagingTeam();
    }
  } catch (error) {
    console.error('Flight sync error:', error);
    alert('Failed to sync flight data. Please try again.');
  } finally {
    syncingFlightId.value = null;
  }
}

// ── Stays ──────────────────────────────────────────────────────────────────
const showStayForm = ref(false);
const editingStay = ref(null);
const stayForm = ref(emptyStayForm());

function emptyStayForm() {
  return {
    hotel_name: "",
    address: "",
    check_in: "",
    check_out: "",
    room_count: "",
    notes: "",
  };
}

function openStayForm(stay) {
  processing.value = false;
  editingStay.value = stay;
  stayForm.value = stay
    ? {
        hotel_name: stay.hotel_name || "",
        address: stay.address || "",
        check_in: stay.check_in ? String(stay.check_in).substring(0, 10) : "",
        check_out: stay.check_out
          ? String(stay.check_out).substring(0, 10)
          : "",
        room_count: stay.room_count || "",
        notes: stay.notes || "",
      }
    : emptyStayForm();
  showStayForm.value = true;
}

function submitStay() {
  processing.value = true;
  const et = managingTeam.value;
  const url = editingStay.value
    ? `/events/${et.event_id}/stays/${editingStay.value.id}`
    : `/events/${et.event_id}/teams/${et.code}/stays`;
  const method = editingStay.value ? "put" : "post";
  router[method](url, stayForm.value, {
    onFinish: () => {
      processing.value = false;
      showStayForm.value = false;
    },
    onSuccess: () => refreshManagingTeam(),
  });
}

function deleteStay(stay) {
  openDeleteModal(
    "Accommodation",
    `Are you sure you want to delete the accommodation at ${
      stay.hotel_name || "this hotel"
    }?`,
    { type: "stay", id: stay.id }
  );
}

// ── Trainings ────────────────────────────────────────────────────────────
const showTrainingForm = ref(false);
const editingTraining = ref(null);
const trainingForm = ref(emptyTrainingForm());

function emptyTrainingForm() {
  return {
    training_ground: "",
    training_start_at: "",
    notes: "",
  };
}

function openTrainingForm(training) {
  processing.value = false;
  editingTraining.value = training;
  trainingForm.value = training
    ? {
        training_ground: training.training_ground || "",
        // "YYYY-MM-DD HH:mm:ss" -> "YYYY-MM-DDTHH:mm" for datetime-local input,
        // via plain string slicing (no Date parsing, avoids timezone shifts)
        training_start_at: training.training_start_at
          ? String(training.training_start_at).substring(0, 16).replace(" ", "T")
          : "",
        notes: training.notes || "",
      }
    : emptyTrainingForm();
  showTrainingForm.value = true;
}

function submitTraining() {
  processing.value = true;
  const et = managingTeam.value;
  const url = editingTraining.value
    ? `/events/${et.event_id}/trainings/${editingTraining.value.id}`
    : `/events/${et.event_id}/teams/${et.code}/trainings`;
  const method = editingTraining.value ? "put" : "post";
  router[method](url, trainingForm.value, {
    onFinish: () => {
      processing.value = false;
      showTrainingForm.value = false;
    },
    onSuccess: () => refreshManagingTeam(),
  });
}

function deleteTraining(training) {
  openDeleteModal(
    "Training",
    `Are you sure you want to delete the training at ${
      training.training_ground || "this ground"
    }?`,
    { type: "training", id: training.id }
  );
}

// ── Import Teams ─────────────────────────────────────────────────────────
const showImportModal = ref(false);
const importFile = ref(null);
const importing = ref(false);
const importError = ref("");
const showImportResultsModal = ref(false);
const importResult = ref(null);

const importTemplateUrl = "/event-teams/import-template";

function openImportModal() {
  importFile.value = null;
  importError.value = "";
  showImportModal.value = true;
}

function closeImportModal() {
  showImportModal.value = false;
}

function onFileSelected(event) {
  importFile.value = event.target.files?.[0] || null;
  importError.value = "";
}

function onFileDrop(event) {
  const file = event.dataTransfer?.files?.[0];
  if (file) {
    importFile.value = file;
    importError.value = "";
  }
}

async function submitImport() {
  if (!importFile.value || !props.activeEvent) return;

  importing.value = true;
  importError.value = "";

  try {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    const formData = new FormData();
    formData.append("file", importFile.value);

    const response = await fetch(`/events/${props.activeEvent.id}/teams/import`, {
      method: "POST",
      headers: {
        "X-CSRF-TOKEN": csrf,
        Accept: "application/json",
      },
      body: formData,
    });

    const data = await response.json();

    if (!response.ok) {
      importError.value = data.message || "Import failed. Please check the file and try again.";
      return;
    }

    importResult.value = data;
    showImportModal.value = false;
    showImportResultsModal.value = true;
    router.reload({ only: ["eventTeams"] });
  } catch (error) {
    console.error("Team import error:", error);
    importError.value = "Import failed. Please check the file and try again.";
  } finally {
    importing.value = false;
  }
}

function downloadFailedRows() {
  const failed = importResult.value?.failed;
  if (!failed?.length) return;

  const headers = [...TEAM_IMPORT_HEADERS, "Error"];
  const lines = [headers.map(csvEscape).join(",")];

  for (const item of failed) {
    const original = item.original || {};
    const values = TEAM_IMPORT_HEADERS.map((header) => original[headerKey(header)] ?? "");
    values.push(item.error);
    lines.push(values.map(csvEscape).join(","));
  }

  const blob = new Blob([lines.join("\r\n")], { type: "text/csv;charset=utf-8;" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "event-teams-import-failed-rows.csv";
  a.click();
  URL.revokeObjectURL(url);
}

const TEAM_IMPORT_HEADERS = [
  "Trigram", "Team Name", "Country Code", "Group", "Hotel Name", "Room Count",
  "Airport Code", "Arrival Flight Number", "Arrival Date", "Arrival Time", "Arrival Passengers",
  "Departure Flight Number", "Departure Date", "Departure Time", "Departure Passengers", "Notes",
];

function headerKey(header) {
  return header.toLowerCase().replace(/ /g, "_");
}

function csvEscape(value) {
  const str = String(value ?? "");
  return /[",\n]/.test(str) ? `"${str.replace(/"/g, '""')}"` : str;
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
.empty-state-icon {
  font-size: 64px;
  margin-bottom: 16px;
}
.empty-state-title {
  font-size: 24px;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 8px;
}
.empty-state-text {
  font-size: 14px;
  color: var(--ink3);
  max-width: 400px;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 20px;
  gap: 16px;
}
.page-title {
  font-size: 26px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
}
.page-sub {
  font-size: 13px;
  color: var(--ink3);
  margin: 4px 0 0;
}
.header-actions {
  display: flex;
  gap: 8px;
  align-items: center;
  flex-shrink: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.table-header {
  margin-bottom: 12px;
}
.table-controls {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-wrap: wrap;
}
.search-box {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 7px 11px;
  flex: 1;
  min-width: 200px;
  max-width: 320px;
  color: var(--ink3);
}
.search-input {
  flex: 1;
  border: none;
  background: none;
  font-size: 13px;
  color: var(--ink);
  outline: none;
}

.table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
}

.events-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
}
.events-table thead {
  background: var(--panel);
  border-bottom: 1px solid var(--border);
}
.events-table th {
  text-align: left;
  padding: 11px 14px;
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  color: var(--ink3);
  white-space: nowrap;
}
.events-table th.center {
  text-align: center;
}
.events-table td {
  padding: 12px 14px;
  border-bottom: 1px solid var(--border);
  color: var(--ink2);
}
.events-table tbody tr:last-child td {
  border-bottom: none;
}
.events-table tbody tr:hover {
  background: var(--panel);
}

.team-badge-sm {
  display: inline-block;
  background: var(--accent);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.02em;
}

.direction-badge-sm {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 16px;
  height: 16px;
  border-radius: 3px;
  font-size: 10px;
  font-weight: 700;
}
.direction-badge-sm--arrival {
  background: #dbeafe;
  color: #1e40af;
}
.direction-badge-sm--departure {
  background: #fef3c7;
  color: #92400e;
}

.party-size-badge-sm {
  display: inline-block;
  padding: 1px 4px;
  border-radius: 8px;
  font-size: 9px;
  font-weight: 600;
  background: #f3f4f6;
  color: #6b7280;
}

.mono {
  font-family: var(--mono);
}
.text-muted {
  color: var(--ink3);
}
.center {
  text-align: center;
}

/* Manage button */
.manage-team-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border: 1px solid var(--border);
  background: var(--surface);
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s;
  color: var(--ink3);
}
.manage-team-btn:hover {
  background: var(--panel);
  border-color: var(--accent);
  color: var(--accent);
}

/* Manage Modal */
.manage-modal {
  display: flex;
  flex-direction: column;
  gap: 20px;
}
.manage-section {
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 16px;
  background: var(--panel);
}
.manage-section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}
.manage-section-title {
  font-size: 14px;
  font-weight: 600;
  color: var(--ink);
  margin: 0;
}

/* Flights list */
.flights-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.flight-record {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 10px 12px;
}
.flight-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
}
.direction-badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
}
.direction-badge--arrival {
  background: #dbeafe;
  color: #1e40af;
}
.direction-badge--departure {
  background: #fef3c7;
  color: #92400e;
}
.flight-num {
  font-weight: 600;
  color: var(--ink);
}
.flight-airports {
  font-family: var(--mono);
  color: var(--ink3);
  font-size: 11px;
}
.flight-time {
  color: var(--ink2);
}
.party-size-badge {
  display: inline-block;
  padding: 2px 6px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  background: #f3f4f6;
  color: #6b7280;
}
.fr-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  border: 1px solid var(--border);
  background: var(--surface);
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s;
  color: var(--ink3);
}
.fr-btn:hover {
  background: var(--panel);
  border-color: var(--accent);
  color: var(--accent);
}
.fr-btn--sync:hover {
  border-color: #3b82f6;
  color: #3b82f6;
  background: #eff6ff;
}
.fr-btn--del:hover {
  border-color: #ef4444;
  color: #ef4444;
}

/* Stays */
.stay-record {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 6px;
  padding: 12px;
  font-size: 13px;
}
.stay-row {
  display: flex;
  gap: 12px;
  padding: 6px 0;
  border-bottom: 1px solid var(--border);
}
.stay-row:last-of-type {
  border-bottom: none;
}
.stay-label {
  font-weight: 600;
  color: var(--ink3);
  min-width: 70px;
}
.stay-delete-btn {
  margin-top: 8px;
  width: 100%;
  padding: 6px;
  border: 1px solid #ef4444;
  background: transparent;
  color: #ef4444;
  border-radius: 4px;
  font-size: 12px;
  cursor: pointer;
  transition: all 0.15s;
}
.stay-delete-btn:hover {
  background: #ef4444;
  color: white;
}

/* Spinner */
.spinner-sm {
  width: 10px;
  height: 10px;
  border: 1.5px solid rgba(0,0,0,0.15);
  border-top-color: currentColor;
  border-radius: 50%;
  display: inline-block;
  animation: spin 0.6s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Sync Result Modal */
.sync-modal-body {
  display: flex;
  flex-direction: column;
  gap: 12px;
}
.sync-mismatch-warn {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  background: #fef3c7;
  border: 1px solid #f59e0b;
  border-radius: 6px;
  font-size: 12px;
  color: #92400e;
  line-height: 1.5;
}
.sync-status-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: 10px;
  font-size: 10px;
  font-weight: 600;
  text-transform: capitalize;
}
.sync-status--landed { background: #d1fae5; color: #065f46; }
.sync-status--active { background: #d1fae5; color: #065f46; }
.sync-status--scheduled { background: #dbeafe; color: #1e40af; }
.sync-status--cancelled { background: #fee2e2; color: #991b1b; }
.sync-status--delayed { background: #fef3c7; color: #92400e; }

.sync-card {
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  background: var(--surface);
}
.sync-route-bar {
  display: flex;
  align-items: center;
  padding: 20px 24px 14px;
  gap: 12px;
}
.sync-iata-big {
  font-size: 34px;
  font-weight: 700;
  color: var(--ink);
  letter-spacing: -0.5px;
  flex-shrink: 0;
}
.sync-route-middle {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
}
.sync-duration-label {
  font-size: 12px;
  color: var(--ink3);
}
.sync-arrow-line {
  width: 100%;
  display: flex;
  align-items: center;
}
.sync-line-track {
  flex: 1;
  height: 2px;
  background: #8b1a1a;
}
.sync-plane-icon {
  color: #8b1a1a;
  flex-shrink: 0;
  margin-left: -2px;
}
.sync-airports-grid {
  display: flex;
  padding: 0 24px 18px;
}
.sync-airport-section {
  flex: 1;
  min-width: 0;
}
.sync-airport-section--right {
  text-align: right;
}
.sync-divider-v {
  width: 1px;
  background: var(--border);
  margin: 0 20px;
  flex-shrink: 0;
  align-self: stretch;
}
.sync-airport-city {
  font-size: 12px;
  color: var(--ink3);
  margin-bottom: 1px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.sync-airport-date {
  font-size: 13px;
  font-weight: 500;
  color: var(--ink2);
  margin-bottom: 10px;
}
.sync-detail-row {
  display: flex;
  gap: 20px;
}
.sync-detail-row--right {
  justify-content: flex-end;
}
.sync-detail-col {
  min-width: 0;
}
.sync-detail-label {
  font-size: 11px;
  color: var(--ink3);
  margin-bottom: 2px;
}
.sync-detail-time-actual {
  font-size: 20px;
  font-weight: 600;
  color: #8b1a1a;
  line-height: 1.2;
}
.sync-detail-time-sched {
  font-size: 11px;
  color: var(--ink3);
  text-decoration: line-through;
  margin-top: 1px;
}
.sync-detail-val {
  font-size: 14px;
  color: var(--ink2);
  padding-top: 4px;
}
.sync-card-footer {
  padding: 8px 24px;
  border-top: 1px solid var(--border);
  font-size: 11px;
  color: var(--ink3);
  background: var(--panel);
}

/* Empty state */
.empty-state {
  padding: 20px;
  text-align: center;
  color: var(--ink3);
  font-size: 12px;
}

/* Forms */
.team-form {
  display: flex;
  flex-direction: column;
  gap: 14px;
}
.form-row {
  display: flex;
  gap: 12px;
}
.form-group {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 5px;
}
.form-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--ink2);
}
.required {
  color: #ef4444;
}
.form-input,
.form-select {
  padding: 7px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  font-size: 13px;
  background: var(--surface);
  color: var(--ink);
}
.form-input:focus,
.form-select:focus {
  outline: none;
  border-color: var(--accent);
}
.form-actions {
  display: flex;
  gap: 8px;
  justify-content: flex-end;
  margin-top: 8px;
}
</style>
