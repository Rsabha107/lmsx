# Duplicate Movement Prevention System

## Overview

This system prevents duplicate movements using a two-tier approach:
1. **Strict blocking** for flight and match-based movements
2. **Smart warnings** for transfers, training, and daily ops

**Key Feature**: The system checks **individual movement legs** from templates, not just the template type. This ensures accurate duplicate detection for multi-leg templates.

---

## Database Layer

### Migration
**File**: `2026_06_18_000001_add_unique_constraints_to_movements.php`

**Indexes created:**
```php
// For flight-based duplicate detection
idx_team_flight_kind: (team_id, flight_id, kind)

// For match-based duplicate detection  
idx_team_match_kind: (team_id, match_id, kind)

// For similar movement detection
idx_team_movement_details: (team_id, kind, from_location, to_location, window_start)
```

**Run migration:**
```bash
php artisan migrate
```

---

## Backend Logic

### Controller Methods
**File**: `app/Http/Controllers/PlanManagementController.php`

#### 1. `checkDuplicateMovement(array $data): array`
Protected method that checks for duplicates based on movement type.

**Returns:**
```php
[
    'exists' => true|false,
    'strict' => true|false,  // true = block, false = warn
    'message' => 'Description of duplicate',
    'existing' => [
        'id' => 123,
        'code' => 'M-2026-001',
        'plan_name' => 'Match Day 4',
        'window_start' => '2026-06-18 14:30:00'
    ]
]
```

#### 2. `checkDuplicate(Request $request)`
Public API endpoint for frontend duplicate checking.

**Route**: `POST /api/check-duplicate`

**Request:**
```json
{
  "kind": "arrival",
  "team_id": 1,
  "flight_id": 5,
  "match_id": null,
  "from_location": "Airport",
  "to_location": "Hotel Solene",
  "window_start": "2026-06-18 14:30:00"
}
```

**Response:**
```json
{
  "exists": true,
  "strict": true,
  "message": "This team already has an arrival movement for this flight.",
  "existing": {
    "id": 45,
    "code": "M-2026-045",
    "plan_name": "Team Brazil Arrival"
  }
}
```

---

## Duplicate Detection Rules

### Strict Blocking (prevents creation)

#### 1. Arrival Movements
**Rule**: `team_id + flight_id + kind='arrival'`

**Example:**
```
❌ BLOCKED: Team Spain already has arrival for Flight SK205
```

**Database query:**
```php
Movement::where('team_id', $teamId)
    ->where('flight_id', $flightId)
    ->where('kind', 'arrival')
    ->exists()
```

#### 2. Departure Movements
**Rule**: `team_id + flight_id + kind='departure'`

**Example:**
```
❌ BLOCKED: Team Brazil already has departure for Flight AF456
```

#### 3. Match Movements
**Rule**: `team_id + match_id + kind='match'`

**Example:**
```
❌ BLOCKED: Team Germany already has match movement for Match #45
```

### Smart Warnings (allows creation with confirmation)

#### 4. Transfer/Training/Daily Ops
**Rule**: `team_id + kind + from_location + to_location + window_start (±30 minutes)`

**Example:**
```
⚠️ WARNING: Similar transfer found: Hotel Solene → Stadium Azure at 14:30
□ Create anyway?
```

**Database query:**
```php
Movement::where('team_id', $teamId)
    ->where('kind', $kind)
    ->where('from_location', $fromLocation)
    ->where('to_location', $toLocation)
    ->whereBetween('window_start', [$start->subMinutes(30), $start->addMinutes(30)])
    ->exists()
```

---

## Frontend Integration

### Example: Vue.js Form with Duplicate Check

