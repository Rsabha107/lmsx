import { usePage } from '@inertiajs/vue3';

// Board pages (Dashboard, Schedule, Tracker) use their own words for job states;
// each points at the stored status it stands for.
const ALIASES = { scheduled: 'pending', done: 'completed', live: 'in-progress' };

/**
 * Status wording from the server (JobOperation::uiStatusLabels, shared on every
 * page). Pass the page's own label as a fallback for states the server doesn't name.
 */
export function useStatusLabels() {
  const page = usePage();

  function statusLabel(status, fallback) {
    const labels = page.props.jobStatusLabels ?? {};
    return labels[status] ?? labels[ALIASES[status]] ?? fallback ?? status;
  }

  return { statusLabel };
}
