<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Support;

use App\Settings\BusinessProfileSettings;
use App\Settings\LabelSettings;
use App\Support\Money\Money;
use Illuminate\Support\Str;

/**
 * Builds raw TSPL command jobs for Xprinter-class label printers (e.g. the
 * XP-T361U). TSPL is a plain-text command language, unrelated to the
 * ESC/POS commands `mike42/escpos-php`'s `Printer` class builds for the
 * receipt printer — only the connector transport (raw TCP/Windows/CUPS) is
 * shared between the two.
 */
class TsplLabelBuilder
{
    private const DOTS_PER_MM = 8;

    private const HORIZONTAL_MARGIN_DOTS = 8;

    private const TITLE_LEFT_CORRECTION_DOTS = 16;

    private const PRODUCT_NAME_LEFT_CORRECTION_DOTS = 12;

    public function __construct(
        private readonly LabelSettings $settings,
        private readonly BusinessProfileSettings $business,
    ) {}

    /** One label, ready to send to the printer as-is. */
    public function build(string $sku, string $price, string $barcode, string $name = ''): string
    {
        $width = $this->settings->width_mm;
        $height = $this->settings->height_mm;
        $gap = $this->settings->gap_mm;
        $density = $this->settings->density;

        $labelWidthDots = $width * self::DOTS_PER_MM;
        $labelHeightDots = $height * self::DOTS_PER_MM;
        $textBlockWidth = $labelWidthDots - (self::HORIZONTAL_MARGIN_DOTS * 2);

        // The printer's font 2 is wider than its documented 12-dot metric and
        // clips a long shop title on 35 mm stock. Font 1 at double height keeps
        // the title prominent while leaving reliable horizontal margins.
        $shopName = $this->truncate($this->escape($this->business->store_name), 8, $textBlockWidth);
        $nameLines = $this->wrapLines(
            $this->escape($name),
            12,
            $textBlockWidth,
            $labelHeightDots >= 200 ? 2 : 1,
        );
        $price = $this->truncate($this->escape(Money::currency().' '.$price), 16, $textBlockWidth);
        $barcode = $this->escape($barcode);

        // This printer renders built-in fonts 1 and 2 to the right of their
        // documented advance width. Correct those two display lines without
        // disturbing the already-centered barcode, SKU, or price.
        $shopNameX = $this->centeredTextX($shopName, 8, $labelWidthDots, -self::TITLE_LEFT_CORRECTION_DOTS);
        $priceX = $this->centeredTextX($price, 16, $labelWidthDots);
        [$barcodeX, $moduleWidth] = $this->centeredBarcode($barcode, $labelWidthDots);

        // Keep a physical top margin: this printer clips glyphs placed at
        // y=8 even though that coordinate is theoretically printable.
        $shopNameY = max(12, (int) round($labelHeightDots * 0.10));
        $priceY = $labelHeightDots - 34;
        $nameYPositions = count($nameLines) > 1
            ? [$shopNameY + 28, $shopNameY + 50]
            : [$shopNameY + 34];
        $lastNameY = $nameYPositions[count($nameLines) - 1] ?? ($shopNameY + 34);
        $barcodeY = $lastNameY + 28;
        $barcodeHeight = max(24, $priceY - $barcodeY - 22);

        $lines = [
            "SIZE {$width} mm,{$height} mm",
            "GAP {$gap} mm,0 mm",
            'DIRECTION 1',
            'REFERENCE 0,0',
            "DENSITY {$density}",
            'CLS',
        ];

        if ($shopName !== '') {
            $lines[] = "TEXT {$shopNameX},{$shopNameY},\"1\",0,1,2,\"{$shopName}\"";
        }

        foreach ($nameLines as $index => $nameLine) {
            $nameX = $this->centeredTextX($nameLine, 12, $labelWidthDots, -self::PRODUCT_NAME_LEFT_CORRECTION_DOTS);
            $lines[] = "TEXT {$nameX},{$nameYPositions[$index]},\"2\",0,1,1,\"{$nameLine}\"";
        }

        $lines[] = "BARCODE {$barcodeX},{$barcodeY},\"128\",{$barcodeHeight},2,0,{$moduleWidth},{$moduleWidth},\"{$barcode}\"";
        $lines[] = "TEXT {$priceX},{$priceY},\"3\",0,1,1,\"{$price}\"";
        $lines[] = 'PRINT 1,1';
        $lines[] = '';

        return implode("\r\n", $lines)."\r\n";
    }

