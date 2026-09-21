<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Actions\PrintReceiptAction;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SalePrintController extends Controller
{
    public function __invoke(Sale $sale, PrintReceiptAction $action): JsonResponse
    {
        Gate::authorize('view', $sale);

        $action->execute($sale);

        return response()->json(['message' => 'Receipt sent to the printer.']);
    }
}
