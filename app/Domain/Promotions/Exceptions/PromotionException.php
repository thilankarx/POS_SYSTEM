<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Exceptions;

use RuntimeException;

final class PromotionException extends RuntimeException
{
    public static function emptyConditions(): self
    {
        return new self('A promotion needs at least one condition.');
    }

    public static function invalidRewardType(string $type): self
    {
        return new self("Unsupported reward type [{$type}].");
    }

    public static function duplicateCouponCode(string $code): self
    {
        return new self("Coupon code [{$code}] is already in use.");
    }

    public static function bogoRequiresQuantityCondition(): self
    {
        return new self('A bogo promotion needs a quantity condition (>= or =) to set the buy threshold.');
    }

    public static function freeItemRequiresRewardItem(): self
    {
        return new self('A free_item promotion needs a reward item.');
    }
}