```vue
<script setup>
import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const form = ref({
  team_id: null,
  flight_id: null,
  match_id: null,
  kind: 'transfer',
  from_location: '',
  to_location: '',
  window_start: ''
});

const duplicateWarning = ref(null);
const showDuplicateModal = ref(false);

// Check for duplicates when key fields change
watch([
  () => form.value.team_id,
  () => form.value.flight_id,
  () => form.value.match_id,
  () => form.value.kind,
  () => form.value.from_location,
  () => form.value.to_location,
  () => form.value.window_start
], async () => {
  await checkDuplicate();
}, { deep: true });

async function checkDuplicate() {
  // Skip if required fields missing
  if (!form.value.team_id || !form.value.kind) {
    duplicateWarning.value = null;
    return;
  }

  try {
    const response = await axios.post('/api/check-duplicate', form.value);
    
    if (response.data.exists) {
      duplicateWarning.value = response.data;
      
      // If strict, show error immediately
      if (response.data.strict) {
        showDuplicateModal.value = true;
      }
    } else {
      duplicateWarning.value = null;
    }
  } catch (error) {
    console.error('Duplicate check failed:', error);
  }
}

function submitForm() {
  // If strict duplicate, block submission
  if (duplicateWarning.value?.strict) {
    alert(duplicateWarning.value.message);
    return;
  }

  // If soft warning, ask for confirmation
  if (duplicateWarning.value && !duplicateWarning.value.strict) {
    if (!confirm(duplicateWarning.value.message + '\n\nCreate anyway?')) {
      return;
    }
  }

  // Submit normally
  router.post('/plans', form.value);
}
</script>

<template>
  <form @submit.prevent="submitForm">
    <!-- Form fields -->
    
    <!-- Duplicate Warning Display -->
    <div 
      v-if="duplicateWarning?.exists && !duplicateWarning.strict" 
      class="warning-box"
    >
      <svg><!-- Warning icon --></svg>
      <div>
        <strong>Similar Movement Found</strong>
        <p>{{ duplicateWarning.message }}</p>
        <a :href="`/plans/${duplicateWarning.existing.id}`">
          View existing: {{ duplicateWarning.existing.code }}
        </a>
      </div>
    </div>

    <!-- Strict Duplicate Error -->
    <div 
      v-if="duplicateWarning?.exists && duplicateWarning.strict" 
      class="error-box"
    >
      <svg><!-- Error icon --></svg>
      <div>
        <strong>Duplicate Movement</strong>
        <p>{{ duplicateWarning.message }}</p>
        <a :href="`/plans/${duplicateWarning.existing.id}`">
          Go to existing: {{ duplicateWarning.existing.code }}
        </a>
      </div>
    </div>

    <button 
      type="submit"
      :disabled="duplicateWarning?.strict"
    >
      Create Movement
    </button>
  </form>
</template>
```

---

## User Experience Flow

### Strict Duplicate (Arrival/Departure/Match)

1. User fills form and selects Team Spain + Flight SK205 + kind=arrival
2. System checks for duplicate via API
3. **Duplicate found** → Red error box appears
4. Submit button is **disabled**
5. User must click "View existing" link to see the duplicate
6. User cannot proceed without changing team or flight

### Soft Warning (Transfer/Training/Daily Ops)

1. User fills form: Team Spain + Hotel → Stadium + 14:30 + kind=transfer
2. System checks for duplicate via API
3. **Similar movement found** → Yellow warning box appears
4. Submit button remains **enabled**
5. On submit, confirmation dialog appears:
   > ⚠️ Similar transfer found: Hotel Solene → Stadium Azure at 14:30
   > Create anyway?
6. User can choose to proceed or cancel

---

## API Usage Examples

### Check for Arrival Duplicate

```bash
curl -X POST /api/check-duplicate \
  -H "Content-Type: application/json" \
  -d '{
    "kind": "arrival",
    "team_id": 1,
    "flight_id": 5
  }'
```

**Response (duplicate exists):**
```json
{
  "exists": true,
  "strict": true,
  "message": "This team already has an arrival movement for this flight.",
  "existing": {
    "id": 45,
    "code": "M-2026-045",
    "plan_name": "Team Brazil Arrival"
  }
}
```

### Check for Similar Transfer

```bash
curl -X POST /api/check-duplicate \
  -H "Content-Type: application/json" \
  -d '{
    "kind": "transfer",
    "team_id": 1,
    "from_location": "Hotel Solene",
    "to_location": "Stadium Azure",
    "window_start": "2026-06-18 14:30:00"
  }'
```

**Response (similar found):**
```json
{
  "exists": true,
  "strict": false,
  "message": "Similar transfer movement found: Hotel Solene → Stadium Azure at 14:30",
  "existing": {
    "id": 47,
    "code": "M-2026-047",
    "plan_name": "Match Day Transport",
    "window_start": "2026-06-18 14:35:00"
  }
}
```

