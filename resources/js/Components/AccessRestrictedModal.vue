<template>
  <Modal :show="show" max-width="480px" @close="show = false">
    <template #title>
      <span class="ar-title">
        <span class="ar-icon"><SvgIcon name="shield" :size="18" /></span>
        Action not available
      </span>
    </template>

    <p class="ar-message">{{ message }}</p>

    <p class="ar-subhead">Your agency account can:</p>
    <ul class="ar-list">
      <li><SvgIcon name="check" :size="14" /> Assign a movement's vehicle, driver and supervisor</li>
      <li><SvgIcon name="check" :size="14" /> Add, edit and remove vehicles, drivers and providers</li>
      <li><SvgIcon name="check" :size="14" /> View all other pages (read-only)</li>
    </ul>

    <p class="ar-note">Nothing was changed. Contact an administrator if you need wider access.</p>

    <template #footer>
      <Button variant="secondary" size="sm" @click="show = false">Close</Button>
      <Button variant="primary" size="sm" autofocus @click="goToCrewAssignment">Go to Crew Assignment</Button>
    </template>
  </Modal>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import Modal from './Modal.vue';
import Button from './Button.vue';
import SvgIcon from './SvgIcon.vue';

const show = ref(false);
const message = ref('');

let off = null;

onMounted(() => {
  // RestrictAgencyToCrewAssignment flags its 403s; anything else keeps Inertia's default handling.
  off = router.on('httpException', (event) => {
    const { response } = event.detail;
    const restricted = Object.keys(response.headers ?? {}).some((h) => h.toLowerCase() === 'x-access-restricted');
    if (response.status !== 403 || !restricted) return;

    event.preventDefault();
    try {
      message.value = JSON.parse(response.data).message;
    } catch {
      message.value = 'Your account is not allowed to make this change.';
    }
    show.value = true;
  });
});

onUnmounted(() => off?.());

function goToCrewAssignment() {
  show.value = false;
  router.visit('/crew-assignment');
}
</script>

<style scoped>
.ar-title { display: inline-flex; align-items: center; gap: 10px; }
.ar-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 32px; height: 32px; border-radius: 50%;
  background: color-mix(in srgb, var(--warn, #f59e0b) 15%, transparent);
  color: var(--warn, #f59e0b);
}
.ar-message { margin: 0 0 16px; color: var(--ink); font-size: 14px; line-height: 1.5; }
.ar-subhead { margin: 0 0 8px; color: var(--ink2); font-size: 13px; font-weight: 600; }
.ar-list { list-style: none; margin: 0 0 16px; padding: 0; display: grid; gap: 6px; }
.ar-list li { display: flex; align-items: center; gap: 8px; color: var(--ink2); font-size: 13px; }
.ar-list li :deep(svg) { color: var(--success, #16a34a); flex-shrink: 0; }
.ar-note { margin: 0; color: var(--ink3); font-size: 12px; }
</style>
