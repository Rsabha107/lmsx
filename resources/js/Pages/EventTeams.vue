<template>
  <app-layout>
    <NoActiveEvent v-if="!activeEvent" what="event teams" />

    <!-- Active Event Content -->
    <div v-else class="tl">
      <header class="tl-head">
        <div>
          <div class="tl-kicker">Delegations<template v-if="eventDay"> · day {{ eventDay.day }} of {{ eventDay.total }}</template></div>
          <h1 class="tl-title">Teams &amp; travel</h1>
        </div>
        <div class="tl-actions">
          <RefreshButton :only="['eventTeams', 'activeEvent', 'serverNow']" />
          <button type="button" class="tl-btn" :disabled="bulkRefreshing || !refreshableFlights(filteredTeams).length"
            :title="`Refresh the flight times of the ${filteredTeams.length} teams shown from OAG`" @click="refreshFlights(filteredTeams)">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" /></svg>
            {{ bulkRefreshing ? 'Refreshing…' : 'Refresh flights' }}
          </button>
          <button type="button" class="tl-btn" @click="openImportModal">
            <svg-icon name="upload" :size="14" /> Import
          </button>
          <button type="button" class="tl-btn tl-btn--dark" @click="openTeamDialog(null)">
            <svg-icon name="plus" :size="14" style="color: #fff;" /> Add team
          </button>
        </div>
      </header>

      <!-- Key figures -->
      <section class="tl-kpis" aria-label="Key figures">
        <div class="tl-kpi"><span class="tl-kpi-label">Teams</span><span class="tl-kpi-value">{{ eventTeams.length }}<small v-if="groups.length">{{ groups.length }} group{{ groups.length === 1 ? '' : 's' }}</small></span></div>
        <div class="tl-kpi"><span class="tl-kpi-label">On site now</span><span class="tl-kpi-value">{{ kpis.onSite }}<small>of {{ eventTeams.length }}</small></span></div>
        <div class="tl-kpi"><span class="tl-kpi-label">Departed</span><span class="tl-kpi-value">{{ kpis.departed }}</span></div>
        <div class="tl-kpi"><span class="tl-kpi-label">Flights booked</span><span class="tl-kpi-value">{{ kpis.booked }}<small>of {{ kpis.needed }}</small></span></div>
        <div class="tl-kpi"><span class="tl-kpi-label">Return flights TBC</span><span :class="['tl-kpi-value', { 'tl-kpi-value--warn': kpis.returnTbc }]">{{ kpis.returnTbc }}<small>need action</small></span></div>
      </section>

      <!-- Filters -->
      <div class="tl-filters">
        <label class="tl-search">
          <svg-icon name="search" :size="14" />
          <input v-model="searchQuery" type="search" placeholder="Search team, country or flight" aria-label="Search teams" />
        </label>
        <div v-if="groups.length > 1" class="tl-seg" role="group" aria-label="Group">
          <button v-for="g in ['', ...groups]" :key="g || 'all'" type="button" :class="['tl-seg-btn', { 'tl-seg-btn--on': groupFilter === g }]" @click="groupFilter = g">{{ g ? `Group ${g}` : 'All' }}</button>
        </div>
        <div class="tl-seg" role="group" aria-label="Status">
          <button v-for="s in STATUS_FILTERS" :key="s.value" type="button" :class="['tl-seg-btn', { 'tl-seg-btn--on': statusFilter === s.value }]" @click="statusFilter = s.value">{{ s.label }}</button>
        </div>
        <span class="tl-count">{{ filteredTeams.length }} of {{ eventTeams.length }} teams</span>
      </div>

      <!-- Selection: refresh the ticked teams' flights -->
      <div v-if="selectedTeams.length" class="tl-selbar">
        <strong>{{ selectedTeams.length }} team{{ selectedTeams.length === 1 ? '' : 's' }} selected</strong>
        <span>{{ refreshableFlights(selectedTeams).length }} flight{{ refreshableFlights(selectedTeams).length === 1 ? '' : 's' }} to refresh</span>
        <button type="button" class="tl-btn tl-btn--dark" :disabled="bulkRefreshing || !refreshableFlights(selectedTeams).length" @click="refreshFlights(selectedTeams)">
          {{ bulkRefreshing ? 'Refreshing…' : 'Refresh flights' }}
        </button>
        <button type="button" class="tl-link-btn" @click="selectedIds = []">Clear</button>
      </div>

      <!-- Teams -->
      <div class="tl-card">
        <div class="tl-scroll">
          <table class="tl-table">
            <thead>
              <tr>
                <th class="tl-col-check">
                  <input type="checkbox" :checked="allShownSelected" :indeterminate.prop="someShownSelected" aria-label="Select all teams shown" @change="toggleAllShown($event.target.checked)" />
                </th>
                <th>Team</th>
                <th>Group</th>
                <th class="tl-col-stay">
                  <div class="tl-axis-head">
                    <span>Stay</span>
                    <div v-if="axis" class="tl-axis">
                      <span v-for="tick in axis.ticks" :key="tick.label" class="tl-axis-tick" :style="{ left: `${tick.pct}%` }">{{ tick.label }}</span>
                      <span v-if="axis.todayPct !== null" class="tl-axis-today" :style="{ left: `${axis.todayPct}%` }">Today</span>
                    </div>
                  </div>
                </th>
                <th>Arrival</th>
                <th>Departure</th>
                <th>Hotel</th>
                <th class="tl-col-actions"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="et in filteredTeams" :key="et.id" :class="['tl-row', { 'tl-row--departed': rows[et.id].state === 'departed', 'tl-row--selected': selectedIds.includes(et.id) }]" @click="onRowClick(et, $event)">
                <td class="tl-col-check">
                  <input type="checkbox" :checked="selectedIds.includes(et.id)" :aria-label="`Select ${et.team_name}`" @change="toggleSelected(et.id)" />
                </td>
                <td>
                  <div class="tl-team">
                    <flag-icon :code="et.country_id" :fallback="et.country?.flag" />
                    <div>
                      <div class="tl-team-name">{{ et.team_name }}</div>
                      <div class="tl-team-code">{{ et.code }}<span v-if="et.classification" class="tl-team-class">{{ et.classification.name }}</span></div>
                    </div>
                  </div>
                </td>
                <td><span v-if="et.group_pool" :class="['tl-group', `tl-group--${groupTone(et.group_pool)}`]">{{ et.group_pool }}</span><span v-else class="tl-muted">—</span></td>
                <td class="tl-col-stay">
                  <div class="tl-stay-top">
                    <span :class="['tl-stay-state', `tl-stay-state--${rows[et.id].tone}`]"><i />{{ rows[et.id].stateText }}</span>
                    <span v-if="rows[et.id].nights !== null" class="tl-stay-nights">{{ rows[et.id].nights }} night{{ rows[et.id].nights === 1 ? '' : 's' }}</span>
                  </div>
                  <div :class="['tl-bar', { 'tl-bar--local': rows[et.id].state === 'local' }]">
                    <template v-if="rows[et.id].bar">
                      <span :class="['tl-bar-done', `tl-bar-done--${rows[et.id].tone}`]" :style="{ left: `${rows[et.id].bar.from}%`, width: `${rows[et.id].bar.done}%` }" />
                      <span :class="['tl-bar-left', `tl-bar-left--${rows[et.id].tone}`, { 'tl-bar-left--open': rows[et.id].bar.open }]" :style="{ left: `${rows[et.id].bar.from + rows[et.id].bar.done}%`, width: `${rows[et.id].bar.left}%` }" />
                    </template>
                    <span v-if="axis?.todayPct !== null && axis" class="tl-bar-today" :style="{ left: `${axis.todayPct}%` }" />
                  </div>
                </td>
                <td v-for="dir in ['arrival', 'departure']" :key="dir" class="tl-col-leg">
                  <template v-for="leg in [rows[et.id][dir]]" :key="dir">
                    <div v-if="!leg" class="tl-leg">
                      <span :class="rows[et.id].state === 'local' ? 'tl-muted' : 'tl-not-booked'">{{ rows[et.id].state === 'local' ? '—' : 'Not booked' }}</span>
                      <span class="tl-leg-sub">{{ rows[et.id].state === 'local' ? 'Host nation' : '' }}</span>
                      <button v-if="rows[et.id].state !== 'local'" type="button" class="tl-link-btn" @click="openTeamDialog(et)">+ Add {{ dir }}</button>
                    </div>
                    <div v-else class="tl-leg">
                      <button type="button" class="tl-leg-main" :title="`Edit ${et.team_name}'s ${dir}`" @click="openTeamDialog(et)">
                        <strong>{{ leg.dateText }}</strong> <span class="tl-leg-time">{{ leg.flightNumber ? leg.time : `~${leg.time}` }}</span>
                      </button>
                      <span v-if="leg.isBus" class="tl-leg-sub">By road<template v-if="leg.route"> · {{ leg.route }}</template></span>
                      <span v-else-if="!leg.flightNumber" class="tl-leg-sub tl-leg-sub--bad">Flight TBC</span>
                      <span v-else class="tl-leg-sub">{{ leg.flightNumber }}<template v-if="leg.route"> · {{ leg.route }}</template></span>
                      <span v-if="leg.chip" class="tl-leg-chips">
                        <span :class="['tl-chip', `tl-chip--${leg.chip.tone}`]">{{ leg.chip.text }}</span>
                        <span v-if="leg.variance" :class="['tl-variance', `tl-variance--${leg.variance.tone}`]">{{ leg.variance.text }}</span>
                      </span>
                      <span v-if="leg.refresh" :class="['tl-refresh', `tl-refresh--${leg.refresh.tone}`]" :title="leg.refresh.title">
                        <button type="button" class="tl-refresh-btn" :disabled="refreshingIds.includes(leg.flight.id)" :aria-label="`Refresh ${leg.flightNumber}`" @click="refreshFlights([], [leg.flight.id])">
                          <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true" :class="{ 'tl-spin': refreshingIds.includes(leg.flight.id) }"><path d="M21 12a9 9 0 1 1-2.64-6.36M21 3v6h-6" /></svg>
                        </button>
                        {{ refreshingIds.includes(leg.flight.id) ? 'Refreshing…' : leg.refresh.text }}
                      </span>
                      <button v-if="canAskDaleel && leg.flightNumber && !leg.flight.actual_at && leg.flight.flight_status !== 'cancelled'" type="button" class="tl-link-btn tl-impact"
                        :aria-label="`What if ${leg.flightNumber} is late?`" @click="openImpact(et, leg, dir)">What if late?</button>
                    </div>
                  </template>
                </td>
                <td class="tl-col-hotel">
                  <div v-if="et.stay" class="tl-hotel">{{ et.stay.hotel_name }}</div>
                  <div v-else class="tl-muted">No hotel yet</div>
                  <div :class="['tl-training', { 'tl-muted': !rows[et.id].training }]">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                    {{ rows[et.id].training || 'No training scheduled' }}
                  </div>
                </td>
                <td class="tl-col-actions">
                  <div class="tl-row-actions">
                    <Link :href="`/plans?team=${et.id}`" class="tl-icon-btn" :title="`${et.team_name}'s movements in Planning`" :aria-label="`${et.team_name}'s movements in Planning`">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l3-3a5 5 0 0 0-7.07-7.07l-1 1" /><path d="M14 11a5 5 0 0 0-7.07 0l-3 3a5 5 0 0 0 7.07 7.07l1-1" /></svg>
                    </Link>
                    <button type="button" class="tl-icon-btn" :title="`Edit ${et.team_name}: team, flights, hotel, training`" :aria-label="`Edit ${et.team_name}`" @click="openTeamDialog(et)">
                      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M11.5 1.5L14.5 4.5L5 14H2V11L11.5 1.5Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                    <button type="button" class="tl-icon-btn tl-icon-btn--danger" :title="`Delete ${et.team_name}`" :aria-label="`Delete ${et.team_name}`" @click="deleteTeam(et)">
                      <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 4H14M6 4V2H10V4M12 4V14H4V4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="filteredTeams.length === 0">
                <td colspan="8" class="tl-empty">No teams match.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- What if a flight is late: asks Daleel (nothing is changed) -->
    <Modal :show="!!impact" max-width="440px" @close="impact = null">
      <template #title>What if {{ impact?.flightNumber }} is late?</template>
      <div v-if="impact" class="tl-impact-form">
        <p>{{ impact.team }} · {{ impact.dir }} {{ impact.when }}. Daleel moves this flight's movements by the delay, checks the rules again and lists what is newly at risk. Nothing is changed.</p>
        <label class="tl-impact-field">
          <span>Minutes late</span>
          <input v-model.number="impact.minutes" type="number" min="-180" max="1440" step="5" aria-label="Minutes late" />
        </label>
        <p v-if="impact.live" class="tl-impact-live">The flight source currently shows it {{ impact.live }} min late.</p>
      </div>
      <template #footer>
        <Button variant="secondary" size="sm" @click="impact = null">Cancel</Button>
        <Button variant="primary" size="sm" :disabled="!impact?.minutes" @click="askImpact">Ask Daleel</Button>
      </template>
    </Modal>

    <!-- Refresh result: counts, what the new times did to plans and jobs, then each flight's outcome -->
    <FlightRefreshResult :result="bulkResult" @close="bulkResult = null" />

    <!-- Add / Edit team (Teams Logistics design): team, arrival, departure, hotel and trainings in one save -->
    <TeamDialog
      v-if="teamDialog"
      :team="teamDialog.team"
      :event-id="activeEvent.id"
      :airports="airports"
      :countries="countries"
      :classifications="classifications"
      :base-camp-hotels="baseCampHotels"
      :group-options="groupOptions"
      @close="teamDialog = null"
      @saved="onTeamSaved"
      @delete="deleteTeam"
    />

    <!-- After a flight edit: the planned movements that followed it, and the ones with jobs to review -->
    <Modal :show="!!flightChangeResult" @close="flightChangeResult = null" max-width="620px">
      <template #title>Plans updated from the flight change</template>
      <p style="margin: 0 0 12px; font-size: 13px; color: var(--ink2);">
        What the new flight time changed. Jobs not started yet were re-timed and their supervisors told;
        jobs already under way kept their times.
      </p>
      <FlightChangesList v-if="flightChangeResult" :changes="[flightChangeResult]" />
      <template #footer>
        <div style="display: flex; justify-content: flex-end;">
          <Button variant="primary" size="sm" @click="flightChangeResult = null">Done</Button>
        </div>
      </template>
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
    <Modal :show="showImportResultsModal" :closeable="false" max-width="640px">
      <template #title>Import Results</template>
      <div style="display: flex; flex-direction: column; gap: 14px;">
        <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px;">
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
          <div :style="{ padding: '10px', background: importNeedsReview ? '#FEF3C7' : 'var(--panel)', borderRadius: '6px', textAlign: 'center' }">
            <div :style="{ fontSize: '18px', fontWeight: 700, color: importNeedsReview ? '#92400E' : 'var(--ink)' }">{{ importNeedsReview }}</div>
            <div :style="{ fontSize: '10px', fontWeight: 700, color: importNeedsReview ? '#92400E' : 'var(--ink3)', textTransform: 'uppercase', letterSpacing: '0.5px' }">Needs Review</div>
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

        <FlightChangesList :changes="importResult?.flight_changes ?? []" />

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
import { ref, computed } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { useDaleel } from "@/Composables/useDaleel";
import { useToast } from "@/Composables/useToast";
import AppLayout from "@/Components/AppLayout.vue";
import NoActiveEvent from "@/Components/NoActiveEvent.vue";
import Modal from "@/Components/Modal.vue";
import Button from "@/Components/Button.vue";
import RefreshButton from "@/Components/RefreshButton.vue";
import SvgIcon from "@/Components/SvgIcon.vue";
import ConfirmModal from "@/Components/ConfirmModal.vue";
import FlagIcon from "@/Components/FlagIcon.vue";
import FlightChangesList from "@/Components/FlightChangesList.vue";
import FlightRefreshResult from "@/Components/FlightRefreshResult.vue";
import TeamDialog from "@/Components/Teams/TeamDialog.vue";
import { flightFreshness } from "@/Composables/flightFreshness";
import { useFlightRefresh } from "@/Composables/useFlightRefresh";

