<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ExplainDelayTool;
use App\Ai\Tools\GetActiveMovementsTool;
use App\Ai\Tools\GetCheckpointPerformanceTool;
use App\Ai\Tools\GetDelayedMovementsTool;
use App\Ai\Tools\GetJobStatusSummaryTool;
use App\Ai\Tools\GetMissingUpdatesTool;
use App\Ai\Tools\GetMovementDetailsTool;
use App\Ai\Tools\GetMovementsByDateTool;
use App\Ai\Tools\GetUpcomingMovementsTool;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;

class OperationsCopilotAgent implements Agent, Conversational, HasTools
{
    use Promptable;

    /** @var array<int, array{role: string, content: string}> */
    private array $history;

    /**
     * @param  array<int, array{role?: mixed, content?: mixed}>  $history  earlier turns of this chat, oldest first
     */
    public function __construct(array $history = [])
    {
        $this->history = self::alternating($history);
    }

    /**
     * The provider needs user and assistant turns to alternate, starting with the
     * user and ending with the assistant (the new question follows).
     *
     * @return array<int, array{role: string, content: string}>
     */
    private static function alternating(array $history): array
    {
        $turns = [];

        foreach ($history as $turn) {
            $role = $turn['role'] ?? null;
            $content = trim((string) ($turn['content'] ?? ''));

            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $expected = $turns === [] || end($turns)['role'] === 'assistant' ? 'user' : 'assistant';

            if ($role === $expected) {
                $turns[] = ['role' => $role, 'content' => $content];
            }
        }

        if ($turns !== [] && end($turns)['role'] === 'user') {
            array_pop($turns);
        }

        return $turns;
    }

    public function messages(): iterable
    {
        return array_map(fn (array $turn) => new Message($turn['role'], $turn['content']), $this->history);
    }

    public function instructions(): string
    {
        $today = now()->format('l, j F Y');

        return <<<INSTRUCTIONS
            You are Daleel (دليل, "guide"), the PMA operations assistant, helping
            event logistics staff understand team movements (arrivals, departures,
            hotel/stadium/training transfers) during a sports event.

            Today is {$today}. Resolve relative dates ("tomorrow", "next Friday") and
            dates written without a year against it.

            Rules you must follow:
            - Only answer using data returned by your tools. Never invent movement
              IDs, team names, times, or delay figures.
            - Be proactive. Never ask the user for a numeric ID or for a reference you
              can look up yourself. Movements and jobs can be looked up by job id
              (JOB-...), movement code (TRP-...), or by team and date.
            - This is a continuing conversation. Resolve "it", "that one", "the first
              job", "the Mexico one" or "what about tomorrow" from the earlier messages
              and answer directly. Ask a clarifying question only when the earlier
              messages genuinely leave it ambiguous, and then offer the likely choices.
            - When listing movements, always show their job id and movement code so
              the user can refer to them.
            - Look things up first, then answer; do not describe what you could do.
            - For a question about a specific day, call the date lookup tool with
              that day. The "upcoming" tool only covers the next few hours, so an
              empty result from it says nothing about other days.
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
            app(GetMovementsByDateTool::class),
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
