<template>
  <div :data-theme="theme" class="auth-root">
    <div class="auth-bg">

      <div class="auth-card">
        <!-- Card header: logo + app name -->
        <div class="auth-logo">
          <span class="auth-logo-mark">LMS</span>
          <span class="auth-logo-name">Atlas Cup</span>
        </div>

        <slot />
      </div>

      <div class="auth-footer">
        <button class="theme-toggle" @click="toggleTheme">
          <svg-icon :name="theme === 'dark' ? 'sun' : 'moon'" :size="14" />
          {{ theme === 'dark' ? 'Light mode' : 'Dark mode' }}
        </button>
      </div>

    </div>
  </div>
</template>

<script setup>
import { ref, h } from 'vue';
import { icons } from '../Composables/useIcons.js';

const theme = ref(localStorage.getItem('lms-theme') || 'light');
function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('lms-theme', theme.value);
}

const SvgIcon = (props) => h('svg', {
  xmlns: 'http://www.w3.org/2000/svg',
  fill: 'none',
  viewBox: '0 0 24 24',
  'stroke-width': '1.7',
  stroke: 'currentColor',
  width: props.size ?? 18,
  height: props.size ?? 18,
  innerHTML: icons[props.name] ?? '',
});
</script>

<style scoped>
.auth-root {
  min-height: 100vh;
  background: var(--bg);
  color: var(--ink);
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
  -webkit-font-smoothing: antialiased;
}

.auth-bg {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 24px 16px 80px;
  /* subtle dot grid */
  background-image: radial-gradient(var(--border) 1px, transparent 1px);
  background-size: 24px 24px;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px;
  padding: 36px 36px 32px;
  box-shadow: 0 4px 24px rgba(0,0,0,0.06), 0 1px 4px rgba(0,0,0,0.04);
  animation: slideIn 0.22s ease;
}

@media (max-width: 480px) {
  .auth-card { padding: 28px 22px 24px; }
}

/* Logo */
.auth-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 28px;
}
.auth-logo-mark {
  background: var(--accent);
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  letter-spacing: 0.06em;
  padding: 4px 9px;
  border-radius: 6px;
}
.auth-logo-name {
  font-weight: 700;
  font-size: 15px;
  color: var(--ink);
  letter-spacing: -0.01em;
}

/* Footer */
.auth-footer {
  position: fixed;
  bottom: 20px;
  left: 50%;
  transform: translateX(-50%);
}
.theme-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 20px;
  padding: 5px 12px;
  font-size: 12px;
  color: var(--ink3);
  cursor: pointer;
  font-family: inherit;
  transition: all 0.15s;
  white-space: nowrap;
}
.theme-toggle:hover {
  color: var(--ink);
  border-color: var(--accent);
}
</style>
