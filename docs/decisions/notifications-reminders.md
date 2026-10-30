# Notifications and reminders (plan, not built)

Status: proposed, 2026-10-16. Phase 2 (mobile push) built first on 2026-10-16 with three triggers: added to / taken off a job, job re-timed, job starts in 60 / 15 min. Phases 1, 3 and 4 not built.

## Why

Worth building, kept narrow: the payoff is field supervisors not missing checkpoints and the agency not leaving movements uncrewed. Everything else risks noise.

What exists today:
- `NotificationFeedService`: a bell feed computed on the fly (late checkpoints, issues, delayed movements, agency crew changes). Nothing is stored or pushed.
- `DailySummaryMail`.
- Queue on the `database` driver.
- Scheduler runs only `flights:sync` (every 30 min, `routes/console.php`).
- The mobile app (`pma_mobile`) has no push or local notifications.

## Who needs it

| Who | Reminder / alert | Channel |
|---|---|---|
| **Field supervisors** (`ground_control`, mobile) | Job starts in 60 / 15 min | Push + reminder scheduled on the phone |
| | Next checkpoint due in 10 min, or overdue by 10 min | Reminder scheduled on the phone (works without signal) |
| | Added to / removed from a job; job re-timed after a flight change | Push |
| **Agency** (GWC lead supervisors) | Tomorrow's movements missing vehicle, driver or supervisor (e.g. 18:00 the day before) | Email + bell |
| | New crew clash or driver duty-hours breach | Bell |
| | Supervisor hasn't started a job by its first checkpoint (escalation) | Push / bell |
| **Ops / admins** (console) | Checkpoint late beyond a threshold; issue reported | Bell, optional email |
| | Flight delay picked up by `flights:sync` that re-timed jobs | Bell |
| | Next 24h: movements without crew, draft plans with 0 movements | Daily digest (extend `DailySummaryMail`) |
| **Duty roster staff** | Shift tomorrow; shifts with nobody assigned | Email |

Escalation chain for a missed checkpoint: supervisor at +10 min, agency lead at +20, ops at +30.

## Design

1. **Rule scan every minute, not a table of pre-planned reminders.**
   - Job times change constantly (flight edits, `JobGenerationService::rescheduleJob`), so stored reminders would need constant moving and cancelling.
   - `reminders:run` (scheduled every minute) asks each rule what is due now (e.g. "checkpoint due in 10 min, not done").
   - A `notification_log` table with a unique key per rule + item + recipient sends each reminder once. Self-correcting when times change.
   - Times are venue wall-clock and `now()` is Doha (`APP_TIMEZONE=Asia/Qatar`), so comparisons stay simple.
2. **Laravel Notifications** with channels:
   - `database`: stored bell items; move the current computed feed onto these.
   - `mail`.
   - `fcm`: push to the mobile app.
   - SMS/WhatsApp only if a real need appears.
3. **Mobile app**
   - Push: Firebase Messaging, `user_devices` table (token, platform, last_seen) filled via `POST /api/mobile/devices`; delete the token on logout; tapping opens the job.
   - Phone-scheduled reminders: the app schedules its own reminders from the synced job list (`flutter_local_notifications`), so they fire with no signal (stadiums, airport).
4. **Preferences**: defaults per role, on/off per notification type per user, scoped to the event.
5. **Noise control**: group bursts ("5 checkpoints late"), quiet hours for email, never notify someone about their own action.
6. **Security and audit**: push payloads carry only the job reference and a short message, no personal data. Every send is logged in `notification_log`. Rule settings changes go through `AuditLog::change()`.

## Phases

| Phase | Scope |
|---|---|
| **1. Server, bell and email** | `notification_log`, `reminders:run` on the scheduler, 4 rules: agency missing crew, late checkpoint, issue reported, daily digest. Stored bell items, basic preferences, feature tests. |
| **2. Mobile push** | Firebase project, `user_devices`, supervisor rules (job starting, crew change, re-timed job), tap opens the job. |
| **3. Offline reminders and escalation** | Phone-scheduled checkpoint reminders; the +10 / +20 / +30 escalation. |
| **4. Extras** | Duty roster shift reminders, weekly digest, SMS/WhatsApp if needed. |

## Decisions and checks before starting

1. **Stage scheduler and queue worker**: confirm they actually run on stage (Azure App Service). If `flights:sync` isn't running there, nothing scheduled will. Needs a WebJob or a `schedule:work` / `queue:work` process.
2. **Push**: create a Firebase project for Android; iOS would also need an Apple push key.
3. **Email**: pick a mail provider for stage (`MAIL_MAILER`).
4. **Thresholds and recipients**: agree the lead times (60 / 15 / 10 min) and who receives escalations.
