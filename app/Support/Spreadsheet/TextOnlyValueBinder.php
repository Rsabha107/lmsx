<?php

namespace App\Support\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Text that looks like a formula ("=HYPERLINK(...)") is stored as text, never as a formula, so a
 * team, location or note typed by a user cannot run when someone opens an export. Numbers, dates
 * and booleans bind as before. Registered for every workbook in AppServiceProvider.
 */
class TextOnlyValueBinder extends DefaultValueBinder
{
    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && str_starts_with(ltrim($value), '=')) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /** For CSV, where any of = + - @ (or a tab / CR) at the start can be read as a formula. */
    public static function csvCell(mixed $value): string
    {
        $text = (string) $value;
        if ($text !== '' && ! is_numeric($text) && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $text = "'".$text;
        }

        return preg_match('/[",\r\n]/', $text) ? '"'.str_replace('"', '""', $text).'"' : $text;
    }
}
