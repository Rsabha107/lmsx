<template>
  <Modal :show="true" max-width="640px" @close="$emit('close')">
    <template #title>{{ user ? 'Edit user' : 'Create user manually' }}</template>

    <div class="ud">
      <p class="ud-sub">
        {{ user ? 'Roles set what they can do. Events set where they can do it.' : 'Sets up the account now with a password you share with them. No email is sent.' }}
      </p>

      <div class="ud-grid">
        <div class="ud-field">
          <label class="ud-label" for="uf-name">Full name</label>
          <input id="uf-name" v-model="form.name" type="text" :class="['ud-input', { 'ud-input--bad': errors.name }]" placeholder="e.g. Ahmed Elobaid" />
          <span v-if="errors.name" class="ud-msg ud-msg--bad">{{ errors.name }}</span>
        </div>
        <div class="ud-field">
          <label class="ud-label" for="uf-email">Email</label>
          <input id="uf-email" v-model="form.email" type="email" :class="['ud-input', { 'ud-input--bad': emailError }]" placeholder="name@company.com" />
          <span v-if="emailError" class="ud-msg ud-msg--bad">{{ emailError }}</span>
        </div>
      </div>

      <div v-if="!user && isStaff" class="ud-msg ud-msg--warn sc-warn">
        <span>SC/LOC staff sign in with their SC/LOC account and don't need a password.</span>
        <button type="button" class="ud-switch-link" @click="toGrant">Add as SC/LOC staff instead →</button>
      </div>

      <button v-if="user && !setPassword" type="button" class="ud-switch-link set-pw" @click="setPassword = true">Set a new password…</button>

      <div v-if="!user || setPassword" class="pw-block">
        <div class="ud-field">
          <label class="ud-label" for="uf-pw">{{ user ? 'New password' : 'Temporary password' }}</label>
          <div class="pw-row">
            <input id="uf-pw" v-model="form.password" :type="showPw ? 'text' : 'password'" autocomplete="new-password"
              :class="['ud-input', 'ud-input--mono', { 'ud-input--bad': pwShort || errors.password }]" placeholder="At least 8 characters" />
            <button type="button" class="ud-btn" @click="showPw = !showPw">{{ showPw ? 'Hide' : 'Show' }}</button>
            <button type="button" class="ud-btn" @click="generate">Generate</button>
          </div>
          <span :class="['ud-msg', pwShort || errors.password ? 'ud-msg--bad' : form.password ? 'pw-ok' : '']">{{ errors.password || pwHint }}</span>
        </div>
        <button type="button" :class="['ud-check', { 'ud-check--on': form.must_change_password }]" @click="form.must_change_password = !form.must_change_password">
          <span class="ud-check-box" aria-hidden="true">{{ form.must_change_password ? '✓' : '' }}</span>
          <span class="ud-check-text">Ask them to change it on {{ user ? 'their next' : 'first' }} sign-in</span>
        </button>
      </div>

      <AccessChecklist v-model:role-ids="form.roles" v-model:event-ids="form.events" :roles="roles" :events="events" :errors="errors" plain />

      <button type="button" class="active-row" :disabled="isSelf" :aria-pressed="form.is_active" @click="form.is_active = !form.is_active">
        <span class="active-text">
          <span class="active-title">Active</span>
          <span class="active-desc">{{ isSelf ? "You can't deactivate your own account." : "Inactive users can't sign in and don't appear in crew dropdowns." }}</span>
        </span>
        <span :class="['switch', { 'switch--on': form.is_active }]"><span class="knob" /></span>
      </button>

      <div class="ud-field">
        <label class="ud-label" for="uf-provider">Provider <span class="ud-hint">optional</span></label>
        <select id="uf-provider" v-model="form.fleet_provider_id" class="ud-input">
          <option :value="null">None (internal)</option>
          <option v-for="p in fleetProviders" :key="p.id" :value="p.id">{{ p.name }}</option>
        </select>
      </div>
    </div>

    <template #footer>
      <div class="ud-foot">
        <span :class="['ud-status', { 'ud-status--ok': ready }]">{{ ready ? (user ? 'Ready to save' : 'Ready to create') : `Missing: ${issues.join(', ')}` }}</span>
        <div class="ud-foot-actions">
          <Button variant="secondary" size="md" @click="$emit('close')">Cancel</Button>
          <Button variant="primary" size="md" :disabled="!ready" :processing="processing" @click="save">{{ user ? 'Save changes' : 'Create user' }}</Button>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import Modal from '../Modal.vue';
import Button from '../Button.vue';
import AccessChecklist from './AccessChecklist.vue';
import { isEmail } from '@/Composables/userRoles';

