<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\JobCheckpoint;
use App\Models\JobOperation;

class UpdateCheckpointEstimates extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'checkpoints:update-estimates';

    /**
     * The console command description.
     */
    protected $description = 'Update estimated_minutes for existing job checkpoints from their templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating estimated_minutes for job checkpoints...');
        
        $updated = 0;
        $skipped = 0;
        
        // Get all jobs with their movements
        $jobs = JobOperation::with(['movement', 'checkpoints'])->get();
        
        foreach ($jobs as $job) {
            $movement = $job->movement;
            
            if (!$movement || !$movement->checkpoint_template_id) {
                $this->warn("Job {$job->job_id} has no checkpoint template, skipping...");
                $skipped += $job->checkpoints->count();
                continue;
            }
            
            // Get the checkpoint template
            $template = \App\Models\CheckpointTemplate::with('checkpoints')->find($movement->checkpoint_template_id);
            
            if (!$template) {
                $this->warn("Checkpoint template {$movement->checkpoint_template_id} not found for job {$job->job_id}");
                $skipped += $job->checkpoints->count();
                continue;
            }
            
            // Update each job checkpoint with estimated_minutes from template
            foreach ($job->checkpoints as $jobCheckpoint) {
                // Find the matching checkpoint in the template by order or checkpoint_id
                $templateCheckpoint = null;
                
                if ($jobCheckpoint->checkpoint_id) {
                    // Match by checkpoint_id
                    $templateCheckpoint = $template->checkpoints->firstWhere('id', $jobCheckpoint->checkpoint_id);
                } else {
                    // Match by order
                    $templateCheckpoint = $template->checkpoints->firstWhere(function ($cp) use ($jobCheckpoint) {
                        return $cp->pivot->order === $jobCheckpoint->order;
                    });
                }
                
                if ($templateCheckpoint && $templateCheckpoint->pivot->estimated_minutes) {
                    $jobCheckpoint->update([
                        'estimated_minutes' => $templateCheckpoint->pivot->estimated_minutes,
                    ]);
                    $updated++;
                    $this->line("  ✓ Updated checkpoint {$jobCheckpoint->name} (Order {$jobCheckpoint->order}) → {$templateCheckpoint->pivot->estimated_minutes} min");
                } else {
                    $this->line("  ⊘ Skipped checkpoint {$jobCheckpoint->name} (no template match)");
                    $skipped++;
                }
            }
        }
        
        $this->newLine();
        $this->info("✓ Updated {$updated} checkpoints");
        $this->info("⊘ Skipped {$skipped} checkpoints");
        
        return 0;
    }
}
