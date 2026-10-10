# Implementation Backlog

| ID | Priority | Task | Acceptance criteria | Dependencies |
| --- | --- | --- | --- | --- |
| SEC-01 | P0 | Remove or secure legacy job routes. | Every job/checkpoint read/write route has policy/event/assignment enforcement; denial tests cover other event, other supervisor, and token. | Route compatibility inventory |
| SEC-02 | P1 | Production configuration guard. | No wildcard proxy without explicit allow-list; trusted hosts, secure cookie, debug false, TLS and token expiry validated at deploy. | Deployment ownership |
| SEC-03 | P1 | Upgrade vulnerable Composer/npm graph. | Audits have no accepted exploitable advisory; changes have regression results and documented exceptions. | Compatibility testing |
| MOB-01 | P1 | Define versioned mobile sync API. | UUID operation IDs, idempotent replay, cursor/change version, retry response, and conflict contract. | Mobile source/release process |
| MOB-02 | P1 | Offline-first client execution. | Encrypted local queue/cache, evidence retry, user-visible sync state, restart/network-loss tests. | MOB-01 |
| TIME-01 | P1 | Add audit-grade timing evidence. | Baseline/revised/event/receipt/sync timestamps, timezone/source, reason taxonomy, append-only corrections. | API/mobile migration |
| OPS-01 | P1 | Server-enforce checkpoint dependencies. | Direct API cannot complete blocked checkpoint; parallel/conditional behavior is explicit and tested. | Template dependency design |
| PLAN-01 | P1 | Version templates and control regeneration. | Draft/publish/deprecate/version state, stable plan key, dry-run impact, explicit supersede/cancel handling. | TIME-01 recommended |
| OPS-02 | P1 | Build exception ownership workflow. | Severity, owner, SLA, acknowledgement, resolution and audit; Day Board action links. | SEC-01 |
| ARCH-01 | P2 | Add outbox/queue worker for slow/retryable work. | Async jobs are observable, retryable, idempotent, and do not mutate issued work unexpectedly. | Operational monitoring |
| UX-01 | P2 | Replace browser mobile fallbacks with scoped, prepared assignments. | Own/upcoming jobs, real profile, high-contrast next action, correction confirmation. | SEC-01 |
| DATA-01 | P2 | Make dashboards operationally truthful. | No mock data in live control screens; scoped queries, refresh state, alert ownership/actions. | OPS-02, TIME-01 |
| QA-01 | P2 | Add E2E/concurrency/load release suite. | Runs documented event scenario and load targets in CI/staging. | MOB-01, TIME-01 |
| AI-01 | P3 | Read-only operational briefing. | Scoped cited aggregates, no write tools, graceful provider failure, human review. | DATA-01, clean historical data |

## Sequencing

P0 must close first. SEC-02/03, MOB-01, TIME-01, OPS-01, PLAN-01 and OPS-02 form the pre-event foundation. Queue/control-center/advanced UX follow. AI is deliberately last.