const props = defineProps({
  activeEvent: { type: Object, default: null },
  eventTeams: { type: Array, default: () => [] },
  airports: { type: Array, default: () => [] },
  countries: { type: Array, default: () => [] },
  classifications: { type: Array, default: () => [] },
  baseCampHotels: { type: Array, default: () => [] },
  flightLimits: { type: Object, default: () => ({}) },
  serverNow: { type: String, default: null },
});

const { success: showSuccessToast, error: showErrorToast } = useToast();

const searchQuery = ref("");
const groupFilter = ref("");
const statusFilter = ref("all");
const STATUS_FILTERS = [
  { value: "all", label: "All" },
  { value: "onsite", label: "On site" },
  { value: "leaving", label: "Leaving soon" },
  { value: "departed", label: "Departed" },
];
// A departure within this many days counts as leaving soon.
const LEAVING_SOON_DAYS = 3;

// Wall-clock "YYYY-MM-DD HH:mm" values (no timezone) as minutes, so they compare with each other and serverNow.
function wallMinutes(value) {
  const m = String(value ?? "").match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
  if (!m) return null;
  return Date.UTC(+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0)) / 60000;
}
const DAY_MIN = 1440;
const nowMin = computed(() => wallMinutes(props.serverNow) ?? Math.floor(Date.now() / 60000));
const todayMin = computed(() => Math.floor(nowMin.value / DAY_MIN) * DAY_MIN);
const dayStart = (min) => Math.floor(min / DAY_MIN) * DAY_MIN;
const shortDate = (min) => {
  const d = new Date(min * 60000);
  return `${WEEKDAYS[d.getUTCDay()]} ${String(d.getUTCDate()).padStart(2, "0")} ${MONTHS[d.getUTCMonth()]}`;
};
const dayMonth = (min) => {
  const d = new Date(min * 60000);
  return `${d.getUTCDate()} ${MONTHS[d.getUTCMonth()]}`;
};
const hhmm = (min) => {
  const d = new Date(min * 60000);
  return `${String(d.getUTCHours()).padStart(2, "0")}:${String(d.getUTCMinutes()).padStart(2, "0")}`;
};

