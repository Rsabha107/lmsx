<?php

namespace App\Support\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use ZipArchive;

/**
 * Upper bounds for uploaded import sheets, so a small but crafted file cannot exhaust a web worker.
 * The archive is checked before PhpSpreadsheet opens it; the filter stops the parser keeping cells
 * past the limits and records that it had to.
 */
class ImportLimits implements IReadFilter
{
    public const MAX_ROWS = 20000;

    public const MAX_COLUMNS = 300;

    public const MAX_CELLS = 500000;

    public const MAX_SHEETS = 50;

    public const MAX_ZIP_ENTRIES = 1000;

    public const MAX_UNCOMPRESSED_BYTES = 40 * 1024 * 1024;

    public bool $exceeded = false;

    private int $cells = 0;

    /** @var array<string, int> */
    private array $columnIndex = [];

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        $column = $this->columnIndex[$columnAddress] ??= Coordinate::columnIndexFromString($columnAddress);

        if ($row > self::MAX_ROWS || $column > self::MAX_COLUMNS || ++$this->cells > self::MAX_CELLS) {
            $this->exceeded = true;

            return false;
        }

        return true;
    }

    public function assertWithinLimits(): void
    {
        if ($this->exceeded) {
            throw new RuntimeException(sprintf(
                'This file is too large to import (limit %s rows, %d columns, %s cells). Remove unused rows and columns, or split it.',
                number_format(self::MAX_ROWS), self::MAX_COLUMNS, number_format(self::MAX_CELLS),
            ));
        }
    }

    /** Rejects an XLSX whose archive would expand past the limits, before it is parsed. */
    public static function assertXlsxArchive(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('This file is not a readable .xlsx workbook.');
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new RuntimeException('This workbook has too many parts to import.');
            }

            $total = 0;
            $sheets = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $total += (int) ($stat['size'] ?? 0);
                $sheets += str_starts_with((string) ($stat['name'] ?? ''), 'xl/worksheets/sheet') ? 1 : 0;
            }

            if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException(sprintf('This workbook expands to more than %d MB, too large to import.', self::MAX_UNCOMPRESSED_BYTES / 1024 / 1024));
            }
            if ($sheets > self::MAX_SHEETS) {
                throw new RuntimeException(sprintf('This workbook has more than %d sheets, too many to import.', self::MAX_SHEETS));
            }
        } finally {
            $zip->close();
        }
    }
}
