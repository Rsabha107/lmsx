/** Same list Crew Assignment offers: the movement's provider's supervisors, plus whoever is already on it. */
export function supervisorsFor(list, providerId, currentIds = []) {
  if (!providerId) return list;
  return list.filter((s) => s.fleet_provider_id === providerId || currentIds.includes(s.id));
}

const ID_FIELDS = { vehicle: 'vehicle_id', driver: 'driver_id', supervisor: 'field_supervisor_id' };

/**
 * Who may be offered for a role on a movement, as CrewEligibility rules on the server:
 * the movement's provider's fleet and people, plus whoever is already on it.
 * @param {'vehicle'|'driver'|'supervisor'} role
 */
export function crewOptions(list, role, mv) {
  if (!mv?.fleet_provider_id) return list;
  const owner = role === 'supervisor' ? 'fleet_provider_id' : 'provider_id';
  const idField = ID_FIELDS[role];
  const current = role === 'supervisor'
    ? [mv[idField], ...(mv.extra_supervisors ?? []).map((s) => s.id)]
    : [mv[idField], ...(mv.units ?? []).map((u) => u[idField])];
  return list.filter((o) => o[owner] === mv.fleet_provider_id || current.includes(o.id));
}

export const personLabel = (p) => (p.job_title ? `${p.name} · ${p.job_title}` : p.name);
