<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Actions\AddCartPaymentAction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\CartPayment;
use App\Domain\Sales\Models\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddCartPaymentRequest;
use App\Http\Resources\Api\V1\CartPaymentResource;
use App\Support\Idempotency\IdempotencyGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CartPaymentController extends Controller
{
    public function store(AddCartPaymentRequest $request, Cart $cart, IdempotencyGuard $guard, AddCartPaymentAction $action): CartPaymentResource|JsonResponse
    {
        Gate::authorize('view', $cart);

        $validated = $request->validated();
        $method = PaymentMethod::findOrFail($validated['payment_method_id']);

        $build = function () use ($cart, $method, $validated, $action) {
            $payment = $action->execute(
                cart: $cart,
                method: $method,
                amount: (string) $validated['amount'],
                tendered: isset($validated['tendered']) ? (string) $validated['tendered'] : null,
                reference: $validated['reference'] ?? null,
            );

            return new CartPaymentResource($payment->load('method'));
        };

        // Optional, unlike complete()'s required key: add_payment has no
        // dedicated retry UI of its own the way checkout does, and making
        // this a hard requirement would break any caller that predates it.
        // The offline register's sync engine (the one actually at risk of
        // replaying this op after a dropped response) always sends one.
        $key = $request->header('Idempotency-Key');

        if (blank($key)) {
            return $build();
        }

        $result = $guard->handle(
            key: $key,
            endpoint: "carts.{$cart->id}.payments.store",
            requestHash: hash('sha256', $request->getContent() ?: '{}'),
            userId: $request->user()->id,
            callback: fn () => ['status' => 201, 'body' => $build()->response()->getData(true)],
        );

        return response()->json($result['body'], $result['status']);
    }

    public function destroy(Cart $cart, CartPayment $payment): Response
    {
        Gate::authorize('view', $cart);
        abort_unless($payment->cart_id === $cart->id, 404);
        abort_unless($cart->status === Cart::STATUS_ACTIVE, 422, 'Cart is not active.');

        $payment->delete();

        return response()->noContent();
    }
}
