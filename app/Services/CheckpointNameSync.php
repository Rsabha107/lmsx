<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * A job copies each step's name from the library when it is created. Renaming a library
 * checkpoint renames that step on every job, past and future, so lists, exports and stats
 * all read one name. Touches updated_at so the mobile app's delta sync picks it up.
 */
class CheckpointNameSync
{
    /**
     * Job steps whose name differs from their library checkpoint, grouped per checkpoint.
     *
     * @param  list<int>|null  $checkpointIds  null = every checkpoint
     * @return Collection<int, object{checkpoint_id: int, code: string, name: string, old_names: string, rows: int, jobs: int}>
     */
    public function pending(?array $checkpointIds = null): Collection
    {
        return $this->stale($checkpointIds)
            ->groupBy('k.id', 'k.code', 'k.name')
            ->orderBy('k.code')
            ->get([
                'k.id as checkpoint_id', 'k.code', 'k.name',
                DB::raw('GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR " | ") as old_names'),
                DB::raw('COUNT(*) as `rows`'),
                DB::raw('COUNT(DISTINCT c.job_id) as jobs'),
            ]);
    }

    /** @param  list<int>|null  $checkpointIds  null = every checkpoint; returns the job steps renamed */
    public function apply(?array $checkpointIds = null, string $reason = 'Job step names synced'): int
    {
        $pending = $this->pending($checkpointIds);
        if ($pending->isEmpty()) {
            return 0;
        }

        $count = $this->stale($checkpointIds)->update(['c.name' => DB::raw('k.name'), 'c.updated_at' => now()]);

        AuditLog::change($reason, $pending->count().' checkpoint(s)', [
            'renamed' => $pending->map(fn ($p) => ['code' => $p->code, 'to' => $p->name, 'from' => $p->old_names, 'job_steps' => (int) $p->rows])->values()->all(),
        ]);

        return $count;
    }

    private function stale(?array $checkpointIds)
    {
        return DB::table('job_checkpoints as c')
            ->join('checkpoints as k', 'k.id', '=', 'c.checkpoint_id')
            ->whereRaw('BINARY c.name <> BINARY k.name')
            ->when($checkpointIds !== null, fn ($q) => $q->whereIn('k.id', $checkpointIds));
    }
}
