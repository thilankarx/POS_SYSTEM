<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

final class PrintingException extends RuntimeException
{
    public static function printerNotConfigured(): self
    {
        return new self('This terminal has no receipt printer configured.');
    }

    public static function connectionFailed(string $reason): self
    {
        return new self("Could not connect to the receipt printer: {$reason}");
    }
}
