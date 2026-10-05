import { ref, reactive, computed } from 'vue';

const isBlank = (v) => v === null || v === undefined || v === '';

function compare(a, b) {
  if (typeof a === 'number' && typeof b === 'number') return a - b;
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

/**
 * Excel-style header autofilters: one sort column plus a value whitelist per column.
 *
 * columns: { key: { value?: row => string (what the filter lists), sort?: row => string|number|null } }
 * Blank sort values always go last, whichever way the column is sorted.
 */
export function useColumnFilters(columns) {
  const sort = ref(null); // { key, dir: 'asc' | 'desc' }
  const filters = reactive({}); // key -> Set of allowed values

  const valueOf = (key, row) => columns[key].value?.(row) ?? '';
  const sortOf = (key, row) => (columns[key].sort ?? columns[key].value)?.(row);

  function filterRows(rows, exceptKey = null) {
    let out = rows;
    for (const [key, allowed] of Object.entries(filters)) {
      if (!allowed || key === exceptKey) continue;
      out = out.filter((row) => allowed.has(valueOf(key, row)));
    }
    return out;
  }

  function apply(rows) {
    const out = filterRows(rows);
    if (!sort.value) return out;

    const { key, dir } = sort.value;
    const sign = dir === 'desc' ? -1 : 1;
    return [...out].sort((a, b) => {
      const x = sortOf(key, a);
      const y = sortOf(key, b);
      if (isBlank(x) || isBlank(y)) return isBlank(x) === isBlank(y) ? 0 : isBlank(x) ? 1 : -1;
      return sign * compare(x, y);
    });
  }

  function setSort(key, dir) {
    sort.value = dir ? { key, dir } : null;
  }

  function setFilter(key, allowed) {
    if (allowed) filters[key] = allowed;
    else delete filters[key];
  }

  function clearAll() {
    sort.value = null;
    for (const key of Object.keys(filters)) delete filters[key];
  }

  const active = computed(() => !!sort.value || Object.keys(filters).length > 0);

  // Like Excel, a column lists the values left by the *other* columns' filters.
  // countBy counts distinct keys per value (e.g. plans per date); without it, rows are counted.
  function options(rows, key, countBy = null) {
    const seen = new Map();
    for (const row of filterRows(rows, key)) {
      const value = valueOf(key, row);
      if (!seen.has(value)) seen.set(value, { sort: sortOf(key, row), keys: new Set() });
      seen.get(value).keys.add(countBy ? countBy(row) : row);
    }
    return [...seen.entries()]
      .sort(([va, a], [vb, b]) => {
        if (isBlank(va) || isBlank(vb)) return isBlank(va) === isBlank(vb) ? 0 : isBlank(va) ? 1 : -1;
        return isBlank(a.sort) || isBlank(b.sort) ? compare(va, vb) : compare(a.sort, b.sort);
      })
      .map(([value, info]) => ({ value, count: info.keys.size }));
  }

  return { sort, filters, apply, options, setSort, setFilter, clearAll, active };
}
