<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Events</h1>
        <p class="page-sub">{{ events.length }} event{{ events.length !== 1 ? 's' : '' }}</p>
      </div>
      <div class="header-actions">
        <RefreshButton :only="['events']" />
        <Button variant="primary" size="sm" @click="openAddModal">
          <template #icon>
            <svg-icon name="plus" :size="14" style="color:#fff;" />
          </template>
          Add Event
        </Button>
      </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
      <mini-stat label="Total Events"  :value="events.length" />
      <mini-stat label="Active"        :value="countByStatus('active')"    tone="primary" />
      <mini-stat label="Upcoming"      :value="countByStatus('upcoming')"  tone="ok" />
      <mini-stat label="Completed"     :value="countByStatus('completed')" />
    </div>

    <!-- Table controls -->
    <div class="table-header">
      <div class="table-controls">
        <div class="search-box">
          <svg-icon name="search" :size="14" />
          <input v-model="searchQuery" type="text" placeholder="Search events…" class="search-input" />
        </div>
        <select v-model="filterStatus" class="filter-select">
          <option value="">All Statuses</option>
          <option value="upcoming">Upcoming</option>
          <option value="active">Active</option>
          <option value="completed">Completed</option>
        </select>
      </div>
    </div>

    <!-- Table + Detail panel -->
    <div class="events-container">
      <div class="table-card" :class="{ 'with-panel': selectedEvent }">
        <div style="overflow-x:auto;">
          <table class="events-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Short Name</th>
                <th>Host Country</th>
                <th>Dates</th>
                <th class="center">Teams</th>
                <th class="center">Status</th>
                <th class="center" style="width:100px;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="event in filteredEvents"
                :key="event.id"
                class="table-row"
                :class="{ 'table-row--selected': selectedEvent?.id === event.id }"
                @click="selectEvent(event)"
              >
                <td class="event-name-cell">
                  <div class="event-name-primary">{{ event.name }}</div>
                </td>
                <td class="mono">{{ event.short_name || '—' }}</td>
                <td>
                  <span v-if="event.country" style="display: inline-flex; align-items: center; gap: 6px">
                    <flag-icon :code="event.host_country" :fallback="event.country.flag" />
                    {{ event.country.country_name }}</span
                  >
                  <span v-else>—</span>
                </td>
                <td class="mono">
                  <span v-if="event.start_date || event.end_date">
                    {{ formatDate(event.start_date) }} – {{ formatDate(event.end_date) }}
                  </span>
                  <span v-else>—</span>
                </td>
                <td class="center mono">{{ event.teams?.length ?? 0 }}</td>
                <td class="center">
                  <span :class="['status-pill', `status-pill--${event.status}`]">{{ event.status }}</span>
                </td>
                <td class="actions-cell" @click.stop>
                  <TableActions
                    @edit="editEvent(event)"
                    @delete="openDeleteModal(event)"
                  />
                </td>
              </tr>
              <tr v-if="filteredEvents.length === 0">
                <td colspan="7" style="text-align:center;padding:40px;color:var(--ink3);">No events found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Detail panel -->
      <transition name="slide-card">
        <div v-if="selectedEvent" class="detail-card">
          <div class="detail-card-header">
            <div>
              <h3 class="detail-card-team-name">{{ selectedEvent.name }}</h3>
              <div style="font-size:12px;color:var(--ink3);margin-top:2px;">
                <span :class="['status-pill', `status-pill--${selectedEvent.status}`]">{{ selectedEvent.status }}</span>
              </div>
            </div>
            <button @click="selectedEvent = null" class="detail-card-close">
              <svg-icon name="x" :size="18" />
            </button>
          </div>

          <div class="detail-card-content">
            <!-- Assigned Venues -->
            <div class="detail-section">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h4 class="detail-section-title" style="margin:0;">
                  Venues ({{ selectedEvent.venues?.length ?? 0 }})
                </h4>
                <Button variant="secondary" size="sm" @click="openVenueModal(selectedEvent)">
                  <template #icon><svg-icon name="plus" :size="12" /></template>
                  Assign
                </Button>
              </div>
              <div v-if="!selectedEvent.venues?.length" style="font-size:12px;color:var(--ink3);">No venues assigned yet.</div>
              <div v-for="venue in selectedEvent.venues" :key="venue.id" class="assigned-venue-row">
                <div class="assigned-venue-info">
                  <div>
                    <div style="font-size:13px;font-weight:500;">{{ venue.name }}</div>
                    <div style="font-size:11px;color:var(--ink3);">
                      <span v-if="venue.pivot.purpose" class="venue-purpose-badge">{{ venue.pivot.purpose }}</span>
                      <span v-if="venue.city">{{ venue.city }}</span>
                      <span v-if="venue.country" style="display: inline-flex; align-items: center; gap: 4px">
                        · <flag-icon :code="venue.country_code" :fallback="venue.country.flag" /> {{ venue.country.country_name }}</span
                      >
                      <span v-if="venue.capacity"> · {{ venue.capacity }} capacity</span>
                    </div>
                  </div>
                </div>
                <button class="remove-team-btn" @click="removeVenue(selectedEvent, venue.id)" title="Remove from event">
                  <svg-icon name="x" :size="13" />
                </button>
              </div>
            </div>

            <!-- Assigned Teams -->
            <div class="detail-section">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <h4 class="detail-section-title" style="margin:0;">
                  Teams ({{ selectedEvent.teams?.length ?? 0 }})
                </h4>
              </div>
              <div v-if="!selectedEvent.teams?.length" style="font-size:12px;color:var(--ink3);">No teams assigned yet. Manage teams from the Event Teams page.</div>
              <div v-for="et in selectedEvent.teams" :key="et.id" class="assigned-team-row">
                <div class="assigned-team-info">
                  <span class="team-badge-sm">{{ et.code }}</span>
                  <div>
                    <div style="font-size:13px;font-weight:500;">{{ et.team_name }}</div>
                    <div style="font-size:11px;color:var(--ink3);">
                      {{ et.group_pool || 'No group' }}
                      <span v-if="et.classification"> · {{ et.classification.name }}</span>
                    </div>
                    <div style="font-size:11px;color:var(--ink3);margin-top:2px;">
                      <span v-if="et.flights?.length">
                        ✈ {{ et.flights.length }} flight{{ et.flights.length > 1 ? 's' : '' }}
                        <span v-if="et.flights.some(f => f.party_size_total)">
                          ({{ et.flights.reduce((sum, f) => sum + (f.party_size_total || 0), 0) }} pax)
                        </span>
                      </span>
                      <span v-if="et.stay"> · 🏨 {{ et.stay.hotel_name }}</span>
                    </div>
                  </div>
                </div>
                <div style="display:flex;gap:4px;align-items:center;">
                  <button class="manage-team-btn" @click="openManageModal(selectedEvent, et)" title="Manage flights & stay">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
                  </button>
                  <button class="remove-team-btn" @click="removeTeam(selectedEvent, et.code)" title="Remove from event">
                    <svg-icon name="x" :size="13" />
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="detail-card-footer">
            <Button variant="secondary" size="sm" @click="selectedEvent = null">Close</Button>
            <Button variant="primary" size="sm" @click="editEvent(selectedEvent); selectedEvent = null">Edit</Button>
          </div>
        </div>
      </transition>
    </div>

    <!-- Add / Edit Event Modal -->
    <Modal :show="showEventModal" @close="showEventModal = false" max-width="560px">
      <template #title>{{ editingEvent ? 'Edit Event' : 'Add Event' }}</template>
      <form @submit.prevent="submitEvent" class="team-form">
        <div class="form-row">
          <div class="form-group" style="flex:2;">
            <label class="form-label">Event Name <span class="required">*</span></label>
            <input v-model="form.name" type="text" class="form-input" placeholder="FIFA U17 World Cup 2025" required />
          </div>
          <div class="form-group">
            <label class="form-label">Short Name</label>
            <input v-model="form.short_name" type="text" class="form-input" placeholder="U17 WC 2025" maxlength="100" />
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Host Country</label>
            <select v-model="form.host_country" class="form-select">
              <option value="">— None —</option>
              <option v-for="c in countries" :key="c.country_code" :value="c.country_code">
                {{ c.flag }} {{ c.country_name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select v-model="form.status" class="form-select">
              <option value="upcoming">Upcoming</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input v-model="form.start_date" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">End Date</label>
            <input v-model="form.end_date" type="date" class="form-input" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="form.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showEventModal = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">
            {{ processing ? 'Saving…' : (editingEvent ? 'Save Changes' : 'Create Event') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Assign Venue Modal -->
    <Modal :show="showVenueModal" @close="showVenueModal = false" max-width="480px">
      <template #title>Assign Venue to Event</template>
      <form @submit.prevent="submitVenue" class="team-form">
        <div class="form-group">
          <label class="form-label">Venue <span class="required">*</span></label>
          <select v-model="venueForm.venue_id" class="form-select" required>
            <option value="">— Select venue —</option>
            <option v-for="v in availableVenues" :key="v.id" :value="v.id">
              {{ v.name }}
              <span v-if="v.city"> · {{ v.city }}</span>
              <span v-if="v.type"> · {{ v.type }}</span>
            </option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Purpose</label>
            <select v-model="venueForm.purpose" class="form-select">
              <option value="">— None —</option>
              <option value="match">Match</option>
              <option value="training">Training</option>
              <option value="accommodation">Accommodation</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="venueForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showVenueModal = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">Assign Venue</Button>
        </div>
      </form>
    </Modal>

    <!-- Manage Team Modal (flights + stay) -->
    <Modal :show="showManageModal" @close="showManageModal = false" max-width="620px">
      <template #title>
        <span>{{ managingTeam?.code }} · {{ managingTeam?.team_name }}</span>
      </template>

      <div v-if="managingTeam" class="manage-body">

        <!-- ── Flights ─────────────────────────────────────── -->
        <div class="manage-section">
          <div class="manage-section-header">
            <h4 class="manage-section-title">Flights</h4>
            <Button variant="secondary" size="sm" @click="openFlightForm(null)">
              <template #icon><svg-icon name="plus" :size="12"/></template>
              Add Flight
            </Button>
          </div>

          <!-- existing flights -->
          <div v-for="fl in managingTeam.flights" :key="fl.id" class="flight-record">
            <div class="flight-record-header">
              <span :class="['direction-badge', `direction-badge--${fl.direction}`]">{{ fl.direction }}</span>
              <span class="flight-num">{{ fl.flight_number || '—' }}</span>
              <span class="flight-airports" v-if="fl.origin_airport || fl.destination_airport">
                {{ fl.origin_airport?.code || '?' }} → {{ fl.destination_airport?.code || '?' }}
              </span>
              <span class="flight-time mono" v-if="fl.scheduled_at">{{ fmtDT(fl.scheduled_at) }}</span>
              <span v-if="fl.party_size_total" class="party-size-badge">{{ fl.party_size_total }} pax</span>
              <span v-if="fl.delay_minutes" :class="['delay-badge', fl.delay_minutes > 0 ? 'delay-badge--late' : '']">
                +{{ fl.delay_minutes }}m
              </span>
              <div style="display:flex;gap:4px;margin-left:auto;">
                <button class="fr-btn fr-btn--sync" :disabled="syncingFlightId === fl.id" @click="syncFlight(fl)" title="Sync from AviationStack">
                  <span v-if="syncingFlightId === fl.id" class="spinner-sm"></span>
                  <svg v-else width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
                </button>
                <button class="fr-btn" @click="openFlightForm(fl)" title="Edit">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
                <button class="fr-btn fr-btn--del" @click="deleteFlight(fl)" title="Delete">
                  <svg width="13" height="13" viewBox="0 0 16 16" fill="none"><path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
              </div>
            </div>
            <!-- inline sync result card -->
            <div v-if="flightCardMap[fl.id]" class="fc-mini">
              <div v-if="flightCardMap[fl.id].date_mismatch" class="fc-mini-warn">⚠ Data is for {{ flightCardMap[fl.id].flight_date }} — planned is {{ flightCardMap[fl.id].planned_date }}. Not saved.</div>
              <div v-else class="fc-mini-times">
                <div class="fc-mini-leg">
                  <span class="fc-mini-label">Dep actual</span>
                  <span class="fc-mini-val">{{ fcTime(flightCardMap[fl.id].departure?.actual || flightCardMap[fl.id].departure?.scheduled) }}</span>
                  <span v-if="flightCardMap[fl.id].departure?.delay" class="delay-badge delay-badge--late">+{{ flightCardMap[fl.id].departure.delay }}m</span>
                </div>
                <div class="fc-mini-leg">
                  <span class="fc-mini-label">Arr actual</span>
                  <span class="fc-mini-val">{{ fcTime(flightCardMap[fl.id].arrival?.actual || flightCardMap[fl.id].arrival?.estimated || flightCardMap[fl.id].arrival?.scheduled) }}</span>
                  <span v-if="flightCardMap[fl.id].arrival?.delay" class="delay-badge delay-badge--late">+{{ flightCardMap[fl.id].arrival.delay }}m</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="!managingTeam.flights?.length" class="empty-state">No flights added yet.</div>
        </div>

        <!-- ── Accommodation ───────────────────────────────── -->
        <div class="manage-section">
          <div class="manage-section-header">
            <h4 class="manage-section-title">Accommodation</h4>
            <Button v-if="!managingTeam.stay" variant="secondary" size="sm" @click="openStayForm(null)">
              <template #icon><svg-icon name="plus" :size="12"/></template>
              Add Stay
            </Button>
            <Button v-else variant="secondary" size="sm" @click="openStayForm(managingTeam.stay)">Edit Stay</Button>
          </div>

          <div v-if="managingTeam.stay" class="stay-record">
            <div class="stay-row"><span class="stay-label">Hotel</span><span>{{ managingTeam.stay.hotel_name || '—' }}</span></div>
            <div class="stay-row" v-if="managingTeam.stay.training_ground"><span class="stay-label">Training</span><span>{{ managingTeam.stay.training_ground }}</span></div>
            <div class="stay-row" v-if="managingTeam.stay.check_in || managingTeam.stay.check_out">
              <span class="stay-label">Dates</span>
              <span class="mono">{{ fmtDate(managingTeam.stay.check_in) }} – {{ fmtDate(managingTeam.stay.check_out) }}</span>
            </div>
            <div class="stay-row" v-if="managingTeam.stay.room_count"><span class="stay-label">Rooms</span><span>{{ managingTeam.stay.room_count }}</span></div>
            <button class="stay-delete-btn" @click="deleteStay(managingTeam.stay)">Delete Stay</button>
          </div>
          <div v-else class="empty-state">No accommodation added yet.</div>
        </div>
      </div>
    </Modal>

    <!-- Flight Form Modal -->
    <Modal :show="showFlightForm" @close="showFlightForm = false" max-width="480px">
      <template #title>{{ editingFlight ? 'Edit Flight' : 'Add Flight' }}</template>
      <form @submit.prevent="submitFlight" class="team-form">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Direction <span class="required">*</span></label>
            <select v-model="flightForm.direction" class="form-select" required>
              <option value="arrival">Arrival</option>
              <option value="departure">Departure</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Flight Number</label>
            <input v-model="flightForm.flight_number" type="text" class="form-input" placeholder="QR615" maxlength="20" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Origin Airport</label>
          <select v-model="flightForm.origin_airport_id" class="form-select">
            <option value="">— None —</option>
            <option v-for="a in airports" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Destination Airport</label>
          <select v-model="flightForm.destination_airport_id" class="form-select">
            <option value="">— None —</option>
            <option v-for="a in airports" :key="a.id" :value="a.id">{{ a.code }} · {{ a.name }}</option>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Scheduled Date/Time</label>
            <input v-model="flightForm.scheduled_at" type="datetime-local" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Gate</label>
            <input v-model="flightForm.gate" type="text" class="form-input" maxlength="50" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Total Party Size (Auto-calculated)</label>
          <input v-model="flightForm.party_size_total" type="number" class="form-input" min="0" placeholder="0" readonly style="background: var(--panel); cursor: not-allowed;" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Players</label>
            <input v-model="flightForm.party_size_players" type="number" class="form-input" min="0" placeholder="0" />
          </div>
          <div class="form-group">
            <label class="form-label">Staff</label>
            <input v-model="flightForm.party_size_staff" type="number" class="form-input" min="0" placeholder="0" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="flightForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showFlightForm = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">
            {{ processing ? 'Saving…' : (editingFlight ? 'Save Changes' : 'Add Flight') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Stay Form Modal -->
    <Modal :show="showStayForm" @close="showStayForm = false" max-width="480px">
      <template #title>{{ editingStay ? 'Edit Accommodation' : 'Add Accommodation' }}</template>
      <form @submit.prevent="submitStay" class="team-form">
        <div class="form-row">
          <div class="form-group" style="flex:2;">
            <label class="form-label">Hotel Name</label>
            <input v-model="stayForm.hotel_name" type="text" class="form-input" maxlength="255" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Address</label>
          <input v-model="stayForm.address" type="text" class="form-input" />
        </div>
        <div class="form-group">
          <label class="form-label">Training Ground</label>
          <input v-model="stayForm.training_ground" type="text" class="form-input" />
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Check-in</label>
            <input v-model="stayForm.check_in" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Check-out</label>
            <input v-model="stayForm.check_out" type="date" class="form-input" />
          </div>
          <div class="form-group">
            <label class="form-label">Rooms</label>
            <input v-model="stayForm.room_count" type="number" class="form-input" min="0" />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea v-model="stayForm.notes" class="form-input" rows="2" />
        </div>
        <div class="form-actions">
          <Button type="button" variant="secondary" size="sm" @click="showStayForm = false">Cancel</Button>
          <Button type="submit" variant="primary" size="sm" :disabled="processing">
            {{ processing ? 'Saving…' : (editingStay ? 'Save Changes' : 'Add Stay') }}
          </Button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirm Modal -->
    <DeleteConfirmModal
      :show="showDeleteModal"
      title="Event"
      :message="eventToDelete ? `Are you sure you want to delete <strong>${eventToDelete.name}</strong>?<br><br>All team assignments will be removed.` : ''"
      :processing="deleting"
      @close="showDeleteModal = false; eventToDelete = null;"
      @confirm="confirmDelete"
    />

    <!-- Delete Confirm Modal for Flights/Stays -->
    <DeleteConfirmModal
      :show="showManageDeleteModal"
      :title="manageDeleteType"
      :message="manageDeleteMessage"
      :processing="managingDeleting"
      @close="closeManageDeleteModal"
      @confirm="confirmManageDelete"
    />
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout       from '../Components/AppLayout.vue';
import MiniStat        from '../Components/MiniStat.vue';
import SvgIcon         from '../Components/SvgIcon.vue';
import Button          from '../Components/Button.vue';
import Modal           from '../Components/Modal.vue';
import DeleteConfirmModal from '../Components/DeleteConfirmModal.vue';
import TableActions    from '../Components/TableActions.vue';
import RefreshButton   from '../Components/RefreshButton.vue';
import FlagIcon        from '../Components/FlagIcon.vue';

const props = defineProps({
  events:          { type: Array, required: true },
  classifications: { type: Array, required: true },
  countries:       { type: Array, required: true },
  airports:        { type: Array, default: () => [] },
  venues:          { type: Array, default: () => [] },
});

// ── State ──────────────────────────────────────────────────────────────────
const searchQuery   = ref('');
const filterStatus  = ref('');
const selectedEvent = ref(null);

const showEventModal   = ref(false);
const showVenueModal   = ref(false);
const showDeleteModal  = ref(false);
const processing       = ref(false);
const deleting         = ref(false);
const editingEvent     = ref(null);
const eventToDelete    = ref(null);
const assigningVenueEvent = ref(null);

const form = ref(emptyForm());
const venueForm = ref({ venue_id: '', purpose: '', notes: '' });

function emptyForm() {
  return { name: '', short_name: '', host_country: '', start_date: '', end_date: '', status: 'upcoming', notes: '' };
}

// ── Computed ───────────────────────────────────────────────────────────────
const filteredEvents = computed(() => {
  return props.events.filter(e => {
    const q = searchQuery.value.toLowerCase();
    const matchQ = !q || e.name.toLowerCase().includes(q) || (e.short_name || '').toLowerCase().includes(q);
    const matchS = !filterStatus.value || e.status === filterStatus.value;
    return matchQ && matchS;
  });
});

const availableVenues = computed(() => {
  if (!assigningVenueEvent.value) return props.venues;
  const assigned = new Set((assigningVenueEvent.value.venues || []).map(v => v.id));
  return props.venues.filter(v => !assigned.has(v.id));
});

// ── Helpers ────────────────────────────────────────────────────────────────
function countByStatus(s) {
  return props.events.filter(e => e.status === s).length;
}

function formatDate(d) {
  if (!d) return '—';
  const match = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return d;
  return new Date(parseInt(match[1]), parseInt(match[2]) - 1, parseInt(match[3]))
    .toLocaleDateString([], { day: 'numeric', month: 'short', year: 'numeric' });
}

function selectEvent(event) {
  selectedEvent.value = selectedEvent.value?.id === event.id ? null : event;
}

// ── Event CRUD ─────────────────────────────────────────────────────────────
function openAddModal() {
  editingEvent.value = null;
  form.value = emptyForm();
  showEventModal.value = true;
}

function editEvent(event) {
  editingEvent.value = event;
  form.value = {
    name:         event.name         || '',
    short_name:   event.short_name   || '',
    host_country: event.host_country || '',
    start_date:   event.start_date   ? String(event.start_date).substring(0, 10) : '',
    end_date:     event.end_date     ? String(event.end_date).substring(0, 10)   : '',
    status:       event.status       || 'upcoming',
    notes:        event.notes        || '',
  };
  showEventModal.value = true;
}

function submitEvent() {
  processing.value = true;
  const url    = editingEvent.value ? `/events/${editingEvent.value.id}` : '/events';
  const method = editingEvent.value ? 'put' : 'post';
  router[method](url, form.value, {
    onFinish: () => { processing.value = false; showEventModal.value = false; },
  });
}

function openDeleteModal(event) {
  eventToDelete.value = event;
  showDeleteModal.value = true;
}

function confirmDelete() {
  deleting.value = true;
  router.delete(`/events/${eventToDelete.value.id}`, {
    onFinish: () => { deleting.value = false; showDeleteModal.value = false; eventToDelete.value = null; },
  });
}

// ── Team removal ───────────────────────────────────────────────────────────
function removeTeam(event, teamCode) {
  router.delete(`/events/${event.id}/teams/${teamCode}`, {
    onSuccess: () => {
      const updated = props.events.find(e => e.id === event.id);
      if (updated) selectedEvent.value = updated;
    },
  });
}

// ── Venue assignment ───────────────────────────────────────────────────────
function openVenueModal(event) {
  assigningVenueEvent.value = event;
  venueForm.value = { venue_id: '', purpose: '', notes: '' };
  showVenueModal.value = true;
}

function submitVenue() {
  processing.value = true;
  router.post(`/events/${assigningVenueEvent.value.id}/venues`, venueForm.value, {
    onFinish: () => {
      processing.value = false;
      showVenueModal.value = false;
      const updated = props.events.find(e => e.id === assigningVenueEvent.value.id);
      if (updated) selectedEvent.value = updated;
    },
  });
}

function removeVenue(event, venueId) {
  router.delete(`/events/${event.id}/venues/${venueId}`, {
    onSuccess: () => {
      const updated = props.events.find(e => e.id === event.id);
      if (updated) selectedEvent.value = updated;
    },
  });
}

// ── Manage Team Modal ──────────────────────────────────────────────────────
const showManageModal  = ref(false);
const managingTeam     = ref(null);
const showFlightForm   = ref(false);
const editingFlight    = ref(null);
const flightForm       = ref(emptyFlightForm());

// Auto-calculate total party size
watch(
  () => [flightForm.value.party_size_players, flightForm.value.party_size_staff],
  ([players, staff]) => {
    const playersNum = parseInt(players) || 0;
    const staffNum = parseInt(staff) || 0;
    flightForm.value.party_size_total = playersNum + staffNum || '';
  }
);
const showStayForm     = ref(false);
const editingStay      = ref(null);
const stayForm         = ref(emptyStayForm());
const syncingFlightId  = ref(null);
const flightCardMap    = ref({});

// Delete modal for flights/stays
const showManageDeleteModal = ref(false);
const manageDeleteType      = ref('');
const manageDeleteMessage   = ref('');
const manageItemToDelete    = ref(null);
const managingDeleting      = ref(false);

function emptyFlightForm() {
  return { 
    direction: 'arrival', 
    flight_number: '', 
    origin_airport_id: '', 
    destination_airport_id: '', 
    scheduled_at: '', 
    gate: '', 
    party_size_total: '', 
    party_size_players: '', 
    party_size_staff: '', 
    notes: '' 
  };
}
function emptyStayForm() {
  return { hotel_name: '', address: '', training_ground: '', check_in: '', check_out: '', room_count: '', notes: '' };
}

function openManageModal(event, et) {
  managingTeam.value = et;
  showManageModal.value = true;
}

function refreshManagingTeam() {
  const et = managingTeam.value;
  if (!et) return;
  const event = props.events.find(e => e.id === et.event_id);
  if (!event) return;
  const fresh = event.teams?.find(t => t.id === et.id);
  if (fresh) managingTeam.value = fresh;
}

function openManageDeleteModal(type, message, item) {
  manageDeleteType.value      = type;
  manageDeleteMessage.value   = message;
  manageItemToDelete.value    = item;
  showManageDeleteModal.value = true;
}

function closeManageDeleteModal() {
  showManageDeleteModal.value = false;
  manageItemToDelete.value    = null;
  managingDeleting.value      = false;
}

function confirmManageDelete() {
  if (!manageItemToDelete.value) return;
  
  managingDeleting.value = true;
  const item = manageItemToDelete.value;
  
  if (item.type === 'flight') {
    router.delete(`/events/${managingTeam.value.event_id}/flights/${item.id}`, {
      onSuccess: () => {
        refreshManagingTeam();
        closeManageDeleteModal();
      },
      onError: () => {
        managingDeleting.value = false;
      }
    });
  } else if (item.type === 'stay') {
    router.delete(`/events/${managingTeam.value.event_id}/stays/${item.id}`, {
      onSuccess: () => {
        refreshManagingTeam();
        closeManageDeleteModal();
      },
      onError: () => {
        managingDeleting.value = false;
      }
    });
  }
}

// ── Flights ────────────────────────────────────────────────────────────────
function openFlightForm(fl) {
  editingFlight.value = fl;
  flightForm.value = fl
    ? { 
        direction: fl.direction, 
        flight_number: fl.flight_number || '', 
        origin_airport_id: fl.origin_airport_id || '', 
        destination_airport_id: fl.destination_airport_id || '', 
        scheduled_at: fl.scheduled_at ? String(fl.scheduled_at).substring(0, 16) : '', 
        gate: fl.gate || '', 
        party_size_total: fl.party_size_total || '', 
        party_size_players: fl.party_size_players || '', 
        party_size_staff: fl.party_size_staff || '', 
        notes: fl.notes || '' 
      }
    : emptyFlightForm();
  showFlightForm.value = true;
}

function submitFlight() {
  processing.value = true;
  const et = managingTeam.value;
  const url    = editingFlight.value ? `/events/${et.event_id}/flights/${editingFlight.value.id}` : `/events/${et.event_id}/teams/${et.code}/flights`;
  const method = editingFlight.value ? 'put' : 'post';
  router[method](url, flightForm.value, {
    onFinish:  () => { processing.value = false; showFlightForm.value = false; },
    onSuccess: () => refreshManagingTeam(),
  });
}

function deleteFlight(fl) {
  openManageDeleteModal(
    'Flight',
    `Are you sure you want to delete ${fl.direction} flight ${fl.flight_number || 'this flight'}?`,
    { type: 'flight', id: fl.id }
  );
}

async function syncFlight(fl) {
  syncingFlightId.value = fl.id;
  try {
    const res = await fetch(`/events/${managingTeam.value.event_id}/flights/${fl.id}/sync`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    });
    if (res.ok) {
      const data = await res.json();
      flightCardMap.value = { ...flightCardMap.value, [fl.id]: data };
    }
  } finally {
    syncingFlightId.value = null;
    router.reload({ only: ['events'], onSuccess: () => refreshManagingTeam() });
  }
}

// ── Stays ──────────────────────────────────────────────────────────────────
function openStayForm(stay) {
  editingStay.value = stay;
  stayForm.value = stay
    ? { hotel_name: stay.hotel_name || '', address: stay.address || '', training_ground: stay.training_ground || '', check_in: stay.check_in ? String(stay.check_in).substring(0, 10) : '', check_out: stay.check_out ? String(stay.check_out).substring(0, 10) : '', room_count: stay.room_count || '', notes: stay.notes || '' }
    : emptyStayForm();
  showStayForm.value = true;
}

function submitStay() {
  processing.value = true;
  const et = managingTeam.value;
  const url    = editingStay.value ? `/events/${et.event_id}/stays/${editingStay.value.id}` : `/events/${et.event_id}/teams/${et.code}/stays`;
  const method = editingStay.value ? 'put' : 'post';
  router[method](url, stayForm.value, {
    onFinish:  () => { processing.value = false; showStayForm.value = false; },
    onSuccess: () => refreshManagingTeam(),
  });
}

function deleteStay(stay) {
  openManageDeleteModal(
    'Accommodation',
    `Are you sure you want to delete the accommodation at ${stay.hotel_name || 'this hotel'}?`,
    { type: 'stay', id: stay.id }
  );
}

// ── Time helpers ───────────────────────────────────────────────────────────
function fcTime(iso) {
  if (!iso) return '—';
  const m = String(iso).match(/T(\d{2}):(\d{2})/);
  if (!m) return iso;
  let h = parseInt(m[1]);
  const min = m[2];
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${h}:${min} ${ampm}`;
}

function fmtDate(d) {
  if (!d) return '—';
  const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!m) return d;
  return new Date(parseInt(m[1]), parseInt(m[2]) - 1, parseInt(m[3]))
    .toLocaleDateString([], { day: 'numeric', month: 'short' });
}

function fmtDT(dt) {
  if (!dt) return '—';
  const dm = String(dt).match(/^(\d{4})-(\d{2})-(\d{2})/);
  const tm = String(dt).match(/T(\d{2}):(\d{2})/);
  if (!dm) return dt;
  const d = new Date(parseInt(dm[1]), parseInt(dm[2]) - 1, parseInt(dm[3]));
  const dateStr = d.toLocaleDateString([], { day: 'numeric', month: 'short' });
  if (!tm) return dateStr;
  let h = parseInt(tm[1]);
  const ampm = h >= 12 ? 'PM' : 'AM';
  h = h % 12 || 12;
  return `${dateStr} ${h}:${tm[2]} ${ampm}`;
}
</script>

<style scoped>
/* Reuse the same design tokens as Teams/Matches */
.page-header { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
.page-title  { font-size:22px; font-weight:700; color:var(--ink); margin:0; }
.page-sub    { font-size:13px; color:var(--ink3); margin:2px 0 0; }
.header-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

.stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; margin-bottom:20px; }
@media(max-width:768px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }

.table-header   { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px; }
.table-controls { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.search-box     { display:flex; align-items:center; gap:8px; background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 12px; }
.search-input   { background:transparent; border:none; outline:none; font-size:13px; color:var(--ink); width:200px; }
.filter-select  { background:var(--surface); border:1px solid var(--border); border-radius:8px; padding:6px 10px; font-size:13px; color:var(--ink); cursor:pointer; }

.events-container { display:flex; gap:16px; align-items:flex-start; }
.table-card       { background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; flex:1; min-width:0; }
.table-card.with-panel { flex:1.4; }

.events-table { width:100%; border-collapse:collapse; font-size:13px; }
.events-table thead th { padding:10px 14px; text-align:left; font-size:11px; font-weight:600; color:var(--ink3); text-transform:uppercase; letter-spacing:.04em; border-bottom:1px solid var(--border); white-space:nowrap; background:var(--panel); }
.events-table thead th.center { text-align:center; }
.events-table tbody td { padding:11px 14px; border-bottom:1px solid var(--border); color:var(--ink2); vertical-align:middle; }
.events-table tbody tr:last-child td { border-bottom:none; }
.table-row { cursor:pointer; transition:background .12s; }
.table-row:hover   { background:var(--panel); }
.table-row--selected { background:var(--accent-soft) !important; }
.event-name-primary { font-weight:600; color:var(--ink); }
.mono { font-family:monospace; }
.center { text-align:center; }
.actions-cell { padding:8px 14px !important; }

/* Status pills */
.status-pill { display:inline-block; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:600; text-transform:capitalize; }
.status-pill--upcoming  { background:#EFF6FF; color:#1d4ed8; }
.status-pill--active    { background:#F0FDF4; color:#15803d; }
.status-pill--completed { background:var(--panel); color:var(--ink3); }

/* Detail card */
.detail-card { width:320px; flex-shrink:0; background:var(--surface); border:1px solid var(--border); border-radius:12px; overflow:hidden; max-height:calc(100vh - 40px); display:flex; flex-direction:column; }
.detail-card-header { display:flex; align-items:flex-start; justify-content:space-between; padding:16px; border-bottom:1px solid var(--border); flex-shrink:0; }
.detail-card-team-name { font-size:15px; font-weight:700; color:var(--ink); margin:0; }
.detail-card-close { background:none; border:none; cursor:pointer; color:var(--ink3); padding:2px; border-radius:4px; }
.detail-card-close:hover { background:var(--panel); }
.detail-card-content { padding:0 16px; overflow-y:auto; flex:1; min-height:0; }
.detail-card-footer { padding:12px 16px; border-top:1px solid var(--border); display:flex; gap:8px; justify-content:flex-end; flex-shrink:0; }
.detail-section { padding:14px 0; border-bottom:1px solid var(--border); }
.detail-section:last-child { border-bottom:none; }
.detail-section-title { font-size:11px; font-weight:700; color:var(--ink3); text-transform:uppercase; letter-spacing:.06em; margin:0 0 10px; }
.detail-row   { display:flex; justify-content:space-between; gap:12px; margin-bottom:7px; font-size:13px; }
.detail-label { color:var(--ink3); flex-shrink:0; }
.detail-value { color:var(--ink); text-align:right; }

/* Assigned team row */
.assigned-team-row { display:flex; align-items:center; justify-content:space-between; padding:7px 0; border-bottom:1px solid var(--border); }
.assigned-team-row:last-child { border-bottom:none; }
.assigned-team-info { display:flex; align-items:center; gap:8px; }
.team-badge-sm { display:inline-flex; align-items:center; justify-content:center; padding:2px 7px; border-radius:6px; font-size:11px; font-weight:700; background:var(--accent-soft); color:var(--accent); border:1px solid var(--accent); }
.remove-team-btn { background:none; border:none; cursor:pointer; color:var(--ink3); padding:4px; border-radius:4px; }
.remove-team-btn:hover { background:rgba(239,68,68,.1); color:#ef4444; }

/* Form */
.team-form  { display:flex; flex-direction:column; gap:14px; }
.form-row   { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.form-group { display:flex; flex-direction:column; gap:4px; }
.form-label { font-size:12px; font-weight:600; color:var(--ink2); }
.required   { color:#ef4444; }
.form-input, .form-select { background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:8px 10px; font-size:13px; color:var(--ink); outline:none; }
.form-input:focus, .form-select:focus { border-color:var(--accent); }
.form-actions { display:flex; justify-content:flex-end; gap:8px; padding-top:4px; }

/* Slide-in transition */
.slide-card-enter-active, .slide-card-leave-active { transition:all .2s ease; }
.slide-card-enter-from, .slide-card-leave-to { opacity:0; transform:translateX(20px); }

/* Manage modal */
.manage-body { display:flex; flex-direction:column; }
.manage-section { padding:14px 0; border-bottom:1px solid var(--border); }
.manage-section:last-child { border-bottom:none; }
.manage-section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:10px; }
.manage-section-title { font-size:11px; font-weight:700; color:var(--ink3); text-transform:uppercase; letter-spacing:.06em; margin:0; }

/* Flight records */
.flight-record { background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:10px 12px; margin-bottom:8px; }
.flight-record:last-child { margin-bottom:0; }
.flight-record-header { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.direction-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:10px; font-weight:700; text-transform:uppercase; }
.direction-badge--arrival   { background:#EFF6FF; color:#1d4ed8; }
.direction-badge--departure { background:#F0FDF4; color:#15803d; }
.flight-num      { font-weight:700; font-size:13px; color:var(--ink); font-family:monospace; }
.flight-airports { font-size:12px; color:var(--ink3); font-family:monospace; }
.flight-time     { font-size:12px; color:var(--ink2); }

/* Party size badge */
.party-size-badge { display:inline-block; padding:2px 6px; border-radius:10px; font-size:10px; font-weight:600; background:#f3f4f6; color:#6b7280; }

/* Flight record action buttons */
.fr-btn { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border:1px solid var(--border); background:var(--surface); border-radius:6px; cursor:pointer; color:var(--ink3); transition:all .15s; }
.fr-btn:hover       { background:var(--panel); color:var(--ink); }
.fr-btn--sync:hover { border-color:#6366f1; color:#6366f1; }
.fr-btn--del:hover  { border-color:#ef4444; color:#ef4444; background:rgba(239,68,68,.06); }
.fr-btn:disabled    { opacity:.4; cursor:not-allowed; }

/* Stay record */
.stay-record { background:var(--panel); border:1px solid var(--border); border-radius:8px; padding:10px 12px; }
.stay-row  { display:flex; gap:12px; align-items:baseline; font-size:13px; margin-bottom:6px; }
.stay-label { font-size:11px; color:var(--ink3); width:64px; flex-shrink:0; }
.stay-delete-btn { margin-top:10px; background:none; border:1px solid #fca5a5; color:#dc2626; border-radius:6px; padding:4px 10px; font-size:12px; font-weight:500; cursor:pointer; transition:all .15s; }
.stay-delete-btn:hover { background:#FEF2F2; border-color:#dc2626; }

/* Inline sync card */
.fc-mini { margin-top:8px; padding:8px 10px; background:var(--surface); border:1px solid var(--border); border-radius:6px; font-size:12px; }
.fc-mini-warn  { color:#92400e; background:#FFFBEB; border-radius:4px; padding:4px 8px; font-size:11px; }
.fc-mini-times { display:flex; gap:20px; }
.fc-mini-leg   { display:flex; align-items:center; gap:6px; }
.fc-mini-label { font-size:11px; color:var(--ink3); }
.fc-mini-val   { font-weight:600; color:var(--ink); font-family:monospace; }

/* Delay badge */
.delay-badge       { display:inline-block; padding:1px 6px; border-radius:8px; font-size:10px; font-weight:700; background:var(--panel); color:var(--ink3); }
.delay-badge--late { background:#FEF2F2; color:#dc2626; }

/* Manage team button in detail panel */
.manage-team-btn { background:none; border:none; cursor:pointer; color:var(--ink3); padding:4px; border-radius:4px; }
.manage-team-btn:hover { background:var(--panel); color:var(--accent); }

/* Assigned venue row */
.assigned-venue-row { display:flex; align-items:center; justify-content:space-between; padding:7px 0; border-bottom:1px solid var(--border); }
.assigned-venue-row:last-child { border-bottom:none; }
.assigned-venue-info { display:flex; align-items:center; gap:8px; flex:1; }
.venue-purpose-badge { display:inline-block; padding:2px 7px; border-radius:10px; font-size:10px; font-weight:700; text-transform:uppercase; background:#EFF6FF; color:#1d4ed8; margin-right:6px; }

/* Empty state */
.empty-state { font-size:12px; color:var(--ink3); padding:10px 0; text-align:center; }

/* Spinner */
.spinner-sm { display:inline-block; width:11px; height:11px; border:2px solid currentColor; border-top-color:transparent; border-radius:50%; animation:spin .6s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
</style>
