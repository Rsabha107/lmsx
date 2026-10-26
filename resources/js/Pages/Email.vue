<template>
  <app-layout>
    <div class="page-header">
      <div>
        <h1 class="page-title">Daily Email</h1>
        <p class="page-sub">Preview and send the event's daily summary</p>
      </div>
      <div class="page-header-actions">
        <button class="btn btn--primary btn--sm" :disabled="!recipients.length || sending" @click="send">
          <svg-icon name="send" :size="15" /> {{ sending ? 'Sending…' : `Send to ${recipients.length} recipient${recipients.length === 1 ? '' : 's'}` }}
        </button>
      </div>
    </div>

    <NoActiveEvent v-if="!event" what="the daily email" />

    <template v-else>
    <div v-if="!recipients.length" class="audience-banner">
      No audience is set for {{ eventName }}, so the email can't be sent.
      <a v-if="canManageAudience" href="/setups/settings?section=dailyEmail">Choose recipients in Settings → Notifications</a>
      <span v-else>Ask an administrator to choose recipients in Settings → Notifications.</span>
    </div>

    <div v-if="sent" class="sent-banner">
      Email sent to {{ recipients.length }} recipient{{ recipients.length === 1 ? '' : 's' }}.
    </div>

    <div class="email-preview">
      <div class="email-meta">
        <div class="email-meta-row"><span class="meta-label">To:</span> <span>{{ recipientSummary }}</span></div>
        <div class="email-meta-row"><span class="meta-label">Subject:</span> <span>{{ subject }}</span></div>
      </div>

      <div class="email-body">
        <div class="email-header-block">
          <div class="email-logo-row">
            <span class="email-logo-mark">LMS</span>
            <span class="email-logo-name">{{ eventName }} · Daily Email</span>
          </div>
          <div class="email-title">{{ prettyDate }}</div>
          <div :class="['email-state', attentionTotal ? 'email-state--warn' : 'email-state--ok']">
            {{ attentionTotal ? `⚠ ${attentionTotal} ${attentionTotal === 1 ? 'item needs' : 'items need'} attention today or tomorrow` : '✓ Nothing needs attention today or tomorrow' }}
          </div>
        </div>

        <div v-for="block in attention" :key="block.key" class="attn">
          <div class="attn-title">{{ block.title }} <span class="attn-count">({{ block.count }})</span></div>
          <ul class="attn-rows">
            <li v-for="(row, i) in block.rows" :key="i">{{ row }}</li>
            <li v-if="block.count > block.rows.length" class="attn-more">and {{ block.count - block.rows.length }} more</li>
          </ul>
          <a :href="block.link" class="attn-link">{{ block.linkLabel }} →</a>
        </div>

        <div class="email-section">
          <div class="email-section-title">Yesterday ({{ shortDate(yesterday.date) }})</div>
          <p class="line">
            <strong>{{ yesterday.jobsCompleted }} of {{ yesterday.jobsPlanned }}</strong> jobs completed ·
            on time <strong>{{ yesterday.onTimePct !== null ? `${yesterday.onTimePct}%` : '—' }}</strong>
            <span v-if="yesterday.judged" class="muted">({{ yesterday.judged }} checkpoints judged)</span> ·
            avg delay <strong>{{ yesterday.avgDelay !== null ? `${yesterday.avgDelay} min` : '—' }}</strong> ·
            {{ yesterday.skipped }} checkpoint{{ yesterday.skipped === 1 ? '' : 's' }} skipped ·
            issues {{ yesterday.issuesRaised }} raised / {{ yesterday.issuesResolved }} resolved
          </p>
        </div>

        <div class="email-section">
          <div class="email-section-title">Today at a glance</div>
          <p class="line">
            <template v-if="glance.movements">
              <strong>{{ glance.movements }}</strong> movement{{ glance.movements === 1 ? '' : 's' }}, first at <strong>{{ glance.first }}</strong>,
              last at <strong>{{ glance.last }}</strong>. Busiest hour {{ glance.busiestHour }} ({{ glance.busiestCount }}).
            </template>
            <template v-else>No movements today. </template>
            <a :href="glance.link" class="attn-link">Open the Day Board →</a>
          </p>
        </div>

        <details v-if="schedule.length" class="email-section">
          <summary class="email-section-title email-section-title--toggle">Today's movements ({{ schedule.length }})</summary>
          <table class="email-table">
            <thead><tr>
              <th>Time</th><th>Team</th><th>Route</th><th>Vehicle</th><th>Status</th>
            </tr></thead>
            <tbody>
              <tr v-for="mv in schedule" :key="mv.id">
                <td class="mono">{{ mv.dep }}–{{ mv.arr }}</td>
                <td>{{ mv.code }}</td>
                <td>{{ mv.from }} → {{ mv.to }}</td>
                <td :class="{ muted: mv.vehicle === 'Unassigned' }">{{ mv.vehicle }}</td>
                <td :class="`status-${mv.status}`">{{ mv.status }}<span v-if="mv.delay"> (+{{ mv.delay }} min)</span></td>
              </tr>
            </tbody>
          </table>
        </details>

        <div class="email-footer">
          Generated by NAQLA LMS · {{ prettyDate }} · Confidential
        </div>
      </div>
    </div>
    </template>
  </app-layout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../Components/AppLayout.vue';
