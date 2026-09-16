<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Checkpoint payload for the mobile supervisor app.
 */
class CheckpointResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order' => $this->order,
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,

            'state' => $this->state,
            'is_required' => (bool) $this->is_required,
            'requires_photo' => (bool) $this->requires_photo,
            'requires_signature' => (bool) $this->requires_signature,
            // Only the checkpoint library carries this flag; job_checkpoints has
            // no snapshot column for it, so read through the relation.
            'requires_baggage_count' => (bool) ($this->checkpoint?->requires_baggage_count ?? false),
            'estimated_minutes' => $this->estimated_minutes,

            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'scheduled_time' => $this->scheduled_at?->format('H:i'),
            'actual_time' => $this->completed_at?->format('H:i'),

            'is_on_time' => $this->is_on_time,
            // JobCheckpoint::getDelayMinutesAttribute() shadows this column and
            // returns null whenever scheduled_at is unset, which contradicts
            // is_on_time. Report the stored value so the two agree.
            'delay_minutes' => $this->getRawOriginal('delay_minutes'),

            'was_overridden' => (bool) $this->was_overridden,
            'skip_reason' => $this->skip_reason,
            'notes' => $this->notes,

            'planned_bags' => $this->planned_bags,
            'bags_loaded' => $this->bags_loaded,
            'food_bags' => $this->food_bags,
            'oversized_pieces' => $this->oversized_pieces,

            'has_photo' => (bool) $this->photo_path,
            'has_signature' => (bool) $this->signature_path,
            // Evidence lives on a private disk, so expose authenticated endpoints
            // rather than a public storage URL.
            'photo_url' => $this->photo_path
                ? route('api.mobile.checkpoints.photo', $this->id)
                : null,
            'signature_url' => $this->signature_path
                ? route('api.mobile.checkpoints.signature', $this->id)
                : null,

            'completed_by' => $this->whenLoaded(
                'completedBy',
                fn () => $this->completedBy?->name
            ),
        ];
    }
}
