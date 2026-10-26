// Shared by the checkpoint template list and editor.
export const TYPE_ORDER = ['arrival', 'departure', 'match', 'training', 'transfer', 'daily_ops'];

export const typeLabel = (t) => (t === 'daily_ops' ? 'Daily ops' : (t ? t[0].toUpperCase() + t.slice(1) : '—'));

export const isRequired = (cp) => cp.pivot?.is_required !== false && cp.pivot?.is_required !== 0;

export function fmtMinutes(m) {
  const n = Number(m) || 0;
  if (!n) return '0m';
  const h = Math.floor(n / 60);
  return h ? `${h}h${n % 60 ? ` ${n % 60}m` : ''}` : `${n}m`;
}

// A template's steps in order, each with its running clock ("T+25"); blank from the first step without minutes.
export function stepsOf(template) {
  let clock = 0;
  let known = true;
  return (template?.checkpoints ?? []).map((cp) => {
    const raw = cp.pivot?.estimated_minutes;
    const minutes = raw == null ? null : Number(raw);
    if (minutes == null) known = false;
    else clock += minutes;
    return { id: cp.id, name: cp.name, code: cp.code, required: isRequired(cp), minutes, clock: known ? `T+${clock}` : '' };
  });
}
