<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Service for managing and retrieving application settings with cascading lookup.
 *
 * Lookup priority:
 * 1. Checkpoint-specific offset (event-scoped, then global-scoped) — drives
 *    the movement window when configured on the movement's first checkpoint
 * 2. Event-specific movement-type setting
 * 3. Global movement-type setting
 *
 * There is no hardcoded fallback offset. If nothing is configured at any
 * level, the offset is 0 — the movement window starts exactly at its
 * reference time (arrival/departure flight time, KO time, or training
 * start), until an offset is explicitly set in Settings.
 */
class SettingsService
{
    /** Keys of the admin-controlled UI toggles. */
    public const FLAG_JOBS_MOBILE_MENU = 'ui.jobs_mobile_menu';
    public const FLAG_UTILITIES = 'ui.utilities';

    /** Every UI toggle, with the description stored alongside its setting row. */
    public const UI_FLAGS = [
        self::FLAG_JOBS_MOBILE_MENU => 'Show the Jobs (Mobile) menu in the sidebar',
        self::FLAG_UTILITIES => 'Show the Utilities menu and allow its sheet converters',
    ];

    /**
     * Per-request memoization of resolved setting values, keyed by cache
     * key. Settings are looked up per checkpoint per movement (dozens to
     * hundreds of times in a single Plans-page request), and the
     * database-backed cache driver turns every Cache::remember() call into
     * its own SQL query — this array collapses repeat lookups for the same
     * key down to one query for the lifetime of this service instance.
     */
    private array $localCache = [];

    /**
     * Get movement time offset with cascading lookup.
     *
     * @param string $movementType (arrival, departure, match, transfer, training, daily_ops)
     * @param int|null $eventId
     * @return int Offset in minutes (negative = before reference time, positive = after)
     */
    public function getMovementOffset(string $movementType, ?int $eventId = null): int
    {
        $key = "movement_offset.{$movementType}";

        // Try event-specific setting
        if ($eventId) {
            $value = $this->getSetting($key, Setting::SCOPE_EVENT, $eventId);
            if ($value !== null) {
                return (int) $value;
            }
        }
        
        // Fallback to global default
        $value = $this->getSetting($key, Setting::SCOPE_GLOBAL);
        if ($value !== null) {
            return (int) $value;
        }

        // No offset configured anywhere — window_start equals the reference
        // time exactly (arrival/departure flight time, KO time, or training
        // start) until someone sets an offset in Settings.
        return 0;
    }
    
    /**
     * Get a plain (non-checkpoint-specific) setting value with optional
     * caching. Always excludes checkpoint-specific rows — those are looked
     * up separately via getCheckpointOffset().
     *
     * @param string $key
     * @param string $scope
     * @param int|null $scopeId
     * @return string|null
     */
    protected function getSetting(string $key, string $scope, ?int $scopeId = null): ?string
    {
        $cacheKey = "setting.{$key}.{$scope}." . ($scopeId ?? 'null');

        if (array_key_exists($cacheKey, $this->localCache)) {
            return $this->localCache[$cacheKey];
        }

        return $this->localCache[$cacheKey] = Cache::remember($cacheKey, 3600, function () use ($key, $scope, $scopeId) {
            $query = Setting::where('key', $key)->where('scope', $scope)->whereNull('checkpoint_id');

            if ($scopeId) {
                $query->where('scope_id', $scopeId);
            } else {
                $query->whereNull('scope_id');
            }

            return $query->value('value');
        });
    }
    
    /**
     * Set a setting value and clear cache.
     * 
     * @param string $key
     * @param mixed $value
     * @param string $scope
     * @param int|null $scopeId
     * @param string|null $description
     * @return Setting
     */
    public function setSetting(
        string $key,
        $value,
        string $scope = Setting::SCOPE_GLOBAL,
        ?int $scopeId = null,
        ?string $description = null,
        ?int $checkpointId = null
    ): Setting {
        $setting = Setting::updateOrCreate(
            [
                'key' => $key,
                'scope' => $scope,
                'scope_id' => $scopeId,
                'checkpoint_id' => $checkpointId,
            ],
            [
                'value' => (string) $value,
                'description' => $description,
            ]
        );

        // Clear cache
        $cacheKey = $checkpointId
            ? $this->checkpointCacheKey($key, $scope, $scopeId, $checkpointId)
            : "setting.{$key}.{$scope}." . ($scopeId ?? 'null');
        Cache::forget($cacheKey);
        unset($this->localCache[$cacheKey]);

        return $setting;
    }

