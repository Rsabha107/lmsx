<template>
  <app-layout>
    <div class="mobile-container">
      <!-- Header -->
      <div class="mobile-header">
        <h1 class="mobile-title">My Jobs</h1>
        <div class="mobile-subtitle">{{ schedule.length }} active jobs</div>
        <div class="mobile-supervisor">Priya Anand · Supervisor</div>
      </div>

      <!-- Job Cards -->
      <div class="job-cards">
        <div
          v-for="job in schedule"
          :key="job.id"
          @click="selectJob(job)"
          class="job-card"
        >
          <div class="job-card-header">
            <div class="job-card-left">
              <div class="team-badge">{{ job.code }}</div>
              <div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <div class="job-card-team" style="margin-bottom: 0;">{{ job.team }}</div>
                  <span
                    v-if="job.kind"
                    class="mobile-kind-badge"
                    :class="`mobile-kind-badge--${job.kind}`"
                  >{{ job.kind }}</span>
                </div>
                <div class="job-card-id">
                  {{ job.jobId || job.id }}
                  <span v-if="job.functional_area" class="mobile-fa-badge">
                    {{ job.functional_area }}
                  </span>
                </div>
              </div>
            </div>
            <status-pill :tone="statusTone(job.status)" :dot="true" size="sm">
              {{ statusLabel(job.status) }}
            </status-pill>
          </div>

          <div class="job-card-route">
            <svg-icon name="plane" :size="14" style="color: var(--ink3);" />
            <span>{{ formatJobFromLocation(job) }}</span>
            <span style="color: var(--ink4); margin: 0 4px;">→</span>
            <span>{{ formatJobToLocation(job) }}</span>
          </div>

          <div class="job-card-details">
            <!-- <div v-if="job.window_start" class="detail-item detail-item--prominent">
              <svg-icon name="calendar" :size="14" />
              <span class="mono">{{ job.window_start }}</span>
            </div> -->
            <div class="detail-item">
              <svg-icon name="clock" :size="14" />
              <span class="mono">{{ job.dep }} – {{ job.arr }}</span>
            </div>
            <div class="detail-item">
              <svg-icon name="bus" :size="14" />
              <span>{{ job.vehicle }}</span>
            </div>
            <div class="detail-item">
              <svg-icon name="user" :size="14" />
              <span class="mono">{{ job.pax }} pax</span>
            </div>
          </div>

          <div v-if="job.next_checkpoint" class="job-card-next-checkpoint">
            <svg-icon name="check" :size="14" />
            <span class="next-checkpoint-label">Next:</span>
            <span class="next-checkpoint-name">{{ job.next_checkpoint.name }}</span>
            <span v-if="job.next_checkpoint.scheduled_at" class="next-checkpoint-time mono">
              {{ job.next_checkpoint.scheduled_at }}
            </span>
          </div>

          <div v-if="job.delay" class="job-card-alert">
            <svg-icon name="warn" :size="14" />
            <span>Delayed +{{ job.delay }}m</span>
          </div>
        </div>
      </div>

      <!-- Floating Action Button -->
      <!-- <button class="fab" @click="showNewJob = true">
        <svg-icon name="plus" :size="20" style="color: #fff;" />
      </button> -->
    </div>
  </app-layout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import StatusPill from '../Components/StatusPill.vue';
import SvgIcon from '../Components/SvgIcon.vue';

const props = defineProps({
  schedule: { type: Array, default: () => [] },
});

const showNewJob = ref(false);

function selectJob(job) {
  router.visit(`/jobs/mobile/${job.id}`);
}

const statusMap = {
  'in-progress': { tone: 'live', label: 'In Progress' },
  'live': { tone: 'live', label: 'In Progress' },
  'pending': { tone: 'primary', label: 'Scheduled' },
  'scheduled': { tone: 'primary', label: 'Scheduled' },
  'dispatched': { tone: 'primary', label: 'Dispatched' },
  'delayed': { tone: 'warn', label: 'Delayed' },
  'completed': { tone: 'ok', label: 'Done' },
  'done': { tone: 'ok', label: 'Done' },
  'cancelled': { tone: 'neutral', label: 'Cancelled' },
  'queued': { tone: 'neutral', label: 'Queued' },
  'issue': { tone: 'warn', label: 'Issue' },
};

function statusTone(s) {
  return statusMap[s]?.tone ?? 'neutral';
}

function statusLabel(s) {
  return statusMap[s]?.label ?? s;
}

function formatFunctionalArea(code) {
  const map = {
    'LOG': 'Logistics',
    'AND': 'Arrival & Departure',
    'MOB': 'Mobility'
  };
  return map[code] || code;
}

// Location formatting functions
function formatLocationWithAirport(location, airportCode) {
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

  const trainingTerms = ["training", "training ground", "practice"];
  const locationLower = location.toLowerCase();

  // Only augment if it's a training reference
  if (trainingTerms.some((term) => locationLower.includes(term))) {
    return `${location} (${trainingGroundName})`;
  }

  return location;
}

function formatJobFromLocation(job) {
  if (!job) return "—";
  
  let location = job.from || "—";
  const locationLower = location.toLowerCase();
  
  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    let airportCode = null;
    
    // For arrival movements, use destination airport
    if (job.kind === "arrival") {
      airportCode = job.flight?.destination_airport || 
                   job.team_data?.destination_airport;
    }
    // For departure movements, use origin airport
    else if (job.kind === "departure") {
      airportCode = job.flight?.origin_airport || 
                   job.team_data?.origin_airport;
    }
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = job.accommodation?.hotel_name || 
                     job.team_data?.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    const venueName = job.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  // 4. Training ground locations
  else if (locationLower.includes('training')) {
    const trainingGround = job.team_data?.training_ground;
    if (trainingGround) {
      location = formatLocationWithTrainingGround(location, trainingGround);
    }
  }
  
  return location;
}

