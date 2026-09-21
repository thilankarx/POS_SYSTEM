<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

final class KitchenPrintingException extends RuntimeException
{
    public static function printerNotConfigured(): self
    {
        return new self('This location has no kitchen printer configured.');
    }

    public static function connectionFailed(string $reason): self
    {
        return new self("Could not connect to the kitchen printer: {$reason}");
    }
}
