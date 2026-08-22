// Maps this app's 3-letter team/country codes to the ISO 3166-1 alpha-2
// (or flag-icons extended) codes used by the `flag-icons` package classnames,
// e.g. flagIconClass('QAT') -> 'fi fi-qa'.
const CODE_MAP = {
  ALG: 'dz', ARG: 'ar', AUS: 'au', AUT: 'at', BEL: 'be', BFA: 'bf', BOL: 'bo', BRA: 'br',
  CAN: 'ca', CHI: 'cl', CHN: 'cn', CIV: 'ci', CMR: 'cm', COL: 'co', CRC: 'cr', CRO: 'hr',
  CUB: 'cu', CZE: 'cz', DEU: 'de', DNK: 'dk', ECU: 'ec', EGY: 'eg', ENG: 'gb-eng', ESP: 'es', FIJ: 'fj',
  FRA: 'fr', GBR: 'gb', GRE: 'gr', HAI: 'ht', HON: 'hn', IDN: 'id', IRL: 'ie',
  ITA: 'it', JAM: 'jm', JPN: 'jp', KOR: 'kr', KSA: 'sa', MAR: 'ma', MEX: 'mx',
  MLI: 'ml', MNE: 'me', MOZ: 'mz', NCL: 'nc', NOR: 'no', NZL: 'nz', PAN: 'pa', PAR: 'py',
  POR: 'pt', PRK: 'kp', QAT: 'qa', ROU: 'ro', RSA: 'za', SEN: 'sn', SLV: 'sv', SRB: 'rs',
  SUI: 'ch', TAN: 'tz', TJK: 'tj', TUN: 'tn', UAE: 'ae', UGA: 'ug', URU: 'uy', USA: 'us',
  UZB: 'uz', VEN: 've', VIE: 'vn', ZAM: 'zm',
};

export function flagIconClass(code) {
  if (!code) return null;
  const iso = CODE_MAP[code.toUpperCase()];
  return iso ? `fi fi-${iso}` : null;
}