import SvgIcon from '../Components/SvgIcon.vue';
import NoActiveEvent from '../Components/NoActiveEvent.vue';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  event:          { type: Object, default: null },
  date:           { type: String, default: '' },
  subject:        { type: String, default: '' },
  attention:      { type: Array, default: () => [] },
  attentionTotal: { type: Number, default: 0 },
  yesterday:      { type: Object, default: () => ({}) },
  glance:         { type: Object, default: () => ({}) },
  schedule:       { type: Array, default: () => [] },
  recipients:     { type: Array, default: () => [] },
  canManageAudience: { type: Boolean, default: false },
});

const { success: showSuccessToast, error: showErrorToast } = useToast();

const sending = ref(false);
const sent = ref(false);

function send() {
  router.post('/email/send', { date: props.date }, {
    preserveScroll: true,
    onStart: () => { sending.value = true; },
    onFinish: () => { sending.value = false; },
    onSuccess: (page) => {
      const flash = page.props.flash || {};
      if (flash.error) {
        showErrorToast(flash.error);
        return;
      }
      sent.value = true;
      showSuccessToast(flash.success || 'Daily email sent.');
    },
    onError: () => showErrorToast('Could not send the daily email.'),
  });
}

const eventName = computed(() => props.event?.short_name || props.event?.name || 'NAQLA LMS');

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// 'YYYY-MM-DD' read as a calendar date, never shifted by the browser's timezone.
function parts(s) {
  const [y, m, d] = String(s).split('-').map(Number);
  return { y, m, d, wd: DAYS[new Date(Date.UTC(y, m - 1, d)).getUTCDay()] };
}
function shortDate(s) {
  if (!s) return '';
  const p = parts(s);
  return `${p.wd} ${p.d} ${MONTHS[p.m - 1]}`;
}
const prettyDate = computed(() => {
  if (!props.date) return '';
  const p = parts(props.date);
  return `${p.wd}, ${String(p.d).padStart(2, '0')} ${MONTHS[p.m - 1]} ${p.y}`;
});

