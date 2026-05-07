<template>
  <auth-layout>

    <!-- Success state -->
    <template v-if="$page.props.flash?.status">
      <div class="success-state">
        <div class="success-icon">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
          </svg>
        </div>
        <h1 class="auth-title">Check your email</h1>
        <p class="auth-sub">
          We sent a password reset link to <strong>{{ sentTo }}</strong>.
          Check your inbox and follow the link to reset your password.
        </p>
        <p class="resend-note">
          Didn't get it?
          <button class="link-btn" @click="resetState">Try again</button>
          or check your spam folder.
        </p>
      </div>
    </template>

    <!-- Form state -->
    <template v-else>
      <h1 class="auth-title">Forgot your password?</h1>
      <p class="auth-sub">Enter your email and we'll send you a reset link.</p>

      <form @submit.prevent="submit" class="auth-form">
        <div class="form-group">
          <label class="form-label" for="email">Email address</label>
          <input
            id="email"
            v-model="form.email"
            type="email"
            class="form-input"
            :class="{ 'input-error': errors.email }"
            placeholder="you@example.com"
            autocomplete="email"
            autofocus
          />
          <span v-if="errors.email" class="error-msg">{{ errors.email }}</span>
        </div>

        <button type="submit" class="btn-submit" :disabled="processing">
          <span v-if="processing" class="submit-spinner" />
          <span v-else>Send reset link</span>
        </button>
      </form>

      <div class="auth-footer-link">
        <a href="/login" class="back-link">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
          Back to sign in
        </a>
      </div>
    </template>

  </auth-layout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Components/AuthLayout.vue';

const errors = ref({});
const processing = ref(false);
const sentTo = ref('');

const form = reactive({ email: '' });

function submit() {
  processing.value = true;
  errors.value = {};
  sentTo.value = form.email;
  router.post('/forgot-password', form, {
    onError:  (e) => { processing.value = false; errors.value = e; sentTo.value = ''; },
    onFinish: () => { processing.value = false; },
  });
}

function resetState() {
  router.reload({ only: ['flash'] });
}
</script>

<style scoped>
.auth-title {
  font-size: 22px; font-weight: 700; color: var(--ink);
  margin: 0 0 4px; letter-spacing: -0.02em;
}
.auth-sub {
  font-size: 13.5px; color: var(--ink3); margin: 0 0 24px; line-height: 1.55;
}
.auth-sub strong { color: var(--ink); }

.auth-form { display: flex; flex-direction: column; gap: 0; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }
.form-input {
  width: 100%; padding: 9px 12px; font-size: 13.5px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--bg); color: var(--ink); outline: none;
  transition: border-color 0.15s, box-shadow 0.15s;
  box-sizing: border-box; font-family: inherit;
}
.form-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 12%, transparent);
}
.input-error { border-color: var(--danger) !important; }
.error-msg { font-size: 12px; color: var(--danger); margin-top: 5px; display: block; }

.btn-submit {
  width: 100%; padding: 10px; font-size: 14px; font-weight: 600; margin-bottom: 20px;
  background: var(--accent); color: #fff; border: none; border-radius: 9px;
  cursor: pointer; font-family: inherit;
  transition: opacity 0.15s, transform 0.1s;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  min-height: 42px;
}
.btn-submit:hover:not(:disabled) { opacity: 0.9; }
.btn-submit:active:not(:disabled) { transform: scale(0.99); }
.btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
.submit-spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,0.35); border-top-color: #fff;
  border-radius: 50%; animation: spin 0.65s linear infinite; flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }

.auth-footer-link { text-align: center; }
.back-link {
  display: inline-flex; align-items: center; gap: 6px;
  font-size: 13px; color: var(--ink3); text-decoration: none;
  transition: color 0.13s;
}
.back-link:hover { color: var(--accent); }

/* Success state */
.success-state { text-align: center; }
.success-icon {
  width: 56px; height: 56px; border-radius: 50%;
  background: var(--ok-soft); color: var(--ok);
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
}
.resend-note {
  font-size: 13px; color: var(--ink4); margin-top: 16px; line-height: 1.5;
}
.link-btn {
  background: none; border: none; padding: 0;
  font-size: 13px; color: var(--accent); cursor: pointer; font-family: inherit;
}
.link-btn:hover { text-decoration: underline; }
</style>