// Group letter of a pool like "A1"; the group pills and badge colours use it.
const groupOf = (pool) => String(pool ?? "").trim().replace(/\d+$/, "").toUpperCase() || String(pool ?? "").trim().toUpperCase();
const groups = computed(() => [...new Set(props.eventTeams.map((et) => groupOf(et.group_pool)).filter(Boolean))].sort());
const groupTone = (pool) => ["a", "b", "c", "d"][Math.max(0, groups.value.indexOf(groupOf(pool))) % 4];

// The stay timeline runs over the event's dates (else the first arrival to the last departure).
const axis = computed(() => {
  const legs = props.eventTeams.flatMap((et) => et.flights ?? []).map((f) => wallMinutes(f.scheduled_at)).filter((v) => v !== null);
  const start = wallMinutes(props.activeEvent?.start_date) ?? (legs.length ? dayStart(Math.min(...legs)) : null);
  const endDay = wallMinutes(props.activeEvent?.end_date) ?? (legs.length ? dayStart(Math.max(...legs)) : null);
  if (start === null || endDay === null) return null;
  const end = Math.max(endDay + DAY_MIN, start + DAY_MIN);
  const pct = (min) => Math.max(0, Math.min(100, ((min - start) / (end - start)) * 100));
  const mid = dayStart(start + (end - start) / 2);
  return {
    start, end, pct,
    ticks: [{ label: dayMonth(start), pct: 0 }, { label: dayMonth(mid), pct: pct(mid) }, { label: dayMonth(endDay), pct: 100 }],
    todayPct: nowMin.value >= start && nowMin.value <= end ? pct(nowMin.value) : null,
  };
});

