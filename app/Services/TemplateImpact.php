<?php

namespace App\Services;

use App\Models\CheckpointTemplate;
use App\Models\JobOperation;
use App\Models\Movement;
use App\Models\MovementTemplate;
use Illuminate\Database\Eloquent\Builder;

/**
 * What editing a template would touch, shown before the save. Generated jobs keep their own
 * steps (they are copied at generation); only movements still without a job follow the change.
 */
class TemplateImpact
{
    public function forCheckpointTemplate(CheckpointTemplate $template): array
    {
        return $this->summarise(fn (Builder $q) => $q->where('checkpoint_template_id', $template->id));
    }

    public function forMovementTemplate(MovementTemplate $template): array
    {
        return $this->summarise(fn (Builder $q) => $q->whereHas('plan', fn ($p) => $p->where('movement_template_id', $template->id)));
    }

    /** @return array{jobs: int, running: int, waiting: int} */
    private function summarise(\Closure $movements): array
    {
        $jobs = fn () => JobOperation::query()
            ->where('status', '!=', 'cancelled')
            ->whereHas('movement', $movements);

        return [
            // Generated jobs: unchanged by the edit.
            'jobs' => $jobs()->count(),
            // Being worked right now (dispatched or in progress).
            'running' => $jobs()->whereIn('status', ['dispatched', 'in-progress'])->count(),
            // Planned movements with no job yet: these follow the edit.
            'waiting' => Movement::query()->where($movements)->withoutJob()
                ->whereNotIn('status', ['cancelled', 'completed'])->count(),
        ];
    }
}
