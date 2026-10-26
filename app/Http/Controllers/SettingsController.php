<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Checkpoint;
use App\Models\Event;
use App\Models\Movement;
use App\Models\Setting;
use App\Models\User;
use App\Services\JobGenerationService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SettingsController extends Controller
{
    protected SettingsService $settingsService;
    protected JobGenerationService $jobGenerationService;

    public function __construct(SettingsService $settingsService, JobGenerationService $jobGenerationService)
    {
        $this->settingsService = $settingsService;
        $this->jobGenerationService = $jobGenerationService;
    }

    /**
     * Display settings management page.
     */
    public function index(Request $request)
    {
        $activeEventId = $request->session()->get('active_event_id');
        $activeEvent = $activeEventId ? Event::find($activeEventId) : null;

        $movementTypes = SettingsService::OFFSET_TYPES;

        // Get all events for dropdown
        $events = Event::select('id', 'name', 'start_date', 'end_date')
            ->orderBy('start_date', 'desc')
            ->get();

        // Get all event overrides grouped by event
        $eventOverrides = Setting::where('scope', Setting::SCOPE_EVENT)
            ->whereNotNull('scope_id')
            ->with('checkpoint')
            ->get()
            ->groupBy('scope_id')
            ->map(function ($settings) {
                return $settings->map(function ($setting) {
                    return [
                        'id' => $setting->id,
                        'key' => $setting->key,
                        'value' => $setting->value,
                        'description' => $setting->description,
                        'checkpoint_id' => $setting->checkpoint_id,
                        'checkpoint_name' => $setting->checkpoint?->name,
                    ];
                });
            });

        // Global-scope overrides — pure DB CRUD, nothing synthesized. Rows
        // with checkpoint_id null are the plain per-type default; rows with
        // a checkpoint set are checkpoint-specific (and drive the movement
        // window when that's the movement's first checkpoint).
        $globalOverrides = Setting::where('scope', Setting::SCOPE_GLOBAL)
            ->with('checkpoint')
            ->orderBy('key')
            ->get()
            ->map(function ($setting) {
                return [
                    'id' => $setting->id,
                    'key' => $setting->key,
                    'value' => $setting->value,
                    'description' => $setting->description,
                    'checkpoint_id' => $setting->checkpoint_id,
                    'checkpoint_name' => $setting->checkpoint?->name,
                ];
            })
            ->values();

        // Global checkpoint library, for the checkpoint-specific override dropdowns
        $checkpoints = Checkpoint::select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings', [
            'movementTypes' => $movementTypes,
            'events' => $events,
            'eventOverrides' => $eventOverrides,
            'globalOverrides' => $globalOverrides,
            'activeEvent' => $activeEvent,
            'checkpoints' => $checkpoints,
            'uiFlags' => [
                'jobsMobileMenu' => $this->settingsService->getGlobalFlag(SettingsService::FLAG_JOBS_MOBILE_MENU),
                'utilities' => $this->settingsService->getGlobalFlag(SettingsService::FLAG_UTILITIES),
            ],
            'mobileOnlyUsers' => $this->mobileOnlyUserCount(),
            'dailyEmail' => $this->dailyEmailAudience($request),
            'conflictThresholds' => collect(SettingsService::CONFLICT_THRESHOLDS)
                ->map(fn (array $t, string $key) => $t + ['key' => $key, 'value' => $this->settingsService->getThreshold($key)])
                ->values(),
        ]);
    }

    /**
     * The active event's Daily Email audience and who can be picked: active users with an email,
     * plus anyone already chosen who has since been deactivated (shown, but not sent to).
     */
    private function dailyEmailAudience(Request $request): array
    {
        $eventId = (int) $request->session()->get('active_event_id');
        $event = $eventId ? Event::find($eventId) : null;
        $chosen = $event ? $event->dailyEmailRecipients()->pluck('users.id')->all() : [];

        return [
            'event' => $event?->only(['id', 'name', 'short_name']),
            'recipientIds' => $chosen,
            'users' => $event
                ? User::with('roles:id,name')
                    ->whereNotNull('email')
                    ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $chosen))
                    ->orderBy('name')
                    ->get(['id', 'name', 'email', 'is_active'])
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'active' => (bool) $u->is_active, 'roles' => $u->roles->pluck('name')->all()])
                : [],
        ];
    }

    /** Who receives the active event's Daily Email. */
    public function updateDailyEmail(Request $request)
    {
        $eventId = (int) $request->session()->get('active_event_id');
        abort_unless($eventId && $request->user()->canAccessEvent($eventId), 403, 'Pick an event first.');
        $event = Event::findOrFail($eventId);

        $ids = $request->validate([
            'user_ids' => 'present|array|max:200',
            'user_ids.*' => ['integer', \Illuminate\Validation\Rule::exists('users', 'id')->whereNotNull('email')],
        ])['user_ids'];

        $before = $event->dailyEmailRecipients()->pluck('users.email', 'users.id');
        $event->dailyEmailRecipients()->sync(array_unique(array_map('intval', $ids)));
        $after = $event->dailyEmailRecipients()->pluck('users.email', 'users.id');

        $added = $after->diffKeys($before)->values()->all();
        $removed = $before->diffKeys($after)->values()->all();
        if ($added || $removed) {
            AuditLog::change('Daily Email audience changed', $event->name, ['added' => $added, 'removed' => $removed], $event, $event->id);
        }

        return back()->with('success', $added || $removed ? "Daily Email audience saved: {$after->count()} recipient(s)." : 'No changes to save.');
    }

    /** Saves the limits the conflict checks judge against. */
    public function updateThresholds(Request $request)
    {
        $rules = [];
        foreach (SettingsService::CONFLICT_THRESHOLDS as $key => $t) {
            $rules[$key] = "required|integer|min:{$t['min']}|max:{$t['max']}";
        }
        $validated = $request->validate($rules);

        $changes = [];
        foreach ($validated as $key => $value) {
            $before = $this->settingsService->getThreshold($key);
            if ($before === (int) $value) {
                continue;
            }

            $this->settingsService->setSetting("conflict.{$key}", (int) $value, Setting::SCOPE_GLOBAL, null, SettingsService::CONFLICT_THRESHOLDS[$key]['label']);
            $changes[$key] = ['from' => $before, 'to' => (int) $value];
        }

        if ($changes) {
            AuditLog::change('Conflict thresholds changed', null, $changes);
        }

        return back()->with('success', $changes ? 'Conflict rules updated.' : 'No changes to save.');
    }

    /**
     * Users whose only screen is the mobile jobs view - they have jobs.view but
     * no console access, so turning that view off locks them out of the app.
     */
    private function mobileOnlyUserCount(): int
    {
        return User::permission('jobs.view')->get()
            ->filter(fn (User $user) => ! $user->can('console.view'))
            ->count();
    }

    /**
     * Toggle an optional feature on or off for everyone. Each flag both hides
     * its menu and closes its routes (see EnsureUiFlagEnabled).
     */
    public function updateUiFlag(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|in:' . implode(',', array_keys(SettingsService::UI_FLAGS)),
            'enabled' => 'required|boolean',
        ]);

        $this->settingsService->setSetting(
            $validated['key'],
            $validated['enabled'] ? '1' : '0',
            Setting::SCOPE_GLOBAL,
            null,
            SettingsService::UI_FLAGS[$validated['key']],
        );

        AuditLog::change('Feature switch changed', $validated['key'], ['enabled' => (bool) $validated['enabled']]);

        return back()->with('success', 'Feature visibility updated.');
    }

    /**
     * Update a global setting.
     */
    public function updateGlobal(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:pma_settings,id',
            'movement_type' => ['required', Rule::in(SettingsService::OFFSET_TYPES)],
            'value' => 'required|integer|min:-999|max:999',
            'description' => 'nullable|string|max:255',
            'checkpoint_id' => 'nullable|integer|exists:checkpoints,id',
        ]);

        $checkpointId = $validated['checkpoint_id'] ?? null;
        $oldMovementType = null;

        if ($this->duplicateOverrideExists(
            "movement_offset.{$validated['movement_type']}",
            Setting::SCOPE_GLOBAL,
            null,
            $checkpointId,
            $validated['id'] ?? null
        )) {
            return back()->with('error', 'An override for this movement type and checkpoint already exists — edit that row instead.');
        }

        if (!empty($validated['id'])) {
            $existing = Setting::find($validated['id']);
            $oldMovementType = $existing ? str_replace('movement_offset.', '', $existing->key) : null;

            $setting = $this->settingsService->updateSettingById(
                $validated['id'],
                "movement_offset.{$validated['movement_type']}",
                $validated['value'],
                Setting::SCOPE_GLOBAL,
                null,
                $validated['description'] ?? null,
                $checkpointId
            );
        } else {
            $setting = $this->settingsService->setSetting(
                "movement_offset.{$validated['movement_type']}",
                $validated['value'],
                Setting::SCOPE_GLOBAL,
                null,
                $validated['description'] ?? null,
                $checkpointId
            );
        }

        $activeEventId = $request->session()->get('active_event_id');
        $message = 'Global setting updated successfully.';

        AuditLog::change('Global offset saved', $validated['movement_type'], [
            'checkpoint_id' => $checkpointId,
            'from' => isset($existing) ? $existing->value : null,
            'to' => (int) $validated['value'],
        ]);

        if ($activeEventId) {
            // If editing changed which movement type this row applies to,
            // recompute both the old and new type's movements.
            $types = array_unique(array_filter([$validated['movement_type'], $oldMovementType]));
            $updated = 0;
            foreach ($types as $type) {
                $updated += $this->jobGenerationService->recomputeWindowsForEventAndKind((int) $activeEventId, $type);
            }
            $message .= $updated > 0
                ? " {$updated} movement window(s) recalculated."
                : ' No movement windows needed recalculation.';
        } else {
            $message .= ' No active event selected, so no movement windows were recalculated.';
        }

        return back()->with('success', $message);
    }

    /**
     * Create or update an event-specific override.
     */
    public function updateEvent(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:pma_settings,id',
            'event_id' => 'required|exists:events,id',
            'movement_type' => ['required', Rule::in(SettingsService::OFFSET_TYPES)],
            'value' => 'required|integer|min:-999|max:999',
            'description' => 'nullable|string|max:255',
            'checkpoint_id' => 'nullable|integer|exists:checkpoints,id',
        ]);

        $checkpointId = $validated['checkpoint_id'] ?? null;
        $oldMovementType = null;

        if ($this->duplicateOverrideExists(
            "movement_offset.{$validated['movement_type']}",
            Setting::SCOPE_EVENT,
            $validated['event_id'],
            $checkpointId,
            $validated['id'] ?? null
        )) {
            return back()->with('error', 'An override for this movement type and checkpoint already exists on this event — edit that row instead.');
        }

        if (!empty($validated['id'])) {
            $existing = Setting::find($validated['id']);
            $oldMovementType = $existing ? str_replace('movement_offset.', '', $existing->key) : null;

            $setting = $this->settingsService->updateSettingById(
                $validated['id'],
                "movement_offset.{$validated['movement_type']}",
                $validated['value'],
                Setting::SCOPE_EVENT,
                $validated['event_id'],
                $validated['description'] ?? null,
                $checkpointId
            );
        } else {
            $setting = $this->settingsService->setSetting(
                "movement_offset.{$validated['movement_type']}",
                $validated['value'],
                Setting::SCOPE_EVENT,
                $validated['event_id'],
                $validated['description'] ?? null,
                $checkpointId
            );
        }

        // A checkpoint-specific override on a movement's first checkpoint
        // drives that movement's window_start, so it goes through the same
        // recompute as a plain event override. If editing changed which
        // movement type this row applies to, recompute both.
        $types = array_unique(array_filter([$validated['movement_type'], $oldMovementType]));
        $updated = 0;
        foreach ($types as $type) {
            $updated += $this->jobGenerationService->recomputeWindowsForEventAndKind((int) $validated['event_id'], $type);
        }

        AuditLog::change('Event offset saved', $validated['movement_type'], [
            'checkpoint_id' => $checkpointId,
            'from' => isset($existing) ? $existing->value : null,
            'to' => (int) $validated['value'],
            'windows_recalculated' => $updated,
        ], null, (int) $validated['event_id']);

        $message = 'Event override created successfully.';
        $message .= $updated > 0
            ? " {$updated} movement window(s) recalculated."
            : ' No movement windows needed recalculation.';

        return back()->with('success', $message);
    }

    /**
     * Delete a setting override.
     */
    public function destroy(Request $request, $id)
    {
        $setting = Setting::findOrFail($id);

        $movementType = str_replace('movement_offset.', '', $setting->key);
        $scope = $setting->scope;
        $scopeId = $setting->scope_id;
        // getMovementOffset() already falls back to a hardcoded default when
        // no row exists, so deleting the plain global default is safe — it
        // just reverts that movement type to the hardcoded fallback.
        $isBaseGlobalDefault = $scope === Setting::SCOPE_GLOBAL && !$setting->checkpoint_id;

        $setting->delete();
        $this->settingsService->clearCache();

        AuditLog::change('Offset deleted', $movementType, [
            'scope' => $scope,
            'scope_id' => $scopeId,
            'checkpoint_id' => $setting->checkpoint_id,
            'was' => $setting->value,
        ], null, $scope === Setting::SCOPE_EVENT ? (int) $scopeId : null);

        $message = $isBaseGlobalDefault
            ? 'Global default removed — this movement type now uses the hardcoded fallback offset.'
            : 'Override deleted successfully.';

        if ($scope === Setting::SCOPE_EVENT && $scopeId) {
            $updated = $this->jobGenerationService->recomputeWindowsForEventAndKind(
                (int) $scopeId,
                $movementType
            );
            $message .= " {$updated} movement window(s) recalculated.";
        } elseif ($scope === Setting::SCOPE_GLOBAL) {
            $activeEventId = $request->session()->get('active_event_id');
            if ($activeEventId) {
                $updated = $this->jobGenerationService->recomputeWindowsForEventAndKind(
                    (int) $activeEventId,
                    $movementType
                );
                $message .= " {$updated} movement window(s) recalculated.";
            }
        }

        return back()->with('success', $message);
    }

    /**
     * Preview what offset will be used for a specific context.
     */
    public function preview(Request $request)
    {
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(SettingsService::OFFSET_TYPES)],
            'event_id' => 'nullable|exists:events,id',
        ]);

        $offset = $this->settingsService->getMovementOffset(
            $validated['movement_type'],
            $validated['event_id'] ?? null
        );

        // Determine which scope was used
        $source = 'global';
        if ($validated['event_id'] ?? null) {
            $eventSetting = Setting::where('key', "movement_offset.{$validated['movement_type']}")
                ->where('scope', Setting::SCOPE_EVENT)
                ->where('scope_id', $validated['event_id'])
                ->first();
            if ($eventSetting) {
                $source = 'event';
            }
        }

        return response()->json([
            'offset' => $offset,
            'source' => $source,
            'hours' => abs($offset) / 60,
            'formatted' => $this->formatOffset($offset),
        ]);
    }

    /**
     * Preview how many movements would be recalculated by a global or event
     * offset change, before the change is actually saved.
     */
    public function previewImpact(Request $request)
    {
        $validated = $request->validate([
            'movement_type' => ['required', Rule::in(SettingsService::OFFSET_TYPES)],
            'scope' => 'required|in:global,event',
            'event_id' => 'required_if:scope,event|nullable|exists:events,id',
        ]);

        if ($validated['movement_type'] === 'daily_ops') {
            return response()->json(['count' => 0, 'excluded' => true]);
        }

        $targetEventId = $validated['scope'] === 'event'
            ? (int) $validated['event_id']
            : $request->session()->get('active_event_id');

        if (!$targetEventId) {
            return response()->json(['count' => 0, 'no_active_event' => true]);
        }

        // A bus type covers only the road legs of its kind; a flight type is recomputed for every
        // movement of its kind (road legs follow it until they have bus offsets of their own).
        $type = $validated['movement_type'];
        $count = Movement::where('event_id', $targetEventId)
            ->where('kind', SettingsService::offsetKind($type))
            ->withoutJob()
            ->with('flight:id,flight_number')
            ->get()
            ->filter(fn (Movement $movement) => !isset(SettingsService::OFFSET_FALLBACK[$type]) || $movement->offsetType() === $type)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * True if another row (different id) already occupies this exact
     * key/scope/scope_id/checkpoint_id slot. Only relevant when editing an
     * existing row by ID and changing its movement type or checkpoint — the
     * plain add/upsert flow (no excludeId) never conflicts, since setSetting()
     * updates the existing row for that slot instead of duplicating it.
     */
    private function duplicateOverrideExists(string $key, string $scope, ?int $scopeId, ?int $checkpointId, ?int $excludeId): bool
    {
        if (!$excludeId) {
            return false;
        }

        $query = Setting::where('key', $key)->where('scope', $scope)->where('id', '!=', $excludeId);
        $scopeId ? $query->where('scope_id', $scopeId) : $query->whereNull('scope_id');
        $checkpointId ? $query->where('checkpoint_id', $checkpointId) : $query->whereNull('checkpoint_id');

        return $query->exists();
    }

    /**
     * Format offset for display.
     */
    private function formatOffset(int $minutes): string
    {
        $hours = abs($minutes) / 60;
        $direction = $minutes < 0 ? 'before' : 'after';
        
        if ($hours >= 1) {
            return number_format($hours, 1) . ' hours ' . $direction;
        } else {
            return abs($minutes) . ' minutes ' . $direction;
        }
    }
}
