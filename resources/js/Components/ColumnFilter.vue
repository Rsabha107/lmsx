<template>
  <div class="cf" :class="`cf--${align}`">
    <button
      ref="trigger"
      type="button"
      :class="['cf-trigger', { 'cf-trigger--active': isSorted || isFiltered }]"
      :aria-expanded="open"
      :title="`Sort or filter ${label}`"
      @click.stop="toggle"
    >
      <span>{{ label }}</span>
      <span :class="['cf-icons', { 'cf-icons--idle': !isSorted && !isFiltered }]">
        <span v-if="isSorted" class="cf-arrow">{{ sortDir === 'asc' ? '▲' : '▼' }}<sup v-if="showRank" class="cf-rank">{{ sortState.rank }}</sup></span>
        <svg-icon v-if="filterable" name="filter" :size="10" :class="['cf-funnel', { 'cf-funnel--on': isFiltered }]" />
        <span v-else-if="!isSorted" class="cf-arrow">⇅</span>
      </span>
    </button>

    <Teleport to="body">
      <div v-if="open" ref="pop" class="cf-pop" :style="popStyle" role="dialog" :aria-label="`${label} sort and filter`" @click.stop>
        <button type="button" :class="['cf-sort', { 'cf-sort--on': sortDir === 'asc' }]" @click="sortBy('asc')">
          <span class="cf-sort-icon">▲</span> {{ sortLabels.asc }}
        </button>
        <button type="button" :class="['cf-sort', { 'cf-sort--on': sortDir === 'desc' }]" @click="sortBy('desc')">
          <span class="cf-sort-icon">▼</span> {{ sortLabels.desc }}
        </button>

        <template v-if="filterable">
          <div class="cf-divider" />
          <input v-model="search" type="search" class="cf-search" placeholder="Search…" :aria-label="`Search ${label} values`" />
          <label class="cf-option cf-option--all">
            <input type="checkbox" :checked="allVisibleChecked" :indeterminate="someVisibleChecked && !allVisibleChecked" @change="toggleVisible" />
            (Select all)
          </label>
          <div class="cf-list">
            <label v-for="option in visibleOptions" :key="option" class="cf-option">
              <input type="checkbox" :checked="isChecked(option)" @change="toggleOption(option)" />
              <span class="cf-option-text">{{ option === '' ? '(Blanks)' : option }}</span>
              <span class="cf-option-count">
                {{ optionCounts.get(option) }} {{ countUnit }}{{ optionCounts.get(option) === 1 ? '' : 's' }}
              </span>
            </label>
            <div v-if="!visibleOptions.length" class="cf-empty">No matches</div>
          </div>
        </template>

        <div class="cf-divider" />
        <div class="cf-footer">
          <button type="button" class="cf-link" :disabled="!isSorted && !isFiltered" @click="clear">Clear</button>
          <button type="button" class="cf-done" @click="open = false">Done</button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, computed, watch, onUnmounted, nextTick } from 'vue';
import SvgIcon from './SvgIcon.vue';

const props = defineProps({
  label: { type: String, required: true },
  column: { type: String, required: true },
  // The object returned by useColumnFilters().
  state: { type: Object, required: true },
  // Rows before any header filter, so every value can be offered.
  rows: { type: Array, default: () => [] },
  filterable: { type: Boolean, default: true },
  sortLabels: { type: Object, default: () => ({ asc: 'Sort A → Z', desc: 'Sort Z → A' }) },
  align: { type: String, default: 'left' }, // 'left' | 'center' | 'right'
  // Counts distinct countBy(row) keys behind each value (e.g. plans per date); rows when omitted.
  countBy: { type: Function, default: null },
  countUnit: { type: String, default: 'row' },
});

const open = ref(false);
const search = ref('');
const trigger = ref(null);
const pop = ref(null);
const popStyle = ref({});

const sortState = computed(() => props.state.sortOfColumn(props.column));
const sortDir = computed(() => sortState.value?.dir ?? null);
const isSorted = computed(() => !!sortDir.value);
// The rank only matters once more than one column is sorted.
const showRank = computed(() => isSorted.value && props.state.sort.value.length > 1);
const allowed = computed(() => props.state.filters[props.column] ?? null);
const isFiltered = computed(() => !!allowed.value);

const optionEntries = computed(() =>
  open.value && props.filterable ? props.state.options(props.rows, props.column, props.countBy) : []);
const allOptions = computed(() => optionEntries.value.map((o) => o.value));
const optionCounts = computed(() => new Map(optionEntries.value.map((o) => [o.value, o.count])));
const visibleOptions = computed(() => {
  const q = search.value.trim().toLowerCase();
  return q ? allOptions.value.filter((o) => String(o).toLowerCase().includes(q)) : allOptions.value;
});

const isChecked = (option) => !allowed.value || allowed.value.has(option);
const allVisibleChecked = computed(() => visibleOptions.value.length > 0 && visibleOptions.value.every(isChecked));
const someVisibleChecked = computed(() => visibleOptions.value.some(isChecked));

function commit(set) {
  // Everything ticked is the same as no filter, so the column stops showing as filtered.
  const complete = allOptions.value.every((o) => set.has(o));
  props.state.setFilter(props.column, complete ? null : set);
}

