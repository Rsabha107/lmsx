# AI Operations Copilot

## Overview

A read-only, natural-language query layer over the movement/job operational data, built on `laravel/ai`. It answers questions like "which teams are delayed?" or "explain the delay on job 42" by having an LLM call a small set of pre-approved, permission-scoped PHP methods — it never sees SQL, a DB connection, or raw Eloquent models.

```
DATABASE
   ↓
DETERMINISTIC APPLICATION LOGIC   (JobCheckpoint scopes/accessors, OperationsQueryService)
   ↓
SAFE, POLICY-SCOPED TOOLS         (App\Ai\Tools\*)
   ↓
LLM (Anthropic Claude via laravel/ai)
   ↓
EXPLANATION / SUMMARY             (never invents figures — only narrates what a tool returned)
   ↓
USER
```

The LLM is treated as an untrusted interpretation layer: every number it reports comes from a tool call, every tool call is authorized server-side against the same Policies the rest of the app would use, and a tool that fails authorization returns an empty result rather than an "unauthorized" marker (so functional-area boundaries aren't discoverable by how a question is phrased).

## Access Control (RBAC)

Before this feature, the app had almost no authorization beyond `auth` — two ad-hoc roles (`admin`, `supervisor`) referenced in one query, no Policies, no functional-area enforcement. This feature added the first real authorization layer:

- **Roles** (seeded by `RolePermissionSeeder`): `admin`, `ground_control`, `transport`, `team_services`, `venue_ops`. The last three map to the existing `functional_area` enum (`LOG`/`AND`/`MOB`) already used across `movements`/`jobs_operations`.
- **Permissions**: `movements.view`, `movements.view-all-functional-areas`, `jobs.view`, `jobs.view-all-functional-areas`, `ai.use`.
- **`user_functional_areas`** — a pivot table (a user can work more than one area) mapping a user to the functional area(s) they can see. `User::hasFunctionalArea()` / `functionalAreaCodes()` are the single reusable check.
- **`MovementPolicy` / `JobOperationPolicy`** — `view()` checks event scoping (must match `session('active_event_id')`), the base `*.view` permission, and functional-area match (or the `*-all-functional-areas` permission). These are enforced **only** in the AI tool layer for now, not retrofitted onto existing controllers — see "Known limitations" below.

New users/roles are not auto-assigned by the seeder — after seeding, assign a role manually:

```php
$user->assignRole('transport'); // or admin, ground_control, team_services, venue_ops
$user->functionalAreas()->create(['functional_area' => 'LOG']); // required for the 3 scoped roles
```

## Setup

```bash
# .env
ANTHROPIC_API_KEY=sk-ant-...
AI_MODEL=              # optional, defaults to the provider's default text model
AI_TIMEOUT=15          # seconds; kept short since this is synchronous request-time Q&A

php artisan migrate
php artisan db:seed --class=RolePermissionSeeder
php artisan permission:cache-reset   # if permissions were just seeded/changed
```

No app-level `config/ai.php` is needed — `laravel/ai` auto-merges its own config, which already reads `ANTHROPIC_API_KEY` from the env. Model/timeout live in `config/services.php` under `anthropic`, matching this project's existing convention (see `aviationstack`).

## Architecture

| Layer | File(s) |
|---|---|
| Deterministic data access | `app/Services/OperationsQueryService.php` |
| AI orchestration + audit logging | `app/Services/AiCopilotService.php` |
| Agent (system prompt + tool registration) | `app/Ai/Agents/OperationsCopilotAgent.php` |
| Tools (one per capability) | `app/Ai/Tools/*.php` |
| Policies | `app/Policies/MovementPolicy.php`, `app/Policies/JobOperationPolicy.php` |
| Route/controller | `routes/web/ai.php`, `app/Http/Controllers/AiCopilotController.php` |
| Chat UI | `resources/js/Pages/Ai.vue` |
| Contextual UI | "Explain delay" button on `resources/js/Pages/JobDetail.vue` |
| Audit log | `ai_interactions` table / `App\Models\AiInteraction` |

### Tools available to the Copilot

| Tool | Backs | Scope |
|---|---|---|
| `GetActiveMovementsTool` | `OperationsQueryService::getActiveMovements()` | event + functional area |
| `GetDelayedMovementsTool` | `getDelayedMovements()` | event + functional area |
| `GetUpcomingMovementsTool` | `getUpcomingMovements($withinMinutes)` | event + functional area |
| `GetJobStatusSummaryTool` | `getJobStatusSummary()` | event + functional area |
| `GetMovementDetailsTool` | `getMovementDetails($movementId)` | `MovementPolicy::view` |
| `GetMissingUpdatesTool` | `getMissingUpdates()` — active jobs with an overdue checkpoint | event + functional area |
| `ExplainDelayTool` | `explainDelay($jobId)` — checkpoint-by-checkpoint variance, largest contributor, recovery | `JobOperationPolicy::view` |
| `GetCheckpointPerformanceTool` | `getCheckpointPerformance()` — avg delay per checkpoint name | event + functional area |

All the delay/on-time figures reuse `JobCheckpoint`'s existing `scopeOverdue()`, `scopeDelayed()`, and `getDelayMinutesAttribute()` rather than re-deriving the arithmetic a third time (it was previously duplicated between the model and `LmsController`).

### Adding a new tool

```bash
php artisan make:tool SomeNewTool   # scaffolds into App\Ai\Tools
```

1. Add the deterministic query to `OperationsQueryService` (authorize via the existing Policies, return a plain array — never raw Eloquent models, so nothing unintended reaches the LLM).
2. Implement `description()` (tells the LLM when to use it), `schema()` (its parameters, via Laravel's `JsonSchema` builder), and `handle()` (resolve `auth()->user()`/`session('active_event_id')` via the `ResolvesRequestContext` trait, call the service, `json_encode()` the result).
3. Register it in `OperationsCopilotAgent::tools()`.

### Safety / cost controls

- **Read-only.** No tool can modify a movement, checkpoint, or user.
- **Timeout-bounded.** `AiCopilotService::ask()` wraps every call in try/catch; on any failure (timeout, provider outage, malformed response) it returns `{ok: false, message: "..."}` — never a 500, and the rest of the app is completely unaffected by the AI provider being down.
- **Rate limited.** `RateLimiter::for('ai', ...)` in `AppServiceProvider` — 10 requests/minute per user, applied via `throttle:ai` on the routes.
- **Audited.** Every call (success or failure) writes an `ai_interactions` row: which tools were invoked and with what arguments, provider/model, duration, status — but never the tool's output payload, checkpoint photo/signature data, GPS coordinates, or the raw LLM request/response.

## Known limitations

- Policies (`MovementPolicy`/`JobOperationPolicy`) are enforced **only** in the AI tool layer. Existing screens (`LmsController`, `JobOperationController`, etc.) still have no row-level authorization — this was a deliberate minimal-risk choice to avoid destabilizing untested existing screens. It does mean the Copilot can show a user *less* than the same user can already see via the regular UI.
- The seeder creates roles/permissions but assigns none to existing users — that's a manual step per user.
- Delay figures depend on realistic `scheduled_at`/`completed_at` data; some seeded sample jobs have unrealistic multi-month gaps between the two, which `explainDelay` will faithfully (if uselessly) report.

## Testing

```bash
php artisan test --filter=AiCopilot
php artisan test --filter=OperationsQueryServiceTest
```

Tests use `.env.testing` (real mysql, `lmsx_test` database — kept separate from the dev DB since these tests use `RefreshDatabase`). `OperationsCopilotAgent::fake([...])` / `::fake(fn () => throw new \Exception(...))` simulate provider responses/failures without hitting the real API — see `tests/Feature/AiCopilotDegradationTest.php` for the failure-path pattern.

## Roadmap

Phase 3 (not yet built): a deterministic movement risk engine (GREEN/AMBER/RED/CRITICAL, explainable scoring), downstream impact analysis across sequential checkpoints, and configurable operational alert thresholds.