const eventDay = computed(() => {
  if (!axis.value) return null;
  const total = Math.round((axis.value.end - axis.value.start) / DAY_MIN);
  const day = Math.floor((todayMin.value - axis.value.start) / DAY_MIN) + 1;
  return day >= 1 && day <= total ? { day, total } : null;
});

// Refresh state of one flight, judged with the data-check limits (Settings → Conflict rules).
function refreshState(f) {
  return flightFreshness({
    flight_number: f.flight_number,
    scheduled: f.scheduled_at,
    synced: f.flight_synced_at,
    attempted: f.flight_sync_attempted_at,
    error: f.flight_sync_error,
    done: ["landed", "cancelled"].includes(f.flight_status) || !!f.actual_at,
  }, nowMin.value, props.flightLimits ?? {});
}

function legView(f, dir) {
  if (!f) return null;
  const at = wallMinutes(f.scheduled_at);
  const actual = wallMinutes(f.actual_at);
  const estimated = wallMinutes(f.estimated_at);
  const origin = f.origin_airport?.code;
  const destination = f.destination_airport?.code;
  const variance = actual !== null && at !== null ? Math.round(actual - at) : null;
  const fmt = (n) => (Math.abs(n) >= 60 ? `${Math.floor(Math.abs(n) / 60)}h ${Math.abs(n) % 60}m` : `${Math.abs(n)}m`);

  let chip = null;
  if (f.flight_status === "cancelled") chip = { tone: "bad", text: "Cancelled" };
  else if (actual !== null) chip = { tone: "ok", text: `${dir === "arrival" ? "Landed" : "Departed"} ${hhmm(actual)}` };
  else if (f.flight_status === "active") chip = { tone: "live", text: estimated !== null ? `In the air · ETA ${hhmm(estimated)}` : "In the air" };
  else if (f.flight_number && f.flight_number !== "BUS") chip = { tone: "plan", text: estimated !== null && at !== null && estimated - at >= 15 ? `Delayed · ${hhmm(estimated)}` : "Scheduled" };

  return {
    flight: f,
    at,
    dateText: at !== null ? shortDate(at) : "Date TBC",
    time: at !== null ? hhmm(at) : "--:--",
    flightNumber: f.flight_number && f.flight_number !== "BUS" ? f.flight_number : null,
    isBus: f.flight_number === "BUS",
    route: origin || destination ? `${origin || "?"} → ${destination || "?"}` : null,
    chip,
    variance: variance === null ? null : Math.abs(variance) < 1 ? { tone: "ok", text: "On time" }
      : variance < 0 ? { tone: "ok", text: `${fmt(variance)} early` } : { tone: variance >= 15 ? "bad" : "warn", text: `${fmt(variance)} late` },
    refresh: refreshState(f),
  };
}

// Everything a row shows, worked out once per team.
const rows = computed(() => {
  const out = {};
  for (const et of props.eventTeams) {
    const flights = et.flights ?? [];
    const arrival = legView(flights.find((f) => f.direction === "arrival"), "arrival");
    const departure = legView(flights.find((f) => f.direction === "departure"), "departure");
    const arrDay = arrival?.at != null ? dayStart(arrival.at) : null;
    const depDay = departure?.at != null ? dayStart(departure.at) : null;
    const days = (d) => Math.round((d - todayMin.value) / DAY_MIN);
    const host = et.role === "host";

    let state; let tone; let stateText;
    if (!arrival && !departure) {
      [state, tone, stateText] = host ? ["local", "idle", "Local · no travel"] : ["unbooked", "idle", "Travel not booked"];
    } else if (departure && (departure.flight.actual_at || (depDay !== null && depDay < todayMin.value))) {
      [state, tone, stateText] = ["departed", "done", "Departed"];
    } else if (arrDay !== null && arrDay > todayMin.value) {
      const n = days(arrDay);
      [state, tone, stateText] = ["arriving", "info", n === 1 ? "Arrives tomorrow" : `Arrives in ${n} days`];
    } else if (depDay !== null && days(depDay) <= LEAVING_SOON_DAYS) {
      const n = days(depDay);
      [state, tone, stateText] = ["leaving", "soon", n === 0 ? "Leaves today" : n === 1 ? "Leaves tomorrow" : `Leaves in ${n} days`];
    } else if (arrDay !== null && arrDay === todayMin.value && !arrival.flight.actual_at) {
      [state, tone, stateText] = ["arriving", "info", "Arrives today"];
    } else {
      [state, tone, stateText] = ["onsite", "live", arrDay !== null ? `On site · day ${Math.round((todayMin.value - arrDay) / DAY_MIN) + 1}` : "On site"];
    }

    const nights = arrDay !== null && depDay !== null ? Math.max(0, Math.round((depDay - arrDay) / DAY_MIN)) : null;
    let bar = null;
    if (axis.value && arrival?.at != null) {
      const from = axis.value.pct(arrival.at);
      const to = axis.value.pct(departure?.at ?? axis.value.end);
      const split = Math.min(Math.max(axis.value.pct(nowMin.value), from), to);
      bar = { from, done: split - from, left: to - split, open: !departure?.at };
    }

    // Next session from today on (trainings come ordered by start).
    const upcoming = (et.trainings ?? [])
      .map((s) => ({ s, at: wallMinutes(s.training_start_at) }))
      .filter((x) => x.at !== null && x.at >= todayMin.value);
    const next = upcoming[0];
    const training = next
      ? `${shortDate(next.at)} ${hhmm(next.at)}${next.s.training_ground ? ` · ${next.s.training_ground}` : ""}${upcoming.length > 1 ? ` · +${upcoming.length - 1} more` : ""}`
      : null;

    out[et.id] = { arrival, departure, state, tone, stateText, nights, bar, training, host };
  }
  return out;
});

