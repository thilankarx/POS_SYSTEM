<?php

declare(strict_types=1);

namespace App\Domain\Giftcards\Exceptions;

use RuntimeException;

final class GiftcardException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('No gift card matches that number.');
    }

    public static function inactive(): self
    {
        return new self('This gift card is not active.');
    }

    public static function expired(): self
    {
        return new self('This gift card has expired.');
    }

    public static function insufficientBalance(): self
    {
        return new self('This gift card does not have enough balance for that amount.');
    }

    public static function cannotDeleteUsed(): self
    {
        return new self('This gift card has already been used and cannot be deleted.');
    }
}
