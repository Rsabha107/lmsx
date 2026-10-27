<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pages that can show several events at once (Crew Assignment, Jobs Queue, Day Board): the
 * active event first, plus the others picked with ?events[]= (remembered per page in the
 * session), limited to events the user may open.
 */
class EventSelection
{
    /** @return list<int> */
    public static function ids(Request $request, string $sessionKey): array
    {
        $active = (int) $request->session()->get('active_event_id');
        if ($request->has('events')) {
            $picked = array_map('intval', array_filter((array) $request->query('events'), 'is_numeric'));
            $request->session()->put($sessionKey, $picked);
        } else {
            $picked = (array) $request->session()->get($sessionKey, []);
        }

        $others = $picked
            ? Event::accessibleTo($request->user())->whereIn('id', array_diff($picked, [$active]))->orderBy('name')->pluck('id')->all()
            : [];

        return array_values(array_filter([$active ?: null, ...$others]));
    }

    /** The events that can be shown alongside the active one, for the picker. */
    public static function options(User $user, ?int $activeId): array
    {
        return Event::accessibleTo($user)
            ->when(! $user->canAccessAllEvents() && $activeId, fn ($q) => $q->orWhere('id', $activeId))
            ->orderBy('name')->get(['id', 'name', 'short_name'])
            ->map(fn (Event $e) => ['id' => $e->id, 'name' => $e->name, 'short' => $e->short_name ?: $e->name])
            ->all();
    }
}
