// Checkpoint option lists; must match CheckpointController's validation.
export const CATEGORIES = ['NA', 'Logistics', 'A&D', 'Transportation', 'Guest Services'];
export const TYPES = ['dispatch', 'arrival', 'boarding', 'departure', 'handoff'];
export const CAPTURE_METHODS = ['manual', 'auto', 'gps', 'photo', 'signature'];

const CAPTURE_LABELS = { manual: 'Manual', auto: 'Auto', gps: 'GPS', photo: 'Photo', signature: 'Signature' };
const capitalise = (s) => (s ? s[0].toUpperCase() + s.slice(1) : '');

export const categoryLabel = (c) => (c === 'NA' ? 'N/A' : (c || 'N/A'));
export const typeLabel = (t) => capitalise(t);
export const captureLabel = (m) => CAPTURE_LABELS[m] ?? capitalise(m);

export const blankCheckpoint = () => ({
  name: '',
  description: '',
  category: 'Logistics',
  type: 'dispatch',
  capture_method: 'manual',
  requires_photo: false,
  requires_signature: false,
  requires_baggage_count: false,
  is_active: true,
});
