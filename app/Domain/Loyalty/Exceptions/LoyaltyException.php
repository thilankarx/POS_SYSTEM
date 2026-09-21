<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Exceptions;

use RuntimeException;

final class LoyaltyException extends RuntimeException
{
    public static function noLoyaltyPackage(): self
    {
        return new self('This customer has no active loyalty package to redeem points against.');
    }

    public static function insufficientPoints(): self
    {
        return new self('This customer does not have enough points for that redemption.');
    }

    public static function duplicatePackageName(string $name): self
    {
        return new self("A loyalty package named [{$name}] already exists.");
    }
}
