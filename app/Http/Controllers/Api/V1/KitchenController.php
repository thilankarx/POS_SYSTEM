<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartLine;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class KitchenController extends Controller
{
    /**
     * The active kitchen queue: every fired-but-not-yet-prepared line,
     * grouped into tickets by cart. This is an aggregated view, not a
     * single Eloquent model per row, so it's built as a plain array rather
     * than forced through a JsonResource.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['stock_location_id' => ['required', 'integer', Rule::exists('stock_locations', 'id')]]);
        abort_unless($request->user()->canOperateAt($request->integer('stock_location_id')), 403);

        $lines = CartLine::query()
            ->whereNotNull('kitchen_sent_at')
            ->whereNull('kitchen_prepared_at')
            ->whereHas('cart', fn ($query) => $query
                ->where('stock_location_id', $request->integer('stock_location_id'))
                ->whereIn('status', [Cart::STATUS_ACTIVE, Cart::STATUS_SUSPENDED]))
            ->with(['item', 'cart.dinnerTable'])
            ->orderBy('kitchen_sent_at')
            ->get()
            ->groupBy('cart_id');

        $tickets = $lines->map(function ($cartLines) {
            $cart = $cartLines->first()->cart;

            return [
                'cart_id' => $cart->id,
                'table' => $cart->dinnerTable ? ['id' => $cart->dinnerTable->id, 'name' => $cart->dinnerTable->name] : null,
                'sent_at' => $cartLines->min('kitchen_sent_at'),
                'lines' => $cartLines->map(fn (CartLine $line) => [
                    'id' => $line->id,
                    'item_name' => $line->item->name,
                    'quantity' => (string) $line->quantity,
                    'description' => $line->description,
                    'sent_at' => $line->kitchen_sent_at,
                ])->values(),
            ];
        })->sortBy('sent_at')->values();

        return response()->json(['data' => $tickets]);
    }

    public function prepareLine(CartLine $line): JsonResponse
    {
        Gate::authorize('view', $line->cart);

        abort_if($line->kitchen_sent_at === null, 422, 'This line has not been sent to the kitchen.');

        $line->update(['kitchen_prepared_at' => now()]);

        return response()->json(['message' => 'Marked prepared.']);
    }
}