const kpis = computed(() => {
  const list = Object.values(rows.value);
  const travelling = list.filter((r) => r.state !== "local");
  return {
    onSite: list.filter((r) => ["onsite", "leaving", "local"].includes(r.state)).length,
    departed: list.filter((r) => r.state === "departed").length,
    booked: travelling.reduce((n, r) => n + [r.arrival, r.departure].filter((l) => l && (l.flightNumber || l.isBus)).length, 0),
    needed: travelling.length * 2,
    returnTbc: travelling.filter((r) => r.state !== "departed" && !(r.departure && (r.departure.flightNumber || r.departure.isBus))).length,
  };
});

const filteredTeams = computed(() => {
  const q = searchQuery.value.trim().toLowerCase();
  return props.eventTeams.filter((et) => {
    const r = rows.value[et.id];
    if (groupFilter.value && groupOf(et.group_pool) !== groupFilter.value) return false;
    if (statusFilter.value === "onsite" && !["onsite", "leaving", "local"].includes(r.state)) return false;
    if (statusFilter.value === "leaving" && r.state !== "leaving") return false;
    if (statusFilter.value === "departed" && r.state !== "departed") return false;
    if (!q) return true;
    const flights = (et.flights ?? []).map((f) => f.flight_number ?? "").join(" ");
    return [et.team_name, et.code, et.group_pool, et.country?.country_name, flights].some((v) => String(v ?? "").toLowerCase().includes(q));
  });
});

// ── What if a flight is late (Daleel's impact analysis) ────────────────────
const page = usePage();
const canAskDaleel = computed(() => page.props.auth?.can?.["ai.use"] === true);
const { askAbout } = useDaleel();
const impact = ref(null);
function openImpact(et, leg, dir) {
  const f = leg.flight;
  const sched = wallMinutes(f.scheduled_at);
  const est = wallMinutes(f.estimated_at);
  const live = sched !== null && est !== null && est - sched > 0 ? Math.round(est - sched) : (f.delay_minutes > 0 ? f.delay_minutes : null);
  impact.value = { flightId: f.id, flightNumber: leg.flightNumber, team: et.team_name, dir, when: `${leg.dateText} ${leg.time}`, live, minutes: live || 30 };
}
function askImpact() {
  const i = impact.value;
  askAbout(`${i.flightNumber} (${i.team}, ${i.dir} ${i.when}) is ${i.minutes} min late: what is now at risk?`, { page: "event-teams", flight_id: i.flightId });
  impact.value = null;
}

// ── Selection and flight refresh (OAG, then AviationStack) ─────────────────
const selectedIds = ref([]);
const selectedTeams = computed(() => props.eventTeams.filter((et) => selectedIds.value.includes(et.id)));
const allShownSelected = computed(() => filteredTeams.value.length > 0 && filteredTeams.value.every((et) => selectedIds.value.includes(et.id)));
const someShownSelected = computed(() => !allShownSelected.value && filteredTeams.value.some((et) => selectedIds.value.includes(et.id)));
function toggleSelected(id) {
  selectedIds.value = selectedIds.value.includes(id) ? selectedIds.value.filter((x) => x !== id) : [...selectedIds.value, id];
}
function toggleAllShown(on) {
  const shown = filteredTeams.value.map((et) => et.id);
  selectedIds.value = on ? [...new Set([...selectedIds.value, ...shown])] : selectedIds.value.filter((id) => !shown.includes(id));
}

// Flights worth refreshing: a real flight number, not landed or cancelled.
function refreshableFlights(teams) {
  return teams.flatMap((et) => et.flights ?? [])
    .filter((f) => f.flight_number && f.flight_number !== "BUS" && !["landed", "cancelled"].includes(f.flight_status));
}

// Refresh through the shared composable; a new time can re-time plans and jobs, so the result lists what changed.
const { refresh: runRefresh, refreshing: bulkRefreshing, refreshingIds, result: bulkResult } = useFlightRefresh({ reloadOnly: ["eventTeams", "serverNow"] });

async function refreshFlights(teams, onlyIds = null) {
  const ids = (onlyIds ?? refreshableFlights(teams).map((f) => f.id)).slice(0, 60);
  if (!ids.length || !props.activeEvent) return;
  const single = !!onlyIds && onlyIds.length === 1;
  const done = await runRefresh(ids.map((id) => ({ id, event_id: props.activeEvent.id })), { single });
  if (done && !single) selectedIds.value = [];
}

const MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
const WEEKDAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
// What the last team save did to the plans built on its flights (flash flight_change).
const flightChangeResult = ref(null);

