<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use RuntimeException;

final class InventoryException extends RuntimeException
{
    public static function itemDoesNotTrackStock(): self
    {
        return new self('This item does not track stock.');
    }

    public static function zeroAdjustment(): self
    {
        return new self('Select a quantity to increase or decrease.');
    }

    public static function sameLocation(): self
    {
        return new self('The source and destination locations must be different.');
    }

    public static function zeroQuantity(): self
    {
        return new self('Enter a quantity to transfer.');
    }

    public static function noteRequired(): self
    {
        return new self('A note is required to adjust stock.');
    }

    public static function insufficientStockToTransfer(string $available): self
    {
        return new self("Insufficient stock at the source location; {$available} available.");
    }
}
