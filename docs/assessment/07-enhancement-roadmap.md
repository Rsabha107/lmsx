# Enhancement Roadmap

## Priority Plan

| Priority | Enhancement | Type | Value / prerequisite |
| --- | --- | --- | --- |
| P0 | Retire or fully secure legacy job/checkpoint routes. | Security/rule-based | Protects execution integrity; policy and regression tests required. |
| P1 | Offline-first mobile sync contract with idempotent operation IDs, evidence queue, conflict resolution. | Reliability | Essential field prerequisite; requires mobile source ownership and API versioning. |
| P1 | Time evidence, revised targets, reason taxonomy, correction audit. | Data integrity | Enables fair and trusted KPIs; database/API/mobile migration. |
| P1 | Plan/template versions, deterministic regeneration and impact review. | Rule-based | Prevents duplicate/stale operational work. |
| P1 | Exception queue with owner, severity, SLA, acknowledgement and reassignment. | Operations | Turns visibility into corrective action. |
| P1 | Dependency patch and production security baseline. | Security | Required release gate. |
| P2 | Durable notifications/outbox/queues and push escalation. | Reliability | Requires worker monitoring and device registration. |
| P2 | Live control board backed only by real scoped data. | UX/operations | Build on exception and time models. |
| P2 | Rule-based impact analysis for flight/kickoff/vehicle changes. | Automation | Uses conflict engine and template revisions. |
| P2 | Geofence-assisted arrival validation. | Rule-based | Optional, consented, accuracy-aware; never sole proof. |
| P3 | Delay-risk prediction and resource recommendations. | Statistical | Needs sufficient clean historical time/attribution data. |
| P3 | AI daily summary and natural-language analytics. | Generative AI | Use read-only, scoped aggregates with citations and human review. |

## Automation Classification

- **Deterministic first:** plan keys, duplicate prevention, conflict detection, re-timing, missing-evidence alerts, reassignment suggestions, and change impact analysis.
- **Statistical later:** ETA, missed-checkpoint risk, demand/resource forecasts, and anomaly detection after data quality is measured.
- **Generative AI last:** briefing narratives, summarisation, and natural-language analysis. It must not issue jobs, change timestamps, or resolve conflicts autonomously.

## Quick Wins

- Delete/disable `EXAMPLE_ROUTES.php` execution surface after compatibility inventory.
- Make every state-changing endpoint policy-protected and add a route inventory test.
- Explicitly set production proxy/host/session/debug/token settings.
- Label mock dashboard/tracker data or remove it from live operations.
- Add Day Board actions for acknowledge, assign owner, call escalation, and open correction.

## Explicitly Do Not Build Yet

- Microservices, autonomous dispatching, black-box supervisor scoring, real-time tracking without consent/governance, or generative AI action tools. These add failure modes before the operational record is reliable.
