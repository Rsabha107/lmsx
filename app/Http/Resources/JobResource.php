<?php

namespace App\Http\Resources;

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

        return [
            'id' => $this->id,
            'job_id' => $this->job_id ?? 'JOB-'.$this->id,
            'status' => $this->status,

            'team' => $team?->team_name ?? 'Unassigned team',
            'team_code' => $team?->code ?? '—',
            'team_flag' => $team?->flag,

            'kind' => $movement?->kind ?? 'transfer',
            'from' => $movement?->from_location ?? '—',
            'to' => $movement?->to_location ?? '—',
            'window_start' => $movement?->window_start?->toIso8601String(),
            'window_end' => $movement?->window_end?->toIso8601String(),
            'window' => $this->formatWindow($movement),
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
        ];
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
