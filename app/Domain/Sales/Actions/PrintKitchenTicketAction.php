<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Inventory\Support\KitchenPrinterConnectorFactory;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use Illuminate\Support\Collection;
use Mike42\Escpos\Printer;

/**
 * Prints a kitchen ticket: what to make, not what it costs. No prices,
 * totals, or payments -- kitchen staff don't need them, unlike the customer
 * receipt PrintReceiptAction builds.
 */
final class PrintKitchenTicketAction
{
    public function __construct(private readonly KitchenPrinterConnectorFactory $connectors) {}

    /**
     * @param  Collection<int, CartLine>  $lines
     */
    public function execute(Cart $cart, Collection $lines): void
    {
        $cart->loadMissing(['stockLocation', 'dinnerTable']);

        $printer = new Printer($this->connectors->resolve($cart->stockLocation));

        try {
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->setEmphasis(true);
            $printer->text(($cart->dinnerTable?->name ?? 'Order')."\n");
            $printer->setEmphasis(false);
            $printer->text(now()->format('Y-m-d H:i')."\n");
            $printer->feed();

            $printer->setJustification(Printer::JUSTIFY_LEFT);
            $printer->setEmphasis(true);
            foreach ($lines as $line) {
                $printer->text($this->trimZeros((string) $line->quantity).' x '.$line->item->name."\n");
                if (filled($line->description)) {
                    $printer->text('   '.$line->description."\n");
                }
            }
            $printer->setEmphasis(false);

            $printer->feed(2);
            $printer->cut();
        } finally {
            $printer->close();
        }
    }

    private function trimZeros(string $number): string
    {
        return rtrim(rtrim($number, '0'), '.');
    }
}
