<?php

namespace App\Services;

use App\Models\CheckpointTemplate;
use App\Models\MovementTemplate;
use App\Models\MovementTemplateLeg;

/**
 * Copies checkpoint/movement templates from one event into another.
 */
class TemplateCopyService
{
    /**
     * Copy a checkpoint template, including its checkpoint sequence, into another event.
     */
    public function copyCheckpointTemplate(CheckpointTemplate $source, int $targetEventId): CheckpointTemplate
    {
        $copy = CheckpointTemplate::create([
            'event_id' => $targetEventId,
            'code' => $this->uniqueCode(CheckpointTemplate::class, $source->code),
            'name' => $source->name,
            'movement_type' => $source->movement_type,
            'description' => $source->description,
            'estimated_duration_minutes' => $source->estimated_duration_minutes,
            'is_active' => $source->is_active,
        ]);

        foreach ($source->checkpoints as $checkpoint) {
            $copy->checkpoints()->attach($checkpoint->id, [
                'order' => $checkpoint->pivot->order,
                'is_required' => $checkpoint->pivot->is_required,
                'estimated_minutes' => $checkpoint->pivot->estimated_minutes,
            ]);
        }

        return $copy;
    }

    /**
     * Copy a movement template into another event, copying its legs and,
     * when needed, the checkpoint templates those legs reference.
     */
    public function copyMovementTemplate(MovementTemplate $source, int $targetEventId): MovementTemplate
    {
        $copy = MovementTemplate::create([
            'event_id' => $targetEventId,
            'code' => $this->uniqueCode(MovementTemplate::class, $source->code),
            'name' => $source->name,
            'description' => $source->description,
            'scenario_type' => $source->scenario_type,
            'functional_area' => $source->functional_area,
            'total_legs' => $source->total_legs,
            'estimated_duration_minutes' => $source->estimated_duration_minutes,
            'is_active' => $source->is_active,
        ]);

        // Reuse one copied checkpoint template per source template, even if
        // several legs reference the same one.
        $checkpointTemplateMap = [];

        foreach ($source->legs as $leg) {
            $sourceCheckpointTemplateId = $leg->checkpoint_template_id;

            if (!isset($checkpointTemplateMap[$sourceCheckpointTemplateId])) {
                $checkpointTemplateMap[$sourceCheckpointTemplateId] = $this->copyCheckpointTemplate(
                    $leg->checkpointTemplate,
                    $targetEventId
                )->id;
            }

            MovementTemplateLeg::create([
                'movement_template_id' => $copy->id,
                'checkpoint_template_id' => $checkpointTemplateMap[$sourceCheckpointTemplateId],
                'order' => $leg->order,
                'name' => $leg->name,
                'leg_type' => $leg->leg_type,
                'from_location' => $leg->from_location,
                'to_location' => $leg->to_location,
                'estimated_duration_minutes' => $leg->estimated_duration_minutes,
                'vehicle_type' => $leg->vehicle_type,
                'estimated_passengers' => $leg->estimated_passengers,
            ]);
        }

        return $copy;
    }

    /**
     * Find a code that isn't already taken (code is globally unique across events).
     */
    private function uniqueCode(string $modelClass, string $baseCode): string
    {
        $code = $baseCode;
        $suffix = 2;
        while ($modelClass::where('code', $code)->exists()) {
            $code = "{$baseCode}-{$suffix}";
            $suffix++;
        }

        return $code;
    }
}
