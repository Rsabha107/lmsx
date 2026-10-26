<template>
  <auth-layout>
    <template v-if="state === 'open'">
      <h1 class="auth-title">Create your account</h1>
      <p class="auth-sub">You've been invited to NAQLA LMS. Set your name and a password to finish.</p>

      <div class="invite-box">
        <div class="invite-row"><span class="invite-k">Email</span><span class="invite-v">{{ invitation.email }}</span></div>
        <div class="invite-row"><span class="invite-k">Role</span><span class="invite-v">{{ invitation.roles.map(roleLabel).join(', ') || '—' }}</span></div>
        <div class="invite-row"><span class="invite-k">Events</span><span class="invite-v">{{ invitation.events.join(', ') || 'All events' }}</span></div>
        <div class="invite-row"><span class="invite-k">Link expires</span><span class="invite-v">{{ invitation.expires_at }}</span></div>
      </div>

      <form @submit.prevent="submit" class="auth-form">
        <div class="form-group">
          <label class="form-label" for="name">Full name</label>
          <input id="name" v-model="form.name" type="text" class="form-input" :class="{ 'input-error': errors.name }" autocomplete="name" />
          <span v-if="errors.name" class="error-msg">{{ errors.name }}</span>
        </div>

        <div class="form-group">
          <label class="form-label" for="phone">Mobile number <span class="form-hint">(optional)</span></label>
          <input id="phone" v-model="form.phone" type="tel" class="form-input" :class="{ 'input-error': errors.phone }" autocomplete="tel" placeholder="+974 …" />
          <span v-if="errors.phone" class="error-msg">{{ errors.phone }}</span>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input id="password" v-model="form.password" :type="showPassword ? 'text' : 'password'" class="form-input"
            :class="{ 'input-error': errors.password }" placeholder="At least 8 characters" autocomplete="new-password" />
          <span v-if="errors.password" class="error-msg">{{ errors.password }}</span>
        </div>

        <div class="form-group">
          <label class="form-label" for="password_confirmation">Confirm password</label>
          <input id="password_confirmation" v-model="form.password_confirmation" :type="showPassword ? 'text' : 'password'" class="form-input"
            autocomplete="new-password" />
          <label class="show-pw"><input v-model="showPassword" type="checkbox" /> Show password</label>
        </div>

        <span v-if="errors.email" class="error-msg form-error">{{ errors.email }}</span>

        <button type="submit" class="btn-submit" :disabled="processing">
          <span v-if="processing" class="submit-spinner" />
          <span v-else>Create account</span>
        </button>
      </form>
    </template>

    <template v-else>
      <h1 class="auth-title">Invitation unavailable</h1>
      <p class="auth-sub">{{ unavailableMessage }}</p>
      <a href="/login" class="btn-submit btn-link">Go to sign in</a>
    </template>
  </auth-layout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '../../Components/AuthLayout.vue';

const props = defineProps({
  state: { type: String, required: true },
  invitation: { type: Object, default: null },
  submitUrl: { type: String, default: '' },
});

const errors = ref({});
const processing = ref(false);
const showPassword = ref(false);
const form = reactive({ name: props.invitation?.name ?? '', phone: '', password: '', password_confirmation: '' });

const unavailableMessage = computed(() => ({
  accepted: 'This invitation has already been used. Sign in with the email and password you chose.',
  expired: 'This invitation has expired. Ask your administrator to send a new one.',
  revoked: 'This invitation was cancelled. Ask your administrator if you still need access.',
}[props.state] ?? 'This invitation link is not valid. Ask your administrator to send a new one.'));

function roleLabel(name) {
  return name.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function submit() {
  processing.value = true;
  errors.value = {};
  router.post(props.submitUrl, form, {
    onError: (e) => { errors.value = e; },
    onFinish: () => { processing.value = false; },
  });
}
</script>

<style scoped>
.auth-title { font-size: 22px; font-weight: 700; color: var(--ink); margin: 0 0 4px; letter-spacing: -0.02em; }
.auth-sub { font-size: 13.5px; color: var(--ink3); margin: 0 0 18px; }

.invite-box {
  border: 1px solid var(--border); border-radius: 9px; background: var(--panel);
  padding: 10px 12px; margin-bottom: 20px; display: grid; gap: 4px;
}
.invite-row { display: flex; gap: 10px; font-size: 12.5px; }
.invite-k { width: 92px; flex-shrink: 0; color: var(--ink3); }
.invite-v { color: var(--ink); font-weight: 600; min-width: 0; overflow-wrap: anywhere; }

.auth-form { display: flex; flex-direction: column; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
.form-hint { font-weight: 400; color: var(--ink4); }
.form-input {
  width: 100%; padding: 9px 12px; font-size: 13.5px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--bg); color: var(--ink); outline: none;
  box-sizing: border-box; font-family: inherit;
}
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 12%, transparent); }
.input-error { border-color: var(--danger) !important; }
.error-msg { font-size: 12px; color: var(--danger); margin-top: 5px; display: block; }
.form-error { margin: 0 0 10px; }
.show-pw { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ink3); margin-top: 8px; cursor: pointer; }

.btn-submit {
  width: 100%; padding: 10px; font-size: 14px; font-weight: 600;
  background: var(--accent); color: #fff; border: none; border-radius: 9px;
  cursor: pointer; font-family: inherit; margin-top: 4px; min-height: 42px;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-sizing: border-box; text-decoration: none;
}
.btn-submit:hover:not(:disabled) { opacity: 0.9; }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.submit-spinner {
  width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
  border-radius: 50%; animation: spin 0.65s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