function formatJobToLocation(job) {
  if (!job) return "—";
  
  let location = job.to || "—";
  const locationLower = location.toLowerCase();
  
  // Check location type and append appropriate data
  // 1. Airport locations
  if (locationLower.includes('airport')) {
    // For both arrival and departure, to_location typically uses destination airport
    const airportCode = job.flight?.destination_airport || 
                       job.team_data?.destination_airport;
    
    if (airportCode) {
      location = formatLocationWithAirport(location, airportCode);
    }
  }
  // 2. Hotel locations
  else if (locationLower.includes('hotel')) {
    const hotelName = job.accommodation?.hotel_name || 
                     job.team_data?.hotel_name;
    if (hotelName) {
      location = formatLocationWithHotel(location, hotelName);
    }
  }
  // 3. Stadium/Venue locations
  else if (locationLower.includes('stadium') || locationLower.includes('venue') || 
           locationLower.includes('ground') || locationLower.includes('arena')) {
    const venueName = job.match?.venue?.name;
    if (venueName) {
      location = formatLocationWithVenue(location, venueName);
    }
  }
  // 4. Training ground locations
  else if (locationLower.includes('training')) {
    const trainingGround = job.team_data?.training_ground;
    if (trainingGround) {
      location = formatLocationWithTrainingGround(location, trainingGround);
    }
  }
  
  return location;
}

</script>

<style scoped>
.mobile-container {
  max-width: 480px;
  margin: 0 auto;
  padding: 16px;
  padding-bottom: 80px;
}

.mobile-header {
  margin-bottom: 20px;
}

.mobile-title {
  font-size: 20px;
  font-weight: 700;
  color: var(--ink);
  margin: 0 0 4px;
  letter-spacing: -0.4px;
}

.mobile-subtitle {
  font-size: 13px;
  color: var(--ink3);
  margin-bottom: 6px;
}

.mobile-supervisor {
  font-size: 12px;
  color: var(--ink4);
}

/* Job Cards */
.job-cards {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.job-card {
  background: var(--surface);
  border: 2px solid var(--border);
  border-radius: 12px;
  padding: 16px;
  cursor: pointer;
  transition: all 0.2s;
}

.job-card:active {
  transform: scale(0.98);
}

.job-card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 12px;
  gap: 12px;
}

.job-card-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
  min-width: 0;
}

.team-badge {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: var(--accent-soft);
  color: var(--accent-fg);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.job-card-team {
  font-size: 15px;
  font-weight: 600;
  color: var(--ink);
  margin-bottom: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.job-card-id {
  font-size: 11px;
  font-family: var(--mono);
  color: var(--ink3);
  display: flex;
  align-items: center;
  gap: 6px;
}

.mobile-fa-badge {
  font-size: 8px;
  font-weight: 700;
  padding: 2px 6px;
  border-radius: 4px;
  letter-spacing: 0.5px;
  font-family: var(--font-sans, sans-serif);
  background: var(--green-soft, #dcfce7);
  color: var(--green, #166534);
}

.mobile-kind-badge {
  font-size: 9px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 4px;
  text-transform: capitalize;
  letter-spacing: 0.3px;
  flex-shrink: 0;
}
.mobile-kind-badge--arrival { background: var(--ok-soft); color: var(--ok); }
.mobile-kind-badge--departure { background: var(--danger-soft); color: var(--danger); }
.mobile-kind-badge--transfer { background: var(--accent-soft); color: var(--accent-fg); }
.mobile-kind-badge--match { background: #fef3c7; color: #92400e; }
.mobile-kind-badge--training { background: #ede9fe; color: #6d28d9; }
.mobile-kind-badge--daily_ops { background: var(--panel); color: var(--ink3); }

.job-card-route {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  color: var(--ink2);
  margin-bottom: 12px;
  padding: 10px;
  background: var(--panel);
  border-radius: 8px;
}

.job-card-details {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 5px;
  font-size: 12px;
  color: var(--ink3);
}

.detail-item--prominent {
  color: var(--ink);
  font-weight: 600;
  padding: 6px 8px;
  background: var(--accent-soft);
  border-radius: 6px;
  flex-basis: 100%;
}

.job-card-next-checkpoint {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding: 8px 10px;
  background: var(--blue-soft, #dbeafe);
  color: var(--blue, #1e40af);
  border-radius: 8px;
  font-size: 12px;
}

.next-checkpoint-label {
  font-weight: 600;
  color: var(--blue-dark, #1e3a8a);
}

.next-checkpoint-name {
  flex: 1;
  font-weight: 500;
}

.next-checkpoint-time {
  font-weight: 600;
  color: var(--blue-dark, #1e3a8a);
}

.job-card-alert {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 12px;
  padding: 8px 10px;
  background: var(--warn-soft);
  color: var(--warn);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
}

/* Floating Action Button */
.fab {
  position: fixed;
  bottom: 24px;
  right: 24px;
  width: 56px;
  height: 56px;
  border-radius: 16px;
  background: var(--accent);
  color: #fff;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.fab:active {
  transform: scale(0.95);
}

.mono {
  font-family: var(--mono);
}
</style>