**Response (no duplicate):**
```json
{
  "exists": false
}
```

---

## Testing

### Test Strict Duplicates

```php
// Test arrival duplicate
$team = Team::first();
$flight = TeamFlight::first();

// Create first arrival
Movement::create([
    'team_id' => $team->id,
    'flight_id' => $flight->id,
    'kind' => 'arrival',
    // ... other fields
]);

// Try to create duplicate
$result = $controller->checkDuplicateMovement([
    'team_id' => $team->id,
    'flight_id' => $flight->id,
    'kind' => 'arrival',
]);

// Should return: ['exists' => true, 'strict' => true, ...]
```

### Test Soft Warnings

```php
// Create a transfer
Movement::create([
    'team_id' => 1,
    'kind' => 'transfer',
    'from_location' => 'Hotel A',
    'to_location' => 'Stadium B',
    'window_start' => '2026-06-18 14:30:00',
    // ... other fields
]);

// Check similar transfer (within 30 minutes)
$result = $controller->checkDuplicateMovement([
    'team_id' => 1,
    'kind' => 'transfer',
    'from_location' => 'Hotel A',
    'to_location' => 'Stadium B',
    'window_start' => '2026-06-18 14:35:00', // 5 minutes later
]);

// Should return: ['exists' => true, 'strict' => false, ...]
```

---

## Template Leg-Based Checking (NEW)

### Overview
The duplicate checking system now inspects **individual movement legs** from templates rather than just the template's scenario_type. This ensures accurate duplicate detection for multi-leg templates.

### How It Works

When creating a plan from a template:

1. **All Movements Mode** (no specific position selected):
   - System loops through **all legs** in the template
   - Checks each leg's `leg_type` individually
   - Stops at the first duplicate found (strict) or warning (soft)

2. **Single Movement Mode** (specific position selected):
   - System checks **only the selected leg**
   - Uses that leg's `leg_type` for duplicate detection

### Example Scenario

**Template**: "Match Day Protocol" with 3 legs:
- Leg 1: `leg_type='transfer'` (Hotel → Stadium)
- Leg 2: `leg_type='match'` (At Stadium)
- Leg 3: `leg_type='transfer'` (Stadium → Hotel)

**Behavior**:
- If user selects "All movements": System checks all 3 legs
- If user selects "M2 only" (position 1): System checks only the match leg
- Each leg is validated against its specific duplicate rules

### Frontend Implementation

```javascript
// Check each leg individually
for (const leg of legsToCheck) {
  const legType = leg.leg_type?.toLowerCase();
  
  // For arrival/departure legs
  if (legType === 'arrival' || legType === 'departure') {
    checkData.flight_id = newPlanFlightId.value;
    // Check strict duplicate
  }
  
  // For transfer/training/daily_ops legs
  else if (['transfer', 'training', 'daily_ops'].includes(legType)) {
    checkData.from_location = leg.from_location;
    checkData.to_location = leg.to_location;
    // Check soft duplicate
  }
}
```

### Benefits

✅ **Accurate for multi-leg templates**: Each leg is checked with its appropriate duplicate rule  
✅ **Position-aware**: Only checks the leg being created  
✅ **Type-specific validation**: Uses leg's actual type, not template's overall scenario  
✅ **Prevents false negatives**: Won't miss duplicates in complex templates

---

## Summary

| Movement Type | Rule | Action |
|---------------|------|--------|
| **Arrival** | team + flight | ❌ Block |
| **Departure** | team + flight | ❌ Block |
| **Match** | team + match | ❌ Block |
| **Transfer** | team + route + time (±30min) | ⚠️ Warn |
| **Training** | team + route + time (±30min) | ⚠️ Warn |
| **Daily Ops** | team + route + time (±30min) | ⚠️ Warn |

**Key Points:**
- ✅ Prevents accidental duplicate arrivals, departures, and match movements
- ✅ Warns about similar transfers but allows creation (legitimate multiple per day)
- ✅ Provides links to existing movements for review
- ✅ Works at both database (indexes) and application (validation) layers
- ✅ API endpoint for real-time frontend checking
