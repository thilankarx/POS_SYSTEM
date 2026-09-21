<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Models\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AttachCustomerRequest;
use App\Http\Resources\Api\V1\CartResource;
use Illuminate\Support\Facades\Gate;

class CartCustomerController extends Controller
{
    public function store(AttachCustomerRequest $request, Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $cart->update(['customer_id' => $request->validated('customer_id')]);

        return new CartResource($cart->load(['lines.item', 'payments.method', 'customer.person']));
    }

    public function destroy(Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $cart->update(['customer_id' => null]);

        return new CartResource($cart->load(['lines.item', 'payments.method']));
    }
}
