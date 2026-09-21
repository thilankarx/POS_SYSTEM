<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Exceptions;

use RuntimeException;

final class PurchasingException extends RuntimeException
{
    public static function notDraft(): self
    {
        return new self('This purchase order is not a draft and cannot be edited.');
    }

    public static function notSubmitted(): self
    {
        return new self('This purchase order has not been submitted for approval.');
    }

    public static function notCancellable(): self
    {
        return new self('Only a draft or submitted purchase order can be cancelled.');
    }

    public static function alreadyClosed(): self
    {
        return new self('This purchase order is cancelled and cannot be received against.');
    }

    public static function lotNumberRequired(string $itemName): self
    {
        return new self("[{$itemName}] must have a lot number -- every stocked item is lot-tracked now.");
    }

    public static function duplicateLotNumber(string $itemName, string $lotNumber): self
    {
        return new self("Lot number [{$lotNumber}] already exists for [{$itemName}]. Enter a unique lot number.");
    }

    public static function expiryDateRequired(string $itemName): self
    {
        return new self("[{$itemName}] requires an expiry date.");
    }

    public static function emptyLines(): self
    {
        return new self('At least one line is required.');
    }

    public static function invoiceDisputed(): self
    {
        return new self('This invoice is disputed and cannot be paid until the discrepancy is resolved.');
    }

    public static function notDisputed(): self
    {
        return new self('This invoice is not disputed.');
    }

    public static function paymentExceedsInvoiceTotal(): self
    {
        return new self('This payment would exceed the invoice total.');
    }
}
