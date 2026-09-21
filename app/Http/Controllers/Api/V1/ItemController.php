<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBarcode;
use App\Domain\Inventory\Models\StockLot;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ItemResource;
use App\Settings\BusinessProfileSettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $items = Item::query()
            ->withHasMultiplePrices()
            ->withCurrentPrice()
            ->active()
            ->forBusinessType(app(BusinessProfileSettings::class)->business_type)
            ->with('barcodes')
            ->when($request->filled('stock_location_id'), fn (Builder $query) => $query->with('stockLevels'))
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhereHas('barcodes', fn (Builder $barcodes) => $barcodes
                        ->where('barcode', 'like', $term)));
            })
            ->orderBy('name')
            ->paginate(max(1, min($request->integer('per_page', 25), 100)));

        return ItemResource::collection($items);
    }

    public function showByBarcode(string $barcode, Request $request): ItemResource
    {
        $match = ItemBarcode::where('barcode', $barcode)
            ->with(['item.barcodes'])
            ->when($request->filled('stock_location_id'), fn ($query) => $query->with('item.stockLevels'))
            ->first();

        $businessType = app(BusinessProfileSettings::class)->business_type;

        abort_if(
            $match === null || ! $match->item->is_active || ! $match->item->soldByBusinessType($businessType),
            404,
            'No matching item for that barcode.',
        );

        return new ItemResource($match->item);
    }

    /**
     * Active lots (with stock on hand) for an item and their effective
     * selling price, so the register can tell whether the cashier needs to
     * be asked which price applies -- only when 2+ in-stock lots actually
     * resolve to different prices. Every stocked item's lot is required to
     * have its own selling_price at receiving now, so there is no item-level
     * fallback left -- a null here means a legacy/unpriced lot that cannot
     * be sold until it's priced.
     */
    public function lotPrices(Item $item): JsonResponse
    {
        $lots = StockLot::activeLotsWithPrices($item);

        return response()->json([
            'data' => $lots->map(fn (StockLot $lot) => [
                'stock_lot_id' => $lot->id,
                'lot_number' => $lot->lot_number,
                'expires_on' => $lot->expires_on?->toDateString(),
                'selling_price' => $lot->selling_price !== null ? (string) $lot->selling_price->getAmount() : null,
            ])->values(),
        ]);
    }
}
