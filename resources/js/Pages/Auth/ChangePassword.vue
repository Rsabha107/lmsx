<template>
  <auth-layout>
    <h1 class="auth-title">Choose your password</h1>
    <p class="auth-sub">
      You signed in as <strong>{{ email }}</strong> with a temporary password from an administrator.
      Choose your own to continue.
    </p>

    <form class="auth-form" @submit.prevent="submit">
      <div class="form-group">
        <label class="form-label" for="password">New password</label>
        <input id="password" v-model="form.password" :type="show ? 'text' : 'password'" class="form-input"
          :class="{ 'input-error': errors.password }" placeholder="At least 8 characters" autocomplete="new-password" />
        <span v-if="errors.password" class="error-msg">{{ errors.password }}</span>
      </div>
      <div class="form-group">
        <label class="form-label" for="password_confirmation">Confirm new password</label>
        <input id="password_confirmation" v-model="form.password_confirmation" :type="show ? 'text' : 'password'" class="form-input" autocomplete="new-password" />
        <label class="show-pw"><input v-model="show" type="checkbox" /> Show password</label>
      </div>

      <button type="submit" class="btn-submit" :disabled="processing">
        <span v-if="processing" class="submit-spinner" />
        <span v-else>Set password and continue</span>
      </button>
    </form>

    <button type="button" class="link-out" @click="signOut">Sign out instead</button>
  </auth-layout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '../../Components/AuthLayout.vue';

defineProps({ email: { type: String, default: '' } });

const form = reactive({ password: '', password_confirmation: '' });
const errors = ref({});
const processing = ref(false);
const show = ref(false);

function submit() {
  processing.value = true;
  errors.value = {};
  router.post('/password/change', form, {
    onError: (e) => { errors.value = e; },
    onFinish: () => { processing.value = false; },
  });
}

function signOut() {
  router.post('/logout');
}
</script>

<style scoped>
.auth-title { font-size: 22px; font-weight: 700; color: var(--ink); margin: 0 0 4px; letter-spacing: -0.02em; }
.auth-sub { font-size: 13.5px; color: var(--ink3); margin: 0 0 22px; line-height: 1.5; }
.auth-sub strong { color: var(--ink); }
.auth-form { display: flex; flex-direction: column; }
.form-group { margin-bottom: 14px; }
.form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
.form-input {
  width: 100%; padding: 9px 12px; font-size: 13.5px; border: 1px solid var(--border); border-radius: 8px;
  background: var(--bg); color: var(--ink); outline: none; box-sizing: border-box; font-family: inherit;
}
.form-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 12%, transparent); }
.input-error { border-color: var(--danger) !important; }
.error-msg { font-size: 12px; color: var(--danger); margin-top: 5px; display: block; }
.show-pw { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; color: var(--ink3); margin-top: 8px; cursor: pointer; }
.btn-submit {
  width: 100%; min-height: 42px; padding: 10px; font-size: 14px; font-weight: 600; background: var(--accent); color: #fff;
  border: none; border-radius: 9px; cursor: pointer; font-family: inherit; display: flex; align-items: center; justify-content: center;
}
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.submit-spinner { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff; border-radius: 50%; animation: spin 0.65s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
.link-out { margin-top: 14px; background: none; border: none; color: var(--ink3); font-size: 12.5px; cursor: pointer; font-family: inherit; text-decoration: underline; }
</style>
