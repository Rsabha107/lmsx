<template>
  <auth-layout>
    <Head title="Get the app" />

    <h1 class="auth-title">Get the NAQLA LMS app</h1>
    <p class="auth-sub">Hi {{ name }}, your jobs are in the Android app. Download it and sign in with the same account.</p>

    <a href="/downloads/mobile-app" class="btn-download">Download APK</a>

    <ol class="steps">
      <li>Tap <strong>Download APK</strong>.</li>
      <li>Open the downloaded file.</li>
      <li>If Android asks, allow "Install unknown apps" for your browser.</li>
      <li>Open NAQLA LMS and sign in.</li>
    </ol>

    <div class="auth-footer-link">
      <button class="link-btn" @click="router.post('/logout')">Sign out</button>
    </div>
  </auth-layout>
</template>

<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AuthLayout from '../Components/AuthLayout.vue';

const name = computed(() => usePage().props.auth?.user?.name ?? '');
</script>

<style scoped>
.auth-title {
  font-size: 22px; font-weight: 700; color: var(--ink);
  margin: 0 0 4px; letter-spacing: -0.02em;
}
.auth-sub {
  font-size: 13.5px; color: var(--ink3); margin: 0 0 24px; line-height: 1.55;
}
.btn-download {
  display: flex; align-items: center; justify-content: center;
  width: 100%; min-height: 44px; box-sizing: border-box;
  font-size: 14px; font-weight: 600; text-decoration: none;
  background: var(--accent); color: #fff; border-radius: 9px;
}
.btn-download:hover { opacity: 0.9; }
.steps {
  margin: 20px 0; padding-left: 20px;
  font-size: 13px; color: var(--ink3); line-height: 1.8;
}
.steps strong { color: var(--ink); }
.auth-footer-link { text-align: center; }
.link-btn {
  background: none; border: none; padding: 0;
  font-size: 13px; color: var(--ink3); cursor: pointer; font-family: inherit;
}
.link-btn:hover { color: var(--danger); }
</style>
