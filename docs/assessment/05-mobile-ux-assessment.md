# Mobile Field UX Assessment

## Assessment Boundary

The repository contains server API and browser mobile pages but no Flutter/Dart source, Android/iOS source, service worker, sync store, or push implementation. An APK exists, but its runtime behavior is **unverified**. Claims below distinguish server-supported workflow from unverified native UX.

## Verified Experience

- The maintained API exposes authenticated job list/detail, completion, evidence, issues, alerts, map, events, and profile (`routes/api.php:15-44`).
- Supervisor scoping is strong on the API (`ScopesMobileAccess.php:24-145`).
- Checkpoint completion can record notes, evidence, signature, baggage and GPS fields; required evidence is enforced (`MobileJobController.php:117-147`; `JobLifecycleService.php:192-247`).
- Browser detail supports ordered steps, touch signature canvas, image capture, evidence feedback, and responsive navigation (`resources/js/Pages/JobMobileDetail.vue:437-450,522-605,630-750`; `Components/AppLayout.vue:703-719`).

## Findings

| Priority | Finding | Operational consequence | Recommendation |
| --- | --- | --- | --- |
| P0 | Browser mobile routes/detail are not subject to own-job checks and `LmsController::jobMobileDetail()` does not authorize the job (`app/Http/Controllers/LmsController.php:564-681,1134-1178`). | A supervisor may view/complete another in-area job through web flow. | Remove browser execution route or align it with maintained API authorization. |
| P1 | No offline queue, operation ID, delta sync, retry/backoff, or duplicate reconciliation is evidenced. | Lost work or duplicate/conflict errors in airports/stadiums with weak signal. | Implement encrypted local job/cache store and append-only operation queue with UUID idempotency keys. |
| P1 | API permits out-of-order checkpoint submission. | Direct clients can skip operational sequence. | Enforce dependency state on server; return next permitted action. |
| P1 | Browser list shows only in-progress jobs and displays hard-coded supervisor name (`LmsController.php:483-503`; `JobsMobile.vue:16-19`). | Staff cannot prepare and may distrust the app. | Show today/upcoming assignments and authenticated profile. |
| P2 | GPS is accepted but no acquisition, accuracy, geofence, or privacy UX is evidenced. | Location evidence has low reliability/consent clarity. | Make location optional by checkpoint policy; record permission/accuracy/source and explain purpose. |
| P2 | Signature is image/typed-text rendering, not identity proof. | It is evidence of capture, not non-repudiation. | Label honestly; add signer name/role/attestation and hash/audit if legal proof is required. |
| P2 | No push/escalation delivery evidenced. | Critical assignment/changes can be missed. | Use push with in-app inbox, acknowledgement and SMS/phone escalation only for P0 operations. |

## Ideal Field Flow

1. Open app to a high-contrast “Now” card: team, route, vehicle, required next action, target time, and risk.
2. Tap one large action to complete; disclose only required fields and camera/signature when needed.
3. Show saved locally immediately, then “synced” or “needs attention” state without blocking work.
4. Prevent wrong-job actions through team/route/vehicle confirmation at start and server-enforced next checkpoint.
5. Make correction a visible, reasoned action with an audit trail, not an edit that replaces history.

Use large targets, daylight contrast, Arabic/English localisation based on actual operating languages, and one-handed testing with poor connectivity before acceptance.
