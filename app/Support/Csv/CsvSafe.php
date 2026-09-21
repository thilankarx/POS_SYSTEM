<?php

declare(strict_types=1);

namespace App\Support\Csv;

/**
 * Guards a CSV export against formula injection: a cell whose text begins
 * with `=`, `+`, `-`, `@`, a tab, or a carriage return is interpreted as a
 * formula by Excel/Sheets/LibreOffice when the file is opened, not as
 * literal text. An item or customer name is free-text entered by a store
 * user -- nothing stops it from being `=cmd|'/c calc'!A1` or a HYPERLINK()
 * exfiltration payload, and every report exporter in this codebase writes
 * that text straight into a cell with no such guard.
 *
 * Prefixing a leading single quote is the standard mitigation: spreadsheet
 * applications render it as plain text (the quote itself is not shown)
 * instead of evaluating the cell as a formula.
 */
final class CsvSafe
{
    /**
     * @param  list<mixed>  $row
     * @return list<mixed>
     */
    public static function row(array $row): array
    {
        return array_map(self::cell(...), $row);
    }

    public static function cell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$value
            : $value;
    }
}
