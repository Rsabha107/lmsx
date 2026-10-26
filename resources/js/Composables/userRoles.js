// Role labels, one-line descriptions and chip colours for Setups → Users.
// Chips tint their own colour, so they work in light and dark themes.
const ROLES = {
  admin: { label: 'Admin', color: '#8a1538', desc: 'Full access, including setups' },
  agency: { label: 'Agency', color: '#047857', desc: 'Crew agency: assigns crew and fleet' },
  ground_control: { label: 'Ground control', color: '#1d4ed8', desc: 'Field supervisor, mobile app' },
  mobile_viewer: { label: 'Mobile viewer', color: '#4b5563', desc: 'Mobile app, view jobs only' },
  SecurityRole: { label: 'Security', color: '#92400e', desc: 'Roles & permissions only' },
  team_services: { label: 'Team Services', color: '#6d28d9', desc: 'Jobs in their functional area' },
  transport: { label: 'Transport', color: '#0e7490', desc: 'Jobs in their functional area' },
  venue_ops: { label: 'Venue Ops', color: '#c2410c', desc: 'Jobs in their functional area' },
};

export function roleLabel(name) {
  return ROLES[name]?.label ?? name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function roleDesc(name) {
  return ROLES[name]?.desc ?? 'Custom role';
}

export function roleChip(name) {
  const color = ROLES[name]?.color ?? '#4b5563';
  return { color, background: `color-mix(in srgb, ${color} 12%, transparent)` };
}

export function initials(name) {
  const p = String(name || '').trim().split(/\s+/);
  return (((p[0] || '')[0] || '') + ((p.length > 1 ? p[p.length - 1][0] : (p[0] || '')[1]) || '')).toUpperCase();
}

export const isEmail = (s) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(s).trim());
