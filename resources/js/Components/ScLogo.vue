<template>
  <!-- The manual permits the logo on white or Pantone 294 C only, so the lockup
       carries its own background rather than inheriting the surface. -->
  <div class="sc-logo" :class="[`sc-logo--${background}`, { 'sc-logo--empty': failed }]" :title="fullName">
    <img
      v-if="!failed"
      :src="src"
      :alt="fullName"
      class="sc-logo-img"
      @error="failed = true"
    />
    <template v-else>
      <span class="sc-logo-text">
        <span class="sc-logo-text-line">Supreme Committee</span>
        <span class="sc-logo-text-line">for Delivery &amp; Legacy</span>
      </span>
      <span class="sc-logo-hint">add {{ src }}</span>
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue';

defineProps({
  // 'white' or 'blue' — the only two the SC Brand Manual allows.
  background: {
    type: String,
    default: 'white',
    validator: (v) => ['white', 'blue'].includes(v),
  },
  src: { type: String, default: '/images/sc-logo.svg' },
});

const fullName = 'Supreme Committee for Delivery & Legacy';
const failed = ref(false);
</script>

<style scoped>
.sc-logo {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 3px;
  /* Manual: clear space around the logo equal to twice the cap height. */
  padding: 10px;
  border-radius: 8px;
  /* The permitted white background is invisible on a white sidebar, so the
     lockup keeps a hairline edge to stay legible as a distinct element. */
  border: 1px solid var(--border);
}
.sc-logo--white { background: #FFFFFF; }
.sc-logo--blue { background: var(--brand-blue, #1B3668); border-color: var(--brand-blue, #1B3668); }
.sc-logo--empty { border-style: dashed; padding: 10px 14px; }

/* Manual p.06: minimum on-screen height for the two-line logo is 30px. */
.sc-logo-img { width: 100%; height: auto; min-height: 30px; display: block; }

.sc-logo-text {
  display: flex; flex-direction: column; gap: 1px;
  font-size: 10.5px; font-weight: 700; line-height: 1.25;
  letter-spacing: .01em; text-align: center;
}
.sc-logo--white .sc-logo-text { color: var(--brand-blue, #1B3668); }
.sc-logo--blue .sc-logo-text { color: #FFFFFF; }
.sc-logo-text-line { white-space: nowrap; }

.sc-logo-hint {
  font-size: 8.5px; font-family: var(--font-mono, monospace);
  color: var(--ink4, #9AA2B2);
}
.sc-logo--blue .sc-logo-hint { color: rgba(255, 255, 255, .6); }
</style>
