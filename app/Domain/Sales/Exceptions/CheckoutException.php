<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

final class CheckoutException extends RuntimeException
{
    public static function emptyCart(): self
    {
        return new self('Cannot complete a sale with no lines.');
    }

    public static function itemNotSoldHere(string $itemName): self
    {
        return new self("[{$itemName}] is not sold by this business type.");
    }

    public static function lotDoesNotMatchItem(string $itemName): self
    {
        return new self("The selected lot does not belong to [{$itemName}].");
    }

    public static function itemHasNoPricedStock(string $itemName): self
    {
        return new self("[{$itemName}] has no priced stock on hand yet -- receive it before selling it.");
    }

    public static function cartNotActive(string $status): self
    {
        return new self("Cart is [{$status}] and cannot be completed.");
    }

    public static function underpaid(string $due): self
    {
        return new self("Payments do not cover the total; {$due} still due.");
    }

    public static function shiftClosed(): self
    {
        return new self('The shift for this terminal is not open.');
    }

    public static function negativeTotal(): self
    {
        return new self('This sale totals below zero. Check line discounts before completing.');
    }

    public static function tableOccupied(string $name): self
    {
        return new self("Table [{$name}] already has an order open against it.");
    }

    public static function nothingToSendToKitchen(): self
    {
        return new self('Every item on this cart has already been sent to the kitchen.');
    }

    public static function cannotIncreasePreparedLineQuantity(): self
    {
        return new self('This item was already prepared by the kitchen. Add it again instead of increasing the quantity here.');
    }

    public static function insufficientStock(string $item, string $available): self
    {
        return new self("Insufficient stock for [{$item}]; {$available} available.");
    }

    public static function serialRequired(string $itemName): self
    {
        return new self("A serial number is required for [{$itemName}] before this sale can complete.");
    }

    public static function serialNotAvailable(string $serial): self
    {
        return new self("Serial [{$serial}] is not available to sell.");
    }

    public static function paymentMethodInactive(): self
    {
        return new self('This payment method is not active.');
    }

    public static function referenceRequired(): self
    {
        return new self('A reference is required for this payment method.');
    }

    public static function promotionExhausted(string $name): self
    {
        return new self("The promotion [{$name}] is no longer available.");
    }

    public static function couponInvalid(): self
    {
        return new self('This coupon code is invalid, expired, or no longer available.');
    }

    public static function saleNotRefundable(string $status): self
    {
        return new self("A sale that is [{$status}] cannot be refunded.");
    }

    public static function returnExceedsRemaining(string $item): self
    {
        return new self("Cannot return more of [{$item}] than remains on the sale.");
    }

    public static function noReturnLinesSelected(): self
    {
        return new self('Select a quantity to return for at least one line.');
    }

    public static function refundExceedsSaleTotal(): self
    {
        return new self('This refund would exceed what remains refundable on the sale.');
    }

    public static function saleNotVoidable(string $status): self
    {
        return new self("A sale that is [{$status}] cannot be voided.");
    }

    public static function voidReasonRequired(): self
    {
        return new self('A reason is required to void a sale.');
    }
}
