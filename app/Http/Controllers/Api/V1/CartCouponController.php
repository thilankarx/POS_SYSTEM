<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Promotions\Models\Coupon;
use App\Domain\Sales\Exceptions\CheckoutException;
use App\Domain\Sales\Models\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApplyCouponRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Support\Facades\Gate;

class CartCouponController extends Controller
{
    public function store(ApplyCouponRequest $request, Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $code = $request->validated('code');
        $coupon = Coupon::with('promotion')->where('code', $code)->first();

        if ($coupon === null
            || ! $coupon->promotion->isCurrentlyActive(now())
            || ! $coupon->isRedeemable($cart->customer_id, now())
        ) {
            throw CheckoutException::couponInvalid();
        }

        $cart->update(['coupon_code' => $code]);

        return new CartResource($cart->load(['lines.item', 'payments.method']));
    }

    public function destroy(Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $cart->update(['coupon_code' => null]);

        return new CartResource($cart->load(['lines.item', 'payments.method']));
    }
}
