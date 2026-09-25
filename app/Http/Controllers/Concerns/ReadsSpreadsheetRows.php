<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared spreadsheet-import machinery for import controllers: header-name-based
 * column matching (tolerant of a merged/super-header row above the real one,
 * duplicate/ambiguous headers, Excel formula-error values, and date/time
 * serials that lost their number formatting), plus a matching styled xlsx
 * template generator.
 *
 * Using classes must define:
 *   protected const HEADER_ALIASES  array<string, string|array<string>>
 *   protected const DATE_FIELDS     array<string>
 *   protected const TIME_FIELDS     array<string>
 */
trait ReadsSpreadsheetRows
{
    /** Excel's own error-value strings - never usable data, always treated as blank. */
    private const EXCEL_ERROR_VALUES = ['#VALUE!', '#REF!', '#NAME?', '#N/A', '#DIV/0!', '#NULL!', '#NUM!'];

    /**
     * @param UploadedFile|string $file an uploaded file, or an absolute path to a local file
     */
    protected function loadSheet(UploadedFile|string $file): Worksheet
    {
        if (is_string($file)) {
            $path = $file;
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        } else {
            $path = $file->getRealPath();
            $extension = strtolower($file->getClientOriginalExtension());
        }

        $readerType = match ($extension) {
            'csv', 'txt' => 'Csv',
            'xls' => 'Xls',
            default => 'Xlsx',
        };

        /** @var IReader $reader */
        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);

