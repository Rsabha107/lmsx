const vehicleName = (v) => v.code || v.plate_number || v.vehicle_type || `#${v.id}`;

// partnerField: what the second line of a row lists (the reference roster's "Driver · Vehicle").
export const ROLES = {
  driver: {
    idField: 'driver_id', nameField: 'driver', clashKey: 'driver', singular: 'Driver', plural: 'Drivers',
    partner: 'Vehicle', partnerField: 'vehicle', label: (d) => d.name, describe: (d) => d.phone ?? '',
  },
  vehicle: {
    idField: 'vehicle_id', nameField: 'vehicle', clashKey: 'vehicle', singular: 'Vehicle', plural: 'Vehicles',
    partner: 'Driver', partnerField: 'driver', label: vehicleName,
    describe: (v) => [v.vehicle_type, v.capacity ? `${v.capacity} seats` : null].filter(Boolean).join(' · '),
  },
  supervisor: {
    idField: 'field_supervisor_id', nameField: 'supervisor', clashKey: 'supervisor', singular: 'Supervisor', plural: 'Supervisors',
    partner: 'Teams', partnerField: 'team_code', label: (s) => s.name, describe: () => '',
  },
};

export const ROLE_LIST = Object.keys(ROLES);

export function todayKey() {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// Minutes from midnight of dayKey, read as text so no timezone shift applies.
export function minutesFrom(dayKey, stamp) {
  const m = stamp?.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/);
  if (!m) return null;
  const dayOffset = Math.round((new Date(`${m[1]}T00:00:00`) - new Date(`${dayKey}T00:00:00`)) / 86400000);
  return dayOffset * 1440 + Number(m[2]) * 60 + Number(m[3]);
}

export function clockLabel(minutes) {
  const t = ((minutes % 1440) + 1440) % 1440;
  return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
}

export function duration(minutes) {
  const h = Math.floor(minutes / 60);
  const m = Math.round(minutes % 60);
  if (!h) return `${m}m`;
  return m ? `${h}h ${m}m` : `${h}h`;
}

/** Total minutes covered by [start, end] pairs, counting overlaps once. */
export function mergedMinutes(spans) {
  let total = 0;
  let curStart = null;
  let curEnd = null;
  for (const [s, e] of [...spans].sort((a, b) => a[0] - b[0])) {
    if (curEnd === null || s > curEnd) {
      if (curEnd !== null) total += curEnd - curStart;
      curStart = s;
      curEnd = e;
    } else {
      curEnd = Math.max(curEnd, e);
    }
  }
  return curEnd === null ? total : total + curEnd - curStart;
}
