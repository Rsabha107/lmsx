// Design tokens + shared UI utilities for the LMS
export const LMS_TOKENS = {
  // neutrals
  bg: '#F7F8FA',
  surface: '#FFFFFF',
  panel: '#FBFBFD',
  border: '#E6E8EE',
  borderStrong: '#D7DAE2',
  ink: '#0F1724',
  ink2: '#384152',
  ink3: '#6B7486',
  ink4: '#9AA2B2',
  // accents
  primary: '#3B82F6',
  primarySoft: '#EAF2FE',
  live: '#22D3EE',
  liveSoft: '#E6FBFE',
  warn: '#F59E0B',
  warnSoft: '#FEF4E2',
  danger: '#EF4444',
  dangerSoft: '#FDECEC',
  ok: '#16A34A',
  okSoft: '#E6F6EC',
  // dark-mode overrides
  darkBg: '#0B1018',
  darkSurface: '#121822',
  darkPanel: '#0F1420',
  darkBorder: '#1F2634',
  darkBorderStrong: '#2A3242',
  darkInk: '#E8ECF3',
  darkInk2: '#B6BECD',
  darkInk3: '#7D8799',
}

export const useTones = () => {
  return {
    neutral: { bg: '#F1F3F7', fg: '#384152', dot: '#6B7486' },
    primary: { bg: LMS_TOKENS.primarySoft, fg: '#1D4ED8', dot: LMS_TOKENS.primary },
    live: { bg: LMS_TOKENS.liveSoft, fg: '#0E7490', dot: LMS_TOKENS.live },
    warn: { bg: LMS_TOKENS.warnSoft, fg: '#92400E', dot: LMS_TOKENS.warn },
    danger: { bg: LMS_TOKENS.dangerSoft, fg: '#991B1B', dot: LMS_TOKENS.danger },
    ok: { bg: LMS_TOKENS.okSoft, fg: '#166534', dot: LMS_TOKENS.ok },
    ghost: { bg: 'transparent', fg: '#6B7486', dot: '#6B7486' },
  }
}
