// Shared by the flight-change list and the Notifications card: old → new times and crew clash texts.

/** "Wed 14 Oct 05:40" → { day: 'Wed 14 Oct', time: '05:40' }. */
export function dayTime(value) {
  const m = String(value ?? '').match(/^(.*\S)\s+(\d{2}:\d{2})$/);
  return m ? { day: m[1], time: m[2] } : { day: null, time: value || null };
}

/** Old → new as { from, to, day }: the day is pulled out when both ends share it. Null when both are empty. */
export function timeSpan(from, to) {
  if (!from && !to) return null;
  const a = dayTime(from);
  const b = dayTime(to);
  const same = !!a.day && a.day === b.day;
  return { from: same ? a.time : from || '—', to: same ? b.time : to || '—', day: same ? a.day : null };
}

/** A conflict text "… — 35 minutes overlap." as { main, note }, the note shown beside its type. */
export function splitClash(text) {
  const t = String(text ?? '');
  const i = t.lastIndexOf(' — ');
  return i > 0 ? { main: t.slice(0, i), note: t.slice(i + 3).replace(/\.$/, '') } : { main: t, note: null };
}
