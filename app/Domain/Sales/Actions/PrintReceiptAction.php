<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Support\PrintConnectorFactory;
use App\Settings\BusinessProfileSettings;
use Brick\Money\Money;
use Illuminate\Support\Facades\Storage;
use Mike42\Escpos\EscposImage;
use Mike42\Escpos\Printer;
use Throwable;

final class PrintReceiptAction
{
    public function __construct(
        private readonly PrintConnectorFactory $connectors,
        private readonly BusinessProfileSettings $business,
    ) {}

    public function execute(Sale $sale): void
    {
        $sale->loadMissing(['lines', 'taxes', 'payments.method', 'customer.person', 'user.person', 'terminal']);

        $terminal = $sale->terminal;
        $printer = new Printer($this->connectors->resolve($terminal));
        $width = $this->charsPerLine($terminal->printer_paper_width);
        $printWidthDots = $terminal->printer_paper_width === 58 ? 384 : 576;
        $separator = str_repeat('-', $width);
        $currency = strtoupper($sale->currency);
        $itemColumns = $this->itemColumnWidths($width);

        try {
            // An 80 mm XP-80 has a 72 mm (576-dot) printable area. Explicitly
            // reset the raw ESC/POS print area so a previous Windows job cannot
            // leave margins or a narrower width active on the printer.
            $printer->setPrintLeftMargin(0);
            $printer->setPrintWidth($printWidthDots);
            // Pull the new leading edge back after the previous cut, reducing
            // the printer's unavoidable blank top-of-receipt paper.
            $printer->feedReverse(2);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $this->printLogo($printer, $printWidthDots);
            $printer->setEmphasis(true);
            $printer->setTextSize(2, 2);
            $printer->text($this->wrapped($this->business->store_name, intdiv($width, 2))."\n");
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            // Double-height text needs a clear line before returning to the
            // normal-height address block on common XP-80 firmware.
            $printer->feed();
            if ($this->business->address !== '') {
                $printer->text($this->wrapped($this->business->address, $width)."\n");
            }
            if ($this->business->phone !== '') {
                $printer->text($this->wrapped($this->business->phone, $width)."\n");
            }
            if ($this->business->receipt_header !== '') {
                $printer->text($this->wrapped($this->business->receipt_header, $width)."\n");
            }

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->text($separator."\n");
            $printer->text($this->row('RECEIPT', $sale->number, $width)."\n");
            $printer->text($this->row('CASHIER', strtoupper($sale->user?->name ?? ''), $width)."\n");
            $printer->text($this->row('TERMINAL', $terminal->code, $width)."\n");
            $soldAt = $sale->sold_at?->copy()->timezone(config('pos.timezone'));
            $printer->text($this->row('DATE', $soldAt?->format('Y-m-d H:i') ?? '', $width)."\n");
            $printer->text($this->row('CURRENCY', $currency, $width)."\n");
            $printer->text($separator."\n");
            $printer->setEmphasis(true);
            $printer->text($this->columns(
                ['PRODUCT', 'PRICE', 'QTY', 'AMOUNT'],
                $itemColumns,
                [false, true, true, true],
            )."\n");
            $printer->setEmphasis(false);
            $printer->text($separator."\n");

            foreach ($sale->lines as $line) {
                $printer->text($this->wrapped($line->item_name, $width)."\n");
                $printer->text($this->columns([
                    $line->sku,
                    (string) $line->unit_price->getAmount(),
                    $this->trimZeros((string) $line->quantity),
                    (string) $line->line_total->getAmount(),
                ], $itemColumns, [false, true, true, true])."\n");
            }
            $printer->text($separator."\n");

            $printer->text($this->row('SUB TOTAL', $this->money($sale->subtotal, $currency), $width)."\n");
            $printer->text($this->row('DISCOUNT', $this->money($sale->discount_total, $currency, true), $width)."\n");
            $printer->text($this->row('TAX', $this->money($sale->tax_total, $currency), $width)."\n");
            if (! $sale->rounding_adjustment->isZero()) {
                $printer->text($this->row('ROUNDING', $this->money($sale->rounding_adjustment, $currency), $width)."\n");
            }
            $printer->setEmphasis(true);
            $printer->text($this->row('SALE TOTAL', $this->money($sale->total, $currency), $width)."\n");
            $printer->setEmphasis(false);
            if (! $sale->tip_amount->isZero()) {
                $printer->text($this->row('TIP', $this->money($sale->tip_amount, $currency), $width)."\n");
                $printer->setEmphasis(true);
                $printer->text($this->row('GRAND TOTAL', $this->money($sale->total->plus($sale->tip_amount), $currency), $width)."\n");
                $printer->setEmphasis(false);
            }

            $printer->text($separator."\n");
            foreach ($sale->payments as $payment) {
                $showsTendered = $payment->method?->allows_change && $payment->tendered !== null;
                $label = $showsTendered ? strtoupper(($payment->method?->name ?? 'Cash').' TENDERED') : strtoupper($payment->method?->name ?? 'PAYMENT');
                $amount = $showsTendered ? $payment->tendered : $payment->amount;
                $printer->text($this->row($label, $this->money($amount, $currency), $width)."\n");

                // The card terminal's approval/slip number, entered at
                // tender time, is kept on the payment's reference -- print
                // it under the line so the receipt matches the card slip.
                if ($payment->reference !== null && $payment->reference !== '') {
                    $printer->text($this->row('  REF', $payment->reference, $width)."\n");
                }
            }
            if ($sale->payments->isNotEmpty()) {
                $printer->setEmphasis(true);
                $printer->text($this->row('CHANGE DUE', $this->money($sale->change_given, $currency), $width)."\n");
                $printer->setEmphasis(false);
            }

            $pieceCount = $sale->lines->reduce(
                fn (string $total, $line): string => bcadd($total, (string) $line->quantity, 3),
                '0',
            );
            $printer->text($separator."\n");
            $printer->text($this->columns([
                'NO OF ITEMS: '.$sale->lines->count(),
                'NO OF PCS: '.$this->trimZeros($pieceCount),
            ], [intdiv($width, 2), $width - intdiv($width, 2)])."\n");

            if ($sale->customer !== null) {
                $customerName = $sale->customer->company_name ?: $sale->customer->person?->full_name;
                $printer->setJustification(Printer::JUSTIFY_CENTER);
                $printer->setEmphasis(true);
                $printer->text("CUSTOMER DETAILS\n");
                $printer->setEmphasis(false);
                $printer->setJustification(Printer::JUSTIFY_LEFT);
                $printer->text($this->row('NAME', (string) $customerName, $width)."\n");
                $printer->text($this->row('BALANCE POINTS', $this->trimZeros((string) $sale->customer->points_balance), $width)."\n");
            }

            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setBarcodeHeight(50);
            $printer->setBarcodeWidth(2);
            $printer->barcode('{B'.$sale->number, Printer::BARCODE_CODE128);
            $printer->feed();
            $printer->text($sale->number."\n");
            if ($this->business->receipt_footer !== '') {
                $printer->text($this->wrapped($this->business->receipt_footer, $width)."\n");
            }

            $printer->feed();
            $printer->setEmphasis(true);
            $printer->text("AMT Solutions (Pvt) Ltd\n");
            $printer->setEmphasis(false);
            // Font B keeps both contact details on one line on 58 mm and
            // 80 mm receipt paper.
            $printer->setFont(Printer::FONT_B);
            $printer->text("www.amtsolutions.lk  +94 77 341 1861\n");
            $printer->setFont(Printer::FONT_A);

            if ($sale->payments->contains(fn ($payment) => (bool) $payment->method?->opens_drawer)) {
                $printer->pulse();
            }

            // Keep a small clearance between the footer and the cutter.
            $printer->feed();
            $printer->cut(Printer::CUT_FULL, 1);
        } finally {
            $printer->close();
        }
    }

