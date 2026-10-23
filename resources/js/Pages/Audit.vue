<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Audit Trail</h1>
        <p class="page-sub">All system actions · {{ $page.props.today }}</p>
      </div>
      <div class="page-header-actions" style="display: flex; gap: 8px;">
        <RefreshButton :only="['audit', 'total', 'roles']" />
        <a class="btn btn--ghost btn--sm" :href="exportUrl" :title="`Download all ${total} matching entries`">
          <svg-icon name="download" :size="15" /> Export Excel
        </a>
      </div>
    </div>

    <div class="audit-card">
      <div class="audit-filters">
        <input v-model="search" type="text" class="search-input" placeholder="Filter by person, action, target…" aria-label="Search the audit trail" />
        <select v-model="roleFilter" class="role-select" aria-label="Role">
          <option value="">All roles</option>
          <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
        </select>
        <label class="date-field">From <input v-model="from" type="date" class="date-input" aria-label="From date" /></label>
        <label class="date-field">To <input v-model="to" type="date" class="date-input" aria-label="To date" /></label>
        <button v-if="hasFilters" type="button" class="btn btn--ghost btn--sm" @click="clearFilters">Clear</button>
      </div>
      <p class="audit-count">
        <template v-if="total > audit.length">Showing the latest {{ audit.length }} of {{ total }} matching entries. Export Excel downloads all of them.</template>
        <template v-else>{{ total }} matching entr{{ total === 1 ? 'y' : 'ies' }}.</template>
      </p>

      <div class="audit-table-wrap">
        <table class="audit-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Who</th>
              <th>Action</th>
              <th>Target</th>
              <th>Note</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!audit.length"><td colspan="5" class="empty-cell">No entries match these filters.</td></tr>
            <tr v-for="(row, i) in audit" :key="i">
              <td class="mono">{{ row.t }}</td>
              <td>
                <div class="who-cell">
                  <div class="who-avatar">{{ initials(row.who) }}</div>
                  <div>
                    <div class="who-name">{{ row.who }}</div>
                    <div class="who-role">{{ row.role }}</div>
                  </div>
                </div>
              </td>
              <td><span class="action-badge">{{ row.action }}</span></td>
              <td class="target-cell">{{ row.target }}<div v-if="row.event" class="event-tag">{{ row.event }}</div></td>
              <td class="meta-cell">{{ row.meta }}<div v-if="row.ip" class="ip-tag">IP {{ row.ip }}</div></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  audit: { type: Array, default: () => [] },
  total: { type: Number, default: 0 },
  filters: { type: Object, default: () => ({}) },
  roles: { type: Array, default: () => [] },
});

// Filtered on the server, so the count and the export cover the whole log, not just the rows shown.
const search = ref(props.filters.search ?? '');
const roleFilter = ref(props.filters.role ?? '');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

const query = computed(() => Object.fromEntries(
  Object.entries({ search: search.value.trim(), role: roleFilter.value, from: from.value, to: to.value }).filter(([, v]) => v),
));
const hasFilters = computed(() => Object.keys(query.value).length > 0);
const exportUrl = computed(() => `/audit/export?${new URLSearchParams(query.value)}`);

let timer;
watch(query, (q) => {
  clearTimeout(timer);
  timer = setTimeout(() => {
    router.get('/audit', q, { preserveState: true, preserveScroll: true, replace: true, only: ['audit', 'total', 'filters'] });
  }, 300);
});

function clearFilters() {
  search.value = '';
  roleFilter.value = '';
  from.value = '';
  to.value = '';
}

function initials(name) {
  return !name || name === 'System' ? 'SY' : name.split(/[\s.]+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
}
</script>

<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.btn {
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid transparent;
}
.btn--sm { padding: 6px 12px; }
.btn--ghost { background: none; border-color: var(--border); color: var(--ink3); }
.btn--ghost:hover { background: var(--panel); color: var(--ink); }

.audit-card {
  background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow: hidden;
}
.audit-filters {
  display: flex; gap: 10px; padding: 12px 16px;
  border-bottom: 1px solid var(--border); flex-wrap: wrap;
}
.search-input {
  padding: 7px 12px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--ink); font-size: 13px; flex: 1; min-width: 180px; font-family: inherit;
}
.search-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-ring); }
.role-select {
  padding: 7px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--ink); font-size: 13px; font-family: inherit; cursor: pointer;
}
.date-field { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--ink3); }
.date-input {
  padding: 6px 8px; border-radius: 7px; border: 1px solid var(--border);
  background: var(--surface); color: var(--ink); font-size: 13px; font-family: inherit;
}
.audit-count { margin: 0; padding: 8px 16px; font-size: 12px; color: var(--ink3); border-bottom: 1px solid var(--border); }
.btn { text-decoration: none; }
.empty-cell { text-align: center; color: var(--ink3); padding: 24px !important; }
.event-tag, .ip-tag { margin-top: 2px; font-size: 11px; font-style: normal; color: var(--ink4); }
.ip-tag { font-family: var(--font-mono, monospace); }

.audit-table-wrap { overflow-x: auto; }
.audit-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.audit-table th {
  padding: 8px 14px; text-align: left;
  font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--ink3); border-bottom: 1px solid var(--border); background: var(--panel);
  white-space: nowrap;
}
.audit-table td { padding: 11px 14px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.audit-table tr:last-child td { border-bottom: none; }
.audit-table tr:hover td { background: var(--panel); }

.mono { font-family: var(--font-mono, monospace); font-size: 12px; color: var(--ink3); }

.who-cell { display: flex; align-items: center; gap: 10px; }
.who-avatar {
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 10px; font-weight: 700; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
}
.who-name { font-weight: 600; color: var(--ink); font-size: 13px; }
.who-role { font-size: 11px; color: var(--ink4); }

.action-badge { font-weight: 600; color: var(--accent-fg); }
.target-cell { color: var(--ink2); }
.meta-cell { color: var(--ink3); font-size: 12.5px; font-style: italic; }
</style>