const recipientSummary = computed(() => {
  if (!props.recipients.length) return 'No audience set for this event';
  const names = props.recipients.slice(0, 3).map((r) => r.name).join(' · ');
  const rest = props.recipients.length - 3;
  return rest > 0 ? `${names} +${rest} more (${props.recipients.length})` : `${names} (${props.recipients.length})`;
});
</script>
<style scoped>
.page-header {
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 12px; margin-bottom: 16px; flex-wrap: wrap;
}
.page-title { font-size: 20px; font-weight: 700; color: var(--ink); margin: 0 0 2px; }
.page-sub { font-size: 13px; color: var(--ink3); margin: 0; }
.page-header-actions { display: flex; gap: 8px; }
.btn {
  display: inline-flex; align-items: center; gap: 5px;
  border-radius: 7px; font-size: 13px; font-weight: 500; cursor: pointer;
  border: 1px solid transparent;
}
.btn--sm { padding: 6px 12px; }
.btn--ghost { background: none; border-color: var(--border); color: var(--ink3); }
.btn--ghost:hover { background: var(--panel); color: var(--ink); }
.btn--primary { background: var(--accent); color: #fff; }
.btn--primary:hover { opacity: 0.9; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.sent-banner {
  background: var(--ok-soft); color: var(--ok); border: 1px solid var(--ok);
  border-radius: 8px; padding: 10px 16px; margin-bottom: 14px; font-size: 13.5px; font-weight: 600;
}

.email-preview {
  background: var(--surface); border: 1px solid var(--border);
  border-radius: 10px; overflow: hidden; max-width: 720px;
}
.email-meta {
  padding: 14px 20px; border-bottom: 1px solid var(--border);
  display: flex; flex-direction: column; gap: 4px; background: var(--panel);
}
.email-meta-row { font-size: 13px; color: var(--ink2); }
.meta-label { font-weight: 600; color: var(--ink3); margin-right: 6px; width: 60px; display: inline-block; }
.email-body { padding: 24px; }

.email-header-block { margin-bottom: 24px; }
.email-logo-row { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
.email-logo-mark {
  background: var(--accent); color: #fff;
  font-size: 11px; font-weight: 700; padding: 3px 8px; border-radius: 5px;
}
.email-logo-name { font-size: 13px; color: var(--ink3); }
.email-title-row { display: flex; align-items: baseline; justify-content: space-between; }
.email-title { font-size: 22px; font-weight: 800; color: var(--ink); }
.email-date { font-size: 13px; color: var(--ink3); }

.email-section { margin-bottom: 24px; }
.email-section-title {
  font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em;
  color: var(--ink3); margin-bottom: 12px; padding-bottom: 6px;
  border-bottom: 1px solid var(--border);
}

.email-kpi-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px;
}
.email-kpi {
  background: var(--panel); border: 1px solid var(--border);
  border-radius: 8px; padding: 12px;
}
.email-kpi-label { font-size: 11px; color: var(--ink3); margin-bottom: 4px; }
.email-kpi-value { font-size: 18px; font-weight: 700; color: var(--ink); }
.email-kpi-delta { font-size: 12px; font-weight: 600; margin-top: 2px; }
.email-kpi-delta--ok      { color: var(--ok); }
.email-kpi-delta--warn    { color: var(--warn); }
.email-kpi-delta--danger  { color: var(--danger); }
.email-kpi-delta--primary { color: var(--accent-fg); }
.email-kpi-delta--neutral { color: var(--ink3); }

.email-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.email-table th {
  text-align: left; padding: 7px 10px;
  font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;
  color: var(--ink3); border-bottom: 1px solid var(--border); background: var(--panel);
}
.email-table td { padding: 9px 10px; border-bottom: 1px solid var(--border); }
.email-table tr:last-child td { border-bottom: none; }
.mono { font-family: var(--font-mono, monospace); font-size: 12px; }
.status-in-progress { color: var(--live); font-weight: 600; }
.status-delayed     { color: var(--warn); font-weight: 600; }
.status-done        { color: var(--ok); font-weight: 600; }
.status-scheduled   { color: var(--ink3); }

.audience-banner {
  background: var(--warn-soft, #fff4e0); color: var(--ink2); border: 1px solid var(--warn);
  border-radius: 8px; padding: 10px 16px; margin-bottom: 14px; font-size: 13.5px; max-width: 720px; box-sizing: border-box;
}
.audience-banner a { color: var(--accent); font-weight: 600; margin-left: 4px; }
.email-state { font-size: 14px; font-weight: 700; margin-top: 4px; }
.email-state--warn { color: var(--warn); }
.email-state--ok { color: var(--ok); }
.attn {
  border: 1px solid color-mix(in srgb, var(--warn) 35%, transparent); background: color-mix(in srgb, var(--warn) 6%, transparent);
  border-radius: 8px; padding: 12px 14px; margin-bottom: 12px;
}
.attn-title { font-size: 14px; font-weight: 700; color: var(--ink); margin-bottom: 6px; }
.attn-count { color: var(--warn); }
.attn-rows { margin: 0 0 8px; padding-left: 18px; font-size: 13px; color: var(--ink2); }
.attn-rows li { margin-bottom: 2px; }
.attn-more { color: var(--ink3); }
.attn-link { font-size: 13px; font-weight: 700; color: var(--accent); text-decoration: none; }
.attn-link:hover { text-decoration: underline; }
.line { font-size: 13px; color: var(--ink2); line-height: 1.6; margin: 0; }
.muted { color: var(--ink4); }
.email-section-title--toggle { cursor: pointer; list-style: revert; }
.status-cancelled { color: var(--ink4); text-decoration: line-through; }
.email-footer {
  margin-top: 24px; padding-top: 16px;
  border-top: 1px solid var(--border);
  font-size: 11.5px; color: var(--ink4); text-align: center;
}
</style>
