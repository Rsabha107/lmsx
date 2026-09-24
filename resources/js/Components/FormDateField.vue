<template>
  <input
    ref="inputEl"
    type="text"
    :class="inputClass"
    :placeholder="placeholder"
    :disabled="disabled"
    :readonly="!allowInput"
  />
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

/**
 * Flatpickr as a self-contained form field: it owns its own instance lifecycle,
 * so it can be dropped inside a modal without the parent having to init and
 * destroy pickers by hand.
 *
 * The value it emits is parsed and formatted with `valueFormat`, independent of
 * what the field displays. Letting flatpickr parse a value string against the
 * *display* format is the subtle bug this avoids: given dateFormat 'd/m/Y', an
 * ISO 'Y-m-d' value has its digit groups matched into the wrong day/month/year
 * slots, silently shifting dates.
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  // date | time | datetime — sets the sensible flatpickr options and formats.
  mode: {
    type: String,
    default: 'date',
    validator: (v) => ['date', 'time', 'datetime'].includes(v),
  },
  // What the user sees. Defaults to the value format.
  displayFormat: { type: String, default: null },
  // What is emitted and accepted. Defaults per mode.
  valueFormat: { type: String, default: null },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  allowInput: { type: Boolean, default: true },
  // Appends a "Today" shortcut to the calendar, which flatpickr lacks.
  todayButton: { type: Boolean, default: false },
  inputClass: { type: String, default: 'form-input' },
  minDate: { type: [String, Date], default: null },
  maxDate: { type: [String, Date], default: null },
});

const emit = defineEmits(['update:modelValue']);

const MODES = {
  date: { options: { enableTime: false }, format: 'Y-m-d' },
  time: { options: { enableTime: true, noCalendar: true, time_24hr: true }, format: 'H:i' },
  datetime: { options: { enableTime: true, time_24hr: true }, format: 'Y-m-d H:i' },
};

const inputEl = ref(null);
let fp = null;

const valueFormat = () => props.valueFormat ?? props.displayFormat ?? MODES[props.mode].format;
const displayFormat = () => props.displayFormat ?? MODES[props.mode].format;

function emitValue(selectedDates, _dateStr, instance) {
  const next = selectedDates[0] ? instance.formatDate(selectedDates[0], valueFormat()) : '';
  if (next !== props.modelValue) emit('update:modelValue', next);
}

function applyValue(value) {
  if (!fp) return;

  if (!value) {
    if (fp.selectedDates.length) fp.clear(false);
    return;
  }

  const parsed = fp.parseDate(value, valueFormat());
  parsed ? fp.setDate(parsed, false) : fp.clear(false);
}

onMounted(() => {
  fp = flatpickr(inputEl.value, {
    ...MODES[props.mode].options,
    dateFormat: displayFormat(),
    allowInput: props.allowInput,
    minDate: props.minDate ?? undefined,
    maxDate: props.maxDate ?? undefined,
    onChange: emitValue,
    // With allowInput, a typed value is only parsed once the field closes.
    onClose: emitValue,
    onReady: (_dates, _str, instance) => {
      if (!props.todayButton) return;

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'flatpickr-today-btn';
      button.textContent = 'Today';
      button.addEventListener('click', () => {
        instance.setDate(new Date(), true);
        instance.close();
      });
      instance.calendarContainer.appendChild(button);
    },
  });

  applyValue(props.modelValue);
});

onBeforeUnmount(() => {
  fp?.destroy();
  fp = null;
});

watch(() => props.modelValue, (value) => {
  const current = fp?.selectedDates[0] ? fp.formatDate(fp.selectedDates[0], valueFormat()) : '';
  if (value !== current) applyValue(value);
});
</script>

<style>
/* Global (unscoped): flatpickr renders its calendar in a node outside this
   component's DOM, so scoped styles can't reach it. */
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
.flatpickr-time input {
  font-size: 13px;
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
  background: var(--accent);
  color: #fff;
}
</style>
