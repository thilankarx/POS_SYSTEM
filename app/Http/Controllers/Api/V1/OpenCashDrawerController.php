<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Actions\OpenCashDrawerAction;
use App\Domain\Sales\Models\Terminal;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OpenCashDrawerController extends Controller
{
    public function __invoke(Request $request, Terminal $terminal, OpenCashDrawerAction $action): JsonResponse
    {
        $locationIds = $request->user()->stockLocations()->pluck('stock_locations.id');

        abort_unless($locationIds->contains($terminal->stock_location_id), 403);

        $action->execute($terminal);

        return response()->json(['message' => 'Drawer opened.']);
    }
}
