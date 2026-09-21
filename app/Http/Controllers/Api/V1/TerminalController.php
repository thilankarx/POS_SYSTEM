<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Sales\Models\Terminal;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TerminalResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TerminalController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $locationIds = $request->user()->stockLocations()->pluck('stock_locations.id');

        $terminals = Terminal::whereIn('stock_location_id', $locationIds)
            ->where('is_active', true)
            ->with('stockLocation')
            ->orderBy('name')
            ->get();

        return TerminalResource::collection($terminals);
    }
}
