# Tournament board gap analysis and plan

## Problem

The external "Tournament Board Trackers" page (SC/LOC Events – PMA LOG Operations, Qatar 2026) gives
LOG an at-a-glance view the LMS doesn't: one cross-tournament **Today** board, per-tournament
**Arrivals / Departures** flight boards with a LOG service status per delegation, a **Tournament
Status** board (results, standings, qualified/eliminated, bracket, match grid) and a **Truck plan**
(trucks required vs available, 9 h shifts, pairing, uncovered jobs). It is kept up to date by hand from
365Scores/Sofascore/FlightAware and LOG WhatsApp updates.

Most of its data already exists in the LMS: flights with AviationStack sync, matches, movements linked to
their flight (`movements.flight_id`) and match (`movements.match_id`), jobs + checkpoints, crew, fleet pool.
What's missing is results/standings/bracket, a few status concepts, and the views. superrtq
(`C:\laragon\www\superrtq`, Laravel 13 + React/TS) already has results/standings/bracket logic on an almost
identical teams/matches schema.

Source: the board's Today, Arrivals, Departures, Tournament Status and Truck plan tabs as of 29 Oct 2026.

## Decisions (confirmed)

- Port superrtq's tournament logic into the LMS (Vue pages); results are entered in the LMS.
- Order: **Today board first**, then results/standings/bracket, flight boards, truck plan.
- TSV = the job's lead field supervisor (per movement).
- Scores: manual entry + Excel import; a live feed is a later optional phase.
- Qualification probability / early elimination: later optional phase.
- Today board: signed-in users only, same access rules as the Day Board (no public/TV link).
- **One page with tabs**, not a page per board (decided 31 Oct 2026): see "How it is built" below.

## How it is built

One page, **Tournament Board** (`/board`, sidebar item under Live Ops), with the external tracker's tabs.
Each phase adds its tab; tabs not built yet are not shown, so Phase 1 ships with only **Today**.

| Tab | URL | Phase | Data (service) |
|---|---|---|---|
| Today | `/board?tab=today&date=YYYY-MM-DD` | 1 | the day's movements, flights, matches, jobs (`TodayBoard`) |
| Arrivals | `/board?tab=arrivals` | 3 | the events' arrival flights + LOG service status (`FlightBoard`) |
| Departures | `/board?tab=departures` | 3 | the same for departures (`FlightBoard`) |
| Tournament Status | `/board?tab=status` | 2 | results, standings, bracket, match grid (`TournamentStatus`) |
| Truck plan | `/board?tab=trucks&date=YYYY-MM-DD` | 4 | vehicles, shifts, pairing (`TruckPlan`) |

