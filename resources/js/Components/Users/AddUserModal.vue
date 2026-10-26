<template>
  <Modal :show="true" max-width="640px" @close="$emit('close')">
    <template #title>{{ invite ? 'Invite an external user' : 'Add SC/LOC staff' }}</template>

    <div class="ud">
      <p class="ud-sub">
        {{ invite
          ? 'External users (agencies, providers, field supervisors) get an email invite to set their password.'
          : `SC/LOC staff sign in with their ${domainList} account. Existing accounts get the roles and events added.` }}
      </p>

      <div class="am-modes" role="tablist" aria-label="Who are you adding?">
        <button v-for="m in MODES" :key="m.value" type="button" role="tab" :aria-selected="form.mode === m.value"
          :class="['am-mode', { 'am-mode--on': form.mode === m.value }]" @click="form.mode = m.value">{{ m.label }}</button>
      </div>

      <div class="ud-field">
        <div class="ud-grid">
          <div class="ud-field">
            <label class="ud-label" for="am-email">{{ invite ? 'Email address' : 'SC/LOC email' }}</label>
            <input id="am-email" v-model="form.email" type="email" :class="['ud-input', { 'ud-input--bad': message.bad || errors.email }]"
              :placeholder="invite ? 'name@company.com' : `first.last@${staffDomains[0] ?? 'sc.qa'}`" />
          </div>
          <div class="ud-field">
            <label class="ud-label" for="am-name">{{ invite ? 'Name' : 'Full name' }} <span v-if="invite" class="ud-hint">optional, they can change it</span></label>
            <input id="am-name" :value="merge ? merge.name : form.name" type="text" class="ud-input" :disabled="!!merge"
              placeholder="Jane Smith" @input="form.name = $event.target.value" />
          </div>
        </div>
        <div v-if="errors.email || errors.name" class="ud-msg ud-msg--bad">{{ errors.email || errors.name }}</div>
        <div v-else-if="message.text" :class="['ud-msg', message.tone && `ud-msg--${message.tone}`]">
          <span>{{ message.text }}</span>
          <button v-if="message.switchTo" type="button" class="ud-switch-link" @click="form.mode = message.switchTo">{{ message.switchLabel }}</button>
        </div>
      </div>

      <div v-if="merge" class="am-existing">
        <span class="am-avatar">{{ initials(merge.name) }}</span>
        <div class="am-existing-text">
          <span class="am-existing-name">{{ merge.name }} already has an account</span>
          <span class="am-existing-sub">{{ mergeSummary }} · what you tick below is added; nothing is removed.</span>
        </div>
      </div>

      <div v-if="invite" class="ud-grid">
        <div class="ud-field">
          <label class="ud-label" for="am-provider">Transport provider</label>
          <select id="am-provider" v-model="form.fleet_provider_id" class="ud-input">
            <option :value="null">None</option>
            <option v-for="p in fleetProviders" :key="p.id" :value="p.id">{{ p.name }}</option>
          </select>
        </div>
        <div class="ud-field">
          <label class="ud-label" for="am-days">Invite link valid for</label>
          <select id="am-days" v-model.number="form.expires_in_days" class="ud-input">
            <option :value="1">24 hours</option>
            <option :value="3">3 days</option>
            <option :value="7">7 days</option>
            <option :value="14">14 days</option>
          </select>
        </div>
      </div>

      <AccessChecklist v-model:role-ids="form.role_ids" v-model:event-ids="form.event_ids" :roles="roles" :events="events"
        :has-role-ids="mergeRoleIds" :has-event-ids="mergeEventIds" :errors="errors" />

      <button v-if="!invite" type="button" :class="['ud-check', { 'ud-check--on': form.notify }]" @click="form.notify = !form.notify">
        <span class="ud-check-box" aria-hidden="true">{{ form.notify ? '✓' : '' }}</span>
        <span class="ud-check-text">Email them the sign-in link</span>
      </button>
    </div>

    <template #footer>
      <div class="ud-foot">
        <span :class="['ud-status', { 'ud-status--ok': ready }]">{{ status }}</span>
        <div class="ud-foot-actions">
          <Button variant="secondary" size="md" @click="$emit('close')">Cancel</Button>
          <Button variant="primary" size="md" :disabled="!ready" :processing="processing" @click="submit">{{ cta }}</Button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from '../Modal.vue';
import Button from '../Button.vue';
import AccessChecklist from './AccessChecklist.vue';
import { roleLabel, initials, isEmail } from '@/Composables/userRoles';

