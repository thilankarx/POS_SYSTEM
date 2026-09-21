<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartResource;
use App\Http\Resources\Api\V1\DinnerTableResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DinnerTableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['stock_location_id' => ['required', 'integer', Rule::exists('stock_locations', 'id')]]);
        abort_unless($request->user()->canOperateAt($request->integer('stock_location_id')), 403);

        $tables = DinnerTable::where('stock_location_id', $request->integer('stock_location_id'))
            ->orderBy('name')
            ->get();

        return DinnerTableResource::collection($tables);
    }

    // Only a *suspended* cart is discoverable here -- same restriction
    // listOtherTerminalCarts() already relies on, so a cart still active on
    // another terminal is never silently reattributed to whoever taps the
    // table.
    public function cart(DinnerTable $table): CartResource
    {
        $cart = Cart::where('dinner_table_id', $table->id)
            ->where('status', Cart::STATUS_SUSPENDED)
            ->latest()
            ->firstOrFail();

        // Unlike every other cart endpoint, this one is reached via the
        // table, not the cart id -- easy to reason carefully about the
        // status restriction above and forget the location check every
        // sibling endpoint has. CartPolicy::view is itself location-scoped.
        Gate::authorize('view', $cart);

        return new CartResource($cart->load(['lines.item', 'payments.method', 'customer.person', 'dinnerTable']));
    }
}
