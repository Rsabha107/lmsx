# Testing and Production Readiness

## Executed Checks

| Command | Result |
| --- | --- |
| `php artisan about --only=environment --no-ansi` | Laravel 13.6.0, PHP 8.4.14, local environment, UTC. Debug was enabled locally. |
| `php artisan test` | A first 120-second attempt was interrupted. A subsequent complete run passed: 203 tests, 991 assertions, 114.66 seconds. |
| `composer audit --no-interaction --format=plain` | Found 38 advisories affecting 11 packages. |
| `npm audit --omit=dev --json` | Found 8 production dependency findings, 7 high. |
| Route/static inspection | Verified P0 legacy authorization bypass and maintained mobile API controls. |

## Existing Coverage Strengths

Feature tests cover mobile job scoping, provider/agency restrictions, crew extras/conflicts, flight re-timing, cancellation/deletion, day board, imports and access roles. Examples: `tests/Feature/MobileAssignedJobsTest.php`, `AgencyCrewAssignmentTest.php`, `MovementUnitsTest.php`, `OverrideFlightChangeTest.php`, and `DayBoardTest.php`.

## Coverage Gaps

- Legacy `/jobs/*` and legacy `/api/*` cross-user/event authorization denial.
- Mobile checkpoint completion: required evidence, out-of-order rejection, idempotent retry, timestamp correction, and upload failure recovery.
- Offline/cache/synchronization/push/secure storage/native accessibility, because mobile source is absent.
- Full E2E scenario, concurrency stress, load/performance, backup restore, deployment rollback, security DAST, and abuse-rate tests.
- Template revision/regeneration and change-impact acceptance.

## Required E2E Acceptance Scenario

1. Import teams/matches, configure locations and publish checkpoint/movement template versions.
2. Generate plans twice using same inputs; prove deterministic no-duplicate result.
3. Validate timing/dependencies/conflicts; assign lead and extra resources; issue jobs only when ready.
4. Execute checkpoint flow on mobile online and offline, including signature/photo and a safe retry after lost response.
5. Simulate flight delay, early completion, late completion, reassignment, bad evidence upload, and two concurrent operators.
6. Verify revised targets, baseline and actual time evidence, audit/correction history, scoped notifications, and dashboard actions.
7. Verify final report attributes external delay separately from controllable execution variance.

## Production Readiness Gate

Block release until: P0 security fix/tests are complete; dependency remediation is reviewed; mobile offline behavior is demonstrated; a full suite completes; critical E2E/concurrency tests pass; queue/worker/backup/rollback/runbook are exercised; and load targets are set from expected simultaneous movements and evidence uploads.
