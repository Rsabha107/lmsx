import { ref } from 'vue';

export const canStartJob = (job) => ['pending', 'dispatched'].includes(job?.status);
export const canRevertJob = (job) => job?.status === 'in-progress';
export const canCancelJob = (job) => ['pending', 'dispatched', 'in-progress'].includes(job?.status);
export const canReinstateJob = (job) => job?.status === 'cancelled' && !!job?.reinstate_to;

const LABELS = { pending: 'Scheduled', dispatched: 'Dispatched', 'in-progress': 'In Progress' };

/**
 * Start / mark scheduled / cancel a job through POST /jobs/{id}/status, behind
 * a confirmation. `getJob()` returns the job (its `id` is the job code);
 * `onChanged()` runs after a successful change.
 */
export function useJobStatusActions(getJob, onChanged) {
  const pending = ref(null);
  const changing = ref(false);
  const showError = ref(false);
  const errorMessage = ref('');

  const header = (job) => `<strong>${job.id}</strong> · ${job.team || ''}<br><br>`;

  function promptStart() {
    const job = getJob();
    if (!job) return;
    pending.value = {
      status: 'in-progress',
      title: 'Start Job?',
      confirmLabel: 'Start Job',
      message: header(job)
        + 'This will:<br>'
        + '&bull; Set the job status to <strong>In Progress</strong><br>'
        + '&bull; Record the start time as <strong>now</strong><br>'
        + '&bull; Stamp the actual departure on the linked movement<br>'
        + '&bull; Write an entry to the audit trail<br><br>'
        + 'Checkpoints are not affected — the crew still completes them in the field.',
    };
  }

  function promptRevert() {
    const job = getJob();
    if (!job) return;
    pending.value = {
      status: 'pending',
      title: 'Mark Job as Scheduled?',
      confirmLabel: 'Mark Scheduled',
      message: header(job)
        + 'Use this if the job was started by mistake. It will:<br>'
        + '&bull; Set the job status back to <strong>Scheduled</strong><br>'
        + '&bull; Clear the recorded start time<br>'
        + '&bull; Clear the actual departure on the linked movement<br>'
        + '&bull; Write an entry to the audit trail<br><br>'
        + 'Completed checkpoints are kept.',
    };
  }

  function promptCancel() {
    const job = getJob();
    if (!job) return;
    pending.value = {
      status: 'cancelled',
      title: 'Cancel Job?',
      confirmLabel: 'Cancel Job',
      tone: 'danger',
      message: header(job)
        + 'This will:<br>'
        + '&bull; Set the job status to <strong>Cancelled</strong><br>'
        + '&bull; Keep the job, its checkpoints and evidence (nothing is deleted)<br>'
        + '&bull; Write an entry to the audit trail',
      note: 'It can be reinstated later to the status it has now.',
    };
  }

  function promptReinstate() {
    const job = getJob();
    if (!job) return;
    const label = LABELS[job.reinstate_to] ?? job.reinstate_to;
    pending.value = {
      url: `/jobs/${job.id}/reinstate`,
      title: 'Reinstate Job?',
      confirmLabel: 'Reinstate',
      message: header(job)
        + 'This will:<br>'
        + `&bull; Put the job back to <strong>${label}</strong>, its status before it was cancelled<br>`
        + '&bull; Keep its checkpoints and recorded times<br>'
        + '&bull; Write an entry to the audit trail',
    };
  }

  async function confirm() {
    const change = pending.value;
    const job = getJob();
    if (!change || !job) return;

    changing.value = true;
    try {
      const response = await fetch(change.url ?? `/jobs/${job.id}/status`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify(change.status ? { status: change.status } : {}),
      });
      // A refused transition is a 422 with an explanation, so read the body first.
      const data = await response.json().catch(() => ({}));
      if (!response.ok || !data.success) {
        errorMessage.value = data.message || 'Failed to update job status. Please try again.';
        showError.value = true;
        return;
      }
      onChanged?.();
    } catch {
      errorMessage.value = 'Failed to update job status. Please try again.';
      showError.value = true;
    } finally {
      changing.value = false;
      pending.value = null;
    }
  }

  return { pending, changing, showError, errorMessage, promptStart, promptRevert, promptCancel, promptReinstate, confirm };
}
