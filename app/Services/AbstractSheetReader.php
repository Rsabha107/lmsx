<?php

namespace App\Services;

use App\Ai\Agents\PdfTableExtractionAgent;
use App\Ai\Agents\SheetColumnMappingAgent;
use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Document;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Turns an arbitrary spreadsheet into rows keyed by the field names an import
 * service expects.
 *
 * Column matching is done first against a fixed alias list; when that leaves
 * fields unmatched (a sheet from an organiser we haven't seen before), an LLM
 * can be asked to map the remaining columns. The model only ever chooses a
 * column-to-field mapping - every cell value is still read and converted by
 * the deterministic code below, so the data itself is never AI-generated.
 *
 * A PDF has no cells to read, so that path works differently: the document is
 * handed to PdfTableExtractionAgent, which returns the row values themselves.
 * That output is a draft for a human to check, which is why every caller shows
 * it for review before it becomes a file.
 *
 * Subclasses supply the alias list, the field set, and any domain-specific
 * post-processing of a parsed row.
 */
abstract class AbstractSheetReader
{
    use ReadsSpreadsheetRows;

    /** Rows of the source sheet shown to the mapping agent (headers plus samples). */
    private const AI_PREVIEW_ROWS = 14;

    /** Upper bound on rows accepted back from the PDF agent, as a runaway guard. */
    private const PDF_MAX_ROWS = 1000;

    /** A whole PDF plus a row-per-record answer routinely runs past the package's 60s default. */
    private const PDF_TIMEOUT = 300;

    /**
     * Overridden by every subclass; declared here so the PDF path can read them
     * off the abstract type. See ReadsSpreadsheetRows for what they mean.
     */
    protected const DATE_FIELDS = [];
    protected const TIME_FIELDS = [];

    /**
     * How the columns of the last-read file were matched, for showing a human
     * what the conversion is about to do.
     *
     * @var array{source_kind: string, header_row: int, columns: array<int, array{field: string, column: string, header: string, source: string, confidence: ?string}>, ai_notes: ?string, used_ai: bool}
     */
    private array $report = ['source_kind' => 'sheet', 'header_row' => 0, 'columns' => [], 'ai_notes' => null, 'used_ai' => false];

    /** @var array<string, ?string> "field:column" => confidence, for columns the agent placed */
    private array $aiColumnSources = [];

    /** The field a row must have a value in to count as a data row, and its human label. */
    abstract protected function requiredField(): string;

    abstract protected function requiredFieldLabel(): string;

    /**
     * Every field a column may be mapped to.
     *
     * @return array<int, string>
     */
    abstract protected function mappableFields(): array;

    /** What the sheet is about, for the mapping agent's instructions. */
    abstract protected function aiSubject(): string;

    /** Domain-specific rules for the mapping agent, one per line. */
    protected function aiGuidance(): string
    {
        return '';
    }

    /**
     * A field that may be sourced from several columns at once and concatenated,
     * unlike every other field which takes the first non-blank value.
     */
    protected function concatenatedField(): ?string
    {
        return null;
    }

    /**
     * Domain-specific post-processing of one parsed row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function transformRow(array $row): array
    {
        return $row;
    }

    /**
     * @param UploadedFile|string $file an uploaded file, or an absolute path to a local file
     * @param bool $allowAi ask the mapping agent about columns the alias list can't place;
     *                      required for a PDF, which can only be read by the extraction agent
     * @param string|null $provider AI provider for the agents; null uses the app default
     * @param string $context free-text about the event the file belongs to (name, dates),
     *                        used to resolve values a PDF prints without a year
     * @return array<int, array<string, mixed>> rows keyed by import field name
     */
    public function read(UploadedFile|string $file, bool $allowAi = false, ?string $provider = null, string $context = ''): array
    {
        if ($this->isPdf($file)) {
            return $this->readPdf($file, $allowAi, $provider, $context);
        }

        $sheet = $this->loadSheet($file);

        [$columnMap, $headerRowIndex] = $this->buildColumnMap($sheet, $allowAi, $provider);

        $this->buildReport($sheet, $columnMap, $headerRowIndex);

        return $this->extractRows($sheet, $columnMap, $headerRowIndex);
    }

    /**
     * @return array{source_kind: string, header_row: int, columns: array<int, array{field: string, column: string, header: string, source: string, confidence: ?string}>, ai_notes: ?string, used_ai: bool}
     */
    public function report(): array
    {
        return $this->report;
    }

    private function isPdf(UploadedFile|string $file): bool
    {
        $name = is_string($file) ? $file : $file->getClientOriginalName();

        return strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Hands the whole document to the extraction agent and sanitises what comes
     * back: unknown keys dropped, dates and times re-parsed, rows without the
     * required field discarded. The model's output is treated as untrusted input,
     * because that is what it is.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readPdf(UploadedFile|string $file, bool $allowAi, ?string $provider, string $context): array
    {
        if (!$allowAi) {
            throw new RuntimeException(
                'A PDF has no columns to match, so it can only be read by AI. Turn on AI conversion, or upload the spreadsheet this PDF was printed from.'
            );
        }

        $agent = new PdfTableExtractionAgent(
            $this->mappableFields(),
            $this->requiredField(),
            $this->aiSubject(),
            $this->aiGuidance(),
            static::DATE_FIELDS,
            static::TIME_FIELDS,
        );

        $prompt = trim("Extract the table from the attached PDF.\n\n" . $context);
        $document = is_string($file) ? Document::fromPath($file) : Document::fromUpload($file);

        // The PHP request must outlive the AI call it is waiting on.
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::PDF_TIMEOUT + 60);
        }

        try {
            $structured = $agent->prompt(
                $prompt,
                attachments: [$document],
                provider: $provider,
                timeout: self::PDF_TIMEOUT,
            )->structured;
        } catch (Throwable $e) {
            Log::warning(static::class . ': AI PDF extraction failed', ['message' => $e->getMessage()]);

            throw new RuntimeException(
                str_contains($e->getMessage(), 'timed out')
                    ? 'The AI took too long to read that PDF. Split it into fewer pages, or upload the spreadsheet instead.'
                    : 'The AI could not read that PDF. Please try again, or upload the spreadsheet instead.'
            );
        }

        $this->report = [
            'source_kind' => 'pdf',
            'header_row' => 0,
            'columns' => [],
            'ai_notes' => $structured['notes'] ?? null,
            'used_ai' => true,
        ];

        $fallbackYear = $this->yearFrom($context);
        $fields = $this->mappableFields();
        $rows = [];

        foreach (array_slice($structured['rows'] ?? [], 0, self::PDF_MAX_ROWS) as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $rowValues = [];
            foreach ($fields as $field) {
                $rowValues[$field] = $this->cleanPdfValue($field, $raw[$field] ?? null, $fallbackYear);
            }

            if (($rowValues[$this->requiredField()] ?? null) === null) {
                continue;
            }

            $rows[] = $this->transformRow($rowValues);
        }

        if ($rows !== []) {
            $this->report['columns'] = $this->pdfReportColumns($rows);
        }

        return $rows;
    }

    /**
     * Normalises one value the agent returned. Dates and times are re-parsed
     * rather than trusted: the agent is asked for ISO, but a value it copied
     * verbatim off the page ("28-Oct") would otherwise reach the import as-is.
     */
    private function cleanPdfValue(string $field, mixed $value, ?int $fallbackYear): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '' || in_array(strtoupper($value), [...self::EXCEL_ERROR_VALUES, 'N/A', 'TBC', 'NULL', '-'], true)) {
            return null;
        }

        $value = mb_substr(preg_replace('/\s+/u', ' ', $value), 0, 500);

        $kind = $this->fieldKind($field);

        if ($kind === null) {
            return $value;
        }

        try {
            $parsed = Carbon::parse($value);
        } catch (Throwable) {
            return null; // unparseable date/time - better blank than wrong
        }

        if ($kind === 'time') {
            return $parsed->format('H:i');
        }

        // "28-Oct" has no year of its own; Carbon defaults to the current one,
        // which is rarely the event's.
        if ($fallbackYear !== null && !preg_match('/\d{4}/', $value)) {
            $parsed->setYear($fallbackYear);
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * The PDF path has no source columns to report, so the review panel gets the
     * next most useful thing: which fields the agent actually filled in, and how
     * completely.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array{field: string, column: string, header: string, source: string, confidence: ?string}>
     */
    private function pdfReportColumns(array $rows): array
    {
        $total = count($rows);
        $columns = [];

        foreach ($this->mappableFields() as $field) {
            $filled = count(array_filter(
                array_column($rows, $field),
                fn ($value) => $value !== null && $value !== '',
            ));

            if ($filled === 0) {
                continue;
            }

            $columns[] = [
                'field' => $field,
                'column' => 'PDF',
                'header' => "{$filled} of {$total} rows",
                'source' => 'ai',
                'confidence' => match (true) {
                    $filled === $total => 'high',
                    $filled >= $total / 2 => 'medium',
                    default => 'low',
                },
            ];
        }

        return $columns;
    }

    /** Picks the event year out of the context string, for dates printed without one. */
    private function yearFrom(string $context): ?int
    {
        return preg_match('/\b(19|20)\d{2}\b/', $context, $matches) ? (int) $matches[0] : null;
    }

    /**
     * @return array{0: array<string, array<int, int>>, 1: int}
     */
    private function buildColumnMap(Worksheet $sheet, bool $allowAi, ?string $provider): array
    {
        $columnMap = [];
        $headerRowIndex = null;
        $aliasFailure = null;

        try {
            ['columnMap' => $columnMap, 'headerRowIndex' => $headerRowIndex] =
                $this->findHeaderRow($sheet, $this->requiredField(), $this->requiredFieldLabel());
        } catch (RuntimeException $e) {
            if (!$allowAi) {
                throw $e;
            }
            $aliasFailure = $e;
        }

        if (!$allowAi || ($headerRowIndex !== null && $this->unmappedFields($columnMap) === [])) {
            return [$columnMap, $headerRowIndex];
        }

        $suggestion = $this->askAgent($sheet, $provider);

        if ($suggestion === null) {
            if ($headerRowIndex === null) {
                throw $aliasFailure;
            }

            return [$columnMap, $headerRowIndex];
        }

        $this->report['used_ai'] = true;
        $this->report['ai_notes'] = $suggestion['notes'] ?? null;
        $this->aiColumnSources = [];

        // A header row we found ourselves is trusted over the model's guess.
        $headerRowIndex ??= (int) $suggestion['header_row'];

        // The alias pass above only reported a map when it also found the required
        // column. Now that a header row is known, re-run it there: every column the
        // alias list can place should still win over the model's opinion.
        if ($columnMap === []) {
            $columnMap = $this->aliasMapForRow($sheet, $headerRowIndex);
        }

        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $unmapped = $this->unmappedFields($columnMap);
        $taken = [];

        foreach ($suggestion['columns'] ?? [] as $entry) {
            $field = $entry['field'] ?? null;

            if ($field === null || $field === 'ignore' || !in_array($field, $unmapped, true) || isset($taken[$field])) {
                continue;
            }

            $column = $this->verifyColumn($sheet, $headerRowIndex, $entry, $highestColumn);
            if ($column === null) {
                continue;
            }

            $columnMap[$field][] = $column;
            $taken[$field] = true;
            $this->aiColumnSources[$field . ':' . $column] = $entry['confidence'] ?? null;
        }

        if ($headerRowIndex === null || !isset($columnMap[$this->requiredField()])) {
            throw $aliasFailure ?? new RuntimeException(
                'Could not find a "' . $this->requiredFieldLabel() . '" column in this file.'
            );
        }

        return [$columnMap, $headerRowIndex];
    }

    /**
     * @param array<string, array<int, int>> $columnMap
     * @return array<int, string>
     */
    private function unmappedFields(array $columnMap): array
    {
        return array_values(array_diff($this->mappableFields(), array_keys($columnMap)));
    }

    /**
     * Alias-matches one known header row, without the "must contain the required
     * field" check findHeaderRow() applies while it is still guessing which row
     * is the header.
     *
     * @return array<string, array<int, int>>
     */
    private function aliasMapForRow(Worksheet $sheet, int $row): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());

        $headerRow = [];
        for ($c = 1; $c <= $highestColumn; $c++) {
            $headerRow[$c] = $this->headerAt($sheet, $c, $row);
        }

        return $this->resolveColumnMap($headerRow);
    }

    /**
     * Checks a column number the agent returned against the header text it quoted
     * alongside it. Models reliably read the right header but occasionally miscount
     * the column it sits in - an off-by-one that would silently fill a whole field
     * from the wrong column. The quoted header wins: if it belongs to exactly one
     * other column, that column is used; if it matches nothing, the mapping is
     * dropped rather than trusted.
     *
     * @param array<string, mixed> $entry
     */
    private function verifyColumn(Worksheet $sheet, int $headerRowIndex, array $entry, int $highestColumn): ?int
    {
        $column = (int) ($entry['column'] ?? 0);
        $quoted = $this->normalizeHeader((string) ($entry['header'] ?? ''));

        if ($quoted === '') {
            return $column >= 1 && $column <= $highestColumn ? $column : null;
        }

        if ($column >= 1 && $column <= $highestColumn && $this->headerAt($sheet, $column, $headerRowIndex) === $quoted) {
            return $column;
        }

        $matches = [];
        for ($c = 1; $c <= $highestColumn; $c++) {
            if ($this->headerAt($sheet, $c, $headerRowIndex) === $quoted) {
                $matches[] = $c;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function headerAt(Worksheet $sheet, int $column, int $row): string
    {
        return $this->normalizeHeader((string) ($this->cellValue($sheet->getCell([$column, $row])) ?? ''));
    }

    /**
     * @return array{header_row: int, columns: array<int, array<string, mixed>>, notes: ?string}|null
     *     null when the agent is unavailable or errors - the caller falls back to alias-only matching.
     */
    private function askAgent(Worksheet $sheet, ?string $provider = null): ?array
    {
        try {
            $agent = new SheetColumnMappingAgent(
                $this->mappableFields(),
                $this->aiSubject(),
                $this->aiGuidance(),
            );

            $structured = $agent->prompt($this->previewGrid($sheet), provider: $provider)->structured;

            return isset($structured['header_row']) ? $structured : null;
        } catch (Throwable $e) {
            Log::warning(static::class . ': AI column mapping failed', ['message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Renders the top of the sheet as a compact, row/column-numbered text grid -
     * enough for the agent to read headers and a few sample values without
     * sending it the whole file.
     */
    private function previewGrid(Worksheet $sheet): string
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastRow = min(self::AI_PREVIEW_ROWS, $sheet->getHighestDataRow());

        $lines = [];
        for ($r = 1; $r <= $lastRow; $r++) {
            $cells = [];
            for ($c = 1; $c <= $highestColumn; $c++) {
                $value = $this->cellValue($sheet->getCell([$c, $r]));
                if ($value === null || $value === '') {
                    continue;
                }
                $cells[] = $c . '=' . mb_substr(str_replace(["\r", "\n"], ' ', (string) $value), 0, 40);
            }
            if ($cells !== []) {
                $lines[] = "row {$r}: " . implode(' | ', $cells);
            }
        }

        return "Map the columns of this sheet to the import fields.\n\n" . implode("\n", $lines);
    }

    /**
     * @param array<string, array<int, int>> $columnMap
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(Worksheet $sheet, array $columnMap, int $headerRowIndex): array
    {
        $concatField = $this->concatenatedField();
        $concatColumns = $concatField !== null ? ($columnMap[$concatField] ?? []) : [];
        if ($concatField !== null) {
            unset($columnMap[$concatField]);
        }

        $requiredColumn = $columnMap[$this->requiredField()][0];
        $highestRow = $sheet->getHighestDataRow();
        $rows = [];

        for ($r = $headerRowIndex + 1; $r <= $highestRow; $r++) {
            $required = $this->cellValue($sheet->getCell([$requiredColumn, $r]));
            if ($required === null || $required === '') {
                continue; // skip blank/spacer rows
            }

            $rowValues = [];
            foreach ($columnMap as $field => $candidateCols) {
                $rowValues[$field] = $this->firstNonBlank($sheet, $candidateCols, $r, $this->fieldKind($field));
            }

            if ($concatColumns !== []) {
                $parts = [];
                foreach ($concatColumns as $col) {
                    $text = $this->cellValue($sheet->getCell([$col, $r]));
                    if ($text !== null && $text !== '') {
                        $parts[] = $text;
                    }
                }
                if ($parts !== []) {
                    $rowValues[$concatField] = implode(' / ', array_unique($parts));
                }
            }

            $rows[] = $this->transformRow($rowValues);
        }

        return $rows;
    }

    /**
     * @param array<string, array<int, int>> $columnMap
     */
    private function buildReport(Worksheet $sheet, array $columnMap, int $headerRowIndex): void
    {
        $columns = [];

        foreach ($columnMap as $field => $cols) {
            foreach ($cols as $col) {
                $key = $field . ':' . $col;
                $isAi = array_key_exists($key, $this->aiColumnSources);

                $columns[] = [
                    'field' => $field,
                    'column' => Coordinate::stringFromColumnIndex($col),
                    'header' => (string) ($this->cellValue($sheet->getCell([$col, $headerRowIndex])) ?? ''),
                    'source' => $isAi ? 'ai' : 'alias',
                    'confidence' => $isAi ? $this->aiColumnSources[$key] : null,
                ];
            }
        }

        $this->report['header_row'] = $headerRowIndex;
        $this->report['columns'] = $columns;
    }
}
