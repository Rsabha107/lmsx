<template>
  <div
    style="
      flex: 1;
      overflow: auto;
      display: flex;
      flex-direction: column;
      gap: 10px;
    "
  >
    <div v-if="planMovementIds" class="ct-scope" role="group" aria-label="Conflicts shown">
      <button type="button" :class="['ct-scope-btn', { 'ct-scope-btn--on': scope === 'plan' }]" @click="scope = 'plan'">
        This plan <span>{{ planOpenCount }}</span>
      </button>
      <button type="button" :class="['ct-scope-btn', { 'ct-scope-btn--on': scope === 'event' }]" @click="scope = 'event'">
        Whole event <span>{{ props.conflicts.filter((c) => !c.accepted).length }}</span>
      </button>
    </div>
    <div
      v-if="openConflicts.length === 0"
      style="
        padding: 12px;
        background: var(--ok-soft, var(--panel));
        border: 1px solid var(--ok, var(--border));
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
      "
    >
      <svg-icon
        name="check"
        :size="20"
        style="color: var(--ok); flex-shrink: 0"
      />
      <div style="flex: 1; font-size: 12px; color: var(--ink2)">
        <b>No conflicts detected</b> — every movement in this {{ scoped ? 'plan' : 'event' }} has a
        valid window, free resources and realistic timings.
      </div>
    </div>
    <div
      v-else
      style="
        padding: 12px;
        background: var(--warn-soft);
        border: 1px solid var(--warn);
        border-radius: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
      "
    >
      <svg-icon
        name="warn"
        :size="20"
        style="color: var(--warn); flex-shrink: 0"
      />
      <div style="flex: 1; font-size: 12px; color: #92400e">
        <b>{{ openConflicts.length }} conflicts detected</b> — review and
        resolve before generating jobs.
        {{ openConflicts.filter((c) => c.sev === "high").length }} require
        immediate attention.
        <span v-if="shown.length > openConflicts.length">
          {{ shown.length - openConflicts.length }} accepted (listed at the bottom).
        </span>
      </div>
    </div>
    <div v-for="c in shown" :key="c.id" class="plan-table-card" :style="c.accepted ? 'opacity: 0.6' : ''">
      <div
        style="
          padding: 14px;
          display: flex;
          gap: 12px;
          align-items: flex-start;
        "
      >
        <div
          :style="{
            width: '36px',
            height: '36px',
            borderRadius: '8px',
            flexShrink: 0,
            background:
              c.sev === 'high'
                ? 'var(--danger-soft)'
                : c.sev === 'medium'
                ? 'var(--warn-soft)'
                : 'var(--panel)',
            color:
              c.sev === 'high'
                ? 'var(--danger)'
                : c.sev === 'medium'
                ? 'var(--warn)'
                : 'var(--ink3)',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }"
        >
          <svg-icon name="warn" :size="18" />
        </div>
        <div style="flex: 1">
          <div
            style="
              display: flex;
              align-items: center;
              gap: 8px;
              margin-bottom: 4px;
            "
          >
            <status-pill :tone="sevTone[c.sev]" :dot="true" size="sm">{{
              c.sev
            }}</status-pill>
            <span
              style="font-size: 13px; font-weight: 700; color: var(--ink)"
              >{{ c.type }}</span
            >
            <span
              style="
                font-size: 11px;
                color: var(--ink4);
                font-family: var(--mono);
              "
              >{{ c.id }}</span
            >
            <span
              v-if="c.when"
              style="font-size: 11px; color: var(--ink3)"
              >· {{ c.when }}</span
            >
            <span
              v-if="c.plan"
              style="font-size: 11px; color: var(--ink3)"
              >· {{ c.plan }}</span
            >
          </div>
          <div
            style="
              font-size: 12px;
              color: var(--ink2);
              line-height: 1.5;
              margin-bottom: 8px;
            "
          >
            {{ c.text }}
          </div>
          <div
            v-if="c.hint"
            style="
              font-size: 11.5px;
              color: var(--ink3);
              line-height: 1.5;
              margin-bottom: 8px;
            "
          >
            <b style="color: var(--ink2)">Suggested fix:</b> {{ c.hint }}
          </div>
          <div
            style="
              display: flex;
              gap: 6px;
              align-items: center;
              flex-wrap: wrap;
            "
          >
            <span style="font-size: 11px; color: var(--ink3)"
              >Affects:</span
            >
            <span
              v-for="a in c.affects"
              :key="a"
              style="
                font-family: var(--mono);
                font-size: 11px;
                padding: 2px 6px;
                background: var(--panel);
                border: 1px solid var(--border);
                border-radius: 4px;
                color: var(--ink);
              "
              >{{ a }}</span
            >
          </div>
          <div v-if="c.accepted" style="margin-top: 6px; font-size: 11.5px; color: var(--ink3)">
            <b style="color: var(--ink2)">Accepted</b> by {{ c.accepted.by }} · {{ c.accepted.at }} — “{{ c.accepted.reason }}”
          </div>
        </div>
        <div style="display: flex; flex-direction: column; gap: 4px">
          <Button variant="primary" size="sm" @click="emit('resolve', c)"
            >{{ c.accepted ? 'Review' : 'Resolve' }}</Button
          >
          <Button variant="secondary" size="sm" @click="emit('view', c)"
            >View</Button
          >
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import Button from '../Button.vue';
import StatusPill from '../StatusPill.vue';
import SvgIcon from '../SvgIcon.vue';

// All of the event's conflicts; accepted ones stay listed but don't count.
const props = defineProps({
  conflicts: { type: Array, default: () => [] },
  // With a plan open: its movement ids, to offer a "This plan" filter.
  planMovementIds: { type: Array, default: null },
});
const scope = defineModel('scope', { type: String, default: 'event' });

const emit = defineEmits(['resolve', 'view']);

const touchesPlan = (c) => (c.movement_ids ?? []).some((id) => props.planMovementIds.includes(id));
const scoped = computed(() => !!props.planMovementIds && scope.value === 'plan');
const shown = computed(() => (scoped.value ? props.conflicts.filter(touchesPlan) : props.conflicts));
const openConflicts = computed(() => shown.value.filter((c) => !c.accepted));
const planOpenCount = computed(() => (props.planMovementIds ? props.conflicts.filter((c) => !c.accepted && touchesPlan(c)).length : 0));

const sevTone = {
  high: 'danger',
  medium: 'warn',
  low: 'neutral',
};
</script>

<style scoped>
.ct-scope { display: inline-flex; align-self: flex-start; gap: 2px; padding: 3px; border-radius: 9px; background: var(--panel); }
.ct-scope-btn {
  height: 28px; padding: 0 12px; border: 0; border-radius: 6px; background: transparent;
  color: var(--ink3); font-size: 12px; font-weight: 500; cursor: pointer;
}
.ct-scope-btn span { margin-left: 4px; opacity: 0.7; }
.ct-scope-btn--on { background: var(--surface); color: var(--ink); box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1); }
.plan-table-card {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 10px;
  overflow: hidden;
  overflow-x: auto;
}
</style>
