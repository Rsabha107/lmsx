// One-off: move the Conflicts tab of Plans.vue into Components/Plans/ConflictsTab.vue.
import { readFileSync, writeFileSync } from 'node:fs';

const path = 'resources/js/Pages/Plans.vue';
const lines = readFileSync(path, 'utf8').split('\n').map((l) => l.replace(/\r$/, ''));
const expect = (n, text) => {
  if (!lines[n - 1].includes(text)) throw new Error(`Line ${n} is "${lines[n - 1]}", expected "${text}"`);
};

expect(1951, '<!-- Conflicts tab -->');
expect(1953, `v-else-if="activeTab === 'conflicts'"`);
expect(2141, '</div>');
expect(2142, '</template>');
expect(6479, 'const sevTone');
expect(6483, '};');

const template = lines.slice(1952 - 1, 2141)
  .filter((l) => !l.includes(`v-else-if="activeTab === 'conflicts'"`))
  .map((l) => (l.startsWith('    ') ? l.slice(4) : l))
  .map((l) => l
    .replace('@click="resolvingConflict = c"', `@click="emit('resolve', c)"`)
    .replace('@click="viewConflict(c)"', `@click="emit('view', c)"`));

const component = `<template>
${template.join('\n')}
</template>

<script setup>
import { computed } from 'vue';
import Button from '../Button.vue';
import StatusPill from '../StatusPill.vue';
import SvgIcon from '../SvgIcon.vue';

// All of the event's conflicts; accepted ones stay listed but don't count.
const props = defineProps({
  conflicts: { type: Array, default: () => [] },
});

const emit = defineEmits(['resolve', 'view']);

const openConflicts = computed(() => props.conflicts.filter((c) => !c.accepted));

const sevTone = {
  high: 'danger',
  medium: 'warn',
  low: 'neutral',
};
</script>

<style scoped>
.plan-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  overflow-x: auto;
}
</style>
`;

const kept = [];
lines.forEach((line, i) => {
  const n = i + 1;
  if (n === 1952) {
    kept.push(
      '      <ConflictsTab',
      `        v-else-if="activeTab === 'conflicts'"`,
      '        :conflicts="conflicts"',
      '        @resolve="resolvingConflict = $event"',
      '        @view="viewConflict"',
      '      />',
    );
  }
  if ((n >= 1952 && n <= 2141) || (n >= 6479 && n <= 6484)) return;
  kept.push(line);
  if (line.startsWith('import EditMovementModal from')) kept.push('import ConflictsTab from "../Components/Plans/ConflictsTab.vue";');
});

writeFileSync('resources/js/Components/Plans/ConflictsTab.vue', component.replace(/\n/g, '\r\n'));
writeFileSync(path, kept.join('\r\n'));
console.log(`Plans.vue: ${lines.length} -> ${kept.length} lines`);
