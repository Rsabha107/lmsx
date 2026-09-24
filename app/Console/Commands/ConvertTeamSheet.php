<?php

namespace App\Console\Commands;

use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use App\Services\TeamImportService;
use App\Services\TeamSheetReader;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Throwable;

/**
 * Converts an arbitrary event-teams spreadsheet (an organiser's own PMA sheet,
 * a travel agent's export, ...) into our standard team import template, showing
 * the result for review before anything is written.
 */
class ConvertTeamSheet extends Command
{
    use ReadsSpreadsheetRows;

    protected $signature = 'teams:convert
        {source : Path to the source spreadsheet, e.g. docs/GFFVC26/PMA event teams.xlsx}
        {--output= : Where to write the converted file (default: alongside the source, "<name> - import.xlsx")}
        {--rows=15 : How many converted rows to show in the preview (0 = all)}
        {--no-ai : Match columns using the built-in alias list only, never the AI mapper}
        {--provider= : AI provider for the column mapper (defaults to the app\'s configured provider)}
        {--force : Skip the confirmation prompt and write the file straight away}';

    protected $description = 'Convert a team spreadsheet into the team import template, previewing it first';

    public function handle(TeamSheetReader $reader): int
    {
        $source = $this->resolvePath($this->argument('source'));

        if (!is_file($source)) {
            $this->error("Source file not found: {$source}");

            return self::FAILURE;
        }

        $this->info("Reading {$source} ...");

        try {
            $rows = $reader->read($source, allowAi: !$this->option('no-ai'), provider: $this->option('provider'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->error('No team rows found in that file.');

            return self::FAILURE;
        }

        $this->showMapping($reader->report());

        $templateRows = array_map($this->toTemplateRow(...), $rows);
        $this->showPreview($templateRows);

        $output = $this->resolvePath(
            $this->option('output') ?: $this->defaultOutputPath($source)
        );

        $this->newLine();
        $this->line("Output file: <comment>{$output}</comment>");

        if (file_exists($output) && !$this->option('force')
            && !$this->confirm('That file already exists. Overwrite it?', false)) {
            $this->warn('Nothing written.');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Save this conversion?', true)) {
            $this->warn('Nothing written.');

            return self::SUCCESS;
        }

        $spreadsheet = $this->buildTemplateSpreadsheet('Teams', TeamImportService::HEADERS, $templateRows);
        (new Xlsx($spreadsheet))->save($output);

        $this->info('Saved ' . count($templateRows) . " rows to {$output}");
        $this->line('Upload it on the event\'s Teams page to import.');

        return self::SUCCESS;
    }

    /**
     * Flattens one reader row into the template's column order. The reader may
     * return an airport as a soft hint parsed from an itinerary rather than a
     * validated "Airport Code" - the venue leg is what the template's Airport
     * Code column means, so that is what gets written.
     *
     * @param array<string, mixed> $row
     * @return array<int, mixed>
     */
    private function toTemplateRow(array $row): array
    {
        $row['airport_code'] ??= $row['venue_airport_code_hint'] ?? null;

        return array_map(
            fn (string $header) => $row[str_replace(' ', '_', strtolower($header))] ?? '',
            TeamImportService::HEADERS,
        );
    }

    /**
     * @param array{header_row: int, columns: array<int, array<string, mixed>>, ai_notes: ?string, used_ai: bool} $report
     */
    private function showMapping(array $report): void
    {
        $this->newLine();
        $this->line("Header row: <comment>{$report['header_row']}</comment>"
            . ($report['used_ai'] ? '   Column matching: <comment>aliases + AI</comment>' : '   Column matching: <comment>aliases</comment>'));

        $this->table(
            ['Source column', 'Source header', 'Template field', 'Matched by', 'Confidence'],
            array_map(fn (array $c) => [
                $c['column'],
                $c['header'],
                $c['field'],
                $c['source'],
                $c['confidence'] ?? '-',
            ], $report['columns']),
        );

        $unmapped = array_diff(
            array_map(fn (string $h) => str_replace(' ', '_', strtolower($h)), TeamImportService::HEADERS),
            array_column($report['columns'], 'field'),
        );

        if ($unmapped !== []) {
            $this->warn('No source column for: ' . implode(', ', $unmapped) . ' (these will be blank)');
        }

        if ($report['ai_notes']) {
            $this->newLine();
            $this->line('<comment>AI notes:</comment> ' . $report['ai_notes']);
        }
    }

    /**
     * @param array<int, array<int, mixed>> $templateRows
     */
    private function showPreview(array $templateRows): void
    {
        $limit = (int) $this->option('rows');
        $shown = $limit > 0 ? array_slice($templateRows, 0, $limit) : $templateRows;

        $this->newLine();
        $this->line('<info>Converted rows</info> (' . count($templateRows) . ' total)');
        $this->table(
            TeamImportService::HEADERS,
            array_map(
                fn (array $row) => array_map(fn ($v) => (string) ($v ?? ''), $row),
                $shown,
            ),
        );

        if (count($shown) < count($templateRows)) {
            $this->line('... ' . (count($templateRows) - count($shown)) . ' more rows not shown (use --rows=0 to see all)');
        }
    }

    private function defaultOutputPath(string $source): string
    {
        return dirname($source) . DIRECTORY_SEPARATOR
            . pathinfo($source, PATHINFO_FILENAME) . ' - import.xlsx';
    }

    private function resolvePath(string $path): string
    {
        return preg_match('/^([A-Za-z]:[\\\\\/]|[\\\\\/])/', $path) === 1
            ? $path
            : base_path($path);
    }
}
