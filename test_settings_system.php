<?php

/**
 * Test script for Movement Time Offset Settings System
 * 
 * Usage: php test_settings_system.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Setting;
use App\Services\SettingsService;

echo "=================================================\n";
echo "  Movement Time Offset Settings - Test Suite\n";
echo "=================================================\n\n";

$settingsService = app(SettingsService::class);

// Test 1: Check default global settings exist
echo "✓ Test 1: Verify default global settings\n";
echo str_repeat('-', 50) . "\n";

$movementTypes = ['arrival', 'departure', 'match', 'transfer', 'training', 'daily_ops'];
foreach ($movementTypes as $type) {
    $offset = $settingsService->getMovementOffset($type);
    echo sprintf("  %-12s : %4d minutes\n", ucfirst($type), $offset);
}
echo "\n";

// Test 2: Cascading lookup - Global only
echo "✓ Test 2: Global default lookup (no event/template override)\n";
echo str_repeat('-', 50) . "\n";
$offset = $settingsService->getMovementOffset('arrival');
echo "  arrival offset: {$offset} minutes (expected: -180)\n";
echo "  Result: " . ($offset === -180 ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 3: Create event-specific override
echo "✓ Test 3: Event-specific override\n";
echo str_repeat('-', 50) . "\n";
$settingsService->setSetting(
    'movement_offset.arrival',
    '-240',
    Setting::SCOPE_EVENT,
    999,  // Test event ID
    'Test VIP Event - 4 hours before'
);
$offset = $settingsService->getMovementOffset('arrival', 999);
echo "  Created event-specific setting for event_id=999\n";
echo "  arrival offset: {$offset} minutes (expected: -240)\n";
echo "  Result: " . ($offset === -240 ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 4: Create template-specific override (highest priority)
echo "✓ Test 4: Template-specific override (highest priority)\n";
echo str_repeat('-', 50) . "\n";
$settingsService->setSetting(
    'movement_offset.arrival',
    '-120',
    Setting::SCOPE_TEMPLATE,
    888,  // Test template ID
    'Test Domestic Template - 2 hours before'
);
$offset = $settingsService->getMovementOffset('arrival', 999, 888);
echo "  Created template-specific setting for template_id=888\n";
echo "  arrival offset with event=999 & template=888: {$offset} minutes (expected: -120)\n";
echo "  Result: " . ($offset === -120 ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 5: Verify cascading priority
echo "✓ Test 5: Cascading priority verification\n";
echo str_repeat('-', 50) . "\n";
$offset1 = $settingsService->getMovementOffset('arrival');           // Global: -180
$offset2 = $settingsService->getMovementOffset('arrival', 999);      // Event: -240
$offset3 = $settingsService->getMovementOffset('arrival', 999, 888); // Template: -120
$offset4 = $settingsService->getMovementOffset('arrival', 999, 777); // Event (no template): -240

echo "  No overrides (global):           {$offset1} minutes (expected: -180)\n";
echo "  Event override only:             {$offset2} minutes (expected: -240)\n";
echo "  Template wins over event:        {$offset3} minutes (expected: -120)\n";
echo "  Event wins over global:          {$offset4} minutes (expected: -240)\n";

$pass = ($offset1 === -180 && $offset2 === -240 && $offset3 === -120 && $offset4 === -240);
echo "  Result: " . ($pass ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Test 6: View all settings in database
echo "✓ Test 6: Database contents\n";
echo str_repeat('-', 50) . "\n";
$settings = Setting::orderBy('scope')->orderBy('key')->get();
echo sprintf("  Total settings: %d\n\n", $settings->count());

$grouped = $settings->groupBy('scope');
foreach ($grouped as $scope => $items) {
    echo "  Scope: {$scope}\n";
    foreach ($items as $setting) {
        $scopeId = $setting->scope_id ? " (ID: {$setting->scope_id})" : "";
        echo sprintf("    %-25s = %6s %s\n", $setting->key, $setting->value, $scopeId);
    }
    echo "\n";
}

// Test 7: Match movement type
echo "✓ Test 7: Match movement type offset\n";
echo str_repeat('-', 50) . "\n";
$offset = $settingsService->getMovementOffset('match');
echo "  match offset: {$offset} minutes (expected: -120)\n";
echo "  Result: " . ($offset === -120 ? "✓ PASS" : "✗ FAIL") . "\n\n";

// Cleanup test data
echo "🧹 Cleanup: Removing test event/template settings\n";
Setting::where('scope', Setting::SCOPE_EVENT)->where('scope_id', 999)->delete();
Setting::where('scope', Setting::SCOPE_TEMPLATE)->where('scope_id', 888)->delete();
$settingsService->clearCache();
echo "  ✓ Test data removed\n\n";

echo "=================================================\n";
echo "  All Tests Complete!\n";
echo "=================================================\n";
