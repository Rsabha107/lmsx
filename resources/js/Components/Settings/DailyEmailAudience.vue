<template>
  <div class="de">
    <p v-if="!audience.event" class="de-empty">
      Pick an event at the top of the page to choose who receives its Daily Email.
    </p>

    <template v-else>
      <div class="de-head">
        <div>
          <div class="de-event">{{ audience.event.name }}</div>
          <div class="de-note">
            The email goes only to the people chosen here, and contains this event's movements, crew gaps and issues.
            With nobody chosen it can't be sent. Deactivated users are skipped.
          </div>
        </div>
      </div>

      <div class="de-chosen">
        <div class="de-label">Recipients <span class="de-count">{{ selected.length }}</span></div>
        <div v-if="!selected.length" class="de-none">Nobody yet.</div>
        <div v-else class="de-chips">
          <span v-for="u in chosenUsers" :key="u.id" :class="['de-chip', { 'de-chip--off': !u.active }]" :title="u.email">
            {{ u.name }}<span v-if="!u.active" class="de-chip-tag">inactive, not sent</span>
            <button type="button" class="de-chip-x" :aria-label="`Remove ${u.name}`" @click="toggle(u.id)">×</button>
          </span>
        </div>
      </div>

      <div class="de-search">
        <input v-model="search" type="text" class="de-input" placeholder="Search users by name, email or role…" aria-label="Search users" />
      </div>
      <div class="de-list" role="list">
        <label v-for="u in filtered" :key="u.id" class="de-row" role="listitem">
          <input type="checkbox" :checked="selected.includes(u.id)" @change="toggle(u.id)" />
          <span class="de-row-name">{{ u.name }}</span>
          <span class="de-row-email">{{ u.email }}</span>
          <span class="de-row-roles">{{ u.roles.map(r => r.replace(/_/g, ' ')).join(', ') }}</span>
        </label>
        <div v-if="!filtered.length" class="de-none">No users match “{{ search }}”.</div>
      </div>

      <div class="de-foot">
        <span :class="['de-dirty', { 'de-dirty--on': dirty }]">{{ dirty ? 'Unsaved changes' : 'All changes saved' }}</span>
        <Button variant="primary" size="sm" :disabled="!dirty || saving" :processing="saving" @click="save">Save recipients</Button>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import Button from '@/Components/Button.vue';

const props = defineProps({
  audience: { type: Object, required: true },
});

const selected = ref([...(props.audience.recipientIds ?? [])]);
const search = ref('');
const saving = ref(false);

// A reload (save, refresh, event switch) resets the picker to what is stored.
watch(() => props.audience.recipientIds, (ids) => { selected.value = [...(ids ?? [])]; });

const byId = computed(() => new Map((props.audience.users ?? []).map((u) => [u.id, u])));
const chosenUsers = computed(() => selected.value.map((id) => byId.value.get(id)).filter(Boolean));
const dirty = computed(() => {
  const a = [...selected.value].sort((x, y) => x - y).join(',');
  const b = [...(props.audience.recipientIds ?? [])].sort((x, y) => x - y).join(',');
  return a !== b;
});

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase();
  const users = (props.audience.users ?? []).filter((u) => u.active || selected.value.includes(u.id));
  if (!q) return users;
  return users.filter((u) => u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q) || u.roles.some((r) => r.toLowerCase().includes(q)));
});

function toggle(id) {
  const i = selected.value.indexOf(id);
  if (i === -1) selected.value.push(id);
  else selected.value.splice(i, 1);
}

function save() {
  saving.value = true;
  router.post('/setups/settings/daily-email', { user_ids: selected.value }, {
    preserveScroll: true,
    onFinish: () => { saving.value = false; },
  });
}
</script>

<style scoped>
.de { display: flex; flex-direction: column; gap: 14px; padding: 20px 28px; }
.de-empty, .de-none { font-size: 13px; color: var(--ink4); }
.de-event { font-size: 15px; font-weight: 700; color: var(--ink); }
.de-note { font-size: 12.5px; color: var(--ink3); margin-top: 2px; line-height: 1.5; }
.de-label { font-size: 12px; font-weight: 700; color: var(--ink2); margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
.de-count { font-weight: 600; color: var(--accent-fg); background: var(--accent-soft); border-radius: 20px; padding: 1px 7px; margin-left: 4px; }
.de-chips { display: flex; flex-wrap: wrap; gap: 6px; }
.de-chip {
  display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 600;
  background: var(--accent-soft); color: var(--accent-fg); border-radius: 20px; padding: 3px 4px 3px 10px;
}
.de-chip--off { background: var(--panel); color: var(--ink3); border: 1px solid var(--border); }
.de-chip-tag { font-size: 10.5px; font-weight: 500; }
.de-chip-x {
  border: none; background: none; cursor: pointer; color: inherit; font-size: 15px; line-height: 1;
  width: 20px; height: 20px; border-radius: 50%;
}
.de-chip-x:hover { background: color-mix(in srgb, currentColor 15%, transparent); }
.de-input {
  width: 100%; box-sizing: border-box; padding: 8px 10px; font-size: 13px; font-family: inherit;
  border: 1px solid var(--border); border-radius: 8px; background: var(--surface); color: var(--ink); outline: none;
}
.de-input:focus { border-color: var(--accent); }
.de-list { border: 1px solid var(--border); border-radius: 8px; max-height: 340px; overflow-y: auto; background: var(--surface); }
.de-list .de-none { padding: 14px; }
.de-row {
  display: grid; grid-template-columns: 20px minmax(140px, 1fr) minmax(160px, 1.2fr) minmax(100px, 1fr);
  align-items: center; gap: 10px; padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; font-size: 13px;
}
.de-row:last-child { border-bottom: none; }
.de-row:hover { background: var(--panel); }
.de-row-name { font-weight: 600; color: var(--ink); }
.de-row-email { color: var(--ink3); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.de-row-roles { color: var(--ink4); font-size: 12px; text-transform: capitalize; }
.de-foot { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.de-dirty { font-size: 12px; color: var(--ink4); }
.de-dirty--on { color: var(--warn); font-weight: 600; }
</style>
