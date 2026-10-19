import axios from 'axios';

const LOCATION_KINDS = ['transfer', 'training', 'daily_ops'];

/**
 * The duplicate check for one template leg, or null when the leg can't be checked:
 * flight legs need a flight, match legs a match, the rest both locations.
 * `base` is { team_id, window_start, flight_id?, match_id? }.
 */
export function legCheck(leg, { flight_id, match_id, ...base }) {
  const kind = leg.leg_type?.toLowerCase();
  if (!kind) return null;

  const check = { ...base, kind };
  if (kind === 'arrival' || kind === 'departure') {
    return flight_id ? { ...check, flight_id } : null;
  }
  if (kind === 'match') {
    return match_id ? { ...check, match_id, checkpoint_template_id: leg.checkpoint_template_id || null } : null;
  }
  if (LOCATION_KINDS.includes(kind) && leg.from_location && leg.to_location) {
    return { ...check, from_location: leg.from_location, to_location: leg.to_location };
  }
  return null;
}

/**
 * Runs all checks in one request (POST /api/check-duplicate-bulk) and returns a
 * Map of owner -> the first duplicate found for it, in the order given.
 *
 * @param {Array<{ owner: string|number, check: object }>} items
 */
export async function findDuplicates(items) {
  const found = new Map();
  if (!items.length) return found;

  const { data } = await axios.post('/api/check-duplicate-bulk', {
    checks: items.map(({ check }, i) => ({ ...check, key: String(i) })),
  });
  items.forEach(({ owner }, i) => {
    const result = data.results?.[String(i)];
    if (result?.exists && !found.has(owner)) found.set(owner, result);
  });

  return found;
}