    /**
     * Update an existing setting row in place, by ID — used for editing a
     * row where any of key/scope/scope_id/checkpoint_id might change (e.g.
     * switching which checkpoint an override applies to). setSetting()'s
     * updateOrCreate matches on (key, scope, scope_id, checkpoint_id), so it
     * can't be used for this: changing checkpoint_id there would create a
     * second row instead of updating the one being edited.
     */
    public function updateSettingById(
        int $id,
        string $key,
        $value,
        string $scope,
        ?int $scopeId,
        ?string $description = null,
        ?int $checkpointId = null
    ): Setting {
        $setting = Setting::findOrFail($id);

        // Clear the cache entry for the row's OLD identity before mutating it
        $oldCacheKey = $setting->checkpoint_id
            ? $this->checkpointCacheKey($setting->key, $setting->scope, $setting->scope_id, $setting->checkpoint_id)
            : "setting.{$setting->key}.{$setting->scope}." . ($setting->scope_id ?? 'null');
        Cache::forget($oldCacheKey);
        unset($this->localCache[$oldCacheKey]);

        $setting->update([
            'key' => $key,
            'value' => (string) $value,
            'scope' => $scope,
            'scope_id' => $scopeId,
            'checkpoint_id' => $checkpointId,
            'description' => $description,
        ]);

        $newCacheKey = $checkpointId
            ? $this->checkpointCacheKey($key, $scope, $scopeId, $checkpointId)
            : "setting.{$key}.{$scope}." . ($scopeId ?? 'null');
        Cache::forget($newCacheKey);
        unset($this->localCache[$newCacheKey]);

        return $setting;
    }

    /**
     * Read a global on/off setting, e.g. a UI feature toggle. Returns the
     * default when no row exists, so a fresh install behaves sensibly.
     */
    public function getGlobalFlag(string $key, bool $default = true): bool    {
        $value = $this->getSetting($key, Setting::SCOPE_GLOBAL);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get a checkpoint-specific time offset: event-scoped override first (if
     * an event is given), then global-scoped override, else null — in which
     * case the caller should fall back to the plain movement-type offset
     * (getMovementOffset) or cumulative estimated_minutes chaining.
     *
     * @param int $checkpointId
     * @param string $movementType (arrival, departure, match, transfer, training, daily_ops)
     * @param int|null $eventId
     * @return int|null Offset in minutes (negative = before reference time, positive = after)
     */
    public function getCheckpointOffset(int $checkpointId, string $movementType, ?int $eventId = null): ?int
    {
        $key = "movement_offset.{$movementType}";

        if ($eventId) {
            $value = $this->getCheckpointSetting($key, Setting::SCOPE_EVENT, $eventId, $checkpointId);
            if ($value !== null) {
                return (int) $value;
            }
        }

        $value = $this->getCheckpointSetting($key, Setting::SCOPE_GLOBAL, null, $checkpointId);
        if ($value !== null) {
            return (int) $value;
        }

        return null;
    }

    protected function getCheckpointSetting(string $key, string $scope, ?int $scopeId, int $checkpointId): ?string
    {
        $cacheKey = $this->checkpointCacheKey($key, $scope, $scopeId, $checkpointId);

        if (array_key_exists($cacheKey, $this->localCache)) {
            return $this->localCache[$cacheKey];
        }

        return $this->localCache[$cacheKey] = Cache::remember($cacheKey, 3600, function () use ($key, $scope, $scopeId, $checkpointId) {
            $query = Setting::where('key', $key)->where('scope', $scope)->where('checkpoint_id', $checkpointId);
            if ($scopeId) {
                $query->where('scope_id', $scopeId);
            } else {
                $query->whereNull('scope_id');
            }

            return $query->value('value');
        });
    }

    protected function checkpointCacheKey(string $key, string $scope, ?int $scopeId, int $checkpointId): string
    {
        return "checkpoint_setting.{$key}.{$scope}." . ($scopeId ?? 'null') . ".checkpoint.{$checkpointId}";
    }
    
    /**
     * Clear all settings cache.
     */
    public function clearCache(): void
    {
        Cache::flush();
        $this->localCache = [];
    }
    
    /**
     * Get all settings for an event (for admin UI).
     * 
     * @param int $eventId
     * @return \Illuminate\Support\Collection
     */
    public function getEventSettings(int $eventId)
    {
        return Setting::where('scope', Setting::SCOPE_EVENT)
            ->where('scope_id', $eventId)
            ->get();
    }
    
    /**
     * Get all global settings (for admin UI).
     * 
     * @return \Illuminate\Support\Collection
     */
    public function getGlobalSettings()
    {
        return Setting::where('scope', Setting::SCOPE_GLOBAL)
            ->whereNull('scope_id')
            ->get();
    }
}
