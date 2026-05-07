<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Audit Trail</h1>
        <p class="page-sub">All system actions · {{ $page.props.today }}</p>
      </div>
      <div class="page-header-actions" style="display: flex; gap: 8px;">
        <RefreshButton :only="['audit']" />
        <button class="btn btn--ghost btn--sm">
        <svg-icon name="download" :size="15" /> Export CSV
        </button>
      </div>
    </div>

    <div class="audit-card">
      <div class="audit-filters">
        <input v-model="search" type="text" class="search-input" placeholder="Filter by person, action, target…" />
        <select v-model="roleFilter" class="role-select">
          <option value="">All roles</option>
          <option v-for="r in roles" :key="r" :value="r">{{ r }}</option>
        </select>
      </div>

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
            <tr v-for="(row, i) in filtered" :key="i">
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
              <td class="target-cell">{{ row.target }}</td>
              <td class="meta-cell">{{ row.meta }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </app-layout>
</template>

<script setup>
import { ref, computed } from 'vue';
import AppLayout from '../Components/AppLayout.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import RefreshButton from '../Components/RefreshButton.vue';

const props = defineProps({
  audit: { type: Array, default: () => [] },
});

const search = ref('');
const roleFilter = ref('');

const roles = computed(() => [...new Set(props.audit.map(r => r.role))]);

const filtered = computed(() => {
  let rows = props.audit;
  if (roleFilter.value) rows = rows.filter(r => r.role === roleFilter.value);
  if (search.value) {
    const q = search.value.toLowerCase();
    rows = rows.filter(r =>
      r.who.toLowerCase().includes(q) ||
      r.action.toLowerCase().includes(q) ||
      r.target.toLowerCase().includes(q) ||
      r.meta.toLowerCase().includes(q)
    );
  }
  return rows;
});

function initials(name) {
  return name === 'System' ? 'SY' : name.split(/[\s.]+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
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
.search-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.12); }
.role-select {
  padding: 7px 10px; border-radius: 7px;
  border: 1px solid var(--border); background: var(--surface);
  color: var(--ink); font-size: 13px; font-family: inherit; cursor: pointer;
}

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
