<template>
  <Teleport to="body">
    <section
      v-if="state.open"
      v-show="!state.minimized"
      ref="win"
      :class="['dw', { 'dw--max': maximized, 'dw--dragging': dragging }]"
      :style="maximized ? null : boxStyle"
      role="dialog"
      aria-label="Daleel"
      @keydown.esc.stop="close"
    >
      <header class="dw-head" @pointerdown="startDrag" @dblclick="toggleMax">
        <div class="dw-title">
          <span class="dw-name">Daleel</span>
          <span class="dw-sub">Read-only, scoped to what you can already see</span>
        </div>
        <div class="dw-actions" @pointerdown.stop>
          <button type="button" class="dw-btn" :disabled="!state.thread.length || state.sending" title="Start a new conversation" @click="clear">New chat</button>
          <Link href="/ai" class="dw-btn" title="Open Daleel as a page" @click="close">Open as page</Link>
          <button type="button" class="dw-icon" title="Minimise" aria-label="Minimise Daleel" @click="minimize">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M5 19h14" /></svg>
          </button>
          <button type="button" class="dw-icon" :title="maximized ? 'Restore size' : 'Maximise'" :aria-label="maximized ? 'Restore size' : 'Maximise'" @click="toggleMax">
            <svg v-if="!maximized" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" /></svg>
            <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="7" y="3" width="14" height="14" rx="2" /><path d="M3 7v12a2 2 0 0 0 2 2h12" /></svg>
          </button>
          <button type="button" class="dw-icon" title="Close" aria-label="Close Daleel" @click="close">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </header>

      <DaleelChat class="dw-body" autofocus />

      <div v-if="!maximized" class="dw-resize" title="Drag to resize" aria-hidden="true" @pointerdown.stop="startResize" />
    </section>

    <!-- Minimised: a tab in the corner that brings the window back as it was. -->
    <div v-if="state.open && state.minimized" class="dw-dock">
      <button type="button" class="dw-dock-main" aria-label="Restore Daleel" @click="restore">
        <span class="dw-dock-avatar">د</span>
        <span class="dw-dock-name">Daleel</span>
        <span v-if="state.sending" class="dw-dock-state">Looking it up…</span>
        <span v-else-if="state.unread" class="dw-dock-state dw-dock-state--new"><span class="dw-dock-dot" />New answer</span>
      </button>
      <button type="button" class="dw-dock-x" title="Close" aria-label="Close Daleel" @click="close">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M18 6 6 18M6 6l12 12" /></svg>
      </button>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import DaleelChat from './DaleelChat.vue';
import { useDaleel } from '../Composables/useDaleel';

const { state, clear, close, minimize, restore } = useDaleel();

// Position and size are remembered on this device.
const STORE_KEY = 'daleel.window';
const MIN_W = 420;
const MIN_H = 360;
const MARGIN = 12;

const win = ref(null);
const maximized = ref(false);
const dragging = ref(false);
const box = reactive({ x: 0, y: 0, w: 0, h: 0 });

function defaults() {
  const w = Math.min(900, window.innerWidth - 2 * MARGIN);
  const h = Math.min(700, window.innerHeight - 2 * MARGIN);
  return { x: Math.round((window.innerWidth - w) / 2), y: Math.round((window.innerHeight - h) / 2), w, h };
}

// Keep the window on screen (at least its header) whatever the screen size.
function clamp() {
  const vw = window.innerWidth;
  const vh = window.innerHeight;
  box.w = Math.min(Math.max(box.w, MIN_W), vw - 2 * MARGIN);
  box.h = Math.min(Math.max(box.h, MIN_H), vh - 2 * MARGIN);
  box.x = Math.min(Math.max(box.x, MARGIN), vw - box.w - MARGIN);
  box.y = Math.min(Math.max(box.y, MARGIN), vh - box.h - MARGIN);
}

function load() {
  let saved = null;
  try { saved = JSON.parse(localStorage.getItem(STORE_KEY) || 'null'); } catch { saved = null; }
  Object.assign(box, saved && saved.w ? saved : defaults());
  maximized.value = !!saved?.max;
  clamp();
}

function save() {
  try { localStorage.setItem(STORE_KEY, JSON.stringify({ ...box, max: maximized.value })); } catch { /* storage full or blocked */ }
}

const boxStyle = computed(() => ({ left: `${box.x}px`, top: `${box.y}px`, width: `${box.w}px`, height: `${box.h}px` }));

