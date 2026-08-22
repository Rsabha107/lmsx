<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\WebSearch;

/**
 * Looks up a published airline flight schedule via web search, for flights too
 * far in the future for AviationStack's live-tracking data to cover. Read-only:
 * this is an LLM's best-effort answer from search results, not a verified feed,
 * so callers should treat it as a suggestion for a human to review, not a fact
 * to write straight into the database.
 */
class FlightScheduleLookupAgent implements Agent, HasTools, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
            You look up published airline flight schedules using web search, for
            event logistics staff planning ground transport around a specific
            flight, often months ahead of the travel date.

            Rules you must follow:
            - Search for the exact flight number and date given in the prompt.
            - Only report information you actually found via search - never guess
              or infer a time from a "typical" schedule if you can't find a source
              that confirms it applies to the requested date.
            - If you can't find reliable information for that exact date, set
              "found" to false and explain why in "notes" rather than returning a
              best guess.
            - Prefer the operating airline's own website, FlightAware, and
              FlightRadar24 as sources over less authoritative pages.
            - Report times in local time at each airport, not UTC, unless you
              can't determine the local timezone - note that in "notes" if so.
            - Always include the URL of the page you found the information on.
        INSTRUCTIONS;
    }

    public function tools(): iterable
    {
        return [
            new WebSearch(maxSearches: 4),
        ];
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'found' => $schema->boolean()->required()
                ->description('Whether reliable scheduled flight information was found for this exact flight number and date.'),
            'airline' => $schema->string()->nullable()
                ->description('Operating airline name.'),
            'origin_iata' => $schema->string()->nullable()
                ->description('3-letter IATA code of the departure airport.'),
            'destination_iata' => $schema->string()->nullable()
                ->description('3-letter IATA code of the arrival airport.'),
            'scheduled_departure' => $schema->string()->nullable()
                ->description('Scheduled local departure date/time in ISO 8601 format, e.g. 2026-11-16T18:30:00.'),
            'scheduled_arrival' => $schema->string()->nullable()
                ->description('Scheduled local arrival date/time in ISO 8601 format.'),
            'terminal' => $schema->string()->nullable(),
            'gate' => $schema->string()->nullable(),
            'source_url' => $schema->string()->nullable()
                ->description('URL of the page where this information was found.'),
            'confidence' => $schema->string()->nullable()
                ->enum(['high', 'medium', 'low'])
                ->description('How confident you are this schedule is accurate for the requested date.'),
            'notes' => $schema->string()->nullable()
                ->description('Any caveats or explanation, especially when found is false.'),
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
        // Web search takes longer than the pure-DB copilot tools, so this
        // deliberately doesn't share services.anthropic.timeout.
        return 30;
    }
}
