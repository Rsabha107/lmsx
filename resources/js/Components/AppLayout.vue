<template>
  <div :data-theme="theme" :data-density="density" style="min-height:100vh;background:var(--bg);color:var(--ink)">

    <!-- Sidebar overlay (mobile only) -->
    <transition name="fade">
      <div v-if="sidebarOpen && isMobile"
        class="sidebar-overlay"
        @click="sidebarOpen = false"
      />
    </transition>

    <!-- Sidebar -->
    <aside :class="['sidebar', sidebarOpen ? 'sidebar--open' : '', !isMobile && sidebarMini ? 'sidebar--mini' : '']">
      <div class="sidebar-logo">
        <span class="sidebar-logo-mark">LMS</span>
        <span class="sidebar-logo-name">Atlas Cup</span>
        <button v-if="isMobile" class="sidebar-close-btn" @click="sidebarOpen = false">
          <svg-icon name="x" />
        </button>
      </div>

      <nav class="sidebar-nav">
        <template v-for="item in navItems" :key="item.type === 'section' ? '__s__' + item.label : item.route">
          <div v-if="item.type === 'section'" class="nav-section-label">{{ item.label }}</div>
          <sidebar-link v-else :item="item" @click="onNavClick" />
        </template>
      </nav>

      <div class="sidebar-footer">
        <button class="sidebar-footer-btn" @click="toggleTheme" :title="theme === 'dark' ? 'Light mode' : 'Dark mode'">
          <svg-icon :name="theme === 'dark' ? 'sun' : 'moon'" />
          <span class="sidebar-footer-label">{{ theme === 'dark' ? 'Light' : 'Dark' }}</span>
        </button>
        <div class="sidebar-role-pill">{{ role }}</div>
        <button class="logout-btn" @click="logout" title="Sign out">
          <svg-icon name="logout" />
        </button>
      </div>
    </aside>

    <!-- Main content area -->
    <div class="main-wrap" :style="mainWrapStyle">

      <!-- Topbar -->
      <header class="topbar">
        <button class="topbar-menu-btn" @click="toggleSidebar">
          <svg-icon name="menu" />
        </button>
        <div class="topbar-title">
          <span class="topbar-event">{{ $page.props.eventName }}</span>
          <span class="topbar-sep">·</span>
          <span class="topbar-day">{{ $page.props.matchDay }}</span>
        </div>
        <div class="topbar-right">
          <span class="topbar-date">{{ $page.props.today }}</span>
          <div class="topbar-live-dot" />
          <span class="topbar-live-text">LIVE</span>
        </div>
      </header>

      <!-- Page content -->
      <main class="page-content">
        <slot />
      </main>
    </div>

    <!-- Mobile bottom nav -->
    <nav v-if="isMobile" class="mobile-bottom-nav">
      <mobile-nav-item v-for="item in mobileNavItems" :key="item.route" :item="item" />
    </nav>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, h } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { icons } from '../Composables/useIcons.js';
import Toast from './Toast.vue';

const page = usePage();

// Theme & density
const theme = ref(localStorage.getItem('lms-theme') || 'light');
const density = ref(localStorage.getItem('lms-density') || 'comfortable');
const role = ref(localStorage.getItem('lms-role') || 'Manager');

function toggleTheme() {
  theme.value = theme.value === 'dark' ? 'light' : 'dark';
  localStorage.setItem('lms-theme', theme.value);
}

function logout() {
  router.post('/logout');
}

// Sidebar
const sidebarOpen = ref(false);
const sidebarMini = ref(localStorage.getItem('lms-sidebar-mini') === 'true');
const windowWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1280);
const isMobile = computed(() => windowWidth.value < 768);

function toggleSidebar() {
  if (isMobile.value) {
    sidebarOpen.value = !sidebarOpen.value;
  } else {
    sidebarMini.value = !sidebarMini.value;
    localStorage.setItem('lms-sidebar-mini', sidebarMini.value);
  }
}

function onNavClick() {
  if (isMobile.value) sidebarOpen.value = false;
}

const mainWrapStyle = computed(() => {
  if (isMobile.value) return {};
  return {
    marginLeft: sidebarMini.value ? '52px' : '220px',
    transition: 'margin-left 0.22s ease',
  };
});

function onResize() { windowWidth.value = window.innerWidth; }
onMounted(() => {
  window.addEventListener('resize', onResize);
  if (!isMobile.value) sidebarOpen.value = true;
});
onUnmounted(() => window.removeEventListener('resize', onResize));

