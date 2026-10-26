<template>
  <div class="ac">
    <div class="ac-block">
      <div class="ac-head">
        <label class="ac-label">Roles</label>
        <span class="ac-count">{{ roleIds.length }} selected</span>
      </div>
      <div :class="['ac-roles', { 'ac-roles--plain': plain }]">
        <button v-for="r in roles" :key="r.id" type="button"
          :class="['ac-role', { 'ac-on': picked(roleIds, r.id) && !has(hasRoleIds, r.id), 'ac-has': has(hasRoleIds, r.id) }]"
          :aria-pressed="picked(roleIds, r.id) || has(hasRoleIds, r.id)" :disabled="has(hasRoleIds, r.id)" @click="toggle(roleIds, r.id)">
          <span v-if="!plain" class="ac-box" aria-hidden="true">{{ picked(roleIds, r.id) || has(hasRoleIds, r.id) ? '✓' : '' }}</span>
          <span class="ac-text">
            <span class="ac-name">{{ roleLabel(r.name) }} <span v-if="has(hasRoleIds, r.id)" class="ac-note">has</span></span>
            <span class="ac-desc">{{ roleDesc(r.name) }}</span>
          </span>
        </button>
      </div>
      <span v-if="errors.role_ids || errors.roles" class="ac-error">{{ errors.role_ids || errors.roles }}</span>
    </div>

    <div class="ac-block">
      <div class="ac-head">
        <label class="ac-label">Events</label>
        <span v-if="allEvents" class="ac-count">Admins see all events</span>
        <span v-else class="ac-head-right">
          <span class="ac-count">{{ eventIds.length ? `${eventIds.length} selected` : 'None selected' }}</span>
          <button type="button" class="ac-link" @click="toggleAll">{{ allOn ? 'Clear' : 'Select all' }}</button>
        </span>
      </div>
      <div v-if="allEvents" class="ac-info">Admins see every event, including future ones.</div>
      <div v-else :class="['ac-events', { 'ac-events--error': errors.event_ids || errors.events }]">
        <button v-for="e in events" :key="e.id" type="button"
          :class="['ac-event', { 'ac-on': picked(eventIds, e.id) && !has(hasEventIds, e.id), 'ac-has': has(hasEventIds, e.id) }]"
          :aria-pressed="picked(eventIds, e.id) || has(hasEventIds, e.id)" :disabled="has(hasEventIds, e.id)" @click="toggle(eventIds, e.id)">
          <span class="ac-box" aria-hidden="true">{{ picked(eventIds, e.id) || has(hasEventIds, e.id) ? '✓' : '' }}</span>
          <span class="ac-event-name">{{ e.name }} <span v-if="has(hasEventIds, e.id)" class="ac-note">has</span></span>
          <span v-if="e.short_name" class="ac-code">{{ e.short_name }}</span>
        </button>
        <div v-if="!events.length" class="ac-empty">No events defined yet.</div>
      </div>
      <span v-if="errors.event_ids || errors.events" class="ac-error">{{ errors.event_ids || errors.events }}</span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { roleLabel, roleDesc } from '@/Composables/userRoles';

const props = defineProps({
  roles: { type: Array, default: () => [] },
  events: { type: Array, default: () => [] },
  // An existing account's roles and events: shown ticked and locked ("has").
  hasRoleIds: { type: Array, default: () => [] },
  hasEventIds: { type: Array, default: () => [] },
  errors: { type: Object, default: () => ({}) },
  // Edit dialog: highlighted tiles without tick boxes.
  plain: { type: Boolean, default: false },
});

// Arrays are changed in place so the parent form sees every change.
const roleIds = defineModel('roleIds', { type: Array, required: true });
const eventIds = defineModel('eventIds', { type: Array, required: true });

const has = (list, id) => list.includes(id);
const picked = (list, id) => list.includes(id);

/** An admin (or a role that reaches every event) needs no event. */
const allEvents = computed(() => props.roles.some((r) => r.all_events && (roleIds.value.includes(r.id) || props.hasRoleIds.includes(r.id))));

const open = computed(() => props.events.filter((e) => !props.hasEventIds.includes(e.id)));
const allOn = computed(() => open.value.length > 0 && open.value.every((e) => eventIds.value.includes(e.id)));

function toggle(list, id) {
  const i = list.indexOf(id);
  if (i === -1) list.push(id);
  else list.splice(i, 1);
}

function toggleAll() {
  eventIds.value.splice(0);
  if (!allOn.value) eventIds.value.push(...open.value.map((e) => e.id));
}

</script>

<style scoped>
.ac { display: flex; flex-direction: column; gap: 18px; }
.ac-block { display: flex; flex-direction: column; gap: 8px; }
.ac-head { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
.ac-head-right { display: flex; align-items: baseline; gap: 10px; }
.ac-label { font-size: 11px; font-weight: 600; color: var(--ink3); }
.ac-count { font-size: 11px; color: var(--ink4); }
.ac-link { border: none; background: none; padding: 0; font-size: 11px; font-weight: 600; color: var(--accent); cursor: pointer; font-family: inherit; }
.ac-error { font-size: 11.5px; color: var(--danger); }

.ac-roles { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 6px; }
.ac-roles--plain { grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; }
.ac-role {
  display: flex; align-items: flex-start; gap: 10px; padding: 9px 10px; border-radius: 8px; text-align: left;
  border: 1px solid var(--border); background: var(--surface); cursor: pointer; font-family: inherit;
}
.ac-roles--plain .ac-role { padding: 10px; border-radius: 10px; border-width: 1.5px; }
.ac-role:hover:not(:disabled) { border-color: var(--border-strong); }
.ac-on { border-color: var(--accent) !important; background: color-mix(in srgb, var(--accent) 5%, var(--surface)) !important; }
.ac-has { background: var(--panel) !important; cursor: default !important; }
.ac-text { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
.ac-name { font-size: 12px; font-weight: 600; color: var(--ink); }
.ac-note { font-weight: 400; color: var(--ink4); }
.ac-desc { font-size: 11px; color: var(--ink3); line-height: 1.35; }

.ac-box {
  margin-top: 1px; width: 16px; height: 16px; border-radius: 4px; flex: none;
  border: 1.5px solid var(--border-strong); background: var(--surface); color: #fff;
  font-size: 10px; display: flex; align-items: center; justify-content: center;
}
.ac-on .ac-box { background: var(--accent); border-color: var(--accent); }
.ac-has .ac-box { background: var(--ink4); border-color: var(--ink4); }

.ac-events { display: flex; flex-direction: column; border: 1px solid var(--border); border-radius: 10px; overflow: hidden; max-height: 260px; overflow-y: auto; }
.ac-events--error { border-color: var(--danger); }
.ac-event {
  display: flex; align-items: center; gap: 10px; padding: 9px 12px; border: none; border-bottom: 1px solid var(--border);
  background: var(--surface); cursor: pointer; text-align: left; font-family: inherit;
}
.ac-event:last-child { border-bottom: none; }
.ac-event:hover:not(:disabled) { background: var(--panel); }
.ac-event.ac-on { border-color: var(--border) !important; }
.ac-event-name { flex: 1; font-size: 12px; font-weight: 500; color: var(--ink); }
.ac-code { font-family: var(--font-mono, ui-monospace, monospace); font-size: 10px; color: var(--ink3); }
.ac-info { font-size: 12px; color: var(--ink2); background: var(--panel); border-radius: 8px; padding: 10px 12px; }
.ac-empty { font-size: 12px; color: var(--ink4); padding: 12px; }
</style>
