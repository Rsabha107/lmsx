<template>
  <auth-layout>
    <h1 class="auth-title">Reset your password</h1>
    <p class="auth-sub">Choose a strong new password for your account.</p>

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
        />
        <span v-if="errors.email" class="error-msg">{{ errors.email }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">New password</label>
        <div class="pw-wrap">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="form-input pw-input"
            :class="{ 'input-error': errors.password }"
            placeholder="At least 8 characters"
            autocomplete="new-password"
          />
          <button type="button" class="pw-toggle" @click="showPassword = !showPassword" tabindex="-1">
            <svg v-if="showPassword" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
            </svg>
            <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <div v-if="form.password" class="strength-bar">
          <div class="strength-track">
            <div class="strength-fill" :class="`strength--${strength.level}`" :style="{ width: strength.width }" />
          </div>
          <span class="strength-label" :class="`strength-text--${strength.level}`">{{ strength.label }}</span>
        </div>
        <span v-if="errors.password" class="error-msg">{{ errors.password }}</span>
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirmation">Confirm new password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          :type="showPassword ? 'text' : 'password'"
          class="form-input"
          :class="{ 'input-error': errors.password_confirmation }"
          placeholder="••••••••"
          autocomplete="new-password"
        />
        <span v-if="errors.password_confirmation" class="error-msg">{{ errors.password_confirmation }}</span>
      </div>

      <button type="submit" class="btn-submit" :disabled="processing">
        <span v-if="processing" class="submit-spinner" />
        <span v-else>Reset password</span>
      </button>
    </form>
  </auth-layout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AuthLayout from '../../Components/AuthLayout.vue';

const props = defineProps({
  token: { type: String, required: true },
});

const errors = ref({});
const processing = ref(false);
const showPassword = ref(false);

const form = reactive({
  token: props.token,
  email: '',
  password: '',
  password_confirmation: '',
});

const strength = computed(() => {
  const pw = form.password;
  if (!pw) return { level: 'empty', label: '', width: '0%' };
  let score = 0;
  if (pw.length >= 8)  score++;
  if (pw.length >= 12) score++;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
  if (/\d/.test(pw)) score++;
  if (/[^A-Za-z0-9]/.test(pw)) score++;
  if (score <= 1) return { level: 'weak',   label: 'Weak',   width: '25%' };
  if (score <= 2) return { level: 'fair',   label: 'Fair',   width: '50%' };
  if (score <= 3) return { level: 'good',   label: 'Good',   width: '75%' };
  return              { level: 'strong', label: 'Strong', width: '100%' };
});

function submit() {
  processing.value = true;
  errors.value = {};
  router.post('/reset-password', form, {
    onError:  (e) => { processing.value = false; errors.value = e; },
    onFinish: () => { processing.value = false; },
  });
}
</script>

<style scoped>
.auth-title {
  font-size: 22px; font-weight: 700; color: var(--ink);
  margin: 0 0 4px; letter-spacing: -0.02em;
}
.auth-sub { font-size: 13.5px; color: var(--ink3); margin: 0 0 24px; }

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

.pw-wrap { position: relative; }
.pw-input { padding-right: 40px; }
.pw-toggle {
  position: absolute; right: 10px; top: 50%; transform: translateY(-50%);
  background: none; border: none; cursor: pointer;
  color: var(--ink4); padding: 4px; display: flex; transition: color 0.13s;
}
.pw-toggle:hover { color: var(--ink); }

/* Strength bar */
.strength-bar {
  display: flex; align-items: center; gap: 8px; margin-top: 8px;
}
.strength-track {
  flex: 1; height: 4px; background: var(--border); border-radius: 2px; overflow: hidden;
}
.strength-fill { height: 100%; border-radius: 2px; transition: width 0.3s ease, background 0.3s ease; }
.strength--weak   .strength-fill, .strength-fill.strength--weak   { background: var(--danger); }
.strength--fair   .strength-fill, .strength-fill.strength--fair   { background: var(--warn); }
.strength--good   .strength-fill, .strength-fill.strength--good   { background: #84CC16; }
.strength--strong .strength-fill, .strength-fill.strength--strong { background: var(--ok); }
.strength-fill.strength--weak   { background: var(--danger); }
.strength-fill.strength--fair   { background: var(--warn); }
.strength-fill.strength--good   { background: #84CC16; }
.strength-fill.strength--strong { background: var(--ok); }
.strength-label { font-size: 11px; font-weight: 600; min-width: 42px; text-align: right; }
.strength-text--weak   { color: var(--danger); }
.strength-text--fair   { color: var(--warn); }
.strength-text--good   { color: #84CC16; }
.strength-text--strong { color: var(--ok); }

.btn-submit {
  width: 100%; padding: 10px; font-size: 14px; font-weight: 600;
  background: var(--accent); color: #fff; border: none; border-radius: 9px;
  cursor: pointer; font-family: inherit; margin-top: 4px;
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
</style>