- **Server**: one route and `TournamentBoardController`; each tab's data comes from its own service in
  `app/Services/Board/*` and is an `Inertia::optional` prop, sent only for the open tab (the pattern of the
  Duty Roster's Hotel schedule, `?tab=hotels`). Switching tab reloads `only: [that tab's prop]`.
- **Client**: `Pages/TournamentBoard.vue` is the shell (header, event picker, tab bar, day strip, refresh);
  each tab is its own component in `Components/Board/` (`TodayTab`, `FlightBoardTab`, `StatusTab`,
  `TruckPlanTab`), so the page doesn't grow into another `Plans.vue`. Shared helpers: `flightFreshness.js`,
  `FlightLine.vue`, `App\Support\FlightState`.
- **Day strip** (day 1, day 2, … of the tournament): part of the shell, one chip per event day with that day's
  count, today marked, past days collapsed. It sets `?date=` for the date-based tabs (Today, Truck plan);
  Arrivals, Departures and Status cover the whole tournament and hide it.
- **Several tournaments**: the "Showing" picker of the Day Board and Jobs Queue (`EventSelection`, session key
  `board_event_ids`, active event first), so all tournaments can be on one Today board; rows are tagged with
  their event.
- **Refresh**: the open tab reloads its own prop every minute, with a "last update" stamp; flight data is as
  fresh as `flights:sync` (the header chip shows it).
- **Access**: viewing uses the Day Board's rules (`jobs.view` or agency; supervisors without
  `jobs.view-unassigned` see only their movements; `ProviderScope`). Score entry and imports (Phase 2) are a
  separate page behind `results.manage`; the Status tab links to it.
- **Tests**: a feature test per tab service (`TodayBoardTest`, …) plus one browser test for the shell (tabs,
  day strip, picker) that grows a step per tab.

## Gap analysis

| Board feature | LMS today | Gap | Phase |
|---|---|---|---|
| **Today**: all tournaments on one timeline (flights, transfers, match-day legs, matches) | Day Board: jobs only, by hour; multi-event picker | No view mixing flights, matches and movements | 1 |
| Today: day strip with counts (past days, yesterday, today, next days) | Day Board strip counts jobs per day | Counts jobs only | 1 |
| Today: "LOG service started / completed" times | First/last done checkpoint exists on jobs | Not shown as such | 1 (derived) |
| Today: trucks required / available / short / allocated | Unassigned check, event fleet pool | No daily capacity summary | 1 (summary), 4 (full) |
| Today: "truck not required" rows | No exemption concept | Missing | 3 (shown on Today once it exists) |
| **Tournament Status**: scores, live state | `matches` has no score/status | Missing | 2 (port) |
| Standings (P W D L GF GA GD Pts) per group | Teams have `group_pool` only | Missing | 2 (port `TournamentService`) |
| Best third-placed teams (48-team FIFA format) | — | Missing | 2 (superrtq `thirdPlaceRanking`) |
| Bracket with placeholders 1A / 2B / W21 | Matches need real team ids | Knockout matches can't be planned until teams are known | 2 (+ fill planned movements when a slot resolves) |
| Team status QUALIFIED / IN CONTENTION / ELIMINATED, next match | — | Missing | 2 (deterministic), 5 (early elimination) |
| Qualification probability (simulation) | — | Missing (superrtq neither) | 5 optional |
| Status board shows each team's departure date + flight | Departure flights exist | Not linked to standings | 2 |
| **Arrivals / Departures** board per tournament | Event Teams lists flights; `flights:sync` stores estimated/actual/status | No board, no KPIs | 3 |
| Flight state ON TIME / EARLY / DELAYED / CANCELLED / DEPARTED (15-min margin) | Raw `flight_status`, `delay_minutes` | No classification | 1 (helper), 3 |
| LOG service status PENDING / IN PROGRESS / COMPLETED / NOT REQUIRED / NOT GRANTED | Job status + checkpoints | No exemption states | 3 (new fields) |
| "No kit truck required" reasons (host, pre-camp, own arrangements, by bus, flight cancelled) | — | Missing | 3 |
| Every delegation listed, incl. no flight (host, by road, not known yet) | Only teams with flights | Missing | 3 |
| KPIs: arrived X of Y, by air / by road, service provided | — | Missing | 3 |
| TSV person per delegation | Lead supervisor on movement/job | Not shown on a flight view | 3 |
| **Truck plan**: required vs available vs short, trucks from other services | Fleet pool, unassigned check | No capacity maths | 4 |
| 9 h shift per truck/crew ("5:45 of 9:00") | `driver_span_hours` / rest checks (`ConflictDetectionService`) | Not shown per vehicle | 4 |
| Pairing two jobs on one truck (fits / over shift) | Clash detection only | No suggestions | 4 |
| Timeline per truck with an "uncovered" row | Resource Schedule (one resource), Crew Assignment timeline | No all-trucks shift view | 4 |
| Live score feed every 10 min | — | Missing | 5 optional |
| Flight-change alerts | Push infra exists for jobs | No push on ETA change / cancellation | 5 optional |

The LMS already does better than the board on: real checkpoint times from the phones instead of WhatsApp
codes, crew and conflict rules, audit, multi-event and provider scoping, the Daily Email.

## Phases

### Phase 1: Today board (cross-tournament ops view)
The shell page `/board` (`TournamentBoardController`, `Pages/TournamentBoard.vue`, see "How it is built")
with its first tab, **Today** (`TodayBoard` service, `Components/Board/TodayTab.vue`), using the Day Board's
access rules (`jobs.view` or agency; supervisors without `jobs.view-unassigned` see only their movements;
`ProviderScope`). Events via `EventSelection` (session key `board_event_ids`, active event first, picker adds others).
Rows for the chosen day, sorted by time, tagged with their event:
- **Movements** of the day (arrival, departure, transfer, match legs, training) with their flight
  (`flight_id`: flight number, STA/ETA or STD, landed/departed actual time, delayed / cancelled) and match.
- **Service status** per movement from its job: Scheduled → "LOG service started HH:MM" (first done
  checkpoint) → "completed HH:MM" (last), plus Cancelled, "No job yet", "No crew".
- **Matches** of the day: kickoff, teams, stage, venue (score / LIVE / FT added in Phase 2).
- **Flights with no movement**, shown as "No LOG movement planned", so nothing is hidden.
- Header: counts by type, a day strip with counts over a range (past days collapsed), and a **trucks strip**:
  required = movements needing a vehicle that day, assigned, short, vehicles in the events' pools; links to
  Crew Assignment for that day.
- Refreshes every minute, "last update" stamp, legend.
- `App\Support\FlightState`: classifies a flight with the 15-minute margin; reused in Phase 3.
- Sidebar item + ability, guide and CLAUDE.md sections. Test `TodayBoardTest` (rows, scoping, multi-event,
  counts, flight state).

### Phase 2: Results, standings, bracket (ported from superrtq)
- Migration on `matches`: `status` (scheduled/live/finished/postponed/cancelled), `team1_score`,
  `team2_score`, `team1_penalties`, `team2_penalties`, `winner_team_id`, `team1_source`, `team2_source`
  (1A, 2B, 3:ABCD, W:21, L:21), `result_source`, `result_updated_at`; `team1_id` / `team2_id` nullable for
  unresolved knockout slots. Every place that assumes they are set (imports, plan generation, movements,
  conflict checks) gets checked first.
- New `event_standings` table. Port `TournamentService` without the prediction points: `recordResult`,
  `winnerOf`, `calculateGroupStandings` (check tie-breakers), `syncStandings` + manual override,
  `thirdPlaceRanking`, `propagate`, `autoLink`. Port the results / standings / bracket imports through
  `ReadsSpreadsheetRows::loadSheet`.
- Permission `results.manage` (permission migration); `AuditLog::change` on every result change.
- Logistics links: a resolved knockout slot fills the team on that match's movements that have no job
  (movements with a job are reported for review, like `FlightMovementSync`); an eliminated team is flagged
  "departure to plan" with a link to its departure flight/plan.
- Vue: the **Tournament Status** tab (`StatusTab`: standings with best thirds, bracket, match grid) on the
  board, and a separate results-entry page (`results.manage`) the tab links to.
- Today shows score / LIVE / FT on match rows.

### Phase 3: Arrivals / Departures boards + LOG service status
- `team_flights.log_service` (required / not_required / not_granted) + `log_service_reason`; team-level
  "no flight" entries (host, by road/bus, own arrangements) so every delegation appears.
- **Arrivals** and **Departures** tabs (`FlightBoard` service, one `FlightBoardTab` component for both):
  KPIs (arrived X of Y, by air, by road, service provided), every delegation,
  STA/ETA/STD/ETD, flight state, actual time, LOG service status from the linked job (or the exemption),
  TSV = lead supervisor.
- Today picks up the exemptions ("Truck not required").

### Phase 4: Truck plan
- The **Truck plan** tab (`TruckPlan` service, `TruckPlanTab`), using the shell's day strip. Per day and
  event(s): required vs available (pool) vs short; per-vehicle shift usage against a
  configurable shift length (default 9 h; reuse `driver_span_hours` or add a setting); timeline per vehicle
  with the shift box and limit line; an "uncovered" row; gaps for repositioning; greedy pairing suggestions
  (two jobs that fit one shift).
- Reuse `ConflictDetectionService` duty logic and the Crew Assignment timeline component.

### Phase 5: Optional
Qualification probability (Monte Carlo of remaining group matches) and early elimination; live score
feed; push to the job's supervisors on flight ETA change or cancellation.

## Notes / risks
- Making `matches.team1_id/team2_id` nullable touches imports, plan generation and conflict checks;
  audit every use before the migration.
- superrtq has two overlapping tournament schemas; port only the event-centric one (`matches` +
  `event_standings`).
- superrtq's UI is React/TS: the logic ports, the pages are rewritten in Vue.
- Flight data on Today and the flight boards is only as fresh as `flights:sync`, which now runs from the
  App Service scheduler (`startup.sh`).
- Testing: targeted feature tests per phase; browser tests only for the new pages, when Vite is up.
- Each phase is its own commit set and can ship to staging on its own.
