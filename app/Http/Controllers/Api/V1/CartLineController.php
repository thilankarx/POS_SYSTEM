<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Item;
use App\Domain\Sales\Actions\AddCartLineAction;
use App\Domain\Sales\Actions\UpdateCartLineAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddCartLineRequest;
use App\Http\Requests\Api\V1\UpdateCartLineRequest;
use App\Http\Resources\Api\V1\CartLineResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CartLineController extends Controller
{
    public function store(AddCartLineRequest $request, Cart $cart, AddCartLineAction $action): CartLineResource
    {
        Gate::authorize('view', $cart);

        $validated = $request->validated();
        $item = Item::findOrFail($validated['item_id']);

        $line = $action->execute(
            cart: $cart,
            item: $item,
            quantity: (string) $validated['quantity'],
            stockLotId: $validated['stock_lot_id'] ?? null,
            description: $validated['description'] ?? null,
        );

        return new CartLineResource($line->load('item'));
    }

    public function update(UpdateCartLineRequest $request, Cart $cart, CartLine $line, UpdateCartLineAction $action): CartLineResource
    {
        Gate::authorize('view', $cart);
        abort_unless($line->cart_id === $cart->id, 404);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $validated = $request->validated();

        if (array_intersect(['unit_price', 'discount_value', 'discount_type'], array_keys($validated)) !== []) {
            Gate::authorize('sales.change_price');
        }

        $line = $action->execute($line, $validated, $request->user());

        return new CartLineResource($line->load('item'));
    }

    public function destroy(Cart $cart, CartLine $line): Response
    {
        Gate::authorize('view', $cart);
        abort_unless($line->cart_id === $cart->id, 404);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $line->delete();

        return response()->noContent();
    }
}
