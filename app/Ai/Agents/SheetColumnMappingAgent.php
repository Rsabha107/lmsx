<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Works out which column of an arbitrary real-world spreadsheet feeds which
 * column of one of our import templates - the one step of a conversion that a
 * fixed alias list can't cover, because every organiser names their columns
 * differently ("Allocated Hotel", "Arrival in Doha", "PAX IN", ...).
 *
 * It only ever sees the first handful of rows (headers plus a few samples) and
 * only ever returns a column->field mapping. The actual cell values are read
 * and converted deterministically by the sheet readers, so nothing the model
 * says can invent, drop, or alter a row's data.
 *
 * The field list and domain rules come from the calling reader, so one agent
 * serves every template.
 */
class SheetColumnMappingAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param array<int, string> $fields fields a column may be mapped to
     * @param string $subject what the sheet is about
     * @param string $guidance domain-specific mapping rules, one per line
     */
    public function __construct(
        private array $fields = [],
        private string $subject = 'event data',
        private string $guidance = '',
    ) {
    }

    public function instructions(): string
    {
        $guidance = trim($this->guidance);
        $domainRules = $guidance === '' ? '' : "\n" . $guidance . "\n";

        return <<<INSTRUCTIONS
            You map the columns of a spreadsheet about {$this->subject} onto a
            fixed set of import fields, so the file can be converted to a standard
            import template.

            You are given the first rows of the sheet as a grid. Each line starts
            with its 1-based row number, then the cell values by column, each shown
            as "<column number>=<value>".

            Rules you must follow:
            - First decide which row is the real header row. Sheets often have a
              title row, a merged "grouping" row (e.g. "ARRIVAL" spanning several
              columns), or blank rows above it. The header row is the one whose
              cells name the individual columns.
            - Then map each column to exactly one field from the allowed list, or
              to "ignore" if it does not correspond to any field.
            - Use the sample data rows to disambiguate, not just the header text.
              A column headed "Flight" holding "QR 1234" is a flight number; one
              holding "18:05" is a time.
            - Copy each column's header text into "header" exactly as you see it.
              It is checked against the sheet, so a mismatch loses that mapping.
            - Never map two columns to the same field. If several columns could fit,
              pick the single best one and set the others to "ignore".
            - Do not guess wildly. If you are unsure a column is a given field, map
              it to "ignore" and say why in "notes". A missing field is recoverable;
              a wrongly mapped one silently corrupts every row.
            - Set "confidence" per column honestly: "high" only when the header text
              and the sample values both clearly agree.
            {$domainRules}
            INSTRUCTIONS;
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'header_row' => $schema->integer()->required()
                ->description('1-based row number of the row containing the column headers.'),
            'columns' => $schema->array()->required()
                ->description('One entry per column you were shown that maps to a field. Columns you would ignore may be omitted.')
                ->items($schema->object([
                    'column' => $schema->integer()->required()
                        ->description('1-based column number, as shown in the grid.'),
                    'header' => $schema->string()->nullable()
                        ->description('The header text of that column, copied verbatim.'),
                    'field' => $schema->string()->required()
                        ->enum([...$this->fields, 'ignore'])
                        ->description('The import field this column feeds, or "ignore".'),
                    'confidence' => $schema->string()->required()
                        ->enum(['high', 'medium', 'low']),
                ])),
            'notes' => $schema->string()->nullable()
                ->description('Anything a human should check: ambiguous columns, fields you could not find, assumptions you made.'),
        ];
    }
}
