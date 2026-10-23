<template>
  <app-layout>
    <div class="rb-head">
      <div>
        <h1 class="rb-title">Recycle bin</h1>
        <p class="rb-sub">Deleted jobs, plans, movements, templates and events stay here for {{ keepDays }} days and can be restored. Large deletes wait here for a second admin.</p>
      </div>
      <RefreshButton :only="['pending', 'deleted', 'history', 'earlier', 'pendingDeletions']" />
    </div>

    <div class="rb-tabs" role="tablist" aria-label="Recycle bin">
      <button v-for="t in tabs" :key="t.key" type="button" role="tab" :aria-selected="tab === t.key"
        :class="['rb-tab', { 'rb-tab--on': tab === t.key }]" @click="tab = t.key">
        {{ t.label }}<span v-if="t.count" :class="['rb-count', { 'rb-count--alert': t.key === 'pending' && t.count }]">{{ t.count }}</span>
      </button>
    </div>

    <!-- Waiting for approval -->
    <section v-if="tab === 'pending'" class="rb-list">
      <p v-if="!pending.length" class="rb-empty">No delete requests are waiting.</p>
      <article v-for="d in pending" :key="d.id" class="rb-card rb-card--pending">
        <div class="rb-card-main">
          <div class="rb-card-title">
            <span class="rb-kind">{{ d.label }}</span>
            <span v-if="d.event" class="rb-event">{{ d.event }}</span>
            <span :class="['rb-pill', d.trigger === 'brake' ? 'rb-pill--danger' : 'rb-pill--warn']">{{ d.trigger === 'brake' ? 'Mass-delete brake' : 'Needs approval' }}</span>
          </div>
          <p class="rb-meta">Asked by <strong>{{ d.requester }}</strong> on {{ d.requested_at }} · expires {{ d.expires_at }}</p>
          <p v-if="d.why" class="rb-meta">Why: {{ d.why }}</p>
          <p v-if="d.reason" class="rb-reason">“{{ d.reason }}”</p>
          <ul class="rb-labels">
            <li v-for="l in d.labels" :key="l">{{ l }}</li>
            <li v-if="d.labels.length < itemCount(d)" class="rb-more">and {{ itemCount(d) - d.labels.length }} more</li>
          </ul>
        </div>
        <div class="rb-actions">
          <template v-if="d.mine">
            <span class="rb-hint">Another admin has to decide.</span>
            <button type="button" class="rb-btn" :disabled="busy" @click="post(d, 'withdraw')">Withdraw</button>
          </template>
          <template v-else>
            <button type="button" class="rb-btn" :disabled="busy" @click="openReject(d)">Reject</button>
            <button type="button" class="rb-btn rb-btn--danger" :disabled="busy" @click="confirming = { kind: 'approve', d }">Approve delete</button>
          </template>
        </div>
      </article>
    </section>

    <!-- Deleted, restorable -->
    <section v-else-if="tab === 'deleted'" class="rb-list">
      <p v-if="!deleted.length" class="rb-empty">The recycle bin is empty.</p>
      <article v-for="d in deleted" :key="d.id" class="rb-card">
        <div class="rb-card-main">
          <div class="rb-card-title">
            <span class="rb-kind">{{ d.label }}</span>
            <span v-if="d.event" class="rb-event">{{ d.event }}</span>
            <span v-if="d.status === 'partially_restored'" class="rb-pill rb-pill--info">Partly restored</span>
          </div>
          <p class="rb-meta">
            Deleted {{ d.deleted_at }} by <strong>{{ d.requester }}</strong>
            <template v-if="d.approver"> · approved by <strong>{{ d.approver }}</strong></template>
            <template v-if="alsoText(d)"> · with {{ alsoText(d) }}</template>
          </p>
          <p class="rb-meta">Permanently removed on {{ d.purge_after }} ({{ d.days_left }} day{{ d.days_left === 1 ? '' : 's' }} left)</p>
          <button v-if="d.items.length" type="button" class="rb-link" @click="toggleOpen(d.id)">
            {{ open[d.id] ? 'Hide' : 'Show' }} {{ d.items.length }} item{{ d.items.length === 1 ? '' : 's' }}
          </button>
          <ul v-if="open[d.id]" class="rb-items">
            <li v-for="it in d.items" :key="it.id">
              <label v-if="d.kind !== 'event'" class="rb-item">
                <input type="checkbox" :checked="isPicked(d.id, it.id)" :aria-label="`Select ${it.label}`" @change="togglePick(d.id, it.id)" />
                <span>{{ it.label }}</span>
              </label>
              <span v-else class="rb-item">{{ it.label }}</span>
            </li>
          </ul>
        </div>
        <div class="rb-actions">
          <button v-if="picked(d.id).length" type="button" class="rb-btn" :disabled="busy"
            @click="confirming = { kind: 'restore', d, ids: picked(d.id) }">Restore selected ({{ picked(d.id).length }})</button>
          <button type="button" class="rb-btn rb-btn--primary" :disabled="busy" @click="confirming = { kind: 'restore', d, ids: null }">Restore all</button>
        </div>
      </article>
    </section>

    <!-- History -->
    <section v-else-if="tab === 'history'" class="rb-table-card">
      <p v-if="!history.length" class="rb-empty">Nothing yet.</p>
      <table v-else class="rb-table">
        <thead><tr><th>What</th><th>Event</th><th>Asked by</th><th>Decided by</th><th>Status</th><th>When</th></tr></thead>
        <tbody>
          <tr v-for="d in history" :key="d.id">
            <td>{{ d.label }}<div v-if="d.note" class="rb-note">“{{ d.note }}”</div></td>
            <td>{{ d.event || '—' }}</td>
            <td>{{ d.requester || '—' }}</td>
            <td>{{ d.approver || '—' }}</td>
            <td><span :class="['rb-pill', `rb-pill--${statusTone(d.status)}`]">{{ statusLabel(d.status) }}</span></td>
            <td class="rb-mono">{{ d.restored_at || d.deleted_at || d.requested_at }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Plans deleted before the recycle bin existed -->
    <section v-else class="rb-table-card">
      <p class="rb-sub rb-pad">Plans deleted before the recycle bin existed. Restoring one brings back the movements deleted with it.</p>
      <p v-if="!earlier.length" class="rb-empty">None.</p>
      <table v-else class="rb-table">
        <thead><tr><th>Plan</th><th>Event</th><th>Plan date</th><th>Deleted</th><th>Movements</th><th></th></tr></thead>
        <tbody>
          <tr v-for="p in earlier" :key="p.id">
            <td>{{ p.label }}</td>
            <td>{{ p.event || '—' }}</td>
            <td class="rb-mono">{{ p.date || '—' }}</td>
            <td class="rb-mono">{{ p.deleted_at }}</td>
            <td>{{ p.movements }}</td>
            <td><button type="button" class="rb-btn rb-btn--sm" :disabled="busy" @click="restoreEarlier(p)">Restore</button></td>
          </tr>
        </tbody>
      </table>
    </section>

    <ConfirmModal
      :show="confirming !== null"
      :tone="confirming?.kind === 'approve' ? 'danger' : 'primary'"
      :title="confirming?.kind === 'approve' ? 'Approve delete?' : 'Restore?'"
      :message="confirmMessage"
      :confirm-label="confirming?.kind === 'approve' ? 'Approve delete' : 'Restore'"
      :processing="busy"
      @close="confirming = null"
      @confirm="runConfirm"
    />

    <Modal :show="rejecting !== null" max-width="460px" @close="rejecting = null">
      <template #title>Reject delete request</template>
      <p class="rb-modal-text">Nothing will be deleted. {{ rejecting?.requester }} sees your note in the history.</p>
      <label class="rb-modal-label" for="rb-reject-note">Note (optional)</label>
      <textarea id="rb-reject-note" v-model="rejectNote" rows="3" class="rb-textarea" maxlength="500"></textarea>
      <template #footer>
        <button type="button" class="rb-btn" @click="rejecting = null">Cancel</button>
        <button type="button" class="rb-btn rb-btn--primary" :disabled="busy" @click="reject">Reject request</button>
      </template>
    </Modal>
  </app-layout>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import Modal from '../Components/Modal.vue';
import ConfirmModal from '../Components/ConfirmModal.vue';
import RefreshButton from '../Components/RefreshButton.vue';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  pending: { type: Array, default: () => [] },
  deleted: { type: Array, default: () => [] },
  history: { type: Array, default: () => [] },
  earlier: { type: Array, default: () => [] },
  keepDays: { type: Number, default: 30 },
});

