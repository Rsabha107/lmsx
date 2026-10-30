# Multiple vehicles on one job

**Status:** Option A implemented (2026-10-05). Option B kept for future consideration.

## Need
A single team movement (e.g. an arrival) may need more than one vehicle/driver, such as a coach plus a baggage truck.

## Option A — extra units on the movement (implemented)
- Table `movement_units (movement_id, vehicle_id, driver_id, position)`.
- `movements.vehicle_id` / `driver_id` stay as the lead unit; jobs read extras through their movement.
- One supervisor and one checkpoint flow per job.
- Conflict detection, capacity, driver duty/day status and auto-assign treat extra units like the lead.
- Edited on Crew Assignment (table and roster panel) and in Planning's Edit Movement modal.

Known gaps: the Crew Assignment day-timeline/week roster lanes, and the job Override modal, still show and change only the lead vehicle/driver.

## Extra supervisors (implemented 2026-10-05)
Table `movement_supervisors (movement_id, user_id)`, not tied to any vehicle. The lead `field_supervisor_id` stays accountable and required for job generation. Extra supervisors see and work the job in the mobile app (`JobOperation::supervisedBy()`), and count in supervisor clash checks and the Resource Schedule.

## Option B — split into linked movements (not built)
A "Split → N vehicles" action that clones the movement N times, linked by a `parent_movement_id` or group code.

- Each piece is a normal movement with its own job, checkpoints, supervisor and mobile flow, and almost no new logic is needed.
- Suits trucks that leave at **different times**, take **different routes**, or need **their own supervisor/sign-offs**.
- Costs: one arrival becomes N jobs, so dashboard job counts go up and passengers and bags must be split between the jobs.

Switch to (or add) B if operations need per-vehicle timings or per-vehicle checkpoint sign-off.
