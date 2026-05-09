<?php

namespace App\Console\Commands;

use App\Models\JobCheckpoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigrateCheckpointFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkpoints:migrate-files {--dry-run : Preview changes without making them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate checkpoint files from old structure (checkpoints/) to new job-based structure (jobs/{id}/)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No files will be moved');
        }

        $this->info('Starting checkpoint file migration...');
        $this->newLine();

        // Get all checkpoints with file paths
        $checkpoints = JobCheckpoint::whereNotNull('photo_path')
            ->orWhereNotNull('signature_path')
            ->get();

        if ($checkpoints->isEmpty()) {
            $this->warn('No checkpoints with files found.');
            return 0;
        }

        $this->info("Found {$checkpoints->count()} checkpoints with files");
        $this->newLine();

        $bar = $this->output->createProgressBar($checkpoints->count());
        $bar->start();

        $movedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($checkpoints as $checkpoint) {
            $updated = false;
            $newPhotoPath = null;
            $newSignaturePath = null;

            // Migrate photo
            if ($checkpoint->photo_path) {
                $result = $this->migrateFile(
                    $checkpoint->photo_path,
                    $checkpoint->job_id,
                    'photos',
                    $dryRun
                );

                if ($result['status'] === 'moved') {
                    $newPhotoPath = $result['new_path'];
                    $movedCount++;
                    $updated = true;
                } elseif ($result['status'] === 'skipped') {
                    $skippedCount++;
                } elseif ($result['status'] === 'error') {
                    $errorCount++;
                }
            }

            // Migrate signature
            if ($checkpoint->signature_path) {
                $result = $this->migrateFile(
                    $checkpoint->signature_path,
                    $checkpoint->job_id,
                    'signatures',
                    $dryRun
                );

                if ($result['status'] === 'moved') {
                    $newSignaturePath = $result['new_path'];
                    $movedCount++;
                    $updated = true;
                } elseif ($result['status'] === 'skipped') {
                    $skippedCount++;
                } elseif ($result['status'] === 'error') {
                    $errorCount++;
                }
            }

            // Update database with new paths
            if ($updated && !$dryRun) {
                $checkpoint->update([
                    'photo_path' => $newPhotoPath ?? $checkpoint->photo_path,
                    'signature_path' => $newSignaturePath ?? $checkpoint->signature_path,
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Migration Summary:');
        $this->table(
            ['Status', 'Count'],
            [
                ['✓ Moved', $movedCount],
                ['⊘ Skipped (already migrated)', $skippedCount],
                ['✗ Errors', $errorCount],
            ]
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Migrate a single file
     */
    private function migrateFile(string $oldPath, int $jobId, string $type, bool $dryRun): array
    {
        // Check if already in new structure
        if (str_starts_with($oldPath, "jobs/{$jobId}/{$type}/")) {
            return ['status' => 'skipped', 'new_path' => $oldPath];
        }

        // Check if old file exists
        if (!Storage::disk('local')->exists($oldPath)) {
            $this->newLine();
            $this->error("File not found: {$oldPath}");
            return ['status' => 'error', 'new_path' => null];
        }

        // Extract filename from old path
        $filename = basename($oldPath);
        
        // Create new path
        $newPath = "jobs/{$jobId}/{$type}/{$filename}";

        // Check if new file already exists
        if (Storage::disk('local')->exists($newPath)) {
            return ['status' => 'skipped', 'new_path' => $newPath];
        }

        if (!$dryRun) {
            try {
                // Get file content
                $content = Storage::disk('local')->get($oldPath);
                
                // Save to new location
                Storage::disk('local')->put($newPath, $content);
                
                // Verify new file exists
                if (!Storage::disk('local')->exists($newPath)) {
                    throw new \Exception("Failed to create new file");
                }
                
                // Delete old file
                Storage::disk('local')->delete($oldPath);
                
            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error migrating {$oldPath}: " . $e->getMessage());
                return ['status' => 'error', 'new_path' => null];
            }
        }

        return ['status' => 'moved', 'new_path' => $newPath];
    }
}
