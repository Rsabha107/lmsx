// Is a flight's data current? Shared by Event Teams, the Day Board, the Jobs Queue, Job Detail and the
// header chip. Limits come from Settings → Conflict rules (page.props.flightStatus.limits).

const DAY_MIN = 1440;

/** Wall-clock "YYYY-MM-DD HH:mm" (no timezone) as minutes, so values compare with each other and server time. */
export function wallMinutes(value) {
  const m = String(value ?? '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/);
  if (!m) return null;
  return Date.UTC(+m[1], +m[2] - 1, +m[3], +(m[4] ?? 0), +(m[5] ?? 0)) / 60000;
}

export function agoText(minutes) {
  if (minutes === null) return '';
  if (minutes < 1) return 'just now';
  if (minutes < 60) return `${minutes} min ago`;
  if (minutes < DAY_MIN) return `${Math.round(minutes / 6) / 10} h ago`;
  return `${Math.round(minutes / DAY_MIN)} d ago`;
}

/** A FlightSummary (Jobs Queue, Day Board, Job Detail) in the shape flightFreshness reads. */
export function fromSummary(s) {
  return {
    flight_number: s?.flight_number,
    scheduled: s?.scheduled_wall,
    synced: s?.synced_at,
    attempted: s?.sync_attempted_at,
    error: s?.sync_error,
    done: ['landed', 'cancelled'].includes(s?.flight_status) || !!s?.actual_wall,
  };
}

/**
 * Refresh state of one flight: { tone: ok | warn | bad | idle, text, title }, or null for a bus / no number.
 * @param {{flight_number, scheduled, synced, attempted, error, done}} f
 */
export function flightFreshness(f, nowMin, limits = {}) {
  if (!f?.flight_number || f.flight_number === 'BUS') return null;
  const synced = wallMinutes(f.synced);
  const attempted = wallMinutes(f.attempted);
  const to = (wallMinutes(f.scheduled) ?? nowMin) - nowMin;
  const age = synced !== null ? nowMin - synced : null;
  const ago = agoText(age);

  if (f.error && (synced === null || (attempted ?? 0) > synced)) {
    return { tone: Math.abs(to) <= 720 && !f.done ? 'bad' : 'warn', text: 'Not found by OAG', title: `${f.error}${synced !== null ? ` Last good refresh ${ago}.` : ''}` };
  }
  if (synced === null) {
    const urgent = !f.done && to <= 720 && to >= -180;
    return { tone: urgent ? 'bad' : 'idle', text: 'Never refreshed', title: urgent ? 'Within 12 h and never refreshed from OAG.' : 'Refreshed automatically from 12 h before the flight; use ↻ to check it now.' };
  }
  const stale = !f.done && ((Math.abs(to) <= 180 && age > (limits.near_minutes ?? 60)) || (to > 180 && to <= 720 && age > (limits.far_hours ?? 6) * 60));
  return { tone: stale ? (Math.abs(to) <= 180 ? 'bad' : 'warn') : 'ok', text: `Updated ${ago}`, title: stale ? 'Older than the refresh limit; check that the scheduler is running.' : `Last refreshed from the flight source ${ago}.` };
}
