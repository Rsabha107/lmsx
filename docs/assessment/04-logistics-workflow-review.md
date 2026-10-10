# Logistics Workflow Review

## What Works

The implemented workflow matches the intended core model. Checkpoint definitions carry capture/evidence requirements; templates sequence reusable definitions; movement templates comprise legs; plans contain movements; generated jobs snapshot checkpoint requirements; supervisors execute checkpoints with evidence. Crew can include additional vehicles/drivers/supervisors and conflict checks cover real allocation risks.

Examples: template sequence (`app/Models/CheckpointTemplate.php:39-47`), movement leg structure (`app/Models/MovementTemplate.php:40-53`), reference-based plan creation (`app/Services/JobGenerationService.php:532-764`), lifecycle/override/cancellation (`app/Services/JobLifecycleService.php:45-116,291-386`), and conflict coverage (`app/Services/ConflictDetectionService.php:368-797`).

## Process Gaps

| Area | Status | Risk | Recommendation |
| --- | --- | --- | --- |
| Checkpoint fields | Partial | Required photo/signature/baggage exists, but conditional fields, passenger counts, structured checklists, approvals, and explicit exception reason schemas are not evidenced. | Define field schema per checkpoint version, including validation, visibility condition, and reason taxonomy. |
| Sequence/dependencies | Partial | Browser prevents out-of-order completion, but service/API only checks already-resolved state (`JobLifecycleService.php:712-721`). Parallel/dependency semantics are absent. | Enforce dependencies server-side; model sequential, parallel, and conditional gates. |
| Template governance | Partial | No draft/published/deprecated/versioned states; changing template legs affects future/planned semantics. | Publish immutable template versions; create revision and impact-review workflow. |
| Planning/replanning | Partial | Reference updates re-time outstanding checkpoints, and conflicts are broad; idempotent regeneration/replacement policy is not evidenced. | Add a deterministic plan key, dry-run impact report, revision IDs, and rules for issued/in-progress work. |
| Plan-to-job readiness | Partial | Generation snapshots correctly, but controlled readiness gate for crew/evidence/accepted conflicts is not explicit. | Require plan validation state: draft, ready, blocked, issued, superseded, cancelled. |
| Exceptions | Partial | Overrides/cancellations/issues exist; no owned incident/escalation/recovery workflow. | Add exception record with severity, owner, action, resolution, and operational impact. |

## Recommended State Model

- Template: `draft -> published -> deprecated`; generated plans retain template version.
- Plan/movement: `draft -> validated -> ready -> issued -> executing -> completed`, with `blocked`, `superseded`, `cancelled` side states.
- Job: retain current `pending/dispatched/in-progress/completed/cancelled`; add explicit `blocked` only if it changes assignment behavior.
- Checkpoint: `pending -> completed`; controlled `skipped/missed/overridden`, all with reason and audit. Dependency resolution must occur server-side.

## Resilience Matrix

| Scenario | Current evidence | Required improvement |
| --- | --- | --- |
| Flight/kickoff changes | Planned/unfinished checkpoint re-timing exists; issued-job impact is flagged in tests. | Revised target and acknowledgement workflow. |
| Crew/vehicle unavailable | Reassignment, resource conflict and cancellation support exist. | Dispatch replacement procedure and escalation SLA. |
| Wrong/missed checkpoint | Override with reason/audit exists. | Mobile correction UX, supervisor approval policy, and immutable correction history. |
| Split across vehicles | Extra movement units and capacity conflict checks exist. | Define passenger allocation, per-unit evidence, and split/merge reporting. |
| Evidence upload failure/network loss | Transaction cleanup exists server-side. | Offline local durable queue and replay-safe evidence sync. |
| Simultaneous updates | Row locking exists. | Idempotency and version conflict responses for mobile sync. |

Operational rule: never score a supervisor late merely because the reference flight/match or an upstream checkpoint moved. Preserve baseline, revised target, external cause, and recovery action.
