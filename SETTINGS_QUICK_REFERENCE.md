# Movement Time Offset Settings - Quick Reference

## ✅ System Status: OPERATIONAL

**Last Tested:** 2026-06-19  
**All Tests:** ✓ PASSING

---

## 🚀 Quick Start

### View Current Settings
```bash
php artisan tinker
```
```php
use App\Models\Setting;
Setting::where('scope', 'global')->get();
```

### Change Global Default
```php
use App\Services\SettingsService;
$service = app(SettingsService::class);

// Change arrival to 4 hours before
$service->setSetting('movement_offset.arrival', '-240', Setting::SCOPE_GLOBAL);
```

### Set Event-Specific Override (World Cup needs more time)
```php
// Event ID 5 = World Cup
$service->setSetting(
    'movement_offset.arrival',
    '-300',  // 5 hours before
    Setting::SCOPE_EVENT,
    5,
    'World Cup enhanced security protocol'
);

$service->setSetting(
    'movement_offset.match',
    '-180',  // 3 hours before kick-off
    Setting::SCOPE_EVENT,
    5,
    'World Cup match day protocol'
);
```

### Set Template-Specific Override (International vs Domestic)
```php
// Template ID 12 = International Arrival
$service->setSetting(
    'movement_offset.arrival',
    '-240',  // 4 hours for international flights
    Setting::SCOPE_TEMPLATE,
    12,
    'International arrival with customs'
);

// Template ID 13 = Domestic Arrival
$service->setSetting(
    'movement_offset.arrival',
    '-120',  // 2 hours for domestic flights
    Setting::SCOPE_TEMPLATE,
    13,
    'Domestic arrival - no customs'
);
```

---

## 📊 Current Default Values

| Movement Type | Default Offset | Meaning |
|--------------|----------------|---------|
| arrival | -180 min | Start 3 hours before flight lands |
| departure | -30 min | Start 30 minutes before flight leaves |
| match | -120 min | Depart hotel 2 hours before kick-off |
| transfer | -180 min | Start 3 hours before (linked to flight) |
| training | 0 min | At scheduled time |
| daily_ops | 0 min | At scheduled time |

---

## 🎯 Priority Order (Cascading Lookup)

```
1. Template-specific setting (template_id + key)
   ↓ (if not found)
2. Event-specific setting (event_id + key)
   ↓ (if not found)
3. Global default (scope = global)
   ↓ (if not found)
4. Hardcoded fallback in SettingsService
```

**Example:**
- Template 12 + Event 5 + `movement_offset.arrival`:
  1. Check `pma_settings` where key='movement_offset.arrival' AND scope='template' AND scope_id=12
  2. If not found, check scope='event' AND scope_id=5
  3. If not found, check scope='global' AND scope_id IS NULL
  4. If not found, return -180 (hardcoded)

---

## 🔧 Direct Database Queries

```sql
-- View all settings
SELECT * FROM pma_settings ORDER BY scope, key;

-- View event-specific settings
SELECT * FROM pma_settings WHERE scope = 'event' AND scope_id = 5;

-- Update global arrival offset to 3.5 hours
UPDATE pma_settings 
SET value = '-210' 
WHERE key = 'movement_offset.arrival' AND scope = 'global';

-- Add new event override
INSERT INTO pma_settings (key, value, scope, scope_id, description, created_at, updated_at)
VALUES ('movement_offset.match', '-150', 'event', 10, 'Semi-finals event', NOW(), NOW());
```

---

## 🧪 Test the System

```bash
php test_settings_system.php
```

Expected output: All 7 tests should pass with ✓ PASS

---

## 💡 Real-World Scenarios

### Scenario 1: Friendly Match (Relaxed)
```php
// Event ID 20 = Friendly Match
$service->setSetting('movement_offset.arrival', '-120', 'event', 20); // 2 hours
$service->setSetting('movement_offset.match', '-90', 'event', 20);    // 1.5 hours
```

### Scenario 2: VIP Teams (Tighter Schedule)
```php
// Event ID 25 = VIP Tournament
$service->setSetting('movement_offset.arrival', '-90', 'event', 25);  // 1.5 hours
$service->setSetting('movement_offset.departure', '-15', 'event', 25); // 15 mins
```

### Scenario 3: Airport Changes (Per Template)
```php
// Template ID 30 = Old Airport (far from city)
$service->setSetting('movement_offset.transfer', '-240', 'template', 30); // 4 hours

// Template ID 31 = New Airport (close to city)
$service->setSetting('movement_offset.transfer', '-120', 'template', 31); // 2 hours
```

---

## 🚨 Troubleshooting

**Q: Changes not applying?**  
Clear cache:
```php
$service->clearCache();
// OR
php artisan cache:clear
```

**Q: Want to reset to defaults?**  
Delete overrides, keep global:
```sql
DELETE FROM pma_settings WHERE scope IN ('event', 'template');
```

**Q: How to see what offset is actually used?**
```php
$offset = $service->getMovementOffset('arrival', $eventId, $templateId);
echo "Using offset: {$offset} minutes\n";
```

---

## 📝 Files Modified

✅ `database/migrations/2026_06_18_000002_create_settings_table.php`  
✅ `app/Models/Setting.php`  
✅ `app/Services/SettingsService.php`  
✅ `database/seeders/SettingsSeeder.php`  
✅ `app/Services/JobGenerationService.php` (uses SettingsService)  
✅ `test_settings_system.php` (test suite)  
✅ `MOVEMENT_TIME_OFFSET_SETTINGS.md` (full documentation)  
✅ `SETTINGS_QUICK_REFERENCE.md` (this file)

---

## ⚠️ Important Notes

1. **Negative values** = BEFORE reference time (e.g., -180 = 3 hours before)
2. **Positive values** = AFTER reference time (rare, but supported)
3. **Cache duration** = 1 hour (change in SettingsService if needed)
4. **Table name** = `pma_settings` (renamed to avoid conflict with existing `settings` table)

---

## 🎉 Next Steps (Optional)

- [ ] Build admin UI for managing settings
- [ ] Add validation rules (min/max offsets)
- [ ] Add audit logging for setting changes
- [ ] Export/import settings between environments
- [ ] Per-venue settings (future enhancement)
