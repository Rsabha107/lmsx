# CLAUDE.md — NAQLA LMS (lmsx)

Logistics management for football events (FIFA U-17, GFF cups): teams, flights, matches → **plans** → **movements** → **jobs** with **checkpoints**, worked by field supervisors in the mobile app.

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
- `agency` (outside crew agency, e.g. GWC lead supervisors): reads the console; writes only crew assignment and vehicles/drivers/providers (`movements.assign-crew`, `fleet.manage-resources`). Write allow-list: `app/Http/Middleware/RestrictAgencyToCrewAssignment.php`. Refused Inertia writes return JSON 403 with `X-Access-Restricted`, shown by `AccessRestrictedModal.vue`.
- GWC agency users are seeded by `GwcLeadSupervisorSeeder` (SSO/forgot-password; random password).
- Legacy `routes/EXAMPLE_ROUTES.php` jobs/* write routes have no authorization.

## Crew model
- Lead crew lives on `movements.vehicle_id / driver_id / field_supervisor_id`, mirrored onto `jobs_operations` (`supervisor_id`). Change crew through `JobLifecycleService::assignMovementCrew()` (audits + mirrors to the job).
- **Extra vehicles + drivers**: `movement_units` (`Movement::units`).
- **Extra supervisors**: `movement_supervisors` pivot (`Movement::extraSupervisors`).
- Jobs read extras through their movement. Use `Movement::resourceIds('vehicle_id'|'driver_id'|'field_supervisor_id')` to get lead + extras.
- Mobile "own jobs" scoping: `JobOperation::supervisedBy()` / `isSupervisedBy()` and `ScopesMobileAccess::whereSupervisedBy()` — never filter on `supervisor_id` alone.
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
