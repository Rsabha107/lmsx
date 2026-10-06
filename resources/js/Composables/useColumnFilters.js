import { ref, reactive, computed } from 'vue';

const isBlank = (v) => v === null || v === undefined || v === '';

function compare(a, b) {
  if (typeof a === 'number' && typeof b === 'number') return a - b;
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

/**
 * Excel-style header autofilters: multi-level sort plus a value whitelist per column.
 * Sorts compound in the order applied: the first sorted column wins, later ones break ties.
 *
 * columns: { key: { value?: row => string (what the filter lists), sort?: row => string|number|null } }
 * Blank sort values always go last, whichever way the column is sorted.
 */
export function useColumnFilters(columns) {
  const sort = ref([]); // [{ key, dir: 'asc' | 'desc' }], highest priority first
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
    if (!sort.value.length) return out;

    return [...out].sort((a, b) => {
      for (const { key, dir } of sort.value) {
        const x = sortOf(key, a);
        const y = sortOf(key, b);
        const diff = isBlank(x) || isBlank(y)
          ? (isBlank(x) === isBlank(y) ? 0 : isBlank(x) ? 1 : -1)
          : (dir === 'desc' ? -1 : 1) * compare(x, y);
        if (diff) return diff;
      }
      return 0;
    });
  }

  function setSort(key, dir) {
    const rest = sort.value.filter((s) => s.key !== key);
    const at = sort.value.findIndex((s) => s.key === key);
    if (!dir) sort.value = rest;
    else if (at === -1) sort.value = [...rest, { key, dir }];
    else sort.value = sort.value.map((s) => (s.key === key ? { key, dir } : s));
  }

  function sortOfColumn(key) {
    const at = sort.value.findIndex((s) => s.key === key);
    return at === -1 ? null : { dir: sort.value[at].dir, rank: at + 1 };
  }

  function setFilter(key, allowed) {
    if (allowed) filters[key] = allowed;
    else delete filters[key];
  }

  function clearAll() {
    sort.value = [];
    for (const key of Object.keys(filters)) delete filters[key];
  }

  const active = computed(() => sort.value.length > 0 || Object.keys(filters).length > 0);

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

  return { sort, filters, apply, options, setSort, sortOfColumn, setFilter, clearAll, active };
}