const page = usePage();
const toast = useToast();
watch(() => page.props.flash, (flash) => {
  if (flash?.success) toast.success(flash.success, 8000);
  if (flash?.error) toast.error(flash.error, 8000);
});

const tabs = computed(() => [
  { key: 'pending', label: 'Waiting for approval', count: props.pending.length },
  { key: 'deleted', label: 'Deleted', count: props.deleted.length },
  { key: 'history', label: 'History', count: 0 },
  ...(props.earlier.length ? [{ key: 'earlier', label: 'Earlier deletions', count: props.earlier.length }] : []),
]);
const tab = ref(new URLSearchParams(window.location.search).get('tab') || (props.pending.length ? 'pending' : 'deleted'));

const itemCount = (d) => Number((d.label.match(/^\d+/) || [0])[0]);
const NAMES = { jobs: 'job', movements: 'movement', plans: 'plan', movement_templates: 'template', events: 'event' };
function alsoText(d) {
  const primary = d.kind === 'event' ? 'events' : d.kind;
  return Object.entries(d.counts || {})
    .filter(([k, n]) => k !== primary && n)
    .map(([k, n]) => `${n} ${NAMES[k] ?? k}${n === 1 ? '' : 's'}`)
    .join(', ');
}

const STATUS = {
  rejected: ['Rejected', 'danger'], withdrawn: ['Withdrawn', 'muted'], expired: ['Expired', 'muted'],
  restored: ['Restored', 'ok'], purged: ['Purged', 'muted'],
};
const statusLabel = (s) => STATUS[s]?.[0] ?? s;
const statusTone = (s) => STATUS[s]?.[1] ?? 'muted';

