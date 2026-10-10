<?php

namespace Tests\Unit;

use App\Models\JobCheckpoint;
use App\Models\JobOperation;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/** JobLifecycleService is the only place that moves a job or checkpoint through its states. */
class LifecycleBoundaryTest extends TestCase
{
    public function test_models_expose_no_state_mutators(): void
    {
        foreach (['markAsDone', 'markAsSkipped', 'start', 'verify'] as $method) {
            $this->assertFalse(method_exists(JobCheckpoint::class, $method), "JobCheckpoint::{$method}() bypasses the lifecycle service.");
        }

        $this->assertFalse(method_exists(JobOperation::class, 'updateProgress'), 'JobOperation::updateProgress() bypasses the lifecycle service.');
    }

    public function test_nothing_outside_the_services_writes_lifecycle_state(): void
    {
        $allowed = [
            'Services/JobLifecycleService.php',
            'Services/JobGenerationService.php',
            'Data/LmsData.php',
        ];

        $writes = [
            "/'state'\\s*=>\\s*'(done|skipped|active)'/",
            "/'status'\\s*=>\\s*'(dispatched|in-progress|completed|cancelled)'/",
            "/->(?:status|state)\\s*=\\s*'(dispatched|in-progress|completed|cancelled|done|skipped|active)'/",
        ];

        $offenders = [];

        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());

            if (in_array($path, $allowed, true)) {
                continue;
            }

            foreach ($writes as $pattern) {
                if (preg_match($pattern, $file->getContents())) {
                    $offenders[] = $path;
                    break;
                }
            }
        }

        $this->assertSame([], $offenders, 'These files set job/checkpoint state directly; route it through JobLifecycleService.');
    }
}
