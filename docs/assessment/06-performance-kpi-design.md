# Time Integrity, Efficiency Metrics, and KPI Design

## Current Evidence

Scheduled checkpoint time is correctly derived from authoritative movement reference plus checkpoint-specific configured offset, with no invented fallback (`app/Services/JobGenerationService.php:393-426`). Flight changes re-time unfinished checkpoints while preserving resolved records (`JobGenerationService.php:176-207`). The current mobile API accepts `HH:mm`; lifecycle code selects the date/overnight interpretation (`JobLifecycleService.php:804-827`). Existing punctuality uses movement-window end, while other UI/analytics show checkpoint schedule variance (`JobLifecycleService.php:849-876`). These measures must not be presented as one KPI.

## Required Timestamp Model

For each checkpoint completion retain immutable fields:

| Field | Meaning |
| --- | --- |
| `baseline_scheduled_at` | Original approved operational target. |
| `revised_scheduled_at` | Latest target after accepted flight/match/operations change. |
| `occurred_at_device` | Full ISO-8601 device event time with offset, supplied only as evidence. |
| `received_at_server` | Server receipt time, authoritative. |
| `synced_at_server` | Server time durable sync was confirmed. |
| `device_id`, `operation_id`, `app_version` | Replay, diagnostics and device provenance. |
| `time_source`, `accuracy`, `correction_reason` | Device/server/manual/offline source and correction governance. |

Keep wall-clock planning semantics where the domain requires them, but store the event timezone/airport-local timezone explicitly. Do not turn a device timestamp into truth merely because it was submitted.

## Measurement Rules

- Checkpoint variance: `occurred_at - revised_scheduled_at`; retain baseline variance separately.
- Movement punctuality: arrival/completion versus revised movement target.
- Execution efficiency: controllable time excluding accepted external delays and inherited predecessor delay.
- Recovery: reduction of inherited variance across later checkpoints.
- Delay attribution: `external`, `resource`, `passenger/team`, `venue/security`, `weather/traffic`, `data/unknown`, with optional contributor weights.
- Corrections must append a correction event; never overwrite the original submitted/received evidence.

## KPI Framework

| KPI | Decision supported | Guardrail |
| --- | --- | --- |
| On-time movement rate | Is the program meeting its revised commitment? | Segment by route/type and exclude cancelled work. |
| Baseline disruption rate | How much did original schedule change? | Attribute to reference/source, not supervisor. |
| Checkpoint adherence | Which operating step is unstable? | Compare like checkpoint/version only. |
| Controllable execution variance | Where do resource processes need improvement? | Exclude accepted external/inherited delay. |
| Recovery rate | Which teams recover disruption safely? | Never reward unsafe compression. |
| Evidence compliance | Is required proof complete? | Separate system sync failure from operator omission. |
| Exception response SLA | Are risks owned and resolved? | Measure acknowledged and resolved times. |

No performance score should be emitted when time source, revised target, or attribution is unknown. Report data quality alongside KPIs.
