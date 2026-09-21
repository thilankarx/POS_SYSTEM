<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Actions\SendCartToKitchenAction;
use App\Domain\Sales\Models\Cart;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CartKitchenTicketController extends Controller
{
    public function __invoke(Cart $cart, SendCartToKitchenAction $action): JsonResponse
    {
        Gate::authorize('view', $cart);

        $printError = $action->execute($cart);

        return response()->json([
            'message' => $printError === null ? 'Sent to the kitchen.' : "Sent to the kitchen, but it did not print: {$printError}",
            'print_error' => $printError,
        ]);
    }
}
