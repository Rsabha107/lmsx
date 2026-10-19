# CLAUDE.md — NAQLA LMS (lmsx)

Logistics management for football events (FIFA U-17, GFF cups): teams, flights, matches → **plans** → **movements** → **jobs** with **checkpoints**, worked by field supervisors in the mobile app.

User-facing documentation lives in one file: `docs/NAQLA_LMS_GUIDE.md` (source for the user manual). Update it when behaviour changes.

## Stack
- Laravel 13 (PHP 8.4), Inertia v3 + Vue 3 (`<script setup>`), Vite, MySQL.
- Spatie laravel-permission (roles/permissions), Sanctum (mobile API at `/api/mobile`), Socialite Microsoft SSO, PhpSpreadsheet (xlsx import/export).

## Running things (Windows / Laragon)
- PHP is not on PATH: `$env:Path = "C:\laragon\bin\php\php-8.4.14-nts-Win32-vs17-x64;" + $env:Path`
- Tests: `php artisan test *> storage\logs\x.txt`, then read the file (terminal output is flaky). Tests use `.env.testing` → MySQL `lmsx_test`; the first test runs `migrate:fresh` (~30s). Never run two test runs at once — they share `lmsx_test`.
- **Do not run `npm run build` without the user's confirmation.**
- `ConflictResolveTest` can flake (random fixture country code collision).
- Browser smoke tests (Playwright, `tests/e2e`): `$env:PHP_BINARY = "<path>\php.exe"; npx playwright test`. They start `php artisan serve` on :8020 with `.env.e2e` (database `lmsx_e2e`, reset by `E2eSeeder` each run) and need the Vite dev server (`npm run dev`) running. Tests fail on any console error or Vue warning raised after sign-in. The first run right after editing a page can hit a Vite reload (blank page); run again before debugging. If `HTTP_PROXY` is set, also set `$env:NO_PROXY = "127.0.0.1,localhost"`: otherwise Playwright's readiness check gets the proxy's 403, treats :8020 as already up and never starts the server (every test then fails with `ERR_CONNECTION_REFUSED`).