function toggleOption(option) {
  const set = new Set(allowed.value ?? allOptions.value);
  set.has(option) ? set.delete(option) : set.add(option);
  commit(set);
}

function toggleVisible() {
  const set = new Set(allowed.value ?? allOptions.value);
  const check = !allVisibleChecked.value;
  for (const option of visibleOptions.value) check ? set.add(option) : set.delete(option);
  commit(set);
}

function sortBy(dir) {
  props.state.setSort(props.column, sortDir.value === dir ? null : dir);
}

function clear() {
  if (isSorted.value) props.state.setSort(props.column, null);
  props.state.setFilter(props.column, null);
  search.value = '';
}

function place() {
  const rect = trigger.value?.getBoundingClientRect();
  if (!rect) return;
  const width = 240;
  const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));
  popStyle.value = { top: `${rect.bottom + 4}px`, left: `${left}px`, width: `${width}px` };
}

function toggle() {
  open.value = !open.value;
}

function onDocDown(e) {
  if (!pop.value?.contains(e.target) && !trigger.value?.contains(e.target)) open.value = false;
}
function onKey(e) {
  if (e.key === 'Escape') open.value = false;
}
// The popover is fixed-positioned, so it would drift away from a scrolled header.
function onScroll(e) {
  if (!pop.value?.contains(e.target)) open.value = false;
}

function listen(on) {
  const method = on ? 'addEventListener' : 'removeEventListener';
  document[method]('mousedown', onDocDown);
  document[method]('keydown', onKey);
  window[method]('scroll', onScroll, true);
  window[method]('resize', onScroll);
}

watch(open, async (value) => {
  if (value) {
    search.value = '';
    place();
    listen(true);
    await nextTick();
    pop.value?.querySelector('.cf-search, .cf-sort')?.focus();
  } else {
    listen(false);
  }
});

onUnmounted(() => listen(false));
</script>

<style scoped>
.cf { display: flex; min-width: 0; width: 100%; }
.cf--center { justify-content: center; }
.cf--right { justify-content: flex-end; }

.cf-trigger {
  display: inline-flex; align-items: center; gap: 4px;
  max-width: 100%;
  padding: 2px 4px; margin: -2px -4px;
  border: none; border-radius: 4px; background: none;
  font: inherit; color: inherit; letter-spacing: inherit; text-transform: inherit;
  cursor: pointer; white-space: nowrap;
}
.cf-trigger > span:first-child { overflow: hidden; text-overflow: ellipsis; }
.cf-trigger:hover { background: var(--border); color: var(--ink); }
.cf-trigger--active { color: var(--accent); }
.cf-arrow { font-size: 8px; line-height: 1; }
.cf-rank { font-size: 8px; margin-left: 1px; vertical-align: super; }
.cf-icons { display: inline-flex; align-items: center; gap: 3px; flex-shrink: 0; }
/* Zero width until in use, so the hint never steals room from the label in narrow columns. */
.cf-icons--idle { width: 0; overflow: visible; opacity: 0.35; }
.cf-trigger:hover .cf-icons--idle { opacity: 0.8; }
.cf-funnel { flex-shrink: 0; }
.cf-funnel--on { color: var(--accent); }

.cf-pop {
  position: fixed; z-index: 1100;
  display: flex; flex-direction: column;
  padding: 6px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 8px;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
  font-size: 12px; color: var(--ink);
}
.cf-sort {
  display: flex; align-items: center; gap: 8px;
  padding: 6px 8px; border: none; border-radius: 5px; background: none;
  font: inherit; color: inherit; text-align: left; cursor: pointer;
}
.cf-sort:hover { background: var(--panel); }
.cf-sort--on { background: var(--accent-soft); color: var(--accent); font-weight: 600; }
.cf-sort-icon { font-size: 9px; width: 10px; text-align: center; }
.cf-divider { height: 1px; margin: 6px -6px; background: var(--border); }
.cf-search {
  margin: 0 2px 4px; padding: 5px 8px;
  border: 1px solid var(--border); border-radius: 5px;
  background: var(--surface); color: var(--ink); font: inherit;
}
.cf-list { max-height: 220px; overflow-y: auto; }
.cf-option {
  display: flex; align-items: center; gap: 8px;
  padding: 4px 6px; border-radius: 4px; cursor: pointer;
}
.cf-option:hover { background: var(--panel); }
.cf-option input { margin: 0; cursor: pointer; }
.cf-option--all { font-weight: 600; }
.cf-option-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cf-option-count { margin-left: auto; padding-left: 8px; font-size: 11px; color: var(--ink3); white-space: nowrap; }
.cf-empty { padding: 6px; color: var(--ink3); font-style: italic; }
.cf-footer { display: flex; justify-content: space-between; align-items: center; padding: 0 2px; }
.cf-link {
  border: none; background: none; padding: 4px; font: inherit;
  color: var(--accent); cursor: pointer;
}
.cf-link:disabled { color: var(--ink4, var(--ink3)); cursor: default; opacity: 0.6; }
.cf-done {
  padding: 4px 12px; border: 1px solid var(--border); border-radius: 5px;
  background: var(--panel); color: var(--ink); font: inherit; font-weight: 600; cursor: pointer;
}
</style>
