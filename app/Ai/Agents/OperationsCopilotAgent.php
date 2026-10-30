<?php

namespace App\Ai\Agents;

use App\Ai\Tools\ExplainDelayTool;
use App\Ai\Tools\GetActiveMovementsTool;
use App\Ai\Tools\GetCheckpointPerformanceTool;
use App\Ai\Tools\GetCrewOptionsTool;
use App\Ai\Tools\GetDataQualityTool;
use App\Ai\Tools\GetFlightDelayImpactTool;
use App\Ai\Tools\GetDelayedMovementsTool;
use App\Ai\Tools\GetJobStatusSummaryTool;
use App\Ai\Tools\GetMissingUpdatesTool;
use App\Ai\Tools\GetMovementDetailsTool;
use App\Ai\Tools\GetMovementsByDateTool;
use App\Ai\Tools\GetUpcomingMovementsTool;
use App\Ai\Tools\GetPlanReadinessTool;
use App\Ai\Tools\GetResourceBottlenecksTool;
use App\Ai\Tools\GetShiftBriefingTool;
use App\Support\AiProvider;
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
     * @param  string|null  $context  what the user is looking at (built and checked by AiCopilotController)
     */
    public function __construct(array $history = [], private ?string $context = null)
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
        $today = now()->format('l, j F Y').' ('.now()->toDateString().')';

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
            - The status summary tool counts the whole event, not one day: never use
              it for a question about a particular day, and never mix its numbers
              with a day's figures.
            - Vehicles, trucks, buses, drivers or supervisors needed / short / clashing,
              for a day or a range: use the resource bottlenecks tool. Say how many are
              required, assigned and still missing, the peak at one time against the
              pool, and any clashes. A pool of 0 means it isn't set up, not a shortage.
            - "What needs attention", "brief me", "handover", "next N hours", "brief me
              on tomorrow": use the shift briefing tool. Lead with the total and the
              most urgent items (overdue updates, delays, conflicts, missing crew
              starting soonest), then one table per category that has items:
              Time | Team | Job / Movement | Problem. Give each category's full count
              even when only some items are listed. If nothing needs attention, say so
              in one line.
            - "Why can't I pick X", "who is free for this movement", "why is this
              driver / vehicle / supervisor unavailable": use the crew options tool and
              give each unavailable one's reason as the tool states it.
            - Next step of a job ("what's next", "is it at risk"): the delay tool's
              next_checkpoint says overdue / at risk / on track and why (basis).
            - A flight running late / "what if QR123 is 45 min late": use the flight delay
              impact tool. Say the assumed new time, which movements move (old → new
              window, linked), then what is newly at risk, most severe first, each with
              its fact; then anything that gets worse. If nothing new is at risk, say so.
              Make clear it is a what-if and nothing was changed.
            - "Is our data OK", "check the flights", data quality: use the data quality tool.
              High-severity findings first, grouped by category, each with its fact and
              link; say what fixes it (Sync on Event Teams, generate the job, change the
              crew, fix the template). If it finds nothing, say the data looks clean.
            - Shortages by provider or functional area: the bottlenecks tool's
              by_provider and by_functional_area.
            - "Which plans aren't ready", "why can't this plan generate jobs", "what
              is blocking plan X": use the plan readiness tool. Per plan: status, what
              is missing, and its next step; list the blocked movements with their
              problems. Link plans and movements with the urls given.
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

            How to format answers (the app renders Markdown):
            - Start with one short summary sentence with the key numbers, taken from
              the tool result you are answering from. Counts you list must add up
              to the total you state; never reuse numbers from these instructions.
            - Two or more movements or jobs: one Markdown table, sorted by time, with
              the columns Time | Team | Movement | Route | Job | Status. Time is the
              window as HH:MM–HH:MM, Movement the kind (Arrival, Departure, Match,
              Transfer, Training), Route "From → To", Job the job id or "—", Status a
              single word or phrase from the data (Scheduled, Pending, Dispatched,
              In progress, Completed, Cancelled, Delayed, No job yet). Put the movement
              code in the Team cell after the team name, e.g. "Oman · TRP-00619".
            - A single movement or job: a short heading line with the team and kind,
              then "**Label:** value" bullet lines. Never nest lists or number them.
            - End with a line starting "Attention:" only when something needs action
              (no job, no crew, delayed, overdue update); otherwise leave it out.
            - Evidence: every recommendation or judgement (at risk, blocked, short,
              unavailable, should check) names the recorded fact behind it, in the
              tool's words: the time, the clash, the missing resource or the reason.
              No fact in the data, no recommendation.
            - Link records: when a tool result has a "url" for a job or movement, write
              its job id (or movement code when there is no job) as a Markdown link to
              that url, e.g. [JOB-20261028-0003](/job/42). Use only urls the tools
              returned, never make one up, and never link to other sites.
            - No filler ("Here are…", "Let me know if…").
            INSTRUCTIONS
            .($this->context ? "\n\n{$this->context} Read \"this plan\", \"this day\", \"this job\" or \"this conflict\" in the question as that. Still look everything up with your tools." : '');
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
            app(GetShiftBriefingTool::class),
            app(GetPlanReadinessTool::class),
            app(GetResourceBottlenecksTool::class),
            app(GetCrewOptionsTool::class),
            app(GetDataQualityTool::class),
            app(GetFlightDelayImpactTool::class),
        ];
    }

    public function provider(): string
    {
        return AiProvider::current();
    }

    public function model(): ?string
    {
        return AiProvider::model();
    }

    public function timeout(): int
    {
        return (int) config('services.anthropic.timeout', 15);
    }
}
