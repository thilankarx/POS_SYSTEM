<?php

declare(strict_types=1);

namespace App\Domain\Sales\Exceptions;

use RuntimeException;

final class ShiftException extends RuntimeException
{
    public static function alreadyOpen(): self
    {
        return new self('This terminal already has an open shift.');
    }

    public static function terminalInactive(): self
    {
        return new self('This terminal is not active.');
    }

    public static function notOpen(): self
    {
        return new self('This shift is not open.');
    }

    public static function unauthorizedTerminal(): self
    {
        return new self('You are not authorized to operate at this terminal.');
    }
}