    private function row(string $label, string $value, int $width): string
    {
        $label = mb_substr($label, 0, max($width - mb_strlen($value) - 1, 1));
        $padding = max($width - mb_strlen($label) - mb_strlen($value), 1);

        return $label.str_repeat(' ', $padding).$value;
    }

    private function trimZeros(string $number): string
    {
        return rtrim(rtrim($number, '0'), '.');
    }

    private function charsPerLine(?int $paperWidthMm): int
    {
        return $paperWidthMm === 58 ? 32 : 48;
    }

    private function wrapped(string $text, int $width): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return wordwrap($text, $width, "\n", true);
    }

    private function money(Money $money, string $currency, bool $negative = false): string
    {
        $sign = $negative && ! $money->isZero() ? '-' : '';

        return $currency.' '.$sign.$money->getAmount();
    }

    /** @return list<int> */
    private function itemColumnWidths(int $width): array
    {
        return $width === 32 ? [12, 7, 5, 8] : [20, 9, 6, 13];
    }

    /**
     * @param  list<string>  $values
     * @param  list<int>  $widths
     * @param  list<bool>  $rightAligned
     */
    private function columns(array $values, array $widths, array $rightAligned = []): string
    {
        $columns = [];
        foreach ($values as $index => $value) {
            $columnWidth = $widths[$index];
            $value = mb_substr($value, 0, $columnWidth);
            $columns[] = str_pad($value, $columnWidth, ' ', ($rightAligned[$index] ?? false) ? STR_PAD_LEFT : STR_PAD_RIGHT);
        }

        return implode('', $columns);
    }

    private function printLogo(Printer $printer, int $printWidthDots): void
    {
        if ($this->business->logo_path === null || $this->business->logo_path === '') {
            return;
        }

        try {
            $path = Storage::disk('public')->path($this->business->logo_path);
            $logo = EscposImage::load($path, preferred: ['gd', 'native']);

            // The admin upload rule keeps logos inside the paper's printable
            // width. This guard also protects older uploads made before that
            // validation existed from spilling beyond the receipt edge.
            if ($logo->getWidth() > $printWidthDots) {
                return;
            }

            // XP-80 firmware variants commonly reject the newer GS ( L
            // graphics command and print its binary payload as garbage text.
            // GS v 0 raster bit-image mode is the compatible alternative.
            $printer->bitImage($logo);
        } catch (Throwable $exception) {
            // A missing/corrupt logo must never prevent a sale receipt from
            // printing. The text business details remain available below it.
            report($exception);
        }
    }
}
