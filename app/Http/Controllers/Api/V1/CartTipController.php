<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Models\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SetCartTipRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Support\Facades\Gate;

class CartTipController extends Controller
{
    public function store(SetCartTipRequest $request, Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $cart->update(['tip_amount' => $request->validated('tip_amount')]);

        return new CartResource($cart->load(['lines.item', 'payments.method']));
    }
}
