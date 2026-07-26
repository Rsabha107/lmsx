// Maps this app's 3-letter team/country codes to the ISO 3166-1 alpha-2
// (or flag-icons extended) codes used by the `flag-icons` package classnames,
// e.g. flagIconClass('QAT') -> 'fi fi-qa'.
const CODE_MAP = {
  ARG: 'ar', AUT: 'at', BEL: 'be', BFA: 'bf', BOL: 'bo', BRA: 'br',
  CAN: 'ca', CHI: 'cl', CIV: 'ci', COL: 'co', CRC: 'cr', CRO: 'hr',
  CZE: 'cz', DEU: 'de', EGY: 'eg', ENG: 'gb-eng', ESP: 'es', FIJ: 'fj',
  FRA: 'fr', GBR: 'gb', HAI: 'ht', HON: 'hn', IDN: 'id', IRL: 'ie',
  ITA: 'it', JPN: 'jp', KOR: 'kr', KSA: 'sa', MAR: 'ma', MEX: 'mx',
  MLI: 'ml', NCL: 'nc', NOR: 'no', NZL: 'nz', PAN: 'pa', PAR: 'py',
  POR: 'pt', PRK: 'kp', QAT: 'qa', RSA: 'za', SEN: 'sn', SLV: 'sv',
  SUI: 'ch', TJK: 'tj', TUN: 'tn', UAE: 'ae', UGA: 'ug', USA: 'us',
  UZB: 'uz', VEN: 've', ZAM: 'zm',
};

export function flagIconClass(code) {
  if (!code) return null;
  const iso = CODE_MAP[code.toUpperCase()];
  return iso ? `fi fi-${iso}` : null;
}
