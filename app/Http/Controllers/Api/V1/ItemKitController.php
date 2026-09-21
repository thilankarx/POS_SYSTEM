<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Catalog\Models\ItemKit;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ItemKitResource;
use App\Settings\BusinessProfileSettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ItemKitController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $kits = ItemKit::query()
            ->with('items')
            ->forBusinessType(app(BusinessProfileSettings::class)->business_type)
            ->when($request->filled('q'), function (Builder $query) use ($request) {
                $term = '%'.$request->string('q').'%';
                $query->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhere('kit_number', 'like', $term));
            })
            ->orderBy('name')
            ->paginate(max(1, min($request->integer('per_page', 25), 100)));

        return ItemKitResource::collection($kits);
    }
}
