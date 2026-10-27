// "Save changes to a template in use?": what an edit touches, asked before saving it.
// Generated jobs keep the steps they were made with; only movements without a job follow.
import { ref } from 'vue';

const plural = (n, one, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

export function useTemplateImpact(kind) {
  const confirm = ref(null);

  /** Resolves true to save now, false when the confirmation dialog was opened instead. */
  async function check(templateId) {
    let impact;
    try {
      const res = await fetch(`/admin/${kind}-templates/${templateId}/impact`, { headers: { Accept: 'application/json' } });
      if (!res.ok) return true;
      impact = await res.json();
    } catch {
      return true;
    }
    if (!impact.jobs && !impact.waiting) return true;

    const parts = [];
    if (impact.jobs) {
      parts.push(`<strong>${plural(impact.jobs, 'job')}</strong> already generated from it keep${impact.jobs === 1 ? 's' : ''} ${kind === 'checkpoint' ? 'their current steps' : 'their current steps and times'}.`);
    }
    if (impact.running) {
      parts.push(`<strong>${plural(impact.running, 'job is', 'jobs are')} running right now</strong>; ${impact.running === 1 ? 'it continues' : 'they continue'} with ${impact.running === 1 ? 'its' : 'their'} own steps.`);
    }
    if (impact.waiting) {
      parts.push(kind === 'checkpoint'
        ? `<strong>${plural(impact.waiting, 'planned movement')}</strong> without a job will get the new steps when ${impact.waiting === 1 ? 'its job is' : 'their jobs are'} generated.`
        : `<strong>${plural(impact.waiting, 'planned movement')}</strong> without a job follow${impact.waiting === 1 ? 's' : ''} the new legs: ${impact.waiting === 1 ? 'its' : 'their'} end time is recalculated from the new durations.`);
    }

    confirm.value = {
      title: 'Save changes to a template in use?',
      message: parts.join('<br><br>'),
      note: 'The change applies to jobs generated from now on.',
    };
    return false;
  }

  return { confirm, check };
}