// ── Add / Edit team dialog ─────────────────────────────────────────────────
// { team } while open; team is null for a new one.
const teamDialog = ref(null);
const groupOptions = computed(() =>
  [...new Set(props.eventTeams.map((t) => t.group_pool).filter(Boolean))].sort()
);

function openTeamDialog(team) {
  teamDialog.value = { team };
}

// A click anywhere on a row edits the team; the row's own controls (and the checkbox cell) keep their action.
function onRowClick(team, event) {
  if (event.target.closest("button, a, input, select, label, .tl-col-check")) return;
  if (window.getSelection()?.toString()) return; // selecting text to copy isn't a click
  openTeamDialog(team);
}

function onTeamSaved(page) {
  teamDialog.value = null;
  flightChangeResult.value = page?.props?.flash?.flight_change ?? null;
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

  if (item.type === "team") {
    router.delete(`/events/${props.activeEvent.id}/teams/${item.code}`, {
      onSuccess: (page) => {
        if (!page.props.flash?.error) teamDialog.value = null;
        closeDeleteModal();
      },
      onError: () => {
        deleting.value = false;
      },
    });
  }
}

// ── Import Teams ─────────────────────────────────────────────────────────
const showImportModal = ref(false);
const importFile = ref(null);
const importing = ref(false);
const importError = ref("");
const showImportResultsModal = ref(false);
const importResult = ref(null);
const importNeedsReview = computed(() =>
  (importResult.value?.flight_changes ?? [])
    .flatMap((change) => change.movements)
    .filter((m) => m.status === "needs_review").length
);

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

.team-cell { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; }
.team-cell .team-badge-sm { font-size: 13px; padding: 3px 9px; border-radius: 5px; }

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

/* Flights column: one trip card per team */
.flights-col { min-width: 330px; }

