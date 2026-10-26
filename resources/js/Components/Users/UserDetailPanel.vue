<template>
  <aside class="dp" aria-label="User details">
    <div class="dp-head">
      <div class="dp-who">
        <span :class="['dp-avatar', { 'dp-avatar--off': row.status === 'inactive' }]">{{ initials(row.name) }}</span>
        <div class="dp-id">
          <span class="dp-name">{{ row.name }}</span>
          <span class="dp-email">{{ row.email }}</span>
        </div>
      </div>
      <button type="button" class="dp-close" aria-label="Close details" @click="$emit('close')">×</button>
    </div>

    <div class="dp-body">
      <div class="dp-grid">
        <span class="dp-k">Roles</span>
        <div class="dp-roles">
          <div v-for="r in row.roles" :key="r.id" class="dp-role">
            <span class="chip" :style="roleChip(r.name)">{{ roleLabel(r.name) }}</span>
            <span class="dp-role-desc">{{ roleDesc(r.name) }}</span>
          </div>
          <span v-if="!row.roles.length" class="dp-muted">No roles</span>
        </div>

        <span class="dp-k">Sign-in</span>
        <span>{{ row.staff ? 'SC/LOC account' : row.kind === 'invite' ? 'Email + password (once accepted)' : 'Email + password' }}</span>

        <template v-if="row.kind === 'user'">
          <span class="dp-k">Last sign-in</span>
          <span :class="{ 'dp-muted': !row.last_login_at }">{{ row.last_login_at ? when(row.last_login_at) : 'Never' }}</span>
        </template>

        <span class="dp-k">Provider</span>
        <span :class="{ 'dp-muted': !row.provider }">{{ row.provider || 'Internal' }}</span>

        <span class="dp-k">Status</span>
        <div class="dp-status-row">
          <span class="dp-status">
            <span class="dp-status-main" :style="{ color: STATUS[row.status].fg }">
              <span class="dp-dot" :style="{ background: STATUS[row.status].dot }" />{{ statusLabel }}
            </span>
            <span v-if="statusNote" class="dp-muted dp-small">{{ statusNote }}</span>
          </span>
          <button v-if="!isSelf" type="button" class="dp-action" @click="$emit('status', row)">
            {{ row.kind === 'invite' ? (row.invite_status === 'expired' ? 'Send again' : 'Resend invite') : row.status === 'inactive' ? 'Activate' : 'Deactivate' }}
          </button>
        </div>
      </div>

      <div class="dp-events">
        <div class="dp-section">EVENTS · {{ row.allEvents ? 'ALL' : row.events.length }}</div>
        <div v-if="row.allEvents" class="dp-info">Access to all events, including future ones.</div>
        <div v-else-if="!row.events.length" class="dp-info dp-info--bad">No events: this user sees nothing until one is added.</div>
        <div v-for="e in row.allEvents ? [] : row.events" :key="e.id" class="dp-event">
          <span class="dp-event-dot" />
          <span class="dp-event-name">{{ e.name }}</span>
        </div>
      </div>
    </div>

    <div class="dp-foot">
      <Button v-if="row.kind === 'user'" variant="primary" size="md" class="dp-cta" @click="$emit('edit', row)">Edit user</Button>
      <Button v-else variant="secondary" size="md" class="dp-cta" @click="$emit('delete', row)">Cancel invitation</Button>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Button from '../Button.vue';
import { roleLabel, roleDesc, roleChip, initials } from '@/Composables/userRoles';

const props = defineProps({ row: { type: Object, required: true } });
defineEmits(['close', 'edit', 'status', 'delete']);

const STATUS = {
  active: { fg: 'var(--ok)', dot: 'var(--ok)' },
  invited: { fg: 'var(--warn)', dot: 'var(--warn)' },
  inactive: { fg: 'var(--ink3)', dot: 'var(--ink4)' },
};

const isSelf = computed(() => props.row.kind === 'user' && props.row.id === usePage().props.auth.user?.id);

const statusLabel = computed(() => {
  const r = props.row;
  if (r.kind === 'invite') return r.invite_status === 'expired' ? 'Invitation expired' : 'Invitation pending';
  return r.status === 'active' ? 'Active' : 'Inactive';
});

