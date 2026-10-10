import { ref, computed } from 'vue';
import { useStatusLabels } from './useStatusLabels';

const STATUS = {
  'in-progress': { tone: 'live',    label: 'In Progress' },
  'scheduled':   { tone: 'primary', label: 'Scheduled' },
  'delayed':     { tone: 'warn',    label: 'Delayed' },
  'done':        { tone: 'ok',      label: 'Done' },
};

/** Tone and label for a Movement Schedule row's status. */
export function useScheduleStatus() {
  const { statusLabel: sharedLabel } = useStatusLabels();

  return {
    statusTone: (s) => STATUS[s]?.tone ?? 'neutral',
    statusLabel: (s) => sharedLabel(s, STATUS[s]?.label),
  };
}

/** The All / In Progress / Scheduled / Delayed tabs over the schedule rows. */
export function useScheduleFilter(getSchedule) {
  const { statusLabel } = useScheduleStatus();

  const filters = computed(() => [
    { value: 'all', label: 'All' },
    ...['in-progress', 'scheduled', 'delayed'].map((value) => ({ value, label: statusLabel(value) })),
  ]);
  const activeFilter = ref('all');
  const filtered = computed(() => (activeFilter.value === 'all'
    ? getSchedule()
    : getSchedule().filter((m) => m.status === activeFilter.value)));

  return { filters, activeFilter, filtered };
}