## Access control
- Roles/permissions: `database/seeders/RolePermissionSeeder.php`. Add a migration whenever permissions change (see `2026_09_28_000002_add_agency_role.php`).
- Abilities shared to the UI: `HandleInertiaRequests::abilities()`; sidebar items gate on them in `resources/js/Components/AppLayout.vue`.
- `ground_control` = field supervisors (mobile only, no `console.view`).
- Acting on a job (complete checkpoints, report/resolve issues, change status) needs `jobs.work` on top of seeing it: `JobOperationPolicy::update` (web) and `ScopesMobileAccess::authorizeJobWork` (mobile + legacy token API). `mobile_viewer` = read-only mobile app users: `jobs.view`, `jobs.view-unassigned`, `jobs.view-all-functional-areas`, no `jobs.work`, so they see every job of their events (their provider's only when linked to one) but cannot act; never offered as crew (`User::fieldSupervisors` is ground_control only). The API user payload (`/login`, `/me`, `/profile`) carries `read_only` so pma_mobile (`C:\laragon\www\pma_mobile`) hides the actions. Added by `2026_10_19_000001_add_mobile_viewer_role`, which gave `jobs.work` to every role/user that had `jobs.view`.
- `SecurityRole` holds `access.manage`, which gates Roles & Permissions (`/setups/access`, roles, permissions routes) and its sidebar item; admin does not have it. `User::PROTECTED_ROLES` (`admin`, `SecurityRole`) cannot be deleted or renamed (`RoleController`). Granting/removing SecurityRole or deleting a holder needs `access.manage` (`UserController::guardSecurityRole`). Migration `2026_10_09_000001_add_security_role` assigns it to r.sabha@sc.qa.
- `agency` (outside crew agency, e.g. GWC lead supervisors): reads the console; writes only crew assignment and vehicles/drivers/providers (`movements.assign-crew`, `fleet.manage-resources`). Write allow-list: `app/Http/Middleware/RestrictAgencyToCrewAssignment.php`. Refused Inertia writes return JSON 403 with `X-Access-Restricted`, shown by `AccessRestrictedModal.vue`.
- GWC agency users are seeded by `GwcLeadSupervisorSeeder` (SSO/forgot-password; random password).
- `routes/EXAMPLE_ROUTES.php` keeps only the admin template routes and the legacy token API (`/api/my-jobs`, `/api/jobs/{job}/progress`, `/api/checkpoints/{id}/quick-complete`); the latter use `ScopesMobileAccess` like `/api/mobile`. Its old web `jobs/{job}/start|complete|...` routes were removed (they were authenticated-only).
- `JobOperationPolicy::view/update` (web) also limit users without `jobs.view-unassigned` to jobs they supervise, like the mobile API; `override`/`delete` rely on `jobs.override`/`plans.manage` instead. `LmsController` uses `ScopesMobileAccess` for the browser mobile list.

## Providers and fleet
- Provider scoping: `users.fleet_provider_id` (not `provider_id`, which is SSO), `movements.fleet_provider_id`, `events.fleet_provider_id` (default for new movements via the `Movement` creating hook). `ProviderScope` (global scope on Movement/JobOperation/Driver/Vehicle/FleetProvider) applies when `User::isProviderRestricted()` (agency or has a provider, and not admin). A restricted user with no provider sees nothing.
- Crew rules: `app/Support/CrewEligibility.php`. Cross-event clashes: `ConflictDetectionService::foreignMovements`.
- Event fleet pool: `event_vehicle` / `event_driver` (`Vehicle`/`Driver::inEventPool`), toggled on the Fleet page via `/fleet/pool`. The pool is separate from provider ownership: fleet that gets a provider later is not in it until `php artisan fleet:sync-pool --apply` (also run by `fleet:adopt-unowned` / `fleet:assign-orphans`, and on an event's default-provider change via `EventFleetPool`).
- Vehicles require a provider (except for restricted users, who are pinned to theirs by `FleetController::pinProvider`).
- Fleet bulk actions: `fleet.bulk-delete` (skips rows in use; provider deletes refused for restricted users) and `fleet.bulk-provider` (admin only, not on the agency allow-list).
- Test fixtures: `createVehicle`, `createDriver`, `createProviderUser`, `fixtureProvider` in `CreatesOperationsFixtures`.

## Mobile app download
- `GET /downloads/mobile-app` (`MobileAppDownloadController`, ability `mobileApp.download`) streams the APK from the `local` disk at `config('app.mobile_apk')` (`MOBILE_APK_PATH`, default `downloads/NAQLA LMS - V(1.0.0).apk`). Linked from the sidebar and Settings.
- `GET /get-app` (`GetApp.vue`, standalone, ground_control|transport): download-only page. The dashboard sends those roles there when the Jobs (Mobile) flag is off; logins of non-console users go via the dashboard.

## Mobile sync contract (`/api/mobile`)
- Writes (`POST jobs/{job}/checkpoints/{cp}/complete`, `POST jobs/{job}/issues`) are replay-safe: send one `Idempotency-Key` (or `client_op_id`, 8-64 of `A-Za-z0-9._:-`) per queued action. A retry after the first committed returns 200 with `Idempotent-Replayed: true`; without or with a different key it is still 409. Stored in `job_checkpoints.client_op_id` / `job_issues.client_op_id`; replay only for the same user.
- `GET jobs?updated_since=<synced_at>` returns only changed jobs (job, checkpoints or movement); `visible_ids` is always the full visible set so the app drops reassigned/removed jobs. Store the server's `synced_at`, never the device clock.
- Time evidence: completion accepts `event_at` + `client_sent_at` (ISO 8601 **with offset**, both required together). The device time is shifted by the measured clock skew (server receipt minus `client_sent_at`) and stored as venue wall-clock (the app has no timezone handling, so the offset is dropped after the maths). Implausible times (skew over a day, future, older than 7 days) fall back to server receipt. `actual_time` (HH:mm) is a `manual` time. Columns on `job_checkpoints`: `event_at` (raw claim), `received_at`, `clock_skew_seconds`, `time_source` (`device|manual|server|override`).
- Rate limits: `throttle:mobile` (120/min per user) on the whole token API, `throttle:mobile-upload` (30/min) on checkpoint completion and issue reports (`AppServiceProvider`).

## Push notifications (FCM)
- Off until `FCM_CREDENTIALS` (service-account JSON path) is set; `FcmClient::enabled()`. The app needs `FIREBASE_*` in its `config/*.json`.
- Devices: `POST/DELETE /api/mobile/devices` → `user_devices`, tied to the Sanctum token (cascade: logout drops the device). Dead tokens are deleted on send.
- `JobPushNotifier` sends to a job's supervisors (lead + extras), never to the actor, via `SendPushNotification::dispatchAfterResponse` (no queue worker needed). Crew changes go through `trackCrew()` (wraps `assignMovementCrew`, `reassignCrew`, Planning `updateMovement`; nested calls report once). Also `jobsCreated` (bulk generation, grouped) and `retimed` (`rescheduleJob`).
- `push:job-reminders` (every minute): 60 / 15 min before a not-started job's first scheduled checkpoint; `push_notification_log` dedupes per lead/job/start time/user.
- Tests: `Bus::fake([SendPushNotification::class])`; `PushNotificationsTest::enableFcm()` makes a throwaway key.

## Security and audit
- Trusted proxies default to loopback/private ranges (`bootstrap/app.php`); set `TRUSTED_PROXIES` to the balancer CIDRs, `TRUSTED_HOSTS` to the hostnames. `php artisan app:check-production` verifies debug, cookies, proxies, hosts and token expiry.
- Privileged and configuration changes (users, roles, permissions, events, fleet, templates, offsets, imports) go through `AuditLog::change()` with `AuditLog::changes($model, $original)` for before/after; never put credentials in the summary. Don't log request payloads.
- `WriteRouteAuthorizationTest` fails for any write route that is auth-only and not on its allow-list.
- Events lists use `Event::accessibleTo($user)` (assignments unless admin / `events.access-all`).
- `PlanManagementController`: plans, movements and templates reached by id must be in the active event (`ensureInActiveEvent`); lists and bulk actions filter on `activeEventId()` (null when none picked or access was removed). `PlanEventScopingTest` covers it.

## Crew model
- Job and checkpoint state changes go only through `JobLifecycleService` (the models have no mutators; `LifecycleBoundaryTest` fails if app code outside the services sets lifecycle state). `syncJobProgress` completes a job once every checkpoint is done or skipped with at least one done; `completed_at` = last done step by order (`alignJobTimes`).
- Lead crew lives on `movements.vehicle_id / driver_id / field_supervisor_id`, mirrored onto `jobs_operations` (`supervisor_id`). Change crew through `JobLifecycleService::assignMovementCrew()` (audits + mirrors to the job).
- **Extra vehicles + drivers**: `movement_units` (`Movement::units`).
- **Extra supervisors**: `movement_supervisors` pivot (`Movement::extraSupervisors`).
- Jobs read extras through their movement. Use `Movement::resourceIds('vehicle_id'|'driver_id'|'field_supervisor_id')` to get lead + extras.
- Mobile "own jobs" scoping: `JobOperation::supervisedBy()` / `isSupervisedBy()` and `ScopesMobileAccess::whereSupervisedBy()` — never filter on `supervisor_id` alone.
- Who can be a supervisor: `User::fieldSupervisors()` (ground_control, provider-scoped for restricted viewers) — the one source for Crew Assignment, Planning, the Jobs override and `ConflictDetectionService::crewOptions`. Whoever is already on a movement stays allowed.
- `ConflictDetectionService` treats extras like the lead (double-booking, turnarounds, driver duty, capacity sum). Lead-only clash ids keep the `a-b` form so existing acceptances match; extra-resource clashes append `-{id}`.
- Option B (split a movement into linked per-vehicle movements) is recorded in `docs/decisions/multi-vehicle-jobs.md`.

## Scheduling rules
- Checkpoint times = reference time (flight / kick-off) + `SettingsService::getCheckpointOffset` (no fallback). Flight edits re-time via `JobGenerationService::rescheduleJob` — never shift by a delta.
- Movement window length = `MovementTemplate::legMinutes()` (one-leg template: template `estimated_duration_minutes`; multi-leg: leg's own, else an even share of the template's; else 30). `planFormat.legMinutes` mirrors it for previews. Template saves re-end windows of movements without a job (`applyTemplateDurations`).
- Road legs: a team flight with number `BUS` is a bus arrival/departure. It is timed from the bus's `scheduled_at` like a flight and generates jobs. Offsets use `Movement::offsetType()` (`bus_arrival` / `bus_departure`), which fall back to `arrival` / `departure` when not configured (`SettingsService::OFFSET_FALLBACK`, applied in `getMovementOffset`, `getCheckpointOffset` and `offsetScope`). Always pass `offsetType()`, not `kind`, when resolving a movement's offset.
- Team flight edits (Event Teams `TeamFlightsController@update`, the OAG save in `@sync`, and the team import) go through `FlightMovementSync`: movements without a job are re-timed (`recomputeMovementWindow`) and take the new number / pax; a team-only plan follows the flight to a new day; movements with a job are reported `needs_review`, never changed. The result reaches Event Teams as the `flight_change` flash.
- `JobCheckpoint::delay_minutes` is an accessor (completed_at − scheduled_at); the raw column is `getRawOriginal`.
- Punctuality (`is_on_time`, raw `delay_minutes`) is judged against the checkpoint's own `scheduled_at` (`JobLifecycleService::resolvePunctuality`); no scheduled time → both null. `php artisan checkpoints:recompute-punctuality [--event=] [--apply]` re-judges stored results (dry run by default). Daleel's movement details send a `punctuality` label (`on time` / `late` / `not judged`) so a null flag isn't misread.
- Override "exclude date": the server picks the day nearest `scheduled_at` (`alignTimeOfDayTo`), matching the Jobs.vue preview.
- Datetimes are wall-clock strings; parse `YYYY-MM-DD HH:MM` as text in the frontend (no timezone conversion).
- Everything is Qatar time: `APP_TIMEZONE=Asia/Qatar` (so `now()` is Doha), MySQL connection pinned to `+00:00` (`DB_TIMEZONE`) so TIMESTAMP columns keep their digits. Models use `Concerns\SerializesWallClock` (JSON = stored digits + `Z`, never converted); API ISO dates go through `App\Support\WallClock::iso()` (digits + `+00:00`). Browser variance maths uses `Composables/useVenueTime.js`, not `*_ts`. `php artisan time:shift-to-venue [--apply]` is the one-off +3h move of old server-clock (UTC) values; it refuses a second run.
- Admins see the app, MySQL (app session and server default), UTC and browser clocks in the Dashboard's System Clocks card (`LmsController::clocks`). SQL clients on a MySQL whose default zone isn't `+00:00` (local Laragon: Arab Standard Time) show TIMESTAMP columns shifted; `SET time_zone='+00:00'` first.

## Frontend conventions
- Pages in `resources/js/Pages`, shared components in `resources/js/Components`, composables in `resources/js/Composables`.
- Excel-style header filters: `ColumnFilter.vue` + `useColumnFilters.js` (used on Jobs and Planning; options show per-value counts).
- Crew roster helpers: `useCrewRoster.js` (`minutesFrom`, `mergedMinutes`, `duration`).
- Modals: `Modal.vue` / `ConfirmModal.vue`; toasts via `useToast`.
- No `v-html` for data. `ConfirmModal`'s `message` goes through `InlineMarkup.vue`: only `<strong>`, `<b>`, `<em>`, `<br>` and entities are honoured, anything else shows as text.

## Key pages
- Planning `Plans.vue` (very large; being split into `Components/Plans/*`: `NewPlanModal` (numbered steps: mode → template → team/date or a per-day tick list (`pickDays`); exclusions keyed per preview row, reset when template or mode changes), `EditMovementModal` (crew rows: row 1 = lead vehicle/driver, rest = `units`; supervisor chips: first = lead, rest = extras), `PlanList`, `ConflictsTab`; dialogs share `planDialog.css` via `<style scoped src>`. Dialogs mount with `v-if` so each opening starts clean. Shared helpers in `Composables/planFormat.js`, `planDuplicateChecks.js`; crew option rule `crewPeople.crewOptions` (also used by Crew Assignment)), Jobs Queue `Jobs.vue`, Crew Assignment `CrewAssignment.vue` (table / day timeline / week matrix), Resource Schedule `ResourceSchedule.vue` (one resource's week as a Gantt; Excel via `/resource-schedule/export`, PDF via the browser print dialog).
- Duty Roster `DutyRoster.vue` (`/duty-roster`, `DutyRosterController`): staff shifts per event (`roster_members`, `roster_shifts`, global `shift_types`). Admin only: every route (view, export, edit) needs `roster.manage`, held only by admin (`2026_10_14_000001_make_duty_roster_admin_only`). Tabs: Summary (default; coverage, workload, period figures, computed client-side) and Schedule (`?tab=schedule`, paint grid). Excel via `/duty-roster/export`.
- Day Board `DayBoard.vue` (`/day-board`, `jobs.view`): a day's jobs by hour with current + previous checkpoint; attention flags computed client-side against the live clock. Supervisors without `jobs.view-unassigned` see only jobs they supervise. Agency also gets it (route `role_or_permission:jobs.view|agency`, ability `dayBoard.view`), seeing all crews' jobs within `ProviderScope`. Their job links open `JobDetail` read-only (`JobOperationPolicy::read`, `job.read_only`; no buttons, no `jobs.view`).

## Style
- Keep changes minimal; comments only for what the code can't show, one short line.
- Add a feature test in `tests/Feature` (fixtures: `Tests\Concerns\CreatesOperationsFixtures`) for behaviour changes.
