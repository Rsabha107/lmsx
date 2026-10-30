import { formatDate, jobBlocker } from './planFormat';

/**
 * Where a plan stands on the way from set-up to jobs. Shared by the plan page checklist and the plan list.
 * Daleel's App\Services\Ai\PlanReadiness uses the same rules; change both together.
 * Step states: 'done' | 'partial' (some way there) | 'warn' (needs a look) | 'todo'.
 *
 * @param {object} plan  a plan with its movements (vehicle_id, driver_id, field_supervisor_id, job_id, window_start)
 * @param {Array} openConflicts  the event's unaccepted conflicts ({ movement_ids })
 */
export function planReadiness(plan, openConflicts = []) {
  const movements = plan?.movements ?? [];
  const total = movements.length;
  const ids = new Set(movements.map((mv) => mv.id));
  const untimed = movements.filter((mv) => !mv.window_start).length;
  const conflicts = openConflicts.filter((c) => (c.movement_ids ?? []).some((id) => ids.has(id))).length;
  const crewed = movements.filter((mv) => mv.vehicle_id && mv.driver_id && mv.field_supervisor_id).length;
  const jobs = movements.filter((mv) => mv.job_id).length;
  // Same rule as the Generate jobs button: a vehicle and a supervisor, and a flight for arrivals / departures.
  const noFlight = movements.filter((mv) => !mv.job_id && jobBlocker(mv)).length;
  const readyForJobs = movements.filter((mv) => !mv.job_id && mv.vehicle_id && mv.field_supervisor_id && !jobBlocker(mv)).length;
  const share = (n) => (n === total ? 'done' : (n ? 'partial' : 'todo'));
  const none = !total;

  const steps = [
    {
      key: 'setup',
      label: 'Team, date & template',
      state: 'done',
      detail: [plan?.movement_template?.name ?? 'Blank plan', formatDate(plan?.date)].filter(Boolean).join(' · '),
    },
    {
      key: 'movements',
      label: 'Review movements',
      state: none ? 'todo' : (untimed ? 'warn' : 'done'),
      detail: none
        ? 'No movements yet'
        : `${total} movement${total === 1 ? '' : 's'}${untimed ? ` · ${untimed} without a time` : ''}`,
    },
    {
      key: 'conflicts',
      label: 'Resolve conflicts',
      state: none ? 'todo' : (conflicts ? 'warn' : 'done'),
      detail: none ? 'Nothing to check yet' : (conflicts ? `${conflicts} open` : 'None open'),
    },
    {
      key: 'crew',
      label: 'Assign crew',
      state: none ? 'todo' : share(crewed),
      detail: none ? '—' : `${crewed} / ${total} crewed`,
    },
    {
      key: 'jobs',
      label: 'Generate jobs',
      state: none ? 'todo' : (noFlight && !readyForJobs && jobs < total ? 'warn' : share(jobs)),
      detail: none ? '—' : `${jobs} / ${total} generated${readyForJobs ? ` · ${readyForJobs} ready` : ''}${noFlight ? ` · ${noFlight} without a flight` : ''}`,
    },
  ];

  return {
    steps,
    done: steps.filter((s) => s.state === 'done').length,
    next: steps.find((s) => s.state !== 'done') ?? null,
    total,
    conflicts,
    readyForJobs,
  };
}
