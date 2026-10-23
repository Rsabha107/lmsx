<?php

namespace App\Http\Middleware;

use App\Models\Event;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $activeEventId = $request->session()->get('active_event_id');
        // An event moved to the recycle bin stops being anyone's active event.
        if ($activeEventId && ! Event::whereKey($activeEventId)->exists()) {
            $request->session()->forget('active_event_id');
            $activeEventId = null;
        }

        return array_merge(parent::share($request), [
            'appName'       => 'NAQLA LMS',
            'today'         => now()->format('D, d M Y'),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
                'roles' => $request->user()?->getRoleNames()->values()->all() ?? [],
                'can' => $this->abilities($request),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'status'  => $request->session()->get('status'),
                // Movements a flight edit re-timed or flagged (Event Teams shows them in a dialog).
                'flight_change' => $request->session()->get('flight_change'),
            ],
            // Offering an event the user would be 403'd on is worse than hiding it.
            'eventList'     => $request->user()
                ? Event::active()->accessibleTo($request->user())->orderBy('start_date', 'desc')->get(['id', 'name', 'short_name', 'event_logo', 'start_date', 'end_date', 'active_flag'])
                    ->filter(fn (Event $event) => $request->user()->canAccessEvent($event->id))
                    ->values()
                    ->toArray()
                : [],
            'activeEventId' => $activeEventId ? (int) $activeEventId : null,
            'ui' => $this->uiFlags(),
            // The one source of status wording; the mobile API sends the same labels.
            'jobStatusLabels' => \App\Models\JobOperation::uiStatusLabels(),
            // Delete dialogs say up front when a second admin must approve.
            'deletionRules' => [
                'approveOver' => \App\Services\DeletionService::APPROVAL_OVER,
                'keepDays' => \App\Services\DeletionService::KEEP_DAYS,
            ],
            // Delete requests other admins are waiting on, for the sidebar badge.
            'pendingDeletions' => fn () => $request->user()?->can('plans.manage')
                ? \App\Models\Deletion::pending()->where('requested_by', '!=', $request->user()->id)->count()
                : 0,
        ]);
    }

    /**
     * Admin-controlled UI toggles (Setups > Settings), shared with every page
     * so the sidebar can hide what an installation doesn't use.
     *
     * @return array<string, bool>
     */
    private function uiFlags(): array
    {
        $settings = app(SettingsService::class);

        return [
            'jobsMobileMenu' => $settings->getGlobalFlag(SettingsService::FLAG_JOBS_MOBILE_MENU),
            'utilities' => $settings->getGlobalFlag(SettingsService::FLAG_UTILITIES),
        ];
    }

    /**
     * Abilities the sidebar uses to hide links the user would only be 403'd on.
     *
     * @return array<string, bool>
     */
    private function abilities(Request $request): array
    {
        $user = $request->user();

        $names = [
            'console.view',
            'jobs.view',
            'jobs.override',
            'plans.view',
            'plans.manage',
            'movements.view',
            'movements.assign-crew',
            'fleet.view',
            'fleet.manage',
            'events.view',
            'analytics.view',
            'audit.view',
            'access.manage',
            'roster.manage',
            'ai.use',
        ];

        $can = [];
        foreach ($names as $name) {
            $can[$name] = (bool) $user?->can($name);
        }

        $can['setups'] = (bool) $user?->hasRole('admin');
        $can['mobileApp.download'] = (bool) $user?->hasAnyRole(['admin', 'agency', 'ground_control', 'transport', 'mobile_viewer']);
        $can['dayBoard.view'] = $can['jobs.view'] || (bool) $user?->hasRole('agency');

        return $can;
    }
}