function track(e, onMove) {
  e.preventDefault();
  const start = { px: e.clientX, py: e.clientY, ...box };
  const move = (ev) => onMove(ev.clientX - start.px, ev.clientY - start.py, start);
  const up = () => {
    window.removeEventListener('pointermove', move);
    window.removeEventListener('pointerup', up);
    dragging.value = false;
    save();
  };
  dragging.value = true;
  window.addEventListener('pointermove', move);
  window.addEventListener('pointerup', up);
}

function startDrag(e) {
  if (maximized.value || e.button !== 0) return;
  track(e, (dx, dy, s) => { box.x = s.x + dx; box.y = s.y + dy; clamp(); });
}

function startResize(e) {
  if (e.button !== 0) return;
  track(e, (dx, dy, s) => { box.w = s.w + dx; box.h = s.h + dy; clamp(); });
}

function toggleMax() {
  maximized.value = !maximized.value;
  save();
}

const onResize = () => { clamp(); };
onMounted(() => { load(); window.addEventListener('resize', onResize); });
onUnmounted(() => window.removeEventListener('resize', onResize));
watch(() => state.open, (open) => { if (open) load(); });
</script>

<style scoped>
.dw {
  position: fixed; z-index: 900;
  display: flex; flex-direction: column;
  background: var(--surface); border: 1px solid var(--border); border-radius: 12px;
  box-shadow: 0 24px 64px rgba(17, 24, 39, 0.28), 0 2px 8px rgba(17, 24, 39, 0.08);
  overflow: hidden;
}
.dw--max { inset: 12px; width: auto; height: auto; }
.dw--dragging { user-select: none; }
.dw-head {
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  padding: 10px 12px 10px 16px; border-bottom: 1px solid var(--border);
  background: var(--panel); cursor: move; touch-action: none;
}
.dw--max .dw-head { cursor: default; }
.dw-title { display: flex; flex-direction: column; min-width: 0; }
.dw-name { font-size: 14px; font-weight: 700; color: var(--ink); }
.dw-sub { font-size: 11.5px; color: var(--ink3); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dw-actions { display: flex; align-items: center; gap: 6px; cursor: default; }
.dw-btn {
  height: 28px; padding: 0 10px; display: inline-flex; align-items: center;
  font-size: 12px; font-weight: 600; color: var(--ink2); text-decoration: none; cursor: pointer;
  border: 1px solid var(--border); border-radius: 6px; background: var(--surface);
}
.dw-btn:hover:not(:disabled) { color: var(--ink); border-color: var(--border-strong); }
.dw-btn:disabled { opacity: 0.5; cursor: default; }
.dw-icon {
  width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center;
  border: none; border-radius: 6px; background: none; color: var(--ink3); cursor: pointer;
}
.dw-icon:hover { background: var(--surface); color: var(--ink); }
.dw-body { flex: 1; min-height: 0; }
.dw-resize {
  position: absolute; right: 0; bottom: 0; width: 18px; height: 18px; cursor: nwse-resize; touch-action: none;
  background: linear-gradient(135deg, transparent 50%, var(--border-strong) 50%, var(--border-strong) 58%, transparent 58%, transparent 70%, var(--border-strong) 70%, var(--border-strong) 78%, transparent 78%);
}
@media (max-width: 640px) {
  .dw { inset: 0 !important; width: auto !important; height: auto !important; border-radius: 0; }
  .dw-resize, .dw-sub { display: none; }
  .dw-head { cursor: default; }
  .dw-dock { bottom: 76px; }
}

.dw-dock {
  position: fixed; right: 20px; bottom: 20px; z-index: 900;
  display: flex; align-items: center;
  background: var(--surface); border: 1px solid var(--border); border-radius: 999px;
  box-shadow: 0 10px 30px rgba(17, 24, 39, 0.18);
}
.dw-dock-main {
  display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 10px 0 6px;
  border: none; background: none; cursor: pointer; color: var(--ink); border-radius: 999px;
}
.dw-dock-avatar {
  width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
  background: var(--accent); color: #fff; font-size: 13px; font-weight: 700;
}
.dw-dock-name { font-size: 13px; font-weight: 700; }
.dw-dock-state { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--ink3); font-style: italic; }
.dw-dock-state--new { color: var(--accent-fg); font-style: normal; font-weight: 600; }
.dw-dock-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--accent); animation: dw-pulse 1.4s infinite; }
@keyframes dw-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .3; } }
.dw-dock-x {
  width: 28px; height: 28px; margin-right: 6px; display: inline-flex; align-items: center; justify-content: center;
  border: none; border-radius: 50%; background: none; color: var(--ink3); cursor: pointer;
}
.dw-dock-x:hover { background: var(--panel); color: var(--ink); }
</style>
