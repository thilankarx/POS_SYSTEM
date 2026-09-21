<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Sales\Actions\AddKitToCartAction;
use App\Domain\Sales\Models\Cart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddCartKitLineRequest;
use App\Http\Resources\Api\V1\CartLineResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CartKitLineController extends Controller
{
    public function store(AddCartKitLineRequest $request, Cart $cart, AddKitToCartAction $action): JsonResponse
    {
        Gate::authorize('view', $cart);

        $validated = $request->validated();
        $kit = ItemKit::findOrFail($validated['item_kit_id']);

        $lines = $action->execute(
            cart: $cart,
            kit: $kit,
            kitQuantity: (string) ($validated['quantity'] ?? '1'),
        );

        return CartLineResource::collection($lines->load('item'))
            ->response()
            ->setStatusCode(201);
    }
}
