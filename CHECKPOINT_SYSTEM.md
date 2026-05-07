# Checkpoint & Movement Template System

## Overview

This system implements a hierarchical structure for managing logistics operations:

```
Checkpoint Library (Global)
    ↓
Checkpoint Templates (Sequences for movement types)
    ↓
Movement Templates (Multi-leg itineraries)
    ↓
Plans (Daily operations)
    ↓
Movements (Individual trips)
    ↓
Jobs (Generated operational units with snapshotted checkpoints)
```

## Database Structure

### Tables Created

1. **checkpoints** - Global library of checkpoint steps
2. **checkpoint_templates** - Sequences for different movement types
3. **checkpoint_checkpoint_template** - Pivot table linking checkpoints to templates
4. **movement_templates** - Pre-built multi-leg itineraries
5. **movement_template_legs** - Individual legs in a movement template
6. **plans** - Daily operational plans
7. **movements** - Individual movements in a plan
8. **jobs_operations** - Generated operational jobs
9. **job_checkpoints** - Snapshotted checkpoints for each job

## Models

- `Checkpoint` - Individual checkpoint definition
- `CheckpointTemplate` - Sequence of checkpoints for a movement type
- `MovementTemplate` - Complete multi-leg itinerary
- `MovementTemplateLeg` - Single leg in a movement template
- `Plan` - Daily plan containing movements
- `Movement` - Single trip/movement
- `JobOperation` - Generated job for execution
- `JobCheckpoint` - Snapshotted checkpoint in a job

## Usage Examples

### 1. Creating a Checkpoint Template

```php
use App\Models\Checkpoint;
use App\Models\CheckpointTemplate;

// Create a new checkpoint template
$template = CheckpointTemplate::create([
    'code' => 'TPL-CUSTOM-001',
    'name' => 'Custom Transfer',
    'movement_type' => 'transfer',
    'description' => 'Custom checkpoint sequence',
    'estimated_duration_minutes' => 45,
]);

// Attach checkpoints in order
$template->checkpoints()->attach([
    $checkpoint1->id => ['order' => 1, 'is_required' => true, 'estimated_minutes' => 5],
    $checkpoint2->id => ['order' => 2, 'is_required' => true, 'estimated_minutes' => 10],
    $checkpoint3->id => ['order' => 3, 'is_required' => true, 'estimated_minutes' => 30],
]);
```

### 2. Creating a Movement Template

```php
use App\Models\MovementTemplate;
use App\Models\MovementTemplateLeg;

$template = MovementTemplate::create([
    'code' => 'MVT-CUSTOM-001',
    'name' => 'Custom Match Day',
    'scenario_type' => 'match_day',
    'total_legs' => 2,
]);

// Add legs
MovementTemplateLeg::create([
    'movement_template_id' => $template->id,
    'checkpoint_template_id' => $stadiumTransfer->id,
    'order' => 1,
    'leg_type' => 'transfer',
    'from_location' => 'Hotel',
    'to_location' => 'Stadium',
    'estimated_duration_minutes' => 45,
]);
```

### 3. Creating a Plan from Template

```php
use App\Services\JobGenerationService;

$service = new JobGenerationService();

$plan = $service->createPlanFromTemplate(
    $movementTemplate,
    [
        'name' => 'Match Day 5',
        'date' => '2026-04-20',
        'status' => 'draft',
        'base_time' => now(),
    ],
    [
        'default' => $team->id, // Default team for all legs
        // Or specific: 1 => $team1->id, 2 => $team2->id
    ]
);
```

### 4. Generating Jobs from Movements

```php
$service = new JobGenerationService();

// Generate single job
$job = $service->generateJobFromMovement($movement, [
    'supervisor_id' => $supervisor->id,
]);

// Generate multiple jobs
$jobs = $service->generateJobsFromMovements(
    [1, 2, 3, 4], // Movement IDs
    ['supervisor_id' => $supervisor->id]
);
```

### 5. Completing Checkpoints

```php
$checkpoint = JobCheckpoint::find($id);
$checkpoint->markAsDone($user, 'mobile', [
    'photo' => 'path/to/photo.jpg',
    'signature' => 'path/to/signature.png',
    'gps' => '48.8566,2.3522',
    'notes' => 'Team confirmed on board',
]);

// This automatically updates job progress
```

## Workflow

### Planning Phase

1. **Create/Select Movement Template** → Defines the overall itinerary
2. **Create Plan** → For a specific date
3. **Add Movements** → Either from template or manual
4. **Assign Resources** → Teams, vehicles, drivers

### Execution Phase

1. **Generate Jobs** → Snapshot checkpoints from templates
2. **Dispatch Jobs** → Assign supervisors, notify drivers
3. **Execute Checkpoints** → Field teams mark completion
4. **Monitor Progress** → Real-time tracking
5. **Complete Jobs** → Final handoff and closeout

## Key Features

### Historical Integrity
- Checkpoints are **snapshotted** when jobs are generated
- Changes to templates don't affect existing jobs
- Full audit trail of what was planned vs. executed

### Reusability
- Checkpoint library prevents duplication
- Templates speed up planning
- Consistent processes across operations

### Flexibility
- Can customize checkpoint templates per movement type
- Can override templates on individual movements
- Can create ad-hoc movements without templates

## Migration & Seeding

```bash
# Run migrations
php artisan migrate

# Seed sample data
php artisan db:seed --class=CheckpointLibrarySeeder
php artisan db:seed --class=CheckpointTemplateLibrarySeeder
php artisan db:seed --class=MovementTemplateLibrarySeeder

# Or seed everything
php artisan db:seed
```

## Example Queries

```php
// Get all active checkpoint templates for transfers
$templates = CheckpointTemplate::active()
    ->forMovementType('transfer')
    ->with('checkpoints')
    ->get();

// Get all movements without jobs for a plan
$movements = Movement::where('plan_id', $plan->id)
    ->withoutJob()
    ->get();

// Get active jobs with progress
$jobs = JobOperation::active()
    ->with(['checkpoints', 'team', 'vehicle'])
    ->get();

// Find checkpoints pending completion
$pending = JobCheckpoint::pending()
    ->where('job_id', $job->id)
    ->orderBy('order')
    ->get();
```

## API Endpoints (Suggested)

```
# Plans
GET    /api/checkpoint-templates          - List checkpoint templates
POST   /api/checkpoint-templates          - Create checkpoint template
GET    /api/movement-templates            - List movement templates
POST   /api/plans                         - Create plan
POST   /api/plans/{id}/generate-jobs      - Generate jobs from plan

# Job Execution (Field Operations)
GET    /api/jobs                          - List jobs (with filters)
GET    /api/jobs/{id}                     - Show job details
POST   /api/jobs/{id}/dispatch            - Dispatch job
POST   /api/jobs/{id}/start               - Start job
POST   /api/jobs/{id}/complete            - Complete job
POST   /api/jobs/{id}/checkpoints/{cp}/complete - Complete checkpoint
POST   /api/jobs/{id}/checkpoints/{cp}/skip     - Skip checkpoint
GET    /api/jobs/{id}/progress            - Get job progress

# Mobile API
GET    /api/my-jobs                       - Get jobs for current supervisor
POST   /api/checkpoints/{cp}/quick-complete - Quick checkpoint completion
```
