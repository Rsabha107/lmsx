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
          <Button variant="primary" size="sm" @click="openAddTeamModal">
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
                    <span class="team-badge-sm">{{ et.team?.code }}</span>
                    <span style="font-weight: 500">{{
                      et.team?.team_name
                    }}</span>
                  </div>
                </td>
                <td>
                  <span v-if="et.team?.country"
                    >{{ et.team.country.flag }}
                    {{ et.team.country.country_name }}</span
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
          <span class="team-badge-sm">{{ managingTeam?.team?.code }}</span>
          <span>{{ managingTeam?.team?.team_name }}</span>
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
            <div class="stay-row" v-if="managingTeam.stay.training_ground">
              <span class="stay-label">Training</span
              ><span>{{ managingTeam.stay.training_ground }}</span>
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
            <input
              v-model="flightForm.scheduled_at"
              type="datetime-local"
              class="form-input"
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
            <input
              v-model="stayForm.hotel_name"
              type="text"
              class="form-input"
              maxlength="255"
            />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input v-model="stayForm.address" type="text" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Training Ground</label>
          <input
            v-model="stayForm.training_ground"
            type="text"
            class="form-input"
          />
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

    <!-- Add Team Modal -->
    <Modal :show="showAddTeamModal" @close="showAddTeamModal = false" max-width="420px">
      <template #title>Add Team to Event</template>
      <form @submit.prevent="submitAddTeam" class="team-form">
        <div class="form-group">
          <label class="form-label">Team <span class="required">*</span></label>
          <select v-model="addTeamForm.team_code" class="form-select" required>
            <option value="">— Select team —</option>
            <option v-for="t in availableTeams" :key="t.code" :value="t.code">
              {{ t.code }} · {{ t.team_name }}
            </option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Group / Pool</label>
            <input v-model="addTeamForm.group_pool" type="text" class="form-input" placeholder="e.g. Group A" maxlength="50" />
          </div>
          <div class="form-group">
            <label class="form-label">Classification</label>
            <select v-model="addTeamForm.classification_type_id" class="form-select">
              <option value="">— None —</option>
              <option v-for="c in classifications" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showAddTeamModal = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">Add Team</Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <DeleteConfirmModal
      :show="showDeleteModal"
      :title="deleteType"
      :message="deleteMessage"
      :processing="deleting"
      @close="closeDeleteModal"
      @confirm="confirmDelete"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { router } from "@inertiajs/vue3";
import AppLayout from "@/Components/AppLayout.vue";
import Modal from "@/Components/Modal.vue";
import Button from "@/Components/Button.vue";
import RefreshButton from "@/Components/RefreshButton.vue";
import MiniStat from "@/Components/MiniStat.vue";
import SvgIcon from "@/Components/SvgIcon.vue";
import DeleteConfirmModal from "@/Components/DeleteConfirmModal.vue";

const props = defineProps({
  activeEvent: { type: Object, default: null },
  eventTeams: { type: Array, default: () => [] },
  airports: { type: Array, default: () => [] },
  teams: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
});

const searchQuery = ref("");

const filteredTeams = computed(() => {
  let teams = props.eventTeams;

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase();
    teams = teams.filter(
      (et) =>
        et.team?.team_name?.toLowerCase().includes(q) ||
        et.team?.code?.toLowerCase().includes(q) ||
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

// ── Manage Team Modal ──────────────────────────────────────────────────────
const showManageModal = ref(false);
const managingTeam = ref(null);
const processing = ref(false);

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
      const fresh = props.eventTeams.find((t) => t.team_id === et.team_id);
      if (fresh) managingTeam.value = fresh;
    },
  });
}

// ── Add Team Modal ─────────────────────────────────────────────────────────
const showAddTeamModal = ref(false);
const addTeamForm = ref({ team_code: '', group_pool: '', classification_type_id: '' });

const availableTeams = computed(() => {
  const assigned = new Set(props.eventTeams.map(et => et.team.code));
  return props.teams.filter(t => !assigned.has(t.code));
});

function openAddTeamModal() {
  addTeamForm.value = { team_code: '', group_pool: '', classification_type_id: '' };
  showAddTeamModal.value = true;
}

function submitAddTeam() {
  if (!props.activeEvent) return;
  processing.value = true;
  router.post(`/events/${props.activeEvent.id}/teams`, addTeamForm.value, {
    onFinish: () => {
      processing.value = false;
      showAddTeamModal.value = false;
    },
  });
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
  }
}

// ── Flights ────────────────────────────────────────────────────────────────
const showFlightForm = ref(false);
const editingFlight = ref(null);
const flightForm = ref(emptyFlightForm());

// Auto-calculate total party size
watch(
  () => [flightForm.value.party_size_players, flightForm.value.party_size_staff],
  ([players, staff]) => {
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
    notes: "",
  };
}

function openFlightForm(fl) {
  processing.value = false;
  editingFlight.value = fl;
  flightForm.value = fl
    ? {
        direction: fl.direction,
        flight_number: fl.flight_number || "",
        origin_airport_id: fl.origin_airport_id || "",
        destination_airport_id: fl.destination_airport_id || "",
        scheduled_at: fl.scheduled_at
          ? String(fl.scheduled_at).substring(0, 16)
          : "",
        gate: fl.gate || "",
        party_size_total: fl.party_size_total || "",
        party_size_players: fl.party_size_players || "",
        party_size_staff: fl.party_size_staff || "",
        notes: fl.notes || "",
      }
    : emptyFlightForm();
  showFlightForm.value = true;
}

function submitFlight() {
  processing.value = true;
  const et = managingTeam.value;
  const url = editingFlight.value
    ? `/events/${et.event_id}/flights/${editingFlight.value.id}`
    : `/events/${et.event_id}/teams/${et.team.code}/flights`;
  const method = editingFlight.value ? "put" : "post";
  router[method](url, flightForm.value, {
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

// ── Stays ──────────────────────────────────────────────────────────────────
const showStayForm = ref(false);
const editingStay = ref(null);
const stayForm = ref(emptyStayForm());

function emptyStayForm() {
  return {
    hotel_name: "",
    address: "",
    training_ground: "",
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
        training_ground: stay.training_ground || "",
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
    : `/events/${et.event_id}/teams/${et.team.code}/stays`;
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
