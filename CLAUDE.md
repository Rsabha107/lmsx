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

## Access control
- Roles/permissions: `database/seeders/RolePermissionSeeder.php`. Add a migration whenever permissions change (see `2026_09_28_000002_add_agency_role.php`).
- Abilities shared to the UI: `HandleInertiaRequests::abilities()`; sidebar items gate on them in `resources/js/Components/AppLayout.vue`.
- `ground_control` = field supervisors (mobile only, no `console.view`).
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

## Mobile sync contract (`/api/mobile`)
- Writes (`POST jobs/{job}/checkpoints/{cp}/complete`, `POST jobs/{job}/issues`) are replay-safe: send one `Idempotency-Key` (or `client_op_id`, 8-64 of `A-Za-z0-9._:-`) per queued action. A retry after the first committed returns 200 with `Idempotent-Replayed: true`; without or with a different key it is still 409. Stored in `job_checkpoints.client_op_id` / `job_issues.client_op_id`; replay only for the same user.
- `GET jobs?updated_since=<synced_at>` returns only changed jobs (job, checkpoints or movement); `visible_ids` is always the full visible set so the app drops reassigned/removed jobs. Store the server's `synced_at`, never the device clock.
- Time evidence: completion accepts `event_at` + `client_sent_at` (ISO 8601 **with offset**, both required together). The device time is shifted by the measured clock skew (server receipt minus `client_sent_at`) and stored as venue wall-clock (the app has no timezone handling, so the offset is dropped after the maths). Implausible times (skew over a day, future, older than 7 days) fall back to server receipt. `actual_time` (HH:mm) is a `manual` time. Columns on `job_checkpoints`: `event_at` (raw claim), `received_at`, `clock_skew_seconds`, `time_source` (`device|manual|server|override`).
- Rate limits: `throttle:mobile` (120/min per user) on the whole token API, `throttle:mobile-upload` (30/min) on checkpoint completion and issue reports (`AppServiceProvider`).

## Security and audit
- Trusted proxies default to loopback/private ranges (`bootstrap/app.php`); set `TRUSTED_PROXIES` to the balancer CIDRs, `TRUSTED_HOSTS` to the hostnames. `php artisan app:check-production` verifies debug, cookies, proxies, hosts and token expiry.
- Privileged and configuration changes (users, roles, permissions, events, fleet, templates, offsets, imports) go through `AuditLog::change()` with `AuditLog::changes($model, $original)` for before/after; never put credentials in the summary. Don't log request payloads.
- `WriteRouteAuthorizationTest` fails for any write route that is auth-only and not on its allow-list.
- Events lists use `Event::accessibleTo($user)` (assignments unless admin / `events.access-all`).

## Crew model
- Job and checkpoint state changes go only through `JobLifecycleService` (the models have no mutators; `LifecycleBoundaryTest` fails if app code outside the services sets lifecycle state).
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
- `JobCheckpoint::delay_minutes` is an accessor (completed_at − scheduled_at); the raw column is `getRawOriginal`.
- Override "exclude date": the server picks the day nearest `scheduled_at` (`alignTimeOfDayTo`), matching the Jobs.vue preview.
- Datetimes are wall-clock strings; parse `YYYY-MM-DD HH:MM` as text in the frontend (no timezone conversion).

## Frontend conventions
- Pages in `resources/js/Pages`, shared components in `resources/js/Components`, composables in `resources/js/Composables`.
- Excel-style header filters: `ColumnFilter.vue` + `useColumnFilters.js` (used on Jobs and Planning; options show per-value counts).
- Crew roster helpers: `useCrewRoster.js` (`minutesFrom`, `mergedMinutes`, `duration`).
- Modals: `Modal.vue` / `ConfirmModal.vue`; toasts via `useToast`.

## Key pages
- Planning `Plans.vue` (very large), Jobs Queue `Jobs.vue`, Crew Assignment `CrewAssignment.vue` (table / day timeline / week matrix), Resource Schedule `ResourceSchedule.vue` (one resource's week as a Gantt; Excel via `/resource-schedule/export`, PDF via the browser print dialog).
- Day Board `DayBoard.vue` (`/day-board`, `jobs.view`): a day's jobs by hour with current + previous checkpoint; attention flags computed client-side against the live clock. Supervisors without `jobs.view-unassigned` see only jobs they supervise.

## Style
- Keep changes minimal; comments only for what the code can't show, one short line.
- Add a feature test in `tests/Feature` (fixtures: `Tests\Concerns\CreatesOperationsFixtures`) for behaviour changes.
