# Target Architecture

## Recommended Shape

Retain Laravel/Vue as a **modular monolith** with a separately versioned mobile API. Add bounded modules, asynchronous processing, operational audit, and a field synchronization contract rather than adopting microservices.

```text
Web console / Mobile apps
        |                    
Inertia controllers       Mobile API v1/v2
        |                    |
Authorization + active event + functional area + provider scope
        |
Planning module ---- Lifecycle module ---- Exception module
        |                    |                    |
Template versions     Job/checkpoint snapshots     Owners/SLA/actions
        |                    |                    |
Conflict engine ---- Audit/time evidence ---- Notification outbox
        |                    |                    |
MySQL transactional core ---- Queue worker ---- Push/email/integrations
        |
Read models: Day Board, resource schedule, KPI/control center
```

## Component Responsibilities

| Component | Responsibility |
| --- | --- |
| Planning | Versioned templates, deterministic plan keys, validation, generation/revision impact. |
| Lifecycle | The sole command path for job/checkpoint/crew/status changes; dependency enforcement and audit. |
| Time evidence | Baseline/revised/event/receipt/sync facts and correction events. |
| Conflict engine | Read-only feasibility evaluation and explainable recommendations. |
| Exception module | Incident, severity, owner, status, response SLA, resolution and escalation. |
| Mobile sync | Delta feed, idempotent append commands, conflict responses, attachment sync. |
| Outbox/worker | Durable notifications, imports, reports, integrations, retries and failure monitoring. |
| Read models | Scoped operational projections only; never use mock data in production control views. |

## Security Boundaries

- Route all commands through policy plus event, functional-area, provider, and supervised-by checks as applicable.
- Use private evidence storage, short-lived authorized downloads, audit access, malware/media inspection, retention policy, and least-privilege worker credentials.
- Treat device time/location/signatures as evidence with provenance, not inherently authoritative.

## Migration Considerations

1. Inventory and retire legacy routes before creating API v2.
2. Add nullable new time/sync/version columns and backfill legacy facts with `source=legacy` rather than fabricating accuracy.
3. Publish template versions while preserving current templates as version 1; generated jobs remain immutable snapshots.
4. Introduce outbox records within existing transactions, then deploy monitored workers before moving slow work.
5. Run old/new dashboard calculations side by side until reconciliation is accepted.

No service extraction is recommended until observed volume, availability, or independent team ownership justifies it.