    /** Job for N copies of the same label in one connector write. */
    public function buildMany(string $sku, string $price, string $barcode, int $quantity, string $name = ''): string
    {
        return str_repeat($this->build($sku, $price, $barcode, $name), max($quantity, 1));
    }

    /**
     * Built-in TSPL fonts are single-byte bitmap fonts, so a value carrying
     * multi-byte UTF-8 (accents, curly quotes, CJK, …) desyncs strlen()-based
     * width math from the glyphs actually drawn and can cut a multi-byte
     * sequence in half in truncate(), printing an off-center, right-clipped
     * title. Transliterating to ASCII first keeps one byte per printed glyph.
     */
    private function escape(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['\\"', '', ''], Str::ascii($value));
    }

    private function centeredTextX(string $value, int $fontWidth, int $labelWidth, int $offset = 0): int
    {
        $textWidth = strlen($value) * $fontWidth;

        return max(self::HORIZONTAL_MARGIN_DOTS, (int) round(($labelWidth - $textWidth) / 2) + $offset);
    }

    /** Cuts text down to whatever fits the label width at the given font width, so it never prints past the edge. */
    private function truncate(string $value, int $fontWidth, int $labelWidth): string
    {
        $maxChars = max(1, intdiv($labelWidth, $fontWidth));

        if (strlen($value) <= $maxChars) {
            return $value;
        }

        return $maxChars > 3
            ? rtrim(substr($value, 0, $maxChars - 3)).'...'
            : substr($value, 0, $maxChars);
    }

    /** @return list<string> */
    private function wrapLines(string $value, int $fontWidth, int $labelWidth, int $maximumLines): array
    {
        $value = trim($value);
        if ($value === '') {
            return [];
        }

        $maxChars = max(1, intdiv($labelWidth, $fontWidth));
        $wrapped = explode("\n", wordwrap($value, $maxChars, "\n", true));

        if (count($wrapped) <= $maximumLines) {
            return $wrapped;
        }

        $lines = array_slice($wrapped, 0, $maximumLines);
        $remaining = implode(' ', array_slice($wrapped, $maximumLines - 1));
        $lines[$maximumLines - 1] = $this->truncate($remaining, $fontWidth, $labelWidth);

        return $lines;
    }

    /** @return array{int, int} X coordinate and narrow/wide module width. */
    private function centeredBarcode(string $value, int $labelWidth): array
    {
        // Code 128 uses 11 modules per symbol plus a 13-module stop pattern.
        // The XP-365B automatically switches long digit strings to compact
        // Code Set C. An odd-length value uses one Code B digit, a switch
        // symbol, then pairs of digits in Code C.
        $length = strlen($value);
        $isNumeric = ctype_digit($value);
        $encodedDataSymbols = match (true) {
            $isNumeric && $length % 2 === 0 => $length / 2,
            $isNumeric && $length >= 5 => 2 + ($length - 1) / 2,
            default => $length,
        };
        $encodedModules = (int) (11 * ($encodedDataSymbols + 2) + 13);

        // Use the wider Code B estimate when choosing bar thickness so a
        // scanner value remains inside the label even if firmware encoding
        // heuristics differ for an odd or mixed value.
        $fitDataSymbols = $isNumeric && $length % 2 === 0 ? $length / 2 : $length;
        $fitModules = (int) (11 * ($fitDataSymbols + 2) + 13);
        $availableWidth = $labelWidth - 20;
        $moduleWidth = $fitModules * 2 <= $availableWidth ? 2 : 1;
        $barcodeWidth = $encodedModules * $moduleWidth;
        $centeredX = max(self::HORIZONTAL_MARGIN_DOTS, (int) round(($labelWidth - $barcodeWidth) / 2));
        $maximumX = max(self::HORIZONTAL_MARGIN_DOTS, $labelWidth - $barcodeWidth - self::HORIZONTAL_MARGIN_DOTS);

        return [min($centeredX, $maximumX), $moduleWidth];
    }
}