// ── Restore selection ──────────────────────────────────────────────────────
const open = reactive({});
const picks = reactive({});
const toggleOpen = (id) => { open[id] = !open[id]; };
const picked = (id) => picks[id] ?? [];
const isPicked = (id, item) => picked(id).includes(item);
function togglePick(id, item) {
  picks[id] = isPicked(id, item) ? picked(id).filter((x) => x !== item) : [...picked(id), item];
}

// ── Actions ────────────────────────────────────────────────────────────────
const busy = ref(false);
const confirming = ref(null);
const rejecting = ref(null);
const rejectNote = ref('');

function post(d, action, data = {}, done) {
  busy.value = true;
  router.post(`/recycle-bin/${d.id}/${action}`, data, {
    preserveScroll: true,
    onSuccess: () => done?.(),
    onFinish: () => { busy.value = false; },
  });
}

const confirmMessage = computed(() => {
  const c = confirming.value;
  if (!c) return '';
  if (c.kind === 'approve') {
    return `Delete <strong>${c.d.label}</strong> asked for by ${c.d.requester}? They move to the recycle bin and can be restored for ${props.keepDays} days.`;
  }
  return c.ids
    ? `Restore <strong>${c.ids.length}</strong> selected item(s) from this delete?`
    : `Restore everything from this delete (<strong>${c.d.label}</strong>)?`;
});

function runConfirm() {
  const c = confirming.value;
  if (c.kind === 'approve') {
    post(c.d, 'approve', {}, () => { confirming.value = null; });
  } else {
    post(c.d, 'restore', c.ids ? { ids: c.ids } : {}, () => {
      confirming.value = null;
      picks[c.d.id] = [];
    });
  }
}

function openReject(d) {
  rejecting.value = d;
  rejectNote.value = '';
}
function reject() {
  post(rejecting.value, 'reject', { note: rejectNote.value.trim() || null }, () => { rejecting.value = null; });
}

function restoreEarlier(p) {
  busy.value = true;
  router.post(`/recycle-bin/earlier-plans/${p.id}/restore`, {}, {
    preserveScroll: true,
    onFinish: () => { busy.value = false; },
  });
}
</script>

<style scoped>
.rb-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 14px; }
.rb-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 4px; }
.rb-sub { font-size: 13px; color: var(--ink3); margin: 0; max-width: 760px; }
.rb-pad { padding: 12px 16px 0; }

