<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Exceptions\KitchenPrintingException;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;

final class SendCartToKitchenAction
{
    public function __construct(private readonly PrintKitchenTicketAction $printer) {}

    /**
     * Marking a line "sent" -- and so visible on the kitchen display -- is
     * never conditional on a physical printer existing or being reachable.
     * The display and the printer are two independent outlets for the same
     * ticket, not one gating the other: a kitchen running the screen with
     * no printer at all must work exactly the same as one with both.
     * Printing is attempted only if a printer is actually configured, and a
     * failure there is reported back (so the cashier knows to walk it over)
     * without blocking the send.
     *
     * @return string|null a printer-failure message, or null if either
     *                     printing succeeded or no printer is configured
     */
    public function execute(Cart $cart): ?string
    {
        $cart->loadMissing(['lines.item', 'stockLocation']);

        $unsent = $cart->lines->whereNull('kitchen_sent_at');

        if ($unsent->isEmpty()) {
            throw CheckoutException::nothingToSendToKitchen();
        }

        $printError = null;

        if ($cart->stockLocation->hasKitchenPrinterConfigured()) {
            try {
                $this->printer->execute($cart, $unsent);
            } catch (KitchenPrintingException $e) {
                $printError = $e->getMessage();
            }
        }

        CartLine::whereIn('id', $unsent->pluck('id'))->update(['kitchen_sent_at' => now()]);

        return $printError;
    }
}
