<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Reads a table out of a PDF and returns its rows keyed by our import fields.
 *
 * Unlike SheetColumnMappingAgent - which only picks a column->field mapping and
 * leaves the cell values to deterministic code - a PDF has no cells to read, so
 * this agent does return the values themselves. That is the whole reason the
 * result is shown for review before anything is downloaded or imported: a PDF
 * conversion is a first draft, not a trusted extract.
 *
 * The field list and domain rules come from the calling reader, so one agent
 * serves every template.
 */
class PdfTableExtractionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param array<int, string> $fields every field a row may carry
     * @param string $requiredField the field a row is keyed on; rows without it are useless
     * @param string $subject what the table is about
     * @param string $guidance domain-specific rules, one per line
     * @param array<int, string> $dateFields fields that must come back as YYYY-MM-DD
     * @param array<int, string> $timeFields fields that must come back as HH:MM
     */
    public function __construct(
        private array $fields = [],
        private string $requiredField = '',
        private string $subject = 'event data',
        private string $guidance = '',
        private array $dateFields = [],
        private array $timeFields = [],
    ) {
    }

    public function instructions(): string
    {
        $guidance = trim($this->guidance);
        $domainRules = $guidance === '' ? '' : "\n" . $guidance . "\n";

        return <<<INSTRUCTIONS
            You read a PDF containing a table about {$this->subject} and return its
            rows as structured data, so it can be converted to a standard import
            template.

            The PDF is usually a wide spreadsheet printed to paper: one logical
            table whose columns did not fit the page width, so the remaining
            columns continue on later pages while the rows keep the same order.

            Rules you must follow:
            - Return one entry in "rows" per record, in the order they appear.
              Every row must have a value for "{$this->requiredField}" - the key the
              import matches on. Drop any row you cannot give one for.
            - Stitch a continuation page back onto the rows it belongs to only when
              it is plainly the right-hand continuation of the same table: same
              number of rows, same order, and no identity column of its own. If a
              page repeats the identity column with different values it is a
              DIFFERENT table - do not merge it, and say so in "notes".
            - Copy values exactly as printed. Never invent, complete or tidy a value
              you cannot see. Leave a field null rather than guessing at it.
            - Blank cells, dashes, "TBC", "N/A" and spreadsheet errors such as
              "#REF!" are all null, not text.
            - Rejoin values that wrapped onto two printed lines (a long team name, a
              status like "2. UNDER DISCUSSIONS") into a single value.
            - A heading that spans several columns is a group label, not a field:
              "ARRIVAL" or "INBOUND" above a flight number tells you that flight
              belongs to the arrival leg.
            {$this->dateTimeRules()}{$domainRules}
            - Put anything a human must check into "notes": rows you were unsure of,
              fields you could not find, pages you refused to merge.
            INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $row = [];

        foreach ($this->fields as $field) {
            $row[$field] = match (true) {
                in_array($field, $this->dateFields, true) => $schema->string()->nullable()
                    ->description('Date as YYYY-MM-DD.'),
                in_array($field, $this->timeFields, true) => $schema->string()->nullable()
                    ->description('Time as HH:MM, 24-hour.'),
                default => $schema->string()->nullable(),
            };
        }

        return [
            'rows' => $schema->array()->required()
                ->description('One entry per record in the table, in the order printed.')
                ->items($schema->object($row)),
            'notes' => $schema->string()->nullable()
                ->description('Anything a human should check: unclear rows, missing fields, pages you did not merge.'),
        ];
    }

    private function dateTimeRules(): string
    {
        $rules = [];

        if ($this->dateFields !== []) {
            $rules[] = '- These fields are dates and must be written as YYYY-MM-DD: '
                . implode(', ', $this->dateFields) . '. Printed dates often omit the'
                . "\n  year (\"28-Oct\"); take it from the event context given with the prompt.";
        }

        if ($this->timeFields !== []) {
            $rules[] = '- These fields are times and must be written as HH:MM on a 24-hour'
                . "\n  clock: " . implode(', ', $this->timeFields) . '.';
        }

        return $rules === [] ? '' : implode("\n", $rules) . "\n";
    }
}
