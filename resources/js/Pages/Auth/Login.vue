<template>
  <auth-layout>
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-sub">Sign in to your account to continue</p>

    <!-- Status flash (e.g. after password reset) -->
    <div v-if="$page.props.flash?.status" class="alert alert--ok">
      {{ $page.props.flash.status }}
    </div>

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

      <div class="form-group">
        <div class="pw-label-row">
          <label class="form-label" for="password">Password</label>
          <a href="/forgot-password" class="forgot-link">Forgot password?</a>
        </div>
        <div class="pw-wrap">
          <input
            id="password"
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            class="form-input pw-input"
            :class="{ 'input-error': errors.password }"
            placeholder="••••••••"
            autocomplete="current-password"
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
        <span v-if="errors.password" class="error-msg">{{ errors.password }}</span>
      </div>

      <div class="remember-row">
        <label class="remember-label">
          <input type="checkbox" v-model="form.remember" class="remember-check" />
          <span>Remember me</span>
        </label>
      </div>

      <button type="submit" class="btn-submit" :disabled="processing">
        <span v-if="processing" class="submit-spinner" />
        <span v-else>Sign in</span>
      </button>
    </form>
  </auth-layout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import AuthLayout from '../../Components/AuthLayout.vue';

const page = usePage();
const errors = ref({});
const processing = ref(false);
const showPassword = ref(false);

const form = reactive({
  email: '',
  password: '',
  remember: false,
});

function submit() {
  processing.value = true;
  errors.value = {};
  router.post('/login', form, {
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
.auth-sub {
  font-size: 13.5px; color: var(--ink3); margin: 0 0 24px;
}

.alert {
  border-radius: 8px; padding: 10px 14px;
  font-size: 13px; margin-bottom: 18px;
}
.alert--ok { background: var(--ok-soft); color: var(--ok); border: 1px solid color-mix(in srgb, var(--ok) 20%, transparent); }

.auth-form { display: flex; flex-direction: column; gap: 0; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-bottom: 6px; }

.pw-label-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.pw-label-row .form-label { margin-bottom: 0; }
.forgot-link { font-size: 12px; color: var(--accent); text-decoration: none; font-weight: 500; }
.forgot-link:hover { text-decoration: underline; }

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
  color: var(--ink4); padding: 4px; display: flex;
  transition: color 0.13s;
}
.pw-toggle:hover { color: var(--ink); }

.remember-row { margin-bottom: 20px; }
.remember-label {
  display: flex; align-items: center; gap: 8px;
  font-size: 13px; color: var(--ink3); cursor: pointer; user-select: none;
}
.remember-check { accent-color: var(--accent); width: 14px; height: 14px; cursor: pointer; }

.btn-submit {
  width: 100%; padding: 10px; font-size: 14px; font-weight: 600;
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
  border: 2px solid rgba(255,255,255,0.35);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.65s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