.rb-tabs { display: flex; gap: 4px; border-bottom: 1px solid var(--border); margin-bottom: 14px; flex-wrap: wrap; }
.rb-tab {
  border: 0; background: none; padding: 9px 14px; font: inherit; font-size: 13.5px; font-weight: 600;
  color: var(--ink3); cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px;
  display: inline-flex; align-items: center; gap: 6px;
}
.rb-tab--on { color: var(--accent); border-bottom-color: var(--accent); }
.rb-count { min-width: 20px; padding: 1px 6px; border-radius: 999px; background: var(--panel); color: var(--ink3); font-size: 11px; }
.rb-count--alert { background: var(--danger); color: #fff; }

.rb-list { display: flex; flex-direction: column; gap: 10px; }
.rb-empty { padding: 32px; text-align: center; color: var(--ink3); font-size: 13px; background: var(--surface); border: 1px dashed var(--border); border-radius: 10px; margin: 0; }
.rb-table-card .rb-empty { border: 0; }

.rb-card {
  display: flex; gap: 16px; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;
  padding: 14px 16px; background: var(--surface); border: 1px solid var(--border); border-radius: 10px;
}
.rb-card--pending { border-left: 3px solid var(--warn); }
.rb-card-main { flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 4px; }
.rb-card-title { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rb-kind { font-size: 15px; font-weight: 700; color: var(--ink); }
.rb-event { font-size: 12px; color: var(--ink3); }
.rb-meta { margin: 0; font-size: 12.5px; color: var(--ink2); }
.rb-reason { margin: 2px 0 0; font-size: 13px; color: var(--ink); font-style: italic; }
.rb-labels { margin: 6px 0 0; padding-left: 18px; font-size: 12.5px; color: var(--ink2); columns: 2; column-gap: 24px; }
.rb-more { color: var(--ink3); list-style: none; }
.rb-items { margin: 6px 0 0; padding: 0; list-style: none; max-height: 260px; overflow: auto; border: 1px solid var(--border); border-radius: 8px; }
.rb-items li { padding: 5px 10px; border-bottom: 1px solid var(--border); font-size: 12.5px; color: var(--ink2); }
.rb-items li:last-child { border-bottom: 0; }
.rb-item { display: flex; align-items: center; gap: 8px; cursor: pointer; }
.rb-link { align-self: flex-start; border: 0; background: none; padding: 0; margin-top: 4px; color: var(--accent); font: inherit; font-size: 12.5px; font-weight: 600; cursor: pointer; }

.rb-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.rb-hint { font-size: 12px; color: var(--ink3); }
.rb-btn {
  display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--surface); color: var(--ink);
  font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.rb-btn:hover { background: var(--panel); }
.rb-btn:disabled { opacity: .5; pointer-events: none; }
.rb-btn--primary { background: var(--accent); border-color: var(--accent); color: #fff; }
.rb-btn--primary:hover { background: color-mix(in srgb, var(--accent) 88%, #000); }
.rb-btn--danger { background: var(--danger); border-color: var(--danger); color: #fff; }
.rb-btn--danger:hover { background: color-mix(in srgb, var(--danger) 88%, #000); }
.rb-btn--sm { padding: 4px 10px; font-size: 12px; }

.rb-pill { font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.rb-pill--warn { background: var(--warn-soft); color: var(--warn); }
.rb-pill--danger { background: var(--danger-soft); color: var(--danger); }
.rb-pill--info { background: var(--accent-soft); color: var(--accent); }
.rb-pill--ok { background: var(--ok-soft, #e6f6ec); color: var(--ok); }
.rb-pill--muted { background: var(--panel); color: var(--ink3); }

.rb-table-card { background: var(--surface); border: 1px solid var(--border); border-radius: 10px; overflow-x: auto; }
.rb-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.rb-table th { padding: 8px 14px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--ink3); background: var(--panel); border-bottom: 1px solid var(--border); }
.rb-table td { padding: 9px 14px; border-bottom: 1px solid var(--border); color: var(--ink2); vertical-align: top; }
.rb-table tr:last-child td { border-bottom: 0; }
.rb-note { font-size: 12px; color: var(--ink3); font-style: italic; margin-top: 2px; }
.rb-mono { font-family: var(--font-mono, monospace); font-size: 12px; }

.rb-modal-text { margin: 0 0 12px; font-size: 13.5px; color: var(--ink2); }
.rb-modal-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink2); margin-bottom: 6px; }
.rb-textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink); font: inherit; font-size: 13px; resize: vertical; box-sizing: border-box; }
</style>