        return $reader->load($path)->getActiveSheet();
    }

    /**
     * Scans the first several rows for one that contains the required field
     * (some sheets have a merged "group" header row above the real one) and
     * returns the resolved column map for that row.
     *
     * @return array{columnMap: array<string, array<int, int>>, headerRowIndex: int}
     */
    protected function findHeaderRow(Worksheet $sheet, string $requiredField, string $requiredFieldLabel, int $maxScanRows = 10): array
    {
        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $highestRow = $sheet->getHighestDataRow();

        for ($candidateRow = 1; $candidateRow <= min($maxScanRows, $highestRow); $candidateRow++) {
            $headerRow = [];
            for ($c = 1; $c <= $highestColumn; $c++) {
                $headerRow[$c] = $this->normalizeHeader((string) ($this->cellValue($sheet->getCell([$c, $candidateRow])) ?? ''));
            }

            $columnMap = $this->resolveColumnMap($headerRow);

            if (isset($columnMap[$requiredField])) {
                return ['columnMap' => $columnMap, 'headerRowIndex' => $candidateRow];
            }
        }

        throw new RuntimeException("Could not find a \"{$requiredFieldLabel}\" column in this file's first {$maxScanRows} rows.");
    }

    /**
     * Maps normalized header text (by column index) to field keys, resolving
     * ambiguous/repeated headers (e.g. a header reused once per leg/side) by
     * left-to-right occurrence order.
     *
     * @param array<int, string> $headerRow normalized header text keyed by 1-based column index
     * @return array<string, array<int, int>>
     */
    protected function resolveColumnMap(array $headerRow): array
    {
        $columnMap = [];
        $occurrence = [];

        foreach ($headerRow as $col => $norm) {
            if ($norm === '' || !isset(static::HEADER_ALIASES[$norm])) {
                continue;
            }

            $candidates = static::HEADER_ALIASES[$norm];
            $candidates = is_array($candidates) ? $candidates : [$candidates];

            if (count($candidates) > 1) {
                $occurrence[$norm] = ($occurrence[$norm] ?? -1) + 1;
                $field = $candidates[min($occurrence[$norm], count($candidates) - 1)];
            } else {
                $field = $candidates[0];
            }

            $columnMap[$field][] = $col;
        }

        return $columnMap;
    }

    /**
     * Reads the first non-blank value across a set of candidate columns for one
     * row - used for fields that may be sourced from more than one column.
     *
     * @param array<int, int> $columns
     */
    protected function firstNonBlank(Worksheet $sheet, array $columns, int $row, ?string $kind): mixed
    {
        foreach ($columns as $col) {
            $value = $this->cellValue($sheet->getCell([$col, $row]), $kind);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function fieldKind(string $field): ?string
    {
        if (in_array($field, static::DATE_FIELDS, true)) {
            return 'date';
        }
        if (in_array($field, static::TIME_FIELDS, true)) {
            return 'time';
        }

        return null;
    }

    protected function normalizeHeader(string $header): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $header) ?? '');
    }

    /**
     * @param string|null $kind 'date' or 'time' when this cell is known to hold one -
     *                          lets us convert a raw Excel serial number straight to a
     *                          date/time even when the cell's own number format doesn't
     *                          say it's a date (common after copying data into a new
     *                          sheet without carrying over the original formatting).
     *
     * Deliberately never triggers PhpSpreadsheet's live formula calculation engine
     * (getCalculatedValue()/getFormattedValue()/isDateTime($cell) without an explicit
     * value all do). Some formulas in real-world exports are either unsupported (an
     * uncaught error deep in the engine) or pathologically slow/circular (which isn't
     * an exception at all - it just never returns, hanging the whole import). A
     * formula cell's own last-cached value is used instead - it's what a human
     * opening the file in Excel would see anyway.
     */
    protected function cellValue(Cell $cell, ?string $kind = null): mixed
    {
        try {
            $raw = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();

            if ($kind !== null && is_numeric($raw)) {
                return $this->formatExcelSerial((float) $raw, $kind);
            }

            if (is_numeric($raw) && ExcelDate::isDateTime($cell, $raw)) {
                $isDateOnly = fmod((float) $raw, 1.0) === 0.0;

                return $this->formatExcelSerial((float) $raw, $isDateOnly ? 'date' : 'time');
            }

            $value = $raw;
        } catch (\Throwable $e) {
            $value = null;
        }

        if (is_string($value)) {
            $value = trim($value);

            // A formula whose cached value is an Excel error (e.g. a broken lookup) -
            // there's no usable data here, treat it the same as a blank cell.
            return in_array(strtoupper($value), self::EXCEL_ERROR_VALUES, true) ? null : $value;
        }

        return is_scalar($value) ? $value : null;
    }

    /**
     * Converts an Excel date/time serial to a Y-m-d or H:i string, rounding to the
     * nearest minute - serials are often stored with only a few decimal places of
     * precision (e.g. 0.753472 for 18:05), which truncates a second or two short
     * of the intended minute if not rounded.
     */
    protected function formatExcelSerial(float $serial, string $kind): string
    {
        $dt = ExcelDate::excelToDateTimeObject($serial);
        $dt->setTimestamp((int) round($dt->getTimestamp() / 60) * 60);

        return $kind === 'date' ? $dt->format('Y-m-d') : $dt->format('H:i');
    }

    /**
     * Builds a downloadable xlsx template: a styled header row, one example row,
     * and a second "Instructions" sheet describing each column.
     *
     * @param array<int, string> $headers
     * @param array<int, mixed> $exampleRow
     * @param array<int, array{0: string, 1: string}> $instructionRows
     */
    protected function buildTemplateResponse(
        string $sheetTitle,
        array $headers,
        array $exampleRow,
        array $instructionRows,
        string $downloadFilename,
    ): StreamedResponse {
        $spreadsheet = $this->buildTemplateSpreadsheet($sheetTitle, $headers, [$exampleRow], $instructionRows);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $downloadFilename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Builds the template workbook itself: a styled header row, the given data rows,
     * and an optional second "Instructions" sheet describing each column.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $dataRows
     * @param array<int, array{0: string, 1: string}> $instructionRows
     */
    protected function buildTemplateSpreadsheet(
        string $sheetTitle,
        array $headers,
        array $dataRows,
        array $instructionRows = [],
    ): Spreadsheet {
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($sheetTitle);
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            // SC Brand Manual, Pantone 1955 C.
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '8A1538']],
        ]);

        if ($dataRows !== []) {
            $sheet->fromArray($dataRows, null, 'A2');
        }

        foreach (range(1, count($headers)) as $columnIndex) {
            $sheet->getColumnDimensionByColumn($columnIndex)->setAutoSize(true);
        }

        if ($instructionRows !== []) {
            $instructions = $spreadsheet->createSheet();
            $instructions->setTitle('Instructions');
            $instructions->fromArray($instructionRows, null, 'A1');
            $instructions->getStyle('A1:B1')->getFont()->setBold(true);
            $instructions->getColumnDimension('A')->setWidth(26);
            $instructions->getColumnDimension('B')->setWidth(100);
            foreach ($instructions->getRowIterator() as $row) {
                $instructions->getStyle("B{$row->getRowIndex()}")->getAlignment()->setWrapText(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