const navItems = [
  { label: 'Dashboard',     route: 'dashboard',        icon: 'dashboard' },
  { label: 'Schedule',      route: 'schedule',          icon: 'schedule' },
  { label: 'Planning',      route: 'plans',             icon: 'plans' },
  { label: 'Library',       route: 'library',           icon: 'database' },
  { label: 'Jobs Queue',    route: 'jobs',              icon: 'jobs' },
  { label: 'Jobs (Mobile)', route: 'jobs/mobile',       icon: 'phone' },
  { label: 'Job Detail',    route: 'job/JOB-2026-001',  icon: 'info' },
  { label: 'Live Tracker',  route: 'tracker',           icon: 'tracker' },
  { label: 'Fleet',         route: 'fleet',             icon: 'fleet' },
  { label: 'Teams',         route: 'teams',             icon: 'team' },
  { label: 'Contacts',      route: 'contacts',          icon: 'contacts' },
  { label: 'Notifications', route: 'notifications',     icon: 'bell' },
  { label: 'Daily Email',   route: 'email',             icon: 'email' },
  { label: 'Audit Trail',   route: 'audit',             icon: 'audit' },
  { label: 'Analytics',     route: 'analytics',         icon: 'chart' },
  { type: 'section', label: 'Setups' },
  { label: 'Users',         route: 'setups/users',       icon: 'user'   },
  { label: 'Roles',         route: 'setups/roles',       icon: 'shield' },
  { label: 'Permissions',   route: 'setups/permissions', icon: 'key'    },
];

const mobileNavItems = [
  { label: 'Dashboard', route: 'dashboard',  icon: 'dashboard' },
  { label: 'Schedule',  route: 'schedule',   icon: 'schedule' },
  { label: 'Jobs',      route: 'jobs/mobile', icon: 'jobs' },
  { label: 'Tracker',  route: 'tracker',    icon: 'tracker' },
];

// Icon component
const SvgIcon = (props) => h('svg', {
  xmlns: 'http://www.w3.org/2000/svg',
  fill: 'none',
  viewBox: '0 0 24 24',
  'stroke-width': '1.7',
  stroke: 'currentColor',
  width: 18,
  height: 18,
  innerHTML: icons[props.name] ?? '',
});

// Sidebar link — reads sidebarMini from closure for tooltip
const SidebarLink = (props, { emit }) => {
  const currentRoute = page.url;
  const href = props.item.route === 'dashboard' ? '/' : `/${props.item.route}`;
  
  let isActive;
  if (props.item.route === 'dashboard') {
    isActive = currentRoute === '/';
  } else {
    const routePath = `/${props.item.route}`;
    // Match exact route or route with query string, but check for child routes
    if (props.item.route.includes('/')) {
      // For nested routes like "jobs/mobile", allow child routes
      isActive = currentRoute.startsWith(routePath);
    } else {
      // For simple routes like "jobs", only match exact or with query string
      isActive = currentRoute === routePath || currentRoute.startsWith(routePath + '?');
    }
  }

  return h(Link, {
    href,
    class: ['sidebar-link', isActive ? 'sidebar-link--active' : ''],
    title: sidebarMini.value ? props.item.label : undefined,
    onClick: () => emit('click'),
  }, () => [
    h(SvgIcon, { name: props.item.icon }),
    h('span', { class: 'nav-label' }, props.item.label),
  ]);
};
SidebarLink.props = ['item'];
SidebarLink.emits = ['click'];

const MobileNavItem = (props) => {
  const currentRoute = page.url;
  const href = props.item.route === 'dashboard' ? '/' : `/${props.item.route}`;
  
  let isActive;
  if (props.item.route === 'dashboard') {
    isActive = currentRoute === '/';
  } else {
    const routePath = `/${props.item.route}`;
    // Match exact route or route with query string, but check for child routes
    if (props.item.route.includes('/')) {
      // For nested routes like "jobs/mobile", allow child routes
      isActive = currentRoute.startsWith(routePath);
    } else {
      // For simple routes like "jobs", only match exact or with query string
      isActive = currentRoute === routePath || currentRoute.startsWith(routePath + '?');
    }
  }

  return h(Link, {
    href,
    class: ['mobile-nav-item', isActive ? 'mobile-nav-item--active' : ''],
  }, () => [
    h(SvgIcon, { name: props.item.icon }),
    h('span', props.item.label),
  ]);
};
MobileNavItem.props = ['item'];
</script>

<style scoped>
/* Sidebar overlay */
.sidebar-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 40;
}
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

/* Sidebar */
.sidebar {
  position: fixed; top: 0; left: 0; bottom: 0;
  width: 220px;
  background: var(--surface);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  z-index: 50;
  overflow: hidden;
  transform: translateX(-100%);
  transition: transform 0.22s ease, width 0.22s ease;
}
.sidebar--open { transform: translateX(0); }
@media (min-width: 768px) {
  .sidebar { transform: translateX(0); }
}

/* Mini mode — icon rail */
.sidebar--mini { width: 52px; }