.trip-status {
  display: inline-flex; align-items: center; gap: 5px;
  font-size: 10.5px; font-weight: 600; margin-bottom: 5px;
  color: var(--ink3);
}
.trip-status-dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.trip-status--soon { color: #1d4ed8; }
.trip-status--live { color: #15803d; }
.trip-status--live .trip-status-dot { box-shadow: 0 0 0 3px rgba(21, 128, 61, .18); animation: trip-pulse 1.8s ease-in-out infinite; }
.trip-status--done { color: var(--ink4, #9ca3af); }
.trip-status--idle { color: #b45309; }
@keyframes trip-pulse { 50% { box-shadow: 0 0 0 5px rgba(21, 128, 61, 0); } }

.trip {
  display: grid; grid-template-columns: 1fr 56px 1fr; align-items: stretch;
}

.trip-leg {
  position: relative;
  display: block; width: 100%; text-align: left; font: inherit; color: inherit; cursor: pointer;
  padding: 7px 9px 7px 11px; border-radius: 8px;
  background: var(--panel); border: 1px solid var(--border);
  min-width: 0;
  transition: border-color .15s, box-shadow .15s, transform .15s;
}
.trip-leg:hover { transform: translateY(-1px); box-shadow: 0 3px 10px rgba(0, 0, 0, .08); }
.trip-leg--arr:hover { border-color: #93c5fd; }
.trip-leg--dep:hover { border-color: #fcd34d; }
.trip-leg:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
.trip-add { display: none; font-style: normal; font-weight: 700; color: var(--accent); margin-left: 4px; }
.trip-leg--empty:hover .trip-add { display: inline; }
.trip-leg::before {
  content: ""; position: absolute; left: 0; top: 7px; bottom: 7px;
  width: 3px; border-radius: 0 3px 3px 0;
}
.trip-leg--arr::before { background: #2563eb; }
.trip-leg--dep::before { background: #d97706; }
.trip-leg--empty { background: transparent; border-style: dashed; }
.trip-leg--empty::before { background: var(--border); }

.trip-leg-head {
  display: flex; align-items: center; gap: 4px;
  font-size: 9.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
  color: var(--ink3); margin-bottom: 3px;
}
.trip-leg--arr .trip-leg-icon { color: #2563eb; }
.trip-leg--dep .trip-leg-icon { color: #d97706; }

.trip-chip {
  margin-left: auto; padding: 0 5px; border-radius: 999px;
  font-size: 9px; letter-spacing: 0; text-transform: none;
}
.trip-chip--late { background: #fef3c7; color: #92400e; }
.trip-chip--ok { background: #dcfce7; color: #166534; }

.trip-date { display: flex; align-items: center; gap: 6px; }
.trip-date strong {
  font-size: 20px; font-weight: 800; line-height: 1; color: var(--ink);
  font-variant-numeric: tabular-nums; letter-spacing: -.5px;
}
.trip-date span { font-size: 9.5px; line-height: 1.15; color: var(--ink3); font-weight: 600; text-transform: uppercase; }

.trip-meta { display: flex; align-items: baseline; gap: 6px; margin-top: 4px; }
.trip-flight {
  font-family: var(--mono); font-size: 11px; font-weight: 700; color: var(--ink);
  white-space: nowrap;
}
.trip-flight--bus { font-family: inherit; color: #6d28d9; }
.trip-flight--tbc { color: var(--ink4, #9ca3af); font-weight: 600; }
.trip-time { font-family: var(--mono); font-size: 11px; color: var(--ink2, var(--ink)); }

.trip-sub {
  margin-top: 2px; font-size: 10px; color: var(--ink3);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.trip-missing { font-size: 11px; color: var(--ink4, #9ca3af); font-style: italic; padding: 8px 0; }

.trip-span {
  display: flex; align-items: center; gap: 3px; padding: 0 4px;
}
.trip-span::before, .trip-span::after {
  content: ""; flex: 1; border-top: 1.5px dashed var(--border);
}
.trip-span-label {
  display: flex; flex-direction: column; align-items: center;
  font-size: 14px; font-weight: 800; line-height: 1; color: var(--ink);
}
.trip-span-label small { font-size: 8.5px; font-weight: 600; color: var(--ink3); text-transform: uppercase; letter-spacing: .04em; margin-top: 2px; }

.trip-extra { margin-top: 4px; font-size: 10px; color: var(--ink3); }

.mono {
  font-family: var(--mono);
}
.text-muted {
  color: var(--ink3);
}
.center {
  text-align: center;
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
/* ── Teams & travel (Teams Logistics design) ─────────────────────────── */
.tl { --tl-mono: var(--mono, var(--font-mono, ui-monospace, SFMono-Regular, Menlo, monospace)); display: flex; flex-direction: column; gap: 16px; }
.tl-head { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.tl-kicker { font-family: var(--tl-mono); font-size: 12px; letter-spacing: .08em; text-transform: uppercase; color: var(--accent); margin-bottom: 4px; }
.tl-title { margin: 0; font-size: 30px; font-weight: 700; letter-spacing: -.02em; color: var(--ink); line-height: 1.1; }
.tl-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.tl-btn {
  display: inline-flex; align-items: center; gap: 7px; height: 36px; padding: 0 14px; cursor: pointer;
  font-size: 13px; font-weight: 600; color: var(--ink); background: var(--surface);
  border: 1px solid var(--border); border-radius: 8px;
}
.tl-btn:hover:not(:disabled) { border-color: var(--border-strong, var(--ink3)); }
.tl-btn:disabled { opacity: .5; cursor: default; }
.tl-btn--dark { background: var(--ink); color: var(--surface); border-color: var(--ink); }
.tl-btn--dark:hover:not(:disabled) { opacity: .9; }
.tl-link-btn { border: none; background: none; padding: 0; cursor: pointer; font-size: 12px; font-weight: 600; color: var(--accent); }

.tl-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.tl-kpi { display: flex; flex-direction: column; gap: 6px; padding: 16px 20px; border-left: 1px solid var(--border); }
.tl-kpi:first-child { border-left: none; }
.tl-kpi-label { font-size: 12.5px; color: var(--ink2); }
.tl-kpi-value { font-size: 26px; font-weight: 700; color: var(--ink); line-height: 1; display: flex; align-items: baseline; gap: 8px; }
.tl-kpi-value small { font-family: var(--tl-mono); font-size: 12px; font-weight: 400; color: var(--ink3); }
.tl-kpi-value--warn { color: var(--warn); }
@media (max-width: 1000px) { .tl-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } .tl-kpi:nth-child(odd) { border-left: none; } }

.tl-filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.tl-search { display: inline-flex; align-items: center; gap: 8px; height: 38px; width: 280px; padding: 0 12px; background: var(--surface); border: 1px solid var(--border); border-radius: 8px; color: var(--ink3); }
.tl-search input { flex: 1; min-width: 0; height: 100%; padding: 0; border: none !important; box-shadow: none !important; background: none; font-size: 13px; color: var(--ink); }
.tl-search:focus-within { border-color: var(--accent); }
.tl-seg { display: inline-flex; padding: 3px; gap: 2px; background: var(--panel); border: 1px solid var(--border); border-radius: 9px; }
.tl-seg-btn { height: 30px; padding: 0 12px; border: none; border-radius: 7px; background: none; cursor: pointer; font-size: 13px; color: var(--ink2); }
.tl-seg-btn:hover { color: var(--ink); }
.tl-seg-btn--on { background: var(--surface); color: var(--ink); font-weight: 600; box-shadow: 0 1px 2px rgba(17, 24, 39, .08); }
.tl-count { margin-left: auto; font-family: var(--tl-mono); font-size: 12px; color: var(--ink3); }

.tl-selbar { display: flex; align-items: center; gap: 14px; padding: 10px 14px; border-radius: 10px; background: var(--accent-soft, var(--panel)); color: var(--ink); font-size: 13px; }
.tl-selbar span { color: var(--ink2); }
.tl-selbar .tl-btn { height: 32px; margin-left: auto; }

.tl-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; overflow: hidden; }
.tl-scroll { overflow-x: auto; }
.tl-table { width: 100%; border-collapse: collapse; min-width: 1180px; }
.tl-table th {
  text-align: left; vertical-align: bottom; padding: 12px 14px; white-space: nowrap;
  font-family: var(--tl-mono); font-size: 11px; font-weight: 500; letter-spacing: .06em; text-transform: uppercase; color: var(--ink3);
  background: color-mix(in srgb, var(--panel) 50%, var(--surface)); border-bottom: 1px solid var(--border);
}
.tl-table td { padding: 14px; vertical-align: top; border-bottom: 1px solid var(--border); font-size: 13px; color: var(--ink); }
.tl-table tbody tr:last-child td { border-bottom: none; }
.tl-row { cursor: pointer; }
.tl-row .tl-col-check { cursor: default; }
.tl-row:hover td { background: color-mix(in srgb, var(--panel) 40%, transparent); }
.tl-row--selected td { background: color-mix(in srgb, var(--accent-soft, var(--panel)) 60%, transparent); }
.tl-row--departed td > * { opacity: .55; }
.tl-row--departed .tl-col-actions > *, .tl-row--departed .tl-col-check > * { opacity: 1; }
.tl-col-check { width: 36px; padding-right: 0 !important; }
.tl-col-check input { margin-top: 3px; accent-color: var(--accent); }
.tl-col-stay { width: 260px; min-width: 240px; }
.tl-col-leg { min-width: 190px; }
.tl-col-hotel { min-width: 200px; max-width: 260px; }
.tl-col-actions { width: 100px; }
.tl-muted { color: var(--ink4); }
.tl-not-booked { font-size: 12.5px; font-weight: 600; color: var(--warn); }
.tl-empty { text-align: center; padding: 40px !important; color: var(--ink3) !important; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

.tl-team { display: flex; align-items: center; gap: 12px; }
/* Flags at the design's size (FlagIcon is sized in em). */
.tl-team :deep(.flag-icon), .tl-team :deep(.flag-icon-fallback) { font-size: 21px; }
.tl-team-name { font-size: 14px; font-weight: 700; color: var(--ink); }
.tl-team-code { font-family: var(--tl-mono); font-size: 11.5px; color: var(--accent); margin-top: 2px; }
.tl-team-class { margin-left: 6px; color: var(--ink3); }
.tl-group { display: inline-block; min-width: 40px; text-align: center; padding: 4px 8px; border-radius: 6px; font-family: var(--tl-mono); font-size: 12px; font-weight: 600; }
.tl-group--a { background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent); }
.tl-group--b { background: color-mix(in srgb, var(--live) 12%, transparent); color: var(--live); }
.tl-group--c { background: var(--ok-soft); color: var(--ok); }
.tl-group--d { background: var(--warn-soft); color: var(--warn); }

.tl-axis-head { display: flex; flex-direction: column; gap: 6px; }
.tl-axis { position: relative; height: 26px; }
.tl-axis-tick { position: absolute; bottom: 0; transform: translateX(-50%); font-size: 10.5px; letter-spacing: 0; text-transform: none; color: var(--ink3); }
.tl-axis-tick:first-child { transform: none; }
.tl-axis-tick:nth-child(3) { transform: translateX(-100%); }
.tl-axis-today { position: absolute; top: 0; transform: translateX(-50%); font-size: 10.5px; font-weight: 700; letter-spacing: 0; text-transform: none; color: var(--ink); }
.tl-stay-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 7px; }
.tl-stay-state { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; }
.tl-stay-state i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
.tl-stay-state--live { color: var(--ok); }
.tl-stay-state--soon { color: var(--warn); }
.tl-stay-state--info { color: var(--live); }
.tl-stay-state--done, .tl-stay-state--idle { color: var(--ink3); }
.tl-stay-nights { font-family: var(--tl-mono); font-size: 11.5px; color: var(--ink3); }
.tl-bar { position: relative; height: 6px; border-radius: 3px; background: var(--panel); }
.tl-bar--local { background: repeating-linear-gradient(135deg, var(--panel) 0 5px, color-mix(in srgb, var(--panel) 40%, var(--surface)) 5px 10px); }
.tl-bar-done, .tl-bar-left { position: absolute; top: 0; height: 6px; }
.tl-bar-done { border-radius: 3px 0 0 3px; }
.tl-bar-done--live { background: var(--ok); }
.tl-bar-done--soon { background: var(--warn); }
.tl-bar-done--done, .tl-bar-done--idle, .tl-bar-done--info { background: var(--ink4); }
.tl-bar-left { background: color-mix(in srgb, var(--ok) 25%, transparent); border-radius: 0 3px 3px 0; }
.tl-bar-left--soon { background: color-mix(in srgb, var(--warn) 25%, transparent); }
.tl-bar-left--info { background: color-mix(in srgb, var(--live) 25%, transparent); }
.tl-bar-left--done, .tl-bar-left--idle { background: color-mix(in srgb, var(--ink4) 35%, transparent); }
/* No departure booked yet: the stay runs on, open-ended. */
.tl-bar-left--open { background: repeating-linear-gradient(90deg, color-mix(in srgb, var(--ink4) 45%, transparent) 0 6px, transparent 6px 10px); }
.tl-bar-today { position: absolute; top: -4px; width: 2px; height: 14px; margin-left: -1px; background: var(--ink); border-radius: 1px; }

.tl-leg { display: flex; flex-direction: column; align-items: flex-start; gap: 3px; }
.tl-leg-main { border: none; background: none; padding: 0; cursor: pointer; text-align: left; font-size: 13.5px; color: var(--ink); }
.tl-leg-main:hover strong { text-decoration: underline; }
.tl-leg-time { font-family: var(--tl-mono); font-size: 12px; color: var(--ink3); }
.tl-leg-sub { font-family: var(--tl-mono); font-size: 11.5px; color: var(--ink2); }
.tl-leg-sub--bad { color: var(--danger); }
.tl-leg-chips { display: inline-flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.tl-chip { font-family: var(--tl-mono); font-size: 11px; padding: 2px 7px; border-radius: 5px; white-space: nowrap; }
.tl-chip--ok { background: var(--ok-soft); color: var(--ok); }
.tl-chip--live { background: var(--live-soft); color: var(--live); }
.tl-chip--plan { background: var(--warn-soft); color: var(--warn); }
.tl-chip--bad { background: var(--danger-soft); color: var(--danger); }
.tl-variance { font-family: var(--tl-mono); font-size: 11px; }
.tl-variance--ok { color: var(--ok); }
.tl-variance--warn { color: var(--warn); }
.tl-variance--bad { color: var(--danger); }
.tl-refresh { display: inline-flex; align-items: center; gap: 4px; margin-top: 2px; font-size: 11px; }
.tl-refresh--ok, .tl-refresh--idle { color: var(--ink3); }
.tl-refresh--warn { color: var(--warn); }
.tl-refresh--bad { color: var(--danger); font-weight: 600; }
.tl-refresh-btn { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; padding: 0; border: 1px solid var(--border); border-radius: 4px; background: var(--surface); color: inherit; cursor: pointer; }
.tl-refresh-btn:hover:not(:disabled) { border-color: currentColor; }
.tl-spin { animation: tl-spin 1s linear infinite; }
@keyframes tl-spin { to { transform: rotate(360deg); } }

.tl-hotel { font-size: 13px; color: var(--ink); line-height: 1.35; }
.tl-training { display: flex; gap: 6px; align-items: flex-start; margin-top: 5px; font-family: var(--tl-mono); font-size: 11px; color: var(--ink2); line-height: 1.4; }
.tl-training svg { flex-shrink: 0; margin-top: 2px; }
.tl-row-actions { display: flex; gap: 2px; justify-content: flex-end; }
.tl-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 30px; height: 30px; border: none; border-radius: 6px; background: none; color: var(--ink3); cursor: pointer; }
.tl-icon-btn:hover { background: var(--panel); color: var(--ink); }
.tl-icon-btn--danger:hover { color: var(--danger); }

.tl-bad { color: var(--danger); }
.tl-impact { font-size: 11px; margin-top: 2px; }
.tl-impact-form { display: flex; flex-direction: column; gap: 12px; font-size: 13px; color: var(--ink2); }
.tl-impact-form p { margin: 0; line-height: 1.5; }
.tl-impact-field { display: flex; align-items: center; gap: 10px; font-weight: 600; color: var(--ink); }
.tl-impact-field input { width: 110px; height: 34px; padding: 0 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink); font-size: 14px; }
.tl-impact-live { color: var(--warn); }</style>
