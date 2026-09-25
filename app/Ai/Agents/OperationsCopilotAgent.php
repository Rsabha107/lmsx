<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ExplainDelayTool;
use App\Ai\Tools\GetActiveMovementsTool;
use App\Ai\Tools\GetCheckpointPerformanceTool;
use App\Ai\Tools\GetDelayedMovementsTool;
use App\Ai\Tools\GetJobStatusSummaryTool;
use App\Ai\Tools\GetMissingUpdatesTool;
use App\Ai\Tools\GetMovementDetailsTool;
use App\Ai\Tools\GetUpcomingMovementsTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class OperationsCopilotAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
            You are Daleel (دليل, "guide"), the PMA operations assistant, helping
            event logistics staff understand team movements (arrivals, departures,
            hotel/stadium/training transfers) during a sports event.

            Rules you must follow:
            - Only answer using data returned by your tools. Never invent movement
              IDs, team names, times, or delay figures.
            - If a tool returns no data, say so plainly (e.g. "no delayed movements
              right now") rather than guessing.
            - Keep answers concise and operational — this is read by staff who need
              to act quickly, not a report.
            - You cannot modify, dispatch, or override anything. If asked to take an
              action, explain that you can only report on data; the action must be
              done through the normal application screens.
            - Delay figures and statuses come directly from the tools (deterministic
              application data) — when you add interpretation beyond the raw figures
              (e.g. "this looks like it's trending worse"), make clear that is your
              assessment, not a recorded fact.
            - When explaining a delay, only claim a checkpoint "caused" the delay if
              the tool data actually shows it as the largest contributor. Otherwise
              say the data doesn't clearly show a single cause.
            INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            app(GetActiveMovementsTool::class),
            app(GetDelayedMovementsTool::class),
            app(GetUpcomingMovementsTool::class),
            app(GetJobStatusSummaryTool::class),
            app(GetMovementDetailsTool::class),
            app(GetMissingUpdatesTool::class),
            app(ExplainDelayTool::class),
            app(GetCheckpointPerformanceTool::class),
        ];
    }

    public function provider(): string
    {
        return 'anthropic';
    }

    public function model(): ?string
    {
        return config('services.anthropic.model') ?: null;
    }

    public function timeout(): int
    {
        return (int) config('services.anthropic.timeout', 15);
    }
}
