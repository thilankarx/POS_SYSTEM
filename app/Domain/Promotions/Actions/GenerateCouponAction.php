<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Actions;

use App\Domain\Promotions\Exceptions\PromotionException;
use App\Domain\Promotions\Models\Coupon;
use App\Domain\Promotions\Models\Promotion;

final class GenerateCouponAction
{
    /**
     * @param  array{code: string, customer_id?: ?int, max_uses?: string, expires_at?: ?string}  $attributes
     */
    public function execute(Promotion $promotion, array $attributes): Coupon
    {
        if (Coupon::where('code', $attributes['code'])->exists()) {
            throw PromotionException::duplicateCouponCode($attributes['code']);
        }

        return $promotion->coupons()->create([
            'code' => $attributes['code'],
            'customer_id' => $attributes['customer_id'] ?? null,
            'max_uses' => $attributes['max_uses'] ?? 1,
            'expires_at' => $attributes['expires_at'] ?? null,
        ]);
    }
}
