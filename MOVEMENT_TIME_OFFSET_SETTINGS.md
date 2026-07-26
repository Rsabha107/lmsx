# Movement Time Offset Settings System

## Overview

This system allows configurable time offsets for different movement types with **cascading lookup** across three scopes:

1. **Template-specific** (most specific)
2. **Event-specific**
3. **Global default** (fallback)

---

## Quick Start

### 1. Run Migration & Seed Defaults

```bash
php artisan migrate
php artisan db:seed --class=SettingsSeeder
```

### 2. View Default Settings

All times are in **minutes**. Negative values = before reference time.

| Movement Type | Default Offset | Description |
|--------------|----------------|-------------|
| arrival | -180 | 3 hours before flight lands |
| departure | -30 | 30 minutes before flight leaves |
| match | -120 | 2 hours before kick-off |
| transfer | -180 | 3 hours before (usually linked to flight) |
| training | 0 | At scheduled time |
| daily_ops | 0 | At scheduled time |

---

## How Cascading Works

### Example Scenario:

**Global Default:** Arrivals start 3 hours (-180 min) before flight  
**Event Override:** VIP Event needs 4 hours (-240 min) for security  
**Template Override:** Domestic template only needs 2 hours (-120 min)

```php
// Global default (all events, all templates)
Setting::create([
    'key' => 'movement_offset.arrival',
    'value' => '-180',
    'scope' => 'global',
    'scope_id' => null,
]);

// Event-specific override (VIP Event ID = 5)
Setting::create([
    'key' => 'movement_offset.arrival',
    'value' => '-240',
    'scope' => 'event',
    'scope_id' => 5,
]);

// Template-specific override (Domestic Template ID = 12)
Setting::create([
    'key' => 'movement_offset.arrival',
    'value' => '-120',
    'scope' => 'template',
    'scope_id' => 12,
]);
```

**Lookup priority:**
- Template 12 + Event 5 → **-120** (template wins)
- Template 8 + Event 5 → **-240** (event wins, no template override)
- Template 8 + Event 3 → **-180** (global default, no overrides)

---

## Usage in Code

### SettingsService Methods

```php
use App\Services\SettingsService;

$settingsService = app(SettingsService::class);

// Get offset with cascading lookup
$offset = $settingsService->getMovementOffset(
    'arrival',        // Movement type
    $eventId,         // Event context (optional)
    $templateId       // Template context (optional)
);

// Set a global default
$settingsService->setSetting(
    'movement_offset.arrival',
    '-180',
    Setting::SCOPE_GLOBAL
);

// Set event-specific override
$settingsService->setSetting(
    'movement_offset.arrival',
    '-240',
    Setting::SCOPE_EVENT,
    5  // event_id
);

// Set template-specific override
$settingsService->setSetting(
    'movement_offset.arrival',
    '-120',
    Setting::SCOPE_TEMPLATE,
    12  // template_id
);
```

---

## Database Structure

### pma_settings table

```
id  | key                     | value | scope    | scope_id | description
----|-------------------------|-------|----------|----------|-------------
1   | movement_offset.arrival | -180  | global   | null     | Global default: 3 hours
2   | movement_offset.arrival | -240  | event    | 5        | VIP Event: 4 hours
3   | movement_offset.arrival | -120  | template | 12       | Domestic: 2 hours
4   | movement_offset.match   | -120  | global   | null     | Global default: 2 hours
5   | movement_offset.match   | -300  | event    | 5        | VIP Event: 5 hours
```

---

## Managing Settings

### Via Tinker

```bash
php artisan tinker
```

```php
use App\Models\Setting;
use App\Services\SettingsService;

$settingsService = app(SettingsService::class);

// View all global settings
Setting::where('scope', 'global')->get();

// View event-specific settings
Setting::where('scope', 'event')->where('scope_id', 5)->get();

// Update a setting
$settingsService->setSetting(
    'movement_offset.arrival',
    '-240',
    Setting::SCOPE_EVENT,
    5,
    'VIP Event with enhanced security requires 4 hours'
);

// Get computed offset for a specific context
$offset = $settingsService->getMovementOffset('arrival', 5, 12);
echo "Offset: {$offset} minutes\n";
```

### Via Database

```sql
-- View all settings
SELECT * FROM pma_settings ORDER BY scope, key;

-- Add event-specific setting
INSERT INTO pma_settings (key, value, scope, scope_id, description, created_at, updated_at)
VALUES ('movement_offset.arrival', '-240', 'event', 5, 'VIP Event security protocol', NOW(), NOW());

-- Update global default
UPDATE pma_settings 
SET value = '-210' 
WHERE key = 'movement_offset.arrival' AND scope = 'global';
```

---

## Real-World Examples

### Example 1: Different Events, Same Templates

**Scenario:** World Cup (strict security) vs Friendly Match (relaxed)

```php
// World Cup Event (event_id = 10)
$settingsService->setSetting('movement_offset.arrival', '-240', 'event', 10);
$settingsService->setSetting('movement_offset.match', '-180', 'event', 10);

// Friendly Match Event (event_id = 11)
$settingsService->setSetting('movement_offset.arrival', '-120', 'event', 11);
$settingsService->setSetting('movement_offset.match', '-90', 'event', 11);

// Same templates used for both events, different offsets applied automatically
```

### Example 2: International vs Domestic Flights

**Scenario:** International flights need more time for customs

```php
// International Arrival Template (template_id = 5)
$settingsService->setSetting('movement_offset.arrival', '-240', 'template', 5);

// Domestic Arrival Template (template_id = 6)
$settingsService->setSetting('movement_offset.arrival', '-120', 'template', 6);
```

### Example 3: VIP Teams

**Scenario:** VIP teams get priority treatment with tighter schedules

```php
// VIP Event with reduced offsets
$settingsService->setSetting('movement_offset.arrival', '-90', 'event', 15);
$settingsService->setSetting('movement_offset.departure', '-15', 'event', 15);
```

---

## Cache Management

Settings are cached for 1 hour by default. Clear cache after updates:

```php
$settingsService->clearCache();

// Or via Artisan
php artisan cache:clear
```

---

## Future Enhancements

Potential additions to the settings system:

- Buffer times (min/max)
- Vehicle assignment preferences
- Checkpoint timing overrides
- Resource allocation rules
- Notification thresholds
- Per-venue settings

---

## Troubleshooting

**Q: Offset not applying?**  
A: Check cascading priority. Template > Event > Global. Clear cache.

**Q: How to reset to defaults?**  
A: Delete event/template overrides, keep global settings.

**Q: Can I have negative AND positive offsets?**  
A: Yes! Positive = after reference time (rare but supported).

---

## Summary

✅ **Flexible**: Per-event, per-template, or global  
✅ **Cascading**: Most specific setting wins  
✅ **Cached**: Fast lookups  
✅ **Multi-event**: Perfect for your use case  
✅ **No code changes**: Just update settings
