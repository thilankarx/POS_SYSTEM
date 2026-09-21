<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Actions\CompleteSaleAction;
use App\Domain\Sales\Actions\CreateOrResumeCartAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\DinnerTable;
use App\Domain\Sales\Models\Terminal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOrResumeCartRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\Http\Resources\Api\V1\SaleResource;
use App\Support\Idempotency\IdempotencyGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['terminal_id' => ['required', 'integer', Rule::exists('terminals', 'id')]]);
        $terminal = Terminal::findOrFail($request->integer('terminal_id'));

        // Rule::exists only proves the terminal exists, not that the caller
        // may operate at its location -- without this, any authenticated
        // terminal could read another location's suspended carts (order
        // contents, payments, customer PII) just by naming that location's
        // terminal id here.
        abort_unless($request->user()->canOperateAt($terminal->stock_location_id), 403);

        $carts = Cart::suspended()
            ->where('stock_location_id', $terminal->stock_location_id)
            ->where('terminal_id', '!=', $terminal->id)
            ->with(['lines.item', 'payments.method', 'customer.person', 'dinnerTable'])
            ->latest('suspended_at')
            ->get();

        return CartResource::collection($carts);
    }

    public function suspend(Request $request, Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $cart->update([
            'status' => Cart::STATUS_SUSPENDED,
            'suspended_by_user_id' => $request->user()->id,
            'suspended_at' => now(),
        ]);

        return new CartResource($cart->load(['lines.item', 'payments.method', 'customer.person', 'dinnerTable']));
    }

    public function abandon(Request $request, Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);
        abort_unless(in_array($cart->status, [Cart::STATUS_ACTIVE, Cart::STATUS_SUSPENDED], true), 422, 'Cart cannot be abandoned.');

        $cart->update([
            'status' => Cart::STATUS_ABANDONED,
            'abandoned_by_user_id' => $request->user()->id,
            'abandoned_at' => now(),
        ]);

        if ($cart->dinner_table_id !== null) {
            DinnerTable::where('id', $cart->dinner_table_id)->update(['status' => DinnerTable::STATUS_AVAILABLE]);
        }

        return new CartResource($cart->load(['lines.item', 'payments.method', 'customer.person', 'dinnerTable']));
    }

    public function store(CreateOrResumeCartRequest $request, CreateOrResumeCartAction $action): CartResource
    {
        $validated = $request->validated();
        $terminal = Terminal::findOrFail($validated['terminal_id']);

        $cart = $action->execute(
            user: $request->user(),
            terminal: $terminal,
            clientUuid: $validated['client_uuid'],
            customerId: $validated['customer_id'] ?? null,
            saleType: $validated['sale_type'] ?? 'pos',
            dinnerTableId: $validated['dinner_table_id'] ?? null,
            reference: $validated['reference'] ?? null,
            comment: $validated['comment'] ?? null,
        );

        return new CartResource($cart->load(['lines.item', 'payments.method', 'customer.person', 'dinnerTable']));
    }

    public function show(Cart $cart): CartResource
    {
        Gate::authorize('view', $cart);

        return new CartResource($cart->load(['lines.item', 'payments.method', 'customer.person', 'dinnerTable']));
    }

    public function complete(Request $request, Cart $cart, IdempotencyGuard $guard, CompleteSaleAction $action): JsonResponse
    {
        Gate::authorize('view', $cart);

        $key = $request->header('Idempotency-Key');
        abort_if(blank($key), 422, 'Idempotency-Key header is required.');

        $result = $guard->handle(
            key: $key,
            endpoint: "carts.{$cart->id}.complete",
            requestHash: hash('sha256', $request->getContent() ?: '{}'),
            userId: $request->user()->id,
            callback: function () use ($cart, $action) {
                $sale = $action->execute($cart);

                return [
                    'status' => 201,
                    'body' => (new SaleResource($sale->load(['lines', 'payments.method', 'taxes'])))
                        ->response()
                        ->getData(true),
                ];
            },
        );

        return response()->json($result['body'], $result['status']);
    }
}
