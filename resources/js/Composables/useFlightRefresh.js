import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from '@/Composables/useToast';

// Refreshes flights from OAG (then AviationStack) through the per-event bulk route and keeps the merged result
// for FlightRefreshResult. A refresh can re-time plans and jobs, so the result lists what changed.
// Needs `events.manage` (the route's permission); callers show their button only then.
export function useFlightRefresh({ reloadOnly = null } = {}) {
  const { success: toastOk, error: toastError } = useToast();
  const refreshing = ref(false);
  const refreshingIds = ref([]);
  const result = ref(null);

  /**
   * @param {Array<{id: number, event_id: number}>} flights
   * @param {{ single?: boolean }} options  one flight: a short message unless its new time moved a plan or job
   */
  async function refresh(flights, { single = false } = {}) {
    const byEvent = new Map();
    for (const f of flights) {
      if (!f?.id || !f?.event_id) continue;
      if (!byEvent.has(f.event_id)) byEvent.set(f.event_id, []);
      if (!byEvent.get(f.event_id).includes(f.id)) byEvent.get(f.event_id).push(f.id);
    }
    if (!byEvent.size) return null;
    const ids = [...byEvent.values()].flat();
    refreshing.value = true;
    refreshingIds.value = [...refreshingIds.value, ...ids];

    const merged = { refreshed: 0, failed: 0, results: [], summary: {} };
    try {
      const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
      // The route takes 60 flights at a time (and 6 calls a minute): larger sets go in batches.
      const batches = [];
      for (const [eventId, eventIds] of byEvent) {
        for (let i = 0; i < eventIds.length; i += 60) batches.push([eventId, eventIds.slice(i, i + 60)]);
      }
      for (const [eventId, batch] of batches) {
        const response = await fetch(`/events/${eventId}/flights/sync-bulk`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({ flight_ids: batch }),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          toastError(response.status === 429 ? 'Too many refreshes; wait a minute.' : (data.message || 'Could not refresh the flights.'));
          if (!merged.results.length) return null;
          break;
        }
        merged.refreshed += data.refreshed ?? 0;
        merged.failed += data.failed ?? 0;
        merged.results.push(...(data.results ?? []));
        for (const [key, n] of Object.entries(data.summary ?? {})) merged.summary[key] = (merged.summary[key] ?? 0) + n;
      }

      const moved = merged.results.reduce((n, r) => n + (r.moved_movements || 0), 0);
      if (single && !moved) {
        const r = merged.results[0];
        r?.ok ? toastOk(`${r.flight_number} updated from ${r.source === 'oag' ? 'OAG' : 'AviationStack'}.`) : toastError(`${r?.flight_number}: ${r?.error ?? 'not found'}`);
      } else {
        result.value = merged;
      }
      router.reload(reloadOnly ? { only: reloadOnly } : {});
      return merged;
    } catch (e) {
      toastError('Could not reach the server to refresh the flights.');
      return null;
    } finally {
      refreshing.value = false;
      refreshingIds.value = refreshingIds.value.filter((id) => !ids.includes(id));
    }
  }

  return { refresh, refreshing, refreshingIds, result };
}
