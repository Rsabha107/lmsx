<?php

namespace App\Http\Resources;

use App\Support\CountryFlags;
use App\Support\FlightSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Job payload for the mobile supervisor app.
 */
class JobResource extends JsonResource
{
    /**
     * The list endpoint only needs counts and the next step; embedding every
     * checkpoint for every job bloats the payload by an order of magnitude.
     *
     * Set via a factory rather than a constructor argument: Collection::mapInto()
     * calls `new static($value, $key)`, so a second constructor parameter would
     * receive the collection index and coerce to true for every item but the first.
     */
    private bool $withCheckpoints = false;

    public static function withCheckpoints($resource): self
    {
        $resource = new self($resource);
        $resource->withCheckpoints = true;

        return $resource;
    }

    public function toArray(Request $request): array
    {
        $movement = $this->movement;
        $team = $this->team ?? $movement?->team;
        // The first checkpoint's time; the movement start is derived from the same checkpoint.
        $pickupAt = $this->firstCheckpoint()?->scheduled_at ?? $movement?->window_start;

        return [
            'id' => $this->id,
            'job_id' => $this->job_id ?? 'JOB-'.$this->id,
            'status' => $this->status,
            'status_label' => \App\Models\JobOperation::statusLabel($this->status),

            'team' => $team?->team_name ?? 'Unassigned team',
            'team_code' => $team?->code ?? '—',
            'team_flag' => $team?->flag,
            // Team codes carry a squad suffix ("ALG-17"), so fall back to its stem when no country is set.
            'team_country_iso' => CountryFlags::iso($team?->country_id)
                ?? CountryFlags::iso(explode('-', (string) $team?->code)[0]),

            'kind' => $movement?->kind ?? 'transfer',
            'from' => $movement?->from_location ?? '—',
            'to' => $movement?->to_location ?? '—',
            'window_start' => $movement?->window_start?->toIso8601String(),
            'window_end' => $movement?->window_end?->toIso8601String(),
            'window' => $this->formatWindow($movement),
            'pickup_at' => $pickupAt?->toIso8601String(),
            'pickup_time' => $pickupAt?->format('H:i'),
            'pickup_date' => $pickupAt?->format('D j M'),
            'pickup_checkpoint' => $this->firstCheckpoint()?->name,
            'delay_minutes' => $movement?->delay_minutes,

            'pax' => $movement?->passengers ?? 0,
            'vehicle' => $this->vehicle?->code
                ?? $this->vehicle?->plate_number
                ?? 'Unassigned',
            'driver' => $this->driver?->name,
            'driver_phone' => $this->driver?->phone,
            'supervisor' => $this->supervisor?->name,

            'checkpoints_completed' => (int) $this->checkpoints_completed,
            'checkpoints_total' => (int) $this->checkpoints_total,
            'progress' => (float) $this->progress_percentage,

            'next_checkpoint' => $this->whenLoaded('checkpoints', function () {
                $next = $this->checkpoints
                    ->whereIn('state', ['active', 'pending'])
                    ->sortBy('order')
                    ->first();

                return $next?->name;
            }),

            'checkpoints' => $this->when(
                $this->withCheckpoints && $this->relationLoaded('checkpoints'),
                fn () => CheckpointResource::collection($this->checkpoints)->resolve()
            ),

            'match' => $this->matchPayload($movement?->match_id ? $movement->match : null),
            'flight' => in_array($movement?->kind, ['arrival', 'departure'], true)
                ? FlightSummary::from($movement->flight)
                : null,
        ];
    }

    /**
     * Times are pre-formatted in the event's timezone so the app shows them as
     * the schedule does, whatever timezone the phone is set to.
     */
    private function matchPayload($match): ?array
    {
        if (! $match) {
            return null;
        }

        $side = fn ($team) => $team ? [
            'code' => $team->code,
            'name' => $team->team_name,
            'country_iso' => CountryFlags::iso($team->country_id)
                ?? CountryFlags::iso(explode('-', (string) $team->code)[0]),
        ] : null;

        return [
            'number' => $match->match_number,
            'stage' => $match->stage,
            'venue' => $match->venue?->name,
            'kick_off' => $match->kick_off?->toIso8601String(),
            'kick_off_time' => $match->kick_off?->format('H:i'),
            'kick_off_date' => $match->kick_off?->format('D j M'),
            'gates_opening' => $match->gates_opening?->format('H:i'),
            'team1' => $side($match->team1),
            'team2' => $side($match->team2),
        ];
    }

    private function firstCheckpoint()
    {
        return $this->relationLoaded('checkpoints') ? $this->checkpoints->sortBy('order')->first() : null;
    }

    private function formatWindow($movement): string
    {
        if (! $movement?->window_start) {
            return '--:-- – --:--';
        }

        return $movement->window_start->format('H:i')
            .' – '
            .($movement->window_end?->format('H:i') ?? '--:--');
    }
}
