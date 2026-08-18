<template>
  <div class="date-picker">
    <button class="date-picker-btn" @click="shiftDay(-1)" title="Previous day" type="button">‹</button>
    <input ref="inputEl" type="text" class="date-picker-input" readonly />
    <button class="date-picker-btn" @click="shiftDay(1)" title="Next day" type="button">›</button>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

const props = defineProps({
  // ISO 'Y-m-d' string — kept as the wire format so it drops straight into
  // query params / Carbon::parse(), while the input itself displays d/m/Y.
  modelValue: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const inputEl = ref(null);
let fp = null;

function toIso(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

// Flatpickr parses date *strings* against the configured dateFormat ('d/m/Y'),
// so handing it our ISO 'Y-m-d' strings gets the digit groups matched into the
// wrong day/month/year slots. Passing a real Date object sidesteps its string
// parser entirely (flatpickr just clones it), so this is the only safe way to
// feed it a value.
function parseIso(iso) {
  const [y, m, d] = iso.split('-').map(Number);
  return new Date(y, m - 1, d);
}

onMounted(() => {
  fp = flatpickr(inputEl.value, {
    // Display d/m/Y in the field; we convert to/from ISO ourselves rather
    // than using flatpickr's altInput, which injects a second DOM element
    // outside this component's scoped styles.
    dateFormat: 'd/m/Y',
    allowInput: false,
    defaultDate: parseIso(props.modelValue),
    onChange: (selectedDates) => {
      if (!selectedDates[0]) return;
      const iso = toIso(selectedDates[0]);
      if (iso !== props.modelValue) emit('update:modelValue', iso);
    },
    // Flatpickr has no built-in "Today" shortcut, so append one to the
    // calendar's own footer — onReady only fires once per instance, so
    // this doesn't re-inject the button on every open.
    onReady: (_selectedDates, _dateStr, instance) => {
      const todayBtn = document.createElement('button');
      todayBtn.type = 'button';
      todayBtn.className = 'flatpickr-today-btn';
      todayBtn.textContent = 'Today';
      todayBtn.addEventListener('click', () => {
        instance.setDate(new Date(), true);
        instance.close();
      });
      instance.calendarContainer.appendChild(todayBtn);
    },
  });
});

onBeforeUnmount(() => {
  fp?.destroy();
});

// Sync the calendar when the value changes from outside (nav buttons below,
// or a parent reacting to browser back/forward) without re-emitting.
watch(() => props.modelValue, (v) => {
  if (v && fp && v !== toIso(fp.selectedDates[0] ?? new Date(NaN))) {
    fp.setDate(parseIso(v), false);
  }
});

function shiftDay(delta) {
  const d = parseIso(props.modelValue);
  d.setDate(d.getDate() + delta);
  emit('update:modelValue', toIso(d));
}
</script>

<style scoped>
.date-picker { display: flex; align-items: center; gap: 4px; }

.date-picker-btn {
  width: 26px; height: 26px; display: flex; align-items: center; justify-content: center;
  border-radius: 6px; border: 1px solid var(--border); background: none;
  color: var(--ink3); font-size: 15px; cursor: pointer; line-height: 1;
}
.date-picker-btn:hover { background: var(--panel); color: var(--ink); }

.date-picker-input {
  width: 108px; padding: 5px 8px; border-radius: 6px; border: 1px solid var(--border);
  background: none; font-size: 12.5px; color: var(--ink); cursor: pointer; text-align: center;
}
</style>

<style>
/* Global (unscoped): flatpickr renders its calendar in a teleported node
   outside this component's DOM, so scoped styles can't reach it. */
.flatpickr-calendar {
  font-family: inherit;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--border);
  border-radius: 8px;
}
.flatpickr-day.selected {
  background: var(--accent);
  border-color: var(--accent);
}
.flatpickr-day.today {
  border-color: var(--accent);
}
.flatpickr-day:hover {
  background: var(--panel);
}
.flatpickr-current-month {
  font-size: 14px;
}
.flatpickr-today-btn {
  display: block;
  width: calc(100% - 20px);
  margin: 0 10px 10px;
  padding: 6px;
  border-radius: 6px;
  border: 1px solid var(--accent);
  background: none;
  color: var(--accent);
  font-size: 12.5px;
  font-weight: 600;
  font-family: inherit;
  cursor: pointer;
}
.flatpickr-today-btn:hover {
  background: var(--accent-soft);
}
</style>
