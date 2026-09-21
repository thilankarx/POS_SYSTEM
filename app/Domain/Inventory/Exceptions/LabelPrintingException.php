<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use RuntimeException;

final class LabelPrintingException extends RuntimeException
{
    public static function printerNotConfigured(): self
    {
        return new self('This stock location has no label printer configured.');
    }

    public static function connectionFailed(string $reason): self
    {
        return new self("Could not connect to the label printer: {$reason}");
    }

    public static function lotHasNoPrice(string $itemName): self
    {
        return new self("[{$itemName}] has no selling price set on the received lot -- price it before printing labels.");
    }
}
