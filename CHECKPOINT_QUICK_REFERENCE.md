# Quick Reference: Checkpoint vs Movement Templates

## Visual Hierarchy

```
┌─────────────────────────────────────────────────────────────────┐
│ CHECKPOINT LIBRARY (Global reusable checkpoints)                │
│                                                                   │
│ ✓ Vehicle Dispatch    ✓ Arrived at Origin    ✓ Team on Board   │
│ ✓ Bags Loaded         ✓ Depart Origin        ✓ Arrive at Dest  │
│ ✓ Handoff Complete    ... and 8 more                            │
└─────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────┐
│ CHECKPOINT TEMPLATES (Sequences for ONE movement type)          │
│                                                                   │
│ ┌─────────────────────┐  ┌──────────────────┐                  │
│ │ Airport Arrival     │  │ Stadium Transfer │                   │
│ │ 7 steps · 75 min    │  │ 5 steps · 45 min │                   │
│ │                     │  │                  │                   │
│ │ 1. Dispatch         │  │ 1. Dispatch      │                   │
│ │ 2. Arrive Airport   │  │ 2. Arrive Origin │                   │
│ │ 3. Flight Landed    │  │ 3. Team on Board │                   │
│ │ 4. Team on Board    │  │ 4. Depart        │                   │
│ │ 5. Bags Loaded      │  │ 5. Arrive        │                   │
│ │ 6. Depart           │  └──────────────────┘                   │
│ │ 7. Arrive Hotel     │                                          │
│ └─────────────────────┘                                          │
└─────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────┐
│ MOVEMENT TEMPLATES (Complete multi-leg scenarios)                │
│                                                                   │
│ ┌───────────────────────────────────────────────────────────┐  │
│ │ Match Day - Standard (4 legs)                              │  │
│ │                                                             │  │
│ │  Leg 1: Hotel → Stadium                                    │  │
│ │         Uses: Stadium Transfer (5 checkpoints)             │  │
│ │         Time: 14:00 → 14:45                                │  │
│ │                                                             │  │
│ │  Leg 2: Stadium → Hotel                                    │  │
│ │         Uses: Match Return (6 checkpoints)                 │  │
│ │         Time: 22:30 → 23:20                                │  │
│ │                                                             │  │
│ │  Leg 3: Hotel → Media Center                               │  │
│ │         Uses: Quick Transfer (3 checkpoints)               │  │
│ │         Time: 09:00 → 09:30                                │  │
│ │                                                             │  │
│ │  Leg 4: Media Center → Hotel                               │  │
│ │         Uses: Quick Transfer (3 checkpoints)               │  │
│ │         Time: 11:30 → 12:00                                │  │
│ └───────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────┐
│ PLANS (Specific date, specific teams)                            │
│                                                                   │
│ PLN-2026-0418 · Match Day 4 · Sat, 18 Apr 2026                  │
│ Based on: Match Day - Standard template                          │
│ Teams: FC Meridian, Nordstad FK, Al-Sahra SC                    │
│ Status: Active                                                    │
└─────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────┐
│ MOVEMENTS (Individual trips in the plan)                         │
│                                                                   │
│ M-001: FC Meridian · Hotel Aurora → Stadium Azure                │
│        Checkpoint Template: Stadium Transfer                      │
│        Status: scheduled                                          │
└─────────────────────────────────────────────────────────────────┘
                                  ↓
┌─────────────────────────────────────────────────────────────────┐
│ JOBS (Generated for execution, checkpoints SNAPSHOTTED)         │
│                                                                   │
│ JOB-2401 · FC Meridian                                           │
│ ✓ Vehicle Dispatch      (done 14:05)                             │
│ ✓ Arrived at Origin     (done 14:18)                             │
│ ✓ Team on Board         (done 14:32)                             │
│ ⟳ Depart Origin         (active)                                 │
│ ○ Arrive at Stadium     (pending)                                │
└─────────────────────────────────────────────────────────────────┘
```

## Real Example Flow

### Scenario: Planning "FC Meridian" Match Day

**Step 1: Planner creates a new plan**
- Selects: "Match Day - Standard" movement template
- Date: April 20, 2026
- Result: 4 movements created automatically

**Step 2: Planner assigns team & resources**
- Movement 1 gets: FC Meridian, Coach 12, Driver K. Haddad
- Movement 1 uses: "Stadium Transfer" checkpoint template

**Step 3: Planner clicks "Generate Jobs"**
- System creates JOB-2401 from Movement 1
- System SNAPSHOTS the 5 checkpoints from "Stadium Transfer" template
- Job now has its own copy of checkpoints (won't change if template changes)

**Step 4: Field execution**
- Supervisor sees JOB-2401 on mobile app
- Supervisor completes checkpoints one by one:
  - ✓ Vehicle Dispatch (14:05)
  - ✓ Arrived at Hotel (14:18)
  - ✓ Team on Board (14:32)
  - ⟳ Depart Hotel (in progress...)

## Key Differences Table

| Aspect | Checkpoint Template | Movement Template |
|--------|-------------------|-------------------|
| **What it is** | Checklist for ONE trip | Complete itinerary with multiple trips |
| **Contains** | List of checkpoint steps in order | List of legs/movements |
| **Example** | "Airport Arrival" (7 steps) | "Match Day - Standard" (4 legs) |
| **Used by** | Movements and Movement Template Legs | Plans |
| **Reusability** | Many movements use same checkpoint template | Many plans use same movement template |
| **User sees it** | During job execution as checklist | During plan creation as blueprint |
| **Database** | `checkpoint_templates` table | `movement_templates` table |

## When to Use What

**Use Checkpoint Template when:**
- Defining workflow for a specific movement type
- Setting up standard procedures (airport pickup, stadium transfer, etc.)
- Creating checklists for field teams

**Use Movement Template when:**
- Defining a complete day's itinerary
- Creating repeatable scenarios (match days, training days)
- Planning multi-leg journeys

## Database Queries Cheat Sheet

```php
// Get checkpoint template with its steps
$template = CheckpointTemplate::with('checkpoints')->find($id);

// Get movement template with all legs and their checkpoint templates
$template = MovementTemplate::with('legs.checkpointTemplate.checkpoints')->find($id);

// Create a plan from movement template
$service = new JobGenerationService();
$plan = $service->createPlanFromTemplate($movementTemplate, $planData, $teamAssignments);

// Generate job from movement (snapshots checkpoints)
$job = $service->generateJobFromMovement($movement);

// Complete a checkpoint in a job
$checkpoint = JobCheckpoint::find($id);
$checkpoint->markAsDone($user, 'mobile', ['photo' => '...']);

// Get jobs for field execution
$jobs = JobOperation::active()
    ->where('supervisor_id', $supervisorId)
    ->with('checkpoints')
    ->get();

// Dispatch a job
$job->update(['status' => 'dispatched', 'dispatched_at' => now()]);

// Get job progress
$progress = $job->progress_percentage;
$nextCheckpoint = $job->checkpoints()->pending()->orderBy('order')->first();
```
