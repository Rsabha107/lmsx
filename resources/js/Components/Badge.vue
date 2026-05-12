<template>
  <span :class="badgeClasses" :style="customStyle">
    <slot />
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: { 
    type: String, 
    default: 'default',
    validator: (value) => ['default', 'arrival', 'departure', 'transfer', 'match', 'draft', 'upcoming', 'active', 'completed', 'cancelled', 'icon'].includes(value)
  },
  type: {
    type: String,
    default: 'kind', // 'kind', 'status', 'plan-type'
  },
  customStyle: {
    type: Object,
    default: null
  }
});

const badgeClasses = computed(() => {
  const classes = [];
  
  if (props.type === 'kind') {
    classes.push('badge', 'badge--kind');
    if (props.variant !== 'default') {
      classes.push(`badge--kind-${props.variant}`);
    }
  } else if (props.type === 'status') {
    classes.push('badge', 'badge--status');
    if (props.variant !== 'default') {
      classes.push(`badge--status-${props.variant}`);
    }
  } else if (props.type === 'plan-type') {
    classes.push('badge', 'badge--plan-type');
    if (props.variant !== 'default') {
      classes.push(`badge--plan-type-${props.variant}`);
    }
  } else {
    classes.push('badge');
  }
  
  return classes;
});
</script>

<style scoped>
/* Base badge styles */
.badge {
  display: inline-flex;
  align-items: center;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
  white-space: nowrap;
}

/* Kind badges (movement types) */
.badge--kind-arrival {
  background: var(--ok-soft);
  color: var(--ok);
}

.badge--kind-departure {
  background: var(--danger-soft);
  color: var(--danger);
}

.badge--kind-transfer {
  background: var(--accent-soft);
  color: var(--accent-fg);
}

.badge--kind-match {
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fbbf24;
}

/* Status badges (plan status) */
.badge--status {
  border-radius: 999px;
  font-size: 10px;
}

.badge--status-draft {
  background: #f3f4f6;
  color: #6b7280;
}

.badge--status-upcoming {
  background: #dbeafe;
  color: #1e40af;
}

.badge--status-active {
  background: #d1fae5;
  color: #065f46;
}

.badge--status-completed {
  background: #e0e7ff;
  color: #4338ca;
}

.badge--status-cancelled {
  background: #fee2e2;
  color: #991b1b;
}

/* Plan type badges (icon badges) */
.badge--plan-type {
  width: 28px;
  height: 28px;
  padding: 0;
  justify-content: center;
  border-radius: 6px;
  font-size: 14px;
  background: var(--panel);
  border: 1px solid var(--border);
  text-transform: none;
}

.badge--plan-type-arrival {
  background: #dbeafe;
  border-color: #3b82f6;
}

.badge--plan-type-match {
  background: #fef3c7;
  border-color: #fbbf24;
}

.badge--plan-type-departure {
  background: #e0e7ff;
  border-color: #6366f1;
}

.badge--plan-type-transfer {
  background: #f3e8ff;
  border-color: #a855f7;
}

.badge--plan-type-other {
  background: var(--panel);
  border-color: var(--border);
}
</style>
