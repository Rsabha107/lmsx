<?php

namespace App\Services;

use App\Ai\Agents\SheetColumnMappingAgent;
use App\Http\Controllers\Concerns\ReadsSpreadsheetRows;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
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
 * Subclasses supply the alias list, the field set, and any domain-specific
 * post-processing of a parsed row.
 */
abstract class AbstractSheetReader
{
    use ReadsSpreadsheetRows;

    /** Rows of the source sheet shown to the mapping agent (headers plus samples). */
    private const AI_PREVIEW_ROWS = 14;

    /**
     * How the columns of the last-read file were matched, for showing a human
     * what the conversion is about to do.
     *
     * @var array{header_row: int, columns: array<int, array{field: string, column: string, header: string, source: string, confidence: ?string}>, ai_notes: ?string, used_ai: bool}
     */
    private array $report = ['header_row' => 0, 'columns' => [], 'ai_notes' => null, 'used_ai' => false];

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
     * @param bool $allowAi ask the mapping agent about columns the alias list can't place
     * @param string|null $provider AI provider for the mapping agent; null uses the app default
     * @return array<int, array<string, mixed>> rows keyed by import field name
     */
    public function read(UploadedFile|string $file, bool $allowAi = false, ?string $provider = null): array
    {
        $sheet = $this->loadSheet($file);

        [$columnMap, $headerRowIndex] = $this->buildColumnMap($sheet, $allowAi, $provider);

        $this->buildReport($sheet, $columnMap, $headerRowIndex);

        return $this->extractRows($sheet, $columnMap, $headerRowIndex);
    }

    /**
     * @return array{header_row: int, columns: array<int, array{field: string, column: string, header: string, source: string, confidence: ?string}>, ai_notes: ?string, used_ai: bool}
     */
    public function report(): array
    {
        return $this->report;
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
