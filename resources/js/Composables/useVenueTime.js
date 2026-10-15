// Venue wall-clock helpers: times are Qatar local, compared as plain text so the browser's timezone never applies.
export const VENUE_TIMEZONE = 'Asia/Qatar';

/** Today's date at the venue, 'YYYY-MM-DD'. */
export function venueToday() {
  return new Intl.DateTimeFormat('en-CA', { timeZone: VENUE_TIMEZONE }).format(new Date());
}

/** Minutes since the epoch for a 'YYYY-MM-DD[T ]HH:mm' wall-clock string, or null. */
export function wallMinutes(value) {
  const m = value?.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
  return m ? Date.UTC(+m[1], m[2] - 1, +m[3], +m[4], +m[5]) / 60000 : null;
}

/**
 * Typed "HH:mm" minus the planned wall-clock time, in minutes, using the server's rule:
 * the venue's today, or the next day when planned from 18:00 and done before 06:00.
 */
export function varianceFromPlan(plannedLocal, typed, shiftMinutes = 0) {
  const planned = wallMinutes(plannedLocal);
  const t = typed?.match(/^(\d{1,2}):(\d{2})/);
  if (planned === null || !t) return null;

  const plannedAt = planned + shiftMinutes;
  const plannedHour = Math.floor((((plannedAt % 1440) + 1440) % 1440) / 60);
  let actual = wallMinutes(`${venueToday()} ${t[1].padStart(2, '0')}:${t[2]}`);
  if (plannedHour >= 18 && +t[1] < 6) actual += 1440;
  return actual - plannedAt;
}
