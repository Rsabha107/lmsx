<?php

namespace App\Http\Middleware;

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
        return array_merge(parent::share($request), [
            'appName'   => 'Atlas Cup LMS',
            'eventName' => 'Atlas Cup 2026',
            'matchDay'  => 'Match Day 4',
            'today'     => 'Sat, 18 Apr 2026',
            'auth' => [
                'user' => $request->user()?->only('id', 'name', 'email'),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'status'  => $request->session()->get('status'),
            ],
        ]);
    }
}
