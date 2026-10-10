/** Same list Crew Assignment offers: the movement's provider's supervisors, plus whoever is already on it. */
export function supervisorsFor(list, providerId, currentIds = []) {
  if (!providerId) return list;
  return list.filter((s) => s.fleet_provider_id === providerId || currentIds.includes(s.id));
}

export const personLabel = (p) => (p.job_title ? `${p.name} · ${p.job_title}` : p.name);
