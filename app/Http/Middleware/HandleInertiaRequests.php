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
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'status'  => $request->session()->get('status'),
            ],
            'eventList'     => $request->user()
                ? Event::orderBy('start_date', 'desc')->get(['id', 'name', 'short_name'])->toArray()
                : [],
            'activeEventId' => $activeEventId ? (int) $activeEventId : null,
        ]);
    }
}
