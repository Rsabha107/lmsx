<?php

namespace App\Http\Middleware;

use App\Models\Event;
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

        return array_merge(parent::share($request), [
            'appName'       => 'NAQLA LMS',
            'matchDay'      => 'Match Day 4',
            'today'         => now()->format('D, d M Y'),
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
                'can' => $this->abilities($request),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'status'  => $request->session()->get('status'),
            ],
            // Offering an event the user would be 403'd on is worse than hiding it.
            'eventList'     => $request->user()
                ? Event::orderBy('start_date', 'desc')->get(['id', 'name', 'short_name'])
                    ->filter(fn (Event $event) => $request->user()->canAccessEvent($event->id))
                    ->values()
                    ->toArray()
                : [],
            'activeEventId' => $activeEventId ? (int) $activeEventId : null,
        ]);
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
            'fleet.view',
            'events.view',
            'analytics.view',
            'audit.view',
            'ai.use',
        ];

        $can = [];
        foreach ($names as $name) {
            $can[$name] = (bool) $user?->can($name);
        }

        $can['setups'] = (bool) $user?->hasRole('admin');

        return $can;
    }
}
