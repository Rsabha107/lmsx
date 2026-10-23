<?php

namespace App\Console\Commands;

use App\Models\Deletion;
use App\Services\DeletionService;
use Illuminate\Console\Command;

/**
 * Empties the recycle bin of batches older than DeletionService::KEEP_DAYS (photos and
 * signatures go with them) and expires delete requests nobody decided on. Lists only,
 * unless --apply; the scheduler runs it with --apply every night.
 */
class PurgeDeletions extends Command
{
    protected $signature = 'deletions:purge {--apply : Remove them for good}';

    protected $description = 'Permanently remove recycle-bin batches past their keep period and expire old delete requests';

    public function handle(DeletionService $deletions): int
    {
        $due = Deletion::whereIn('status', Deletion::IN_BIN)->where('purge_after', '<=', now())->orderBy('id')->get();
        $stale = Deletion::where('status', 'pending')->whereNotNull('expires_at')->where('expires_at', '<=', now())->count();

        if (! $this->option('apply')) {
            $this->info("Dry run: {$due->count()} batch(es) would be purged and {$stale} request(s) expired. Add --apply to do it.");
            foreach ($due as $deletion) {
                $this->line("  #{$deletion->id} ".Deletion::label($deletion->kind, $deletion->item_count)." · deleted {$deletion->executed_at?->format('Y-m-d H:i')} by {$deletion->requester_name}");
            }

            return self::SUCCESS;
        }

        $expired = $deletions->expireOld();
        $purged = $deletions->purgeDue();
        $this->info("Purged {$purged} batch(es); expired {$expired} request(s).");

        return self::SUCCESS;
    }
}