const props = defineProps({
  user: { type: Object, default: null },
  users: { type: Array, default: () => [] },
  roles: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  fleetProviders: { type: Array, default: () => [] },
  staffDomains: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'to-grant']);

const u = props.user;
const form = reactive({
  name: u?.name ?? '',
  email: u?.email ?? '',
  password: '',
  must_change_password: true,
  roles: u ? u.roles.map((r) => r.id) : [],
  events: u ? (u.events ?? []).map((e) => e.id) : [],
  is_active: u ? !!u.is_active : true,
  fleet_provider_id: u?.fleet_provider_id ?? null,
});
const errors = ref({});
const processing = ref(false);
const showPw = ref(false);
const setPassword = ref(false);

const isSelf = computed(() => !!u && u.id === usePage().props.auth.user?.id);
const email = computed(() => form.email.trim().toLowerCase());
const isStaff = computed(() => props.staffDomains.includes(email.value.split('@')[1] ?? ''));
const duplicate = computed(() => props.users.some((x) => x.id !== u?.id && x.email.toLowerCase() === email.value));
const emailError = computed(() => errors.value.email
  || (email.value && duplicate.value ? 'Another user already has this email' : email.value && !isEmail(email.value) ? 'Enter a valid email address' : ''));

const allEvents = computed(() => props.roles.some((r) => r.all_events && form.roles.includes(r.id)));
const needsPassword = computed(() => !u || setPassword.value);
const pwShort = computed(() => !!form.password && form.password.length < 8);
const pwHint = computed(() => {
  const n = form.password.length;
  if (!n) return u ? 'Leave blank to keep their current password.' : 'Share it with them securely.';
  return n < 8 ? `${8 - n} more character${8 - n > 1 ? 's' : ''} needed` : 'Strong enough. Share it with them securely.';
});

const issues = computed(() => {
  const list = [];
  if (!form.roles.length) list.push('a role');
  if (!form.name.trim()) list.push('name');
  if (!isEmail(email.value) || duplicate.value) list.push('valid email');
  if (!allEvents.value && !form.events.length) list.push('at least one event');
  if ((!u && form.password.length < 8) || (u && setPassword.value && pwShort.value)) list.push('password');
  return list;
});
const ready = computed(() => !issues.value.length);

function generate() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
  const bytes = crypto.getRandomValues(new Uint32Array(12));
  form.password = Array.from(bytes, (b) => chars[b % chars.length]).join('');
  showPw.value = true;
}

function toGrant() {
  emit('to-grant', { mode: 'grant', email: form.email, name: form.name, role_ids: [...form.roles], event_ids: [...form.events] });
}

function save() {
  if (!ready.value) return;
  processing.value = true;
  errors.value = {};
  const data = {
    name: form.name.trim(),
    email: form.email.trim(),
    roles: form.roles,
    events: allEvents.value ? [] : form.events,
    fleet_provider_id: form.fleet_provider_id,
    is_active: form.is_active,
    ...(needsPassword.value && form.password ? { password: form.password, must_change_password: form.must_change_password } : {}),
  };
  const options = {
    preserveScroll: true,
    onSuccess: () => emit('close'),
    onError: (e) => { errors.value = e; },
    onFinish: () => { processing.value = false; },
  };
  u ? router.put(`/setups/users/${u.id}`, data, options) : router.post('/setups/users', data, options);
}
</script>

<style scoped src="./usersDialog.css"></style>
<style scoped>
.sc-warn { margin-top: -8px; }
.set-pw { align-self: flex-start; font-size: 12px; margin-top: -6px; }
.pw-block { display: flex; flex-direction: column; gap: 8px; }
.pw-row { display: flex; gap: 6px; }
.pw-row .ud-input { flex: 1; min-width: 0; }
.pw-ok { color: var(--ok); }

.active-row {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px;
  border: 1px solid var(--border); border-radius: 10px; background: var(--surface); cursor: pointer; text-align: left; font-family: inherit;
}
.active-row:disabled { cursor: not-allowed; opacity: 0.75; }
.active-text { display: flex; flex-direction: column; gap: 2px; }
.active-title { font-size: 12px; font-weight: 600; color: var(--ink); }
.active-desc { font-size: 11px; color: var(--ink3); }
.switch { width: 36px; height: 20px; border-radius: 999px; background: var(--border-strong); position: relative; flex: none; transition: background 0.15s; }
.switch--on { background: var(--accent); }
.knob {
  position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.25); transition: left 0.15s;
}
.switch--on .knob { left: 18px; }
</style>
