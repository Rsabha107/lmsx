import { ref, watch } from 'vue';

/**
 * Why each vehicle, driver and supervisor can't take a movement, from the same checks the
 * Conflicts tab uses (double-booking, turnaround, rest, seats). Pass a getter for the movement
 * id; `note(list, id)` is the " — reason" to append to an option, or '' when it is free.
 */
export function useCrewBusy(movementId) {
  const reasons = ref({ vehicles: {}, drivers: {}, supervisors: {} });
  let token = 0;

  async function load(id) {
    const mine = ++token;
    reasons.value = { vehicles: {}, drivers: {}, supervisors: {} };
    if (!id) return;

    try {
      const res = await fetch(`/movements/${id}/crew-options`, { headers: { Accept: 'application/json' } });
      if (!res.ok || mine !== token) return;
      const data = await res.json();
      const byId = (list) => Object.fromEntries((list ?? []).filter((o) => o.reason).map((o) => [o.id, o.reason]));
      reasons.value = { vehicles: byId(data.vehicles), drivers: byId(data.drivers), supervisors: byId(data.supervisors) };
    } catch {
      // Notes are a hint; the dropdowns work without them.
    }
  }

  watch(movementId, load, { immediate: true });

  const note = (list, id) => (reasons.value[list]?.[id] ? ` — ${reasons.value[list][id]}` : '');

  return { note, reload: () => load(movementId()) };
}
