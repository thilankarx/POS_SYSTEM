<?php

declare(strict_types=1);

namespace App\Support\Idempotency;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public static function mismatchedRequest(): self
    {
        return new self('This Idempotency-Key was already used for a different request.');
    }
}