const statusNote = computed(() => {
  const r = props.row;
  if (r.kind === 'invite') return `${r.invite_status === 'expired' ? 'Expired' : 'Link valid until'} ${when(r.expires_at)}${r.sent_count > 1 ? ` · sent ${r.sent_count}×` : ''}`;
  if (r.status === 'inactive') return r.deactivated_at ? `Since ${when(r.deactivated_at)}` : '';
  return r.must_change_password ? 'Must change their password at next sign-in' : '';
});

// Wall-clock 'YYYY-MM-DD HH:mm…' strings, read as text (no timezone conversion).
function when(s) {
  if (!s) return '—';
  const [d, t = ''] = String(s).replace('T', ' ').split(' ');
  const [y, m, day] = d.split('-');
  const month = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][Number(m) - 1];
  return `${Number(day)} ${month} ${y}${t ? `, ${t.slice(0, 5)}` : ''}`;
}
</script>

<style scoped>
.dp {
  flex: 1 1 320px; max-width: 400px; border: 1px solid var(--border); border-radius: 12px; overflow: hidden;
  display: flex; flex-direction: column; background: var(--surface); position: sticky; top: 16px;
}
.dp-head { padding: 16px; border-bottom: 1px solid var(--border); display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
.dp-who { display: flex; align-items: center; gap: 12px; min-width: 0; }
.dp-avatar {
  width: 44px; height: 44px; border-radius: 50%; flex: none; font-size: 14px; font-weight: 600;
  background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent);
  display: flex; align-items: center; justify-content: center;
}
.dp-avatar--off { background: var(--panel); color: var(--ink4); }
.dp-id { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.dp-name { font-size: 16px; font-weight: 600; color: var(--ink); }
.dp-email { font-size: 12px; color: var(--ink3); word-break: break-all; }
.dp-close { border: none; background: none; font-size: 18px; color: var(--ink3); cursor: pointer; line-height: 1; font-family: inherit; }
.dp-close:hover { color: var(--ink); }

.dp-body { padding: 14px 16px; display: flex; flex-direction: column; gap: 14px; }
.dp-grid { display: grid; grid-template-columns: 90px minmax(0, 1fr); gap: 10px 12px; font-size: 12px; align-items: baseline; color: var(--ink); }
.dp-k { color: var(--ink3); }
.dp-muted { color: var(--ink4); }
.dp-small { font-size: 11px; }
.dp-roles { display: flex; flex-direction: column; gap: 5px; }
.dp-role { display: flex; flex-direction: column; gap: 2px; }
.dp-role-desc { font-size: 11px; color: var(--ink3); }
.chip { align-self: flex-start; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 999px; }

.dp-status-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.dp-status { display: flex; flex-direction: column; gap: 2px; }
.dp-status-main { display: flex; align-items: center; gap: 6px; font-weight: 500; }
.dp-dot { width: 7px; height: 7px; border-radius: 50%; }
.dp-action {
  height: 26px; padding: 0 10px; border: 1px solid var(--border); background: var(--surface); border-radius: 6px;
  font-size: 11px; font-weight: 500; cursor: pointer; color: var(--ink2); font-family: inherit; white-space: nowrap;
}
.dp-action:hover { border-color: var(--border-strong); color: var(--ink); }

.dp-events { display: flex; flex-direction: column; gap: 8px; }
.dp-section { font-size: 10px; font-weight: 600; letter-spacing: 0.06em; color: var(--ink3); }
.dp-info { font-size: 12px; color: var(--ink2); background: var(--panel); border-radius: 8px; padding: 9px 10px; }
.dp-info--bad { color: var(--danger); }
.dp-event { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; }
.dp-event-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--accent); flex: none; }
.dp-event-name { font-size: 12px; font-weight: 500; color: var(--ink); }

.dp-foot { padding: 12px 16px; border-top: 1px solid var(--border); background: var(--panel); margin-top: auto; }
.dp-cta { width: 100%; justify-content: center; }
</style>
