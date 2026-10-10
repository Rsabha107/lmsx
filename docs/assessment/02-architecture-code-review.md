# Architecture and Code Quality Audit

## Current-State Architecture

Laravel 13 / PHP 8.4 provides a Vue 3/Inertia console and Sanctum mobile API (`composer.json:8-27`, `routes/api.php:15-44`). The correct bounded-context direction is a modular monolith:

`Checkpoint library -> CheckpointTemplate -> MovementTemplate/legs -> Plan -> Movement -> JobOperation -> immutable JobCheckpoint -> evidence, audit, issues`

`JobGenerationService` creates a job and snapshot transactionally (`app/Services/JobGenerationService.php:43-146`). `JobLifecycleService` is the intended mutation boundary, using locks, evidence validation, state transitions, crew mirroring, and audit records (`app/Services/JobLifecycleService.php:45-116,181-248,468-550`). Conflict detection includes lead and additional resources, cross-event overlap, capacity, turnaround, and driver rest (`app/Services/ConflictDetectionService.php:283-315,368-531,887-939`).

## Capability Assessment

| Capability | Status | Evidence |
| --- | --- | --- |
| Reusable checkpoints and required evidence | Fully implemented | `app/Models/Checkpoint.php:13-30`; `JobLifecycleService.php:785-802` |
| Ordered reusable checkpoint templates | Fully implemented | `app/Models/CheckpointTemplate.php:39-47` |
| Movement templates with ordered legs | Fully implemented | `app/Models/MovementTemplate.php:40-53` |
| Plan creation and reference-driven timing | Partially implemented | `JobGenerationService.php:532-764`; lifecycle/version states absent |
| Job generation with immutable execution snapshot | Fully implemented | `JobGenerationService.php:43-146` |
| Multi-vehicle/multi-supervisor assignment and conflicts | Fully implemented | `Movement.php:188-224`; `ConflictDetectionService.php:710-797` |
| Web/mobile execution authorization | Implemented but defective | Maintained API is scoped; legacy and browser mobile routes are not consistently authorized. |
| Live tracking and durable alerts | Partially implemented | Day Board is live polling; dashboard/tracker contain mock projections. |
| Async integrations and operational messaging | Missing | No application queue jobs/domain events found; scheduler only runs flight sync. |

## Findings

| Priority | Finding | Why it matters | Recommendation | Complexity |
| --- | --- | --- | --- | --- |
| P0 | Production-loaded legacy routes bypass policies. | Undermines the otherwise strong service boundary and all role/event rules. | Remove routes/controllers if obsolete; otherwise route every action through policy + `JobLifecycleService`, scoped binding, and denial tests. | Low-Medium |
| P1 | Multiple public lifecycle paths remain, including model methods and legacy controller. | Behavioral drift and future bypasses are likely. | Make lifecycle service the only mutation API; reduce model methods to internal state helpers. | Medium |
| P1 | Template mutations do not have draft/publish/version semantics. | Changes after planning can confuse operators even though generated snapshots are protected. | Version templates; preserve published versions; show revision impact and require explicit replan for unissued work. | Medium |
| P1 | Synchronous conflict/planning/report/mail processing. | High-volume imports or bulk actions can block users and make failures hard to retry. | Keep monolith; introduce queues/outbox for imports, notifications, reports, and expensive regeneration. | Medium |
| P2 | `LmsController` mixes dashboard, legacy mobile, reporting, mail, files and queries. | Authorization and regression review become expensive. | Incrementally extract query controllers and dedicated actions; do not rewrite all at once. | Medium |
| P2 | Mock dashboard/tracker projections coexist with live pages. | Operators may treat demo data as operational truth. | Label/remove from operations navigation until backed by live, scoped queries. | Low |

## Strengths To Retain

- Snapshotting checkpoint requirements into jobs avoids retroactive template edits changing historical execution.
- DB transactions and row locks in execution reduce concurrent completion races.
- `ProviderScope`, event pools, agency write restriction, and multi-resource conflict checks reflect real transport operations.
- The checkpoint schedule is recalculated from reference times/settings, not shifted blindly after a flight change (`JobGenerationService.php:176-207,393-426`).

## Scalability Position

Do not split to microservices. First establish a queue worker, Redis-backed cache/queue if justified, database indexes validated by real query plans, an outbox/event table, and observability around planning/conflict duration. Extract a service only when independent scaling or ownership is proven.
