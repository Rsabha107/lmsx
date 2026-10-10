# Security Assessment and Remediation Matrix

Scope: static defensive review only. Runtime infrastructure, APK implementation, WAF, and production values were not inspected. No secrets or personal data were accessed.

## Verified Findings

| Priority | Finding and evidence | Operational impact | Remediation |
| --- | --- | --- | --- |
| P0 | `routes/web.php:60-61` loads `routes/EXAMPLE_ROUTES.php`. Job and checkpoint write routes use only `auth`/`auth:sanctum` (`EXAMPLE_ROUTES.php:20-38`); `JobOperationController.php:77-301` has no policy/object authorization. | Any authenticated user/token can potentially read or alter unrelated jobs/checkpoints. | Remove legacy routes immediately or apply policy authorization, active-event/functional-area/assignment checks, scoped bindings, and tests for every denial case. |
| P1 | Trusted proxy default is `*`, accepting forwarded IP/host/protocol headers (`bootstrap/app.php:24-34`). | If origin is reachable, attackers can spoof client IP, host, scheme, and undermine rate limits/reset URL trust. | Restrict proxies to known CIDRs, set trusted hosts, block direct origin ingress, and verify at deployment. |
| P1 | Authenticated mobile endpoints, including 10 MB uploads, have no route limiter (`routes/api.php:21-43`; `MobileJobController.php:117-128`). | A compromised token can consume storage/CPU/bandwidth. | Apply named per-token/user and upload limiters; quotas, observability, and abuse response. |
| P1 | Dependency scans: Composer reports 38 advisories across 11 packages; npm production audit reports 8 findings, 7 high. | Known framework/client vulnerabilities remain in installed graph. | Upgrade within tested constraints; use lockfile review, rerun audits, and record residual advisories. Do not blindly update major versions. |
| P2 | Event list reads all events and related data despite `User::canAccessEvent()` defining assignment scope (`routes/web/events.php:12-15`; `EventsController.php:27-60`; `User.php:71-86`). | Cross-event operational disclosure if directory access is not deliberate. | Decide policy; constrain index/data or explicitly document global-directory role. Add test. |
| P2 | Durable audit coverage focuses on lifecycle/conflicts, not users, roles, events, fleet, templates, imports (`AuditLog.php:41-62`; `UserController.php:45-89`; `RoleController.php:13-60`). | Incident and change reconstruction is incomplete. | Audit privileged/configuration changes with before/after summaries and actor/request metadata. |
| P2 | Deployment hardening is optional: `.env.example` omits secure session cookie, trusted proxy/host, and Sanctum expiry; example enables debug. | Misconfigured production could expose debug/session/token risk. | Provide a production checklist and fail-fast deployment validation. |
| P3 | Request payloads are logged wholesale in planning/template controllers (`PlanManagementController.php:408-410,596-598`; `CheckpointController.php:65-81`). | Operational notes can become over-retained log data. | Log allow-listed metadata/correlation IDs, define retention/access controls. |

## Positive Controls

- Maintained mobile API restricts event, functional area, and supervised-by (including extra supervisor) access (`app/Http/Controllers/Api/Concerns/ScopesMobileAccess.php:24-145`), with coverage in `tests/Feature/MobileAssignedJobsTest.php:34-102`.
- Mobile evidence is private, server-named, validated, and served through authorization (`CheckpointUploadService.php:16-103`; `MobileJobController.php:216-234`).
- Execution evidence/requiredness, row locks, audit entries, CSRF web middleware, role permissions, provider scopes, and agency restriction are meaningful defenses.

## Security Release Gate

Do not production-release until P0 is fixed and independently regression-tested. Before release, also patch exploitable dependencies, set secure production environment values, apply mobile/API rate limits, verify HTTPS/TLS/HSTS and backup access, and conduct authenticated authorization tests against all state-changing routes.
