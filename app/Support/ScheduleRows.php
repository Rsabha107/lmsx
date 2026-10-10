<?php

namespace App\Support;

use App\Models\JobOperation;

/** One job as a row of the Movement Schedule (the Schedule page and the Day Board's schedule view). */
class ScheduleRows
{
    /** Relations a row reads. */
    public const RELATIONS = ['movement.team', 'movement.flight', 'vehicle'];

    public static function present(JobOperation $job): array
    {
        $movement = $job->movement;
        $team = $movement?->team;
        $delay = $movement?->delay_minutes;

        $status = 'scheduled';
        if ($delay > 0) {
            $status = 'delayed';
        } elseif ($job->status === 'completed') {
            $status = 'done';
        } elseif ($job->status === 'in-progress') {
            $status = 'in-progress';
        }

        return [
            'id' => $job->job_id ?? 'J-' . $job->id,
            'code' => $team?->code ?? 'UNK',
            'team' => $team?->team_name ?? 'Unknown Team',
            'from' => $movement?->from_location ?? 'Unknown',
            'to' => $movement?->to_location ?? 'Unknown',
            'dep' => $movement?->window_start?->format('H:i') ?? '--:--',
            'arr' => $movement?->window_end?->format('H:i') ?? '--:--',
            'pax' => $movement?->passengers ?? $movement?->flight?->party_size_total ?? $team?->party_size_total ?? 0,
            'vehicle' => $job->vehicle ? ($job->vehicle->code ?? $job->vehicle->plate_number ?? $job->vehicle->vehicle_type ?? 'Unassigned') : 'Unassigned',
            'status' => $status,
            'delay' => $delay > 0 ? $delay : null,
        ];
    }
}