const props = defineProps({
  roles: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  users: { type: Array, default: () => [] },
  fleetProviders: { type: Array, default: () => [] },
  staffDomains: { type: Array, default: () => [] },
  // Prefill (e.g. "Add as SC/LOC staff instead" from Create user manually).
  initial: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['close', 'done']);

const MODES = [
  { value: 'invite', label: 'External · email invite' },
  { value: 'grant', label: 'SC/LOC staff · SSO' },
];

const form = reactive({
  mode: 'invite', email: '', name: '', fleet_provider_id: null, expires_in_days: 7, role_ids: [], event_ids: [], notify: true,
  ...props.initial,
});
const errors = ref({});
const processing = ref(false);

const invite = computed(() => form.mode === 'invite');
const domainList = computed(() => props.staffDomains.map((d) => `@${d}`).join(' or '));
const email = computed(() => form.email.trim().toLowerCase());
const valid = computed(() => isEmail(email.value));
const isStaff = computed(() => props.staffDomains.includes(email.value.split('@')[1] ?? ''));
const existing = computed(() => (email.value ? props.users.find((u) => u.email.toLowerCase() === email.value) ?? null : null));
// Granting to an existing SC/LOC account adds to it.
const merge = computed(() => (!invite.value && isStaff.value ? existing.value : null));
const mergeRoleIds = computed(() => merge.value?.roles.map((r) => r.id) ?? []);
const mergeEventIds = computed(() => merge.value?.events.map((e) => e.id) ?? []);
const mergeSummary = computed(() => {
  const m = merge.value;
  if (!m) return '';
  const all = m.roles.some((r) => props.roles.find((x) => x.id === r.id)?.all_events);
  return `${m.roles.map((r) => roleLabel(r.name)).join(', ') || 'No roles'} · ${all ? 'all events' : `${m.events.length} event${m.events.length === 1 ? '' : 's'}`}`;
});

const message = computed(() => {
  const days = { 1: '24 hours', 3: '3 days', 7: '7 days', 14: '14 days' }[form.expires_in_days];
  if (email.value && !valid.value) return { text: 'Enter a valid email address', tone: 'bad', bad: true };
  if (invite.value && isStaff.value) return { text: 'This is an SC/LOC address. Staff sign in with their SC/LOC account.', tone: 'warn', bad: true, switchTo: 'grant', switchLabel: 'Switch to SC/LOC staff →' };
  if (invite.value && existing.value) return { text: `${existing.value.name} already has an account. Edit them from the list instead.`, tone: 'bad', bad: true };
  if (!invite.value && email.value && !isStaff.value) return { text: `Must be an ${domainList.value} address.`, tone: 'bad', bad: true, switchTo: 'invite', switchLabel: 'Switch to external →' };
  if (invite.value && valid.value) return { text: `They'll get a single-use link, valid for ${days}, to set their own password.` };
  if (!invite.value && valid.value && !merge.value) return { text: 'They sign in with their SC/LOC account. No password needed.' };
  return { text: '' };
});

const allEvents = computed(() => props.roles.some((r) => r.all_events && (form.role_ids.includes(r.id) || mergeRoleIds.value.includes(r.id))));
const newRoles = computed(() => form.role_ids.filter((id) => !mergeRoleIds.value.includes(id)));
const newEvents = computed(() => (allEvents.value ? [] : form.event_ids.filter((id) => !mergeEventIds.value.includes(id))));

const issues = computed(() => {
  const list = [];
  if (!email.value || message.value.bad) list.push('email');
  if (!invite.value && !merge.value && !form.name.trim()) list.push('name');
  if (merge.value) {
    if (!newRoles.value.length && !newEvents.value.length) list.push('a role or event to add');
  } else {
    if (!form.role_ids.length) list.push('a role');
    if (!allEvents.value && !form.event_ids.length) list.push('an event');
  }
  return list;
});
const ready = computed(() => !issues.value.length);

const status = computed(() => {
  if (!ready.value) return `Needs ${issues.value.join(', ')}`;
  if (merge.value) {
    const parts = [
      newRoles.value.length && `${newRoles.value.length} role${newRoles.value.length > 1 ? 's' : ''}`,
      newEvents.value.length && `${newEvents.value.length} event${newEvents.value.length > 1 ? 's' : ''}`,
    ].filter(Boolean);
    return `Adds ${parts.join(' and ')}`;
  }
  return 'Ready to send';
});
const cta = computed(() => (invite.value ? 'Send invitation' : merge.value ? 'Add access' : 'Grant access'));

function submit() {
  if (!ready.value) return;
  processing.value = true;
  errors.value = {};
  const eventIds = allEvents.value ? [] : form.event_ids;
  const [url, data] = invite.value
    ? ['/setups/users/invitations', { email: form.email, name: form.name, fleet_provider_id: form.fleet_provider_id, expires_in_days: form.expires_in_days, role_ids: form.role_ids, event_ids: eventIds }]
    : ['/setups/users/grant-access', { email: form.email, name: merge.value ? merge.value.name : form.name, role_ids: merge.value ? (newRoles.value.length ? newRoles.value : mergeRoleIds.value) : form.role_ids, event_ids: eventIds, notify: form.notify }];
  router.post(url, data, {
    preserveScroll: true,
    onSuccess: () => emit('done', email.value),
    onError: (e) => { errors.value = e; },
    onFinish: () => { processing.value = false; },
  });
}
</script>

<style scoped src="./usersDialog.css"></style>
<style scoped>
.am-modes { display: flex; gap: 2px; padding: 2px; background: var(--panel); border-radius: 8px; align-self: flex-start; margin-top: -6px; }
.am-mode {
  height: 28px; padding: 0 12px; border: none; border-radius: 6px; font-size: 12px; font-weight: 500;
  cursor: pointer; background: transparent; color: var(--ink3); font-family: inherit;
}
.am-mode--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.12); }
.am-existing { display: flex; align-items: center; gap: 10px; padding: 10px 12px; background: var(--panel); border-radius: 10px; }
.am-avatar {
  width: 32px; height: 32px; border-radius: 50%; flex: none; font-size: 11px; font-weight: 600;
  background: color-mix(in srgb, var(--accent) 12%, transparent); color: var(--accent);
  display: flex; align-items: center; justify-content: center;
}
.am-existing-text { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.am-existing-name { font-size: 12px; font-weight: 600; color: var(--ink); }
.am-existing-sub { font-size: 11px; color: var(--ink3); }
</style>