.sidebar-logo {
  display: flex; align-items: center; gap: 8px;
  padding: 16px 16px 12px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.sidebar-logo-mark {
  background: var(--accent); color: #fff;
  font-size: 11px; font-weight: 700; letter-spacing: 0.04em;
  padding: 3px 7px; border-radius: 5px; flex-shrink: 0;
}
.sidebar-logo-name {
  font-weight: 600; font-size: 14px; color: var(--ink); flex: 1;
  white-space: nowrap;
}
.sidebar--mini .sidebar-logo { justify-content: center; padding: 16px 8px 12px; }
.sidebar--mini .sidebar-logo-name { display: none; }
.sidebar-close-btn {
  background: none; border: none; cursor: pointer;
  color: var(--ink3); padding: 2px; display: flex;
}

.sidebar-nav {
  flex: 1; overflow-y: auto; overflow-x: hidden;
  padding: 8px 8px; display: flex; flex-direction: column; gap: 1px;
}

.nav-section-label {
  font-size: 10.5px; font-weight: 700; letter-spacing: 0.07em;
  text-transform: uppercase; color: var(--ink4);
  padding: 10px 10px 3px; margin-top: 4px;
  white-space: nowrap;
}
.sidebar--mini .nav-section-label {
  /* replaced by a thin divider when mini */
  font-size: 0; padding: 0; margin: 6px 8px;
  border-top: 1px solid var(--border);
}

:deep(.sidebar-link) {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 10px; border-radius: 7px;
  color: var(--ink3); text-decoration: none;
  font-size: 13.5px; font-weight: 500;
  transition: background 0.13s, color 0.13s;
  white-space: nowrap;
}
:deep(.sidebar-link:hover) { background: var(--panel); color: var(--ink); }
:deep(.sidebar-link--active) { background: var(--accent-soft); color: var(--accent-fg); }

.sidebar--mini :deep(.sidebar-link) {
  justify-content: center;
  padding: 9px 0;
  gap: 0;
}
.sidebar--mini :deep(.nav-label) { display: none; }

.sidebar-footer {
  border-top: 1px solid var(--border);
  padding: 10px 12px;
  display: flex; align-items: center; gap: 8px; flex-shrink: 0;
}
.sidebar-footer-btn {
  display: flex; align-items: center; gap: 6px;
  background: none; border: none; cursor: pointer;
  color: var(--ink3); font-size: 12.5px; flex: 1;
  padding: 4px; white-space: nowrap;
}
.sidebar-footer-btn:hover { color: var(--ink); }
.sidebar--mini .sidebar-footer { justify-content: center; padding: 10px 8px; }
.sidebar--mini .sidebar-footer-btn { flex: none; }
.sidebar--mini .sidebar-footer-label { display: none; }
.sidebar-role-pill {
  background: var(--accent-soft); color: var(--accent-fg);
  font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px;
  white-space: nowrap; flex-shrink: 0;
}
.sidebar--mini .sidebar-role-pill { display: none; }

.logout-btn {
  background: none; border: none; cursor: pointer; flex-shrink: 0;
  color: var(--ink4); padding: 5px; display: flex; border-radius: 6px;
  transition: color 0.13s, background 0.13s;
}
.logout-btn:hover { color: var(--danger); background: var(--danger-soft); }

/* Main wrap */
.main-wrap { min-height: 100vh; display: flex; flex-direction: column; }

/* Topbar */
.topbar {
  position: sticky; top: 0; z-index: 30;
  height: 52px;
  background: var(--surface);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
  padding: 0 16px;
}
.topbar-menu-btn {
  background: none; border: none; cursor: pointer;
  color: var(--ink3); padding: 4px; display: flex; border-radius: 6px;
}
.topbar-menu-btn:hover { background: var(--panel); color: var(--ink); }

.topbar-title { display: flex; align-items: center; gap: 6px; flex: 1; }
.topbar-event { font-weight: 700; font-size: 14px; color: var(--ink); }
.topbar-sep { color: var(--ink4); }
.topbar-day { font-size: 13px; color: var(--ink3); }

.topbar-right {
  display: flex; align-items: center; gap: 6px;
  font-size: 12.5px; color: var(--ink3);
}
.topbar-date { display: none; }
@media (min-width: 480px) { .topbar-date { display: block; } }
.topbar-live-dot {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--live); animation: pulseDot 1.4s ease-in-out infinite;
}
.topbar-live-text { font-size: 11px; font-weight: 700; color: var(--live); letter-spacing: 0.08em; }

/* Page content */
.page-content { flex: 1; padding: 20px 16px; }
@media (min-width: 768px) { .page-content { padding: 24px 24px; } }
@media (max-width: 767px) { .page-content { padding-bottom: 72px; } }

/* Mobile bottom nav */
.mobile-bottom-nav {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: var(--surface);
  border-top: 1px solid var(--border);
  display: flex; z-index: 40;
}
@media (min-width: 768px) { .mobile-bottom-nav { display: none; } }

:deep(.mobile-nav-item) {
  flex: 1; display: flex; flex-direction: column; align-items: center;
  gap: 3px; padding: 8px 4px 10px;
  font-size: 10px; color: var(--ink3); text-decoration: none;
  font-weight: 500;
}
:deep(.mobile-nav-item--active) { color: var(--accent); }
</style>
